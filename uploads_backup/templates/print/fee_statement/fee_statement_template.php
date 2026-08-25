<?php
/**
 * Student Fee Statement — Dompdf Printable Template
 */
declare(strict_types=1);

if (!function_exists('se')) { 
    function se(mixed $v): string { 
        return htmlspecialchars((string)($v ?? ''), ENT_QUOTES|ENT_SUBSTITUTE, 'UTF-8'); 
    }
}
if (!function_exists('sm')) { 
    function sm($v): string { 
        return number_format((float)($v ?? 0), 2); 
    }
}

$student = $student ?? [];
$academicYear = $academicYear ?? date('Y');
$termName = $termName ?? '';
$feeLines = $feeLines ?? [];
$payments = $payments ?? [];
$summary = $summary ?? ['total_billed'=>0, 'total_paid'=>0, 'total_waived'=>0, 'total_balance'=>0];
$studentName = trim(($student['first_name']??'') . ' ' . ($student['last_name']??'')) ?: 'Student';
$useSharedReportShell = $useSharedReportShell ?? false;
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<style>
@page { 
    size: A4 portrait; 
    margin: 12mm 15mm; 
}
* { 
    margin: 0; 
    padding: 0; 
    box-sizing: border-box; 
    -webkit-print-color-adjust: exact;
    print-color-adjust: exact;
}
body { 
    font-family: 'DejaVu Sans', Arial, sans-serif; 
    font-size: 8.5pt; 
    color: #17202a; 
    background: #ffffff;
}

/* Header */
.stmt-header { 
    text-align: center; 
    margin-bottom: 4mm; 
}
.stmt-header .school-name { 
    font-size: 15pt; 
    font-weight: 800; 
    color: #062653; 
    text-transform: uppercase;
}
.stmt-header .school-motto { 
    font-size: 8pt; 
    color: #07552f; 
    font-style: italic; 
    font-family: 'Georgia', serif;
    margin-top: 0.5mm;
}
.stmt-header .stmt-title { 
    font-size: 10.5pt; 
    font-weight: 800; 
    color: #062653; 
    margin-top: 2mm; 
    padding: 1.5mm 0; 
    border-top: 1pt solid #c9941d; 
    border-bottom: 1pt solid #c9941d; 
    text-transform: uppercase;
    letter-spacing: 0.5pt;
}

/* Info Table */
.stmt-info { 
    width: 100%; 
    border-collapse: collapse; 
    margin-bottom: 3.5mm; 
    font-size: 8pt; 
}
.stmt-info td { 
    padding: 1.2mm 2.5mm; 
    border: 0.5pt solid #cbd5e1; 
}
.stmt-info td.lbl { 
    font-weight: 700; 
    background: #f8fafc; 
    width: 18%; 
    color: #062653; 
}

/* Statement Table */
.stmt-table { 
    width: 100%; 
    border-collapse: collapse; 
    font-size: 8pt; 
    margin-bottom: 3.5mm;
}
.stmt-table th { 
    background: #062653; 
    color: #ffffff; 
    padding: 1.5mm 2mm; 
    text-align: left; 
    font-weight: 800; 
    font-size: 7.5pt; 
    border: 0.3pt solid #062653;
}
.stmt-table th.amt { text-align: right; }
.stmt-table td { 
    padding: 1.2mm 2mm; 
    border: 0.5pt solid #cbd5e1; 
}
.stmt-table td.amt { 
    text-align: right; 
    font-weight: 600;
}
.stmt-table tr:nth-child(even) td { background: #fcfbfa; }

/* Dompdf Safe Table Status Labels */
.status-cell {
    text-align: center;
    font-weight: 800;
    font-size: 7pt;
    text-transform: uppercase;
}
.status-Paid { color: #07552f; }
.status-Partial { color: #d97706; }
.status-Unpaid { color: #dc2626; }
.status-Waived { color: #475569; }

/* Summary */
.stmt-summary { margin-top: 2mm; }
.stmt-summary table { 
    width: 100%; 
    border-collapse: collapse; 
    font-size: 8.5pt; 
}
.stmt-summary td { 
    padding: 1.5mm 2.5mm; 
    border: 0.5pt solid #cbd5e1; 
}
.stmt-summary td.lbl { 
    font-weight: 700; 
    background: #f8fafc; 
    width: 35%; 
    color: #062653; 
}
.stmt-summary td.val { 
    text-align: right; 
    font-weight: 700; 
}
.stmt-summary tr.balance td { 
    background: #062653; 
    color: #ffffff; 
    font-size: 10pt; 
    font-weight: 800;
}

/* Notes Box */
.stmt-notes { 
    margin-top: 3.5mm; 
    font-size: 7.5pt; 
    color: #1e293b; 
    border: 0.5pt solid #c9941d; 
    padding: 2mm 3mm; 
    background: #fff9e9; 
    border-radius: 1mm;
}
.stmt-notes strong { color: #062653; }
</style>
</head>
<body>

<?php if (!$useSharedReportShell): ?><div class="stmt-header">
    <div class="school-name"><?= se($schoolName ?? 'KINGSWAY PREPARATORY SCHOOL') ?></div>
    <div class="school-motto">"<?= se($schoolMotto ?? 'In God We Soar') ?>"</div>
    <div class="stmt-title">STUDENT FEE STATEMENT</div>
</div><?php endif; ?>

<table class="stmt-info">
    <tr>
        <td class="lbl">Student Name</td>
        <td><strong><?= se($studentName) ?></strong></td>
        <td class="lbl">Admission No</td>
        <td><?= se($student['admission_no'] ?? '') ?></td>
    </tr>
    <tr>
        <td class="lbl">Class / Stream</td>
        <td><?= se($student['class_name'] ?? '') ?> <?= !empty($student['stream_name']) ? '(' . se($student['stream_name']) . ')' : '' ?></td>
        <td class="lbl">Student Type</td>
        <td><?= se($student['student_type'] ?? '') ?></td>
    </tr>
    <tr>
        <td class="lbl">Academic Year</td>
        <td><?= se($academicYear) ?></td>
        <td class="lbl">Term</td>
        <td><?= se($termName) ?></td>
    </tr>
</table>

<?php if (!empty($feeLines)): ?>
<table class="stmt-table">
    <thead>
        <tr>
            <th>Term</th>
            <th>Fee Item</th>
            <th class="amt">Amount Due</th>
            <th class="amt">Paid</th>
            <th class="amt">Waived</th>
            <th class="amt">Balance</th>
            <th style="text-align:center;">Status</th>
        </tr>
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
            <td class="status-cell <?= $statusClass ?>"><?= se($status) ?></td>
        </tr>
    <?php endforeach; ?>
    </tbody>
</table>
<?php else: ?>
<p style="text-align:center; color:#64748b; padding:4mm;">No fee records found.</p>
<?php endif; ?>

<?php if (!empty($payments)): ?>
<div style="margin-bottom: 3.5mm;">
    <h3 style="font-size:8.5pt; color:#062653; margin-bottom:1.5mm; font-weight:800;">PAYMENTS RECEIVED</h3>
    <table class="stmt-table">
        <thead>
            <tr>
                <th>Date</th>
                <th>Method</th>
                <th class="amt">Amount</th>
                <th>Receipt No.</th>
                <th>Reference</th>
            </tr>
        </thead>
        <tbody>
        <?php foreach ($payments as $payment): ?>
            <tr>
                <td><?= se($payment['payment_date'] ?? '') ?></td>
                <td><?= se($payment['payment_method'] ?? '') ?></td>
                <td class="amt"><?= sm($payment['amount_paid'] ?? 0) ?></td>
                <td><?= se($payment['receipt_no'] ?? '') ?></td>
                <td><?= se($payment['reference'] ?? '') ?></td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
</div>
<?php endif; ?>

<div class="stmt-summary">
    <table>
        <tr><td class="lbl">Total Billed</td><td class="val">KES <?= sm($summary['total_billed'] ?? 0) ?></td></tr>
        <tr><td class="lbl">Total Paid</td><td class="val">KES <?= sm($summary['total_paid'] ?? 0) ?></td></tr>
        <tr><td class="lbl">Total Waived</td><td class="val">KES <?= sm($summary['total_waived'] ?? 0) ?></td></tr>
        <tr class="balance"><td class="lbl" style="background:#062653; color:#fff;">OUTSTANDING BALANCE</td><td class="val">KES <?= sm($summary['total_balance'] ?? 0) ?></td></tr>
    </table>
</div>

<div class="stmt-notes">
    <strong>Note:</strong> This statement is issued by Kingsway Preparatory School accounts office.
    All payments should be made via official bank transfer or M-Pesa Paybill. Please keep this statement for your records.
</div>

</body>
</html>
