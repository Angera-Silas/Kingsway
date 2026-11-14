<?php
namespace App\API\Services;

use App\Database\Database;
use PDO;

/**
 * Central completed-year write check for every API write.
 *
 * Rule (user, 2026-11-14): a completed academic year is readable by every
 * user; school admin, system admin and director may change it, and so may
 * any user the system administrator grants
 * AcademicYearLifecycleService::EDIT_COMPLETED_PERMISSION. Upcoming and
 * current years are unaffected.
 *
 * ControllerRouter calls check() for POST/PUT/PATCH/DELETE after the
 * controller method is resolved and before it runs. The target year is
 * resolved only from keys that name year data unambiguously:
 *
 *   academic_year_id, year_id                    -> the year itself
 *   academic_year_term_id, year_term_id          -> academic_year_terms
 *   assessment_id                                -> assessments
 *   assessment_paper_id, paper_id                -> assessment_papers -> assessments
 *   exam_period_id                               -> exam_periods
 *   exam_schedule_id                             -> exam_schedules
 *   academic_year_class_id                       -> academic_year_classes
 *   academic_year_class_stream_id                -> academic_year_class_streams
 *   student_academic_enrollment_id               -> student_academic_enrollments
 *   date, attendance_date (attendance only)      -> the year whose dates contain it
 *
 * `term_id` alone (1/2/3) is never used: it repeats every year.
 *
 * Keys are read at the top level and inside lists of rows (one level of
 * nesting, e.g. {assessment_id, marks:[...]} or {results:[{...}]}). A write
 * that names no year key passes; the controller's own rules still apply.
 *
 * Not checked:
 *   - read-style POSTs (reports, exports, prints, previews, searches, AI drafts
 *     and queues, calculations), so viewing a completed year never breaks;
 *   - year lifecycle and rollover routes (create/set-current/transition/
 *     promotion/fee rollover); they read the outgoing year by design, are
 *     gated by their own permissions, and the transition workflow guards its
 *     target year itself;
 *   - payments and receipts: money received today against brought-forward
 *     balances must always post;
 *   - controllers that hold no academic-year data (auth, users, system, website,
 *     communications, dashboards, reports, print, payments, commerce, ...).
 */
final class YearWriteScopeService
{
    private const WRITE_METHODS = ['POST', 'PUT', 'PATCH', 'DELETE'];

    private const EXEMPT_CONTROLLERS = [
        'auth', 'users', 'system', 'system-config', 'systemconfig', 'school-config', 'schoolconfig',
        'website', 'public', 'communications', 'dashboard', 'reports', 'print', 'payments', 'ipn',
        'commerce', 'maintenance', 'uploads', 'node', 'scan', 'data-quality', 'staff-migration',
        'mcp', 'ai', 'analytics', 'documents', 'parent-portal', 'inventory', 'catering',
    ];

    /**
     * Controller method names that only read or compute (no stored change),
     * queue AI work (an approval that saves the draft is still checked), run
     * year lifecycle/promotion/rollover, or move money. Approving, recording,
     * distributing and saving are always checked.
     */
    private const EXEMPT_METHOD_PATTERN =
        '/(Export|Print[A-Z]?|Preview|Search|Query|Analy[sz]eResults|Summary$|Download|Lookup|Validate|'
        . 'Check[A-Z]|Calculate|Compare|Stats|Nlq|Queue$|GenerateReports|GenerateStudentReports|CompileData|'
        . 'Promot|Rollover|Payment|Mpesa|Receipt|Refund|Reconcil)'
        . '|^(post|put|patch|delete)(AcademicYear|Years|YearRollover|YearTransition)/';

    private const ROW_LIMIT = 500;

    /**
     * Writes addressed by a record id in the URL (PUT /assessments/12, ...):
     * controller => [method-name pattern => record type]. The id's record is
     * looked up to find its year, so editing or deleting a completed year's
     * record by id is checked like any other write.
     */
    private const ID_RECORDS = [
        'academic' => [
            '/^(put|patch|delete)(FormativeAssessments|SummativeAssessments|Assessments?)$/' => 'assessment',
            '/^post(Approve|Reject)Assessment$/' => 'assessment',
            '/^(post|put|patch|delete)ExamPeriods(Publish|Reopen|Restore|OpenResults|PublishResults|Timetable)?$/' => 'exam_period',
            '/^(put|patch|delete)ExamSchedule$/' => 'exam_schedule',
        ],
    ];

    private PDO $db;

    /** @var array<string,int> lookup cache "table:id" -> academic_years.id (0 = none) */
    private array $cache = [];

    public function __construct(?PDO $db = null)
    {
        $this->db = $db ?: Database::getInstance()->getConnection();
    }

    /**
     * @return array|null null when the write may proceed, otherwise a 403
     *         response body with the guard's plain-language reason.
     */
    public function check(string $httpMethod, string $controller, string $methodName, $id, $data): ?array
    {
        if (!$this->appliesTo($httpMethod, $controller, $methodName)) {
            return null;
        }

        $yearIds = $this->resolveYearIds(strtolower($controller), is_array($data) ? $data : []);
        $idYear = $this->yearOfUrlRecord(strtolower($controller), $methodName, $id);
        if ($idYear > 0 && !in_array($idYear, $yearIds, true)) {
            $yearIds[] = $idYear;
        }
        if (!$yearIds) {
            return null;
        }

        $userId = (int) ($_SERVER['auth_user']['user_id'] ?? $_SERVER['auth_user']['id'] ?? 0);
        $roles = $this->roleNames($userId);
        foreach ($yearIds as $yearId) {
            $guard = CompletedYearWriteGuard::check($yearId, $roles, $this->db, $userId > 0 ? $userId : null);
            if (!$guard['allowed'] && $guard['year'] !== null) {
                http_response_code(403);
                return [
                    'success' => false,
                    'status' => 'error',
                    'data' => ['academic_year_id' => $yearId, 'year_editable' => false],
                    'message' => $guard['reason'],
                    'errors' => [],
                    'code' => 403,
                ];
            }
        }
        return null;
    }

    public function appliesTo(string $httpMethod, string $controller, string $methodName): bool
    {
        if (!in_array(strtoupper($httpMethod), self::WRITE_METHODS, true)) return false;
        if (in_array(strtolower($controller), self::EXEMPT_CONTROLLERS, true)) return false;
        return preg_match(self::EXEMPT_METHOD_PATTERN, $methodName) !== 1;
    }

    /** @return int[] distinct academic_years.id values named by the payload */
    public function resolveYearIds(string $controller, array $data): array
    {
        $years = [];
        $rows = [$data];
        foreach ($data as $value) {
            if (is_array($value) && array_is_list($value)) {
                foreach (array_slice($value, 0, self::ROW_LIMIT) as $row) {
                    if (is_array($row)) $rows[] = $row;
                }
            }
        }

        foreach ($rows as $row) {
            foreach ($this->yearsInRow($controller, $row) as $yearId) {
                if ($yearId > 0) $years[$yearId] = true;
            }
        }
        return array_map('intval', array_keys($years));
    }

    /** @return int[] */
    private function yearsInRow(string $controller, array $row): array
    {
        $found = [];
        foreach (['academic_year_id', 'year_id'] as $key) {
            if ($v = $this->positiveInt($row[$key] ?? null)) $found[] = $v;
        }
        foreach (['academic_year_term_id', 'year_term_id'] as $key) {
            if ($v = $this->positiveInt($row[$key] ?? null)) $found[] = $this->yearOfTerm($v);
        }
        if ($v = $this->positiveInt($row['assessment_id'] ?? null)) $found[] = $this->yearOfAssessment($v);
        foreach (['assessment_paper_id', 'paper_id'] as $key) {
            if ($v = $this->positiveInt($row[$key] ?? null)) {
                $found[] = $this->yearOfAssessment($this->lookup('assessment_papers', 'assessment_id', $v));
            }
        }
        if ($v = $this->positiveInt($row['exam_period_id'] ?? null)) {
            $found[] = $this->yearOfTerm($this->lookup('exam_periods', 'academic_year_term_id', $v));
        }
        if ($v = $this->positiveInt($row['exam_schedule_id'] ?? null)) {
            $found[] = $this->yearOfTerm($this->lookup('exam_schedules', 'academic_year_term_id', $v));
        }
        if ($v = $this->positiveInt($row['academic_year_class_id'] ?? null)) {
            $found[] = $this->lookup('academic_year_classes', 'academic_year_id', $v);
        }
        if ($v = $this->positiveInt($row['academic_year_class_stream_id'] ?? null)) {
            $found[] = $this->lookup(
                'academic_year_classes',
                'academic_year_id',
                $this->lookup('academic_year_class_streams', 'academic_year_class_id', $v)
            );
        }
        if ($v = $this->positiveInt($row['student_academic_enrollment_id'] ?? null)) {
            $found[] = $this->lookup('student_academic_enrollments', 'academic_year_id', $v);
        }
        if ($controller === 'attendance') {
            foreach (['attendance_date', 'date'] as $key) {
                $date = (string) ($row[$key] ?? '');
                if (preg_match('/^\d{4}-\d{2}-\d{2}/', $date)) $found[] = $this->yearOfDate(substr($date, 0, 10));
            }
        }
        return $found;
    }

    /** Year of the record a write names by URL id, when the method is known. */
    public function yearOfUrlRecord(string $controller, string $methodName, $id): int
    {
        $recordId = $this->positiveInt($id);
        if ($recordId <= 0) return 0;
        foreach (self::ID_RECORDS[$controller] ?? [] as $pattern => $type) {
            if (preg_match($pattern, $methodName) !== 1) continue;
            return match ($type) {
                'assessment' => $this->yearOfAssessment($recordId),
                'exam_period' => $this->yearOfTerm($this->lookup('exam_periods', 'academic_year_term_id', $recordId)),
                'exam_schedule' => $this->yearOfTerm($this->lookup('exam_schedules', 'academic_year_term_id', $recordId)),
                default => 0,
            };
        }
        return 0;
    }

    private function yearOfAssessment(int $assessmentId): int
    {
        return $this->yearOfTerm($this->lookup('assessments', 'academic_year_term_id', $assessmentId));
    }

    private function yearOfTerm(int $termId): int
    {
        return $this->lookup('academic_year_terms', 'academic_year_id', $termId);
    }

    private function yearOfDate(string $date): int
    {
        $key = "date:{$date}";
        if (!isset($this->cache[$key])) {
            $stmt = $this->db->prepare(
                'SELECT id FROM academic_years WHERE ? BETWEEN start_date AND end_date ORDER BY start_date DESC LIMIT 1'
            );
            $stmt->execute([$date]);
            $this->cache[$key] = (int) ($stmt->fetchColumn() ?: 0);
        }
        return $this->cache[$key];
    }

    /** Keyed single-column read on the primary (fixed table/column names only). */
    private function lookup(string $table, string $column, int $id): int
    {
        if ($id <= 0) return 0;
        $key = "{$table}:{$id}";
        if (!isset($this->cache[$key])) {
            $stmt = $this->db->prepare("SELECT {$column} FROM {$table} WHERE id = ? LIMIT 1");
            $stmt->execute([$id]);
            $this->cache[$key] = (int) ($stmt->fetchColumn() ?: 0);
        }
        return $this->cache[$key];
    }

    private function positiveInt($value): int
    {
        if (is_int($value)) return max(0, $value);
        if (is_string($value) && ctype_digit($value)) return (int) $value;
        return 0;
    }

    /** Role names (lowercase) from the token, else from the database. */
    private function roleNames(int $userId): array
    {
        $roles = $_SERVER['auth_user']['roles'] ?? $_SERVER['auth_user']['role_names'] ?? null;
        if (is_string($roles)) $roles = preg_split('/[|,]/', $roles) ?: [];
        if (is_array($roles) && $roles) {
            return array_values(array_filter(array_map(
                static fn ($role) => is_string($role)
                    ? strtolower(trim($role))
                    : (is_array($role) ? strtolower(trim((string) ($role['name'] ?? ''))) : ''),
                $roles
            )));
        }
        if ($userId <= 0) return [];
        // Two keyed reads instead of a join (Hard Rule 1).
        $stmt = $this->db->prepare('SELECT role_id FROM user_roles WHERE user_id = ?');
        $stmt->execute([$userId]);
        $roleIds = array_values(array_filter(array_map('intval', $stmt->fetchAll(PDO::FETCH_COLUMN) ?: [])));
        if (!$roleIds) return [];
        $marks = implode(',', array_fill(0, count($roleIds), '?'));
        $stmt = $this->db->prepare("SELECT name FROM roles WHERE id IN ({$marks})");
        $stmt->execute($roleIds);
        return array_map(static fn ($n) => strtolower(trim((string) $n)), $stmt->fetchAll(PDO::FETCH_COLUMN) ?: []);
    }
}
