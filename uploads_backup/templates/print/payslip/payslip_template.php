<?php
/**
 * Payslip — Portrait A4
 *
 * Variables:
 *   $employeeName, $staffNo, $department, $designation, $kraPin,
 *   $period (string, e.g. "August 2026"), $basicSalary,
 *   $allowances (array [{name, amount}]), $deductions (array [{name, amount}]),
 *   $statutory (array {paye, nssf, nhif_shif, housing_levy}),
 *   $grossPay, $totalDeductions, $netPay,
 *   $paymentMethod, $bankAccount,
 *   $schoolName, $schoolLogo, $schoolMotto
 */
declare(strict_types=1);
if (!function_exists('pe')) { function pe(mixed $v): string { return htmlspecialchars((string)($v ?? ''), ENT_QUOTES|ENT_SUBSTITUTE, 'UTF-8'); }}
if (!function_exists('pm')) { function pm($v): string { return 'KES ' . number_format((float)($v ?? 0), 2); }}

$period = $period ?? date('F Y');
$basicSalary = $basicSalary ?? 0;
$allowances = $allowances ?? [];
$deductions = $deductions ?? [];
$statutory = $statutory ?? ['paye'=>0,'nssf'=>0,'nhif_shif'=>0,'housing_levy'=>0];
$grossPay = $grossPay ?? 0;
$totalDeductions = $totalDeductions ?? 0;
$netPay = $netPay ?? 0;
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<style>
@page { size: A4 portrait; margin: 12mm 15mm; }
* { margin:0; padding:0; box-sizing:border-box; }
body { font-family: 'DejaVu Sans', Arial, sans-serif; font-size:9pt; color:#1b2a23; }

.ps-header { text-align:center; margin-bottom:4mm; padding-bottom:3mm; border-bottom:2pt solid #0f5b3b; }
.ps-header .school-name { font-size:15pt; font-weight:800; color:#0f5b3b; }
.ps-header .school-motto { font-size:8pt; color:#d3ad24; font-style:italic; }
.ps-header .ps-title { font-size:12pt; font-weight:700; color:#083f2b; margin-top:2mm; background:#e8f5e9; padding:2mm; }
.ps-header .ps-period { font-size:10pt; color:#0f5b3b; font-weight:700; margin-top:1mm; }

.ps-info { width:100%; border-collapse:collapse; margin-bottom:4mm; font-size:8.5pt; }
.ps-info td { padding:1.5mm 3mm; border:0.5pt solid #e5e7eb; }
.ps-info td:first-child { font-weight:700; background:#f9fafb; width:22%; color:#0f5b3b; }

.ps-section-title { background:#0f5b3b; color:#fff; padding:1.5mm 3mm; font-size:8.5pt; font-weight:700; margin:3mm 0 0; }
.ps-table { width:100%; border-collapse:collapse; font-size:8.5pt; border:0.5pt solid #e5e7eb; }
.ps-table th { background:#f3f4f6; padding:1.5mm 3mm; text-align:left; font-weight:700; border:0.5pt solid #e5e7eb; }
.ps-table th.amt { text-align:right; }
.ps-table td { padding:1.5mm 3mm; border:0.5pt solid #e5e7eb; }
.ps-table td.amt { text-align:right; font-family:'DejaVu Sans Mono',monospace; }
.ps-table tr.total-row { background:#e8f5e9; font-weight:700; }

.ps-statutory { display:flex; gap:3mm; margin:3mm 0; }
.ps-stat-box { flex:1; border:0.5pt solid #c7d3cc; border-radius:1.5mm; padding:2mm; text-align:center; }
.ps-stat-box .stat-label { font-size:7pt; color:#5e6e65; text-transform:uppercase; }
.ps-stat-box .stat-value { font-size:11pt; font-weight:800; color:#0f5b3b; margin-top:0.5mm; }

.ps-net-pay { background:#0f5b3b; color:#fff; text-align:center; padding:4mm; margin:4mm 0; border-radius:2mm; }
.ps-net-pay .net-label { font-size:9pt; text-transform:uppercase; }
.ps-net-pay .net-value { font-size:18pt; font-weight:800; margin-top:1mm; }

.ps-footer { margin-top:4mm; font-size:7.5pt; color:#5e6e65; }
.ps-footer table { width:100%; border-collapse:collapse; }
.ps-footer td { padding:1mm 0; }
.ps-signatures { margin-top:6mm; }
.ps-signatures table { width:100%; border-collapse:collapse; }
.ps-signatures td { width:50%; text-align:center; vertical-align:top; padding-top:10mm; }
.ps-signatures .sig-line { border-top:0.5pt solid #000; width:60%; margin:0 auto 1mm; }
.ps-signatures .sig-name { font-size:8pt; font-weight:700; }
</style>
</head>
<body>
<div class="ps-header">
    <div class="school-name"><?= pe($schoolName ?? 'KINGSWAY PREPARATORY SCHOOL') ?></div>
    <div class="school-motto">"<?= pe($schoolMotto ?? 'In God We Soar') ?>"</div>
    <div class="ps-title">STAFF PAYSLIP</div>
    <div class="ps-period"><?= pe($period) ?></div>
</div>

<table class="ps-info">
    <tr><td>Employee Name</td><td><strong><?= pe($employeeName ?? '') ?></strong></td><td>Staff No</td><td><?= pe($staffNo ?? '') ?></td></tr>
    <tr><td>Department</td><td><?= pe($department ?? '') ?></td><td>Designation</td><td><?= pe($designation ?? '') ?></td></tr>
    <tr><td>KRA PIN</td><td><?= pe($kraPin ?? '') ?></td><td>Payment Method</td><td><?= pe($paymentMethod ?? 'Bank Transfer') ?></td></tr>
</table>

<div class="ps-section-title">Earnings</div>
<table class="ps-table">
    <thead><tr><th>Description</th><th class="amt">Amount (KES)</th></tr></thead>
    <tbody>
        <tr><td>Basic Salary</td><td class="amt"><?= pm($basicSalary) ?></td></tr>
        <?php foreach ($allowances as $a): ?>
        <tr><td><?= pe($a['name'] ?? '') ?></td><td class="amt"><?= pm($a['amount'] ?? 0) ?></td></tr>
        <?php endforeach; ?>
        <tr class="total-row"><td>GROSS PAY</td><td class="amt"><?= pm($grossPay) ?></td></tr>
    </tbody>
</table>

<div class="ps-section-title">Statutory Deductions</div>
<div class="ps-statutory">
    <div class="ps-stat-box"><div class="stat-label">PAYE</div><div class="stat-value"><?= pm($statutory['paye'] ?? 0) ?></div></div>
    <div class="ps-stat-box"><div class="stat-label">NSSF</div><div class="stat-value"><?= pm($statutory['nssf'] ?? 0) ?></div></div>
    <div class="ps-stat-box"><div class="stat-label">SHIF (NHIF)</div><div class="stat-value"><?= pm($statutory['nhif_shif'] ?? 0) ?></div></div>
    <div class="ps-stat-box"><div class="stat-label">Housing Levy</div><div class="stat-value"><?= pm($statutory['housing_levy'] ?? 0) ?></div></div>
</div>

<div class="ps-section-title">Other Deductions</div>
<table class="ps-table">
    <thead><tr><th>Description</th><th class="amt">Amount (KES)</th></tr></thead>
    <tbody>
        <?php if (empty($deductions)): ?>
        <tr><td colspan="2" style="text-align:center;color:#9ca3af;">No other deductions.</td></tr>
        <?php else: ?>
        <?php foreach ($deductions as $d): ?>
        <tr><td><?= pe($d['name'] ?? '') ?></td><td class="amt"><?= pm($d['amount'] ?? 0) ?></td></tr>
        <?php endforeach; ?>
        <?php endif; ?>
        <tr class="total-row"><td>TOTAL DEDUCTIONS</td><td class="amt"><?= pm($totalDeductions) ?></td></tr>
    </tbody>
</table>

<div class="ps-net-pay">
    <div class="net-label">Net Pay</div>
    <div class="net-value"><?= pm($netPay) ?></div>
</div>

<div class="ps-footer">
    <table>
        <tr><td><strong>Bank / Account:</strong> <?= pe($bankAccount ?? '—') ?></td></tr>
    </table>
</div>

<div class="ps-signatures">
    <table>
        <tr>
            <td><div class="sig-line"></div><div class="sig-name">Accounts Officer</div></td>
            <td><div class="sig-line"></div><div class="sig-name">Employee Acknowledgement</div></td>
        </tr>
    </table>
</div>
</body>
</html>
