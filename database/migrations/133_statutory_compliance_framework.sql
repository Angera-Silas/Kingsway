-- Statutory compliance framework.
-- Rates and deadlines are effective-dated data, never application constants.

ALTER TABLE statutory_remittances
    MODIFY agency ENUM('KRA','SHIF','NHIF','NSSF','Housing Levy') NOT NULL;

ALTER TABLE statutory_agency_accounts
    MODIFY agency ENUM('KRA','SHIF','NHIF','NSSF','Housing Levy') NOT NULL;

CREATE TABLE IF NOT EXISTS statutory_rule_sets (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    agency VARCHAR(80) NOT NULL,
    rule_code VARCHAR(100) NOT NULL,
    version VARCHAR(40) NOT NULL,
    effective_from DATE NOT NULL,
    effective_to DATE DEFAULT NULL,
    rules_json JSON NOT NULL,
    source_name VARCHAR(255) DEFAULT NULL,
    source_url VARCHAR(500) DEFAULT NULL,
    active TINYINT(1) NOT NULL DEFAULT 1,
    created_by INT UNSIGNED DEFAULT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_statutory_rule_version (agency, rule_code, version),
    KEY idx_statutory_rule_effective (agency, effective_from, effective_to, active)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS statutory_payroll_registers (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    payroll_run_id INT UNSIGNED DEFAULT NULL,
    period_month TINYINT UNSIGNED NOT NULL,
    period_year SMALLINT UNSIGNED NOT NULL,
    employee_count INT UNSIGNED NOT NULL DEFAULT 0,
    gross_total DECIMAL(14,2) NOT NULL DEFAULT 0.00,
    employee_deductions_total DECIMAL(14,2) NOT NULL DEFAULT 0.00,
    employer_contributions_total DECIMAL(14,2) NOT NULL DEFAULT 0.00,
    status ENUM('draft','finalized','submitted','archived') NOT NULL DEFAULT 'draft',
    finalized_at DATETIME DEFAULT NULL,
    finalized_by INT UNSIGNED DEFAULT NULL,
    retention_until DATE DEFAULT NULL,
    created_by INT UNSIGNED DEFAULT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_statutory_register_period (period_month, period_year),
    KEY idx_statutory_register_retention (retention_until, status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS statutory_payroll_register_items (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    register_id BIGINT UNSIGNED NOT NULL,
    payslip_id INT UNSIGNED NOT NULL,
    staff_id INT UNSIGNED NOT NULL,
    gross_amount DECIMAL(14,2) NOT NULL DEFAULT 0.00,
    paye_amount DECIMAL(14,2) NOT NULL DEFAULT 0.00,
    shif_employee_amount DECIMAL(14,2) NOT NULL DEFAULT 0.00,
    nssf_employee_amount DECIMAL(14,2) NOT NULL DEFAULT 0.00,
    housing_employee_amount DECIMAL(14,2) NOT NULL DEFAULT 0.00,
    nssf_employer_amount DECIMAL(14,2) NOT NULL DEFAULT 0.00,
    housing_employer_amount DECIMAL(14,2) NOT NULL DEFAULT 0.00,
    rule_snapshot JSON DEFAULT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_statutory_register_payslip (register_id, payslip_id),
    KEY idx_statutory_register_staff (staff_id),
    CONSTRAINT fk_statutory_register_item_register FOREIGN KEY (register_id) REFERENCES statutory_payroll_registers(id) ON DELETE CASCADE,
    CONSTRAINT fk_statutory_register_item_payslip FOREIGN KEY (payslip_id) REFERENCES payslips(id) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS staff_certificates_of_service (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    staff_id INT UNSIGNED NOT NULL,
    certificate_number VARCHAR(100) NOT NULL,
    employment_start_date DATE NOT NULL,
    employment_end_date DATE NOT NULL,
    designation VARCHAR(160) DEFAULT NULL,
    department VARCHAR(160) DEFAULT NULL,
    reason_for_leaving VARCHAR(160) DEFAULT NULL,
    issued_date DATE NOT NULL,
    document_path VARCHAR(500) DEFAULT NULL,
    status ENUM('draft','issued','revoked') NOT NULL DEFAULT 'draft',
    retention_until DATE DEFAULT NULL,
    issued_by INT UNSIGNED DEFAULT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_certificate_number (certificate_number),
    KEY idx_certificate_staff (staff_id),
    CONSTRAINT fk_certificate_staff FOREIGN KEY (staff_id) REFERENCES staff(id) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS statutory_record_retention (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    record_type VARCHAR(80) NOT NULL,
    record_id BIGINT UNSIGNED NOT NULL,
    period_start DATE DEFAULT NULL,
    period_end DATE DEFAULT NULL,
    retain_until DATE NOT NULL,
    status ENUM('active','archived','legal_hold','destroyed') NOT NULL DEFAULT 'active',
    legal_hold_reason VARCHAR(255) DEFAULT NULL,
    archived_at DATETIME DEFAULT NULL,
    destroyed_at DATETIME DEFAULT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_retention_record (record_type, record_id),
    KEY idx_retention_due (retain_until, status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS statutory_audit_log (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    actor_user_id INT UNSIGNED DEFAULT NULL,
    action VARCHAR(80) NOT NULL,
    entity_type VARCHAR(100) NOT NULL,
    entity_id BIGINT UNSIGNED DEFAULT NULL,
    before_json JSON DEFAULT NULL,
    after_json JSON DEFAULT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_statutory_audit_entity (entity_type, entity_id),
    KEY idx_statutory_audit_created (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Initial editable rule snapshots. These are database configuration records,
-- not application constants; administrators can add a new effective-dated
-- version when an official notice changes.
INSERT INTO statutory_rule_sets
    (agency,rule_code,version,effective_from,rules_json,source_name,active)
VALUES
    ('SHIF','employee_contribution','initial','2026-01-01',
     JSON_OBJECT('calculation','percentage_of_gross','employee_rate',2.75,'deadline_day',9,'deadline_basis','working_day_of_following_month'),
     'Initial payroll configuration',1),
    ('NSSF','employee_employer_contribution','initial','2026-01-01',
     JSON_OBJECT('calculation','tiered_percentage','employee_rate',6,'employer_rate',6,'lower_earnings_limit',9000,'upper_earnings_limit',108000,'deadline_day',9,'deadline_basis','working_day_of_following_month'),
     'Initial payroll configuration - verify against current NSSF notice',1),
    ('Housing Levy','employee_employer_contribution','initial','2026-01-01',
     JSON_OBJECT('calculation','percentage_of_gross','employee_rate',1.5,'employer_rate',1.5,'deadline_day',9,'deadline_basis','working_day_of_following_month'),
     'Initial payroll configuration',1),
    ('KRA','paye_bands','initial','2026-01-01',
     JSON_OBJECT('calculation','progressive_bands','personal_relief',2400,'deadline_day',9,'deadline_basis','working_day_of_following_month',
       'bands',JSON_ARRAY(
         JSON_OBJECT('up_to',24000,'rate',10),
         JSON_OBJECT('up_to',32333,'rate',25),
         JSON_OBJECT('up_to',500000,'rate',30),
         JSON_OBJECT('up_to',800000,'rate',32.5),
         JSON_OBJECT('up_to',NULL,'rate',35))),
     'Initial payroll configuration - verify against current KRA notice',1)
ON DUPLICATE KEY UPDATE rules_json=VALUES(rules_json),active=1;
