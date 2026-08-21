<?php
/**
 * Fee Structure — Simple Mode Assembler
 * Includes header, watermark, body sections, and footer. All data is dynamic.
 * THE MASTER/GENERAL/SIMPLE fee structure — existing layout preserved.
 *
 * Expected variables:
 *   $schoolName, $schoolAddress, $schoolPhone, $schoolMotto, $schoolLogo
 *   $yearLabel, $documentTitle, $documentSubtitle
 *   $sections, $otherCharges, $paymentMpesa, $paymentBank, $importantNotes
 *   $generatedAt
 */
$tplDir = __DIR__;
$cssUrl  = '/public/css/fee-structure-print.css';
if (!function_exists('fsMoney')) {
    function fsMoney($amount) { return number_format((float)$amount); }
}
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

    <?php foreach (($sections ?? []) as $section):
        $secTitle   = $section['title'] ?? '';
        $secVariant = $section['variant'] ?? 'custom';
    ?>

        <!-- Section Header -->
        <div class="fs-section-header"><?= htmlspecialchars($secTitle) ?></div>

        <?php if ($secVariant === 'primary' && !empty($section['dayRows'])): ?>
            <!-- Two-column Day / Boarder -->
            <div class="row g-3">
                <div class="col-lg-6">
                    <div class="fs-category mt-3"><i class="bi bi-person-walking"></i>DAY SCHOLARS</div>
                    <div class="fs-card">
                        <table class="fs-table">
                            <thead><tr>
                                <th>GRADE</th>
                                <th>TERM I<br><small>(KSh)</small></th>
                                <th>TERM II<br><small>(KSh)</small></th>
                                <th>TERM III<br><small>(KSh)</small></th>
                                <th>TOTAL<br><small>(KSh)</small></th>
                            </tr></thead>
                            <tbody>
                            <?php foreach (($section['dayRows'] ?? []) as $row): ?>
                                <tr>
                                    <td><?= htmlspecialchars($row['label'] ?? '') ?></td>
                                    <td><?= fsMoney($row['term1'] ?? 0) ?></td>
                                    <td><?= fsMoney($row['term2'] ?? 0) ?></td>
                                    <td><?= fsMoney($row['term3'] ?? 0) ?></td>
                                    <td class="fs-total"><?= fsMoney($row['total'] ?? 0) ?></td>
                                </tr>
                            <?php endforeach; ?>
                            <?php if (empty($section['dayRows'])): ?>
                                <tr><td colspan="5" class="text-center text-muted" style="padding:12px;font-size:12px">No fee data</td></tr>
                            <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
                <div class="col-lg-6">
                    <div class="fs-category mt-3"><i class="bi bi-house-heart-fill"></i>BOARDERS</div>
                    <div class="fs-card fs-card--boarder">
                        <table class="fs-table">
                            <thead><tr>
                                <th>GRADE</th>
                                <th>TERM I<br><small>(KSh)</small></th>
                                <th>TERM II<br><small>(KSh)</small></th>
                                <th>TERM III<br><small>(KSh)</small></th>
                                <th>TOTAL<br><small>(KSh)</small></th>
                            </tr></thead>
                            <tbody>
                            <?php foreach (($section['boarderRows'] ?? []) as $row): ?>
                                <tr>
                                    <td><?= htmlspecialchars($row['label'] ?? '') ?></td>
                                    <td><?= fsMoney($row['term1'] ?? 0) ?></td>
                                    <td><?= fsMoney($row['term2'] ?? 0) ?></td>
                                    <td><?= fsMoney($row['term3'] ?? 0) ?></td>
                                    <td class="fs-total"><?= fsMoney($row['total'] ?? 0) ?></td>
                                </tr>
                            <?php endforeach; ?>
                            <?php if (empty($section['boarderRows'])): ?>
                                <tr><td colspan="5" class="text-center text-muted" style="padding:12px;font-size:12px">No fee data</td></tr>
                            <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

        <?php elseif ($secVariant === 'junior' || $secVariant === 'custom'): ?>
            <!-- Single table (full width) -->
            <?php $tableTitle = $section['tableTitle'] ?? null; ?>
            <?php $tableIcon = $section['tableIcon'] ?? null; ?>
            <?php $firstColHeader = $section['firstCol'] ?? 'CATEGORY'; ?>
            <?php $rows = $section['rows'] ?? []; ?>
            <?php if ($tableTitle): ?>
                <div class="fs-category mt-3">
                    <?php if ($tableIcon): ?><i class="bi <?= htmlspecialchars($tableIcon) ?>"></i><?php endif; ?>
                    <?= htmlspecialchars($tableTitle) ?>
                </div>
            <?php endif; ?>
            <div class="fs-card">
                <table class="fs-table fs-table--junior">
                    <thead><tr>
                        <th><?= htmlspecialchars($firstColHeader) ?></th>
                        <th>TERM I<br><small>(KSh)</small></th>
                        <th>TERM II<br><small>(KSh)</small></th>
                        <th>TERM III<br><small>(KSh)</small></th>
                        <th>TOTAL<br><small>(KSh)</small></th>
                    </tr></thead>
                    <tbody>
                    <?php foreach ($rows as $row): ?>
                        <tr>
                            <td><?= htmlspecialchars($row['category'] ?? $row['label'] ?? '') ?></td>
                            <td><?= fsMoney($row['term1'] ?? 0) ?></td>
                            <td><?= fsMoney($row['term2'] ?? 0) ?></td>
                            <td><?= fsMoney($row['term3'] ?? 0) ?></td>
                            <td class="fs-total"><?= fsMoney($row['total'] ?? 0) ?></td>
                        </tr>
                    <?php endforeach; ?>
                    <?php if (empty($rows)): ?>
                        <tr><td colspan="5" class="text-center text-muted" style="padding:12px;font-size:12px">No fee data</td></tr>
                    <?php endif; ?>
                    </tbody>
                </table>
            </div>

        <?php endif; ?>

    <?php endforeach; ?>

    </main>

    <?php include $tplDir . '/fee_structure_footer.php'; ?>

</div>

</body>
</html>
