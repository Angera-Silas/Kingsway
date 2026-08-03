<?php

declare(strict_types=1);

/**
 * Import the KICD 2024 grade-specific CBC seeds into the normalized schema.
 *
 * Usage (run with the LAMPP PHP runtime, which has pdo_mysql):
 *   /opt/lampp/bin/php-8.2.12 scripts/import_cbc_grade_seeds.php [--apply] [file.json ...]
 *
 * Reads database/seeds/cbc/grade_specific/<grade>.json by default. The seed
 * shape maps 1:1 onto the migration-008 schema — no seed editing/trimming:
 *
 *   learning_areas (code, level_band)
 *     -> strands    (learning_area_id, grade_level, name)  + variant/source_subject
 *        -> sub_strands (strand_id, grade_level, name)
 *           -> learning_outcomes (sub_strand_id, grade_level, outcome)
 *
 * Dry run by default; re-run with --apply to commit (wrapped in a transaction).
 */

require_once __DIR__ . '/../vendor/autoload.php';

use App\Database\Database;

const EXPECTED = ['strands' => 482, 'sub_strands' => 2109, 'objectives' => 10048];

function importerFail(string $message): void
{
    fwrite(STDERR, "ERROR: {$message}\n");
    exit(1);
}

function gradeBandInfo(string $gradeLevel): array
{
    $bands = [
        'Playgroup' => ['band' => 'playgroup', 'levels' => 'Playgroup'],
        'PP1'       => ['band' => 'pp', 'levels' => 'PP1, PP2'],
        'PP2'       => ['band' => 'pp', 'levels' => 'PP1, PP2'],
        'Grade 1'   => ['band' => 'lower_primary', 'levels' => 'Grade 1, Grade 2, Grade 3'],
        'Grade 2'   => ['band' => 'lower_primary', 'levels' => 'Grade 1, Grade 2, Grade 3'],
        'Grade 3'   => ['band' => 'lower_primary', 'levels' => 'Grade 1, Grade 2, Grade 3'],
        'Grade 4'   => ['band' => 'upper_primary', 'levels' => 'Grade 4, Grade 5, Grade 6'],
        'Grade 5'   => ['band' => 'upper_primary', 'levels' => 'Grade 4, Grade 5, Grade 6'],
        'Grade 6'   => ['band' => 'upper_primary', 'levels' => 'Grade 4, Grade 5, Grade 6'],
        'Grade 7'   => ['band' => 'junior_secondary', 'levels' => 'Grade 7, Grade 8, Grade 9'],
        'Grade 8'   => ['band' => 'junior_secondary', 'levels' => 'Grade 7, Grade 8, Grade 9'],
        'Grade 9'   => ['band' => 'junior_secondary', 'levels' => 'Grade 7, Grade 8, Grade 9'],
    ];
    if (!isset($bands[$gradeLevel])) {
        importerFail("Unknown grade level: {$gradeLevel}");
    }
    return $bands[$gradeLevel];
}

function gradeShortCode(string $gradeLevel): string
{
    return str_replace('Grade ', 'G', $gradeLevel);
}

function variantShort(string $variant): ?string
{
    $map = [
        'Christian Religious Education' => 'CRE',
        'Islamic Religious Education'   => 'IRE',
        'Hindu Religious Education'     => 'HRE',
    ];
    return $map[$variant] ?? null;
}

function gradeFromFilename(string $file): string
{
    $base = basename($file, '.json');
    if (preg_match('/^grade_(\d{2})$/', $base, $m)) {
        return 'Grade ' . (int)$m[1];
    }
    if ($base === 'pp1') {
        return 'PP1';
    }
    if ($base === 'pp2') {
        return 'PP2';
    }
    importerFail("Cannot derive grade level from filename: {$file}");
    return 'Grade 1';
}

function loadSeeds(array $files): array
{
    $seeds = [];
    foreach ($files as $file) {
        if (!is_file($file)) {
            importerFail("File not found: {$file}");
        }
        $gradeLevel = gradeFromFilename($file);
        $band = gradeBandInfo($gradeLevel);
        $data = json_decode((string)file_get_contents($file), true);
        if (!is_array($data) || !isset($data['learning_areas'])) {
            importerFail("Invalid seed JSON: {$file}");
        }
        $seeds[] = ['file' => $file, 'grade' => $gradeLevel, 'band' => $band, 'data' => $data];
    }
    return $seeds;
}

function countSeeds(array $seeds): array
{
    $total = ['strands' => 0, 'sub_strands' => 0, 'objectives' => 0];
    $perGrade = [];
    foreach ($seeds as $seed) {
        $counts = ['strands' => 0, 'sub_strands' => 0, 'objectives' => 0];
        foreach ($seed['data']['learning_areas'] as $area) {
            foreach ($area['strands'] as $strand) {
                $counts['strands']++;
                foreach ($strand['sub_strands'] as $sub) {
                    $counts['sub_strands']++;
                    $counts['objectives'] += count($sub['objectives'] ?? []);
                }
            }
        }
        $perGrade[$seed['grade']] = $counts;
        foreach (array_keys($total) as $k) {
            $total[$k] += $counts[$k];
        }
    }
    return ['total' => $total, 'perGrade' => $perGrade];
}

function runImport(array $seeds, $db, $pdo): void
{
    foreach ($seeds as $seed) {
        $gradeLevel = $seed['grade'];
        $band = $seed['band'];

        foreach ($seed['data']['learning_areas'] as $area) {
            $areaCode = trim((string)($area['code'] ?? ''));
            if ($areaCode === '') {
                importerFail("{$seed['file']}: learning area missing code");
            }

            $areaId = (int)$db->query(
                "SELECT id FROM learning_areas WHERE code = :code AND level_band = :band",
                [':code' => $areaCode, ':band' => $band['band']]
            )->fetchColumn();
            $sourceDocuments = json_encode($area['source_documents'] ?? [], JSON_UNESCAPED_UNICODE);
            if ($areaId) {
                $db->query(
                    "UPDATE learning_areas SET name=:name, levels=:levels, is_optional=:optional, source_documents=:docs, status='active' WHERE id=:id",
                    [
                        ':name' => trim((string)$area['name']),
                        ':levels' => $band['levels'],
                        ':optional' => (int)!empty($area['optional']),
                        ':docs' => $sourceDocuments,
                        ':id' => $areaId,
                    ]
                );
            } else {
                $db->query(
                    "INSERT INTO learning_areas (name, code, level_band, description, levels, is_optional, source_documents, status)
                     VALUES (:name, :code, :band, NULL, :levels, :optional, :docs, 'active')",
                    [
                        ':name' => trim((string)$area['name']),
                        ':code' => $areaCode,
                        ':band' => $band['band'],
                        ':levels' => $band['levels'],
                        ':optional' => (int)!empty($area['optional']),
                        ':docs' => $sourceDocuments,
                    ]
                );
                $areaId = (int)$pdo->lastInsertId();
            }

            $strandOrdinal = 0;
            foreach ($area['strands'] as $strand) {
                $strandOrdinal++;
                $strandName = trim((string)($strand['name'] ?? ''));
                if ($strandName === '') {
                    importerFail("{$areaCode}/{$gradeLevel}: strand missing name");
                }
                $variant = variantShort((string)($strand['variant'] ?? ''));
                $sourceSubject = isset($strand['source_subject']) ? trim((string)$strand['source_subject']) : null;

                $strandId = (int)$db->query(
                    "SELECT id FROM strands WHERE learning_area_id=:area_id AND grade_level=:grade AND name=:name AND variant<=>:variant",
                    [':area_id' => $areaId, ':grade' => $gradeLevel, ':name' => $strandName, ':variant' => $variant]
                )->fetchColumn();

                if ($strandId) {
                    $db->query(
                        "UPDATE strands SET variant=:variant, source_subject=:source_subject, is_optional=:optional, sort_order=:sort, level_range=:grade, status='active' WHERE id=:id",
                        [
                            ':variant' => $variant,
                            ':source_subject' => $sourceSubject,
                            ':optional' => (int)!empty($area['optional']),
                            ':sort' => $strandOrdinal,
                            ':grade' => $gradeLevel,
                            ':id' => $strandId,
                        ]
                    );
                } else {
                    $code = sprintf('%s-%s-S%02d', $areaCode, gradeShortCode($gradeLevel), $strandOrdinal);
                    $db->query(
                        "INSERT INTO strands (learning_area_id, grade_level, code, name, description, level_range, variant, source_subject, is_optional, sort_order, status)
                         VALUES (:area_id, :grade, :code, :name, NULL, :grade2, :variant, :source_subject, :optional, :sort, 'active')",
                        [
                            ':area_id' => $areaId,
                            ':grade' => $gradeLevel,
                            ':code' => $code,
                            ':name' => $strandName,
                            ':grade2' => $gradeLevel,
                            ':variant' => $variant,
                            ':source_subject' => $sourceSubject,
                            ':optional' => (int)!empty($area['optional']),
                            ':sort' => $strandOrdinal,
                        ]
                    );
                    $strandId = (int)$pdo->lastInsertId();
                }

                $subOrdinal = 0;
                foreach ($strand['sub_strands'] as $sub) {
                    $subOrdinal++;
                    $subName = trim((string)($sub['name'] ?? ''));
                    if ($subName === '') {
                        importerFail("{$areaCode}/{$gradeLevel}/{$strandName}: sub-strand missing name");
                    }

                    $subId = (int)$db->query(
                        "SELECT id FROM sub_strands WHERE strand_id=:strand_id AND grade_level=:grade AND name=:name AND variant<=>:variant",
                        [':strand_id' => $strandId, ':grade' => $gradeLevel, ':name' => $subName, ':variant' => $variant]
                    )->fetchColumn();

                    if ($subId) {
                        $db->query(
                            "UPDATE sub_strands SET variant=:variant, source_subject=:source_subject, sort_order=:sort, status='active' WHERE id=:id",
                            [
                                ':variant' => $variant,
                                ':source_subject' => $sourceSubject,
                                ':sort' => $subOrdinal,
                                ':id' => $subId,
                            ]
                        );
                    } else {
                        $code = sprintf('%s-%s-S%02d-SS%02d', $areaCode, gradeShortCode($gradeLevel), $strandOrdinal, $subOrdinal);
                        $db->query(
                            "INSERT INTO sub_strands (strand_id, grade_level, code, name, description, variant, source_subject, sort_order, status)
                             VALUES (:strand_id, :grade, :code, :name, NULL, :variant, :source_subject, :sort, 'active')",
                            [
                                ':strand_id' => $strandId,
                                ':grade' => $gradeLevel,
                                ':code' => $code,
                                ':name' => $subName,
                                ':variant' => $variant,
                                ':source_subject' => $sourceSubject,
                                ':sort' => $subOrdinal,
                            ]
                        );
                        $subId = (int)$pdo->lastInsertId();
                    }

                    foreach ($sub['objectives'] as $objective) {
                        $objective = trim((string)$objective);
                        if ($objective === '') {
                            continue;
                        }
                        $exists = (bool)$db->query(
                            "SELECT id FROM learning_outcomes WHERE sub_strand_id=:sub_id AND grade_level=:grade AND BINARY outcome=:outcome LIMIT 1",
                            [':sub_id' => $subId, ':grade' => $gradeLevel, ':outcome' => $objective]
                        )->fetchColumn();
                        if (!$exists) {
                            $db->query(
                                "INSERT INTO learning_outcomes (learning_area_id, strand_id, sub_strand_id, outcome, grade_level)
                                 VALUES (:area_id, :strand_id, :sub_id, :outcome, :grade)",
                                [
                                    ':area_id' => $areaId,
                                    ':strand_id' => $strandId,
                                    ':sub_id' => $subId,
                                    ':outcome' => $objective,
                                    ':grade' => $gradeLevel,
                                ]
                            );
                        }
                    }
                }
            }
        }
    }
}

$apply = in_array('--apply', $argv, true);
$files = array_values(array_filter($argv, fn($arg) => $arg !== '--apply' && $arg !== '--'));
array_shift($files);

if ($files === []) {
    $files = glob(__DIR__ . '/../database/seeds/cbc/grade_specific/*.json');
    sort($files);
}
if ($files === []) {
    importerFail('No seed files found in database/seeds/cbc/grade_specific/');
}

if (!extension_loaded('pdo_mysql')) {
    importerFail('pdo_mysql is required. Run with /opt/lampp/bin/php-8.2.12 (LAMPP runtime) or Apache PHP.');
}

$seeds = loadSeeds($files);
$counts = countSeeds($seeds);

foreach ($counts['perGrade'] as $grade => $c) {
    printf("%-8s strands=%-3d sub_strands=%-3d objectives=%-4d\n", $grade, $c['strands'], $c['sub_strands'], $c['objectives']);
}
$total = $counts['total'];
printf("TOTAL   strands=%-3d sub_strands=%-4d objectives=%-5d\n", $total['strands'], $total['sub_strands'], $total['objectives']);
if ($total['strands'] !== EXPECTED['strands'] || $total['sub_strands'] !== EXPECTED['sub_strands'] || $total['objectives'] !== EXPECTED['objectives']) {
    fprintf(
        STDERR,
        "WARNING: totals differ from expected strands=%d sub_strands=%d objectives=%d\n",
        EXPECTED['strands'], EXPECTED['sub_strands'], EXPECTED['objectives']
    );
}

if (!$apply) {
    echo "DRY RUN: seed files validated; no database changes made. Re-run with --apply to commit.\n";
    exit(0);
}

$db = Database::getInstance();
$pdo = $db->getConnection();

$pdo->beginTransaction();
try {
    runImport($seeds, $db, $pdo);
    $pdo->commit();
} catch (Throwable $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    importerFail('Transaction rolled back: ' . $e->getMessage());
}
echo "APPLIED: curriculum import complete.\n";
