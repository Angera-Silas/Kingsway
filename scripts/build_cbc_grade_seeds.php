<?php

declare(strict_types=1);

/**
 * Builds grade-specific CBC seed JSON from the raw pulled curriculum dataset.
 *
 * Input : database/seeds/cbc/raw/curricula.js  (window.CURRICULA_DATA = {...};)
 * Output: database/seeds/cbc/grade_specific/<grade>.json
 *
 * The raw dataset is the Teacher Boen / CBC Teacher curriculum browser data,
 * extracted from the official 2024 revised KICD design PDFs (each record keeps
 * its source document + page). This script maps the dataset's per-grade subjects
 * onto the 2024 rationalised learning-area lists and emits one canonical seed
 * file per grade (strands -> sub-strands -> learning objectives), source-tagged.
 */

$raw = file_get_contents(__DIR__ . '/../database/seeds/cbc/raw/curricula.js');
if ($raw === false) {
    fwrite(STDERR, "raw dataset not found\n");
    exit(1);
}
$json = trim(substr($raw, strpos($raw, '=') + 1));
$data = json_decode(rtrim($json, "; \t\n"), true);
if (!is_array($data) || !isset($data['curricula'])) {
    fwrite(STDERR, "raw dataset could not be parsed\n");
    exit(1);
}
$curricula = $data['curricula'];

function cbcPick(array $curricula, string $grade, array $subjects): array
{
    $out = [];
    foreach ($subjects as $subject) {
        foreach ($curricula as $item) {
            if ($item['grade'] === $grade && $item['subject'] === $subject) {
                $out[] = $item;
            }
        }
    }
    return $out;
}

function cbcSource(array $item): string
{
    return ($item['source'] ?? 'unknown') . (isset($item['sourcePage']) ? ' (p.' . $item['sourcePage'] . ')' : '');
}

function cbcCleanName(string $name): string
{
    $name = trim($name);
    $name = preg_replace('/\s*\d+\s*\.\s*\d+\s+/', ' ', $name);
    return trim($name);
}

/**
 * One learning area per band. Keys:
 *  code            canonical code for the seed/importer
 *  name            canonical 2024-rationalised learning-area name
 *  dataset_subjects dataset subject names to merge under this area (in order)
 *  optional        whether the area is optional (non-examined) in the 2024 framework
 */
$config = [
    'PP1' => [
        ['code' => 'LAGA', 'name' => 'Language Activities', 'dataset_subjects' => ['Language Activities']],
        ['code' => 'MATA', 'name' => 'Mathematical Activities', 'dataset_subjects' => ['Mathematical Activities']],
        ['code' => 'PSCA', 'name' => 'Psychomotor & Creative Activities', 'dataset_subjects' => ['Creative Activities']],
        ['code' => 'ENVA', 'name' => 'Environmental Activities', 'dataset_subjects' => ['Environmental Activities']],
        ['code' => 'RE', 'name' => 'Religious Education', 'dataset_subjects' => ['Christian Religious Education', 'Islamic Religious Education', 'Hindu Religious Education'], 'variant' => true],
    ],
    'PP2' => [
        ['code' => 'LAGA', 'name' => 'Language Activities', 'dataset_subjects' => ['Language Activities']],
        ['code' => 'MATA', 'name' => 'Mathematical Activities', 'dataset_subjects' => ['Mathematical Activities']],
        ['code' => 'PSCA', 'name' => 'Psychomotor & Creative Activities', 'dataset_subjects' => ['Creative Activities']],
        ['code' => 'ENVA', 'name' => 'Environmental Activities', 'dataset_subjects' => ['Environmental Activities']],
        ['code' => 'RE', 'name' => 'Religious Education', 'dataset_subjects' => ['Christian Religious Education', 'Islamic Religious Education', 'Hindu Religious Education'], 'variant' => true],
    ],
    'Grade 1' => [
        ['code' => 'ENG', 'name' => 'English', 'dataset_subjects' => ['English']],
        ['code' => 'KIS', 'name' => 'Kiswahili', 'dataset_subjects' => ['Kiswahili']],
        ['code' => 'INDL', 'name' => 'Indigenous Language', 'dataset_subjects' => ['Indigenous Language'], 'optional' => true],
        ['code' => 'MATH', 'name' => 'Mathematics', 'dataset_subjects' => ['Mathematics']],
        ['code' => 'ENVA', 'name' => 'Environmental Activities', 'dataset_subjects' => ['Environmental Activities']],
        ['code' => 'RE', 'name' => 'Religious Education', 'dataset_subjects' => ['Christian Religious Education', 'Islamic Religious Education', 'Hindu Religious Education'], 'variant' => true],
        ['code' => 'MOCA', 'name' => 'Movement & Creative Activities', 'dataset_subjects' => ['Creative Activities']],
    ],
    'Grade 2' => [
        ['code' => 'ENG', 'name' => 'English', 'dataset_subjects' => ['English']],
        ['code' => 'KIS', 'name' => 'Kiswahili', 'dataset_subjects' => ['Kiswahili']],
        ['code' => 'INDL', 'name' => 'Indigenous Language', 'dataset_subjects' => ['Indigenous Language'], 'optional' => true],
        ['code' => 'MATH', 'name' => 'Mathematics', 'dataset_subjects' => ['Mathematics']],
        ['code' => 'ENVA', 'name' => 'Environmental Activities', 'dataset_subjects' => ['Environmental Activities']],
        ['code' => 'RE', 'name' => 'Religious Education', 'dataset_subjects' => ['Christian Religious Education', 'Islamic Religious Education', 'Hindu Religious Education'], 'variant' => true],
        ['code' => 'MOCA', 'name' => 'Movement & Creative Activities', 'dataset_subjects' => ['Creative Activities']],
    ],
    'Grade 3' => [
        ['code' => 'ENG', 'name' => 'English', 'dataset_subjects' => ['English']],
        ['code' => 'KIS', 'name' => 'Kiswahili', 'dataset_subjects' => ['Kiswahili']],
        ['code' => 'INDL', 'name' => 'Indigenous Language', 'dataset_subjects' => ['Indigenous Language'], 'optional' => true],
        ['code' => 'MATH', 'name' => 'Mathematics', 'dataset_subjects' => ['Mathematics']],
        ['code' => 'ENVA', 'name' => 'Environmental Activities', 'dataset_subjects' => ['Environmental Activities']],
        ['code' => 'RE', 'name' => 'Religious Education', 'dataset_subjects' => ['Christian Religious Education', 'Islamic Religious Education', 'Hindu Religious Education'], 'variant' => true],
        ['code' => 'MOCA', 'name' => 'Movement & Creative Activities', 'dataset_subjects' => ['Creative Activities']],
    ],
    'Grade 4' => [
        ['code' => 'ENG', 'name' => 'English', 'dataset_subjects' => ['English']],
        ['code' => 'KIS', 'name' => 'Kiswahili', 'dataset_subjects' => ['Kiswahili']],
        ['code' => 'MATH', 'name' => 'Mathematics', 'dataset_subjects' => ['Mathematics']],
        ['code' => 'RE', 'name' => 'Religious Education', 'dataset_subjects' => ['Christian Religious Education', 'Islamic Religious Education'], 'variant' => true],
        ['code' => 'AGN', 'name' => 'Agriculture & Nutrition', 'dataset_subjects' => ['Agriculture', 'Home Science']],
        ['code' => 'SCIT', 'name' => 'Science & Technology', 'dataset_subjects' => ['Science and Technology']],
        ['code' => 'SOST', 'name' => 'Social Studies', 'dataset_subjects' => ['Social Studies']],
        ['code' => 'CART', 'name' => 'Creative Arts', 'dataset_subjects' => ['Creative Arts', 'Physical and Health Education']],
        ['code' => 'INDL', 'name' => 'Indigenous Language', 'dataset_subjects' => ['Indigenous Language'], 'optional' => true],
        ['code' => 'ARAB', 'name' => 'Arabic', 'dataset_subjects' => ['Arabic'], 'optional' => true],
    ],
    'Grade 5' => [
        ['code' => 'ENG', 'name' => 'English', 'dataset_subjects' => ['English']],
        ['code' => 'KIS', 'name' => 'Kiswahili', 'dataset_subjects' => ['Kiswahili']],
        ['code' => 'MATH', 'name' => 'Mathematics', 'dataset_subjects' => ['Mathematics']],
        ['code' => 'RE', 'name' => 'Religious Education', 'dataset_subjects' => ['Christian Religious Education', 'Islamic Religious Education'], 'variant' => true],
        ['code' => 'AGN', 'name' => 'Agriculture & Nutrition', 'dataset_subjects' => ['Agriculture']],
        ['code' => 'SCIT', 'name' => 'Science & Technology', 'dataset_subjects' => ['Science and Technology']],
        ['code' => 'SOST', 'name' => 'Social Studies', 'dataset_subjects' => ['Social Studies']],
        ['code' => 'CART', 'name' => 'Creative Arts', 'dataset_subjects' => ['Creative Arts']],
        ['code' => 'INDL', 'name' => 'Indigenous Language', 'dataset_subjects' => ['Indigenous Language'], 'optional' => true],
    ],
    'Grade 6' => [
        ['code' => 'ENG', 'name' => 'English', 'dataset_subjects' => ['English']],
        ['code' => 'KIS', 'name' => 'Kiswahili', 'dataset_subjects' => ['Kiswahili']],
        ['code' => 'MATH', 'name' => 'Mathematics', 'dataset_subjects' => ['Mathematics']],
        ['code' => 'RE', 'name' => 'Religious Education', 'dataset_subjects' => ['Christian Religious Education', 'Islamic Religious Education'], 'variant' => true],
        ['code' => 'AGN', 'name' => 'Agriculture & Nutrition', 'dataset_subjects' => ['Agriculture']],
        ['code' => 'SCIT', 'name' => 'Science & Technology', 'dataset_subjects' => ['Science and Technology']],
        ['code' => 'SOST', 'name' => 'Social Studies', 'dataset_subjects' => ['Social Studies']],
        ['code' => 'CART', 'name' => 'Creative Arts', 'dataset_subjects' => ['Creative Arts']],
        ['code' => 'INDL', 'name' => 'Indigenous Language', 'dataset_subjects' => ['Indigenous Language'], 'optional' => true],
    ],
    'Grade 7' => [
        ['code' => 'ENG', 'name' => 'English', 'dataset_subjects' => ['English']],
        ['code' => 'KIS', 'name' => 'Kiswahili', 'dataset_subjects' => ['Kiswahili']],
        ['code' => 'MATH', 'name' => 'Mathematics', 'dataset_subjects' => ['Mathematics']],
        ['code' => 'RE', 'name' => 'Religious Education', 'dataset_subjects' => ['Christian Religious Education', 'Islamic Religious Education'], 'variant' => true],
        ['code' => 'SOST', 'name' => 'Social Studies', 'dataset_subjects' => ['Social Studies']],
        ['code' => 'INSC', 'name' => 'Integrated Science', 'dataset_subjects' => ['Integrated Science']],
        ['code' => 'PTS', 'name' => 'Pre-Technical Studies', 'dataset_subjects' => ['Pre-Technical Studies']],
        ['code' => 'AGN', 'name' => 'Agriculture & Nutrition', 'dataset_subjects' => ['Agriculture']],
        ['code' => 'CAS', 'name' => 'Creative Arts & Sports', 'dataset_subjects' => ['Creative Arts and Sports']],
    ],
    'Grade 8' => [
        ['code' => 'ENG', 'name' => 'English', 'dataset_subjects' => ['English']],
        ['code' => 'KIS', 'name' => 'Kiswahili', 'dataset_subjects' => ['Kiswahili']],
        ['code' => 'MATH', 'name' => 'Mathematics', 'dataset_subjects' => ['Mathematics']],
        ['code' => 'RE', 'name' => 'Religious Education', 'dataset_subjects' => ['Christian Religious Education', 'Islamic Religious Education'], 'variant' => true],
        ['code' => 'SOST', 'name' => 'Social Studies', 'dataset_subjects' => ['Social Studies']],
        ['code' => 'INSC', 'name' => 'Integrated Science', 'dataset_subjects' => ['Integrated Science']],
        ['code' => 'PTS', 'name' => 'Pre-Technical Studies', 'dataset_subjects' => ['Pre-Technical Studies']],
        ['code' => 'AGN', 'name' => 'Agriculture & Nutrition', 'dataset_subjects' => ['Agriculture']],
        ['code' => 'CAS', 'name' => 'Creative Arts & Sports', 'dataset_subjects' => ['Creative Arts and Sports']],
        ['code' => 'INDL', 'name' => 'Indigenous Language', 'dataset_subjects' => ['Indigenous Language'], 'optional' => true],
    ],
    'Grade 9' => [
        ['code' => 'ENG', 'name' => 'English', 'dataset_subjects' => ['English']],
        ['code' => 'KIS', 'name' => 'Kiswahili', 'dataset_subjects' => ['Kiswahili']],
        ['code' => 'MATH', 'name' => 'Mathematics', 'dataset_subjects' => ['Mathematics']],
        ['code' => 'RE', 'name' => 'Religious Education', 'dataset_subjects' => ['Christian Religious Education', 'Islamic Religious Education'], 'variant' => true],
        ['code' => 'SOST', 'name' => 'Social Studies', 'dataset_subjects' => ['Social Studies']],
        ['code' => 'INSC', 'name' => 'Integrated Science', 'dataset_subjects' => ['Integrated Science']],
        ['code' => 'PTS', 'name' => 'Pre-Technical Studies', 'dataset_subjects' => ['Pre-Technical Studies']],
        ['code' => 'AGN', 'name' => 'Agriculture & Nutrition', 'dataset_subjects' => ['Agriculture']],
        ['code' => 'CAS', 'name' => 'Creative Arts & Sports', 'dataset_subjects' => ['Creative Arts and Sports']],
        ['code' => 'INDL', 'name' => 'Indigenous Language', 'dataset_subjects' => ['Indigenous Language'], 'optional' => true],
        ['code' => 'FREN', 'name' => 'French', 'dataset_subjects' => ['French'], 'optional' => true],
    ],
];

$fileMap = [
    'PP1' => 'pp1.json',
    'PP2' => 'pp2.json',
    'Grade 1' => 'grade_01.json',
    'Grade 2' => 'grade_02.json',
    'Grade 3' => 'grade_03.json',
    'Grade 4' => 'grade_04.json',
    'Grade 5' => 'grade_05.json',
    'Grade 6' => 'grade_06.json',
    'Grade 7' => 'grade_07.json',
    'Grade 8' => 'grade_08.json',
    'Grade 9' => 'grade_09.json',
];

$outDir = __DIR__ . '/../database/seeds/cbc/grade_specific/';
$totals = [];

foreach ($config as $grade => $areas) {
    $result = ['grade' => $grade, 'learning_areas' => []];
    $totals[$grade] = ['strands' => 0, 'sub_strands' => 0, 'objectives' => 0];

    foreach ($areas as $area) {
        $items = cbcPick($curricula, $grade, $area['dataset_subjects']);
        if (!$items) {
            continue;
        }

        $sources = [];
        $strands = [];
        $seenStrands = [];

        foreach ($items as $item) {
            $sources[] = cbcSource($item);
            foreach ($item['strands'] as $st) {
                $subStrands = [];
                foreach ($st['subStrands'] as $ss) {
                    $objectives = array_values(array_filter(array_map('trim', $ss['objectives'] ?? [])));
                    $subStrands[] = [
                        'name' => trim($ss['name']),
                        'objectives' => $objectives,
                    ];
                }

                $name = cbcCleanName($st['strand']);
                $fingerprint = json_encode([$name, $subStrands]);
                if (isset($seenStrands[$fingerprint])) {
                    continue;
                }
                $seenStrands[$fingerprint] = true;

                $strandRecord = [
                    'name' => $name,
                    'source_subject' => $item['subject'],
                    'sub_strands' => $subStrands,
                ];
                if (!empty($area['variant'])) {
                    $strandRecord['variant'] = $item['subject'];
                }
                $strands[] = $strandRecord;

                $totals[$grade]['strands']++;
                $totals[$grade]['sub_strands'] += count($subStrands);
                foreach ($subStrands as $ss) {
                    $totals[$grade]['objectives'] += count($ss['objectives']);
                }
            }
        }

        if (!$strands) {
            continue;
        }

        $areaRecord = [
            'code' => $area['code'],
            'name' => $area['name'],
            'optional' => (bool)($area['optional'] ?? false),
            'source_documents' => array_values(array_unique($sources)),
            'strands' => $strands,
        ];
        $result['learning_areas'][] = $areaRecord;
    }

    $file = $fileMap[$grade];
    $written = file_put_contents(
        $outDir . $file,
        json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . "\n"
    );
    if ($written === false) {
        fwrite(STDERR, "failed writing $file\n");
        exit(1);
    }
    echo "wrote $file\n";
}

echo "\n-- totals --\n";
$gt = ['strands' => 0, 'sub_strands' => 0, 'objectives' => 0];
foreach ($totals as $grade => $t) {
    printf("%-8s strands=%4d sub_strands=%4d objectives=%5d\n", $grade, $t['strands'], $t['sub_strands'], $t['objectives']);
    foreach ($gt as $k => $v) {
        $gt[$k] += $t[$k];
    }
}
printf("TOTAL    strands=%4d sub_strands=%4d objectives=%5d\n", $gt['strands'], $gt['sub_strands'], $gt['objectives']);
