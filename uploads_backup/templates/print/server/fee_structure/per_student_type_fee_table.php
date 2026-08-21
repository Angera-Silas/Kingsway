<?php
/**
 * Per Student Type Fee Structure — parameterized by $studentType and $gradeFilter.
 * If $studentType is 'both' or empty, prints BOTH Day and Boarder (stacked).
 * If $studentType is 'day' or 'boarder', prints only that type.
 *
 * Supports scope filters: ecd, lower_primary, upper_primary, primary, jss, all
 *
 * Expected variables:
 *   $schoolName, $schoolLogo, $yearLabel, $documentTitle, $documentSubtitle
 *   $studentType — 'both', 'day', 'boarder'
 *   $gradeFilter — 'ecd', 'lower_primary', 'upper_primary', 'primary', 'jss', 'all'
 *   $grades — [ ['id'=>.., 'name'=>.., 'section'=>.., 'day'=>[..], 'boarder'=>[..]], ... ]
 *   $otherCharges, $paymentMpesa, $paymentBank, $importantNotes, $generatedAt
 */
$tplDir = __DIR__;
$cssUrl = '/public/css/fee-structure-print.css';
if (!function_exists('fsMoney')) {
    function fsMoney($amount) { return number_format((float)$amount); }
}

function determineSubLevel(array $g): string {
    $name = strtolower($g['name'] ?? '');
    if (preg_match('/grade\s+(\d+)/', $name, $m)) {
        $num = (int) $m[1];
        if ($num >= 1 && $num <= 3) return 'lower_primary';
        if ($num >= 4 && $num <= 6) return 'upper_primary';
        if ($num >= 7 && $num <= 9) return 'jss';
    }
    if (strpos($name, 'pp') !== false || strpos($name, 'playgroup') !== false || strpos($name, 'pg') !== false) {
        return 'ecd';
    }
    $levelId = (int) ($g['section'] ?? 0);
    if ($levelId === 5) return 'ecd';
    if ($levelId === 2) return 'lower_primary';
    if (in_array($levelId, [3, 4])) return 'jss';
    return 'other';
}

$allGrades = $grades ?? [];
$yearLbl = $yearLabel ?? date('Y');
$selType = strtolower(trim($studentType ?? 'both'));
$selFilter = strtolower(trim($gradeFilter ?? 'all'));

$matchesFilter = function (array $g) use ($selFilter) {
    if ($selFilter === 'all' || $selFilter === '') return true;
    $sub = determineSubLevel($g);
    if ($selFilter === 'primary') return in_array($sub, ['ecd', 'lower_primary', 'upper_primary']);
    return $sub === $selFilter;
};

$filtered = array_values(array_filter($allGrades, $matchesFilter));

$showDay = ($selType === 'both' || $selType === 'day');
$showBoarder = ($selType === 'both' || $selType === 'boarder');

$scopeLabels = [
    'ecd' => 'ECD / PRE-PRIMARY',
    'lower_primary' => 'LOWER PRIMARY',
    'upper_primary' => 'UPPER PRIMARY',
    'primary' => 'PRIMARY',
    'jss' => 'JUNIOR SCHOOL',
    'all' => '',
];
$scopeLabel = $scopeLabels[$selFilter] ?? strtoupper($selFilter);
$filtered = array_values(array_filter($allGrades, $matchesFilter));

$showDay = ($selType === 'both' || $selType === 'day');
$showBoarder = ($selType === 'both' || $selType === 'boarder');

$scopeLabels = [
    'ecd' => 'ECD / PRE-PRIMARY',
    'lower_primary' => 'LOWER PRIMARY',
    'upper_primary' => 'UPPER PRIMARY',
    'primary' => 'PRIMARY',
    'jss' => 'JUNIOR SCHOOL',
    'all' => '',
];
$scopeLabel = $scopeLabels[$selFilter] ?? strtoupper($selFilter);
$typeSuffix = $selType === 'day' ? ' — DAY SCHOLAR ONLY' : ($selType === 'boarder' ? ' — BOARDER ONLY' : '');
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title><?= htmlspecialchars($schoolName ?? 'KINGSWAY PREPARATORY SCHOOL') ?> — Fee Structure</title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
<link href="https://fonts.googleapis.com/css2?family=Montserrat:wght@400;500;600;700;800&family=Playfair+Display:ital,wght@0,600;1,600&display=swap" rel="stylesheet">
<link rel="stylesheet" href="<?= $cssUrl ?>">
</head>
<body>

<div class="fs-document">

    <?php include $tplDir . '/fee_structure_header.php'; ?>
    <?php include $tplDir . '/fee_structure_watermark.php'; ?>

    <main class="fs-content">

    <?php if (empty($filtered)): ?>
        <div class="text-center text-muted py-4">No fee data available for the selected criteria</div>
    <?php else: ?>

    <?php if ($showDay): ?>
    <div class="fs-student-section">
        <div class="fs-student-section-header">
            <span class="fs-dot fs-dot--day"></span>
            DAY SCHOLAR<?= $scopeLabel ? ' — ' . htmlspecialchars($scopeLabel) : '' ?><?= $selType === 'day' ? ' ONLY' : '' ?> • <?= htmlspecialchars($yearLbl) ?>
        </div>
        <table class="fs-student-table">
            <thead><tr>
                <th>GRADE</th>
                <th>TERM I<br><small>(KSh)</small></th>
                <th>TERM II<br><small>(KSh)</small></th>
                <th>TERM III<br><small>(KSh)</small></th>
                <th>ANNUAL<br><small>(KSh)</small></th>
            </tr></thead>
            <tbody>
            <?php foreach ($filtered as $g):
                $d = $g['day'] ?? [];
            ?>
                <tr>
                    <td><?= htmlspecialchars($g['name'] ?? '') ?></td>
                    <td><?= fsMoney($d['term1'] ?? 0) ?></td>
                    <td><?= fsMoney($d['term2'] ?? 0) ?></td>
                    <td><?= fsMoney($d['term3'] ?? 0) ?></td>
                    <td class="fs-total"><?= fsMoney($d['total'] ?? 0) ?></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <?php endif; ?>

    <?php if ($showBoarder): ?>
    <div class="fs-student-section">
        <div class="fs-student-section-header">
            <span class="fs-dot fs-dot--boarder"></span>
            BOARDER<?= $scopeLabel ? ' — ' . htmlspecialchars($scopeLabel) : '' ?><?= $selType === 'boarder' ? ' ONLY' : '' ?> • <?= htmlspecialchars($yearLbl) ?>
        </div>
        <table class="fs-student-table fs-student-table--boarder">
            <thead><tr>
                <th>GRADE</th>
                <th>TERM I<br><small>(KSh)</small></th>
                <th>TERM II<br><small>(KSh)</small></th>
                <th>TERM III<br><small>(KSh)</small></th>
                <th>ANNUAL<br><small>(KSh)</small></th>
            </tr></thead>
            <tbody>
            <?php foreach ($filtered as $g):
                $b = $g['boarder'] ?? [];
            ?>
                <tr>
                    <td><?= htmlspecialchars($g['name'] ?? '') ?></td>
                    <td><?= fsMoney($b['term1'] ?? 0) ?></td>
                    <td><?= fsMoney($b['term2'] ?? 0) ?></td>
                    <td><?= fsMoney($b['term3'] ?? 0) ?></td>
                    <td class="fs-total"><?= fsMoney($b['total'] ?? 0) ?></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <?php endif; ?>

    <?php endif; ?>

    </main>

    <?php include $tplDir . '/fee_structure_footer.php'; ?>

</div>

</body>
</html>
