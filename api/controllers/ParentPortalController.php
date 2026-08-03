<?php
declare(strict_types=1);

namespace App\API\Controllers;

use App\API\Services\OTPDeliveryService;
use App\API\Services\payments\MpesaPaymentService;
use App\Database\Database;
use Exception;

/**
 * ParentPortalController
 * Handles all parent-facing portal endpoints.
 * Uses ParentAuthMiddleware instead of staff JWT auth.
 *
 * ROUTES (all under /api/parent-portal/):
 * POST /api/parent-portal/login                    → postLogin()
 * POST /api/parent-portal/login-otp-request        → postLoginOtpRequest()
 * POST /api/parent-portal/login-otp-verify         → postLoginOtpVerify()
 * POST /api/parent-portal/logout                   → postLogout()
 * GET  /api/parent-portal/dashboard                → getDashboard()
 * GET  /api/parent-portal/student-fees/{id}        → getStudentFees($id)
 * GET  /api/parent-portal/student-payment-history/{id} → getStudentPaymentHistory($id)
 * GET  /api/parent-portal/student-statement/{id}   → getStudentStatement($id)
 * GET  /api/parent-portal/fee-balance/{id}         → getFeeBalance($id)
 */
class ParentPortalController extends BaseController
{
    private int $parentId = 0;

    public function __construct()
    {
        parent::__construct();
        // Override: parent_auth instead of auth_user
        $auth = $_SERVER['parent_auth'] ?? null;
        if ($auth) {
            $this->parentId = (int)($auth['parent_id'] ?? 0);
        }
    }

    // ============================================================
    // AUTH ENDPOINTS (no ParentAuthMiddleware required)
    // ============================================================

    /**
     * POST /api/parent-portal/login
     * Body: {email, password}
     */
    public function postLogin($id = null, $data = [], $segments = [])
    {
        $email    = trim($data['email'] ?? '');
        $password = $data['password'] ?? '';

        if (!$email || !$password) {
            return $this->badRequest('Email and password are required');
        }

        try {
            $db     = Database::getInstance();
            $parent = $db->query(
                "SELECT id, first_name, last_name, email, portal_password, portal_status
                 FROM parents
                 WHERE email = :email AND status = 'active' LIMIT 1",
                [':email' => $email]
            )->fetch(\PDO::FETCH_ASSOC);

            if (!$parent) {
                return $this->unauthorized('Invalid email or password');
            }

            // No portal password set yet → account can't log in via password
            if (empty($parent['portal_password'])) {
                return $this->unauthorized('Portal access not yet activated. Use OTP or contact the school.');
            }

            if (!password_verify($password, $parent['portal_password'])) {
                return $this->unauthorized('Invalid email or password');
            }

            if (!empty($parent['portal_status']) && $parent['portal_status'] !== 'active') {
                return $this->forbidden('Your portal account is ' . ($parent['portal_status'] ?? 'inactive'));
            }

            $token = $this->createSession((int)$parent['id']);

            return $this->success([
                'token'      => $token['token'],
                'expires_at' => $token['expires_at'],
                'parent'     => [
                    'id'         => $parent['id'],
                    'first_name' => $parent['first_name'],
                    'last_name'  => $parent['last_name'],
                    'email'      => $parent['email'],
                ],
            ]);
        } catch (Exception $e) {
            return $this->serverError('Login failed');
        }
    }

    /**
     * POST /api/parent-portal/login-otp-request
     * Body: {phone}
     */
    public function postLoginOtpRequest($id = null, $data = [], $segments = [])
    {
        $phone = trim((string)($data['phone'] ?? ''));

        // Normalize to 254XXXXXXXXX
        if (strlen($phone) === 9) $phone = '254' . $phone;
        if (strlen($phone) === 10 && $phone[0] === '0') $phone = '254' . substr($phone, 1);

        try {
            $db     = Database::getInstance();
            $parent = $db->query(
                "SELECT id FROM parents WHERE (phone_1 = :p1 OR phone_2 = :p2) AND status = 'active' LIMIT 1",
                [':p1' => $phone, ':p2' => $phone]
            )->fetch(\PDO::FETCH_ASSOC);

            if (!$parent) {
                // Return success anyway to prevent phone enumeration
                return $this->success(['message' => 'If this number is registered, an OTP will be sent']);
            }

            $otp     = str_pad((string)random_int(100000, 999999), 6, '0', STR_PAD_LEFT);
            $expires = date('Y-m-d H:i:s', strtotime('+10 minutes'));

            $db->query(
                "INSERT INTO parent_otp_sessions (parent_id, phone, otp_code, otp_expires_at)
                 VALUES (:pid, :phone, :otp, :exp)",
                [
                    ':pid'   => $parent['id'],
                    ':phone' => $phone,
                    ':otp'   => password_hash($otp, PASSWORD_DEFAULT),
                    ':exp'   => $expires,
                ]
            );
            $sessionId = $db->lastInsertId();

            // Send OTP via SMS
            try {
                $delivery = new OTPDeliveryService();
                $delivery->sendSMSOTP($phone, $otp, 'setup');
            } catch (\Throwable $e) {
                error_log("[ParentPortal] OTP SMS failed: " . $e->getMessage());
            }

            return $this->success([
                'otp_session_id' => $sessionId,
                'message'        => 'OTP sent to registered phone number',
                'expires_in'     => '10 minutes',
            ]);
        } catch (Exception $e) {
            return $this->serverError('OTP request failed');
        }
    }

    /**
     * POST /api/parent-portal/login-otp-verify
     * Body: {otp_session_id, otp_code}
     */
    public function postLoginOtpVerify($id = null, $data = [], $segments = [])
    {
        $sessionId = (int)($data['otp_session_id'] ?? 0);
        $otpCode   = trim($data['otp_code'] ?? '');

        if (!$sessionId || !$otpCode) {
            return $this->badRequest('otp_session_id and otp_code required');
        }

        try {
            $db      = Database::getInstance();
            $session = $db->query(
                "SELECT * FROM parent_otp_sessions
                 WHERE id = :id AND otp_expires_at > NOW() AND verified = 0 LIMIT 1",
                [':id' => $sessionId]
            )->fetch(\PDO::FETCH_ASSOC);

            if (!$session) {
                return $this->badRequest('OTP session not found or expired');
            }
            if ((int)$session['attempts'] >= 5) {
                return $this->badRequest('Too many failed attempts. Request a new OTP.');
            }

            // Increment attempts
            $db->query(
                "UPDATE parent_otp_sessions SET attempts = attempts + 1 WHERE id = :id",
                [':id' => $sessionId]
            );

            if (!password_verify($otpCode, $session['otp_code'])) {
                return $this->badRequest('Invalid OTP code');
            }

            // Mark verified
            $db->query(
                "UPDATE parent_otp_sessions SET verified = 1 WHERE id = :id",
                [':id' => $sessionId]
            );

            // Get parent info
            $parent = $db->query(
                "SELECT id, first_name, last_name, email FROM parents WHERE id = :id",
                [':id' => $session['parent_id']]
            )->fetch(\PDO::FETCH_ASSOC);

            $token = $this->createSession((int)$session['parent_id']);

            return $this->success([
                'token'      => $token['token'],
                'expires_at' => $token['expires_at'],
                'parent'     => $parent,
            ]);
        } catch (Exception $e) {
            return $this->serverError('OTP verification failed');
        }
    }

    /**
     * POST /api/parent-portal/logout
     */
    public function postLogout($id = null, $data = [], $segments = [])
    {
        $auth = $_SERVER['parent_auth'] ?? null;
        if ($auth) {
            try {
                Database::getInstance()->query(
                    "UPDATE parent_portal_sessions SET status = 'revoked' WHERE id = :id",
                    [':id' => $auth['session_id']]
                );
            } catch (Exception $e) {}
        }
        return $this->success(['message' => 'Logged out successfully']);
    }

    // ============================================================
    // AUTHENTICATED ENDPOINTS (require ParentAuthMiddleware)
    // ============================================================

    /**
     * GET /api/parent-portal/dashboard
     */
    public function getDashboard($id = null, $data = [], $segments = [])
    {
        if (!$this->parentId) return $this->unauthorized('Not authenticated');

        try {
            $db       = Database::getInstance();
            $children = $db->query("
                SELECT s.id, s.first_name, s.last_name, s.admission_no, s.photo_url,
                       c.name AS class_name, sl.name AS level_name,
                       COALESCE(SUM(sfo.balance), 0) AS current_balance,
                       MAX(sfo.payment_status) AS payment_status,
                       (SELECT MAX(pt.payment_date) FROM payment_transactions pt
                        WHERE pt.student_id = s.id AND pt.status = 'confirmed') AS last_payment_date
                FROM student_parents sp
                JOIN students s ON s.id = sp.student_id AND s.status = 'active'
                JOIN class_enrollments ce ON ce.student_id = s.id
                JOIN classes c ON ce.class_id = c.id
                JOIN school_levels sl ON c.level_id = sl.id
                LEFT JOIN student_fee_obligations sfo
                    ON sfo.student_id = s.id
                    AND sfo.academic_year = YEAR(CURDATE())
                WHERE sp.parent_id = :pid
                AND ce.academic_year_id = (SELECT id FROM academic_years WHERE is_current = 1 LIMIT 1)
                GROUP BY s.id
                ORDER BY s.first_name
            ", [':pid' => $this->parentId])->fetchAll(\PDO::FETCH_ASSOC);

            // Get parent info
            $parentInfo = $db->query(
                "SELECT first_name, last_name, email, phone_1 FROM parents WHERE id = :id",
                [':id' => $this->parentId]
            )->fetch(\PDO::FETCH_ASSOC);

            return $this->success([
                'parent'   => $parentInfo,
                'children' => $children,
            ]);
        } catch (Exception $e) {
            error_log('[ParentPortalController] ' . $e->getMessage() . ' in ' . $e->getFile() . ':' . $e->getLine());
return $this->serverError('An internal error occurred.');
        }
    }

    /**
     * GET /api/parent-portal/student-fees/{id}
     */
    public function getStudentFees($id = null, $data = [], $segments = [])
    {
        if (!$this->parentId) return $this->unauthorized('Not authenticated');
        if (!$id) return $this->badRequest('student_id required');
        if (!$this->verifyAccess((int)$id)) return $this->forbidden('Access denied');

        try {
            $db          = Database::getInstance();
            $obligations = $db->query("
                SELECT sfo.*, at.name AS term_name, at.term_number,
                       ft.name AS fee_type_name, ft.code AS fee_type_code
                FROM student_fee_obligations sfo
                JOIN fee_structures_detailed fsd ON sfo.fee_structure_detail_id = fsd.id
                JOIN fee_types ft ON fsd.fee_type_id = ft.id
                JOIN academic_terms at ON sfo.term_id = at.id
                WHERE sfo.student_id = :sid
                ORDER BY sfo.academic_year DESC, at.term_number ASC
            ", [':sid' => $id])->fetchAll(\PDO::FETCH_ASSOC);

            // Group by year → term
            $grouped = [];
            foreach ($obligations as $o) {
                $yr  = $o['academic_year'];
                $tid = $o['term_id'];
                if (!isset($grouped[$yr])) $grouped[$yr] = ['year' => $yr, 'terms' => []];
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
                $grouped[$yr]['terms'][$tid]['total_due']  += $o['amount_due'];
                $grouped[$yr]['terms'][$tid]['total_paid'] += $o['amount_paid'];
                $grouped[$yr]['terms'][$tid]['balance']    += $o['balance'];
            }

            // Re-index
            $result = [];
            foreach ($grouped as $yData) {
                $yData['terms'] = array_values($yData['terms']);
                $result[] = $yData;
            }

            return $this->success(['academic_years' => $result]);
        } catch (Exception $e) {
            return $this->serverError('Failed to load fees');
        }
    }

    /**
     * GET /api/parent-portal/student-payment-history/{id}
     */
    public function getStudentPaymentHistory($id = null, $data = [], $segments = [])
    {
        if (!$this->parentId) return $this->unauthorized('Not authenticated');
        if (!$id) return $this->badRequest('student_id required');
        if (!$this->verifyAccess((int)$id)) return $this->forbidden('Access denied');

        try {
            $db = Database::getInstance();
            $rows = $db->query("
                SELECT pt.*, at.name AS term_name, at.term_number
                FROM payment_transactions pt
                LEFT JOIN academic_terms at ON pt.term_id = at.id
                WHERE pt.student_id = :sid AND pt.status = 'confirmed'
                ORDER BY pt.payment_date DESC
                LIMIT 100
            ", [':sid' => $id])->fetchAll(\PDO::FETCH_ASSOC);

            return $this->success($rows);
        } catch (Exception $e) {
            return $this->serverError('Failed to load payment history');
        }
    }

    /**
     * GET /api/parent-portal/student-statement/{id}
     * Returns fee statement data for printing
     */
    public function getStudentStatement($id = null, $data = [], $segments = [])
    {
        if (!$this->parentId) return $this->unauthorized('Not authenticated');
        if (!$id) return $this->badRequest('student_id required');
        if (!$this->verifyAccess((int)$id)) return $this->forbidden('Access denied');

        try {
            $db = Database::getInstance();

            // Get student info
            $student = $db->query(
                "SELECT s.*, c.name AS class_name
                 FROM students s
                 LEFT JOIN class_enrollments ce ON ce.student_id = s.id
                 LEFT JOIN classes c ON ce.class_id = c.id
                 WHERE s.id = :id
                 ORDER BY ce.academic_year_id DESC LIMIT 1",
                [':id' => $id]
            )->fetch(\PDO::FETCH_ASSOC);

            // Reuse getStudentFees — returns a BaseController response array
            $feesResp = $this->getStudentFees($id, $data, $segments);
            // Extract the academic_years data from the response array
            $feesData = $feesResp['data']['academic_years'] ?? [];

            // Get payments
            $payments = $db->query(
                "SELECT pt.*, at.name AS term_name
                 FROM payment_transactions pt
                 LEFT JOIN academic_terms at ON pt.term_id = at.id
                 WHERE pt.student_id = :sid AND pt.status = 'confirmed'
                 ORDER BY pt.payment_date DESC",
                [':sid' => $id]
            )->fetchAll(\PDO::FETCH_ASSOC);

            // Log download (non-fatal if table missing)
            try {
                Database::getInstance()->query(
                    "INSERT INTO parent_statement_downloads (parent_id, student_id, downloaded_at, ip_address)
                     VALUES (:pid, :sid, NOW(), :ip)",
                    [
                        ':pid' => $this->parentId,
                        ':sid' => $id,
                        ':ip'  => $_SERVER['REMOTE_ADDR'] ?? null,
                    ]
                );
            } catch (Exception $e) {}

            return $this->success([
                'student'      => $student,
                'fees'         => $feesData,
                'payments'     => $payments,
                'generated_at' => date('Y-m-d H:i:s'),
            ]);
        } catch (Exception $e) {
            return $this->serverError('Failed to generate statement');
        }
    }

    /**
     * GET /api/parent-portal/fee-balance/{id}
     */
    public function getFeeBalance($id = null, $data = [], $segments = [])
    {
        if (!$this->parentId) return $this->unauthorized('Not authenticated');
        if (!$id) return $this->badRequest('student_id required');
        if (!$this->verifyAccess((int)$id)) return $this->forbidden('Access denied');

        try {
            $db   = Database::getInstance();
            $rows = $db->query("
                SELECT academic_year, term_id,
                       SUM(amount_due) AS total_due,
                       SUM(amount_paid) AS total_paid,
                       SUM(balance) AS balance,
                       MAX(payment_status) AS payment_status
                FROM student_fee_obligations
                WHERE student_id = :sid
                GROUP BY academic_year, term_id
                ORDER BY academic_year DESC, term_id ASC
            ", [':sid' => $id])->fetchAll(\PDO::FETCH_ASSOC);

            $totalBalance = array_sum(array_column($rows, 'balance'));
            return $this->success(['per_term' => $rows, 'total_balance' => $totalBalance]);
        } catch (Exception $e) {
            return $this->serverError('Failed to load balance');
        }
    }

    /**
     * GET /api/parent-portal/student-attendance/{id}
     */
    public function getStudentAttendance($id = null, $data = [], $segments = [])
    {
        if (!$this->parentId) return $this->unauthorized('Not authenticated');
        if (!$id) return $this->badRequest('student_id required');
        if (!$this->verifyAccess((int)$id)) return $this->forbidden('Access denied');

        try {
            $db = Database::getInstance();

            // Current term
            $term = $db->query(
                "SELECT id, name, term_number, year FROM academic_terms WHERE status = 'current' LIMIT 1"
            )->fetch(\PDO::FETCH_ASSOC);
            $termId = $term ? (int)$term['id'] : 0;

            // Summary for current term
            $summary = $db->query("
                SELECT
                    COUNT(*) AS total_days,
                    SUM(CASE WHEN status = 'present' THEN 1 ELSE 0 END) AS days_present,
                    SUM(CASE WHEN status = 'absent' THEN 1 ELSE 0 END) AS days_absent,
                    SUM(CASE WHEN status = 'late' THEN 1 ELSE 0 END) AS days_late
                FROM student_attendance
                WHERE student_id = :sid AND term_id = :tid
            ", [':sid' => $id, ':tid' => $termId])->fetch(\PDO::FETCH_ASSOC);

            // Recent 30 entries
            $recent = $db->query("
                SELECT date, status, absence_reason
                FROM student_attendance
                WHERE student_id = :sid
                ORDER BY date DESC
                LIMIT 30
            ", [':sid' => $id])->fetchAll(\PDO::FETCH_ASSOC);

            // Monthly breakdown for current year
            $yearId = $term ? (int)($term['year'] ?? 0) : date('Y');
            $monthly = $db->query("
                SELECT
                    DATE_FORMAT(date, '%Y-%m') AS month,
                    COUNT(*) AS total_days,
                    SUM(CASE WHEN status = 'present' THEN 1 ELSE 0 END) AS days_present,
                    SUM(CASE WHEN status = 'absent' THEN 1 ELSE 0 END) AS days_absent,
                    SUM(CASE WHEN status = 'late' THEN 1 ELSE 0 END) AS days_late
                FROM student_attendance sa
                JOIN academic_terms at ON sa.term_id = at.id
                WHERE sa.student_id = :sid AND at.year = :yr
                GROUP BY DATE_FORMAT(date, '%Y-%m')
                ORDER BY month ASC
            ", [':sid' => $id, ':yr' => $yearId])->fetchAll(\PDO::FETCH_ASSOC);

            $present = (int)($summary['days_present'] ?? 0);
            $total   = (int)($summary['total_days'] ?? 0);
            $percentage = $total > 0 ? round(100 * $present / $total, 1) : 0;

            return $this->success([
                'term'       => $term,
                'summary'    => $summary,
                'percentage' => $percentage,
                'recent'     => $recent,
                'monthly'    => $monthly,
            ]);
        } catch (Exception $e) {
            error_log('[ParentPortalController] ' . $e->getMessage() . ' in ' . $e->getFile() . ':' . $e->getLine());
            return $this->serverError('An internal error occurred.');
        }
    }

    /**
     * GET /api/parent-portal/student-performance/{id}
     * Returns report-card-style data: subject scores, competencies, core values.
     */
    public function getStudentPerformance($id = null, $data = [], $segments = [])
    {
        if (!$this->parentId) return $this->unauthorized('Not authenticated');
        if (!$id) return $this->badRequest('student_id required');
        if (!$this->verifyAccess((int)$id)) return $this->forbidden('Access denied');

        try {
            $db = Database::getInstance();

            // Student info
            $student = $db->query("
                SELECT s.id, s.first_name, s.last_name, s.admission_no,
                       c.name AS class_name, cs.stream_name
                FROM students s
                LEFT JOIN class_streams cs ON cs.id = s.stream_id
                LEFT JOIN classes c ON c.id = cs.class_id
                WHERE s.id = :id LIMIT 1
            ", [':id' => $id])->fetch(\PDO::FETCH_ASSOC);
            if (!$student) return $this->notFound('Student not found');

            // Current term
            $term = $db->query(
                "SELECT id, name, term_number, year FROM academic_terms WHERE status = 'current' LIMIT 1"
            )->fetch(\PDO::FETCH_ASSOC);
            $termId = $term ? (int)$term['id'] : 0;

            // Subject scores
            $scores = $db->query("
                SELECT tss.*, la.name AS subject_name, la.code AS subject_code
                FROM term_subject_scores tss
                JOIN learning_areas la ON la.id = tss.subject_id
                WHERE tss.student_id = :sid AND tss.term_id = :tid
                ORDER BY la.name
            ", [':sid' => $id, ':tid' => $termId])->fetchAll(\PDO::FETCH_ASSOC);

            // Competency ratings
            $competencies = $db->query("
                SELECT lc.competency_id, lc.performance_level_id, lc.evidence,
                       lc.teacher_notes AS notes,
                       cc.code, cc.name AS competency_name,
                       plc.code AS level_code, plc.name AS level_name
                FROM learner_competencies lc
                JOIN core_competencies cc ON cc.id = lc.competency_id
                LEFT JOIN performance_levels_cbc plc ON plc.id = lc.performance_level_id
                WHERE lc.student_id = :sid AND lc.term_id = :tid
            ", [':sid' => $id, ':tid' => $termId])->fetchAll(\PDO::FETCH_ASSOC);

            // Core values
            $values = $db->query("
                SELECT sv.value_id, sv.rating, sv.evidence,
                       cv.name AS value_name
                FROM student_core_values sv
                JOIN core_values cv ON cv.id = sv.value_id
                WHERE sv.student_id = :sid AND sv.term_id = :tid
            ", [':sid' => $id, ':tid' => $termId])->fetchAll(\PDO::FETCH_ASSOC);

            // Attendance context
            $attendance = $db->query("
                SELECT
                    COUNT(*) AS total_days,
                    SUM(CASE WHEN status = 'present' THEN 1 ELSE 0 END) AS days_present,
                    SUM(CASE WHEN status = 'absent' THEN 1 ELSE 0 END) AS days_absent,
                    SUM(CASE WHEN status = 'late' THEN 1 ELSE 0 END) AS days_late
                FROM student_attendance
                WHERE student_id = :sid AND term_id = :tid
            ", [':sid' => $id, ':tid' => $termId])->fetch(\PDO::FETCH_ASSOC);

            return $this->success([
                'student'      => $student,
                'term'         => $term,
                'scores'       => $scores,
                'competencies' => $competencies,
                'values'       => $values,
                'attendance'   => $attendance,
            ]);
        } catch (Exception $e) {
            error_log('[ParentPortalController] ' . $e->getMessage() . ' in ' . $e->getFile() . ':' . $e->getLine());
            return $this->serverError('An internal error occurred.');
        }
    }

    /**
     * GET /api/parent-portal/student-report-card/{id}
     * Returns full report card data matching KICD CBC format.
     */
    public function getStudentReportCard($id = null, $data = [], $segments = [])
    {
        if (!$this->parentId) return $this->unauthorized('Not authenticated');
        if (!$id) return $this->badRequest('student_id required');
        if (!$this->verifyAccess((int)$id)) return $this->forbidden('Access denied');

        try {
            $db = Database::getInstance();

            // Student info
            $student = $db->query("
                SELECT s.id, s.first_name, s.last_name, s.admission_no,
                       c.name AS class_name, cs.stream_name,
                       s.gender, s.date_of_birth, s.photo_url
                FROM students s
                LEFT JOIN class_streams cs ON cs.id = s.stream_id
                LEFT JOIN classes c ON c.id = cs.class_id
                WHERE s.id = :id LIMIT 1
            ", [':id' => $id])->fetch(\PDO::FETCH_ASSOC);
            if (!$student) return $this->notFound('Student not found');

            // Current term
            $term = $db->query(
                "SELECT id, name, term_number, year FROM academic_terms WHERE status = 'current' LIMIT 1"
            )->fetch(\PDO::FETCH_ASSOC);
            $termId = $term ? (int)$term['id'] : 0;
            $termName = $term ? ($term['name'] ?? '') : '';
            $termNumber = $term ? (int)$term['term_number'] : 0;
            $year = $term ? (int)$term['year'] : (int)date('Y');

            // Subject scores (term_subject_scores)
            $scores = $db->query("
                SELECT tss.*, la.name AS subject_name, la.code AS subject_code
                FROM term_subject_scores tss
                JOIN learning_areas la ON la.id = tss.subject_id
                WHERE tss.student_id = :sid AND tss.term_id = :tid
                ORDER BY la.name
            ", [':sid' => $id, ':tid' => $termId])->fetchAll(\PDO::FETCH_ASSOC);

            // Competency ratings
            $competencies = $db->query("
                SELECT lc.competency_id, lc.performance_level_id, lc.evidence,
                       lc.teacher_notes AS notes,
                       cc.code, cc.name AS competency_name,
                       plc.code AS level_code, plc.name AS level_name
                FROM learner_competencies lc
                JOIN core_competencies cc ON cc.id = lc.competency_id
                LEFT JOIN performance_levels_cbc plc ON plc.id = lc.performance_level_id
                WHERE lc.student_id = :sid AND lc.term_id = :tid
            ", [':sid' => $id, ':tid' => $termId])->fetchAll(\PDO::FETCH_ASSOC);

            // Core values
            $values = $db->query("
                SELECT sv.value_id, sv.rating, sv.evidence, cv.name AS value_name
                FROM student_core_values sv
                JOIN core_values cv ON cv.id = sv.value_id
                WHERE sv.student_id = :sid AND sv.term_id = :tid
            ", [':sid' => $id, ':tid' => $termId])->fetchAll(\PDO::FETCH_ASSOC);

            // Attendance
            $attendance = $db->query("
                SELECT COUNT(*) AS total_days,
                       SUM(CASE WHEN status='present' THEN 1 ELSE 0 END) AS days_present,
                       SUM(CASE WHEN status='absent' THEN 1 ELSE 0 END) AS days_absent,
                       SUM(CASE WHEN status='late' THEN 1 ELSE 0 END) AS days_late
                FROM student_attendance
                WHERE student_id = :sid AND term_id = :tid
            ", [':sid' => $id, ':tid' => $termId])->fetch(\PDO::FETCH_ASSOC);

            // Teacher & head teacher comments from class_enrollments
            $enrollment = $db->query("
                SELECT ce.teacher_comments, ce.head_teacher_comments,
                       CONCAT_WS(' ', st.first_name, st.last_name) AS class_teacher_name
                FROM class_enrollments ce
                JOIN academic_years ay ON ay.id = ce.academic_year_id AND ay.year_code = :yr
                LEFT JOIN staff_class_assignments sca
                       ON sca.class_id = ce.class_id
                      AND sca.academic_year_id = ce.academic_year_id
                      AND sca.role = 'class_teacher'
                      AND sca.status = 'active'
                LEFT JOIN staff st ON st.id = sca.staff_id
                WHERE ce.student_id = :sid
                LIMIT 1
            ", [':sid' => $id, ':yr' => $year])->fetch(\PDO::FETCH_ASSOC);

            // School info (key/value settings store, pivoted for the frontend)
            $school = $db->query("
                SELECT setting_key, setting_value
                FROM school_settings
                WHERE setting_key IN ('school_name', 'school_address', 'school_phone', 'school_email', 'school_motto')
            ")->fetchAll(\PDO::FETCH_ASSOC);
            $schoolPivoted = [
                'name'    => '',
                'address' => '',
                'phone'   => '',
                'email'   => '',
                'motto'   => '',
                'logo_url' => null,
            ];
            foreach ($school as $row) {
                $map = [
                    'school_name'    => 'name',
                    'school_address' => 'address',
                    'school_phone'   => 'phone',
                    'school_email'   => 'email',
                    'school_motto'   => 'motto',
                ];
                if (isset($map[$row['setting_key']])) {
                    $schoolPivoted[$map[$row['setting_key']]] = $row['setting_value'];
                }
            }

            return $this->success([
                'student'      => $student,
                'term'         => $term,
                'year'         => $year,
                'scores'       => $scores,
                'competencies' => $competencies,
                'values'       => $values,
                'attendance'   => $attendance,
                'enrollment'   => $enrollment,
                'school'       => $schoolPivoted,
                'generated_at' => date('Y-m-d H:i:s'),
            ]);
        } catch (Exception $e) {
            error_log('[ParentPortalController] ' . $e->getMessage() . ' in ' . $e->getFile() . ':' . $e->getLine());
            return $this->serverError('An internal error occurred.');
        }
    }

    /**
     * GET /api/parent-portal/messages/{studentId?}
     * Returns messages between parent and school for a student context.
     */
    public function getMessages($id = null, $data = [], $segments = [])
    {
        if (!$this->parentId) return $this->unauthorized('Not authenticated');

        try {
            $db = Database::getInstance();
            $studentId = $id ? (int)$id : null;
            if ($studentId && !$this->verifyAccess($studentId)) return $this->forbidden('Access denied');

            $where = 'WHERE m.parent_id = :pid';
            $params = [':pid' => $this->parentId];
            if ($studentId) {
                $where .= ' AND m.student_id = :sid';
                $params[':sid'] = $studentId;
            }

            $messages = $db->query("
                SELECT m.*, m.body AS message,
                       CASE WHEN m.sender_type IN ('staff', 'school', 'admin') THEN
                           (SELECT CONCAT_WS(' ', first_name, last_name) FROM staff WHERE id = m.sender_id)
                       ELSE 'You' END AS sender_name
                FROM parent_portal_messages m
                $where
                ORDER BY m.created_at DESC
                LIMIT 100
            ", $params)->fetchAll(\PDO::FETCH_ASSOC);

            return $this->success($messages);
        } catch (Exception $e) {
            error_log('[ParentPortalController] ' . $e->getMessage() . ' in ' . $e->getFile() . ':' . $e->getLine());
            return $this->serverError('An internal error occurred.');
        }
    }

    /**
     * POST /api/parent-portal/send-message
     * Body: {student_id, subject, message}
     */
    public function postSendMessage($id = null, $data = [], $segments = [])
    {
        if (!$this->parentId) return $this->unauthorized('Not authenticated');

        $studentId = (int)($data['student_id'] ?? 0);
        $subject   = trim($data['subject'] ?? '');
        $message   = trim($data['message'] ?? '');
        if (!$studentId || !$subject || !$message) return $this->badRequest('student_id, subject, and message required');
        if (!$this->verifyAccess($studentId)) return $this->forbidden('Access denied');

        try {
            $db = Database::getInstance();
            $db->query("
                INSERT INTO parent_portal_messages
                    (parent_id, student_id, sender_type, sender_id, recipient_type, recipient_id, subject, body, status)
                VALUES (:pid, :sid, 'parent', :pid, 'school', 1, :subj, :msg, 'sent')
            ", [
                ':pid'  => $this->parentId,
                ':sid'  => $studentId,
                ':subj' => $subject,
                ':msg'  => $message,
            ]);

            return $this->success(['message_id' => (int)$db->lastInsertId()], 'Message sent successfully');
        } catch (Exception $e) {
            error_log('[ParentPortalController] ' . $e->getMessage() . ' in ' . $e->getFile() . ':' . $e->getLine());
            return $this->serverError('An internal error occurred.');
        }
    }

    /**
     * GET /api/parent-portal/portfolio/{studentId}
     * Returns portfolio + artifacts for a student.
     */
    public function getPortfolio($id = null, $data = [], $segments = [])
    {
        if (!$this->parentId) return $this->unauthorized('Not authenticated');
        if (!$id) return $this->badRequest('student_id required');
        if (!$this->verifyAccess((int)$id)) return $this->forbidden('Access denied');

        try {
            $db = Database::getInstance();

            $portfolio = $db->query("
                SELECT p.*,
                       (SELECT COUNT(*) FROM portfolio_artifacts WHERE portfolio_id = p.id) AS artifact_count
                FROM portfolios p
                WHERE p.student_id = :sid AND p.status = 'active'
                ORDER BY p.created_date DESC
                LIMIT 1
            ", [':sid' => $id])->fetch(\PDO::FETCH_ASSOC);

            $artifacts = [];
            if ($portfolio) {
                $artifacts = $db->query("
                    SELECT pa.*, cc.name AS competency_name, cv.name AS value_name
                    FROM portfolio_artifacts pa
                    LEFT JOIN core_competencies cc ON cc.id = pa.competency_id
                    LEFT JOIN core_values cv ON cv.id = pa.value_id
                    WHERE pa.portfolio_id = :pid
                    ORDER BY pa.upload_date DESC
                ", [':pid' => $portfolio['id']])->fetchAll(\PDO::FETCH_ASSOC);
            }

            return $this->success([
                'portfolio' => $portfolio,
                'artifacts' => $artifacts,
            ]);
        } catch (Exception $e) {
            error_log('[ParentPortalController] ' . $e->getMessage() . ' in ' . $e->getFile() . ':' . $e->getLine());
            return $this->serverError('An internal error occurred.');
        }
    }

    /**
     * GET /api/parent-portal/grading-scale
     * Returns the active grading scale + grade rules so the parent portal can
     * resolve CBC grades from the database (no hardcoded thresholds).
     */
    public function getGradingScale($id = null, $data = [], $segments = [])
    {
        if (!$this->parentId) return $this->unauthorized('Not authenticated');

        try {
            $db = Database::getInstance();
            $scale = $db->query(
                "SELECT * FROM grading_scales WHERE status='active' ORDER BY id LIMIT 1"
            )->fetch(\PDO::FETCH_ASSOC);
            if (!$scale) return $this->success(['scale' => null, 'rules' => []]);

            $rules = $db->query(
                "SELECT id, grade_code, grade_name, min_mark, max_mark, grade_points, performance_level, description, sort_order
                 FROM grade_rules
                 WHERE scale_id=:sid
                 ORDER BY sort_order, min_mark DESC",
                [':sid' => $scale['id']]
            )->fetchAll(\PDO::FETCH_ASSOC);

            return $this->success(['scale' => $scale, 'rules' => $rules]);
        } catch (Exception $e) {
            error_log('[ParentPortalController] ' . $e->getMessage() . ' in ' . $e->getFile() . ':' . $e->getLine());
            return $this->serverError('An internal error occurred.');
        }
    }

    /**
     * POST /api/parent-portal/initiate-mpesa-payment
     * Body: {student_id, phone? (defaults to parent phone), amount? (defaults to balance)}
     */
    public function postInitiateMpesaPayment($id = null, $data = [], $segments = [])
    {
        if (!$this->parentId) return $this->unauthorized('Not authenticated');

        $studentId = (int)($data['student_id'] ?? 0);
        if (!$studentId) return $this->badRequest('student_id required');
        if (!$this->verifyAccess($studentId)) return $this->forbidden('Access denied');

        try {
            $db = Database::getInstance();

            // Get parent's phone (fallback for phone input)
            $parent = $db->query(
                "SELECT phone_1, phone_2 FROM parents WHERE id = :id",
                [':id' => $this->parentId]
            )->fetch(\PDO::FETCH_ASSOC);

            // Phone: explicit param or parent's primary phone
            $phone = trim($data['phone'] ?? $parent['phone_1'] ?? '');
            // Normalize to 254XXXXXXXXX
            if (strlen($phone) === 9) $phone = '254' . $phone;
            if (strlen($phone) === 10 && $phone[0] === '0') $phone = '254' . substr($phone, 1);
            if (!preg_match('/^254[0-9]{9}$/', $phone)) {
                return $this->badRequest('A valid phone number is required');
            }

            // Get student admission number
            $student = $db->query(
                "SELECT admission_no FROM students WHERE id = :id",
                [':id' => $studentId]
            )->fetch(\PDO::FETCH_ASSOC);
            if (!$student) return $this->badRequest('Student not found');

            // Get current fee balance
            $balanceRow = $db->query("
                SELECT SUM(balance) AS total_balance
                FROM student_fee_obligations
                WHERE student_id = :sid
            ", [':sid' => $studentId])->fetch(\PDO::FETCH_ASSOC);

            $totalBalance = (float)($balanceRow['total_balance'] ?? 0);
            if ($totalBalance <= 0) {
                return $this->badRequest('No outstanding balance to pay');
            }

            // Amount: explicit or full balance
            $amount = (float)($data['amount'] ?? $totalBalance);
            if ($amount <= 0) return $this->badRequest('Amount must be greater than zero');
            if ($amount > $totalBalance) return $this->badRequest('Amount exceeds outstanding balance');

            $mpesa = new MpesaPaymentService();
            $result = $mpesa->initiateSTKPush(
                $student['admission_no'],
                $phone,
                $amount,
                'Parent Portal Payment - ' . $student['admission_no']
            );

            if ($result['success'] ?? false) {
                return $this->success([
                    'checkout_request_id' => $result['data']['checkout_request_id'] ?? null,
                    'merchant_request_id'  => $result['data']['merchant_request_id'] ?? null,
                    'message'             => 'M-Pesa STK Push sent. Check your phone and enter PIN.',
                ]);
            }

            return $this->error($result['message'] ?? 'Failed to initiate M-Pesa payment');
        } catch (Exception $e) {
            error_log('[ParentPortalController] ' . $e->getMessage() . ' in ' . $e->getFile() . ':' . $e->getLine());
            return $this->serverError('An internal error occurred.');
        }
    }

    /**
     * GET /api/parent-portal/mpesa-status/{checkoutRequestId}
     * Poll Safaricom for transaction status after STK Push.
     */
    public function getMpesaStatus($id = null, $data = [], $segments = [])
    {
        if (!$this->parentId) return $this->unauthorized('Not authenticated');
        $checkoutId = $id ?? ($data['checkout_request_id'] ?? '');
        if (!$checkoutId) return $this->badRequest('checkout_request_id required');

        try {
            $mpesa = new MpesaPaymentService();
            $status = $mpesa->queryTransactionStatus($checkoutId);
            // queryTransactionStatus uses formatResponse internally, so extract its data
            $raw = $status['data'] ?? null;
            return $this->success($raw);
        } catch (Exception $e) {
            error_log('[ParentPortalController] ' . $e->getMessage() . ' in ' . $e->getFile() . ':' . $e->getLine());
            return $this->serverError('An internal error occurred.');
        }
    }

    // ============================================================
    // HELPERS
    // ============================================================

    private function createSession(int $parentId): array
    {
        $token   = bin2hex(random_bytes(32));
        $expires = date('Y-m-d H:i:s', strtotime('+7 days'));
        Database::getInstance()->query("
            INSERT INTO parent_portal_sessions
                (parent_id, session_token, issued_at, expires_at, ip_address, user_agent)
            VALUES (:pid, :tok, NOW(), :exp, :ip, :ua)
        ", [
            ':pid' => $parentId,
            ':tok' => $token,
            ':exp' => $expires,
            ':ip'  => $_SERVER['REMOTE_ADDR'] ?? null,
            ':ua'  => substr($_SERVER['HTTP_USER_AGENT'] ?? '', 0, 255),
        ]);
        return ['token' => $token, 'expires_at' => $expires];
    }

    private function verifyAccess(int $studentId): bool
    {
        try {
            $row = Database::getInstance()->query(
                "SELECT id FROM student_parents WHERE parent_id = :pid AND student_id = :sid LIMIT 1",
                [':pid' => $this->parentId, ':sid' => $studentId]
            )->fetch(\PDO::FETCH_ASSOC);
            return !empty($row);
        } catch (Exception $e) {
            return false;
        }
    }
}
