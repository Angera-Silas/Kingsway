<?php

declare(strict_types=1);

namespace App\API\Services;

use DomainException;
use PDO;

/**
 * Lessons per week for every learning area of every class in one academic
 * year (academic_year_class_learning_areas.lessons_per_week).
 *
 * This is the weekly lesson load the timetable draft solver places and that
 * Teacher Workload totals. A class's total may not exceed the lesson periods
 * of a school week (active lesson time slots x 5 weekdays), because every
 * lesson needs a period.
 *
 * Reads are single-table, keyed reads on the master schema (the editor must
 * see its own writes at once); there are no joins.
 */
final class ClassLessonLoadService
{
    public const SCHOOL_DAYS = 5;
    public const MAX_LESSONS_PER_AREA = 15;

    private static ?bool $columnReady = null;

    /** @param ?bool $columnReadyOverride test seam; production leaves it null */
    public function __construct(private PDO $db, private ?bool $columnReadyOverride = null)
    {
    }

    private function ready(): bool
    {
        return $this->columnReadyOverride ?? self::columnReady($this->db);
    }

    /**
     * Sort key for a class name in school order: Playgroup, PP1, PP2, then
     * Grade 1 to Grade 9; anything else after, by name.
     *
     * @return array{0:int,1:string}
     */
    public static function classOrder(string $name): array
    {
        $n = strtolower(trim($name));
        if (str_starts_with($n, 'playgroup') || str_starts_with($n, 'play group')) return [0, $n];
        if (preg_match('/^pp\s*([12])\b/', $n, $m)) return [(int) $m[1], $n];
        if (preg_match('/^grade\s*(\d+)/', $n, $m)) return [2 + (int) $m[1], $n];
        return [100, $n];
    }

    /** True once the lessons_per_week migration has been applied. */
    public static function columnReady(PDO $db): bool
    {
        if (self::$columnReady === null) {
            $stmt = $db->prepare(
                "SELECT COUNT(*) FROM information_schema.COLUMNS
                  WHERE TABLE_SCHEMA = DATABASE()
                    AND TABLE_NAME = 'academic_year_class_learning_areas'
                    AND COLUMN_NAME = 'lessons_per_week'"
            );
            $stmt->execute();
            self::$columnReady = (int) $stmt->fetchColumn() === 1;
        }
        return self::$columnReady;
    }

    /** Lesson periods in one school week. */
    public function lessonPeriodsPerWeek(): int
    {
        $count = (int) $this->db->query(
            "SELECT COUNT(*) FROM time_slots WHERE is_active = 1 AND slot_type = 'lesson'"
        )->fetchColumn();
        return $count * self::SCHOOL_DAYS;
    }

    /**
     * The year's classes, learning areas and weekly lessons as an editable grid.
     *
     * @return array{ready:bool, academic_year_id:int, lesson_periods_per_week:int,
     *   classes:list<array<string,mixed>>, learning_areas:list<array<string,mixed>>,
     *   cells:list<array<string,mixed>>}
     */
    public function grid(int $academicYearId): array
    {
        if ($academicYearId < 1) throw new DomainException('Select an academic year.', 422);
        $ready = $this->ready();

        $yearClasses = $this->rows(
            "SELECT id, class_id FROM academic_year_classes WHERE academic_year_id = ? AND status <> 'completed'",
            [$academicYearId]
        );
        $classIds = array_values(array_unique(array_map(static fn($r) => (int) $r['class_id'], $yearClasses)));
        $classes = $classIds ? $this->indexBy($this->rows(
            'SELECT id, name, level_id FROM classes WHERE id IN (' . $this->marks($classIds) . ')',
            $classIds
        ), 'id') : [];

        $yearClassIds = array_map(static fn($r) => (int) $r['id'], $yearClasses);
        $cells = $yearClassIds ? $this->rows(
            'SELECT id, academic_year_class_id, learning_area_id, '
            . ($ready ? 'lessons_per_week' : 'NULL AS lessons_per_week')
            . " FROM academic_year_class_learning_areas
               WHERE academic_year_class_id IN (" . $this->marks($yearClassIds) . ")
                 AND status <> 'skipped'",
            $yearClassIds
        ) : [];

        $areaIds = array_values(array_unique(array_map(static fn($r) => (int) $r['learning_area_id'], $cells)));
        $areas = $areaIds ? $this->rows(
            'SELECT id, name, code FROM learning_areas WHERE id IN (' . $this->marks($areaIds) . ') ORDER BY name',
            $areaIds
        ) : [];

        $classList = [];
        foreach ($yearClasses as $yc) {
            $class = $classes[(int) $yc['class_id']] ?? null;
            if (!$class) continue;
            $classList[] = [
                'academic_year_class_id' => (int) $yc['id'],
                'class_id' => (int) $yc['class_id'],
                'class_name' => (string) $class['name'],
                'level_id' => (int) ($class['level_id'] ?? 0),
            ];
        }
        usort($classList, static fn($a, $b) => self::classOrder($a['class_name']) <=> self::classOrder($b['class_name']));

        return [
            'ready' => $ready,
            'academic_year_id' => $academicYearId,
            'lesson_periods_per_week' => $this->lessonPeriodsPerWeek(),
            'classes' => $classList,
            'learning_areas' => array_map(static fn($a) => [
                'learning_area_id' => (int) $a['id'],
                'name' => (string) $a['name'],
                'code' => (string) ($a['code'] ?? ''),
            ], $areas),
            'cells' => array_map(static fn($c) => [
                'id' => (int) $c['id'],
                'academic_year_class_id' => (int) $c['academic_year_class_id'],
                'learning_area_id' => (int) $c['learning_area_id'],
                'lessons_per_week' => $c['lessons_per_week'] === null ? null : (int) $c['lessons_per_week'],
            ], $cells),
        ];
    }

    /**
     * Save weekly lessons for cells of one year. Each change is
     * {id, lessons_per_week} where id is the class learning-area row and
     * lessons_per_week is 0..MAX or null (not set). Rejects the whole save when
     * any class would need more lessons than the week has lesson periods.
     *
     * @param list<array<string,mixed>> $changes
     */
    public function save(int $academicYearId, array $changes): array
    {
        if (!$this->ready()) {
            throw new DomainException('Weekly lessons cannot be saved until the lessons-per-week database update is applied.', 409);
        }
        if (count($changes) > 2000) throw new DomainException('Too many changes in one save.', 422);
        $grid = $this->grid($academicYearId);
        $cells = $this->indexBy($grid['cells'], 'id');

        $values = [];
        foreach ($changes as $change) {
            if (!is_array($change)) throw new DomainException('Each change must be an object.', 422);
            $id = (int) ($change['id'] ?? 0);
            if (!isset($cells[$id])) throw new DomainException('A learning area in this save is not part of the selected academic year.', 422);
            $raw = $change['lessons_per_week'] ?? null;
            if ($raw === null || $raw === '') {
                $values[$id] = null;
                continue;
            }
            if (!is_numeric($raw) || (int) $raw != $raw || (int) $raw < 0 || (int) $raw > self::MAX_LESSONS_PER_AREA) {
                throw new DomainException('Lessons per week must be a whole number from 0 to ' . self::MAX_LESSONS_PER_AREA . '.', 422);
            }
            $values[$id] = (int) $raw;
        }

        // Validate the resulting totals per class against the school week.
        $limit = (int) $grid['lesson_periods_per_week'];
        $totals = [];
        foreach ($cells as $id => $cell) {
            $value = array_key_exists($id, $values) ? $values[$id] : $cell['lessons_per_week'];
            $totals[$cell['academic_year_class_id']] = ($totals[$cell['academic_year_class_id']] ?? 0) + (int) $value;
        }
        $names = [];
        foreach ($grid['classes'] as $class) $names[$class['academic_year_class_id']] = $class['class_name'];
        $over = [];
        foreach ($totals as $aycId => $total) {
            if ($limit > 0 && $total > $limit) $over[] = ($names[$aycId] ?? 'A class') . " needs $total";
        }
        if ($over) {
            throw new DomainException(
                'Each lesson needs a period, and the school week has ' . $limit . ' lesson periods. Too many lessons: '
                . implode('; ', array_slice($over, 0, 6)) . '.', 422
            );
        }

        $update = $this->db->prepare('UPDATE academic_year_class_learning_areas SET lessons_per_week = ? WHERE id = ?');
        $owns = !$this->db->inTransaction();
        if ($owns) $this->db->beginTransaction();
        try {
            $changed = 0;
            foreach ($values as $id => $value) {
                if ($cells[$id]['lessons_per_week'] === $value) continue;
                $update->execute([$value, $id]);
                $changed++;
            }
            if ($owns) $this->db->commit();
        } catch (\Throwable $e) {
            if ($owns && $this->db->inTransaction()) $this->db->rollBack();
            throw $e;
        }
        return ['changed' => $changed, 'grid' => $this->grid($academicYearId)];
    }

    /**
     * Copy weekly lessons into a new year: each class learning area of
     * $toYearId that has no value yet takes the value of the same class and
     * learning area in $fromYearId. Values already set are never overwritten.
     * Returns the number of rows filled (0 before the migration is applied).
     */
    public function carryForward(int $fromYearId, int $toYearId): int
    {
        if ($fromYearId < 1 || $toYearId < 1 || $fromYearId === $toYearId || !$this->ready()) return 0;
        $classOf = static function (array $rows): array {
            $out = [];
            foreach ($rows as $row) $out[(int) $row['id']] = (int) $row['class_id'];
            return $out;
        };
        $from = $classOf($this->rows('SELECT id, class_id FROM academic_year_classes WHERE academic_year_id = ?', [$fromYearId]));
        $to = array_flip($classOf($this->rows('SELECT id, class_id FROM academic_year_classes WHERE academic_year_id = ?', [$toYearId])));
        if (!$from || !$to) return 0;

        $source = $this->rows(
            'SELECT academic_year_class_id, learning_area_id, lessons_per_week FROM academic_year_class_learning_areas
              WHERE academic_year_class_id IN (' . $this->marks(array_keys($from)) . ")
                AND lessons_per_week IS NOT NULL AND status <> 'skipped'",
            array_keys($from)
        );
        $update = $this->db->prepare(
            'UPDATE academic_year_class_learning_areas SET lessons_per_week = ?
              WHERE academic_year_class_id = ? AND learning_area_id = ? AND lessons_per_week IS NULL'
        );
        $filled = 0;
        foreach ($source as $row) {
            $targetAyc = $to[$from[(int) $row['academic_year_class_id']] ?? 0] ?? 0;
            if (!$targetAyc) continue;
            $update->execute([(int) $row['lessons_per_week'], $targetAyc, (int) $row['learning_area_id']]);
            $filled += $update->rowCount();
        }
        return $filled;
    }

    private function rows(string $sql, array $params): array
    {
        $stmt = $this->db->prepare($sql);
        $stmt->execute(array_values($params));
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    private function indexBy(array $rows, string $key): array
    {
        $out = [];
        foreach ($rows as $row) $out[(int) $row[$key]] = $row;
        return $out;
    }

    private function marks(array $ids): string
    {
        return implode(',', array_fill(0, count($ids), '?'));
    }
}
