-- =============================================================
-- Migration 101: Enhanced Extra Charges
-- Adds flexible pricing, billing, targeting, and accounting
-- fields to extra_charges. Replaces the rigid scope/frequency
-- model with a fully configurable charge definition.
-- =============================================================

-- 1. Add new columns
ALTER TABLE extra_charges
    ADD COLUMN calculation_mode ENUM('fixed','per_unit') NOT NULL DEFAULT 'fixed' AFTER amount,
    ADD COLUMN unit_label VARCHAR(50) NULL COMMENT 'e.g. km, month, student' AFTER calculation_mode,
    ADD COLUMN unit_price DECIMAL(12,2) NULL COMMENT 'price per unit when per_unit' AFTER unit_label,
    ADD COLUMN billing_model ENUM('added_to_fees','paid_separately','optional') NOT NULL DEFAULT 'paid_separately' AFTER charge_frequency,
    ADD COLUMN billing_frequency ENUM('one_time','daily','weekly','monthly','per_term','per_year') NOT NULL DEFAULT 'one_time' AFTER billing_model,
    ADD COLUMN visible_on_fee_structure TINYINT(1) NOT NULL DEFAULT 1 AFTER billing_frequency,
    ADD COLUMN gl_account_id INT UNSIGNED NULL COMMENT 'FK chart_of_accounts — which account is credited' AFTER visible_on_fee_structure,
    ADD COLUMN target_scope ENUM('new_admissions','existing_students','all_students','boarders','day_students','specific_class') NOT NULL DEFAULT 'all_students' AFTER scope,
    ADD COLUMN pricing_tiers JSON NULL COMMENT '[{label,amount,condition}] for tiered pricing' AFTER target_scope;

-- 2. Migrate existing data from old scope/frequency to new fields
UPDATE extra_charges SET
    calculation_mode = 'fixed',
    billing_model = 'paid_separately',
    billing_frequency = charge_frequency,
    target_scope = CASE scope
        WHEN 'all' THEN 'all_students'
        WHEN 'student_type' THEN 'all_students'
        WHEN 'class' THEN 'specific_class'
        ELSE 'all_students'
    END,
    visible_on_fee_structure = 1
WHERE calculation_mode = 'fixed' AND billing_model = 'paid_separately';

-- 3. Update Registration Fee seed: tiered pricing + new_admissions scope
UPDATE extra_charges SET
    target_scope = 'new_admissions',
    billing_model = 'paid_separately',
    billing_frequency = 'one_time',
    calculation_mode = 'fixed',
    visible_on_fee_structure = 1,
    pricing_tiers = '[{"label":"New Parents","amount":2000,"condition":"new"},{"label":"Existing Parents","amount":1000,"condition":"existing"}]'
WHERE name = 'Registration Fee';

-- 4. Add FK constraint for gl_account_id (if chart_of_accounts exists)
SET @fk_exists = (
    SELECT COUNT(*) FROM information_schema.TABLE_CONSTRAINTS
    WHERE CONSTRAINT_SCHEMA = DATABASE()
      AND TABLE_NAME = 'extra_charges'
      AND CONSTRAINT_NAME = 'fk_ec_gl_account'
);
SET @sql = IF(@fk_exists = 0,
    'ALTER TABLE extra_charges ADD CONSTRAINT fk_ec_gl_account FOREIGN KEY (gl_account_id) REFERENCES chart_of_accounts(id)',
    'SELECT 1'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- 5. Add index for new targeting column
CREATE INDEX idx_ec_target_scope ON extra_charges(target_scope);
