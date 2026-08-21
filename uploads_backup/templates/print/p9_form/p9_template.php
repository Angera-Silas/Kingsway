<?php
/**
 * KRA Form P9 (PAYE Tax Deduction Card) — Landscape A4
 *
 * Variables:
 *   $employerName, $employerPin, $employerAddress,
 *   $employeeName, $employeePin, $staffNo, $nssfNo, $nhifNo, $nationalId,
 *   $year, $months (array of 12 month records),
 *   $annualTotals
 */
declare(strict_types=1);
if (!function_exists('pe')) { function pe(mixed $v): string { return htmlspecialchars((string)($v ?? ''), ENT_QUOTES|ENT_SUBSTITUTE, 'UTF-8'); }}
if (!function_exists('pm')) { function pm($v): string { return number_format((float)($v ?? 0), 2); }}

$year = $year ?? date('Y');
$months = $months ?? [];
$annualTotals = $annualTotals ?? [];
$monthLabels = ['Jan','Feb','Mar','Apr','May','Jun','Jul','Aug','Sep','Oct','Nov','Dec'];
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<style>
@page { size: A4 landscape; margin: 8mm 10mm; }
* { margin:0; padding:0; box-sizing:border-box; }
body { font-family: 'DejaVu Sans', Arial, sans-serif; font-size:7pt; color:#1b2a23; }

.p9-header { text-align:center; margin-bottom:3mm; border-bottom:2pt solid #000; padding-bottom:2mm; }
.p9-header .kra-title { font-size:11pt; font-weight:800; }
.p9-header .p9-subtitle { font-size:9pt; font-weight:700; margin-top:1mm; }

.p9-sections { display:flex; gap:4mm; margin-bottom:3mm; font-size:7.5pt; }
.p9-section { flex:1; border:1pt solid #000; padding:2mm; }
.p9-section h4 { font-size:8pt; margin-bottom:1.5mm; text-transform:uppercase; border-bottom:0.5pt solid #ccc; padding-bottom:1mm; }
.p9-field { display:flex; margin-bottom:0.8mm; }
.p9-field-label { font-weight:700; width:35%; }
.p9-field-value { border-bottom:0.3pt dotted #999; flex:1; padding-left:1mm; }

.p9-table { width:100%; border-collapse:collapse; font-size:6.5pt; border:1pt solid #000; }
.p9-table th { background:#e5e7eb; padding:1.5mm 1mm; text-align:center; font-weight:700; border:0.5pt solid #000; font-size:6pt; }
.p9-table td { padding:1.2mm 1mm; border:0.5pt solid #000; text-align:right; font-family:'DejaVu Sans Mono',monospace; font-size:6.5pt; }
.p9-table td.month-cell { text-align:left; font-weight:700; background:#f9fafb; }
.p9-table tr.annual-total { background:#0f5b3b; color:#fff; font-weight:800; }
.p9-table tr.annual-total td { border-color:#083f2b; }

.p9-declaration { margin-top:3mm; font-size:7pt; border:1pt solid #000; padding:2mm; }
.p9-declaration h4 { font-size:7.5pt; margin-bottom:1mm; }
.p9-declaration .sig-row { display:flex; gap:10mm; margin-top:3mm; }
.p9-declaration .sig-box { flex:1; text-align:center; }
.p9-declaration .sig-line { border-top:0.5pt solid #000; width:80%; margin:8mm auto 1mm; }
.p9-declaration .sig-label { font-size:6.5pt; }
</style>
</head>
<body>
<div class="p9-header">
    <div class="kra-title">KENYA REVENUE AUTHORITY</div>
    <div class="p9-subtitle">TAX DEDUCTION CARD — P9 (FORM P.A.Y.E. 2)</div>
    <div style="font-size:8pt;margin-top:1mm;">Year: <?= pe($year) ?></div>
</div>

<div class="p9-sections">
    <div class="p9-section">
        <h4>Section A: Employer Details</h4>
        <div class="p9-field"><span class="p9-field-label">Employer Name:</span><span class="p9-field-value"><?= pe($employerName ?? 'Kingsway Preparatory School') ?></span></div>
        <div class="p9-field"><span class="p9-field-label">KRA PIN:</span><span class="p9-field-value"><?= pe($employerPin ?? '') ?></span></div>
        <div class="p9-field"><span class="p9-field-label">Address:</span><span class="p9-field-value"><?= pe($employerAddress ?? 'P.O. Box 203-20203, Londiani') ?></span></div>
    </div>
    <div class="p9-section">
        <h4>Section B: Employee Details</h4>
        <div class="p9-field"><span class="p9-field-label">Employee Name:</span><span class="p9-field-value"><?= pe($employeeName ?? '') ?></span></div>
        <div class="p9-field"><span class="p9-field-label">KRA PIN:</span><span class="p9-field-value"><?= pe($employeePin ?? '') ?></span></div>
        <div class="p9-field"><span class="p9-field-label">Staff No:</span><span class="p9-field-value"><?= pe($staffNo ?? '') ?></span></div>
        <div class="p9-field"><span class="p9-field-label">NSSF No:</span><span class="p9-field-value"><?= pe($nssfNo ?? '') ?></span></div>
        <div class="p9-field"><span class="p9-field-label">NHIF/SHIF No:</span><span class="p9-field-value"><?= pe($nhifNo ?? '') ?></span></div>
        <div class="p9-field"><span class="p9-field-label">National ID:</span><span class="p9-field-value"><?= pe($nationalId ?? '') ?></span></div>
    </div>
</div>

<table class="p9-table">
    <thead>
        <tr>
            <th style="width:5%;">Month</th>
            <th>A: Gross Pay (KES)</th>
            <th>B: NSSF</th>
            <th>C: SHIF (2.75%)</th>
            <th>D: Owner Occ. Interest</th>
            <th>E: Pension</th>
            <th>F: Housing Levy</th>
            <th>G: Chargeable Pay</th>
            <th>H: Tax Charged</th>
            <th>I: Personal Relief</th>
            <th>J: Insurance Relief</th>
            <th>K: PAYE (H-I-J)</th>
        </tr>
    </thead>
    <tbody>
    <?php
    $annual = ['gross'=>0,'nssf'=>0,'shif'=>0,'owner_occ'=>0,'pension'=>0,'housing'=>0,'chargeable'=>0,'tax'=>0,'personal_relief'=>0,'insurance_relief'=>0,'paye'=>0];
    for ($m = 0; $m < 12; $m++):
        $rec = $months[$m] ?? [];
        $gross = (float)($rec['gross_pay'] ?? 0);
        $nssf = (float)($rec['nssf'] ?? 0);
        $shif = (float)($rec['shif'] ?? 0);
        $ownerOcc = (float)($rec['owner_occ_interest'] ?? 0);
        $pension = (float)($rec['pension'] ?? 0);
        $housing = (float)($rec['housing_levy'] ?? 0);
        $chargeable = (float)($rec['chargeable_pay'] ?? ($gross - $nssf - $shif - $ownerOcc - $pension - $housing));
        $tax = (float)($rec['tax_charged'] ?? 0);
        $pr = (float)($rec['personal_relief'] ?? 2400);
        $ir = (float)($rec['insurance_relief'] ?? 0);
        $paye = (float)($rec['paye'] ?? max(0, $tax - $pr - $ir));

        foreach ($annual as $k => &$v):
            $kv = match($k) {
                'gross'=>$gross, 'nssf'=>$nssf, 'shif'=>$shif, 'owner_occ'=>$ownerOcc,
                'pension'=>$pension, 'housing'=>$housing, 'chargeable'=>$chargeable,
                'tax'=>$tax, 'personal_relief'=>$pr, 'insurance_relief'=>$ir, 'paye'=>$paye
            };
            $v += $kv;
        endforeach; unset($v);
    ?>
        <tr>
            <td class="month-cell"><?= $monthLabels[$m] ?></td>
            <td><?= $gross > 0 ? pm($gross) : '—' ?></td>
            <td><?= $nssf > 0 ? pm($nssf) : '—' ?></td>
            <td><?= $shif > 0 ? pm($shif) : '—' ?></td>
            <td><?= $ownerOcc > 0 ? pm($ownerOcc) : '—' ?></td>
            <td><?= $pension > 0 ? pm($pension) : '—' ?></td>
            <td><?= $housing > 0 ? pm($housing) : '—' ?></td>
            <td><?= $chargeable > 0 ? pm($chargeable) : '—' ?></td>
            <td><?= $tax > 0 ? pm($tax) : '—' ?></td>
            <td><?= $pr > 0 ? pm($pr) : '—' ?></td>
            <td><?= $ir > 0 ? pm($ir) : '—' ?></td>
            <td><?= $paye > 0 ? pm($paye) : '—' ?></td>
        </tr>
    <?php endfor; ?>
        <tr class="annual-total">
            <td class="month-cell">TOTAL</td>
            <td><?= pm($annual['gross']) ?></td>
            <td><?= pm($annual['nssf']) ?></td>
            <td><?= pm($annual['shif']) ?></td>
            <td><?= pm($annual['owner_occ']) ?></td>
            <td><?= pm($annual['pension']) ?></td>
            <td><?= pm($annual['housing']) ?></td>
            <td><?= pm($annual['chargeable']) ?></td>
            <td><?= pm($annual['tax']) ?></td>
            <td><?= pm($annual['personal_relief']) ?></td>
            <td><?= pm($annual['insurance_relief']) ?></td>
            <td><?= pm($annual['paye']) ?></td>
        </tr>
    </tbody>
</table>

<div class="p9-declaration">
    <h4>Declaration</h4>
    <p>I hereby certify that the information given above is true and correct to the best of my knowledge and belief.</p>
    <div class="sig-row">
        <div class="sig-box">
            <div class="sig-line"></div>
            <div class="sig-label">Employer's Signature &amp; Stamp</div>
        </div>
        <div class="sig-box">
            <div class="sig-line"></div>
            <div class="sig-label">Date: _______________</div>
        </div>
    </div>
</div>
</body>
</html>
