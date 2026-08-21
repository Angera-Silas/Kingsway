-- =============================================================
-- Migration 100: Extra Charges + Payment Accounts
-- Flexible, database-driven extra charges that appear on the
-- fee structure printout.  NOT automatically billed — staff
-- decide what bills what, where, and when.
-- =============================================================

-- 1. Extra charges table
CREATE TABLE IF NOT EXISTS extra_charges (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    academic_year_id INT UNSIGNED NOT NULL,
    name VARCHAR(150) NOT NULL,
    description TEXT NULL,
    amount DECIMAL(12,2) NOT NULL,
    charge_frequency ENUM('one_time','per_term','per_year') NOT NULL DEFAULT 'one_time',
    scope ENUM('all','student_type','class','specific_students') NOT NULL DEFAULT 'all',
    student_type_id INT UNSIGNED NULL,
    class_id INT UNSIGNED NULL,
    display_order INT NOT NULL DEFAULT 0,
    status ENUM('draft','active','inactive') NOT NULL DEFAULT 'draft',
    created_by INT UNSIGNED NULL,
    approved_by INT UNSIGNED NULL,
    approved_at DATETIME NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_ec_academic_year (academic_year_id),
    KEY idx_ec_status (status),
    KEY idx_ec_scope (scope),
    KEY idx_ec_student_type (student_type_id),
    KEY idx_ec_class (class_id),
    CONSTRAINT fk_ec_academic_year FOREIGN KEY (academic_year_id) REFERENCES academic_years(id),
    CONSTRAINT fk_ec_student_type FOREIGN KEY (student_type_id) REFERENCES student_types(id),
    CONSTRAINT fk_ec_class FOREIGN KEY (class_id) REFERENCES classes(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 2. Review / approval audit log
CREATE TABLE IF NOT EXISTS extra_charge_review_log (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    extra_charge_id INT UNSIGNED NOT NULL,
    action ENUM('submitted','approved','rejected','reopened') NOT NULL,
    reviewer_id INT UNSIGNED NOT NULL,
    notes TEXT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_ecrl_charge (extra_charge_id),
    KEY idx_ecrl_action (action),
    CONSTRAINT fk_ecrl_charge FOREIGN KEY (extra_charge_id) REFERENCES extra_charges(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 3. Seed default registration fee as draft for each current year
INSERT IGNORE INTO extra_charges
    (academic_year_id, name, description, amount, charge_frequency, scope, status, display_order)
SELECT
    ay.id,
    'Registration Fee',
    'One-time admission/registration fee for new students',
    2000.00,
    'one_time',
    'all',
    'draft',
    1
FROM academic_years ay
WHERE ay.is_current = 1;
