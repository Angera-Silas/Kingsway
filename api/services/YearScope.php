<?php
namespace App\API\Services;

use App\Database\Database;
use InvalidArgumentException;
use PDO;

/**
 * One way for every year-scoped READ to know which year (and term) it shows.
 *
 * Rules (user, 2026-11-13/14):
 *   - a year is academic_years.id; a term is academic_year_terms.id. The term
 *     number (`term_id` 1/2/3) repeats every year and is never used here;
 *   - no year in the request means the current year;
 *   - a term that is given must belong to the year that is given;
 *   - for term-scoped reads with no term given, the default is the term in
 *     session today, else the most recent term that has opened, else the
 *     year's first term (an upcoming year) — the same rule as
 *     AcademicContextService::getReportingTermId(), applied to any year.
 *
 *   $scope = (new YearScope())->fromRequest($data, YearScope::TERM_DEFAULT);
 *   // ['year_id' => 1, 'year_term_id' => 3, 'year' => [...], 'term' => [...]]
 *
 * Errors throw InvalidArgumentException with code 404 (unknown year/term) or
 * 422 (term not in year); controllers turn the code into the response status.
 */
final class YearScope
{
    /** No term: whole-year reads. */
    public const TERM_NONE = 0;
    /** A term given is honoured; none given means the whole year (null). */
    public const TERM_OPTIONAL = 1;
    /** A term given is honoured; none given means the default term. */
    public const TERM_DEFAULT = 2;

    private PDO $db;
    private ?string $today;

    /**
     * @param PDO|\App\Database\Database|null $db a PDO or the Database
     *        wrapper some services hold (its connection is used)
     */
    public function __construct($db = null, ?string $today = null)
    {
        if ($db !== null && !($db instanceof PDO) && method_exists($db, 'getConnection')) {
            $db = $db->getConnection();
        }
        $this->db = $db instanceof PDO ? $db : Database::getInstance()->getConnection();
        $this->today = $today;
    }

    /**
     * @return array{year_id:int, year_term_id:?int, year:array, term:?array}
     */
    public function fromRequest(array $data, int $termMode = self::TERM_NONE): array
    {
        $yearId = $this->positiveInt($data['academic_year_id'] ?? $data['year_id'] ?? null);
        $termId = $termMode === self::TERM_NONE
            ? 0
            : $this->positiveInt($data['academic_year_term_id'] ?? $data['year_term_id'] ?? null);

        $term = null;
        if ($termId > 0) {
            $term = $this->term($termId);
            if (!$term) {
                throw new InvalidArgumentException('Academic term not found', 404);
            }
            if ($yearId > 0 && (int) $term['academic_year_id'] !== $yearId) {
                throw new InvalidArgumentException('The selected term does not belong to the selected academic year', 422);
            }
            $yearId = (int) $term['academic_year_id'];
        }

        $year = $yearId > 0 ? $this->year($yearId) : $this->currentYear();
        if (!$year) {
            throw new InvalidArgumentException('Academic year not found', 404);
        }

        if (!$term && $termMode === self::TERM_DEFAULT) {
            $term = $this->defaultTerm((int) $year['id']);
        }

        return [
            'year_id' => (int) $year['id'],
            'year_term_id' => $term ? (int) $term['id'] : null,
            'year' => $year,
            'term' => $term,
        ];
    }

    /**
     * The year a LIST shows: academic_year_id / year_id from the filters,
     * else the current year. is_current tells callers whether to keep
     * current-only conditions (e.g. active learners) or show the whole roll.
     *
     * @return array{year_id:int, is_current:bool}
     */
    public static function listYear($db, array $filters): array
    {
        $scope = new self($db);
        $current = $scope->currentYearId();
        $requested = $scope->positiveInt($filters['academic_year_id'] ?? $filters['year_id'] ?? null);
        $yearId = $requested > 0 ? $requested : $current;
        return ['year_id' => $yearId, 'is_current' => $yearId === $current];
    }

    /**
     * GET endpoints that filter by a date range (date_from / date_to) rather
     * than by year: controller => [method names]. When such a request names
     * an academic year (academic_year_id / year_id) and no dates, the router
     * fills date_from / date_to with that year's start and end dates, so the
     * endpoint shows the chosen year without code changes. A request that
     * names no year keeps the endpoint's own default.
     */
    public const DATE_SCOPED = [
        'boarding' => ['getExeats', 'getRollCall', 'getOccupancy', 'getStats'],
        'attendance' => ['getAcademicSummary', 'getRegisterRange'],
        'payments' => ['getStats', 'getCollectionTrends', 'getRevenueSources'],
    ];

    /**
     * Fill date_from / date_to from the named academic year for a
     * date-scoped GET (see DATE_SCOPED). Returns the (possibly) updated data.
     */
    public static function fillDateRange($db, string $controller, string $methodName, array $data): array
    {
        if (!in_array($methodName, self::DATE_SCOPED[strtolower($controller)] ?? [], true)) return $data;
        if (!empty($data['date_from']) || !empty($data['date_to'])) return $data;
        $scope = new self($db);
        $yearId = $scope->positiveInt($data['academic_year_id'] ?? $data['year_id'] ?? null);
        if ($yearId <= 0) return $data;
        $year = $scope->year($yearId);
        if (!$year) return $data;
        $data['date_from'] = substr((string) $year['start_date'], 0, 10);
        $data['date_to'] = substr((string) $year['end_date'], 0, 10);
        return $data;
    }

    /**
     * The calendar-year number to store in a legacy `academic_year` YEAR
     * column (the 21 tables of migration 20261115_academic_year_id_beside_
     * calendar_year_columns; their triggers fill academic_year_id from it).
     *
     * Taken from, in order: academic_year_id / year_id (that year's start
     * year); academic_year given as a year code ("2026/2027") or a number;
     * else the CURRENT ACADEMIC YEAR's start year. Never the calendar year
     * of today: in January the school is often still in the previous
     * academic year until the year is closed.
     */
    public static function legacyYear($db, array $data): int
    {
        $scope = new self($db);
        $yearId = $scope->positiveInt($data['academic_year_id'] ?? $data['year_id'] ?? null);
        if ($yearId > 0) {
            $year = $scope->year($yearId);
            if (!$year) {
                throw new InvalidArgumentException('Academic year not found', 404);
            }
            return (int) substr((string) $year['start_date'], 0, 4);
        }
        $raw = trim((string) ($data['academic_year'] ?? ''));
        if (preg_match('/^(\d{4})/', $raw, $m) && (int) $m[1] >= 1900 && (int) $m[1] <= 2200) {
            return (int) $m[1];
        }
        $current = $scope->currentYear();
        return $current ? (int) substr((string) $current['start_date'], 0, 4) : (int) date('Y');
    }

    /** The current year's id, or 0 when none is marked current. */
    public function currentYearId(): int
    {
        $year = $this->currentYear();
        return $year ? (int) $year['id'] : 0;
    }

    private function currentYear(): ?array
    {
        $row = $this->db->query(
            'SELECT id, year_code, year_name, state, is_current, start_date, end_date
             FROM academic_years WHERE is_current = 1 ORDER BY start_date DESC, id DESC LIMIT 1'
        )->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    private function year(int $id): ?array
    {
        $stmt = $this->db->prepare(
            'SELECT id, year_code, year_name, state, is_current, start_date, end_date
             FROM academic_years WHERE id = ? LIMIT 1'
        );
        $stmt->execute([$id]);
        return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
    }

    private function term(int $id): ?array
    {
        $stmt = $this->db->prepare(
            'SELECT id, academic_year_id, term_id, opening_date, closing_date
             FROM academic_year_terms WHERE id = ? LIMIT 1'
        );
        $stmt->execute([$id]);
        return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
    }

    /** Term in session today, else the latest opened, else the first. */
    private function defaultTerm(int $yearId): ?array
    {
        $stmt = $this->db->prepare(
            'SELECT id, academic_year_id, term_id, opening_date, closing_date
             FROM academic_year_terms WHERE academic_year_id = ? ORDER BY opening_date, id'
        );
        $stmt->execute([$yearId]);
        $terms = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
        if (!$terms) return null;

        $today = $this->today ?? date('Y-m-d');
        $opened = null;
        foreach ($terms as $term) {
            $opening = substr((string) $term['opening_date'], 0, 10);
            $closing = substr((string) $term['closing_date'], 0, 10);
            if ($opening !== '' && $opening <= $today && ($closing === '' || $today <= $closing)) {
                return $term;
            }
            if ($opening !== '' && $opening <= $today) {
                $opened = $term;
            }
        }
        return $opened ?? $terms[0];
    }

    private function positiveInt($value): int
    {
        if (is_int($value)) return max(0, $value);
        if (is_string($value) && ctype_digit($value)) return (int) $value;
        return 0;
    }
}
