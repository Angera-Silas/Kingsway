<?php
require dirname(__DIR__) . '/vendor/autoload.php';

use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PhpOffice\PhpSpreadsheet\Cell\DataValidation;
use PhpOffice\PhpSpreadsheet\Style\Fill;

$spreadsheet = new Spreadsheet();
$instructions = $spreadsheet->getActiveSheet();
$instructions->setTitle('Instructions');
$instructions->fromArray([
    ['Existing Student Import Template'],
    ['Use the Import Template sheet. Delete the example row before uploading.'],
    ['Required: first_name, last_name, date_of_birth, gender, class_id, stream_name, student_type_id.'],
    ['The stream must already be configured for that class and academic year. No default streams are created.'],
    ['academic_year_paid_amount is the cumulative historical amount paid in that year. It is not replayed as a new payment.'],
    ['current_term_paid_amount is the portion applied to the current term and must be less than or equal to the annual paid amount.'],
    ['fee_arrears_amount is an opening debit. advance_amount is an opening credit for current/future obligations.'],
    ['The green Finance Check cell is calculated automatically; do not upload the example row.'],
    ['Dates use YYYY-MM-DD. Amounts are numeric, without KES or commas.'],
], null, 'A1');
$instructions->getColumnDimension('A')->setWidth(120);
$instructions->getStyle('A1:A9')->getAlignment()->setWrapText(true);

$sheet = $spreadsheet->createSheet();
$sheet->setTitle('Import Template');
$headers = [
    'admission_no','first_name','middle_name','last_name','date_of_birth','gender','class_id','stream_name',
    'student_type_id','admission_date','assessment_number','birth_certificate_no','nationality','religion',
    'blood_group','previous_school','previous_class','parent_first_name','parent_last_name','parent_phone',
    'parent_email','parent_relationship','opening_payment_amount','opening_payment_method','opening_payment_reference',
    'opening_payment_date','opening_payment_receipt','financial_academic_year_code','academic_year_paid_amount',
    'current_term_paid_amount','fee_arrears_amount','advance_amount','opening_balance_reference','opening_balance_date',
    'opening_balance_method','opening_balance_receipt','opening_balance_notes','finance_check'
];
$sheet->fromArray([$headers], null, 'A1');
$sheet->fromArray([["EXAMPLE-DO-NOT-IMPORT","John","","Kamau","2015-05-15","male","5","A","1","2026-08-19","NEM123456","BC123456","Kenyan","Christian","O+","Previous Primary School","Grade 4","Jane","Doe","0712345678","jane.doe@example.com","mother","0","bank_transfer","","","","2026/2027",15000,3500,2000,0,"MIG-EXAMPLE-001","2026-08-19","bank_transfer","KCB-EXAMPLE-001","Example only",null]], null, 'A2');
$sheet->setCellValue('AL2', '=IF(OR(AB2="",AC2="",AD2="",AE2="",AF2=""),"Complete all finance fields",IF(AD2>AC2,"ERROR: current term > year total",IF(AND(AE2=0,AF2=0),"No opening balance","Ready for review")))');
$sheet->getStyle('A1:AL1')->getFont()->setBold(true)->getColor()->setARGB('FFFFFFFF');
$sheet->getStyle('A1:AL1')->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setARGB('1F4E78');
$sheet->getStyle('AL2')->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setARGB('E2F0D9');
$sheet->freezePane('A2');
$sheet->setAutoFilter('A1:AL2');
for ($columnIndex = 1; $columnIndex <= 38; $columnIndex++) {
    $column = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($columnIndex);
    $sheet->getColumnDimension($column)->setWidth(18);
}
$sheet->getColumnDimension('AL')->setWidth(30);
$sheet->getStyle('A1:AL2')->getAlignment()->setWrapText(true);

foreach (['F2' => 'male,female,other', 'X2' => 'bank_transfer,mpesa,cheque,other', 'AI2' => 'bank_transfer,mpesa,cheque,other'] as $cell => $values) {
    $validation = $sheet->getCell($cell)->getDataValidation();
    $validation->setType(DataValidation::TYPE_LIST);
    $validation->setAllowBlank(true);
    $validation->setShowDropDown(true);
    $validation->setFormula1('"' . $values . '"');
}

$writer = new Xlsx($spreadsheet);
$writer->save(dirname(__DIR__) . '/templates/student_import_template.xlsx');
