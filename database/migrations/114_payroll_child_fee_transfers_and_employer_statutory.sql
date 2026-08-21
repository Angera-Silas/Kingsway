-- 4NF payroll hardening.
-- A payslip may contain many child-fee transfers; these are not JSON
-- attributes of the payslip and must be independently auditable/idempotent.
CREATE TABLE IF NOT EXISTS payroll_child_fee_transfers (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    payslip_id INT UNSIGNED NOT NULL,
    staff_child_id INT UNSIGNED NULL,
    student_id INT UNSIGNED NOT NULL,
    student_academic_enrollment_id INT UNSIGNED NULL,
    amount DECIMAL(12,2) NOT NULL,
    receipt_no VARCHAR(80) NOT NULL,
    payment_id INT UNSIGNED NULL,
    status ENUM('pending','posted','failed') NOT NULL DEFAULT 'pending',
    failure_reason VARCHAR(500) NULL,
    posted_at DATETIME NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_payroll_child_fee_transfer (payslip_id, student_id, staff_child_id),
    UNIQUE KEY uq_payroll_child_fee_receipt (receipt_no),
    KEY idx_pcft_payment (payment_id),
    KEY idx_pcft_status (status),
    CONSTRAINT fk_pcft_payslip FOREIGN KEY (payslip_id) REFERENCES payslips(id),
    CONSTRAINT fk_pcft_staff_child FOREIGN KEY (staff_child_id) REFERENCES staff_children(id),
    CONSTRAINT fk_pcft_student FOREIGN KEY (student_id) REFERENCES students(id),
    CONSTRAINT fk_pcft_payment FOREIGN KEY (payment_id) REFERENCES payments(id)
) ENGINE=InnoDB;

-- Employer portions are facts of the payroll calculation, not employee
-- deductions.  These columns remain on the payslip because each is a single
-- period-level fact for that employee.
SET @sql := IF((SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='payslips' AND COLUMN_NAME='employer_nssf_contribution')=0,
 'ALTER TABLE payslips ADD employer_nssf_contribution DECIMAL(12,2) NOT NULL DEFAULT 0.00 AFTER nssf_contribution', 'SELECT 1'); PREPARE s FROM @sql; EXECUTE s; DEALLOCATE PREPARE s;
SET @sql := IF((SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='payslips' AND COLUMN_NAME='employer_housing_levy')=0,
 'ALTER TABLE payslips ADD employer_housing_levy DECIMAL(12,2) NOT NULL DEFAULT 0.00 AFTER housing_levy', 'SELECT 1'); PREPARE s FROM @sql; EXECUTE s; DEALLOCATE PREPARE s;
SET @sql := IF((SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='payslips' AND COLUMN_NAME='shif_contribution')=0,
 'ALTER TABLE payslips ADD shif_contribution DECIMAL(12,2) NOT NULL DEFAULT 0.00 AFTER nhif_contribution', 'SELECT 1'); PREPARE s FROM @sql; EXECUTE s; DEALLOCATE PREPARE s;
UPDATE payslips SET shif_contribution = nhif_contribution WHERE shif_contribution = 0 AND nhif_contribution > 0;

SET @sql := IF((SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='statutory_remittances' AND COLUMN_NAME='agency')=1,
 'ALTER TABLE statutory_remittances MODIFY agency ENUM(\'KRA\',\'NSSF\',\'SHIF\',\'NHIF\',\'Housing Levy\') NOT NULL', 'SELECT 1'); PREPARE s FROM @sql; EXECUTE s; DEALLOCATE PREPARE s;
SET @sql := IF((SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='statutory_remittance_items' AND COLUMN_NAME='contribution_side')=0,
 'ALTER TABLE statutory_remittance_items ADD contribution_side ENUM(\'employee\',\'employer\') NOT NULL DEFAULT \'employee\' AFTER amount', 'SELECT 1'); PREPARE s FROM @sql; EXECUTE s; DEALLOCATE PREPARE s;
SET @sql := IF((SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='payroll_configurations' AND COLUMN_NAME='effective_from')=0,
 'ALTER TABLE payroll_configurations ADD effective_from DATE NULL AFTER financial_year', 'SELECT 1'); PREPARE s FROM @sql; EXECUTE s; DEALLOCATE PREPARE s;

-- Replace obsolete NHIF terminology in active configuration without deleting
-- historical rows.  The exact NSSF values are refreshed by the annual rate
-- seed below or by an administrator from the official NSSF notice.
INSERT INTO payroll_configurations (config_key, config_value, financial_year, description, is_active)
VALUES
 ('SHIF_RATE', '2.75', 2026, 'Employee Social Health Insurance Fund rate; effective-dated', 1),
 ('HOUSING_LEVY_EMPLOYEE_RATE', '1.50', 2026, 'Employee Affordable Housing Levy percentage', 1),
 ('HOUSING_LEVY_EMPLOYER_RATE', '1.50', 2026, 'Employer Affordable Housing Levy percentage', 1),
 ('PAYE_PERSONAL_RELIEF', '2400', 2026, 'Monthly resident personal relief', 1),
 ('STATUTORY_RATE_SOURCE_YEAR', '2026', 2026, 'Rates must be verified against current official notices', 1)
ON DUPLICATE KEY UPDATE config_value=VALUES(config_value), description=VALUES(description), is_active=1;

INSERT INTO payroll_configurations (config_key, config_value, financial_year, effective_from, description, is_active)
VALUES
 ('NSSF_RATE', '6', 2025, '2025-02-01', 'NSSF Year 3 employee/employer percentage', 1),
 ('NSSF_LOWER_EARNINGS_LIMIT', '8000', 2025, 'NSSF Year 3 Tier 1 lower earnings limit', 1),
 ('NSSF_UPPER_EARNINGS_LIMIT', '72000', 2025, 'NSSF Year 3 Tier 2 upper earnings limit', 1),
 ('NSSF_RATE', '6', 2026, '2026-02-01', 'NSSF employee/employer percentage under Year 4 notice', 1),
 ('NSSF_LOWER_EARNINGS_LIMIT', '9000', 2026, '2026-02-01', 'NSSF Year 4 Tier 1 lower earnings limit', 1),
 ('NSSF_UPPER_EARNINGS_LIMIT', '108000', 2026, '2026-02-01', 'NSSF Year 4 Tier 2 upper earnings limit', 1)
ON DUPLICATE KEY UPDATE config_value=VALUES(config_value), effective_from=VALUES(effective_from), description=VALUES(description), is_active=1;
