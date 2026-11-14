<?php
namespace App\API\Modules\academic;

use App\API\Services\ReadReplicaService;
use PDO;
use Exception;

/**
 * Academic Year Manager
 * 
 * Handles all academic year operations including:
 * - Creating and managing academic years
 * - Linking with academic_year_terms table
 * - Managing year transitions
 * - Enrollment periods
 */
class AcademicYearManager
{
    private PDO $db;

    public function __construct(PDO $db)
    {
        $this->db = $db;
    }

    /**
     * Get current active academic year
     */
    public function getCurrentAcademicYear(): ?array
    {
        $stmt = $this->db->query("
            SELECT * FROM academic_years 
            WHERE is_current = TRUE
            ORDER BY id DESC
            LIMIT 1
        ");

        $year = $stmt->fetch(PDO::FETCH_ASSOC);
        return $year ?: null;
    }

    /**
     * Get academic year by ID
     */
    public function getAcademicYear(int $yearId): ?array
    {
        $stmt = $this->db->prepare("SELECT * FROM academic_years WHERE id = ?");
        $stmt->execute([$yearId]);
        $year = $stmt->fetch(PDO::FETCH_ASSOC);
        return $year ?: null;
    }

    /**
     * Get academic year by code (e.g., "2025/2026")
     */
    public function getAcademicYearByCode(string $yearCode): ?array
    {
        $stmt = $this->db->prepare("SELECT * FROM academic_years WHERE year_code = ?");
        $stmt->execute([$yearCode]);
        $year = $stmt->fetch(PDO::FETCH_ASSOC);
        return $year ?: null;
    }

    /**
     * Create new academic year
     */
    public function createAcademicYear(array $data): array
    {
        // Academic years are always represented as YYYY/YYYY+1. Derive the
        // label from the opening date so year_code and year_name cannot drift.
        if (!empty($data['start_date'])) {
            $startYear = (int) date('Y', strtotime($data['start_date']));
            $canonicalYear = $startYear . '/' . ($startYear + 1);
            $data['year_code'] = $canonicalYear;
            $data['year_name'] = $canonicalYear;
        }

        // Validate required fields
        $required = ['year_code', 'year_name', 'start_date', 'end_date'];
        foreach ($required as $field) {
            if (empty($data[$field])) {
                throw new \InvalidArgumentException("Missing required field: $field");
            }
        }

        // Check if year code already exists
        if ($this->getAcademicYearByCode($data['year_code'])) {
            throw new \InvalidArgumentException("Academic year {$data['year_code']} already exists");
        }

        $sql = "INSERT INTO academic_years (
            year_code, year_name, start_date, end_date, status, is_current
        ) VALUES (?, ?, ?, ?, ?, 0)";

        $stmt = $this->db->prepare($sql);
        $stmt->execute([
            $data['year_code'],
            $data['year_name'],
            $data['start_date'],
            $data['end_date'],
            // Every new year is active - upcoming (state defaults to 'upcoming').
            // Archiving has its own path, so a caller-supplied status is ignored.
            'active'
        ]);
        $yearId = (int) $this->db->lastInsertId();

        // Automatically create 3 terms for this academic year
        $this->createTermsForYear($yearId, $data['start_date'], $data['end_date']);

        // Automatically generate the term calendar (weeks, school days,
        // half-term holidays) from the (tentative) term dates. The admin later
        // enters the government-issued dates on the terms; editing a term's
        // dates regenerates the calendar automatically.
        $this->regenerateCalendarForYear($yearId);

        return $this->getAcademicYear($yearId);
    }

    /**
     * Automatically create 3 terms for an academic year
     */
    private function createTermsForYear(int $yearId, string $startDate, string $endDate): void
    {
        // Calculate term dates (roughly equal divisions)
        $start = new \DateTime($startDate);
        $end = new \DateTime($endDate);
        $totalDays = $start->diff($end)->days;
        $termDays = floor($totalDays / 3);

        $terms = [
            [
                'name' => 'Term 1',
                'term_number' => 1,
                'start' => $start->format('Y-m-d'),
                'end' => (clone $start)->add(new \DateInterval("P{$termDays}D"))->format('Y-m-d')
            ],
            [
                'name' => 'Term 2',
                'term_number' => 2,
                'start' => (clone $start)->add(new \DateInterval("P" . ($termDays + 1) . "D"))->format('Y-m-d'),
                'end' => (clone $start)->add(new \DateInterval("P" . ($termDays * 2) . "D"))->format('Y-m-d')
            ],
            [
                'name' => 'Term 3',
                'term_number' => 3,
                'start' => (clone $start)->add(new \DateInterval("P" . ($termDays * 2 + 1) . "D"))->format('Y-m-d'),
                'end' => $end->format('Y-m-d')
            ]
        ];

        $sql = "INSERT INTO academic_year_terms (academic_year_id, term_id, opening_date, closing_date, status)
                VALUES (?, ?, ?, ?, 'upcoming')";

        foreach ($terms as $term) {
            $stmt = $this->db->prepare($sql);
            $stmt->execute([
                $yearId,
                $this->ensureTermId($term['name'], $term['term_number']),
                $term['start'],
                $term['end']
            ]);
        }
    }

    /**
     * Regenerate the academic calendar for a year from the term dates.
     *
     * Delegates to AcademicCalendarService / sp_generate_year_calendar so the
     * week + day structure stays consistent everywhere (year creation, term
     * date edits, transition workflows). Week counts are derived from the dates.
     */
    private function regenerateCalendarForYear(int $yearId): void
    {
        $calendarService = new AcademicCalendarService($this->db);
        $calendarService->generateYearCalendar($yearId);
    }

    /**
     * Public entry point used by controllers/services to rebuild a calendar.
     */
    public function generateCalendar(int $yearId, array $weekCounts = []): array
    {
        $calendarService = new AcademicCalendarService($this->db);
        return $calendarService->generateYearCalendar((int) $yearId, $weekCounts);
    }

    /**
     * Return the generated calendar summary for a year.
     */
    public function getCalendar(int $yearId): array
    {
        $calendarService = new AcademicCalendarService($this->db);
        return $calendarService->getCalendar((int) $yearId);
    }

    /**
     * Resolve (and create if needed) the global terms.id for a term number (T1..Tn).
     */
    private function ensureTermId(string $name, int $termNumber): int
    {
        $code = 'T' . $termNumber;
        $stmt = $this->db->prepare("SELECT id FROM terms WHERE code = ? OR name = ? LIMIT 1");
        $stmt->execute([$code, $name]);
        $termId = (int) ($stmt->fetchColumn() ?: 0);
        if ($termId > 0) {
            return $termId;
        }

        $ins = $this->db->prepare("INSERT INTO terms (name, code) VALUES (?, ?)");
        $ins->execute([$name, $code]);
        return (int) $this->db->lastInsertId();
    }

    /**
     * Make a year the current year.
     *
     * Date-aware: a year only becomes current on or after its own start
     * date, so the outgoing year stays current through the overlap period
     * (November-December holiday and the January intake window) while the
     * incoming year already runs as "active - upcoming".
     *
     * Exactly one year is current (uk_academic_years_single_current), and
     * other years keep their own state: a completed year stays completed.
     */
    public function setCurrentYear(int $yearId, bool $force = false): bool
    {
        $year = $this->getAcademicYear($yearId);
        if (!$year) {
            throw new Exception("Academic year not found");
        }

        $today = date('Y-m-d');
        $started = ($year['start_date'] ?? '') <= $today;
        if (!$started && !$force) {
            throw new Exception(
                sprintf('%s does not start until %s. The current academic year stays active until then.',
                    $year['year_code'] ?? 'The target academic year',
                    $year['start_date'] ?? 'its start date')
            );
        }

        $this->db->beginTransaction();

        try {
            // Only the single current row moves. Completed and upcoming
            // years keep their state so their data stays readable and
            // quotable during the overlap.
            $clear = $this->db->prepare(
                "UPDATE academic_years
                    SET is_current = 0,
                        state = CASE WHEN state = 'current' THEN 'upcoming' ELSE state END
                  WHERE id <> ?"
            );
            $clear->execute([$yearId]);

            $this->db->prepare(
                "UPDATE academic_years
                    SET is_current = 1,
                        state = 'current',
                        status = 'active',
                        completed_at = NULL
                  WHERE id = ?"
            )->execute([$yearId]);

            $this->db->commit();
            return true;
        } catch (Exception $e) {
            $this->db->rollBack();
            throw $e;
        }
    }

    /**
     * Close a year: teaching is finished and its records are complete.
     *
     * This is NOT archiving. The year keeps status = 'active' so every
     * read path and every ordinary user can still see its data; only
     * writes are restricted to school admin / system admin / director.
     */
    public function completeYear(int $yearId, int $userId): bool
    {
        $this->db->beginTransaction();

        try {
            $this->db->prepare(
                "UPDATE academic_year_terms SET status = 'completed' WHERE academic_year_id = ?"
            )->execute([$yearId]);

            $this->db->prepare(
                "UPDATE academic_years
                    SET state = 'completed',
                        status = 'active',
                        is_current = 0,
                        completed_at = NOW(),
                        completed_by = ?
                  WHERE id = ?"
            )->execute([$userId, $yearId]);

            $this->db->commit();
            return true;
        } catch (Exception $e) {
            $this->db->rollBack();
            throw $e;
        }
    }

    /**
     * Mark a year as the upcoming year (rollover preparation / intake).
     */
    public function markYearUpcoming(int $yearId): bool
    {
        return $this->db->prepare(
            "UPDATE academic_years
                SET state = 'upcoming', status = 'active', is_current = 0
              WHERE id = ?"
        )->execute([$yearId]);
    }

    /**
     * Update the sub-state of a year.
     *
     * `status` is intentionally not accepted here: every non-archived year
     * is `active`, and the lifecycle lives in `state`.
     */
    public function updateYearState(int $yearId, string $state): bool
    {
        if (!in_array($state, \App\API\Services\AcademicYearLifecycleService::allowedStates(), true)) {
            throw new Exception("Invalid year state: $state");
        }

        if ($state === \App\API\Services\AcademicYearLifecycleService::STATE_ARCHIVED) {
            throw new Exception(
                'Archiving is not a rollover action. A system administrator may archive a year explicitly.'
            );
        }

        // is_current must move with state: state = 'current' requires the flag,
        // and any other state releases it, so exactly one year is current at
        // all times (enforced in the database by uk_academic_years_single_current
        // and by trg_enforce_year_state_is_current).
        $isCurrent = $state === \App\API\Services\AcademicYearLifecycleService::STATE_CURRENT ? 1 : 0;

        return $this->db->prepare(
            "UPDATE academic_years
                SET state = ?, status = 'active', is_current = ?,
                    completed_at = CASE WHEN ? = 'completed' THEN COALESCE(completed_at, NOW()) ELSE NULL END
              WHERE id = ?"
        )->execute([$state, $isCurrent, $state, $yearId]);
    }

    /**
     * Archive an academic year: a permanent, system-administrator-only closure.
     *
     * Archiving is NOT the same as completing a year. Completing marks the year
     * active - completed, leaving its data readable for every user and writable
     * by school admin / director / system admin. Archiving goes further: the
     * year's data is hidden completely from ordinary access. It is therefore
     * never part of a rollover, and is guarded here as well as at the endpoint.
     */
    public function archiveYear(int $yearId, int $userId, ?string $notes = null): bool
    {
        $year = $this->getAcademicYear($yearId);
        if (!$year) {
            throw new Exception("Academic year not found");
        }

        // The school is still operating inside this year; archiving it would
        // hide the year everyone is working in.
        if ((int) ($year['is_current'] ?? 0) === 1) {
            throw new Exception(
                'The current academic year is in use and cannot be archived. Close it first, then archive it later if required.'
            );
        }

        if (\App\API\Services\AcademicYearLifecycleService::isArchived(
            (string) $year['status'],
            (string) ($year['state'] ?? '')
        )) {
            // Already archived: keep the action idempotent rather than an error.
            return true;
        }

        $this->db->beginTransaction();

        try {
            // Archiving is a lifecycle status change, not a sub-state change.
            // This is the ONLY path that sets status = 'archived', and it is an
            // explicit system-administrator action -- never part of a rollover.
            $this->db->prepare(
                "UPDATE academic_years
                    SET status = 'archived',
                        state = 'archived',
                        is_current = 0
                  WHERE id = ?"
            )->execute([$yearId]);

            // Update academic_year_archives table
            $archiveData = $this->getArchiveStats($yearId);

            $sql = "INSERT INTO academic_year_archives (
                academic_year, status, total_students, promoted_count,
                retained_count, transferred_count, graduated_count,
                closure_initiated_by, closure_date, closure_notes, archived_at
            ) VALUES (?, 'archived', ?, ?, ?, ?, ?, ?, NOW(), ?, NOW())
            ON DUPLICATE KEY UPDATE
                status = 'archived',
                total_students = VALUES(total_students),
                promoted_count = VALUES(promoted_count),
                retained_count = VALUES(retained_count),
                transferred_count = VALUES(transferred_count),
                graduated_count = VALUES(graduated_count),
                closure_initiated_by = VALUES(closure_initiated_by),
                closure_date = NOW(),
                closure_notes = VALUES(closure_notes),
                archived_at = NOW()";

            $yearNumber = (int) substr($year['year_code'], 0, 4) + 1; // e.g., 2025/2026 -> 2026

            $stmt = $this->db->prepare($sql);
            $stmt->execute([
                $yearNumber,
                $archiveData['total_students'],
                $archiveData['promoted_count'],
                $archiveData['retained_count'],
                $archiveData['transferred_count'],
                $archiveData['graduated_count'],
                $userId,
                $notes
            ]);

            $this->db->commit();
            return true;
        } catch (Exception $e) {
            $this->db->rollBack();
            throw $e;
        }
    }

    /**
     * Get statistics for archiving
     */
    private function getArchiveStats(int $yearId): array
    {
        $stmt = $this->db->prepare("
            SELECT
                COUNT(*) as total_students,
                SUM(CASE WHEN enrollment_status = 'completed' THEN 1 ELSE 0 END) as promoted_count,
                SUM(CASE WHEN enrollment_status = 'active' THEN 1 ELSE 0 END) as retained_count,
                SUM(CASE WHEN enrollment_status = 'transferred' THEN 1 ELSE 0 END) as transferred_count,
                SUM(CASE WHEN enrollment_status = 'graduated' THEN 1 ELSE 0 END) as graduated_count
            FROM student_academic_enrollments
            WHERE academic_year_id = ?
        ");
        $stmt->execute([$yearId]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    /**
     * Get next academic year code
     */
    public function getNextYearCode(string $currentCode): string
    {
        // Extract years from code like "2025/2026"
        if (preg_match('/^(\d{4})\/(\d{4})$/', $currentCode, $matches)) {
            $year1 = (int) $matches[1] + 1;
            $year2 = (int) $matches[2] + 1;
            return "{$year1}/{$year2}";
        }

        throw new Exception("Invalid year code format: $currentCode");
    }

    /**
     * Dates for the year after one that runs $start..$end.
     *
     * The school year follows the calendar year (January to December), so the
     * next one is the same span one calendar year later. It never starts on or
     * before the current year's last day; when the shifted start would, it
     * starts the day after the current year ends.
     *
     * @return array{0: \DateTimeImmutable, 1: \DateTimeImmutable}
     */
    public static function nextYearDates(string $start, string $end): array
    {
        $currentStart = new \DateTimeImmutable($start);
        $currentEnd = new \DateTimeImmutable($end);
        $nextStart = $currentStart->modify('+1 year');
        $nextEnd = $currentEnd->modify('+1 year');
        if ($nextStart <= $currentEnd) {
            $nextStart = $currentEnd->modify('+1 day');
        }
        if ($nextEnd <= $nextStart) {
            $nextEnd = $nextStart->modify('+1 year -1 day');
        }
        return [$nextStart, $nextEnd];
    }

    /**
     * Create next academic year based on current one
     */
    public function createNextYear(int $userId): array
    {
        $currentYear = $this->getCurrentAcademicYear();
        if (!$currentYear) {
            throw new Exception("No current academic year found");
        }

        $nextYearCode = $this->getNextYearCode($currentYear['year_code']);

        [$nextStart, $nextEnd] = self::nextYearDates($currentYear['start_date'], $currentYear['end_date']);

        return $this->createAcademicYear([
            'year_code' => $nextYearCode,
            'year_name' => $nextYearCode,
            'start_date' => $nextStart->format('Y-m-d'),
            'end_date' => $nextEnd->format('Y-m-d'),
            'status' => 'active',
            'created_by' => $userId
        ]);
    }

    /**
     * Get all academic years
     */
    public function getAllYears(array $filters = []): array
    {
        // Return the year and its configured term count together so callers
        // do not have to infer the relationship from a second response shape.
        // academic_year_terms is the canonical read projection for year terms.
        $sql = "SELECT ay.*, (
                    SELECT COUNT(*)
                    FROM " . ReadReplicaService::qualifiedRef('academic_year_terms') . " ayt
                    WHERE ayt.academic_year_id = ay.id
                ) AS term_count
                FROM academic_years ay WHERE 1=1";
        $params = [];

        if (!empty($filters['status'])) {
            $sql .= " AND ay.status = ?";
            $params[] = $filters['status'];
        }

        // An archived year is hidden completely: its data is not accessible to
        // ordinary users any more. A caller may still ask for archived years
        // explicitly (status filter above) — that is the system administrator's
        // restore path — but they never appear in a default listing.
        if (empty($filters['status']) || $filters['status'] !== 'archived') {
            $sql .= " AND " . \App\API\Services\AcademicYearLifecycleService::visibleSql('ay');
        }

        $sql .= " ORDER BY ay.start_date DESC";

        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Get terms for an academic year
     */
    public function getTermsForYear(int $yearId): array
    {
        $year = $this->getAcademicYear($yearId);
        if (!$year) {
            throw new Exception("Academic year not found");
        }

        $stmt = $this->db->prepare("
            SELECT
                academic_year_term_id AS id,
                academic_year_id,
                term_id,
                term_name AS name,
                term_code AS code,
                opening_date,
                closing_date,
                term_period_status AS status,
                SUBSTRING(term_code, 2) AS term_number
            FROM " . ReadReplicaService::qualifiedRef('academic_term') . "
            WHERE academic_year_id = ?
            ORDER BY term_number ASC
        ");
        $stmt->execute([$yearId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Get current active term
     */
    public function getCurrentTerm(): ?array
    {
        $stmt = $this->db->query("
            SELECT
                academic_year_term_id AS id,
                academic_year_id,
                term_id,
                term_name AS name,
                term_code AS code,
                opening_date,
                closing_date,
                term_period_status AS status,
                SUBSTRING(term_code, 2) AS term_number
            FROM " . ReadReplicaService::qualifiedRef('academic_term') . "
            WHERE term_period_status = 'current'
            LIMIT 1
        ");
        $term = $stmt->fetch(PDO::FETCH_ASSOC);
        return $term ?: null;
    }
}
