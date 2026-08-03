<?php
namespace App\API\Modules\academic;

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
     * @param int   $academicYearId
     * @param array $weekCounts      Optional explicit week counts keyed by term
     *                               number (e.g. [1 => 14, 2 => 14, 3 => 10]).
     *                               Omitted terms fall back to date derivation.
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

            $t1 = isset($weekCounts[1]) ? max(1, (int) $weekCounts[1]) : 0;
            $t2 = isset($weekCounts[2]) ? max(1, (int) $weekCounts[2]) : 0;
            $t3 = isset($weekCounts[3]) ? max(1, (int) $weekCounts[3]) : 0;

            $stmt = $this->db->prepare('CALL sp_generate_year_calendar(:year_id, :t1, :t2, :t3)');
            $stmt->execute([
                'year_id' => $academicYearId,
                't1' => $t1,
                't2' => $t2,
                't3' => $t3,
            ]);

            $summary = $this->getCalendar($academicYearId);

            return formatResponse(true, $summary, 'Academic calendar generated successfully');
        } catch (Exception $e) {
            return formatResponse(false, null, 'Calendar generation failed: ' . $e->getMessage());
        }
    }

    /**
     * Return the generated calendar summary for an academic year.
     */
    public function getCalendar(int $academicYearId): array
    {
        $sql = "
            SELECT
                ayt.id                         AS term_id,
                ayt.term_id                    AS term_number,
                t.name                         AS term_name,
                ayt.opening_date,
                ayt.half_term_start,
                ayt.half_term_end,
                ayt.closing_date,
                ayt.status,
                COUNT(c.id)                    AS weeks,
                COALESCE(SUM(DATEDIFF(c.week_end, c.week_start) + 1), 0) AS days
            FROM academic_year_terms ayt
            JOIN terms t ON t.id = ayt.term_id
            LEFT JOIN academic_year_calendar c ON c.academic_year_term_id = ayt.id
            WHERE ayt.academic_year_id = ?
            GROUP BY ayt.id, ayt.term_id, t.name, ayt.opening_date,
                     ayt.half_term_start, ayt.half_term_end, ayt.closing_date, ayt.status
            ORDER BY ayt.term_id
        ";
        $stmt = $this->db->prepare($sql);
        $stmt->execute([$academicYearId]);
        $terms = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $totalWeeks = 0;
        foreach ($terms as &$term) {
            $totalWeeks += (int) $term['weeks'];
        }
        unset($term);

        return [
            'academic_year_id' => $academicYearId,
            'standard_weeks' => self::DEFAULT_WEEKS,
            'total_weeks' => $totalWeeks,
            'terms' => $terms,
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
                "SELECT COUNT(*) FROM academic_year_calendar WHERE academic_year_term_id = ?"
            );
            $weeks->execute([$term['id']]);
            $weekCount = (int) $weeks->fetchColumn();

            $checks[] = [
                'term' => $term['name'],
                'has_dates' => $hasDates,
                'weeks' => $weekCount,
                'pass' => $hasDates && $weekCount > 0,
            ];
        }

        $pass = !empty($checks) && count(array_filter($checks, fn ($c) => $c['pass'])) === count($checks);
        return ['pass' => $pass, 'reason' => $pass ? null : 'One or more terms lack dates or calendar weeks', 'checks' => $checks];
    }

    private function academicYearExists(int $academicYearId): bool
    {
        $stmt = $this->db->prepare('SELECT COUNT(*) FROM academic_years WHERE id = ?');
        $stmt->execute([$academicYearId]);
        return (int) $stmt->fetchColumn() > 0;
    }

    private function getYearTerms(int $academicYearId): array
    {
        $stmt = $this->db->prepare(
            "SELECT ayt.id, ayt.term_id, t.name, t.code,
                    ayt.opening_date, ayt.half_term_start, ayt.half_term_end,
                    ayt.closing_date, ayt.status
             FROM academic_year_terms ayt
             JOIN terms t ON t.id = ayt.term_id
             WHERE ayt.academic_year_id = ?
             ORDER BY ayt.term_id"
        );
        $stmt->execute([$academicYearId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
}
