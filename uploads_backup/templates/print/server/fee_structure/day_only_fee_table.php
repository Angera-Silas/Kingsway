<?php
/**
 * Day Scholar Fee Structure — ALL grades, Day Scholar only.
 * Flat table: Grade | Term I | Term II | Term III | Annual
 *
 * Expected variables:
 *   $schoolName, $schoolLogo, $yearLabel, $documentTitle, $documentSubtitle
 *   $grades — [ ['name'=>.., 'day'=>['term1'=>..,'term2'=>..,'term3'=>..,'total'=>..], ...], ... ]
 *   $otherCharges, $paymentMpesa, $paymentBank, $importantNotes, $generatedAt
 */
$tplDir = __DIR__;
$cssUrl = '/public/css/fee-structure-print.css';
if (!function_exists('fsMoney')) {
    function fsMoney($amount) { return number_format((float)$amount); }
}
$allGrades = $grades ?? [];
$yearLbl = $yearLabel ?? date('Y');
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title><?= htmlspecialchars($schoolName ?? 'KINGSWAY PREPARATORY SCHOOL') ?> — Day Scholar Fee Structure</title>
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

    <?php
    $sectionGroups = [
        'ECD / PRE-PRIMARY' => array_filter($allGrades, fn($g) => in_array((int)($g['section'] ?? 0), [5])),
        'PRIMARY SCHOOL' => array_filter($allGrades, fn($g) => (int)($g['section'] ?? 0) === 2),
        'JUNIOR SCHOOL' => array_filter($allGrades, fn($g) => in_array((int)($g['section'] ?? 0), [3, 4])),
    ];
    $sectionGroups = array_filter($sectionGroups);

    foreach ($sectionGroups as $sectionName => $sectionGrades):
    ?>
        <div class="fs-student-section">
            <div class="fs-student-section-header">
                <span class="fs-dot fs-dot--day"></span>
                DAY SCHOLAR — <?= htmlspecialchars($sectionName) ?> • <?= htmlspecialchars($yearLbl) ?>
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
                <?php foreach ($sectionGrades as $g):
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
                <?php if (empty($sectionGrades)): ?>
                    <tr><td colspan="5" class="text-center text-muted" style="padding:12px;font-size:12px">No fee data</td></tr>
                <?php endif; ?>
                </tbody>
            </table>
        </div>
    <?php endforeach; ?>

    <?php if (empty($sectionGroups)): ?>
        <div class="text-center text-muted py-4">No Day Scholar fee data available</div>
    <?php endif; ?>

    </main>

    <?php include $tplDir . '/fee_structure_footer.php'; ?>

</div>

</body>
</html>
