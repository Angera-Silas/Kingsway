<?php

namespace App\API\Services;

use App\Database\Database;
use InvalidArgumentException;
use PDO;

/**
 * National assessment results (KNEC): KPSEA for Grade 6, KJSEA for Grade 9
 * (and the Grade 3 assessment), uploaded by the school when the Ministry of
 * Education releases them.
 *
 * A result belongs to the SITTING: the exam type and the academic year the
 * learner sat it in. Results are released in December or January, usually
 * after year-end promotion, so the candidates are that year's Grade 6 /
 * Grade 9 ROLL (active, completed or graduated enrolments), in the class and
 * stream of that year, however they have moved on since. "KJSEA 2026" is the
 * Grade 9 class of 2026/2027 (the graduating class of 2026).
 *
 * Upload: the results file is read in the browser and sent as rows keyed by
 * column header. Learners are matched by KNEC assessment number or
 * admission number, only among that sitting's candidates; learning-area
 * columns are matched by name to the learning areas the class took that
 * year. Every unmatched row, column or value is reported in the preview and
 * never saved silently. Saving is all-or-nothing.
 *
 * Grades are stored as released (raw_grade, e.g. "ME1"), with the
 * expectation band (EE/ME/AE/BE) and points. Points follow the KNEC scales
 * below unless the file gives its own points column.
 */
class NationalResultsService
{
    public const EXAMS = [
        'KPSEA_G6' => ['label' => 'KPSEA', 'name' => 'Kenya Primary School Education Assessment', 'class' => 'Grade 6',
            'levels' => ['EE' => 4, 'ME' => 3, 'AE' => 2, 'BE' => 1]],
        'KJSEA_G9' => ['label' => 'KJSEA', 'name' => 'Kenya Junior School Education Assessment', 'class' => 'Grade 9',
            'levels' => ['EE1' => 8, 'EE2' => 7, 'ME1' => 6, 'ME2' => 5, 'AE1' => 4, 'AE2' => 3, 'BE1' => 2, 'BE2' => 1]],
        'KNEC_G3' => ['label' => 'Grade 3 assessment', 'name' => 'KNEC Grade 3 school-based assessment', 'class' => 'Grade 3',
            'levels' => ['EE' => 4, 'ME' => 3, 'AE' => 2, 'BE' => 1]],
    ];

    private const ROLL = "('active','completed','graduated')";
    private const PATHWAYS = ['ast' => 'AST', 'arts' => 'AST', 'artsandsports' => 'AST', 'artsandsportsscience' => 'AST',
        'stem' => 'STEM', 'socialsciences' => 'Social_Sciences', 'social' => 'Social_Sciences', 'humanities' => 'Humanities'];

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

    /**
     * Every sitting the school has candidates for, newest first, and the
     * default one: the latest year whose national papers have been written
     * (results are awaited or released), else the current year.
     */
    public function sittings(?string $today = null): array
    {
        $today = $today ?? date('Y-m-d');
        $years = $this->rows('SELECT id, year_code, start_date, is_current FROM ' . $this->ref('academic_years') . ' ORDER BY start_date DESC, id DESC');
        $windows = [];
        foreach ($this->rows(
            'SELECT i.code, w.academic_year, w.papers_end FROM national_assessment_windows w
             JOIN assessment_policy_instruments i ON i.id = w.instrument_id WHERE w.status <> \'superseded\''
        ) as $w) {
            $windows[strtoupper((string) $w['code'])][(int) $w['academic_year']] = (string) $w['papers_end'];
        }
        $uploaded = [];
        foreach ($this->rows(
            'SELECT exam_type, academic_year_id, COUNT(*) AS n FROM national_exam_candidates GROUP BY exam_type, academic_year_id'
        ) as $row) {
            $uploaded[$row['exam_type']][(int) $row['academic_year_id']] = (int) $row['n'];
        }

        $out = [];
        $default = null;
        foreach ($years as $year) {
            $yearId = (int) $year['id'];
            $calendar = (int) substr((string) $year['start_date'], 0, 4);
            foreach (self::EXAMS as $type => $exam) {
                $candidates = count($this->candidates($type, $yearId));
                if (!$candidates) continue;
                $papersEnd = $windows[strtoupper($exam['label'])][$calendar] ?? null;
                $sitting = [
                    'exam_type' => $type,
                    'label' => "{$exam['label']} {$calendar}",
                    'exam_name' => $exam['name'],
                    'class_name' => $exam['class'],
                    'academic_year_id' => $yearId,
                    'academic_year' => $year['year_code'],
                    'exam_year' => $calendar,
                    'papers_end' => $papersEnd,
                    'written' => $papersEnd !== null && $papersEnd < $today,
                    'candidates' => $candidates,
                    'results_uploaded' => $uploaded[$type][$yearId] ?? 0,
                ];
                $out[] = $sitting;
                if ($default === null && $sitting['written'] && $type !== 'KNEC_G3') {
                    $default = $sitting;
                }
            }
        }
        if ($default === null) {
            foreach ($out as $sitting) {
                $current = array_filter($years, static fn ($y) => (int) $y['id'] === $sitting['academic_year_id'] && (int) $y['is_current'] === 1);
                if ($current && $sitting['exam_type'] !== 'KNEC_G3') { $default = $sitting; break; }
            }
        }
        return ['sittings' => $out, 'default' => $default ? ['exam_type' => $default['exam_type'], 'academic_year_id' => $default['academic_year_id']] : null];
    }

    /** One sitting: its candidates, learning areas and the results recorded. */
    public function sitting(string $examType, int $yearId): array
    {
        [$exam, $year] = $this->resolve($examType, $yearId);
        $candidates = $this->candidates($examType, $yearId);
        $areas = $this->learningAreas($examType, $yearId);
        $ids = array_column($candidates, 'student_id');

        $results = [];
        $summary = [];
        if ($ids) {
            $in = implode(',', array_map('intval', $ids));
            foreach ($this->rows(
                "SELECT student_id, learning_area_id, raw_grade, cbc_grade, points, score, max_score, percentage
                 FROM national_exam_results
                 WHERE exam_type = ? AND academic_year_id = ? AND student_id IN ({$in})",
                [$examType, $yearId]
            ) as $r) {
                $results[(int) $r['student_id']][(int) $r['learning_area_id']] = [
                    'grade' => $r['raw_grade'] ?: $r['cbc_grade'],
                    'band' => $r['cbc_grade'],
                    'points' => $r['points'] !== null ? (float) $r['points'] : null,
                    'score' => $r['score'] !== null ? (float) $r['score'] : null,
                    'percentage' => $r['percentage'] !== null ? (float) $r['percentage'] : null,
                ];
            }
            foreach ($this->rows(
                "SELECT student_id, assessment_number, total_points, overall_level, pathway, source_file, uploaded_at
                 FROM national_exam_candidates WHERE exam_type = ? AND academic_year_id = ? AND student_id IN ({$in})",
                [$examType, $yearId]
            ) as $r) {
                $summary[(int) $r['student_id']] = $r;
            }
        }

        $bands = [];
        foreach ($areas as $area) {
            $bands[$area['learning_area_id']] = [];
        }
        $rows = [];
        foreach ($candidates as $c) {
            $sid = $c['student_id'];
            foreach ($results[$sid] ?? [] as $areaId => $r) {
                $g = (string) ($r['grade'] ?? '');
                if ($g !== '') $bands[$areaId][$g] = ($bands[$areaId][$g] ?? 0) + 1;
            }
            $s = $summary[$sid] ?? null;
            $rows[] = $c + [
                'results' => $results[$sid] ?? (object) [],
                'assessment_number' => $s['assessment_number'] ?? $c['assessment_number'],
                'total_points' => isset($s['total_points']) ? (float) $s['total_points'] : null,
                'overall_level' => $s['overall_level'] ?? null,
                'pathway' => $s['pathway'] ?? null,
                'uploaded' => $s !== null || isset($results[$sid]),
            ];
        }
        foreach ($bands as &$b) ksort($b);
        unset($b);

        $calendar = (int) substr((string) $year['start_date'], 0, 4);
        return [
            'sitting' => [
                'exam_type' => $examType,
                'label' => "{$exam['label']} {$calendar}",
                'exam_name' => $exam['name'],
                'class_name' => $exam['class'],
                'academic_year_id' => $yearId,
                'academic_year' => $year['year_code'],
                'exam_year' => $calendar,
                'cohort_label' => "{$exam['class']} · Class of {$calendar}",
                'levels' => array_keys($exam['levels']),
                'points' => $exam['levels'],
            ],
            'learning_areas' => $areas,
            'candidates' => $rows,
            'grade_bands' => $bands,
            'uploaded' => count(array_filter($rows, static fn ($r) => $r['uploaded'])),
            'rules' => "Candidates are the {$exam['class']} learners of {$year['year_code']} (active, promoted or graduated), in that year's class, however they have moved on since.",
        ];
    }

    /**
     * Check (and, unless $dryRun, save) uploaded results.
     *
     * @param array<int,array<string,mixed>> $rows one object per file row, keyed by column header
     */
    public function import(string $examType, int $yearId, array $rows, bool $dryRun, ?int $userId, ?string $sourceFile = null): array
    {
        [$exam, $year] = $this->resolve($examType, $yearId);
        if (!$rows) {
            throw new InvalidArgumentException('The file has no rows.', 422);
        }
        if (count($rows) > 2000) {
            throw new InvalidArgumentException('The file has too many rows (2000 at most).', 422);
        }
        $calendar = (int) substr((string) $year['start_date'], 0, 4);
        $candidates = $this->candidates($examType, $yearId);
        $byAssessment = [];
        $byAdmission = [];
        foreach ($candidates as $c) {
            if ($c['assessment_number']) $byAssessment[$this->key($c['assessment_number'])] = $c;
            if ($c['admission_no']) $byAdmission[$this->key($c['admission_no'])] = $c;
        }

        // Columns: learner identifiers, summary fields, learning areas.
        $headers = array_keys((array) $rows[0]);
        $areaByKey = [];
        foreach ($this->learningAreas($examType, $yearId) as $area) {
            $areaByKey[$this->key($area['name'])] = $area;
        }
        $columns = ['assessment' => null, 'admission' => null, 'name' => null, 'points' => null, 'overall' => null, 'pathway' => null, 'remarks' => null];
        $areaColumns = [];
        $ignored = [];
        foreach ($headers as $header) {
            $k = $this->key($header);
            if ($k === '') continue;
            if (preg_match('/^(assessment|assessmentno|assessmentnumber|indexno|indexnumber|knecno|candidateno|candidatenumber)$/', $k)) $columns['assessment'] = $header;
            elseif (preg_match('/^(admission|admissionno|admissionnumber|admno|adm)$/', $k)) $columns['admission'] = $header;
            elseif (preg_match('/^(name|names|learner|learnername|candidatename|studentname|fullname)$/', $k)) $columns['name'] = $header;
            elseif (preg_match('/^(points|totalpoints|aggregate|aggregatepoints|total)$/', $k)) $columns['points'] = $header;
            elseif (preg_match('/^(overall|overalllevel|overallgrade|level|grade|meangrade)$/', $k)) $columns['overall'] = $header;
            elseif (preg_match('/^(pathway|placementpathway|seniorschoolpathway)$/', $k)) $columns['pathway'] = $header;
            elseif (preg_match('/^(remarks|remark|comments|comment)$/', $k)) $columns['remarks'] = $header;
            elseif (isset($areaByKey[$k])) $areaColumns[$header] = $areaByKey[$k];
            else $ignored[] = $header;
        }
        if (!$columns['assessment'] && !$columns['admission']) {
            throw new InvalidArgumentException('The file needs an assessment number or admission number column to match learners.', 422);
        }
        if (!$areaColumns && !$columns['points'] && !$columns['overall']) {
            throw new InvalidArgumentException('No column matches a learning area of ' . $exam['class'] . ' in ' . $year['year_code'] . ', or points / overall level.', 422);
        }

        $matched = [];
        $unmatched = [];
        $problems = [];
        $seen = [];
        foreach ($rows as $index => $row) {
            $row = (array) $row;
            $line = $index + 2; // header is line 1
            $candidate = null;
            $assessment = trim((string) ($columns['assessment'] ? ($row[$columns['assessment']] ?? '') : ''));
            $admission = trim((string) ($columns['admission'] ? ($row[$columns['admission']] ?? '') : ''));
            if ($assessment !== '') $candidate = $byAssessment[$this->key($assessment)] ?? null;
            if (!$candidate && $admission !== '') $candidate = $byAdmission[$this->key($admission)] ?? null;
            if (!$candidate) {
                if ($assessment === '' && $admission === '' && !array_filter($row, static fn ($v) => trim((string) $v) !== '')) continue;
                $unmatched[] = ['line' => $line, 'assessment_number' => $assessment, 'admission_no' => $admission,
                    'name' => $columns['name'] ? (string) ($row[$columns['name']] ?? '') : ''];
                continue;
            }
            $sid = $candidate['student_id'];
            if (isset($seen[$sid])) {
                $problems[] = ['line' => $line, 'learner' => $candidate['name'], 'problem' => 'Appears more than once in the file (line ' . $seen[$sid] . ').'];
                continue;
            }
            $seen[$sid] = $line;

            $areas = [];
            foreach ($areaColumns as $header => $area) {
                $value = trim((string) ($row[$header] ?? ''));
                if ($value === '') continue;
                $parsed = $this->parseResult($value, $exam);
                if ($parsed === null) {
                    $problems[] = ['line' => $line, 'learner' => $candidate['name'], 'problem' => "\"{$value}\" in {$header} is not a level (" . implode(', ', array_keys($exam['levels'])) . ') or a score from 0 to 100.'];
                    continue;
                }
                $areas[$area['learning_area_id']] = $parsed;
            }
            $points = $columns['points'] ? trim((string) ($row[$columns['points']] ?? '')) : '';
            if ($points !== '' && !is_numeric($points)) {
                $problems[] = ['line' => $line, 'learner' => $candidate['name'], 'problem' => "Points \"{$points}\" is not a number."];
                $points = '';
            }
            $pathwayRaw = $columns['pathway'] ? trim((string) ($row[$columns['pathway']] ?? '')) : '';
            $pathway = $pathwayRaw !== '' ? (self::PATHWAYS[$this->key($pathwayRaw)] ?? null) : null;
            if ($pathwayRaw !== '' && $pathway === null) {
                $problems[] = ['line' => $line, 'learner' => $candidate['name'], 'problem' => "Pathway \"{$pathwayRaw}\" is not STEM, Social Sciences or Arts and Sports Science."];
            }
            $areaPoints = array_filter(array_column($areas, 'points'), static fn ($p) => $p !== null);
            $matched[] = [
                'line' => $line,
                'student_id' => $sid,
                'enrollment_id' => $candidate['enrollment_id'],
                'name' => $candidate['name'],
                'admission_no' => $candidate['admission_no'],
                'assessment_number' => $assessment !== '' ? $assessment : $candidate['assessment_number'],
                'results' => $areas,
                'total_points' => $points !== '' ? (float) $points : ($areaPoints ? (float) array_sum($areaPoints) : null),
                'overall_level' => $columns['overall'] ? (strtoupper(trim((string) ($row[$columns['overall']] ?? ''))) ?: null) : null,
                'pathway' => $pathway,
                'remarks' => $columns['remarks'] ? (trim((string) ($row[$columns['remarks']] ?? '')) ?: null) : null,
            ];
        }

        $report = [
            'sitting' => ['exam_type' => $examType, 'academic_year_id' => $yearId, 'academic_year' => $year['year_code'], 'label' => "{$exam['label']} {$calendar}"],
            'learning_area_columns' => array_map(static fn ($a) => $a['name'], $areaColumns),
            'ignored_columns' => $ignored,
            'matched' => count($matched),
            'candidates' => count($candidates),
            'not_in_file' => count($candidates) - count($matched),
            'unmatched_rows' => $unmatched,
            'problems' => $problems,
            'preview' => array_slice($matched, 0, 15),
            'saved' => false,
        ];
        if ($dryRun) {
            return $report;
        }
        if (!$matched) {
            throw new InvalidArgumentException('No row in the file matches a candidate of this sitting.', 422);
        }

        $this->db->beginTransaction();
        try {
            $result = $this->db->prepare(
                "INSERT INTO national_exam_results
                    (student_id, exam_type, academic_year_id, exam_year, learning_area_id, score, max_score, percentage,
                     cbc_grade, raw_grade, points, pathway, remarks, entered_by)
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NULL, ?)
                 ON DUPLICATE KEY UPDATE academic_year_id = VALUES(academic_year_id), score = VALUES(score),
                    max_score = VALUES(max_score), percentage = VALUES(percentage), cbc_grade = VALUES(cbc_grade),
                    raw_grade = VALUES(raw_grade), points = VALUES(points), pathway = VALUES(pathway),
                    entered_by = VALUES(entered_by), updated_at = NOW()"
            );
            $summary = $this->db->prepare(
                "INSERT INTO national_exam_candidates
                    (student_id, exam_type, academic_year_id, exam_year, student_academic_enrollment_id, assessment_number,
                     total_points, overall_level, pathway, remarks, source_file, uploaded_by)
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
                 ON DUPLICATE KEY UPDATE student_academic_enrollment_id = VALUES(student_academic_enrollment_id),
                    assessment_number = COALESCE(VALUES(assessment_number), assessment_number),
                    total_points = VALUES(total_points), overall_level = VALUES(overall_level),
                    pathway = VALUES(pathway), remarks = VALUES(remarks), source_file = VALUES(source_file),
                    uploaded_by = VALUES(uploaded_by), updated_at = NOW()"
            );
            $source = $sourceFile !== null ? mb_substr(basename($sourceFile), 0, 255) : null;
            foreach ($matched as $m) {
                foreach ($m['results'] as $areaId => $r) {
                    $result->execute([
                        $m['student_id'], $examType, $yearId, $calendar, $areaId,
                        $r['score'], $r['score'] !== null ? 100 : null, $r['score'],
                        $r['band'], $r['grade'], $r['points'], $m['pathway'], $userId,
                    ]);
                }
                $summary->execute([
                    $m['student_id'], $examType, $yearId, $calendar, $m['enrollment_id'], $m['assessment_number'] ?: null,
                    $m['total_points'], $m['overall_level'], $m['pathway'], $m['remarks'], $source, $userId,
                ]);
            }
            $this->db->commit();
        } catch (\Throwable $e) {
            if ($this->db->inTransaction()) $this->db->rollBack();
            throw $e;
        }
        $report['saved'] = true;
        return $report;
    }

    /** The sitting's candidates: that year's roll of the exam's class. */
    public function candidates(string $examType, int $yearId): array
    {
        $exam = self::EXAMS[$examType] ?? null;
        if (!$exam) return [];
        $placements = $this->rows(
            "SELECT lp.student_id, lp.enrollment_id, lp.enrollment_status, lp.class_name, lp.stream_name
             FROM " . $this->ref('learner_placement_history') . " lp
             WHERE lp.academic_year_id = ? AND lp.class_name = ? AND lp.enrollment_status IN " . self::ROLL,
            [$yearId, $exam['class']]
        );
        if (!$placements) return [];
        $ids = implode(',', array_map(static fn ($p) => (int) $p['student_id'], $placements));
        $people = [];
        foreach ($this->rows(
            'SELECT student_id, admission_no, full_name, gender FROM ' . $this->ref('person_directory') . " WHERE student_id IN ({$ids})"
        ) as $p) {
            $people[(int) $p['student_id']] = $p;
        }
        $assessment = [];
        foreach ($this->rows('SELECT id, assessment_number FROM ' . $this->ref('students') . " WHERE id IN ({$ids})") as $s) {
            $assessment[(int) $s['id']] = $s['assessment_number'];
        }
        $out = [];
        foreach ($placements as $p) {
            $sid = (int) $p['student_id'];
            $out[$sid] = [
                'student_id' => $sid,
                'enrollment_id' => (int) $p['enrollment_id'],
                'enrollment_status' => $p['enrollment_status'],
                'name' => trim((string) ($people[$sid]['full_name'] ?? ('Learner ' . $sid))),
                'admission_no' => $people[$sid]['admission_no'] ?? null,
                'gender' => $people[$sid]['gender'] ?? null,
                'assessment_number' => ($assessment[$sid] ?? '') !== '' ? $assessment[$sid] : null,
                'class_name' => trim($p['class_name'] . ' ' . ($p['stream_name'] ?? '')),
            ];
        }
        usort($out, static fn ($a, $b) => strcasecmp($a['name'], $b['name']));
        return array_values($out);
    }

    private function learningAreas(string $examType, int $yearId): array
    {
        $exam = self::EXAMS[$examType];
        return array_map(static fn ($r) => ['learning_area_id' => (int) $r['id'], 'name' => $r['name']], $this->rows(
            "SELECT DISTINCT la.id, la.name
             FROM " . $this->ref('academic_year_class_learning_areas') . " cla
             JOIN " . $this->ref('academic_year_classes') . " ayc ON ayc.id = cla.academic_year_class_id
             JOIN " . $this->ref('classes') . " c ON c.id = ayc.class_id
             JOIN " . $this->ref('learning_areas') . " la ON la.id = cla.learning_area_id
             WHERE ayc.academic_year_id = ? AND c.name = ?
             ORDER BY la.name",
            [$yearId, $exam['class']]
        ));
    }

    /** A level ("ME1", "EE", "Meeting Expectation 2") or a 0-100 score. */
    private function parseResult(string $value, array $exam): ?array
    {
        $levels = $exam['levels'];
        $v = strtoupper(preg_replace('/\s+/', ' ', trim($value)));
        $words = ['EXCEEDING' => 'EE', 'MEETING' => 'ME', 'APPROACHING' => 'AE', 'BELOW' => 'BE'];
        if (preg_match('/^(EXCEEDING|MEETING|APPROACHING|BELOW)\b.*?([12])?$/', $v, $m)) {
            $v = $words[$m[1]] . ($m[2] ?? '');
        }
        $v = str_replace([' ', '-'], '', $v);
        if (isset($levels[$v])) {
            return ['grade' => $v, 'band' => substr($v, 0, 2), 'points' => (float) $levels[$v], 'score' => null];
        }
        if (preg_match('/^(EE|ME|AE|BE)[12]?$/', $v)) {
            // a level of the other scale (e.g. "ME" on KJSEA): keep it, no points
            return ['grade' => $v, 'band' => substr($v, 0, 2), 'points' => null, 'score' => null];
        }
        if (is_numeric($value) && (float) $value >= 0 && (float) $value <= 100) {
            return ['grade' => null, 'band' => null, 'points' => null, 'score' => round((float) $value, 2)];
        }
        return null;
    }

    private function resolve(string $examType, int $yearId): array
    {
        $exam = self::EXAMS[$examType] ?? null;
        if (!$exam) {
            throw new InvalidArgumentException('Unknown national assessment.', 422);
        }
        $year = $this->rows('SELECT id, year_code, start_date FROM ' . $this->ref('academic_years') . ' WHERE id = ?', [$yearId])[0] ?? null;
        if (!$year) {
            throw new InvalidArgumentException('Academic year not found.', 404);
        }
        return [$exam, $year];
    }

    private function key($value): string
    {
        return preg_replace('/[^a-z0-9]/', '', strtolower((string) $value));
    }

    private function rows(string $sql, array $params = []): array
    {
        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }
}
