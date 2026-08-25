-- Backfill statutory payroll registers from existing approved/paid payslips.
-- This preserves the original payslips and creates auditable snapshots.
INSERT INTO statutory_payroll_registers
    (payroll_run_id,period_month,period_year,employee_count,gross_total,
     employee_deductions_total,employer_contributions_total,status,retention_until,created_by)
SELECT
    MAX(pr.id),
    p.payroll_month,
    p.payroll_year,
    COUNT(*),
    COALESCE(SUM(p.gross_salary),0),
    COALESCE(SUM(p.paye_tax + p.shif_contribution + p.nssf_contribution + p.housing_levy),0),
    COALESCE(SUM(p.employer_nssf_contribution + p.employer_housing_levy),0),
    'draft',
    DATE_ADD(STR_TO_DATE(CONCAT(p.payroll_year,'-',LPAD(p.payroll_month,2,'0'),'-01'),'%Y-%m-%d'), INTERVAL 5 YEAR),
    NULL
FROM payslips p
LEFT JOIN payroll_runs pr ON pr.month=p.payroll_month AND pr.year=p.payroll_year
WHERE p.payslip_status IN ('approved','paid')
GROUP BY p.payroll_month,p.payroll_year
ON DUPLICATE KEY UPDATE
    employee_count=VALUES(employee_count),
    gross_total=VALUES(gross_total),
    employee_deductions_total=VALUES(employee_deductions_total),
    employer_contributions_total=VALUES(employer_contributions_total),
    retention_until=VALUES(retention_until);

INSERT INTO statutory_payroll_register_items
    (register_id,payslip_id,staff_id,gross_amount,paye_amount,shif_employee_amount,
     nssf_employee_amount,housing_employee_amount,nssf_employer_amount,housing_employer_amount,rule_snapshot)
SELECT
    r.id,p.id,p.staff_id,p.gross_salary,p.paye_tax,p.shif_contribution,p.nssf_contribution,
    p.housing_levy,p.employer_nssf_contribution,p.employer_housing_levy,
    JSON_OBJECT('source','approved_or_paid_payslip','payroll_month',p.payroll_month,'payroll_year',p.payroll_year)
FROM payslips p
JOIN statutory_payroll_registers r ON r.period_month=p.payroll_month AND r.period_year=p.payroll_year
WHERE p.payslip_status IN ('approved','paid')
ON DUPLICATE KEY UPDATE
    gross_amount=VALUES(gross_amount),
    paye_amount=VALUES(paye_amount),
    shif_employee_amount=VALUES(shif_employee_amount),
    nssf_employee_amount=VALUES(nssf_employee_amount),
    housing_employee_amount=VALUES(housing_employee_amount),
    nssf_employer_amount=VALUES(nssf_employer_amount),
    housing_employer_amount=VALUES(housing_employer_amount),
    rule_snapshot=VALUES(rule_snapshot);

INSERT INTO statutory_record_retention(record_type,record_id,period_start,period_end,retain_until)
SELECT
    'payroll_register',r.id,
    STR_TO_DATE(CONCAT(r.period_year,'-',LPAD(r.period_month,2,'0'),'-01'),'%Y-%m-%d'),
    LAST_DAY(STR_TO_DATE(CONCAT(r.period_year,'-',LPAD(r.period_month,2,'0'),'-01'),'%Y-%m-%d')),
    r.retention_until
FROM statutory_payroll_registers r
ON DUPLICATE KEY UPDATE retain_until=VALUES(retain_until);
