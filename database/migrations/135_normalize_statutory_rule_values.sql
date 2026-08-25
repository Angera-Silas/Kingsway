-- Replace JSON statutory rules with normalized, editable payroll data.
CREATE TABLE IF NOT EXISTS statutory_rule_versions (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    agency VARCHAR(80) NOT NULL,
    rule_code VARCHAR(100) NOT NULL,
    version VARCHAR(40) NOT NULL,
    effective_from DATE NOT NULL,
    effective_to DATE DEFAULT NULL,
    calculation_method VARCHAR(80) NOT NULL,
    employee_rate DECIMAL(9,4) DEFAULT NULL,
    employer_rate DECIMAL(9,4) DEFAULT NULL,
    lower_earnings_limit DECIMAL(14,2) DEFAULT NULL,
    upper_earnings_limit DECIMAL(14,2) DEFAULT NULL,
    cap_amount DECIMAL(14,2) DEFAULT NULL,
    personal_relief DECIMAL(14,2) DEFAULT NULL,
    deadline_day TINYINT UNSIGNED DEFAULT NULL,
    deadline_basis VARCHAR(60) DEFAULT NULL,
    source_name VARCHAR(255) DEFAULT NULL,
    source_url VARCHAR(500) DEFAULT NULL,
    active TINYINT(1) NOT NULL DEFAULT 1,
    created_by INT UNSIGNED DEFAULT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_statutory_rule_version_normalized (agency,rule_code,version),
    KEY idx_statutory_rule_effective_normalized (agency,rule_code,effective_from,effective_to,active)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS statutory_tax_bands (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    rule_version_id BIGINT UNSIGNED NOT NULL,
    band_order SMALLINT UNSIGNED NOT NULL,
    lower_bound DECIMAL(14,2) NOT NULL,
    upper_bound DECIMAL(14,2) DEFAULT NULL,
    tax_rate DECIMAL(9,4) NOT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_statutory_tax_band_order (rule_version_id,band_order),
    KEY idx_statutory_tax_band_rule (rule_version_id),
    CONSTRAINT fk_statutory_tax_band_rule FOREIGN KEY (rule_version_id) REFERENCES statutory_rule_versions(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO statutory_rule_versions
    (agency,rule_code,version,effective_from,calculation_method,employee_rate,employer_rate,
     lower_earnings_limit,upper_earnings_limit,personal_relief,deadline_day,deadline_basis,source_name,active)
VALUES
    ('SHIF','employee_contribution','initial','2026-01-01','percentage_of_gross',2.75,NULL,NULL,NULL,NULL,9,'working_day_of_following_month','Initial payroll configuration',1),
    ('NSSF','employee_employer_contribution','initial','2026-01-01','tiered_percentage',6,6,9000,108000,NULL,9,'working_day_of_following_month','Initial payroll configuration - verify against current NSSF notice',1),
    ('Housing Levy','employee_employer_contribution','initial','2026-01-01','percentage_of_gross',1.5,1.5,NULL,NULL,NULL,9,'working_day_of_following_month','Initial payroll configuration',1),
    ('KRA','paye_bands','initial','2026-01-01','progressive_bands',NULL,NULL,NULL,NULL,2400,9,'working_day_of_following_month','Initial payroll configuration - verify against current KRA notice',1)
ON DUPLICATE KEY UPDATE active=1;

INSERT INTO statutory_tax_bands (rule_version_id,band_order,lower_bound,upper_bound,tax_rate)
SELECT id,1,0,24000,10 FROM statutory_rule_versions WHERE agency='KRA' AND rule_code='paye_bands' AND version='initial'
ON DUPLICATE KEY UPDATE upper_bound=VALUES(upper_bound),tax_rate=VALUES(tax_rate);
INSERT INTO statutory_tax_bands (rule_version_id,band_order,lower_bound,upper_bound,tax_rate)
SELECT id,2,24000,32333,25 FROM statutory_rule_versions WHERE agency='KRA' AND rule_code='paye_bands' AND version='initial'
ON DUPLICATE KEY UPDATE upper_bound=VALUES(upper_bound),tax_rate=VALUES(tax_rate);
INSERT INTO statutory_tax_bands (rule_version_id,band_order,lower_bound,upper_bound,tax_rate)
SELECT id,3,32333,500000,30 FROM statutory_rule_versions WHERE agency='KRA' AND rule_code='paye_bands' AND version='initial'
ON DUPLICATE KEY UPDATE upper_bound=VALUES(upper_bound),tax_rate=VALUES(tax_rate);
INSERT INTO statutory_tax_bands (rule_version_id,band_order,lower_bound,upper_bound,tax_rate)
SELECT id,4,500000,800000,32.5 FROM statutory_rule_versions WHERE agency='KRA' AND rule_code='paye_bands' AND version='initial'
ON DUPLICATE KEY UPDATE upper_bound=VALUES(upper_bound),tax_rate=VALUES(tax_rate);
INSERT INTO statutory_tax_bands (rule_version_id,band_order,lower_bound,upper_bound,tax_rate)
SELECT id,5,800000,NULL,35 FROM statutory_rule_versions WHERE agency='KRA' AND rule_code='paye_bands' AND version='initial'
ON DUPLICATE KEY UPDATE upper_bound=VALUES(upper_bound),tax_rate=VALUES(tax_rate);

DROP TABLE IF EXISTS statutory_rule_sets;
