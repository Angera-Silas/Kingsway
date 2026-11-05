<?php
namespace App\API\Modules\academic;

use App\API\Services\NotificationService;
use App\API\Services\ReadReplicaService;
use App\API\Services\InterServiceClient;
use App\Config\Config;
use PDO;
use Exception;
use function App\API\Includes\formatResponse;

/**
 * Academic Calendar Service
 *
 * Automates the generation of term calendars from the government-issued term
 * dates recorded on academic_year_terms (opening_date, half_term_start,
 * half_term_end, closing_date). The admin only enters the dates; weeks, school
 * days and half-term holidays are derived automatically.
 *
 * Standard Kenyan school year: Term 1 (14 weeks), Term 2 (14 weeks),
 * Term 3 (10 weeks). Week counts are derived from the recorded dates unless
 * explicitly overridden per term.
 *
 * Generation itself runs through the sp_generate_year_calendar stored
 * procedure (migration 010), which is idempotent and also callable from CLI
 * for backfills.
 */
class AcademicCalendarService
{
    private PDO $db;

    /** Standard week counts per term (term_number => weeks). */
    private const DEFAULT_WEEKS = [1 => 14, 2 => 14, 3 => 10];

    public function __construct(PDO $db)
    {
        $this->db = $db;
    }

    /**
     * Generate (or regenerate) the full term calendar for an academic year.
     *
     * Week counts are DERIVED from the recorded opening/closing dates using
     * real Monday-Friday school weeks (see countWeeks()), so terms that run
     * shorter or longer than the standard 14/14/10 Kenyan template produce a
     * correct week grid. The standard counts are only used as a fallback when
     * an explicit $weekCounts override is passed (e.g. from the year-rollover
     * prefill).
     *
     * Manually-marked days (is_manual = 1: emergency/national holidays,
     * closures, special events) are snapshotted before regeneration and
     * re-applied afterwards, so editing term dates never wipes them.
     *
     * @param int   $academicYearId
     * @param array $weekCounts      Optional explicit week counts keyed by term
     *                               number (e.g. [1 => 14, 2 => 14, 3 => 10]).
     *                               Missing terms fall back to the derived count.
     * @return array formatResponse payload with per-term summary
     */
    public function generateYearCalendar(int $academicYearId, array $weekCounts = []): array
    {
        try {
            if (!$this->academicYearExists($academicYearId)) {
                return formatResponse(false, null, 'Academic year not found');
            }

            $terms = $this->getYearTerms($academicYearId);
            if (empty($terms)) {
                return formatResponse(false, null, 'No terms exist for this academic year');
            }

            $missing = [];
            foreach ($terms as $term) {
                if (empty($term['opening_date']) || empty($term['closing_date'])) {
                    $missing[] = $term['name'];
                }
            }
            if (!empty($missing)) {
                return formatResponse(
                    false,
                    null,
                    'Opening/closing dates are required before generating the calendar for: ' . implode(', ', $missing)
                );
            }

            $yearStmt = $this->db->prepare('SELECT year_code FROM academic_years WHERE id = ?');
            $yearStmt->execute([$academicYearId]);
            $yearCode = (string) $yearStmt->fetchColumn();
            $yearStart = (int) substr($yearCode, 0, 4);
            if ($yearStart > 0) {
                $this->syncKenyanPublicHolidays($yearStart);
            }

            $manualDays = $this->getManualDays($academicYearId);

            // Calendar dates and week boundaries are computed from the target
            // year's actual ISO weekdays. Ignore legacy caller-supplied week
            // counts and stored procedures: fixed week templates can truncate
            // a real term or mis-handle a midweek/weekend opening.
            $this->generateCalendarLocally($academicYearId, $terms, []);

            // Generation runs during Stage 1 in the same transaction that
            // creates academic_year_terms. Verify the primary database rather
            // than trusting a materialized read projection, which may be stale
            // (or cannot see the uncommitted term rows at all).
            $calendarChecks = $this->verifyGeneratedCalendar($terms);
            if (count(array_filter($calendarChecks, static fn (array $check): bool => !$check['pass'])) > 0) {
                // Repair partial or stale output through the same deterministic
                // target-date generator, then verify before reporting success.
                $this->generateCalendarLocally($academicYearId, $terms, []);
                $calendarChecks = $this->verifyGeneratedCalendar($terms);
            }
            $failedTerms = array_values(array_filter($calendarChecks, static fn (array $check): bool => !$check['pass']));
            if ($failedTerms !== []) {
                $labels = array_map(static fn (array $check): string => $check['term'], $failedTerms);
                throw new Exception('Calendar generation produced no complete week/day records for: ' . implode(', ', $labels));
            }

            $this->reapplyManualDays($academicYearId, $manualDays);

            $this->notifyCalendarPublished($academicYearId);

            $summary = $this->getCalendar($academicYearId);

            $summary['generation_checks'] = $calendarChecks;
            return formatResponse(true, $summary, 'Academic calendar generated successfully');
        } catch (Exception $e) {
            return formatResponse(false, null, 'Calendar generation failed: ' . $e->getMessage());
        }
    }

    /** Pull Kenya holiday dates from the internal Node calendar source and persist them idempotently. */
    private function syncKenyanPublicHolidays(int $year): void
    {
        $secret = (string) Config::get('NODE_REALTIME_PUBLISH_SECRET', '');
        if ($secret === '') {
            throw new Exception('Kenya holiday calendar is unavailable: Node worker secret is not configured.');
        }
        $client = new InterServiceClient();
        [$raw, $status, $error] = $client->get(
            'realtime_node',
            '/internal/academic/kenya-holidays?year=' . $year,
            ['X-Kingsway-Worker-Secret: ' . $secret, 'Accept: application/json'],
            15
        );
        if ($status !== 200 || !is_string($raw)) {
            throw new Exception('Kenya holiday calendar request failed' . ($error !== '' ? ': ' . $error : " (HTTP {$status})"));
        }
        $payload = json_decode($raw, true);
        if (!is_array($payload) || (int) ($payload['year'] ?? 0) !== $year || !is_array($payload['holidays'] ?? null)) {
            throw new Exception('Kenya holiday calendar returned an invalid response.');
        }
        if ($payload['holidays'] === []) {
            throw new Exception("Kenya holiday calendar returned no dates for {$year}.");
        }

        $refresh = $this->db->prepare(
            "UPDATE school_holidays SET holiday_type = ?, description = ?
             WHERE name = ? AND start_date = ? AND end_date = ?
               AND (description LIKE 'Kenya public holiday calendar (%'
                    OR description LIKE 'Calculated date estimate (%')"
        );
        $insert = $this->db->prepare(
            'INSERT INTO school_holidays (name, holiday_type, start_date, end_date, description, is_active)
             SELECT ?, ?, ?, ?, ?, 1 FROM DUAL
             WHERE NOT EXISTS (
                 SELECT 1 FROM school_holidays WHERE name = ? AND start_date = ? AND end_date = ?
             )'
        );
        foreach ($payload['holidays'] as $holiday) {
            $date = (string) ($holiday['date'] ?? '');
            $name = trim((string) ($holiday['name'] ?? ''));
            if (!preg_match('/^' . preg_quote((string) $year, '/') . '-\d{2}-\d{2}$/', $date) || $name === '') {
                throw new Exception("Kenya holiday calendar contains an invalid date or name for {$year}.");
            }
            $type = ($holiday['type'] ?? '') === 'religious' ? 'religious' : 'national';
            $estimated = !empty($holiday['estimated']);
            $source = trim((string) ($holiday['source'] ?? 'date-holidays KE'));
            $description = $estimated
                ? "Calculated date estimate ({$source}); confirm with the relevant religious authority or official Kenyan notice."
                : "Kenya public holiday calendar ({$source}); verify against the annual official Gazette. ";
            $refresh->execute([$type, $description, $name, $date, $date]);
            $insert->execute([$name, $type, $date, $date, $description, $name, $date, $date]);
        }
    }

    /** Verify generated weeks and dated day rows using keyed primary reads. */
    private function verifyGeneratedCalendar(array $terms): array
    {
        $weekIds = $this->db->prepare(
            'SELECT id FROM academic_year_calendar WHERE academic_year_term_id = ? ORDER BY week_number'
        );
        $dayCount = $this->db->prepare(
            'SELECT COUNT(*) FROM academic_year_calendar_days WHERE academic_year_calendar_id = ?'
        );
        $checks = [];
        foreach ($terms as $term) {
            $weekIds->execute([(int) $term['id']]);
            $ids = array_map('intval', $weekIds->fetchAll(PDO::FETCH_COLUMN));
            $days = 0;
            foreach ($ids as $weekId) {
                $dayCount->execute([$weekId]);
                $days += (int) $dayCount->fetchColumn();
            }
            $checks[] = [
                'term' => (string) $term['name'],
                'weeks' => count($ids),
                'days' => $days,
                'pass' => count($ids) > 0 && $days > 0,
            ];
        }
        return $checks;
    }

    private function hasCanonicalCalendarProcedure(): bool
    {
        $stmt = $this->db->prepare(
            "SELECT COUNT(*) FROM information_schema.PARAMETERS
             WHERE SPECIFIC_SCHEMA = DATABASE() AND SPECIFIC_NAME = 'sp_generate_year_calendar'
               AND PARAMETER_MODE = 'IN'"
        );
        $stmt->execute();
        return (int) $stmt->fetchColumn() === 4;
    }

    /** Generate calendar dates using the target year's real weekday sequence. */
    private function generateCalendarLocally(int $academicYearId, array $terms, array $weekCounts): void
    {
        $types = $this->db->query("SELECT code, id FROM calendar_day_types WHERE code IN ('school_day','school_holiday','weekend','public_holiday','holiday')")
            ->fetchAll(PDO::FETCH_KEY_PAIR);
        foreach (['school_day', 'school_holiday', 'weekend', 'public_holiday', 'holiday'] as $required) {
            if (!isset($types[$required])) throw new Exception('Calendar day type is missing: ' . $required);
        }

        $yearStmt = $this->db->prepare('SELECT start_date, end_date FROM academic_years WHERE id = ?');
        $yearStmt->execute([$academicYearId]);
        $year = $yearStmt->fetch(PDO::FETCH_ASSOC);
        if (!$year) throw new Exception('Academic year not found');

        // Stage 1 already owns a transaction while it creates the academic
        // year and its terms. Do not open/commit a nested transaction there.
        $ownsTransaction = !$this->db->inTransaction();
        if ($ownsTransaction) {
            $this->db->beginTransaction();
        }
        try {
            $deleteDays = $this->db->prepare(
                'DELETE d FROM academic_year_calendar_days d
                 JOIN academic_year_calendar c ON c.id=d.academic_year_calendar_id
                 WHERE c.academic_year_term_id=?'
            );
            $deleteWeeks = $this->db->prepare('DELETE FROM academic_year_calendar WHERE academic_year_term_id=?');
            $insertWeek = $this->db->prepare('INSERT INTO academic_year_calendar (academic_year_term_id,week_number,week_start,week_end) VALUES (?,?,?,?)');
            $insertDay = $this->db->prepare('INSERT INTO academic_year_calendar_days (academic_year_calendar_id,date,calendar_day_type_id,title) VALUES (?,?,?,?)');

            // Fill the complete target-year range as well. Dates between terms
            // are school holidays; Saturdays and Sundays remain weekends.
            // Term rows below provide the more specific instructional context.
            $this->db->prepare(
                'DELETE FROM academic_year_calendar_days
                 WHERE academic_year_calendar_id=0 AND is_manual=0 AND date BETWEEN ? AND ?'
            )->execute([$year['start_date'], $year['end_date']]);
            $insertBaseDay = $this->db->prepare(
                'INSERT IGNORE INTO academic_year_calendar_days (academic_year_calendar_id,date,calendar_day_type_id,title)
                 VALUES (?,?,?,?)'
            );
            $yearStart = new \DateTimeImmutable((string) $year['start_date']);
            $yearEnd = new \DateTimeImmutable((string) $year['end_date']);
            for ($date = $yearStart; $date <= $yearEnd; $date = $date->modify('+1 day')) {
                $isoDow = (int) $date->format('N');
                $weekend = $isoDow >= 6;
                $insertBaseDay->execute([
                    0,
                    $date->format('Y-m-d'),
                    (int) $types[$weekend ? 'weekend' : 'school_holiday'],
                    $weekend ? 'Weekend' : 'School Holiday',
                ]);
            }

            foreach ($terms as $term) {
                $termId = (int) $term['id'];
                $opening = (string) $term['opening_date'];
                $closing = (string) $term['closing_date'];
                $deleteDays->execute([$termId]);
                $deleteWeeks->execute([$termId]);
                if ($opening === '' || $closing === '' || $closing < $opening) continue;

                $cursor = new \DateTimeImmutable($opening);
                $weekNo = 0;
                $closeDate = new \DateTimeImmutable($closing);
                while ($cursor <= $closeDate) {
                    $weekNo++;
                    $isoDow = (int) $cursor->format('N');
                    // A weekday opening creates a partial first school week
                    // ending Friday. A weekend opening gets its own Sat/Sun
                    // date span, then normal Monday-Friday weeks follow.
                    $weekEnd = $cursor->modify($isoDow <= 5
                        ? '+' . (5 - $isoDow) . ' days'
                        : '+' . (7 - $isoDow) . ' days');
                    if ($weekEnd > $closeDate) $weekEnd = $closeDate;
                    $trailingWeekendClose = (int) $closeDate->format('N') >= 6
                        && $weekEnd < $closeDate
                        && $closeDate <= $weekEnd->modify('+2 days');
                    if ($trailingWeekendClose) $weekEnd = $closeDate;
                    $insertWeek->execute([$termId, $weekNo, $cursor->format('Y-m-d'), $weekEnd->format('Y-m-d')]);
                    $calendarId = (int) $this->db->lastInsertId();
                    for ($day = $cursor; $day <= $weekEnd; $day = $day->modify('+1 day')) {
                        $date = $day->format('Y-m-d');
                        $isHalfTerm = !empty($term['half_term_start']) && !empty($term['half_term_end'])
                            && $date >= $term['half_term_start'] && $date <= $term['half_term_end'];
                        $isWeekend = (int) $day->format('N') >= 6;
                        $typeId = (int) ($types[$isHalfTerm ? 'school_holiday' : ($isWeekend ? 'weekend' : 'school_day')]);
                        $title = $isHalfTerm ? 'Half Term Break' : ($isWeekend ? 'Weekend' : null);
                        if ($date === $opening) $title = 'Term ' . (int) $term['term_id'] . ' Opening Day';
                        if ($date === $closing) $title = 'Term ' . (int) $term['term_id'] . ' Closing Day';
                        $insertDay->execute([$calendarId, $date, $typeId, $title]);
                    }
                    $cursor = $weekEnd->modify('+1 day');
                    if ((int) $cursor->format('N') >= 6) {
                        $cursor = $cursor->modify('next monday');
                    }
                }
            }

            $holidays = $this->db->prepare(
                "SELECT name, holiday_type, start_date, end_date FROM school_holidays
                 WHERE is_active=1 AND end_date>=? AND start_date<=? ORDER BY start_date"
            );
            $holidays->execute([$year['start_date'], $year['end_date']]);
            $applyHoliday = $this->db->prepare(
                "UPDATE academic_year_calendar_days d
                 JOIN academic_year_calendar c ON c.id=d.academic_year_calendar_id
                 JOIN academic_year_terms t ON t.id=c.academic_year_term_id
                 SET d.calendar_day_type_id=?, d.title=?, d.is_manual=0
                 WHERE t.academic_year_id=? AND d.date BETWEEN ? AND ? AND d.is_manual=0"
            );
            foreach ($holidays as $holiday) {
                $type = in_array($holiday['holiday_type'], ['national', 'religious'], true) ? 'public_holiday' : 'holiday';
                $applyHoliday->execute([(int) $types[$type], $holiday['name'], $academicYearId, $holiday['start_date'], $holiday['end_date']]);
                $this->db->prepare(
                    "UPDATE academic_year_calendar_days
                     SET calendar_day_type_id=?, title=?
                     WHERE academic_year_calendar_id=0 AND is_manual=0
                       AND date BETWEEN ? AND ?"
                )->execute([(int) $types[$type], $holiday['name'], $holiday['start_date'], $holiday['end_date']]);
            }
            $this->db->prepare('INSERT INTO school_week_config (academic_year_id) VALUES (?) ON DUPLICATE KEY UPDATE academic_year_id=VALUES(academic_year_id)')
                ->execute([$academicYearId]);
            if ($ownsTransaction) {
                $this->db->commit();
            }
        } catch (\Throwable $e) {
            if ($ownsTransaction && $this->db->inTransaction()) $this->db->rollBack();
            throw $e;
        }
    }

    /**
     * Count week buckets using the same actual-date boundaries as generation.
     * The first weekday opening can be a partial week; a weekend opening is
     * represented as its real weekend date span before Monday-Friday weeks.
     */
    private function countWeeks(string $opening, string $closing, int $fallback): int
    {
        $start = strtotime($opening);
        $end = strtotime($closing);
        if (!$start || !$end || $end < $start) {
            return max(1, $fallback);
        }
        $weeks = 0;
        $cursor = new \DateTimeImmutable($opening);
        $endDate = new \DateTimeImmutable($closing);
        while ($cursor <= $endDate) {
            $weeks++;
            $dow = (int) $cursor->format('N');
            $weekEnd = $cursor->modify($dow <= 5
                ? '+' . (5 - $dow) . ' days'
                : '+' . (7 - $dow) . ' days');
            if ($weekEnd > $endDate) $weekEnd = $endDate;
            $cursor = $weekEnd->modify('+1 day');
            if ((int) $cursor->format('N') >= 6) $cursor = $cursor->modify('next monday');
        }
        return max(1, $weeks);
    }

    /**
     * Snapshot manually-marked calendar days for a year so regeneration can
     * re-apply them (the stored procedure rebuilds every term day).
     */
    private function getManualDays(int $academicYearId): array
    {
        $stmt = $this->db->prepare(
            "SELECT d.id, d.date, d.calendar_day_type_id, d.title, d.description
             FROM " . ReadReplicaService::qualifiedRef("academic_year_calendar_days") . " d
             JOIN " . ReadReplicaService::qualifiedRef("academic_year_calendar") . " c ON c.id = d.academic_year_calendar_id
             JOIN " . ReadReplicaService::qualifiedRef("academic_year_terms") . " ayt ON ayt.id = c.academic_year_term_id
             WHERE ayt.academic_year_id = ? AND d.is_manual = 1"
        );
        $stmt->execute([$academicYearId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Re-apply manually-marked days onto the freshly generated calendar by
     * matching each one to the regenerated day row for its date.
     */
    private function reapplyManualDays(int $academicYearId, array $days): void
    {
        if (empty($days)) {
            return;
        }

        $find = $this->db->prepare(
            "SELECT d.id
             FROM " . ReadReplicaService::qualifiedRef("academic_year_calendar_days") . " d
             JOIN " . ReadReplicaService::qualifiedRef("academic_year_calendar") . " c ON c.id = d.academic_year_calendar_id
             JOIN " . ReadReplicaService::qualifiedRef("academic_year_terms") . " ayt ON ayt.id = c.academic_year_term_id
             WHERE ayt.academic_year_id = ? AND d.date = ?
             LIMIT 1"
        );
        $update = $this->db->prepare(
            "UPDATE academic_year_calendar_days
             SET calendar_day_type_id = ?, title = ?, description = ?, is_manual = 1
             WHERE id = ?"
        );

        foreach ($days as $day) {
            $find->execute([$academicYearId, $day['date']]);
            $id = $find->fetchColumn();
            if ($id) {
                $update->execute([
                    $day['calendar_day_type_id'],
                    $day['title'],
                    $day['description'],
                    $id,
                ]);
            }
        }
    }

    /**
     * Return the generated calendar summary for an academic year.
     */
    public function getCalendar(int $academicYearId): array
    {
        // Read the calendar from the primary store. A newly-created year can
        // be requested immediately, before its read projections are refreshed.
        $stmt = $this->db->prepare(
            'SELECT id, term_id, opening_date, half_term_start, half_term_end, closing_date, status
             FROM academic_year_terms WHERE academic_year_id = ? ORDER BY term_id'
        );
        $stmt->execute([$academicYearId]);
        $termRows = $stmt->fetchAll(PDO::FETCH_ASSOC);
        $yearStmt = $this->db->prepare('SELECT start_date, end_date FROM academic_years WHERE id = ?');
        $yearStmt->execute([$academicYearId]);
        $yearRange = $yearStmt->fetch(PDO::FETCH_ASSOC) ?: ['start_date' => null, 'end_date' => null];
        $termName = $this->db->prepare('SELECT name FROM terms WHERE id = ?');
        $weekStmt = $this->db->prepare(
            'SELECT id, week_number, week_start, week_end FROM academic_year_calendar
             WHERE academic_year_term_id = ? ORDER BY week_number'
        );
        $dayTypes = [];
        foreach ($this->db->query('SELECT id, code, name FROM calendar_day_types')->fetchAll(PDO::FETCH_ASSOC) as $type) {
            $dayTypes[(int) $type['id']] = ['code' => $type['code'], 'name' => $type['name']];
        }
        $calendarDays = [];
        $weekMeta = [];
        $terms = [];
        $totalWeeks = 0;
        foreach ($termRows as $row) {
            $termName->execute([(int) $row['term_id']]);
            $termLabel = (string) ($termName->fetchColumn() ?: ('Term ' . (int) $row['term_id']));
            $weekStmt->execute([(int) $row['id']]);
            $weekRows = $weekStmt->fetchAll(PDO::FETCH_ASSOC);
            $weekDetails = [];
            $dayTotal = 0;
            foreach ($weekRows as $week) {
                $calendarId = (int) $week['id'];
                $weekMeta[$calendarId] = [
                    'term_id' => (int) $row['id'],
                    'term_number' => (int) $row['term_id'],
                    'term_name' => $termLabel,
                    'week_number' => (int) $week['week_number'],
                    'start_date' => (string) $week['week_start'],
                    'end_date' => (string) $week['week_end'],
                    'term_closing_date' => (string) $row['closing_date'],
                    'day_details' => [],
                ];
                $weekDetails[] = [
                    'calendar_id' => $calendarId,
                    'week_number' => (int) $week['week_number'],
                    'name' => 'Week ' . (int) $week['week_number'] . ' · '
                        . (new \DateTimeImmutable((string) $week['week_start'], new \DateTimeZone('Africa/Nairobi')))->format('D j M') . '–'
                        . (new \DateTimeImmutable((string) $week['week_end'], new \DateTimeZone('Africa/Nairobi')))->format('D j M Y'),
                    'start_date' => $week['week_start'],
                    'end_date' => $week['week_end'],
                    'start_weekday' => (new \DateTimeImmutable((string) $week['week_start']))->format('l'),
                    'end_weekday' => (new \DateTimeImmutable((string) $week['week_end']))->format('l'),
                    'days' => 0,
                    'calendar_days' => 0,
                    'day_details' => [],
                ];
            }
            $weekCount = count($weekRows);
            $totalWeeks += $weekCount;
            $terms[] = [
                'term_id' => (int) $row['id'],
                'term_number' => (int) $row['term_id'],
                'term_name' => $termLabel,
                'opening_date' => $row['opening_date'],
                'half_term_start' => $row['half_term_start'],
                'half_term_end' => $row['half_term_end'],
                'closing_date' => $row['closing_date'],
                'status' => $row['status'],
                'weeks' => $weekCount,
                'days' => $dayTotal,
                'week_details' => $weekDetails,
            ];
        }

        $normaliseDay = static function (array $row) use ($dayTypes): array {
            $date = (string) $row['date'];
            $type = $dayTypes[(int) ($row['calendar_day_type_id'] ?? 0)] ?? ['code' => 'unknown', 'name' => 'Unknown'];
            return [
                'date' => $date,
                'weekday' => (new \DateTimeImmutable($date, new \DateTimeZone('Africa/Nairobi')))->format('l'),
                'day_type' => $type['code'],
                'day_type_name' => $type['name'],
                'event' => $row['title'] ?? null,
                'description' => $row['description'] ?? null,
                'is_manual' => (bool) ($row['is_manual'] ?? false),
            ];
        };
        $baseDayStmt = $this->db->prepare(
            'SELECT date, calendar_day_type_id, title, description, is_manual
             FROM academic_year_calendar_days
             WHERE academic_year_calendar_id = 0 AND date BETWEEN ? AND ? ORDER BY date'
        );
        $baseDayStmt->execute([$yearRange['start_date'], $yearRange['end_date']]);
        foreach ($baseDayStmt->fetchAll(PDO::FETCH_ASSOC) as $dayRow) {
            $day = $normaliseDay($dayRow);
            $calendarDays[$day['date']] = $day;
        }

        $calendarIds = array_keys($weekMeta);
        if ($calendarIds !== []) {
            $placeholders = implode(',', array_fill(0, count($calendarIds), '?'));
            $termDayStmt = $this->db->prepare(
                "SELECT academic_year_calendar_id, date, calendar_day_type_id, title, description, is_manual
                 FROM academic_year_calendar_days
                 WHERE academic_year_calendar_id IN ({$placeholders}) ORDER BY date"
            );
            $termDayStmt->execute($calendarIds);
            foreach ($termDayStmt->fetchAll(PDO::FETCH_ASSOC) as $dayRow) {
                $calendarId = (int) $dayRow['academic_year_calendar_id'];
                if (!isset($weekMeta[$calendarId])) continue;
                $day = $normaliseDay($dayRow);
                $day['term_id'] = $weekMeta[$calendarId]['term_id'];
                $day['term_number'] = $weekMeta[$calendarId]['term_number'];
                $day['term_name'] = $weekMeta[$calendarId]['term_name'];
                $day['week_number'] = $weekMeta[$calendarId]['week_number'];
                $calendarDays[$day['date']] = $day;
                $weekMeta[$calendarId]['day_details'][$day['date']] = $day;
            }
        }

        // Associate the base-calendar weekend rows with the school week they
        // border, while leaving weekends outside term dates as school breaks.
        foreach ($calendarDays as $date => &$day) {
            if (isset($day['term_id'])) continue;
            foreach ($weekMeta as $calendarId => $meta) {
                $weekEnd = new \DateTimeImmutable($meta['end_date']);
                $weekWeekendEnd = $weekEnd->modify('+2 days')->format('Y-m-d');
                if ($date >= $meta['start_date'] && $date <= $weekWeekendEnd && $date <= $meta['term_closing_date']) {
                    $day['term_id'] = $meta['term_id'];
                    $day['term_number'] = $meta['term_number'];
                    $day['term_name'] = $meta['term_name'];
                    $day['week_number'] = $meta['week_number'];
                    $weekMeta[$calendarId]['day_details'][$date] = $day;
                    break;
                }
            }
        }
        unset($day);

        foreach ($terms as &$term) {
            foreach ($term['week_details'] as &$week) {
                $details = array_values($weekMeta[$week['calendar_id']]['day_details'] ?? []);
                usort($details, static fn (array $a, array $b): int => strcmp($a['date'], $b['date']));
                $week['day_details'] = $details;
                $week['calendar_days'] = count($details);
                $week['days'] = count(array_filter($details, static fn (array $day): bool => $day['day_type'] === 'school_day'));
                unset($week['calendar_id']);
            }
            unset($week);
            $term['days'] = array_sum(array_column($term['week_details'], 'days'));
            $term['calendar_days'] = array_sum(array_column($term['week_details'], 'calendar_days'));
        }
        unset($term);

        ksort($calendarDays);
        $holidayStmt = $this->db->prepare(
            'SELECT name, holiday_type, GREATEST(start_date, ?) AS start_date,
                    LEAST(end_date, ?) AS end_date, description
             FROM school_holidays WHERE is_active = 1 AND end_date >= ? AND start_date <= ?
             ORDER BY start_date, name'
        );
        $holidayStmt->execute([
            $yearRange['start_date'], $yearRange['end_date'],
            $yearRange['start_date'], $yearRange['end_date'],
        ]);
        $holidays = $holidayStmt->fetchAll(PDO::FETCH_ASSOC);
        foreach ($holidays as &$holiday) {
            $holiday['weekday'] = (new \DateTimeImmutable((string) $holiday['start_date']))->format('l');
            $holidayTypeLabel = ucfirst((string) $holiday['holiday_type']) . ' Public Holiday';
            $holidayStart = (string) $holiday['start_date'];
            $holidayEnd = (string) $holiday['end_date'];
            for ($date = $holidayStart; $date <= $holidayEnd; $date = date('Y-m-d', strtotime($date . ' +1 day'))) {
                if (!isset($calendarDays[$date])) continue;
                $calendarDays[$date]['day_type_name'] = $holidayTypeLabel;
                $calendarDays[$date]['holiday_type'] = (string) $holiday['holiday_type'];
                if (empty($calendarDays[$date]['event'])) $calendarDays[$date]['event'] = (string) $holiday['name'];
            }
        }
        unset($holiday);

        return [
            'academic_year_id' => $academicYearId,
            'timezone' => 'Africa/Nairobi',
            'year_start_date' => $yearRange['start_date'],
            'year_end_date' => $yearRange['end_date'],
            'standard_weeks' => self::DEFAULT_WEEKS,
            'total_weeks' => $totalWeeks,
            'terms' => $terms,
            'calendar_days' => array_values($calendarDays),
            'calendar_day_count' => count($calendarDays),
            'holidays' => $holidays,
            'holiday_count' => count($holidays),
        ];
    }

    /**
     * Readiness check: every term must have dates and a generated calendar.
     *
     * @return array ['pass' => bool, 'checks' => [...]]
     */
    public function validateCalendar(int $academicYearId): array
    {
        $terms = $this->getYearTerms($academicYearId);
        if (empty($terms)) {
            return ['pass' => false, 'reason' => 'No academic_year_terms found for the year', 'checks' => []];
        }

        $checks = [];
        foreach ($terms as $term) {
            $hasDates = !empty($term['opening_date']) && !empty($term['closing_date']);

            $weeks = $this->db->prepare(
                "SELECT id FROM academic_year_calendar WHERE academic_year_term_id = ? ORDER BY week_number"
            );
            $weeks->execute([$term['id']]);
            $weekIds = array_map('intval', $weeks->fetchAll(PDO::FETCH_COLUMN));
            $days = $this->db->prepare(
                'SELECT COUNT(*) FROM academic_year_calendar_days WHERE academic_year_calendar_id = ?'
            );
            $dayCount = 0;
            foreach ($weekIds as $weekId) {
                $days->execute([$weekId]);
                $dayCount += (int) $days->fetchColumn();
            }

            $checks[] = [
                'term' => $term['name'],
                'has_dates' => $hasDates,
                'weeks' => count($weekIds),
                'days' => $dayCount,
                'pass' => $hasDates && count($weekIds) > 0 && $dayCount > 0,
            ];
        }

        $pass = !empty($checks) && count(array_filter($checks, fn ($c) => $c['pass'])) === count($checks);
        return ['pass' => $pass, 'reason' => $pass ? null : 'One or more terms lack dates, calendar weeks, or dated calendar days', 'checks' => $checks];
    }

    /**
     * Push a staff-wide notification that the school calendar is out.
     * De-duplicated so regenerations don't spam.
     */
    private function notifyCalendarPublished(int $academicYearId): void
    {
        try {
            $stmt = $this->db->prepare('SELECT name FROM academic_years WHERE id = ?');
            $stmt->execute([$academicYearId]);
            $year = $stmt->fetchColumn();
            $label = $year !== null && $year !== ''
                ? (string) $year . ' academic calendar'
                : 'Academic calendar';
            $service = new NotificationService($this->db);
            $service->push(
                'all_staff',
                'calendar',
                'Academic calendar released',
                'The ' . $label . ' is now available.',
                'high',
                ['dedup_minutes' => 60]
            );
        } catch (Exception $e) {
            \App\API\Services\Logger::legacyError('[AcademicCalendarService] Notification push failed: ' . $e->getMessage());
        }
    }

    private function academicYearExists(int $academicYearId): bool
    {
        $stmt = $this->db->prepare('SELECT COUNT(*) FROM academic_years WHERE id = ?');
        $stmt->execute([$academicYearId]);
        return (int) $stmt->fetchColumn() > 0;
    }

    private function getYearTerms(int $academicYearId): array
    {
        // Calendar creation is also called before the Stage 1 transaction
        // commits. Read the new term rows from the primary database; the
        // materialized academic_term projection cannot contain those rows yet.
        $stmt = $this->db->prepare(
            "SELECT id, term_id, opening_date, half_term_start, half_term_end,
                    closing_date, status
             FROM academic_year_terms
             WHERE academic_year_id = ?
             ORDER BY term_id"
        );
        $stmt->execute([$academicYearId]);
        $terms = $stmt->fetchAll(PDO::FETCH_ASSOC);
        $nameStmt = $this->db->prepare('SELECT name, code FROM terms WHERE id = ?');
        foreach ($terms as &$term) {
            $nameStmt->execute([(int) $term['term_id']]);
            $labels = $nameStmt->fetch(PDO::FETCH_ASSOC) ?: [];
            $term['name'] = $labels['name'] ?? ('Term ' . (int) $term['term_id']);
            $term['code'] = $labels['code'] ?? ('T' . (int) $term['term_id']);
        }
        unset($term);
        return $terms;
    }
}
