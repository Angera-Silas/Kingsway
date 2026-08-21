<?php
/**
 * Fee Structure — Single Student Type — Portrait A4
 *
 * Variables:
 *   $academicYear, $studentType (Day/Boarding/Weekly), $feeItems (array),
 *   $terms (array — if empty/null uses flat single-column mode),
 *   $totals, $notes, $bankDetails, $mpesaDetails,
 *   $schoolName, $schoolLogo, $schoolMotto, $schoolAddress, $schoolPhone
 *
 * feeItems formats:
 *   Multi-term: [['name'=>..., 'terms'=>[t1,t2,t3]], ...]
 *   Flat:       [['name'=>..., 'amount'=>...], ...]  (when $terms is empty)
 */
declare(strict_types=1);
if (!function_exists('fe')) { function fe(mixed $v): string { return htmlspecialchars((string)($v ?? ''), ENT_QUOTES|ENT_SUBSTITUTE, 'UTF-8'); }}
if (!function_exists('fm')) { function fm($v): string { return number_format((float)($v ?? 0), 2); }}

$academicYear = $academicYear ?? date('Y');
$studentType = $studentType ?? 'Day';
$feeItems = $feeItems ?? [];
$terms = $terms ?? ['Term 1','Term 2','Term 3'];
$totals = $totals ?? [];
$notes = $notes ?? 'Payment to be made before the start of each term.';
$bankDetails = $bankDetails ?? '';
$mpesaDetails = $mpesaDetails ?? '';
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<style>
@page { size: A4 portrait; margin: 15mm 18mm; }
* { margin:0; padding:0; box-sizing:border-box; }
body { font-family: 'DejaVu Sans', Arial, sans-serif; font-size:9pt; color:#1b2a23; }

.fs-header { text-align:center; margin-bottom:5mm; }
.fs-header .school-name { font-size:16pt; font-weight:800; color:#0f5b3b; }
.fs-header .school-motto { font-size:9pt; color:#d3ad24; font-style:italic; }
.fs-header .school-addr { font-size:7.5pt; color:#5e6e65; margin-top:1mm; }
.fs-header .fs-title { font-size:12pt; font-weight:700; color:#083f2b; margin-top:3mm; padding:2mm 0; border-top:1pt solid #0f5b3b; border-bottom:1pt solid #0f5b3b; }
.fs-header .fs-type { font-size:10pt; color:#0f5b3b; font-weight:700; margin-top:1mm; }

.fs-table { width:100%; border-collapse:collapse; margin-top:4mm; font-size:8.5pt; }
.fs-table th { background:#0f5b3b; color:#fff; padding:2mm 3mm; text-align:left; font-weight:700; font-size:8pt; }
.fs-table th.num { text-align:right; }
.fs-table td { padding:1.8mm 3mm; border:0.5pt solid #e5e7eb; }
.fs-table td.num { text-align:right; font-family: 'DejaVu Sans Mono', monospace; }
.fs-table tr:nth-child(even) { background:#f9fafb; }
.fs-table tr.subtotal { background:#e8f5e9; font-weight:700; }
.fs-table tr.grand-total { background:#0f5b3b; color:#fff; font-weight:800; font-size:9.5pt; }
.fs-table tr.grand-total td { border-color:#083f2b; }

.fs-notes { margin-top:5mm; font-size:7.5pt; color:#374151; }
.fs-notes strong { color:#0f5b3b; }
.fs-notes ul { margin-left:4mm; }

.fs-signatures { margin-top:8mm; }
.fs-signatures table { width:100%; border-collapse:collapse; }
.fs-signatures td { width:50%; text-align:center; vertical-align:top; padding-top:12mm; }
.fs-signatures .sig-line { border-top:0.5pt solid #000; width:60%; margin:0 auto 1mm; }
.fs-signatures .sig-name { font-size:8pt; font-weight:700; }
.fs-signatures .sig-title { font-size:7pt; color:#5e6e65; }
</style>
</head>
<body>
<div class="fs-header">
    <div class="school-name"><?= fe($schoolName ?? 'KINGSWAY PREPARATORY SCHOOL') ?></div>
    <div class="school-motto">"<?= fe($schoolMotto ?? 'In God We Soar') ?>"</div>
    <div class="school-addr"><?= fe($schoolAddress ?? '') ?></div>
    <div class="fs-title">FEE STRUCTURE — ACADEMIC YEAR <?= fe($academicYear) ?></div>
    <div class="fs-type"><?= fe($studentType) ?> Student Fees</div>
</div>

<table class="fs-table">
    <thead>
        <tr>
            <th style="width:35%">Fee Item</th>
            <?php if (!empty($terms)): ?>
            <?php foreach ($terms as $t): ?>
            <th class="num" style="width:<?= round(55/count($terms)) ?>%"><?= fe($t) ?> (KES)</th>
            <?php endforeach; ?>
            <?php else: ?>
            <th class="num" style="width:20%">Amount (KES)</th>
            <?php endif; ?>
            <?php if (!empty($terms) && count($terms) > 1): ?>
            <th class="num" style="width:15%">Annual Total (KES)</th>
            <?php endif; ?>
        </tr>
    </thead>
    <tbody>
    <?php if (empty($feeItems)): ?>
        <tr><td colspan="<?= 2 + count($terms) ?>" style="text-align:center;color:#9ca3af;padding:5mm;">No fee items configured.</td></tr>
    <?php else: ?>
        <?php
        $isFlat = empty($terms);
        $annualTotals = $isFlat ? [] : array_fill(0, count($terms), 0);
        $grandTotal = 0;
        foreach ($feeItems as $item):
            if ($isFlat):
                $annual = (float)($item['amount'] ?? 0);
            else:
                $annual = 0;
                foreach (($item['terms'] ?? []) as $ti => $amount):
                    $annual += (float)$amount;
                    if (isset($annualTotals[$ti])) $annualTotals[$ti] += (float)$amount;
                endforeach;
            endif;
        ?>
        <tr>
            <td><strong><?= fe($item['name'] ?? $item['fee_item_name'] ?? '') ?></strong></td>
            <?php if ($isFlat): ?>
            <td class="num"><?= fm($annual) ?></td>
            <?php else: ?>
            <?php foreach (($item['terms'] ?? []) as $amount): ?>
            <td class="num"><?= fm($amount) ?></td>
            <?php endforeach; ?>
            <td class="num"><strong><?= fm($annual) ?></strong></td>
            <?php endif; ?>
        </tr>
        <?php $grandTotal += $annual; endforeach; ?>
        <tr class="subtotal">
            <td><strong><?= $isFlat ? 'TOTAL' : 'TOTAL PER TERM' ?></strong></td>
            <?php if ($isFlat): ?>
            <td class="num"><strong><?= fm($grandTotal) ?></strong></td>
            <?php else: ?>
            <?php foreach ($annualTotals as $at): ?>
            <td class="num"><strong><?= fm($at) ?></strong></td>
            <?php endforeach; ?>
            <td class="num"><strong><?= fm($grandTotal) ?></strong></td>
            <?php endif; ?>
        </tr>
    <?php endif; ?>
    </tbody>
</table>

<?php if (!empty($notes) || !empty($bankDetails) || !empty($mpesaDetails)): ?>
<div class="fs-notes">
    <strong>Important Notes:</strong>
    <ul>
        <li><?= fe($notes) ?></li>
        <?php if (!empty($bankDetails)): ?><li><strong>Bank:</strong> <?= fe($bankDetails) ?></li><?php endif; ?>
        <?php if (!empty($mpesaDetails)): ?><li><strong>M-Pesa Paybill:</strong> <?= fe($mpesaDetails) ?></li><?php endif; ?>
        <li>All fees are payable in Kenya Shillings (KES).</li>
        <li>Fees are subject to review by the School Board of Management.</li>
    </ul>
</div>
<?php endif; ?>

<div class="fs-signatures">
    <table>
        <tr>
            <td>
                <div class="sig-line"></div>
                <div class="sig-name">Bursar / Accounts</div>
                <div class="sig-title">Signature &amp; Stamp</div>
            </td>
            <td>
                <div class="sig-line"></div>
                <div class="sig-name">Headteacher</div>
                <div class="sig-title">Signature &amp; Stamp</div>
            </td>
        </tr>
    </table>
</div>
</body>
</html>
