<?php
/**
 * Student Fee Statement — Portrait A4
 *
 * Per-student fee breakdown showing amounts due, paid, waived, balance.
 * Variables:
 *   $student (array: first_name, last_name, admission_no, class_name, student_type),
 *   $academicYear, $termName,
 *   $feeLines (array [{term, item, amount_due, amount_paid, waived, balance, status}]),
 *   $summary (array: total_billed, total_paid, total_waived, total_balance),
 *   $schoolName, $schoolMotto, $schoolAddress, $schoolPhone
 */
declare(strict_types=1);
if (!function_exists('se')) { function se(mixed $v): string { return htmlspecialchars((string)($v ?? ''), ENT_QUOTES|ENT_SUBSTITUTE, 'UTF-8'); }}
if (!function_exists('sm')) { function sm($v): string { return number_format((float)($v ?? 0), 2); }}

$student = $student ?? [];
$academicYear = $academicYear ?? date('Y');
$termName = $termName ?? '';
$feeLines = $feeLines ?? [];
$summary = $summary ?? ['total_billed'=>0,'total_paid'=>0,'total_waived'=>0,'total_balance'=>0];
$studentName = trim(($student['first_name']??'') . ' ' . ($student['last_name']??'')) ?: 'Student';
$statusColors = ['Paid'=>'#10b981','Partial'=>'#f59e0b','Unpaid'=>'#ef4444','Waived'=>'#6b7280'];
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<style>
@page { size: A4 portrait; margin: 15mm 18mm; }
* { margin:0; padding:0; box-sizing:border-box; }
body { font-family: 'DejaVu Sans', Arial, sans-serif; font-size:9pt; color:#1b2a23; }

.stmt-header { text-align:center; margin-bottom:4mm; }
.stmt-header .school-name { font-size:15pt; font-weight:800; color:#0f5b3b; }
.stmt-header .school-motto { font-size:8pt; color:#d3ad24; font-style:italic; }
.stmt-header .stmt-title { font-size:11pt; font-weight:700; color:#083f2b; margin-top:2mm; padding:2mm 0; border-top:1pt solid #0f5b3b; border-bottom:1pt solid #0f5b3b; }

.stmt-info { width:100%; border-collapse:collapse; margin-bottom:4mm; font-size:8.5pt; }
.stmt-info td { padding:1.5mm 3mm; border:0.5pt solid #e5e7eb; }
.stmt-info td:first-child { font-weight:700; background:#f9fafb; width:20%; color:#0f5b3b; }

.stmt-table { width:100%; border-collapse:collapse; font-size:8pt; }
.stmt-table th { background:#0f5b3b; color:#fff; padding:1.5mm 2mm; text-align:left; font-weight:700; font-size:7.5pt; }
.stmt-table th.amt { text-align:right; }
.stmt-table td { padding:1.2mm 2mm; border:0.5pt solid #e5e7eb; }
.stmt-table td.amt { text-align:right; font-family:'DejaVu Sans Mono',monospace; }
.stmt-table tr:nth-child(even) { background:#f9fafb; }

.status-badge { display:inline-block; padding:0.3mm 2mm; border-radius:1mm; font-size:7pt; font-weight:700; color:#fff; }
.status-Paid { background:#10b981; }
.status-Partial { background:#f59e0b; }
.status-Unpaid { background:#ef4444; }
.status-Waived { background:#6b7280; }

.stmt-summary { margin-top:4mm; }
.stmt-summary table { width:100%; border-collapse:collapse; font-size:9pt; }
.stmt-summary td { padding:2mm 3mm; border:0.5pt solid #e5e7eb; }
.stmt-summary td:first-child { font-weight:700; background:#f9fafb; width:30%; color:#0f5b3b; }
.stmt-summary td:last-child { text-align:right; font-family:'DejaVu Sans Mono',monospace; font-weight:700; }
.stmt-summary tr.balance td { background:#0f5b3b; color:#fff; font-size:11pt; }

.stmt-notes { margin-top:4mm; font-size:7.5pt; color:#374151; border:0.5pt solid #e5e7eb; padding:2mm 3mm; background:#f9fafb; }
.stmt-notes strong { color:#0f5b3b; }
</style>
</head>
<body>
<div class="stmt-header">
    <div class="school-name"><?= se($schoolName ?? 'KINGSWAY PREPARATORY SCHOOL') ?></div>
    <div class="school-motto">"<?= se($schoolMotto ?? 'In God We Soar') ?>"</div>
    <div class="stmt-title">STUDENT FEE STATEMENT</div>
</div>

<table class="stmt-info">
    <tr><td>Student Name</td><td><strong><?= se($studentName) ?></strong></td><td>Admission No</td><td><?= se($student['admission_no'] ?? '') ?></td></tr>
    <tr><td>Class / Stream</td><td><?= se($student['class_name'] ?? '') ?> <?= !empty($student['stream_name']) ? '(' . se($student['stream_name']) . ')' : '' ?></td><td>Student Type</td><td><?= se($student['student_type'] ?? '') ?></td></tr>
    <tr><td>Academic Year</td><td><?= se($academicYear) ?></td><td>Term</td><td><?= se($termName) ?></td></tr>
</table>

<?php if (!empty($feeLines)): ?>
<table class="stmt-table">
    <thead>
        <tr><th>Term</th><th>Fee Item</th><th class="amt">Amount Due</th><th class="amt">Paid</th><th class="amt">Waived</th><th class="amt">Balance</th><th>Status</th></tr>
    </thead>
    <tbody>
    <?php foreach ($feeLines as $line):
        $status = $line['status'] ?? 'Unpaid';
        $statusClass = 'status-' . preg_replace('/[^A-Za-z]/', '', $status);
    ?>
        <tr>
            <td><?= se($line['term'] ?? '') ?></td>
            <td><?= se($line['item'] ?? '') ?></td>
            <td class="amt"><?= sm($line['amount_due'] ?? 0) ?></td>
            <td class="amt"><?= sm($line['amount_paid'] ?? 0) ?></td>
            <td class="amt"><?= sm($line['waived'] ?? 0) ?></td>
            <td class="amt"><?= sm($line['balance'] ?? 0) ?></td>
            <td><span class="status-badge <?= $statusClass ?>"><?= se($status) ?></span></td>
        </tr>
    <?php endforeach; ?>
    </tbody>
</table>
<?php else: ?>
<p style="text-align:center;color:#9ca3af;padding:5mm;">No fee records found.</p>
<?php endif; ?>

<div class="stmt-summary">
    <table>
        <tr><td>Total Billed</td><td>KES <?= sm($summary['total_billed'] ?? 0) ?></td></tr>
        <tr><td>Total Paid</td><td>KES <?= sm($summary['total_paid'] ?? 0) ?></td></tr>
        <tr><td>Total Waived</td><td>KES <?= sm($summary['total_waived'] ?? 0) ?></td></tr>
        <tr class="balance"><td>OUTSTANDING BALANCE</td><td>KES <?= sm($summary['total_balance'] ?? 0) ?></td></tr>
    </table>
</div>

<div class="stmt-notes">
    <strong>Note:</strong> This statement is issued by Kingsway Preparatory School. For queries, contact the accounts office.
    All payments should be made via bank transfer or M-Pesa Paybill. Retain this receipt for your records.
</div>
</body>
</html>
