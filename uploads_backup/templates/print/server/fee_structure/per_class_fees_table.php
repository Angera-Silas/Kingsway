<?php
/**
 * Per Class Fee Structure — SINGLE grade card with Day + Boarder (configurable).
 *
 * Expected variables:
 *   $schoolName, $schoolLogo, $yearLabel, $documentTitle, $documentSubtitle
 *   $gradeId — the class ID to display
 *   $studentType — 'both', 'day', 'boarder'
 *   $grades — [ ['id'=>.., 'name'=>.., 'section'=>.., 'day'=>[..], 'boarder'=>[..]], ... ]
 *   $otherCharges, $paymentMpesa, $paymentBank, $importantNotes, $generatedAt
 */
$tplDir = __DIR__;
$cssUrl = '/public/css/fee-structure-print.css';
if (!function_exists('fsMoney')) {
    function fsMoney($amount) { return number_format((float)$amount); }
}

$allGrades = $grades ?? [];
$yearLbl = $yearLabel ?? date('Y');
$targetId = (int)($gradeId ?? 0);
$selType = strtolower(trim($studentType ?? 'both'));

$showDay = ($selType === 'both' || $selType === 'day');
$showBoarder = ($selType === 'both' || $selType === 'boarder');

$targetGrade = null;
foreach ($allGrades as $g) {
    if ((int)($g['id'] ?? 0) === $targetId) {
        $targetGrade = $g;
        break;
    }
}
if ($targetGrade === null && !empty($allGrades)) {
    $targetGrade = $allGrades[0];
}

$gradeName = $targetGrade['name'] ?? 'N/A';
$dayData = $targetGrade['day'] ?? [];
$boarderData = $targetGrade['boarder'] ?? [];
$sectionId = (int)($targetGrade['section'] ?? 0);
$levelName = $targetGrade['level_name'] ?? '';
$sectionLabel = $levelName ?: ($sectionId === 2 ? 'PRIMARY SCHOOL' : ($sectionId >= 3 && $sectionId <= 4 ? 'JUNIOR SCHOOL' : 'SCHOOL'));
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title><?= htmlspecialchars($schoolName ?? 'KINGSWAY PREPARATORY SCHOOL') ?> — <?= htmlspecialchars($gradeName) ?> Fee Structure</title>
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

    <div class="fs-grade-card">
        <div class="fs-grade-card-header">
            <h3><?= htmlspecialchars($gradeName) ?></h3>
            <small><?= htmlspecialchars($sectionLabel) ?> &bull; <?= htmlspecialchars($yearLbl) ?></small>
        </div>
        <div class="fs-grade-card-body">

            <?php if ($showDay && !empty($dayData)): ?>
            <div class="fs-student-type-label"><span class="fs-dot fs-dot--day"></span> DAY SCHOLAR</div>
            <table class="fs-grade-card-table">
                <thead><tr>
                    <th>Term I</th><th>Term II</th><th>Term III</th><th>ANNUAL</th>
                </tr></thead>
                <tbody>
                    <tr>
                        <td style="text-align:left;font-weight:600;">School Fees</td>
                        <td>KSh <?= fsMoney($dayData['term1'] ?? 0) ?></td>
                        <td>KSh <?= fsMoney($dayData['term2'] ?? 0) ?></td>
                        <td>KSh <?= fsMoney($dayData['term3'] ?? 0) ?></td>
                        <td class="fs-total">KSh <?= fsMoney($dayData['total'] ?? 0) ?></td>
                    </tr>
                </tbody>
            </table>
            <?php endif; ?>

            <?php if ($showBoarder && !empty($boarderData)): ?>
            <div class="fs-student-type-label"><span class="fs-dot fs-dot--boarder"></span> BOARDER</div>
            <table class="fs-grade-card-table fs-grade-card-table--boarder">
                <thead><tr>
                    <th>Term I</th><th>Term II</th><th>Term III</th><th>ANNUAL</th>
                </tr></thead>
                <tbody>
                    <tr>
                        <td style="text-align:left;font-weight:600;">School Fees</td>
                        <td>KSh <?= fsMoney($boarderData['term1'] ?? 0) ?></td>
                        <td>KSh <?= fsMoney($boarderData['term2'] ?? 0) ?></td>
                        <td>KSh <?= fsMoney($boarderData['term3'] ?? 0) ?></td>
                        <td class="fs-total">KSh <?= fsMoney($boarderData['total'] ?? 0) ?></td>
                    </tr>
                </tbody>
            </table>
            <?php endif; ?>

            <?php if (($showDay && empty($dayData)) || ($showBoarder && empty($boarderData))): ?>
                <div class="text-center text-muted py-3">No fee data available for this grade</div>
            <?php endif; ?>

        </div>
    </div>

    </main>

    <?php include $tplDir . '/fee_structure_footer.php'; ?>

</div>

</body>
</html>
