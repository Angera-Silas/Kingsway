<?php

declare(strict_types=1);

namespace App\API\Services;

use DomainException;
use PDO;

/**
 * The weekly teaching load of one academic year and term: for every open
 * stream, every learning area it takes, how many lessons a week it needs and
 * which teacher takes them.
 *
 * The teacher comes from the school admin's allocations for that term:
 *  - a stream allocation (academic_year_class_stream_learning_area_teachers)
 *    applies to that stream only and wins;
 *  - otherwise a class allocation (academic_year_class_learning_area_teachers)
 *    applies to every stream of the class.
 * Only subject teachers and HODs take lessons; assistants do not.
 *
 * Each lesson row carries a problem code when it cannot be timetabled as is:
 *   lessons_not_set   no weekly lessons recorded for the learning area
 *   no_teacher        nobody is allocated
 *   several_teachers  more than one teacher is allocated at the same level
 *
 * This is the single source for Teacher Workload and the timetable draft
 * solver. Reads are single-table keyed reads (no joins).
 */
final class TeachingLoadResolver
{
    private const TEACHING_ROLES = ['subject_teacher', 'hod'];

    public function __construct(private PDO $db, private ?bool $lessonsColumnReady = null)
    {
    }

    /**
     * @return array{academic_year_id:int, academic_year_term_id:int, lessons_ready:bool,
     *   lesson_periods_per_week:int, streams:list<array<string,mixed>>, lessons:list<array<string,mixed>>}
     */
    public function resolve(int $academicYearId, int $academicYearTermId): array
    {
        if ($academicYearId < 1 || $academicYearTermId < 1) {
            throw new DomainException('Select an academic year and term.', 422);
        }
        $term = $this->rows('SELECT id, academic_year_id FROM academic_year_terms WHERE id = ?', [$academicYearTermId]);
        if (!$term || (int) $term[0]['academic_year_id'] !== $academicYearId) {
            throw new DomainException('The selected term does not belong to the selected academic year.', 422);
        }
        $lessonsReady = $this->lessonsColumnReady ?? ClassLessonLoadService::columnReady($this->db);
        $loads = new ClassLessonLoadService($this->db, $lessonsReady);

        $yearClasses = $this->rows(
            "SELECT id, class_id FROM academic_year_classes WHERE academic_year_id = ? AND status <> 'completed'",
            [$academicYearId]
        );
        $result = [
            'academic_year_id' => $academicYearId,
            'academic_year_term_id' => $academicYearTermId,
            'lessons_ready' => $lessonsReady,
            'lesson_periods_per_week' => $loads->lessonPeriodsPerWeek(),
            'streams' => [],
            'lessons' => [],
        ];
        if (!$yearClasses) return $result;

        $classIdByAyc = [];
        foreach ($yearClasses as $row) $classIdByAyc[(int) $row['id']] = (int) $row['class_id'];
        $classes = $this->byId($this->in('SELECT id, name, level_id FROM classes WHERE id IN (%s)', array_values(array_unique($classIdByAyc))));

        $streams = $this->in(
            "SELECT id, academic_year_class_id, stream_id FROM academic_year_class_streams
              WHERE academic_year_class_id IN (%s) AND status IN ('active', 'planning')",
            array_keys($classIdByAyc)
        );
        if (!$streams) return $result;
        $streamNames = $this->byId($this->in('SELECT id, name FROM streams WHERE id IN (%s)', array_values(array_unique(array_map(static fn($s) => (int) $s['stream_id'], $streams)))));

        $cells = $this->byId($this->in(
            'SELECT id, academic_year_class_id, learning_area_id, '
            . ($lessonsReady ? 'lessons_per_week' : 'NULL AS lessons_per_week')
            . " FROM academic_year_class_learning_areas WHERE academic_year_class_id IN (%s) AND status <> 'skipped'",
            array_keys($classIdByAyc)
        ));
        $streamIds = array_map(static fn($s) => (int) $s['id'], $streams);
        $links = $cells ? $this->in(
            "SELECT academic_year_class_stream_id, academic_year_class_learning_area_id
               FROM academic_year_class_stream_learning_areas
              WHERE academic_year_class_stream_id IN (%s) AND status <> 'skipped'",
            $streamIds
        ) : [];
        $areaNames = $cells ? $this->byId($this->in(
            'SELECT id, name FROM learning_areas WHERE id IN (%s)',
            array_values(array_unique(array_map(static fn($c) => (int) $c['learning_area_id'], $cells)))
        )) : [];

        // Allocations for the term.
        $classTeachers = [];   // ayc_la id -> [staff ids]
        if ($cells) {
            foreach ($this->in(
                'SELECT academic_year_class_learning_area_id, staff_id, role FROM academic_year_class_learning_area_teachers
                  WHERE academic_year_class_learning_area_id IN (%s) AND academic_year_term_id = ?',
                array_keys($cells), [$academicYearTermId]
            ) as $row) {
                if (!in_array((string) $row['role'], self::TEACHING_ROLES, true)) continue;
                $classTeachers[(int) $row['academic_year_class_learning_area_id']][(int) $row['staff_id']] = true;
            }
        }
        $streamTeachers = [];  // "aycs:area" -> [staff ids]
        foreach ($this->in(
            "SELECT academic_year_class_stream_id, learning_area_id, staff_id, role
               FROM academic_year_class_stream_learning_area_teachers
              WHERE academic_year_class_stream_id IN (%s) AND academic_year_term_id = ? AND status = 'active'",
            $streamIds, [$academicYearTermId]
        ) as $row) {
            if (!in_array((string) $row['role'], self::TEACHING_ROLES, true)) continue;
            $streamTeachers[(int) $row['academic_year_class_stream_id'] . ':' . (int) $row['learning_area_id']][(int) $row['staff_id']] = true;
        }

        $streamById = [];
        foreach ($streams as $s) {
            $aycId = (int) $s['academic_year_class_id'];
            $class = $classes[$classIdByAyc[$aycId] ?? 0] ?? null;
            $streamById[(int) $s['id']] = [
                'academic_year_class_stream_id' => (int) $s['id'],
                'academic_year_class_id' => $aycId,
                'class_id' => (int) ($classIdByAyc[$aycId] ?? 0),
                'class_name' => (string) ($class['name'] ?? ''),
                'level_id' => (int) ($class['level_id'] ?? 0),
                'stream_name' => (string) ($streamNames[(int) $s['stream_id']]['name'] ?? ''),
            ];
        }
        $streamList = array_values($streamById);
        usort($streamList, static fn($a, $b) => [ClassLessonLoadService::classOrder($a['class_name']), $a['stream_name']] <=> [ClassLessonLoadService::classOrder($b['class_name']), $b['stream_name']]);
        $result['streams'] = $streamList;

        $lessons = [];
        foreach ($links as $link) {
            $streamId = (int) $link['academic_year_class_stream_id'];
            $cell = $cells[(int) $link['academic_year_class_learning_area_id']] ?? null;
            if (!$cell || !isset($streamById[$streamId])) continue;
            $areaId = (int) $cell['learning_area_id'];
            $fromStream = array_keys($streamTeachers[$streamId . ':' . $areaId] ?? []);
            $fromClass = array_keys($classTeachers[(int) $cell['id']] ?? []);
            $teachers = $fromStream ?: $fromClass;
            $source = $fromStream ? 'stream' : ($fromClass ? 'class' : null);
            $perWeek = $cell['lessons_per_week'] === null ? null : (int) $cell['lessons_per_week'];
            $problem = null;
            if ($perWeek === null) $problem = 'lessons_not_set';
            elseif ($perWeek > 0 && !$teachers) $problem = 'no_teacher';
            elseif ($perWeek > 0 && count($teachers) > 1) $problem = 'several_teachers';
            $lessons[] = [
                'academic_year_class_stream_id' => $streamId,
                'academic_year_class_learning_area_id' => (int) $cell['id'],
                'learning_area_id' => $areaId,
                'learning_area_name' => (string) ($areaNames[$areaId]['name'] ?? ''),
                'lessons_per_week' => $perWeek,
                'teacher_id' => count($teachers) === 1 ? (int) $teachers[0] : null,
                'teacher_ids' => array_map('intval', $teachers),
                'teacher_source' => $source,
                'problem' => $problem,
            ];
        }
        usort($lessons, static fn($a, $b) => [$a['academic_year_class_stream_id'], $a['learning_area_name']] <=> [$b['academic_year_class_stream_id'], $b['learning_area_name']]);
        $result['lessons'] = $lessons;
        return $result;
    }

    /**
     * Weekly lessons per teacher for the year and term, with their streams and
     * learning areas, plus the rows that block a complete total.
     */
    public function workload(int $academicYearId, int $academicYearTermId): array
    {
        $load = $this->resolve($academicYearId, $academicYearTermId);
        $streams = [];
        foreach ($load['streams'] as $s) $streams[$s['academic_year_class_stream_id']] = $s;

        $teachers = [];
        foreach ($load['lessons'] as $lesson) {
            foreach ($lesson['teacher_ids'] as $teacherId) {
                $t = &$teachers[$teacherId];
                $t ??= ['teacher_id' => $teacherId, 'lessons_per_week' => 0, 'unset_lessons' => 0, 'streams' => [], 'learning_areas' => [], 'allocations' => 0];
                $t['allocations']++;
                if ($lesson['lessons_per_week'] === null) $t['unset_lessons']++;
                elseif (count($lesson['teacher_ids']) === 1) $t['lessons_per_week'] += $lesson['lessons_per_week'];
                $s = $streams[$lesson['academic_year_class_stream_id']] ?? null;
                if ($s) $t['streams'][$s['academic_year_class_stream_id']] = trim($s['class_name'] . ' ' . $s['stream_name']);
                $t['learning_areas'][$lesson['learning_area_id']] = $lesson['learning_area_name'];
                unset($t);
            }
        }
        $names = $this->teacherRecords(array_keys($teachers));
        $list = [];
        foreach ($teachers as $id => $t) {
            $person = $names[$id] ?? [];
            $list[] = [
                'teacher_id' => $id,
                'teacher_name' => trim(($person['first_name'] ?? '') . ' ' . ($person['last_name'] ?? '')) ?: ('Staff #' . $id),
                'department_name' => (string) ($person['department_name'] ?? ''),
                'lessons_per_week' => $t['lessons_per_week'],
                'unset_lessons' => $t['unset_lessons'],
                'allocations' => $t['allocations'],
                'streams' => array_values($t['streams']),
                'learning_areas' => array_values($t['learning_areas']),
            ];
        }
        usort($list, static fn($a, $b) => [$b['lessons_per_week'], $a['teacher_name']] <=> [$a['lessons_per_week'], $b['teacher_name']]);

        $problems = ['lessons_not_set' => 0, 'no_teacher' => 0, 'several_teachers' => 0];
        foreach ($load['lessons'] as $lesson) if ($lesson['problem']) $problems[$lesson['problem']]++;
        return [
            'academic_year_id' => $load['academic_year_id'],
            'academic_year_term_id' => $load['academic_year_term_id'],
            'lessons_ready' => $load['lessons_ready'],
            'lesson_periods_per_week' => $load['lesson_periods_per_week'],
            'teachers' => $list,
            'problems' => $problems,
        ];
    }

    /** @return array<int, array{staff_id:int, first_name:?string, last_name:?string, department_name:?string}> */
    public function teacherRecords(array $staffIds): array
    {
        $staffIds = array_values(array_unique(array_filter(array_map('intval', $staffIds))));
        return $staffIds ? $this->byKey($this->in(
            'SELECT staff_id, first_name, last_name, department_name FROM ' . ReadReplicaService::qualifiedRef('staff_context') . ' WHERE staff_id IN (%s)',
            $staffIds
        ), 'staff_id') : [];
    }

    private function rows(string $sql, array $params): array
    {
        $stmt = $this->db->prepare($sql);
        $stmt->execute(array_values($params));
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /** Run $sql with %s replaced by placeholders for $ids, then $extra params. */
    private function in(string $sql, array $ids, array $extra = []): array
    {
        $ids = array_values(array_map('intval', $ids));
        if (!$ids) return [];
        return $this->rows(sprintf($sql, implode(',', array_fill(0, count($ids), '?'))), array_merge($ids, $extra));
    }

    private function byId(array $rows): array
    {
        return $this->byKey($rows, 'id');
    }

    private function byKey(array $rows, string $key): array
    {
        $out = [];
        foreach ($rows as $row) $out[(int) $row[$key]] = $row;
        return $out;
    }
}
