<?php
namespace App\API\Services;

use App\Database\Database;
use PDO;

/**
 * The periods of an academic year, in order.
 *
 * An academic year is its terms AND its holidays:
 *   Term 1 (with its half term) → April Holiday → Term 2 (half term) →
 *   August Holiday → Term 3 (half term) → December Holiday,
 * and then the next academic year begins with its Term 1.
 *
 * Nothing extra is stored: every holiday is fully determined by the term
 * dates in academic_year_terms (opening, half term, closing) and the year's
 * own start and end. Days of the year before Term 1 opens are the December
 * Holiday carried into the new year.
 *
 * Every page, report and job that needs "where are we in the school year"
 * reads it from here, so a holiday is always a known period of the year and
 * never "no current term".
 *
 * Period shape:
 *   key                   stable id: "term-{aytId}", "half-term-{aytId}",
 *                         "holiday-after-{aytId}", "holiday-before-{aytId}"
 *   kind                  term | half_term | holiday
 *   name                  "Term 1", "Term 1 Half Term", "April Holiday"
 *   label                 name + " · " + year code
 *   start_date, end_date  inclusive (Y-m-d)
 *   days                  calendar days in the period
 *   status                completed | current | upcoming (by date)
 *   academic_year_id, year_code
 *   academic_year_term_id the term (for a term or half term), else null
 *   term_number           1/2/3 for a term or half term, else null
 *   after_term_id         holiday: the term it follows (null before Term 1)
 *   next_term_id          holiday: the term it leads to (may be next year's)
 */
final class AcademicPeriodService
{
    public const KIND_TERM = 'term';
    public const KIND_HALF_TERM = 'half_term';
    public const KIND_HOLIDAY = 'holiday';

    /** Holiday after Term N, by the school's calendar. */
    private const HOLIDAY_AFTER_TERM = [1 => 'April Holiday', 2 => 'August Holiday', 3 => 'December Holiday'];

    private PDO $db;
    private string $today;
    /** @var array<int, array> */
    private array $yearCache = [];
    private ?array $allTermsCache = null;

    /**
     * @param PDO|\App\Database\Database|null $db
     * @param string|null $today Y-m-d (tests); defaults to the server date
     */
    public function __construct($db = null, ?string $today = null)
    {
        if ($db !== null && !($db instanceof PDO) && method_exists($db, 'getConnection')) {
            $db = $db->getConnection();
        }
        $this->db = $db instanceof PDO ? $db : Database::getInstance()->getConnection();
        $this->today = $today ?: date('Y-m-d');
    }

    public static function holidayNameAfterTerm(int $termNumber): string
    {
        return self::HOLIDAY_AFTER_TERM[$termNumber] ?? ('Holiday after Term ' . $termNumber);
    }

    /**
     * Every period of one academic year in date order. A half term follows
     * the term it belongs to in the list; it is also inside that term's dates.
     */
    public function periodsForYear(int $yearId): array
    {
        if (isset($this->yearCache[$yearId])) {
            return $this->yearCache[$yearId];
        }
        $year = $this->year($yearId);
        if (!$year) {
            return $this->yearCache[$yearId] = [];
        }
        $terms = array_values(array_filter(
            $this->termsOfYear($yearId),
            static fn(array $t): bool => !empty($t['opening_date']) && !empty($t['closing_date'])
        ));

        $periods = [];
        $cursor = (string) $year['start_date'];
        $previous = null;
        foreach ($terms as $term) {
            $opening = (string) $term['opening_date'];
            $closing = (string) $term['closing_date'];
            if ($closing < $opening) {
                continue;
            }
            if ($cursor < $opening) {
                $periods[] = $this->holiday($year, $cursor, self::dayBefore($opening), $previous, $term);
            }
            $periods[] = $this->termPeriod($year, $term);
            $half = $this->halfTerm($year, $term);
            if ($half) {
                $periods[] = $half;
            }
            $cursor = max($cursor, self::dayAfter($closing));
            $previous = $term;
        }
        if ($previous && $cursor <= (string) $year['end_date']) {
            $periods[] = $this->holiday($year, $cursor, (string) $year['end_date'], $previous, $this->termAfter((string) $previous['closing_date']));
        }
        return $this->yearCache[$yearId] = $periods;
    }

    /**
     * The most specific period a date falls in (a half term rather than its
     * term), or null when no academic year covers the date.
     */
    public function periodForDate(string $date): ?array
    {
        $yearId = $this->yearIdForDate($date);
        if (!$yearId) {
            return null;
        }
        $match = null;
        foreach ($this->periodsForYear($yearId) as $period) {
            if ($date < $period['start_date'] || $date > $period['end_date']) {
                continue;
            }
            if ($match === null || $period['kind'] === self::KIND_HALF_TERM) {
                $match = $period;
            }
        }
        return $match;
    }

    /** Where the school is today: a term, a half term or a holiday. */
    public function currentPeriod(): ?array
    {
        return $this->periodForDate($this->today);
    }

    /**
     * The term in session on a date, else the next term to open after it
     * (across years: during the December Holiday this is next year's Term 1).
     */
    public function termInSessionOrNext(?string $date = null): ?array
    {
        $date = $date ?: $this->today;
        foreach ($this->allTerms() as $term) {
            if ((string) $term['closing_date'] >= $date) {
                return $term;
            }
        }
        return null;
    }

    /**
     * The term in session on a date, else the most recent term that has
     * closed (during the April Holiday this is Term 1). Reports default here.
     */
    public function termInSessionOrLast(?string $date = null): ?array
    {
        $date = $date ?: $this->today;
        $last = null;
        foreach ($this->allTerms() as $term) {
            if ((string) $term['opening_date'] > $date) {
                break;
            }
            $last = $term;
        }
        return $last;
    }

    /**
     * The term records and reports belong to by default: the term in session,
     * else the most recent term that has opened (during the April Holiday,
     * Term 1), else the first term. Within a chosen year when one is given.
     *
     * @return array{0:int,1:int} [academic_year_id, academic_year_term_id]; [0,0] when none
     */
    public function recordTerm(int $yearId = 0): array
    {
        $terms = $yearId > 0 ? $this->termsOfYear($yearId) : $this->allTerms();
        if (!$terms) {
            return [0, 0];
        }
        $pick = null;
        foreach ($terms as $term) {
            if ((string) $term['opening_date'] <= $this->today) {
                $pick = $term;
            }
        }
        $pick = $pick ?: $terms[0];
        return [(int) $pick['academic_year_id'], (int) $pick['id']];
    }

    /**
     * The year and term to plan for (schemes, lesson plans, allocations,
     * rosters): the chosen term; else in the chosen year the term in session,
     * else its next term, else its last term; with no year chosen, the term
     * in session or the next one to open in any year. A term outside the
     * chosen year is refused.
     *
     * @return array{0:int,1:int} [academic_year_id, academic_year_term_id]; [0,0] when none
     */
    public function planningTerm(int $yearId = 0, int $termId = 0): array
    {
        if ($termId > 0) {
            $term = $this->termById($termId);
            if (!$term || ($yearId > 0 && (int) $term['academic_year_id'] !== $yearId)) {
                return [0, 0];
            }
            return [(int) $term['academic_year_id'], $termId];
        }
        if ($yearId <= 0) {
            $term = $this->termInSessionOrNext();
            if ($term) {
                return [(int) $term['academic_year_id'], (int) $term['id']];
            }
            $yearId = $this->currentYearId();
            if ($yearId <= 0) {
                return [0, 0];
            }
        }
        $terms = $this->termsOfYear($yearId);
        if (!$terms) {
            return [0, 0];
        }
        foreach ($terms as $term) {
            if (!empty($term['closing_date']) && (string) $term['closing_date'] >= $this->today) {
                return [$yearId, (int) $term['id']];
            }
        }
        $last = end($terms);
        return [$yearId, (int) $last['id']];
    }

    /**
     * Summary for the shell and dashboards: the period today, the term to
     * plan for and the next period after today's.
     */
    public function snapshot(): array
    {
        $current = $this->currentPeriod();
        $next = null;
        if ($current) {
            // After a half term this is its term resuming; after a term, the
            // holiday; after the December Holiday, next year's Term 1.
            $next = $this->periodForDate(self::dayAfter($current['end_date']));
            // The December Holiday runs on into the new year's first days;
            // that continuation is the same holiday, so skip to Term 1.
            for ($guard = 0; $next && $current['kind'] === self::KIND_HOLIDAY
                && $next['kind'] === self::KIND_HOLIDAY && $guard < 3; $guard++) {
                $next = $this->periodForDate(self::dayAfter($next['end_date']));
            }
        }
        $planning = $this->termInSessionOrNext();
        return [
            'today' => $this->today,
            'current_period' => $current,
            'next_period' => $next,
            'planning_term' => $planning ? $this->termPeriod($this->year((int) $planning['academic_year_id']) ?: [], $planning) : null,
        ];
    }

    // ------------------------------------------------------------------

    private function termPeriod(array $year, array $term): array
    {
        $number = (int) $term['term_id'];
        $name = 'Term ' . $number;
        return $this->period($year, 'term-' . (int) $term['id'], self::KIND_TERM, $name,
            (string) $term['opening_date'], (string) $term['closing_date'], [
                'academic_year_term_id' => (int) $term['id'],
                'term_number' => $number,
                'half_term_start' => $term['half_term_start'] ?? null,
                'half_term_end' => $term['half_term_end'] ?? null,
            ]);
    }

    private function halfTerm(array $year, array $term): ?array
    {
        $start = (string) ($term['half_term_start'] ?? '');
        $end = (string) ($term['half_term_end'] ?? '');
        if ($start === '' || $end === '' || $end < $start
            || $start < (string) $term['opening_date'] || $end > (string) $term['closing_date']) {
            return null;
        }
        $number = (int) $term['term_id'];
        return $this->period($year, 'half-term-' . (int) $term['id'], self::KIND_HALF_TERM,
            'Term ' . $number . ' Half Term', $start, $end, [
                'academic_year_term_id' => (int) $term['id'],
                'term_number' => $number,
            ]);
    }

    private function holiday(array $year, string $start, string $end, ?array $after, ?array $next): array
    {
        $name = $after
            ? self::holidayNameAfterTerm((int) $after['term_id'])
            // Before Term 1 opens: the December Holiday carried into the year.
            : self::holidayNameAfterTerm(3);
        $key = $after ? 'holiday-after-' . (int) $after['id'] : 'holiday-before-' . (int) ($next['id'] ?? 0);
        return $this->period($year, $key, self::KIND_HOLIDAY, $name, $start, $end, [
            'academic_year_term_id' => null,
            'term_number' => null,
            'after_term_id' => $after ? (int) $after['id'] : null,
            'next_term_id' => $next ? (int) $next['id'] : null,
        ]);
    }

    private function period(array $year, string $key, string $kind, string $name, string $start, string $end, array $extra): array
    {
        $status = $end < $this->today ? 'completed' : ($start > $this->today ? 'upcoming' : 'current');
        $yearCode = (string) ($year['year_code'] ?? '');
        return array_merge([
            'key' => $key,
            'kind' => $kind,
            'name' => $name,
            'label' => $yearCode !== '' ? $name . ' · ' . $yearCode : $name,
            'start_date' => $start,
            'end_date' => $end,
            'days' => (int) ((strtotime($end) - strtotime($start)) / 86400) + 1,
            'status' => $status,
            'academic_year_id' => (int) ($year['id'] ?? 0),
            'year_code' => $yearCode,
            'academic_year_term_id' => null,
            'term_number' => null,
            'after_term_id' => null,
            'next_term_id' => null,
        ], $extra);
    }

    private function year(int $yearId): ?array
    {
        $stmt = $this->db->prepare('SELECT id, year_code, start_date, end_date FROM academic_years WHERE id = ?');
        $stmt->execute([$yearId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    private function yearIdForDate(string $date): int
    {
        $stmt = $this->db->prepare('SELECT id FROM academic_years WHERE start_date <= ? AND end_date >= ? ORDER BY start_date DESC, id DESC LIMIT 1');
        $stmt->execute([$date, $date]);
        return (int) ($stmt->fetchColumn() ?: 0);
    }

    private function currentYearId(): int
    {
        $id = (int) ($this->db->query('SELECT id FROM academic_years WHERE is_current = 1 ORDER BY id DESC LIMIT 1')->fetchColumn() ?: 0);
        return $id ?: $this->yearIdForDate($this->today);
    }

    private function termsOfYear(int $yearId): array
    {
        return array_values(array_filter($this->allTerms(), static fn(array $t): bool => (int) $t['academic_year_id'] === $yearId));
    }

    private function termById(int $termId): ?array
    {
        foreach ($this->allTerms() as $term) {
            if ((int) $term['id'] === $termId) {
                return $term;
            }
        }
        return null;
    }

    private function termAfter(string $date): ?array
    {
        foreach ($this->allTerms() as $term) {
            if ((string) $term['opening_date'] > $date) {
                return $term;
            }
        }
        return null;
    }

    /** Every dated term of every year, in date order. */
    private function allTerms(): array
    {
        if ($this->allTermsCache === null) {
            $rows = $this->db->query(
                'SELECT id, academic_year_id, term_id, opening_date, half_term_start, half_term_end, closing_date, status
                   FROM academic_year_terms
                  WHERE opening_date IS NOT NULL AND closing_date IS NOT NULL
                  ORDER BY opening_date, id'
            )->fetchAll(PDO::FETCH_ASSOC);
            $this->allTermsCache = $rows ?: [];
        }
        return $this->allTermsCache;
    }

    private static function dayBefore(string $date): string
    {
        return date('Y-m-d', strtotime($date . ' -1 day'));
    }

    private static function dayAfter(string $date): string
    {
        return date('Y-m-d', strtotime($date . ' +1 day'));
    }
}
