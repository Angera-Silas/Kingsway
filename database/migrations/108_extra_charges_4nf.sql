-- Migration 108: normalized extra-charge billing model (MariaDB 10.4+)
-- The legacy columns on extra_charges are retained for compatibility. New
-- behavior is stored in the relations below; pricing_tiers JSON is not used.

DELIMITER $$
CREATE PROCEDURE kwa_add_extra_charge_columns()
BEGIN
    IF NOT EXISTS (SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='extra_charges' AND COLUMN_NAME='calculation_mode') THEN
        ALTER TABLE extra_charges ADD COLUMN calculation_mode ENUM('fixed','per_unit') NOT NULL DEFAULT 'fixed' AFTER amount;
        ALTER TABLE extra_charges ADD COLUMN unit_label VARCHAR(50) NULL AFTER calculation_mode;
        ALTER TABLE extra_charges ADD COLUMN unit_price DECIMAL(12,2) NULL AFTER unit_label;
        ALTER TABLE extra_charges ADD COLUMN billing_model ENUM('added_to_fees','paid_separately','optional') NOT NULL DEFAULT 'paid_separately' AFTER charge_frequency;
        ALTER TABLE extra_charges ADD COLUMN billing_frequency ENUM('one_time','daily','weekly','monthly','per_term','per_year') NOT NULL DEFAULT 'one_time' AFTER billing_model;
        ALTER TABLE extra_charges ADD COLUMN visible_on_fee_structure TINYINT(1) NOT NULL DEFAULT 1 AFTER billing_frequency;
        ALTER TABLE extra_charges ADD COLUMN gl_account_id BIGINT UNSIGNED NULL AFTER visible_on_fee_structure;
        ALTER TABLE extra_charges ADD COLUMN target_scope ENUM('new_admissions','existing_students','all_students','boarders','day_students','specific_class') NOT NULL DEFAULT 'all_students' AFTER scope;
    END IF;
    ALTER TABLE extra_charges MODIFY gl_account_id BIGINT UNSIGNED NULL;
    ALTER TABLE extra_charges MODIFY status ENUM('draft','submitted','active','inactive') NOT NULL DEFAULT 'draft';
END$$
DELIMITER ;
CALL kwa_add_extra_charge_columns();
DROP PROCEDURE kwa_add_extra_charge_columns;

SET @admission_source_col := (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='admission_payments' AND COLUMN_NAME='financial_account_id');
SET @sql := IF(@admission_source_col=0, 'ALTER TABLE admission_payments ADD COLUMN financial_account_id BIGINT UNSIGNED NULL AFTER amount', 'SELECT 1');
PREPARE kwa_stmt_source FROM @sql; EXECUTE kwa_stmt_source; DEALLOCATE PREPARE kwa_stmt_source;
SET @admission_source_fk := (SELECT COUNT(*) FROM information_schema.TABLE_CONSTRAINTS WHERE CONSTRAINT_SCHEMA=DATABASE() AND TABLE_NAME='admission_payments' AND CONSTRAINT_NAME='fk_admission_payment_source');
SET @sql := IF(@admission_source_fk=0, 'ALTER TABLE admission_payments ADD CONSTRAINT fk_admission_payment_source FOREIGN KEY (financial_account_id) REFERENCES school_financial_accounts(id)', 'SELECT 1');
PREPARE kwa_stmt_source_fk FROM @sql; EXECUTE kwa_stmt_source_fk; DEALLOCATE PREPARE kwa_stmt_source_fk;

SET @fk_exists := (SELECT COUNT(*) FROM information_schema.TABLE_CONSTRAINTS WHERE CONSTRAINT_SCHEMA=DATABASE() AND TABLE_NAME='extra_charges' AND CONSTRAINT_NAME='fk_ec_gl_account');
SET @sql := IF(@fk_exists=0, 'ALTER TABLE extra_charges ADD CONSTRAINT fk_ec_gl_account FOREIGN KEY (gl_account_id) REFERENCES chart_of_accounts(id)', 'SELECT 1');
PREPARE kwa_stmt FROM @sql; EXECUTE kwa_stmt; DEALLOCATE PREPARE kwa_stmt;

CREATE TABLE IF NOT EXISTS extra_charge_contexts (
    extra_charge_id INT UNSIGNED NOT NULL,
    context_code ENUM('admission','enrollment') NOT NULL,
    PRIMARY KEY (extra_charge_id, context_code),
    CONSTRAINT fk_ecc_charge FOREIGN KEY (extra_charge_id) REFERENCES extra_charges(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS extra_charge_parent_scopes (
    extra_charge_id INT UNSIGNED NOT NULL,
    parent_scope ENUM('new_parent','existing_parent','any_parent') NOT NULL,
    PRIMARY KEY (extra_charge_id, parent_scope),
    CONSTRAINT fk_ecps_charge FOREIGN KEY (extra_charge_id) REFERENCES extra_charges(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS extra_charge_student_types (
    extra_charge_id INT UNSIGNED NOT NULL,
    student_type_id INT UNSIGNED NOT NULL,
    PRIMARY KEY (extra_charge_id, student_type_id),
    CONSTRAINT fk_ecst_charge FOREIGN KEY (extra_charge_id) REFERENCES extra_charges(id) ON DELETE CASCADE,
    CONSTRAINT fk_ecst_type FOREIGN KEY (student_type_id) REFERENCES student_types(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS extra_charge_classes (
    extra_charge_id INT UNSIGNED NOT NULL,
    class_id INT UNSIGNED NOT NULL,
    PRIMARY KEY (extra_charge_id, class_id),
    CONSTRAINT fk_eccs_charge FOREIGN KEY (extra_charge_id) REFERENCES extra_charges(id) ON DELETE CASCADE,
    CONSTRAINT fk_eccs_class FOREIGN KEY (class_id) REFERENCES classes(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS extra_charge_pricing_tiers (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    extra_charge_id INT UNSIGNED NOT NULL,
    condition_code VARCHAR(50) NOT NULL,
    label VARCHAR(120) NOT NULL,
    amount DECIMAL(12,2) NOT NULL,
    sort_order INT NOT NULL DEFAULT 0,
    PRIMARY KEY (id),
    UNIQUE KEY uq_ecpt_condition (extra_charge_id, condition_code),
    CONSTRAINT fk_ecpt_charge FOREIGN KEY (extra_charge_id) REFERENCES extra_charges(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS extra_charge_schedules (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    extra_charge_id INT UNSIGNED NOT NULL,
    frequency ENUM('one_time','daily','weekly','monthly','per_term','per_year') NOT NULL,
    starts_on DATE NOT NULL,
    ends_on DATE NULL,
    academic_year_term_id INT UNSIGNED NULL,
    due_day TINYINT UNSIGNED NULL,
    status ENUM('active','inactive') NOT NULL DEFAULT 'active',
    PRIMARY KEY (id),
    KEY idx_ecs_charge_status (extra_charge_id, status),
    CONSTRAINT fk_ecs_charge FOREIGN KEY (extra_charge_id) REFERENCES extra_charges(id) ON DELETE CASCADE,
    CONSTRAINT fk_ecs_term FOREIGN KEY (academic_year_term_id) REFERENCES academic_year_terms(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS extra_charge_application_obligations (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    application_id INT UNSIGNED NOT NULL,
    extra_charge_id INT UNSIGNED NOT NULL,
    pricing_tier_id INT UNSIGNED NULL,
    amount_due DECIMAL(12,2) NOT NULL,
    amount_paid DECIMAL(12,2) NOT NULL DEFAULT 0,
    status ENUM('pending','partial','paid','waived','cancelled') NOT NULL DEFAULT 'pending',
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_ecao_application_charge (application_id, extra_charge_id),
    CONSTRAINT fk_ecao_application FOREIGN KEY (application_id) REFERENCES admission_applications(id),
    CONSTRAINT fk_ecao_charge FOREIGN KEY (extra_charge_id) REFERENCES extra_charges(id),
    CONSTRAINT fk_ecao_tier FOREIGN KEY (pricing_tier_id) REFERENCES extra_charge_pricing_tiers(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS admission_payment_allocations (
    admission_payment_id INT UNSIGNED NOT NULL,
    application_obligation_id BIGINT UNSIGNED NOT NULL,
    amount DECIMAL(12,2) NOT NULL,
    PRIMARY KEY (admission_payment_id, application_obligation_id),
    CONSTRAINT fk_apa_payment FOREIGN KEY (admission_payment_id) REFERENCES admission_payments(id) ON DELETE CASCADE,
    CONSTRAINT fk_apa_obligation FOREIGN KEY (application_obligation_id) REFERENCES extra_charge_application_obligations(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS extra_charge_student_obligations (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    student_academic_enrollment_id INT UNSIGNED NOT NULL,
    schedule_id INT UNSIGNED NOT NULL,
    academic_year_term_id INT UNSIGNED NULL,
    due_date DATE NOT NULL,
    quantity DECIMAL(12,3) NOT NULL DEFAULT 1,
    unit_price DECIMAL(12,2) NOT NULL,
    amount_due DECIMAL(12,2) NOT NULL,
    amount_paid DECIMAL(12,2) NOT NULL DEFAULT 0,
    status ENUM('pending','partial','paid','waived','cancelled') NOT NULL DEFAULT 'pending',
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_ecso_occurrence (student_academic_enrollment_id, schedule_id, due_date),
    CONSTRAINT fk_ecso_enrollment FOREIGN KEY (student_academic_enrollment_id) REFERENCES student_academic_enrollments(id),
    CONSTRAINT fk_ecso_schedule FOREIGN KEY (schedule_id) REFERENCES extra_charge_schedules(id),
    CONSTRAINT fk_ecso_term FOREIGN KEY (academic_year_term_id) REFERENCES academic_year_terms(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Backfill normalized facts for existing rows. JSON is read only during this
-- migration and is not used by the application afterwards.
INSERT IGNORE INTO extra_charge_contexts (extra_charge_id, context_code)
SELECT id, CASE WHEN target_scope='new_admissions' THEN 'admission' ELSE 'enrollment' END FROM extra_charges;
INSERT IGNORE INTO extra_charge_parent_scopes (extra_charge_id, parent_scope)
SELECT id, CASE WHEN target_scope='new_admissions' THEN 'any_parent' ELSE 'any_parent' END FROM extra_charges WHERE target_scope='new_admissions';
INSERT IGNORE INTO extra_charge_student_types (extra_charge_id, student_type_id)
SELECT id, student_type_id FROM extra_charges WHERE student_type_id IS NOT NULL;
INSERT IGNORE INTO extra_charge_classes (extra_charge_id, class_id)
SELECT id, class_id FROM extra_charges WHERE class_id IS NOT NULL;
INSERT IGNORE INTO extra_charge_pricing_tiers (extra_charge_id, condition_code, label, amount, sort_order)
SELECT ec.id,
       JSON_UNQUOTE(JSON_EXTRACT(ec.pricing_tiers, CONCAT('$[', n.n, '].condition'))),
       COALESCE(JSON_UNQUOTE(JSON_EXTRACT(ec.pricing_tiers, CONCAT('$[', n.n, '].label'))), 'Tier'),
       CAST(JSON_UNQUOTE(JSON_EXTRACT(ec.pricing_tiers, CONCAT('$[', n.n, '].amount'))) AS DECIMAL(12,2)),
       n.n
FROM extra_charges ec
JOIN (SELECT 0 n UNION SELECT 1 UNION SELECT 2 UNION SELECT 3 UNION SELECT 4 UNION SELECT 5 UNION SELECT 6 UNION SELECT 7) n
WHERE JSON_VALID(ec.pricing_tiers)
  AND JSON_UNQUOTE(JSON_EXTRACT(ec.pricing_tiers, CONCAT('$[', n.n, '].condition'))) IS NOT NULL;
INSERT IGNORE INTO extra_charge_schedules (extra_charge_id, frequency, starts_on)
SELECT ec.id, ec.billing_frequency, COALESCE(ay.start_date, CURDATE())
FROM extra_charges ec JOIN academic_years ay ON ay.id=ec.academic_year_id;

SET @idx_exists := (SELECT COUNT(*) FROM information_schema.STATISTICS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='extra_charges' AND INDEX_NAME='idx_ec_target_scope');
SET @sql := IF(@idx_exists=0, 'CREATE INDEX idx_ec_target_scope ON extra_charges(target_scope)', 'SELECT 1');
PREPARE kwa_stmt2 FROM @sql; EXECUTE kwa_stmt2; DEALLOCATE PREPARE kwa_stmt2;

DROP PROCEDURE IF EXISTS sp_generate_due_extra_charge_obligations;
DELIMITER $$
CREATE PROCEDURE sp_generate_due_extra_charge_obligations()
BEGIN
    INSERT IGNORE INTO extra_charge_student_obligations
        (student_academic_enrollment_id,schedule_id,academic_year_term_id,due_date,quantity,unit_price,amount_due)
    SELECT sae.id, sch.id, sch.academic_year_term_id, CURDATE(), 1,
           ec.amount, ec.amount
    FROM student_academic_enrollments sae
    JOIN students s ON s.id=sae.student_id
    JOIN academic_year_class_streams aycs ON aycs.id=sae.academic_year_class_stream_id
    JOIN academic_year_classes ayc ON ayc.id=aycs.academic_year_class_id
    JOIN extra_charges ec ON ec.academic_year_id=sae.academic_year_id
    JOIN extra_charge_contexts ecc ON ecc.extra_charge_id=ec.id AND ecc.context_code='enrollment'
    JOIN extra_charge_schedules sch ON sch.extra_charge_id=ec.id AND sch.status='active'
    LEFT JOIN extra_charge_classes eccs ON eccs.extra_charge_id=ec.id AND eccs.class_id=ayc.class_id
    LEFT JOIN extra_charge_student_types ecst ON ecst.extra_charge_id=ec.id AND ecst.student_type_id=s.student_type_id
    LEFT JOIN student_types st ON st.id=s.student_type_id
    LEFT JOIN academic_year_terms ayt ON ayt.id=sch.academic_year_term_id
    WHERE sae.enrollment_status='active' AND ec.status='active'
      AND ec.billing_model='added_to_fees' AND ec.calculation_mode='fixed'
      AND (sch.ends_on IS NULL OR CURDATE()<=sch.ends_on)
      AND (ec.target_scope='all_students'
        OR (ec.target_scope='boarders' AND st.code IN ('BOARD','WEEKLY'))
        OR (ec.target_scope='day_students' AND st.code='DAY')
        OR (ec.target_scope='specific_class' AND eccs.class_id IS NOT NULL)
        OR ecst.student_type_id IS NOT NULL)
      AND (
        (sch.frequency='one_time' AND sch.starts_on=CURDATE())
        OR (sch.frequency='daily' AND sch.starts_on<=CURDATE())
        OR (sch.frequency='weekly' AND sch.starts_on<=CURDATE() AND DAYOFWEEK(sch.starts_on)=DAYOFWEEK(CURDATE()))
        OR (sch.frequency='monthly' AND sch.starts_on<=CURDATE() AND DAY(CURDATE())=LEAST(COALESCE(sch.due_day,DAY(sch.starts_on)),DAY(LAST_DAY(CURDATE()))))
        OR (sch.frequency='per_year' AND MONTH(sch.starts_on)=MONTH(CURDATE()) AND DAY(sch.starts_on)=DAY(CURDATE()))
        OR (sch.frequency='per_term' AND ayt.opening_date=CURDATE())
      );
END$$
DELIMITER ;

DROP EVENT IF EXISTS ev_generate_due_extra_charge_obligations;
DELIMITER $$
CREATE EVENT ev_generate_due_extra_charge_obligations
    ON SCHEDULE EVERY 1 DAY
    STARTS (CURRENT_DATE + INTERVAL 1 DAY)
    DO CALL sp_generate_due_extra_charge_obligations()$$
DELIMITER ;

-- pricing_tiers was a temporary migration-101 compatibility column. The
-- normalized relation above is now authoritative and the JSON dependency is
-- removed from the production schema.
SET @json_col_exists := (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='extra_charges' AND COLUMN_NAME='pricing_tiers');
SET @sql := IF(@json_col_exists=1, 'ALTER TABLE extra_charges DROP COLUMN pricing_tiers', 'SELECT 1');
PREPARE kwa_stmt3 FROM @sql; EXECUTE kwa_stmt3; DEALLOCATE PREPARE kwa_stmt3;
