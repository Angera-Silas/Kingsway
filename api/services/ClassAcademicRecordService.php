<?php

namespace App\API\Services;

use App\Database\Database;
use PDO;
use RuntimeException;

/**
 * Class records: one class stream in one academic year, and the same group
 * of learners (the cohort) followed through every year they were together.
 *
 * Naming (user, 2026-11-15): a class is named by the academic year it sits
 * in: "Grade 1 A · Class of 2026" is Grade 1, stream A, in the academic year
 * that starts in 2026 (2026/2027). The same rule names graduates: the
 * Grade 9 learners of 2026/2027 are the "graduating class of 2026".
 *
 * Class teachers usually keep a cohort for a phase (Grades 1-3, 4-6, 7-9),
 * so the cohort history shows, year by year, the class each learner was in,
 * that class's teacher and the cohort's results per learning area and term.
 *
 * Counting rules (the same as every year-aware report): a year's roll is the
 * enrolments with status active, completed (promoted) or graduated, counted
 * once per learner; transferred and withdrawn learners are listed apart. A
 * year is academic_years.id and a term academic_year_terms.id. Only term
 * results that carry evidence (assessment_count > 0) are shown.
 *
 * All reads go to KingsWayReads projections (Hard Rule 1).
 */
class ClassAcademicRecordService
{
    private const ROLL = ['active', 'completed', 'graduated'];
    private const LEFT = ['transferred', 'withdrawn'];

    private PDO $db;

    /** @var callable(string):string */
    private $ref;

    public function __construct(?PDO $db = null, ?callable $ref = null)
    {
        $this->db = $db ?? Database::getInstance()->getConnection();
        $this->ref = $ref ?? static fn (string $projection): string => ReadReplicaService::qualifiedRef($projection);
    }

    private function ref(string $projection): string
    {
        return ($this->ref)($projection);
    }

    /** "Grade 1 A · Class of 2026" */
    public static function cohortLabel(?string $className, ?string $streamName, $yearStartDate): string
    {
        $name = trim((string) $className);
        $stream = trim((string) $streamName);
        if ($stream !== '' && strcasecmp($stream, $name) !== 0) {
            $name .= ' ' . $stream;
        }
        $year = self::cohortYear($yearStartDate);
        return $year ? "{$name} · Class of {$year}" : $name;
    }

    /** The calendar year that names a class: the start year of its academic year. */
    public static function cohortYear($yearStartDate): ?int
    {
        $year = (int) substr((string) $yearStartDate, 0, 4);
        return $year > 1900 ? $year : null;
    }

    /**
     * The class streams of one academic year, optionally limited to the given
     * academic_year_class_streams ids (a teacher's own classes).
     *
     * @param int[]|null $onlyStreamIds
     */
    public function streamsForYear(int $yearId, ?array $onlyStreamIds = null): array
    {
        if ($onlyStreamIds !== null && !$onlyStreamIds) {
            return [];
        }
        $params = [$yearId];
        $limit = '';
        if ($onlyStreamIds !== null) {
            $limit = ' AND aycs.id IN (' . implode(',', array_fill(0, count($onlyStreamIds), '?')) . ')';
            $params = array_merge($params, array_map('intval', $onlyStreamIds));
        }
        $rows = $this->rows(
            "SELECT aycs.id AS academic_year_class_stream_id, aycs.class_teacher_id,
                    ayc.id AS academic_year_class_id, ayc.academic_year_id,
                    c.id AS class_id, c.name AS class_name, st.name AS stream_name,
                    ay.year_code, ay.start_date
             FROM " . $this->ref('academic_year_class_streams') . " aycs
             JOIN " . $this->ref('academic_year_classes') . " ayc ON ayc.id = aycs.academic_year_class_id
             JOIN " . $this->ref('academic_years') . " ay ON ay.id = ayc.academic_year_id
             JOIN " . $this->ref('classes') . " c ON c.id = ayc.class_id
             LEFT JOIN " . $this->ref('streams') . " st ON st.id = aycs.stream_id
             WHERE ayc.academic_year_id = ?{$limit}
             ORDER BY c.id, st.name",
            $params
        );
        $rollCounts = [];
        foreach ($this->rows(
            "SELECT aycs_id, COUNT(DISTINCT student_id) AS learners
             FROM " . $this->ref('learner_placement_history') . "
             WHERE academic_year_id = ? AND enrollment_status IN ('active','completed','graduated')
             GROUP BY aycs_id",
            [$yearId]
        ) as $row) {
            $rollCounts[(int) $row['aycs_id']] = (int) $row['learners'];
        }
        $teachers = $this->staffNames(array_column($rows, 'class_teacher_id'));
        return array_map(function (array $row) use ($rollCounts, $teachers): array {
            $id = (int) $row['academic_year_class_stream_id'];
            return [
                'academic_year_class_stream_id' => $id,
                'academic_year_id' => (int) $row['academic_year_id'],
                'academic_year' => $row['year_code'],
                'class_id' => (int) $row['class_id'],
                'class_name' => $row['class_name'],
                'stream_name' => $row['stream_name'],
                'cohort_year' => self::cohortYear($row['start_date']),
                'label' => self::cohortLabel($row['class_name'], $row['stream_name'], $row['start_date']),
                'class_teacher' => $teachers[(int) $row['class_teacher_id']] ?? null,
                'learners' => $rollCounts[$id] ?? 0,
            ];
        }, $rows);
    }

    /**
     * The class record of one class stream in its year: every learner on the
     * roll against every learning area, Terms 1-3, with class averages.
     */
    public function forStream(int $streamId): array
    {
        $class = $this->streamHeader($streamId);
        $yearId = $class['academic_year_id'];
        $terms = $this->terms($yearId);
        $termIds = array_column($terms, 'academic_year_term_id');

        $placements = $this->rows(
            "SELECT student_id, enrollment_id, enrollment_status
             FROM " . $this->ref('learner_placement_history') . "
             WHERE aycs_id = ? AND enrollment_status IN ('active','completed','graduated','transferred','withdrawn')
             ORDER BY student_id",
            [$streamId]
        );
        $studentIds = array_values(array_unique(array_map(static fn ($p) => (int) $p['student_id'], $placements)));
        $people = $this->learnerNames($studentIds);

        $areas = $this->learningAreas((int) $class['academic_year_class_id'], $streamId, $termIds);
        $scores = $this->termScores($studentIds, $termIds);
        foreach ($scores as $areaId => $_) {
            $areas[$areaId] ??= 'Learning area ' . $areaId;
        }
        asort($areas);

        $learners = [];
        $left = [];
        $areaTotals = [];
        foreach ($placements as $placement) {
            $sid = (int) $placement['student_id'];
            $results = [];
            $termSums = [];
            foreach ($areas as $areaId => $_) {
                foreach ($termIds as $tid) {
                    $score = $scores[$areaId][$tid][$sid] ?? null;
                    if ($score === null) continue;
                    $results[$areaId][$tid] = $score;
                    $termSums[$tid][] = $score['overall_percentage'];
                    $areaTotals[$areaId][$tid][] = $score;
                }
            }
            $row = [
                'student_id' => $sid,
                'admission_no' => $people[$sid]['admission_no'] ?? null,
                'name' => $people[$sid]['name'] ?? ('Learner ' . $sid),
                'gender' => $people[$sid]['gender'] ?? null,
                'enrollment_status' => $placement['enrollment_status'],
                'results' => $results,
                'term_averages' => array_map(static fn (array $v) => round(array_sum($v) / count($v), 1), $termSums),
            ];
            if (in_array($placement['enrollment_status'], self::LEFT, true)) {
                $left[] = $row;
            } else {
                $learners[] = $row;
            }
        }
        usort($learners, static fn ($a, $b) => strcasecmp($a['name'], $b['name']));
        usort($left, static fn ($a, $b) => strcasecmp($a['name'], $b['name']));

        $assessed = $this->assessmentCounts($streamId, $termIds);
        $summary = [];
        foreach ($areas as $areaId => $name) {
            $termRows = [];
            foreach ($terms as $term) {
                $tid = $term['academic_year_term_id'];
                $list = $areaTotals[$areaId][$tid] ?? [];
                $bands = [];
                foreach ($list as $score) {
                    $grade = (string) ($score['grade'] ?? '');
                    if ($grade !== '') $bands[$grade] = ($bands[$grade] ?? 0) + 1;
                }
                ksort($bands);
                $termRows[] = [
                    'academic_year_term_id' => $tid,
                    'term' => $term['term'],
                    'learners_assessed' => count($list),
                    'average_percentage' => $list ? round(array_sum(array_column($list, 'overall_percentage')) / count($list), 1) : null,
                    'grade_bands' => $bands,
                    'assessments_approved' => $assessed[$areaId][$tid]['approved'] ?? 0,
                    'assessments_pending' => $assessed[$areaId][$tid]['pending'] ?? 0,
                ];
            }
            $summary[] = ['learning_area_id' => $areaId, 'name' => $name, 'terms' => $termRows];
        }

        return [
            'class' => $class,
            'terms' => $terms,
            'learning_areas' => $summary,
            'learners' => $learners,
            'left_during_year' => $left,
            'roll' => count($learners),
            'rules' => 'The roll is the learners enrolled in this class this year (active, promoted or graduated), counted once. Learners who transferred or withdrew are listed apart. Only term results with approved evidence are shown.',
        ];
    }

    /**
     * The learners on a class stream's roll, followed through every academic
     * year they were enrolled: their class(es) that year with its teacher,
     * and the cohort's average per learning area and term.
     */
    public function cohortHistory(int $streamId): array
    {
        $class = $this->streamHeader($streamId);
        $cohort = array_values(array_unique(array_map('intval', array_column($this->rows(
            "SELECT student_id FROM " . $this->ref('learner_placement_history') . "
             WHERE aycs_id = ? AND enrollment_status IN ('active','completed','graduated')",
            [$streamId]
        ), 'student_id'))));
        if (!$cohort) {
            return ['class' => $class, 'cohort_size' => 0, 'years' => [], 'learning_area_trends' => []];
        }

        $in = implode(',', array_fill(0, count($cohort), '?'));
        $placements = $this->rows(
            "SELECT student_id, academic_year_id, aycs_id, class_name, stream_name, enrollment_status
             FROM " . $this->ref('learner_placement_history') . "
             WHERE student_id IN ({$in}) AND enrollment_status IN ('active','completed','graduated')",
            $cohort
        );
        $byYear = [];
        foreach ($placements as $p) {
            $byYear[(int) $p['academic_year_id']][] = $p;
        }

        $years = $this->rows(
            'SELECT id, year_code, start_date, is_current FROM ' . $this->ref('academic_years') . '
             WHERE id IN (' . implode(',', array_keys($byYear)) . ') ORDER BY start_date, id'
        );

        $streamTeachers = [];
        $streamIds = array_values(array_unique(array_map(static fn ($p) => (int) $p['aycs_id'], $placements)));
        if ($streamIds) {
            $rows = $this->rows(
                'SELECT id, class_teacher_id FROM ' . $this->ref('academic_year_class_streams') . '
                 WHERE id IN (' . implode(',', $streamIds) . ')'
            );
            $names = $this->staffNames(array_column($rows, 'class_teacher_id'));
            foreach ($rows as $row) {
                $streamTeachers[(int) $row['id']] = $names[(int) $row['class_teacher_id']] ?? null;
            }
        }

        $sections = [];
        $trends = [];
        foreach ($years as $year) {
            $yearId = (int) $year['id'];
            $terms = $this->terms($yearId);
            $termIds = array_column($terms, 'academic_year_term_id');
            $members = array_values(array_unique(array_map(static fn ($p) => (int) $p['student_id'], $byYear[$yearId])));

            $classes = [];
            foreach ($byYear[$yearId] as $p) {
                $sid = (int) $p['aycs_id'];
                $classes[$sid] ??= [
                    'academic_year_class_stream_id' => $sid,
                    'label' => self::cohortLabel($p['class_name'], $p['stream_name'], $year['start_date']),
                    'class_teacher' => $streamTeachers[$sid] ?? null,
                    'cohort_learners' => 0,
                ];
                $classes[$sid]['cohort_learners']++;
            }

            $yearScores = $this->termScores($members, $termIds);
            $names = $this->learningAreaNames(array_keys($yearScores));
            $areaRows = [];
            foreach ($yearScores as $areaId => $byTerm) {
                $name = $names[$areaId] ?? ('Learning area ' . $areaId);
                $termRows = [];
                foreach ($terms as $term) {
                    $list = array_values($byTerm[$term['academic_year_term_id']] ?? []);
                    $avg = $list ? round(array_sum(array_column($list, 'overall_percentage')) / count($list), 1) : null;
                    $termRows[] = [
                        'academic_year_term_id' => $term['academic_year_term_id'],
                        'term' => $term['term'],
                        'learners_assessed' => count($list),
                        'average_percentage' => $avg,
                    ];
                    if ($avg !== null) {
                        $trends[$name][] = ['academic_year' => $year['year_code'], 'term' => $term['term'], 'average_percentage' => $avg];
                    }
                }
                $areaRows[] = ['learning_area_id' => $areaId, 'name' => $name, 'terms' => $termRows];
            }
            usort($areaRows, static fn ($a, $b) => strcasecmp($a['name'], $b['name']));

            $sections[] = [
                'academic_year_id' => $yearId,
                'academic_year' => $year['year_code'],
                'cohort_year' => self::cohortYear($year['start_date']),
                'is_current' => (int) $year['is_current'] === 1,
                'cohort_learners' => count($members),
                'classes' => array_values($classes),
                'terms' => $terms,
                'learning_areas' => $areaRows,
            ];
        }
        ksort($trends);

        return [
            'class' => $class,
            'cohort_size' => count($cohort),
            'years' => $sections,
            'learning_area_trends' => array_map(
                static fn (string $name, array $points) => ['learning_area' => $name, 'points' => $points],
                array_keys($trends),
                array_values($trends)
            ),
            'rules' => 'The cohort is the learners on this class\'s roll. Each year shows the class they were in that year, its class teacher and their average per learning area and term.',
        ];
    }

    /** academic_year_id of a class stream, or 0. */
    public function yearOfStream(int $streamId): int
    {
        $row = $this->row(
            'SELECT ayc.academic_year_id FROM ' . $this->ref('academic_year_class_streams') . ' aycs
             JOIN ' . $this->ref('academic_year_classes') . ' ayc ON ayc.id = aycs.academic_year_class_id
             WHERE aycs.id = ?',
            [$streamId]
        );
        return $row ? (int) $row['academic_year_id'] : 0;
    }

    private function streamHeader(int $streamId): array
    {
        $rows = $this->rows(
            "SELECT ayc.academic_year_id FROM " . $this->ref('academic_year_class_streams') . " aycs
             JOIN " . $this->ref('academic_year_classes') . " ayc ON ayc.id = aycs.academic_year_class_id
             WHERE aycs.id = ?",
            [$streamId]
        );
        if (!$rows) {
            throw new RuntimeException('Class not found.');
        }
        $streams = $this->streamsForYear((int) $rows[0]['academic_year_id'], [$streamId]);
        if (!$streams) {
            throw new RuntimeException('Class not found.');
        }
        $header = $streams[0];
        $header['academic_year_class_id'] = (int) $this->row(
            'SELECT academic_year_class_id FROM ' . $this->ref('academic_year_class_streams') . ' WHERE id = ?',
            [$streamId]
        )['academic_year_class_id'];
        return $header;
    }

    private function terms(int $yearId): array
    {
        $terms = $this->rows(
            'SELECT id, term_id FROM ' . $this->ref('academic_year_terms') . '
             WHERE academic_year_id = ? ORDER BY opening_date, id',
            [$yearId]
        );
        $names = [];
        $numbers = array_values(array_unique(array_filter(array_map(static fn ($t) => (int) $t['term_id'], $terms))));
        if ($numbers) {
            foreach ($this->rows(
                'SELECT id, name FROM terms WHERE id IN (' . implode(',', array_fill(0, count($numbers), '?')) . ')',
                $numbers
            ) as $row) {
                $names[(int) $row['id']] = $row['name'];
            }
        }
        return array_map(static fn ($t) => [
            'academic_year_term_id' => (int) $t['id'],
            'term' => $names[(int) $t['term_id']] ?? ('Term ' . (int) $t['term_id']),
        ], $terms);
    }

    /** Learning areas taught to the class that year, plus any assessed in the stream. */
    private function learningAreas(int $yearClassId, int $streamId, array $termIds): array
    {
        $termIn = implode(',', array_map('intval', $termIds ?: [0]));
        $areas = [];
        foreach ($this->rows(
            "SELECT DISTINCT la.id, la.name
             FROM " . $this->ref('academic_year_class_learning_areas') . " cla
             JOIN " . $this->ref('learning_areas') . " la ON la.id = cla.learning_area_id
             WHERE cla.academic_year_class_id = ?
             UNION
             SELECT DISTINCT la.id, la.name
             FROM " . $this->ref('assessments') . " a
             JOIN " . $this->ref('learning_areas') . " la ON la.id = a.learning_area_id
             WHERE a.academic_year_class_stream_id = ? AND a.academic_year_term_id IN ({$termIn})",
            [$yearClassId, $streamId]
        ) as $row) {
            $areas[(int) $row['id']] = $row['name'];
        }
        return $areas;
    }

    private function learningAreaNames(array $ids): array
    {
        $ids = array_values(array_filter(array_map('intval', $ids)));
        if (!$ids) return [];
        $names = [];
        foreach ($this->rows(
            'SELECT id, name FROM ' . $this->ref('learning_areas') . ' WHERE id IN (' . implode(',', $ids) . ')'
        ) as $row) {
            $names[(int) $row['id']] = $row['name'];
        }
        return $names;
    }

    /**
     * Term results with evidence: [area][year term][student] => score.
     *
     * @param int[] $studentIds
     * @param int[] $termIds
     */
    private function termScores(array $studentIds, array $termIds): array
    {
        if (!$studentIds || !$termIds) return [];
        $out = [];
        foreach ($this->rows(
            "SELECT student_id, subject_id, academic_year_term_id, overall_percentage, overall_grade, assessment_count
             FROM " . $this->ref('term_subject_scores') . "
             WHERE student_id IN (" . implode(',', array_map('intval', $studentIds)) . ")
               AND academic_year_term_id IN (" . implode(',', array_map('intval', $termIds)) . ")
               AND assessment_count > 0 AND overall_percentage IS NOT NULL"
        ) as $row) {
            $out[(int) $row['subject_id']][(int) $row['academic_year_term_id']][(int) $row['student_id']] = [
                'overall_percentage' => round((float) $row['overall_percentage'], 1),
                'grade' => $row['overall_grade'],
                'assessments' => (int) $row['assessment_count'],
            ];
        }
        return $out;
    }

    /** [area][term] => {approved, pending} assessments set for the stream. */
    private function assessmentCounts(int $streamId, array $termIds): array
    {
        if (!$termIds) return [];
        $out = [];
        foreach ($this->rows(
            "SELECT learning_area_id, academic_year_term_id, SUM(status = 'approved') AS approved, SUM(status <> 'approved') AS pending
             FROM " . $this->ref('assessments') . "
             WHERE academic_year_class_stream_id = ?
               AND academic_year_term_id IN (" . implode(',', array_map('intval', $termIds)) . ")
             GROUP BY learning_area_id, academic_year_term_id",
            [$streamId]
        ) as $row) {
            $out[(int) $row['learning_area_id']][(int) $row['academic_year_term_id']] = [
                'approved' => (int) $row['approved'],
                'pending' => (int) $row['pending'],
            ];
        }
        return $out;
    }

    private function learnerNames(array $studentIds): array
    {
        $studentIds = array_values(array_filter(array_map('intval', $studentIds)));
        if (!$studentIds) return [];
        $out = [];
        foreach ($this->rows(
            'SELECT student_id, admission_no, full_name, gender FROM ' . $this->ref('person_directory') . '
             WHERE student_id IN (' . implode(',', $studentIds) . ')'
        ) as $row) {
            $out[(int) $row['student_id']] = [
                'admission_no' => $row['admission_no'],
                'name' => trim((string) $row['full_name']),
                'gender' => $row['gender'],
            ];
        }
        return $out;
    }

    private function staffNames(array $staffIds): array
    {
        $staffIds = array_values(array_unique(array_filter(array_map('intval', $staffIds))));
        if (!$staffIds) return [];
        $out = [];
        foreach ($this->rows(
            'SELECT staff_id, full_name FROM ' . $this->ref('person_directory') . '
             WHERE staff_id IN (' . implode(',', $staffIds) . ')'
        ) as $row) {
            $out[(int) $row['staff_id']] = trim((string) $row['full_name']);
        }
        return $out;
    }

    private function rows(string $sql, array $params = []): array
    {
        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    private function row(string $sql, array $params = []): ?array
    {
        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
    }
}
