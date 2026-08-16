<?php

namespace App\API\Modules\parent;

use App\API\Includes\BaseAPI;
use App\API\Services\OTPDeliveryService;
use App\API\Services\payments\MpesaPaymentService;
use PDO;
use Exception;

/**
 * ParentPortalManager
 *
 * Owns every data read, session/OTP decision and messaging orchestration for
 * the parent-facing portal so ParentPortalController stays a thin endpoint
 * exposer (no direct DB access, no business decisions).
 *
 * Live-schema mapping (verified against KingsWayAcademy — normalised targets
 * only, never the legacy portal tables):
 *   - `parents.email / phone_1 / phone_2` → `persons` (parents holds only
 *     person_id, occupation, address, status)
 *   - `parents.portal_password / portal_status / portal_last_login`
 *     → `users.password_hash / status / last_login` (account not a person copy)
 *   - `parent_otp_sessions`            → `user_2fa_otp_sessions`
 *   - `parent_portal_sessions`         → `user_sessions`
 *   - `parent_statement_downloads`     → `audit_logs` (append-only download log)
 *   - `parent_portal_messages`         → `internal_messages` + `conversation_participants`
 *     (one deterministic conversation per parent×student, `title` = canonical
 *     `ParentPortal|<parent_id>|<student_id>`; participants are user IDs)
 *   - fee statement/balance data       → `vw_student_fee_balances` /
 *     `vw_student_fee_ledger` / `vw_payment_transactions_with_amount`
 *   - attendance context               → `vw_student_term_attendance_summary`
 *     (+ `student_attendance` via `student_academic_enrollments`)
 *   - `academic_terms`                 → `academic_year_terms` + `terms`
 *   - `class_streams`/`class_enrollments` → `academic_year_class_streams` +
 *     `academic_year_classes` + `classes` + `streams` + `student_academic_enrollments`
 *   - `student_core_values`            → `learner_values_acquisition`
 *   - `fee_structures_detailed`        → `academic_year_fee_schedules` + `fee_catalog`
 *
 * Session token format: opaque 64-hex string stored in `user_sessions.session_token`;
 * the expiry the frontend caches (7 days) is enforced here via `login_time`.
 */
class ParentPortalManager extends BaseAPI
{
    private const SESSION_TTL_DAYS = 7;

    /** @var int */
    private $parentId = 0;

    /** @var int */
    private $sessionId = 0;

    public function __construct()
    {
        parent::__construct('parent_portal');

        $auth = $_SERVER['parent_auth'] ?? null;
        if (is_array($auth)) {
            $this->parentId  = (int)($auth['parent_id'] ?? 0);
            $this->sessionId = (int)($auth['session_id'] ?? 0);
            $this->user_id   = (int)($auth['user_id'] ?? 0) ?: null;
        }
    }

    // ========================================================================
    // AUTH ENDPOINTS (public — no ParentAuthMiddleware)
    // ========================================================================

    /**
     * Email + password login against users.password_hash (normalised account).
     *
     * @param array $data {email, password}
     * @return array
     */
    public function postLogin(array $data): array
    {
        $email    = trim((string)($data['email'] ?? ''));
        $password = (string)($data['password'] ?? '');

        if ($email === '' || $password === '') {
            return $this->errorResponse('Email and password are required', 400);
        }

        try {
            $stmt = $this->db->prepare(
                "SELECT u.id AS user_id, pr.id AS parent_id,
                        p.first_name, p.last_name, p.email,
                        u.password_hash, u.status AS user_status
                 FROM users u
                 JOIN persons p ON p.id = u.person_id
                 JOIN parents pr ON pr.person_id = u.person_id
                 WHERE p.email = :email AND pr.status = 'active'
                 LIMIT 1"
            );
            $stmt->execute([':email' => $email]);
            $parent = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$parent) {
                return $this->errorResponse('Invalid email or password', 401);
            }

            // No portal password set yet → account can't log in via password
            if (empty($parent['password_hash'])) {
                return $this->errorResponse('Portal access not yet activated. Use OTP or contact the school.', 401);
            }

            if (!password_verify($password, $parent['password_hash'])) {
                return $this->errorResponse('Invalid email or password', 401);
            }

            if (!empty($parent['user_status']) && $parent['user_status'] !== 'active') {
                return $this->errorResponse('Your portal account is ' . $parent['user_status'], 403);
            }

            $session = $this->createSession((int)$parent['user_id']);

            return $this->successResponse([
                'token'      => $session['token'],
                'expires_at' => $session['expires_at'],
                'parent'     => [
                    'id'         => (int)$parent['parent_id'],
                    'first_name' => $parent['first_name'],
                    'last_name'  => $parent['last_name'],
                    'email'      => $parent['email'],
                ],
            ], 'Login successful');
        } catch (Exception $e) {
            error_log('[ParentPortalManager] ' . $e->getMessage() . ' in ' . $e->getFile() . ':' . $e->getLine());
            return $this->errorResponse('Login failed', 500);
        }
    }

    /**
     * SMS OTP request. Anti-enumeration: returns success even for unknown numbers.
     *
     * @param array $data {phone}
     * @return array
     */
    public function postLoginOtpRequest(array $data): array
    {
        $phone = trim((string)($data['phone'] ?? ''));

        // Normalize to 254XXXXXXXXX
        if (strlen($phone) === 9) $phone = '254' . $phone;
        if (strlen($phone) === 10 && $phone[0] === '0') $phone = '254' . substr($phone, 1);

        try {
            $stmt = $this->db->prepare(
                "SELECT u.id AS user_id, p.phone
                 FROM users u
                 JOIN persons p ON p.id = u.person_id
                 JOIN parents pr ON pr.person_id = u.person_id
                 WHERE p.phone = :phone AND pr.status = 'active'
                 LIMIT 1"
            );
            $stmt->execute([':phone' => $phone]);
            $parent = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$parent) {
                // Return success anyway to prevent phone enumeration
                return $this->successResponse(['message' => 'If this number is registered, an OTP will be sent']);
            }

            $otp     = str_pad((string)random_int(100000, 999999), 6, '0', STR_PAD_LEFT);
            $expires = date('Y-m-d H:i:s', strtotime('+10 minutes'));
            $ip      = $_SERVER['REMOTE_ADDR'] ?? null;

            // Invalidate any previous unverified login OTPs for this user
            $this->db->prepare(
                "UPDATE user_2fa_otp_sessions
                 SET verified = 1
                 WHERE user_id = ? AND otp_type = 'login' AND verified = 0"
            )->execute([(int)$parent['user_id']]);

            $ins = $this->db->prepare(
                "INSERT INTO user_2fa_otp_sessions
                    (user_id, otp_code, otp_type, method, otp_expires_at, ip_address)
                 VALUES (?, ?, 'login', 'sms', ?, ?)"
            );
            $ins->execute([
                (int)$parent['user_id'],
                password_hash($otp, PASSWORD_DEFAULT),
                $expires,
                $ip,
            ]);
            $sessionId = (int)$this->db->lastInsertId();

            // Send OTP via SMS (non-fatal on delivery failure)
            try {
                $delivery = new OTPDeliveryService();
                $delivery->sendSMSOTP($phone, $otp, 'login');
            } catch (\Throwable $e) {
                error_log('[ParentPortal] OTP SMS failed: ' . $e->getMessage());
            }

            return $this->successResponse([
                'otp_session_id' => $sessionId,
                'message'        => 'OTP sent to registered phone number',
                'expires_in'     => '10 minutes',
            ]);
        } catch (Exception $e) {
            error_log('[ParentPortalManager] ' . $e->getMessage() . ' in ' . $e->getFile() . ':' . $e->getLine());
            return $this->errorResponse('OTP request failed', 500);
        }
    }

    /**
     * Verify the SMS OTP and issue a session token.
     *
     * @param array $data {otp_session_id, otp_code}
     * @return array
     */
    public function postLoginOtpVerify(array $data): array
    {
        $sessionId = (int)($data['otp_session_id'] ?? 0);
        $otpCode   = trim((string)($data['otp_code'] ?? ''));

        if (!$sessionId || $otpCode === '') {
            return $this->errorResponse('otp_session_id and otp_code required', 400);
        }

        try {
            $stmt = $this->db->prepare(
                "SELECT id, user_id, otp_code, attempts
                 FROM user_2fa_otp_sessions
                 WHERE id = :id AND otp_expires_at > NOW() AND verified = 0
                 LIMIT 1"
            );
            $stmt->execute([':id' => $sessionId]);
            $session = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$session) {
                return $this->errorResponse('OTP session not found or expired', 400);
            }
            if ((int)$session['attempts'] >= 5) {
                $this->db->prepare(
                    "UPDATE user_2fa_otp_sessions SET verified = 1 WHERE id = ?"
                )->execute([$sessionId]);
                return $this->errorResponse('Too many failed attempts. Request a new OTP.', 400);
            }

            // Increment attempts
            $this->db->prepare(
                "UPDATE user_2fa_otp_sessions SET attempts = attempts + 1 WHERE id = ?"
            )->execute([$sessionId]);

            if (!password_verify($otpCode, $session['otp_code'])) {
                return $this->errorResponse('Invalid OTP code', 400);
            }

            // Mark verified
            $this->db->prepare(
                "UPDATE user_2fa_otp_sessions SET verified = 1 WHERE id = ?"
            )->execute([$sessionId]);

            $parent = $this->getParentByUserId((int)$session['user_id']);
            if (!$parent) {
                return $this->errorResponse('Parent account not found', 404);
            }

            $session = $this->createSession((int)$parent['user_id']);

            return $this->successResponse([
                'token'      => $session['token'],
                'expires_at' => $session['expires_at'],
                'parent'     => [
                    'id'         => (int)$parent['parent_id'],
                    'first_name' => $parent['first_name'],
                    'last_name'  => $parent['last_name'],
                    'email'      => $parent['email'],
                ],
            ], 'Login successful');
        } catch (Exception $e) {
            error_log('[ParentPortalManager] ' . $e->getMessage() . ' in ' . $e->getFile() . ':' . $e->getLine());
            return $this->errorResponse('OTP verification failed', 500);
        }
    }

    /**
     * Revoke the active portal session.
     *
     * @return array
     */
    public function postLogout(): array
    {
        if ($this->sessionId) {
            try {
                $this->db->prepare(
                    "UPDATE user_sessions
                     SET session_status = 'logged_out', logout_time = NOW()
                     WHERE id = ?"
                )->execute([$this->sessionId]);
            } catch (Exception $e) {
                error_log('[ParentPortalManager] ' . $e->getMessage() . ' in ' . $e->getFile() . ':' . $e->getLine());
            }
        }
        return $this->successResponse(['message' => 'Logged out successfully']);
    }

    // ========================================================================
    // AUTHENTICATED ENDPOINTS (require ParentAuthMiddleware)
    // ========================================================================

    /**
     * Dashboard: parent profile + children with current fee balance.
     *
     * @return array
     */
    public function getDashboard(): array
    {
        if (!$this->parentId) {
            return $this->errorResponse('Not authenticated', 401);
        }

        try {
            $children = [];
            $stmt = $this->db->prepare(
                "SELECT s.id, ps.first_name, ps.last_name, s.admission_no, s.status,
                        c.name AS class_name, sl.name AS level_name,
                        COALESCE((SELECT SUM(fb.balance) FROM vw_student_fee_balances fb
                                  WHERE fb.student_id = s.id), 0) AS current_balance,
                        (SELECT MAX(pt.payment_date) FROM vw_payment_transactions_with_amount pt
                         WHERE pt.student_id = s.id
                           AND pt.status IN ('confirmed','completed','success')) AS last_payment_date
                 FROM student_parents sp
                 JOIN students s ON s.id = sp.student_id AND s.status = 'active'
                 JOIN persons ps ON ps.id = s.person_id
                 LEFT JOIN student_academic_enrollments sae
                        ON sae.student_id = s.id AND sae.enrollment_status = 'active'
                 LEFT JOIN academic_year_class_streams aycs ON aycs.id = sae.academic_year_class_stream_id
                 LEFT JOIN academic_year_classes ayc ON ayc.id = aycs.academic_year_class_id
                 LEFT JOIN classes c ON c.id = ayc.class_id
                 LEFT JOIN school_levels sl ON sl.id = c.level_id
                 WHERE sp.parent_id = :pid
                 ORDER BY ps.first_name, ps.last_name"
            );
            $stmt->execute([':pid' => $this->parentId]);
            $children = $stmt->fetchAll(PDO::FETCH_ASSOC);

            $parentInfo = $this->getParentProfile($this->parentId);

            return $this->successResponse([
                'parent'   => $parentInfo,
                'children' => $children,
            ]);
        } catch (Exception $e) {
            error_log('[ParentPortalManager] ' . $e->getMessage() . ' in ' . $e->getFile() . ':' . $e->getLine());
            return $this->errorResponse('An internal error occurred.', 500);
        }
    }

    /**
     * Fee obligations grouped by academic year → term (per-fee-type breakdown).
     *
     * @param int $studentId
     * @return array
     */
    public function getStudentFees(int $studentId): array
    {
        $access = $this->assertAccess($studentId);
        if ($access !== null) {
            return $access;
        }

        try {
            $data = $this->buildStudentFeesData($studentId);
            return $this->successResponse(['academic_years' => $data]);
        } catch (Exception $e) {
            error_log('[ParentPortalManager] ' . $e->getMessage() . ' in ' . $e->getFile() . ':' . $e->getLine());
            return $this->errorResponse('Failed to load fees', 500);
        }
    }

    /**
     * Confirmed payment history (latest 100).
     *
     * @param int $studentId
     * @return array
     */
    public function getStudentPaymentHistory(int $studentId): array
    {
        $access = $this->assertAccess($studentId);
        if ($access !== null) {
            return $access;
        }

        try {
            $rows = $this->fetchPaymentHistory($studentId);
            return $this->successResponse($rows);
        } catch (Exception $e) {
            error_log('[ParentPortalManager] ' . $e->getMessage() . ' in ' . $e->getFile() . ':' . $e->getLine());
            return $this->errorResponse('Failed to load payment history', 500);
        }
    }

    /**
     * Printable fee statement: student info + fees + payments + download log.
     *
     * @param int $studentId
     * @return array
     */
    public function getStudentStatement(int $studentId): array
    {
        $access = $this->assertAccess($studentId);
        if ($access !== null) {
            return $access;
        }

        try {
            $student = $this->getStudentInfo($studentId);
            $fees    = $this->buildStudentFeesData($studentId);
            $payments = $this->fetchPaymentHistory($studentId);

            // Append-only download log (parent_statement_downloads → audit_logs)
            $this->logStatementDownload($studentId);

            return $this->successResponse([
                'student'      => $student,
                'fees'         => ['academic_years' => $fees],
                'payments'     => $payments,
                'generated_at' => date('Y-m-d H:i:s'),
            ]);
        } catch (Exception $e) {
            error_log('[ParentPortalManager] ' . $e->getMessage() . ' in ' . $e->getFile() . ':' . $e->getLine());
            return $this->errorResponse('Failed to generate statement', 500);
        }
    }

    /**
     * Per-term balance summary + running total.
     *
     * @param int $studentId
     * @return array
     */
    public function getFeeBalance(int $studentId): array
    {
        $access = $this->assertAccess($studentId);
        if ($access !== null) {
            return $access;
        }

        try {
            $stmt = $this->db->prepare(
                "SELECT academic_year, term_id,
                        SUM(amount_due) AS total_due,
                        SUM(amount_paid) AS total_paid,
                        SUM(balance) AS balance,
                        MAX(payment_status) AS payment_status
                 FROM vw_student_fee_balances
                 WHERE student_id = :sid
                 GROUP BY academic_year, term_id
                 ORDER BY academic_year DESC, term_id ASC"
            );
            $stmt->execute([':sid' => $studentId]);
            $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

            $totalBalance = array_sum(array_map(function ($r) {
                return (float)($r['balance'] ?? 0);
            }, $rows));

            return $this->successResponse(['per_term' => $rows, 'total_balance' => $totalBalance]);
        } catch (Exception $e) {
            error_log('[ParentPortalManager] ' . $e->getMessage() . ' in ' . $e->getFile() . ':' . $e->getLine());
            return $this->errorResponse('Failed to load balance', 500);
        }
    }

    /**
     * Attendance summary, recent entries and monthly breakdown.
     *
     * @param int $studentId
     * @return array
     */
    public function getStudentAttendance(int $studentId): array
    {
        $access = $this->assertAccess($studentId);
        if ($access !== null) {
            return $access;
        }

        try {
            $term = $this->getCurrentTerm();
            $termId = $term ? (int)$term['id'] : 0;

            // Summary for current term (class register)
            $summary = [
                'total_days'   => 0,
                'days_present' => 0,
                'days_absent'  => 0,
                'days_late'    => 0,
            ];
            if ($termId) {
                $stmt = $this->db->prepare(
                    "SELECT COALESCE(SUM(class_days_marked),0)   AS total_days,
                            COALESCE(SUM(class_days_present),0)  AS days_present,
                            COALESCE(SUM(class_days_absent),0)   AS days_absent,
                            COALESCE(SUM(class_days_late),0)     AS days_late
                     FROM vw_student_term_attendance_summary
                     WHERE student_id = :sid AND term_id = :tid AND register_type = 'class'"
                );
                $stmt->execute([':sid' => $studentId, ':tid' => $termId]);
                $summary = $stmt->fetch(PDO::FETCH_ASSOC);
                if (!$summary) {
                    $summary = ['total_days' => 0, 'days_present' => 0, 'days_absent' => 0, 'days_late' => 0];
                }
            }

            // Recent 30 entries
            $stmt = $this->db->prepare(
                "SELECT sa.date, sa.status, sa.absence_reason
                 FROM student_attendance sa
                 JOIN student_academic_enrollments sae ON sae.id = sa.student_academic_enrollment_id
                 WHERE sae.student_id = :sid
                 ORDER BY sa.date DESC, sa.id DESC
                 LIMIT 30"
            );
            $stmt->execute([':sid' => $studentId]);
            $recent = $stmt->fetchAll(PDO::FETCH_ASSOC);

            // Monthly breakdown for current year
            $yearBounds = $this->getCurrentYearBounds();
            $stmt = $this->db->prepare(
                "SELECT DATE_FORMAT(sa.date, '%Y-%m') AS month,
                        COUNT(*) AS total_days,
                        SUM(CASE WHEN sa.status = 'present' THEN 1 ELSE 0 END) AS days_present,
                        SUM(CASE WHEN sa.status = 'absent' THEN 1 ELSE 0 END) AS days_absent,
                        SUM(CASE WHEN sa.status = 'late' THEN 1 ELSE 0 END) AS days_late
                 FROM student_attendance sa
                 JOIN student_academic_enrollments sae ON sae.id = sa.student_academic_enrollment_id
                 WHERE sae.student_id = :sid AND sa.date BETWEEN :start AND :end
                 GROUP BY DATE_FORMAT(sa.date, '%Y-%m')
                 ORDER BY month ASC"
            );
            $stmt->execute([
                ':sid'   => $studentId,
                ':start' => $yearBounds['start'],
                ':end'   => $yearBounds['end'],
            ]);
            $monthly = $stmt->fetchAll(PDO::FETCH_ASSOC);

            $present = (int)($summary['days_present'] ?? 0);
            $total   = (int)($summary['total_days'] ?? 0);
            $percentage = $total > 0 ? round(100 * $present / $total, 1) : 0;

            return $this->successResponse([
                'term'       => $term,
                'summary'    => $summary,
                'percentage' => $percentage,
                'recent'     => $recent,
                'monthly'    => $monthly,
            ]);
        } catch (Exception $e) {
            error_log('[ParentPortalManager] ' . $e->getMessage() . ' in ' . $e->getFile() . ':' . $e->getLine());
            return $this->errorResponse('An internal error occurred.', 500);
        }
    }

    /**
     * Report-card-style performance data: subject scores, competencies, values.
     *
     * @param int $studentId
     * @return array
     */
    public function getStudentPerformance(int $studentId): array
    {
        $access = $this->assertAccess($studentId);
        if ($access !== null) {
            return $access;
        }

        return $this->buildPerformancePayload($studentId, false);
    }

    /**
     * Full KICD-CBC report card data.
     *
     * @param int $studentId
     * @return array
     */
    public function getStudentReportCard(int $studentId): array
    {
        $access = $this->assertAccess($studentId);
        if ($access !== null) {
            return $access;
        }

        return $this->buildPerformancePayload($studentId, true);
    }

    /**
     * Messages between the parent and the school, scoped to a student.
     *
     * @param int|null $studentId
     * @return array
     */
    public function getMessages(?int $studentId): array
    {
        if (!$this->parentId) {
            return $this->errorResponse('Not authenticated', 401);
        }
        if ($studentId && $this->assertAccess($studentId) !== null) {
            return $this->errorResponse('Access denied', 403);
        }

        try {
            $conversation = $studentId
                ? $this->findConversation($studentId)
                : null;

            $messages = [];
            if ($conversation) {
                $stmt = $this->db->prepare(
                    "SELECT im.id, im.conversation_id, im.sender_id, im.subject,
                            im.message_body AS message, im.status, im.created_at,
                            CASE WHEN im.sender_id = :pid THEN 'parent' ELSE 'staff' END AS sender_type,
                            CASE WHEN im.sender_id = :pid THEN 'You'
                                 ELSE (SELECT CONCAT_WS(' ', sp.first_name, sp.last_name)
                                       FROM users su JOIN persons sp ON sp.id = su.person_id
                                       WHERE su.id = im.sender_id)
                            END AS sender_name
                     FROM internal_messages im
                     JOIN internal_conversations c ON c.id = im.conversation_id
                     WHERE c.id = :cid
                     ORDER BY im.created_at DESC
                     LIMIT 100"
                );
                $stmt->execute([':pid' => $this->user_id, ':cid' => (int)$conversation['id']]);
                $messages = $stmt->fetchAll(PDO::FETCH_ASSOC);

                $this->markMessagesRead((int)$conversation['id']);
            }

            return $this->successResponse($messages);
        } catch (Exception $e) {
            error_log('[ParentPortalManager] ' . $e->getMessage() . ' in ' . $e->getFile() . ':' . $e->getLine());
            return $this->errorResponse('An internal error occurred.', 500);
        }
    }

    /**
     * Send a parent→school message in the student's conversation.
     *
     * @param array $data {student_id, subject, message}
     * @return array
     */
    public function postSendMessage(array $data): array
    {
        if (!$this->parentId) {
            return $this->errorResponse('Not authenticated', 401);
        }

        $studentId = (int)($data['student_id'] ?? 0);
        $subject   = trim((string)($data['subject'] ?? ''));
        $message   = trim((string)($data['message'] ?? ''));

        if (!$studentId || $subject === '' || $message === '') {
            return $this->errorResponse('student_id, subject, and message required', 400);
        }
        if ($this->assertAccess($studentId) !== null) {
            return $this->errorResponse('Access denied', 403);
        }

        try {
            $conversation = $this->getOrCreateConversation($studentId);
            $schoolUser   = $this->resolveSchoolUser($studentId);

            $this->ensureParticipant((int)$conversation['id'], (int)$this->user_id);
            if ($schoolUser) {
                $this->ensureParticipant((int)$conversation['id'], (int)$schoolUser);
            }

            $ins = $this->db->prepare(
                "INSERT INTO internal_messages
                    (conversation_id, sender_id, subject, message_body, message_type, priority, status)
                 VALUES (?, ?, ?, ?, 'personal', 'normal', 'sent')"
            );
            $ins->execute([
                (int)$conversation['id'],
                (int)$this->user_id,
                $subject,
                $message,
            ]);
            $messageId = (int)$this->db->lastInsertId();

            $this->db->prepare(
                "UPDATE internal_conversations
                 SET last_message_at = NOW(), last_message_by = ?
                 WHERE id = ?"
            )->execute([(int)$this->user_id, (int)$conversation['id']]);

            // Unread counter for the school participant (parent reads their own)
            if ($schoolUser) {
                $this->db->prepare(
                    "UPDATE conversation_participants
                     SET unread_count = unread_count + 1
                     WHERE conversation_id = ? AND participant_id = ?"
                )->execute([(int)$conversation['id'], (int)$schoolUser]);

                $this->db->prepare(
                    "INSERT IGNORE INTO message_read_status (message_id, recipient_id)
                     VALUES (?, ?)"
                )->execute([$messageId, (int)$this->user_id]);
            }

            return $this->successResponse(['message_id' => $messageId], 'Message sent successfully', 201);
        } catch (Exception $e) {
            error_log('[ParentPortalManager] ' . $e->getMessage() . ' in ' . $e->getFile() . ':' . $e->getLine());
            return $this->errorResponse('An internal error occurred.', 500);
        }
    }

    /**
     * Student portfolio + artifacts.
     *
     * @param int $studentId
     * @return array
     */
    public function getPortfolio(int $studentId): array
    {
        $access = $this->assertAccess($studentId);
        if ($access !== null) {
            return $access;
        }

        try {
            $stmt = $this->db->prepare(
                "SELECT p.*,
                        (SELECT COUNT(*) FROM portfolio_artifacts WHERE portfolio_id = p.id) AS artifact_count
                 FROM portfolios p
                 WHERE p.student_id = :sid AND p.status = 'active'
                 ORDER BY p.created_date DESC
                 LIMIT 1"
            );
            $stmt->execute([':sid' => $studentId]);
            $portfolio = $stmt->fetch(PDO::FETCH_ASSOC);

            $artifacts = [];
            if ($portfolio) {
                $stmt = $this->db->prepare(
                    "SELECT pa.*, cc.name AS competency_name, cv.name AS value_name
                     FROM portfolio_artifacts pa
                     LEFT JOIN core_competencies cc ON cc.id = pa.competency_id
                     LEFT JOIN core_values cv ON cv.id = pa.value_id
                     WHERE pa.portfolio_id = :pid
                     ORDER BY pa.upload_date DESC"
                );
                $stmt->execute([':pid' => (int)$portfolio['id']]);
                $artifacts = $stmt->fetchAll(PDO::FETCH_ASSOC);
            }

            return $this->successResponse([
                'portfolio' => $portfolio,
                'artifacts' => $artifacts,
            ]);
        } catch (Exception $e) {
            error_log('[ParentPortalManager] ' . $e->getMessage() . ' in ' . $e->getFile() . ':' . $e->getLine());
            return $this->errorResponse('An internal error occurred.', 500);
        }
    }

    /**
     * Active grading scale + rules (no hardcoded CBC thresholds).
     *
     * @return array
     */
    public function getGradingScale(): array
    {
        if (!$this->parentId) {
            return $this->errorResponse('Not authenticated', 401);
        }

        try {
            $stmt = $this->db->prepare(
                "SELECT * FROM grading_scales WHERE status = 'active' ORDER BY id LIMIT 1"
            );
            $stmt->execute();
            $scale = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$scale) {
                return $this->successResponse(['scale' => null, 'rules' => []]);
            }

            $stmt = $this->db->prepare(
                "SELECT id, grade_code, grade_name, min_mark, max_mark, grade_points,
                        performance_level, description, sort_order
                 FROM grade_rules
                 WHERE scale_id = :sid
                 ORDER BY sort_order, min_mark DESC"
            );
            $stmt->execute([':sid' => (int)$scale['id']]);
            $rules = $stmt->fetchAll(PDO::FETCH_ASSOC);

            return $this->successResponse(['scale' => $scale, 'rules' => $rules]);
        } catch (Exception $e) {
            error_log('[ParentPortalManager] ' . $e->getMessage() . ' in ' . $e->getFile() . ':' . $e->getLine());
            return $this->errorResponse('An internal error occurred.', 500);
        }
    }

    /**
     * Initiate an M-Pesa STK Push for the student's outstanding balance.
     *
     * @param array $data {student_id, phone?, amount?}
     * @return array
     */
    public function postInitiateMpesaPayment(array $data): array
    {
        if (!$this->parentId) {
            return $this->errorResponse('Not authenticated', 401);
        }

        $studentId = (int)($data['student_id'] ?? 0);
        if (!$studentId) {
            return $this->errorResponse('student_id required', 400);
        }
        if ($this->assertAccess($studentId) !== null) {
            return $this->errorResponse('Access denied', 403);
        }

        try {
            // Get parent's phone (fallback for phone input)
            $parent = $this->getParentProfile($this->parentId);

            // Phone: explicit param or parent's primary phone
            $phone = trim((string)($data['phone'] ?? $parent['phone'] ?? ''));
            // Normalize to 254XXXXXXXXX
            if (strlen($phone) === 9) $phone = '254' . $phone;
            if (strlen($phone) === 10 && $phone[0] === '0') $phone = '254' . substr($phone, 1);
            if (!preg_match('/^254[0-9]{9}$/', $phone)) {
                return $this->errorResponse('A valid phone number is required', 400);
            }

            // Get student admission number
            $stmt = $this->db->prepare(
                "SELECT admission_no FROM students WHERE id = :id"
            );
            $stmt->execute([':id' => $studentId]);
            $student = $stmt->fetch(PDO::FETCH_ASSOC);
            if (!$student) {
                return $this->errorResponse('Student not found', 400);
            }

            // Get current fee balance
            $stmt = $this->db->prepare(
                "SELECT COALESCE(SUM(balance), 0) AS total_balance
                 FROM vw_student_fee_balances
                 WHERE student_id = :sid"
            );
            $stmt->execute([':sid' => $studentId]);
            $totalBalance = (float)($stmt->fetch(PDO::FETCH_ASSOC)['total_balance'] ?? 0);
            if ($totalBalance <= 0) {
                return $this->errorResponse('No outstanding balance to pay', 400);
            }

            // Amount: explicit or full balance
            $amount = (float)($data['amount'] ?? $totalBalance);
            if ($amount <= 0) {
                return $this->errorResponse('Amount must be greater than zero', 400);
            }
            if ($amount > $totalBalance) {
                return $this->errorResponse('Amount exceeds outstanding balance', 400);
            }

            $mpesa = new MpesaPaymentService();
            $result = $mpesa->initiateSTKPush(
                $student['admission_no'],
                $phone,
                $amount,
                'Parent Portal Payment - ' . $student['admission_no']
            );

            if (!empty($result['success'])) {
                $resultData = $result['data'] ?? [];
                return $this->successResponse([
                    'checkout_request_id' => $resultData['checkout_request_id'] ?? null,
                    'merchant_request_id'  => $resultData['merchant_request_id'] ?? null,
                    'message'             => 'M-Pesa STK Push sent. Check your phone and enter PIN.',
                ]);
            }

            return $this->errorResponse($result['message'] ?? 'Failed to initiate M-Pesa payment', 400);
        } catch (Exception $e) {
            error_log('[ParentPortalManager] ' . $e->getMessage() . ' in ' . $e->getFile() . ':' . $e->getLine());
            return $this->errorResponse('An internal error occurred.', 500);
        }
    }

    /**
     * Poll Safaricom for the transaction status of an STK Push.
     *
     * @param string $checkoutRequestId
     * @return array
     */
    public function getMpesaStatus(string $checkoutRequestId): array
    {
        if (!$this->parentId) {
            return $this->errorResponse('Not authenticated', 401);
        }

        try {
            $mpesa = new MpesaPaymentService();
            $status = $mpesa->queryTransactionStatus($checkoutRequestId);
            $raw = $status['data'] ?? null;

            return $this->successResponse($raw);
        } catch (Exception $e) {
            error_log('[ParentPortalManager] ' . $e->getMessage() . ' in ' . $e->getFile() . ':' . $e->getLine());
            return $this->errorResponse('An internal error occurred.', 500);
        }
    }

    // ========================================================================
    // HELPERS
    // ========================================================================

    /**
     * Create an opaque portal session in user_sessions and touch users.last_login.
     *
     * @param int $userId
     * @return array {token, expires_at}
     */
    private function createSession(int $userId): array
    {
        $token   = bin2hex(random_bytes(32));
        $expires = date('Y-m-d H:i:s', strtotime('+' . self::SESSION_TTL_DAYS . ' days'));

        $this->db->prepare(
            "INSERT INTO user_sessions
                (user_id, session_token, ip_address, user_agent, login_time, last_activity, session_status)
             VALUES (?, ?, ?, ?, NOW(), NOW(), 'active')"
        )->execute([
            $userId,
            $token,
            $_SERVER['REMOTE_ADDR'] ?? null,
            substr($_SERVER['HTTP_USER_AGENT'] ?? '', 0, 255),
        ]);

        $this->db->prepare(
            "UPDATE users SET last_login = NOW() WHERE id = ?"
        )->execute([$userId]);

        return ['token' => $token, 'expires_at' => $expires];
    }

    /**
     * Parent identity resolved through users → persons → parents (normalised).
     *
     * @param int $userId
     * @return array|null
     */
    private function getParentByUserId(int $userId): ?array
    {
        $stmt = $this->db->prepare(
            "SELECT u.id AS user_id, pr.id AS parent_id,
                    p.first_name, p.last_name, p.email
             FROM users u
             JOIN persons p ON p.id = u.person_id
             JOIN parents pr ON pr.person_id = u.person_id
             WHERE u.id = :uid
             LIMIT 1"
        );
        $stmt->execute([':uid' => $userId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    /**
     * Parent profile for the dashboard / M-Pesa phone fallback.
     *
     * @param int $parentId
     * @return array
     */
    private function getParentProfile(int $parentId): array
    {
        $stmt = $this->db->prepare(
            "SELECT p.first_name, p.last_name, p.email, p.phone
             FROM parents pr
             JOIN persons p ON p.id = pr.person_id
             WHERE pr.id = :pid"
        );
        $stmt->execute([':pid' => $parentId]);
        return $stmt->fetch(PDO::FETCH_ASSOC) ?: [];
    }

    /**
     * Access guard: does this parent have a link to the student?
     *
     * @param int $studentId
     * @return bool
     */
    private function verifyAccess(int $studentId): bool
    {
        try {
            $stmt = $this->db->prepare(
                "SELECT student_id FROM student_parents
                 WHERE parent_id = :pid AND student_id = :sid
                 LIMIT 1"
            );
            $stmt->execute([':pid' => $this->parentId, ':sid' => $studentId]);
            return (bool)$stmt->fetchColumn();
        } catch (Exception $e) {
            return false;
        }
    }

    /**
     * Returns an errorResponse array when access is denied, else null.
     *
     * @param int $studentId
     * @return array|null
     */
    private function assertAccess(int $studentId): ?array
    {
        if (!$this->parentId) {
            return $this->errorResponse('Not authenticated', 401);
        }
        if (!$this->verifyAccess($studentId)) {
            return $this->errorResponse('Access denied', 403);
        }
        return null;
    }

    /**
     * Current term context from academic_year_terms + terms + academic_years.
     *
     * @return array|null
     */
    private function getCurrentTerm(): ?array
    {
        $stmt = $this->db->prepare(
            "SELECT t.id, t.name, t.code AS term_number, ay.year_code AS year
             FROM academic_year_terms ayt
             JOIN terms t ON t.id = ayt.term_id
             JOIN academic_years ay ON ay.id = ayt.academic_year_id
             WHERE ayt.status = 'current'
             LIMIT 1"
        );
        $stmt->execute();
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    /**
     * Calendar bounds for the current academic year (fallback to calendar year).
     *
     * @return array {start, end}
     */
    private function getCurrentYearBounds(): array
    {
        $stmt = $this->db->prepare(
            "SELECT start_date, end_date FROM academic_years WHERE is_current = 1 LIMIT 1"
        );
        $stmt->execute();
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        return [
            'start' => $row['start_date'] ?? (date('Y') . '-01-01'),
            'end'   => $row['end_date'] ?? (date('Y') . '-12-31'),
        ];
    }

    /**
     * Student info (names on persons, current class/stream via active enrollment).
     *
     * @param int $studentId
     * @return array|null
     */
    private function getStudentInfo(int $studentId): ?array
    {
        $stmt = $this->db->prepare(
            "SELECT s.id, ps.first_name, ps.last_name, ps.middle_name,
                    s.admission_no, s.status, ps.gender, ps.dob, ps.photo_url,
                    c.name AS class_name, sn.name AS stream_name
             FROM students s
             JOIN persons ps ON ps.id = s.person_id
             LEFT JOIN student_academic_enrollments sae
                    ON sae.student_id = s.id AND sae.enrollment_status = 'active'
             LEFT JOIN academic_year_class_streams aycs ON aycs.id = sae.academic_year_class_stream_id
             LEFT JOIN academic_year_classes ayc ON ayc.id = aycs.academic_year_class_id
             LEFT JOIN classes c ON c.id = ayc.class_id
             LEFT JOIN streams sn ON sn.id = aycs.stream_id
             WHERE s.id = :id
             LIMIT 1"
        );
        $stmt->execute([':id' => $studentId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    /**
     * Fee obligations (per fee type) grouped by year → term, with term-level
     * paid/balance totals from vw_student_fee_balances.
     *
     * @param int $studentId
     * @return array
     */
    private function buildStudentFeesData(int $studentId): array
    {
        $stmt = $this->db->prepare(
            "SELECT sfo.id, sfo.student_academic_enrollment_id, sfo.academic_year_id,
                    sfo.academic_year_term_id, sfo.academic_year_fee_schedule_id,
                    sfo.amount_due, sfo.status AS obligation_status, sfo.due_date,
                    sfo.is_sponsored, sfo.sponsored_waiver_amount,
                    ay.year_code AS academic_year, t.id AS term_id, t.name AS term_name,
                    t.code AS term_number, fc.name AS fee_type_name, fc.code AS fee_type_code
             FROM student_fee_obligations sfo
             JOIN student_academic_enrollments sae ON sae.id = sfo.student_academic_enrollment_id
             JOIN academic_years ay ON ay.id = sfo.academic_year_id
             JOIN academic_year_terms ayt ON ayt.id = sfo.academic_year_term_id
             JOIN terms t ON t.id = ayt.term_id
             JOIN academic_year_fee_schedules ayfs ON ayfs.id = sfo.academic_year_fee_schedule_id
             JOIN fee_catalog fc ON fc.id = ayfs.fee_catalog_id
             WHERE sae.student_id = :sid
             ORDER BY ay.year_code DESC, t.id ASC"
        );
        $stmt->execute([':sid' => $studentId]);
        $obligations = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // Term-level paid/balance/waived from the ledger view
        $stmt = $this->db->prepare(
            "SELECT academic_year_term_id,
                    SUM(amount_due)   AS total_due,
                    SUM(amount_waived) AS total_waived,
                    SUM(amount_paid)  AS total_paid,
                    SUM(balance)      AS total_balance
             FROM vw_student_fee_balances
             WHERE student_id = :sid
             GROUP BY academic_year_term_id"
        );
        $stmt->execute([':sid' => $studentId]);
        $termTotals = [];
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $t) {
            $termTotals[(int)$t['academic_year_term_id']] = [
                'due'   => (float)($t['total_due'] ?? 0),
                'paid'  => (float)($t['total_paid'] ?? 0),
                'bal'   => (float)($t['total_balance'] ?? 0),
            ];
        }

        // Group obligations by year → term
        $grouped = [];
        foreach ($obligations as $o) {
            $yr  = $o['academic_year'];
            $tid = (int)$o['term_id'];

            if (!isset($grouped[$yr])) {
                $grouped[$yr] = ['year' => $yr, 'terms' => []];
            }
            if (!isset($grouped[$yr]['terms'][$tid])) {
                $grouped[$yr]['terms'][$tid] = [
                    'term_id'      => $tid,
                    'term_name'    => $o['term_name'],
                    'term_number'  => $o['term_number'],
                    'obligations'  => [],
                    'total_due'    => 0,
                    'total_paid'   => 0,
                    'balance'      => 0,
                ];
            }
            $grouped[$yr]['terms'][$tid]['obligations'][] = $o;
        }

        // Allocate term-level paid/balance across obligations proportionally
        foreach ($grouped as &$yData) {
            foreach ($yData['terms'] as &$term) {
                $aytId = null;
                $termDue = 0;
                foreach ($term['obligations'] as $o) {
                    $aytId = (int)$o['academic_year_term_id'];
                    $termDue += (float)$o['amount_due'];
                }

                $paid = 0;
                $bal  = 0;
                if ($aytId !== null && isset($termTotals[$aytId])) {
                    $paid = $termTotals[$aytId]['paid'];
                    $bal  = $termTotals[$aytId]['bal'];
                }

                foreach ($term['obligations'] as &$o) {
                    $amountDue = (float)$o['amount_due'];
                    $rowPaid   = $termDue > 0 ? round($paid * ($amountDue / $termDue), 2) : 0;
                    $waived    = (float)($o['sponsored_waiver_amount'] ?? 0);
                    $rowBal    = max($amountDue - $waived - $rowPaid, 0);

                    $o['amount_paid'] = $rowPaid;
                    $o['balance']     = $rowBal;
                    $o['payment_status'] = $rowBal <= 0 ? 'paid' : ($rowPaid > 0 ? 'partial' : 'pending');
                }
                unset($o);

                $term['total_due']  = $termDue;
                $term['total_paid'] = $paid;
                $term['balance']    = $bal;
            }
            unset($term);
            $yData['terms'] = array_values($yData['terms']);
        }
        unset($yData);

        return array_values($grouped);
    }

    /**
     * Confirmed payment history rows.
     *
     * @param int $studentId
     * @return array
     */
    private function fetchPaymentHistory(int $studentId): array
    {
        $stmt = $this->db->prepare(
            "SELECT vp.id, vp.payment_date, vp.payment_method, vp.amount_paid,
                    vp.receipt_no, vp.reference_no, vp.term_id, vp.status,
                    t.name AS term_name
             FROM vw_payment_transactions_with_amount vp
             LEFT JOIN academic_year_terms ayt ON ayt.id = vp.term_id
             LEFT JOIN terms t ON t.id = ayt.term_id
             WHERE vp.student_id = :sid AND vp.status IN ('confirmed','completed','success')
             ORDER BY vp.payment_date DESC
             LIMIT 100"
        );
        $stmt->execute([':sid' => $studentId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Append-only statement download log (parent_statement_downloads → audit_logs).
     *
     * @param int $studentId
     * @return void
     */
    private function logStatementDownload(int $studentId): void
    {
        try {
            \App\API\Includes\FileLogger::write('audit', [
                'type' => 'audit',
                'action' => 'parent_statement_download',
                'entity' => 'student',
                'entity_id' => $studentId,
                'user_id' => $this->user_id,
                'ip' => $_SERVER['REMOTE_ADDR'] ?? null,
                'user_agent' => substr($_SERVER['HTTP_USER_AGENT'] ?? '', 0, 255),
                'details' => ['parent_id' => $this->parentId, 'student_id' => $studentId],
                'status' => 'success',
            ]);
        } catch (Exception $e) {
            error_log('[ParentPortalManager] ' . $e->getMessage() . ' in ' . $e->getFile() . ':' . $e->getLine());
        }
    }

    /**
     * Performance/report-card payload builder shared by the two endpoints.
     *
     * @param int  $studentId
     * @param bool $reportCard include school/enrollment context
     * @return array
     */
    private function buildPerformancePayload(int $studentId, bool $reportCard): array
    {
        try {
            $student = $this->getStudentInfo($studentId);
            if (!$student) {
                return $this->errorResponse('Student not found', 404);
            }

            $term   = $this->getCurrentTerm();
            $termId = $term ? (int)$term['id'] : 0;

            // Subject scores
            $scores = [];
            if ($termId) {
                $stmt = $this->db->prepare(
                    "SELECT tss.*, la.name AS subject_name, la.code AS subject_code
                     FROM term_subject_scores tss
                     JOIN learning_areas la ON la.id = tss.subject_id
                     WHERE tss.student_id = :sid AND tss.term_id = :tid
                     ORDER BY la.name"
                );
                $stmt->execute([':sid' => $studentId, ':tid' => $termId]);
                $scores = $stmt->fetchAll(PDO::FETCH_ASSOC);
            }

            // Competency ratings
            $competencies = [];
            if ($termId) {
                $stmt = $this->db->prepare(
                    "SELECT lc.competency_id, lc.performance_level_id, lc.evidence,
                            lc.teacher_notes AS notes,
                            cc.code, cc.name AS competency_name,
                            plc.code AS level_code, plc.name AS level_name
                     FROM learner_competencies lc
                     JOIN core_competencies cc ON cc.id = lc.competency_id
                     LEFT JOIN performance_levels_cbc plc ON plc.id = lc.performance_level_id
                     WHERE lc.student_id = :sid AND lc.term_id = :tid"
                );
                $stmt->execute([':sid' => $studentId, ':tid' => $termId]);
                $competencies = $stmt->fetchAll(PDO::FETCH_ASSOC);
            }

            // Core values (learner_values_acquisition carries evidence only)
            $values = [];
            if ($termId) {
                $stmt = $this->db->prepare(
                    "SELECT lva.value_id, cv.name AS value_name, lva.evidence
                     FROM learner_values_acquisition lva
                     JOIN core_values cv ON cv.id = lva.value_id
                     WHERE lva.student_id = :sid AND lva.term_id = :tid"
                );
                $stmt->execute([':sid' => $studentId, ':tid' => $termId]);
                $values = $stmt->fetchAll(PDO::FETCH_ASSOC);
            }

            // Attendance context (class register)
            $attendance = ['total_days' => 0, 'days_present' => 0, 'days_absent' => 0, 'days_late' => 0];
            if ($termId) {
                $stmt = $this->db->prepare(
                    "SELECT COALESCE(SUM(class_days_marked),0)   AS total_days,
                            COALESCE(SUM(class_days_present),0)  AS days_present,
                            COALESCE(SUM(class_days_absent),0)   AS days_absent,
                            COALESCE(SUM(class_days_late),0)     AS days_late
                     FROM vw_student_term_attendance_summary
                     WHERE student_id = :sid AND term_id = :tid AND register_type = 'class'"
                );
                $stmt->execute([':sid' => $studentId, ':tid' => $termId]);
                $attendance = $stmt->fetch(PDO::FETCH_ASSOC) ?: $attendance;
            }

            $payload = [
                'student'      => $student,
                'term'         => $term,
                'scores'       => $scores,
                'competencies' => $competencies,
                'values'       => $values,
                'attendance'   => $attendance,
            ];

            if ($reportCard) {
                $year = $term ? (int)($term['year'] ?? 0) : (int)date('Y');

                // Class teacher name via active stream; comment fields are not
                // stored in the normalised schema (student_academic_enrollments
                // carries no comment columns) — left null for the frontend.
                $enrollment = ['teacher_comments' => null, 'head_teacher_comments' => null, 'class_teacher_name' => null];
                $teacher = $this->getClassTeacher($studentId);
                if ($teacher) {
                    $enrollment['class_teacher_name'] = $teacher;
                }

                $payload['enrollment'] = $enrollment;
                $payload['school']     = $this->getSchoolSettings();
                $payload['year']       = $year;
                $payload['generated_at'] = date('Y-m-d H:i:s');
            }

            return $this->successResponse($payload);
        } catch (Exception $e) {
            error_log('[ParentPortalManager] ' . $e->getMessage() . ' in ' . $e->getFile() . ':' . $e->getLine());
            return $this->errorResponse('An internal error occurred.', 500);
        }
    }

    /**
     * Class teacher full name from the active stream's class_teacher_id.
     *
     * @param int $studentId
     * @return string|null
     */
    private function getClassTeacher(int $studentId): ?string
    {
        $stmt = $this->db->prepare(
            "SELECT CONCAT_WS(' ', p.first_name, p.last_name) AS teacher_name
             FROM student_academic_enrollments sae
             JOIN academic_year_class_streams aycs ON aycs.id = sae.academic_year_class_stream_id
             LEFT JOIN staff st ON st.id = aycs.class_teacher_id
             LEFT JOIN persons p ON p.id = st.person_id
             WHERE sae.student_id = :sid AND sae.enrollment_status = 'active'
             LIMIT 1"
        );
        $stmt->execute([':sid' => $studentId]);
        $name = $stmt->fetchColumn();
        return $name ? (string)$name : null;
    }

    /**
     * School header settings pivoted for the report card.
     *
     * @return array
     */
    private function getSchoolSettings(): array
    {
        $pivoted = [
            'name'     => '',
            'address'  => '',
            'phone'    => '',
            'email'    => '',
            'motto'    => '',
            'logo_url' => null,
        ];

        try {
            $stmt = $this->db->prepare(
                "SELECT setting_key, setting_value
                 FROM school_settings
                 WHERE setting_key IN ('school_name', 'school_address', 'school_phone', 'school_email', 'school_motto')"
            );
            $stmt->execute();
            $map = [
                'school_name'    => 'name',
                'school_address' => 'address',
                'school_phone'   => 'phone',
                'school_email'   => 'email',
                'school_motto'   => 'motto',
            ];
            foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
                if (isset($map[$row['setting_key']])) {
                    $pivoted[$map[$row['setting_key']]] = $row['setting_value'];
                }
            }
        } catch (Exception $e) {
            error_log('[ParentPortalManager] ' . $e->getMessage() . ' in ' . $e->getFile() . ':' . $e->getLine());
        }

        return $pivoted;
    }

    /**
     * Canonical conversation title for a parent×student pair.
     *
     * @param int $studentId
     * @return string
     */
    private function conversationTitle(int $studentId): string
    {
        return 'ParentPortal|' . $this->parentId . '|' . $studentId;
    }

    /**
     * Find an existing conversation for this parent×student pair.
     *
     * @param int $studentId
     * @return array|null
     */
    private function findConversation(int $studentId): ?array
    {
        $stmt = $this->db->prepare(
            "SELECT id FROM internal_conversations WHERE title = :title LIMIT 1"
        );
        $stmt->execute([':title' => $this->conversationTitle($studentId)]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    /**
     * Get (or lazily create) the parent×student conversation.
     *
     * @param int $studentId
     * @return array
     */
    private function getOrCreateConversation(int $studentId): array
    {
        $existing = $this->findConversation($studentId);
        if ($existing) {
            return $existing;
        }

        $this->db->prepare(
            "INSERT INTO internal_conversations
                (title, conversation_type, created_by, is_locked, last_message_at, participant_count)
             VALUES (?, 'one_on_one', ?, 0, NOW(), 0)"
        )->execute([
            $this->conversationTitle($studentId),
            (int)$this->user_id,
        ]);

        $id = (int)$this->db->lastInsertId();

        // Parent participant
        $this->ensureParticipant($id, (int)$this->user_id);

        return ['id' => $id];
    }

    /**
     * Ensure a participant row exists for a conversation.
     *
     * @param int $conversationId
     * @param int $participantId
     * @return void
     */
    private function ensureParticipant(int $conversationId, int $participantId): void
    {
        $this->db->prepare(
            "INSERT INTO conversation_participants (conversation_id, participant_id, role)
             VALUES (?, ?, 'participant')
             ON DUPLICATE KEY UPDATE conversation_id = VALUES(conversation_id)"
        )->execute([$conversationId, $participantId]);

        $this->db->prepare(
            "UPDATE internal_conversations
             SET participant_count = (SELECT COUNT(*) FROM conversation_participants WHERE conversation_id = ?)
             WHERE id = ?"
        )->execute([$conversationId, $conversationId]);
    }

    /**
     * Resolve a school-side recipient user for the parent's messages: the
     * student's class teacher when they hold a user account, else any active
     * admin/super_admin/administrator user, else the parent user itself.
     *
     * @param int $studentId
     * @return int|null
     */
    private function resolveSchoolUser(int $studentId): ?int
    {
        // Class teacher account first
        $stmt = $this->db->prepare(
            "SELECT u.id
             FROM student_academic_enrollments sae
             JOIN academic_year_class_streams aycs ON aycs.id = sae.academic_year_class_stream_id
             JOIN staff st ON st.id = aycs.class_teacher_id
             JOIN users u ON u.person_id = st.person_id
             WHERE sae.student_id = :sid AND sae.enrollment_status = 'active' AND u.status = 'active'
             LIMIT 1"
        );
        $stmt->execute([':sid' => $studentId]);
        $id = $stmt->fetchColumn();
        if ($id) {
            return (int)$id;
        }

        // Any active admin account
        $stmt = $this->db->prepare(
            "SELECT u.id
             FROM users u
             JOIN user_roles ur ON ur.user_id = u.id
             JOIN roles r ON r.id = ur.role_id
             WHERE u.status = 'active'
               AND (r.name = 'admin' OR r.name = 'super_admin' OR r.name = 'administrator')
             ORDER BY r.id
             LIMIT 1"
        );
        $stmt->execute();
        $id = $stmt->fetchColumn();
        if ($id) {
            return (int)$id;
        }

        return (int)$this->user_id;
    }

    /**
     * Mark school→parent messages as read by the parent and reset unread count.
     *
     * @param int $conversationId
     * @return void
     */
    private function markMessagesRead(int $conversationId): void
    {
        try {
            $this->db->prepare(
                "UPDATE conversation_participants
                 SET unread_count = 0
                 WHERE conversation_id = ? AND participant_id = ?"
            )->execute([$conversationId, (int)$this->user_id]);

            $this->db->prepare(
                "INSERT IGNORE INTO message_read_status (message_id, recipient_id, read_at, created_at)
                 SELECT im.id, ?, NOW(), NOW()
                 FROM internal_messages im
                 WHERE im.conversation_id = ? AND im.sender_id <> ?
                   AND im.id NOT IN (SELECT mrs.message_id FROM message_read_status mrs
                                     WHERE mrs.recipient_id = ?)"
            )->execute([(int)$this->user_id, $conversationId, (int)$this->user_id, (int)$this->user_id]);
        } catch (Exception $e) {
            error_log('[ParentPortalManager] ' . $e->getMessage() . ' in ' . $e->getFile() . ':' . $e->getLine());
        }
    }
}
