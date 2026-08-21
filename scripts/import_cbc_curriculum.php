<?php
declare(strict_types=1);

/**
 * Import authoritative CBC curriculum JSON file(s).
 *
 * Usage:
 *   php scripts/import_cbc_curriculum.php curriculum.json [more.json ...] [--apply]
 *
 * Expected shape (all keys optional except learning_areas entries):
 * {
 *   "learning_areas": [{
 *     "code": "MATH",
 *     "strands": [{
 *       "code": "MATH-S1",
 *       "name": "...",
 *       "sub_strands": [{
 *         "code": "MATH-S1-SS1",
 *         "name": "...",
 *         "learning_outcomes": [{"outcome": "...", "grade_level": "Grade 4"}],
 *         "competencies": ["CT", "DL"]
 *       }]
 *     }]
 *   }],
 *   "rubrics": [{
 *     "tool_code": "CBC-P4-MATH-01",
 *     "criteria": [{
 *       "criteria_name": "...",
 *       "level_1_descriptor": "...",
 *       "level_2_descriptor": "...",
 *       "level_3_descriptor": "...",
 *       "level_4_descriptor": "...",
 *       "points_per_level": 4,
 *       "sort_order": 1
 *     }]
 *   }]
 * }
 *
 * The command is a dry run unless --apply is supplied. It never creates
 * missing learning areas, competencies, or assessment tools; those are
 * reference data and must be provisioned separately.
 */

require_once __DIR__ . '/../vendor/autoload.php';

use App\Config\Config;
use App\Database\Database;

function failImport(string $message): void
{
    fwrite(STDERR, "ERROR: {$message}\n");
    exit(1);
}

function requireString(array $row, string $field, string $context): string
{
    $value = trim((string)($row[$field] ?? ''));
    if ($value === '') failImport("{$context}: {$field} is required");
    return $value;
}

$apply = in_array('--apply', $argv, true);
$files = array_values(array_filter($argv, function ($arg) {
    return $arg !== '--apply' && $arg !== '--';
}));
array_shift($files); // remove script name
if ($files === []) {
    failImport('Usage: php scripts/import_cbc_curriculum.php <file.json> [file2.json ...] [--apply]');
}

$payload = ['learning_areas' => [], 'rubrics' => []];
foreach ($files as $file) {
    if (!is_file($file)) failImport("File not found: {$file}");
    $chunk = json_decode((string)file_get_contents($file), true);
    if (!is_array($chunk)) failImport("Invalid JSON in {$file}");
    if (isset($chunk['learning_areas'])) {
        if (!is_array($chunk['learning_areas'])) failImport("{$file}: learning_areas must be an array");
        $payload['learning_areas'] = array_merge($payload['learning_areas'], $chunk['learning_areas']);
    }
    if (isset($chunk['rubrics'])) {
        if (!is_array($chunk['rubrics'])) failImport("{$file}: rubrics must be an array");
        $payload['rubrics'] = array_merge($payload['rubrics'], $chunk['rubrics']);
    }
}
if ((!isset($payload['learning_areas']) || $payload['learning_areas'] === []) && $payload['rubrics'] === []) {
    failImport('JSON must contain a learning_areas array and/or a rubrics array');
}

if (!extension_loaded('pdo_mysql')) {
    failImport('pdo_mysql is required. Run this importer with the PHP runtime used by Apache, or enable pdo_mysql for CLI PHP.');
}

$db = Database::getInstance();
$pdo = $db->getConnection();
$validated = [];
$areaCodes = [];
$strandKeys = [];
$subStrandKeys = [];
$outcomeKeys = [];
$crosswalkKeys = [];

foreach ($payload['learning_areas'] as $areaIndex => $area) {
    if (!is_array($area)) failImport("learning_areas[{$areaIndex}] must be an object");
    $areaCode = requireString($area, 'code', "learning_areas[{$areaIndex}]");
    if (isset($areaCodes[$areaCode])) failImport("Duplicate learning area code: {$areaCode}");
    $areaCodes[$areaCode] = true;

    $areaRow = $db->query(
        "SELECT id, name FROM learning_areas WHERE code = :code AND status = 'active'",
        [':code' => $areaCode]
    )->fetch(PDO::FETCH_ASSOC);
    if (!$areaRow) failImport("Learning area code does not exist or is inactive: {$areaCode}");

    $strands = $area['strands'] ?? [];
    if (!is_array($strands)) failImport("{$areaCode}.strands must be an array");
    $validatedStrands = [];
    foreach ($strands as $strandIndex => $strand) {
        if (!is_array($strand)) failImport("{$areaCode}.strands[{$strandIndex}] must be an object");
        $strandCode = requireString($strand, 'code', "{$areaCode}.strands[{$strandIndex}]");
        $strandName = requireString($strand, 'name', "{$areaCode}.strands[{$strandIndex}]");
        $strandKey = $areaCode . '|' . $strandCode;
        if (isset($strandKeys[$strandKey])) failImport("Duplicate strand code: {$strandKey}");
        $strandKeys[$strandKey] = true;

        $subStrands = $strand['sub_strands'] ?? [];
        if (!is_array($subStrands)) failImport("{$strandKey}.sub_strands must be an array");
        $validatedSubStrands = [];
        foreach ($subStrands as $subIndex => $subStrand) {
            if (!is_array($subStrand)) failImport("{$strandKey}.sub_strands[{$subIndex}] must be an object");
            $subCode = requireString($subStrand, 'code', "{$strandKey}.sub_strands[{$subIndex}]");
            $subName = requireString($subStrand, 'name', "{$strandKey}.sub_strands[{$subIndex}]");
            $subKey = $strandKey . '|' . $subCode;
            if (isset($subStrandKeys[$subKey])) failImport("Duplicate sub-strand code: {$subKey}");
            $subStrandKeys[$subKey] = true;

            $outcomes = $subStrand['learning_outcomes'] ?? [];
            if (!is_array($outcomes)) failImport("{$subKey}.learning_outcomes must be an array");
            $validatedOutcomes = [];
            foreach ($outcomes as $outcomeIndex => $outcome) {
                if (!is_array($outcome)) failImport("{$subKey}.learning_outcomes[{$outcomeIndex}] must be an object");
                $outcomeText = requireString($outcome, 'outcome', "{$subKey}.learning_outcomes[{$outcomeIndex}]");
                $grade = requireString($outcome, 'grade_level', "{$subKey}.learning_outcomes[{$outcomeIndex}]");
                $outcomeKey = $subKey . '|' . $grade . '|' . $outcomeText;
                if (isset($outcomeKeys[$outcomeKey])) failImport("Duplicate learning outcome: {$outcomeKey}");
                $outcomeKeys[$outcomeKey] = true;
                $validatedOutcomes[] = ['outcome' => $outcomeText, 'grade_level' => $grade];
            }

            $competencies = $subStrand['competencies'] ?? [];
            if (!is_array($competencies)) failImport("{$subKey}.competencies must be an array");
            $validatedCompetencies = [];
            foreach ($competencies as $competencyCode) {
                $competencyCode = trim((string)$competencyCode);
                if ($competencyCode === '') failImport("{$subKey}.competencies contains an empty code");
                $comp = $db->query(
                    "SELECT id FROM core_competencies WHERE code = :code AND status = 'active'",
                    [':code' => $competencyCode]
                )->fetch(PDO::FETCH_ASSOC);
                if (!$comp) failImport("Unknown or inactive competency code: {$competencyCode}");
                $crosswalkKey = $areaCode . '|' . $strandCode . '|' . $competencyCode;
                if (!isset($crosswalkKeys[$crosswalkKey])) {
                    $crosswalkKeys[$crosswalkKey] = true;
                    $validatedCompetencies[] = $competencyCode;
                }
            }

            $validatedSubStrands[] = [
                'code' => $subCode,
                'name' => $subName,
                'description' => isset($subStrand['description']) ? trim((string)$subStrand['description']) : null,
                'sort_order' => (int)($subStrand['sort_order'] ?? ($subIndex + 1)),
                'outcomes' => $validatedOutcomes,
                'competencies' => $validatedCompetencies,
            ];
        }

        $validatedStrands[] = [
            'code' => $strandCode,
            'name' => $strandName,
            'description' => isset($strand['description']) ? trim((string)$strand['description']) : null,
            'level_range' => isset($strand['level_range']) ? trim((string)$strand['level_range']) : null,
            'sort_order' => (int)($strand['sort_order'] ?? ($strandIndex + 1)),
            'sub_strands' => $validatedSubStrands,
        ];
    }

    $validated[] = ['id' => (int)$areaRow['id'], 'code' => $areaCode, 'strands' => $validatedStrands];
}

// ---- Rubrics (optional section) ----
$validatedRubrics = [];
$rubricToolKeys = [];
foreach ($payload['rubrics'] as $rubricIndex => $rubric) {
    if (!is_array($rubric)) failImport("rubrics[{$rubricIndex}] must be an object");
    $toolCode = requireString($rubric, 'tool_code', "rubrics[{$rubricIndex}]");
    $tool = $db->query(
        "SELECT id FROM assessment_tools WHERE tool_code = :code AND status = 'active'",
        [':code' => $toolCode]
    )->fetch(PDO::FETCH_ASSOC);
    if (!$tool) failImport("Unknown or inactive assessment tool code: {$toolCode}");
    if (isset($rubricToolKeys[$toolCode])) failImport("Duplicate rubric section for tool: {$toolCode}");
    $rubricToolKeys[$toolCode] = true;

    $criteria = $rubric['criteria'] ?? [];
    if (!is_array($criteria)) failImport("rubrics[{$rubricIndex}].criteria must be an array");
    $validatedCriteria = [];
    $criteriaNames = [];
    foreach ($criteria as $cIndex => $criterion) {
        if (!is_array($criterion)) failImport("{$toolCode}.criteria[{$cIndex}] must be an object");
        $criterionName = requireString($criterion, 'criteria_name', "{$toolCode}.criteria[{$cIndex}]");
        if (isset($criteriaNames[$criterionName])) failImport("Duplicate criterion '{$criterionName}' for tool {$toolCode}");
        $criteriaNames[$criterionName] = true;
        $level = fn($l) => isset($criterion[$l]) ? trim((string)$criterion[$l]) : null;
        $validatedCriteria[] = [
            'criteria_name' => $criterionName,
            'level_1_descriptor' => $level('level_1_descriptor'),
            'level_2_descriptor' => $level('level_2_descriptor'),
            'level_3_descriptor' => $level('level_3_descriptor'),
            'level_4_descriptor' => $level('level_4_descriptor'),
            'points_per_level' => (int)($criterion['points_per_level'] ?? 0),
            'sort_order' => (int)($criterion['sort_order'] ?? ($cIndex + 1)),
        ];
    }
    $validatedRubrics[] = ['tool_id' => (int)$tool['id'], 'tool_code' => $toolCode, 'criteria' => $validatedCriteria];
}

$summary = sprintf(
    'Validated %d learning areas, %d strands, %d sub-strands, %d outcomes, %d competency mappings, %d rubric criteria.',
    count($validated), count($strandKeys), count($subStrandKeys), count($outcomeKeys), count($crosswalkKeys), count($validatedRubrics)
);
echo $summary . "\n";
if (!$apply) {
    echo "DRY RUN: no database changes made. Re-run with --apply to commit.\n";
    exit(0);
}

$pdo->beginTransaction();
try {
    $counts = ['strands' => 0, 'sub_strands' => 0, 'outcomes' => 0, 'crosswalks' => 0];
    foreach ($validated as $area) {
        foreach ($area['strands'] as $strand) {
            $existing = $db->query(
                "SELECT id FROM strands WHERE learning_area_id = :area_id AND code = :code",
                [':area_id' => $area['id'], ':code' => $strand['code']]
            )->fetch(PDO::FETCH_ASSOC);
            if ($existing) {
                $strandId = (int)$existing['id'];
                $db->query(
                    "UPDATE strands SET name=:name, description=:description, level_range=:level_range, sort_order=:sort_order, status='active' WHERE id=:id",
                    [':name' => $strand['name'], ':description' => $strand['description'], ':level_range' => $strand['level_range'], ':sort_order' => $strand['sort_order'], ':id' => $strandId]
                );
            } else {
                $db->query(
                    "INSERT INTO strands (learning_area_id, code, name, description, level_range, sort_order, status) VALUES (:area_id, :code, :name, :description, :level_range, :sort_order, 'active')",
                    [':area_id' => $area['id'], ':code' => $strand['code'], ':name' => $strand['name'], ':description' => $strand['description'], ':level_range' => $strand['level_range'], ':sort_order' => $strand['sort_order']]
                );
                $strandId = (int)$db->lastInsertId();
            }
            $counts['strands']++;

            foreach ($strand['sub_strands'] as $sub) {
                $existingSub = $db->query(
                    "SELECT id FROM sub_strands WHERE strand_id=:strand_id AND code=:code",
                    [':strand_id' => $strandId, ':code' => $sub['code']]
                )->fetch(PDO::FETCH_ASSOC);
                if ($existingSub) {
                    $subId = (int)$existingSub['id'];
                    $db->query(
                        "UPDATE sub_strands SET name=:name, description=:description, sort_order=:sort_order, status='active' WHERE id=:id",
                        [':name' => $sub['name'], ':description' => $sub['description'], ':sort_order' => $sub['sort_order'], ':id' => $subId]
                    );
                } else {
                    $db->query(
                        "INSERT INTO sub_strands (strand_id, code, name, description, sort_order, status) VALUES (:strand_id, :code, :name, :description, :sort_order, 'active')",
                        [':strand_id' => $strandId, ':code' => $sub['code'], ':name' => $sub['name'], ':description' => $sub['description'], ':sort_order' => $sub['sort_order']]
                    );
                    $subId = (int)$db->lastInsertId();
                }
                $counts['sub_strands']++;

                foreach ($sub['outcomes'] as $outcome) {
                    $exists = $db->query(
                        "SELECT id FROM learning_outcomes WHERE sub_strand_id=:sub_id AND grade_level=:grade AND outcome=:outcome LIMIT 1",
                        [':sub_id' => $subId, ':grade' => $outcome['grade_level'], ':outcome' => $outcome['outcome']]
                    )->fetch(PDO::FETCH_ASSOC);
                    if (!$exists) {
                        $db->query(
                            "INSERT INTO learning_outcomes (learning_area_id, sub_strand_id, outcome, grade_level) VALUES (:area_id, :sub_id, :outcome, :grade)",
                            [':area_id' => $area['id'], ':sub_id' => $subId, ':outcome' => $outcome['outcome'], ':grade' => $outcome['grade_level']]
                        );
                    }
                    $counts['outcomes']++;
                }

                foreach ($sub['competencies'] as $competencyCode) {
                    $competencyId = (int)$db->query(
                        "SELECT id FROM core_competencies WHERE code=:code AND status='active'",
                        [':code' => $competencyCode]
                    )->fetchColumn();
                    $db->query(
                        "INSERT INTO strand_competency (strand_id, competency_id, weight) VALUES (:strand_id, :competency_id, 1.00) ON DUPLICATE KEY UPDATE weight=VALUES(weight)",
                        [':strand_id' => $strandId, ':competency_id' => $competencyId]
                    );
                    $counts['crosswalks']++;
                }
            }
        }
    }

    foreach ($validatedRubrics as $rubric) {
        foreach ($rubric['criteria'] as $criterion) {
            $exists = $db->query(
                "SELECT id FROM assessment_rubrics WHERE tool_id=:tool_id AND criteria_name=:criteria_name LIMIT 1",
                [':tool_id' => $rubric['tool_id'], ':criteria_name' => $criterion['criteria_name']]
            )->fetchColumn();
            if ($exists) {
                $db->query(
                    "UPDATE assessment_rubrics SET level_1_descriptor=:l1, level_2_descriptor=:l2, level_3_descriptor=:l3, level_4_descriptor=:l4, points_per_level=:points, sort_order=:sort WHERE id=:id",
                    [
                        ':l1' => $criterion['level_1_descriptor'], ':l2' => $criterion['level_2_descriptor'],
                        ':l3' => $criterion['level_3_descriptor'], ':l4' => $criterion['level_4_descriptor'],
                        ':points' => $criterion['points_per_level'], ':sort' => $criterion['sort_order'], ':id' => (int)$exists,
                    ]
                );
            } else {
                $db->query(
                    "INSERT INTO assessment_rubrics (tool_id, criteria_name, level_1_descriptor, level_2_descriptor, level_3_descriptor, level_4_descriptor, points_per_level, sort_order)
                     VALUES (:tool_id, :criteria_name, :l1, :l2, :l3, :l4, :points, :sort)",
                    [
                        ':tool_id' => $rubric['tool_id'],
                        ':criteria_name' => $criterion['criteria_name'],
                        ':l1' => $criterion['level_1_descriptor'],
                        ':l2' => $criterion['level_2_descriptor'],
                        ':l3' => $criterion['level_3_descriptor'],
                        ':l4' => $criterion['level_4_descriptor'],
                        ':points' => $criterion['points_per_level'],
                        ':sort' => $criterion['sort_order'],
                    ]
                );
            }
        }
    }

    $pdo->commit();
    echo sprintf(
        "APPLIED: %d strands, %d sub-strands, %d outcomes, %d competency mappings, %d rubric criteria.\n",
        $counts['strands'], $counts['sub_strands'], $counts['outcomes'], $counts['crosswalks'], count($validatedRubrics)
    );
} catch (Throwable $e) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    failImport('Transaction rolled back: ' . $e->getMessage());
}
