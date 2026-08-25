-- Annual student assistance awards.
-- A programme describes the kind of assistance; an award records its
-- student/year-specific decision.  This keeps programme facts separate from
-- student assignments (4NF) and avoids using students.is_sponsored as an
-- annual accounting decision.
CREATE TABLE IF NOT EXISTS scholarship_programs (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    code VARCHAR(50) NOT NULL,
    name VARCHAR(120) NOT NULL,
    coverage_type ENUM('full','percentage','fixed_amount') NOT NULL,
    default_percentage DECIMAL(5,2) NULL,
    default_amount DECIMAL(12,2) NULL,
    description TEXT NULL,
    is_active TINYINT(1) NOT NULL DEFAULT 1,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uk_scholarship_program_code (code),
    KEY idx_scholarship_program_active (is_active)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO scholarship_programs
    (code, name, coverage_type, default_percentage, description)
VALUES
    ('fully_sponsored', 'Fully sponsored', 'full', 100.00, 'The school clears the covered fee obligations.'),
    ('half_sponsored', 'Half sponsored', 'percentage', 50.00, 'The school covers half of each covered obligation.'),
    ('partial_scholarship', 'Partial scholarship', 'percentage', NULL, 'A school-defined percentage award.'),
    ('fixed_school_grant', 'Fixed school grant', 'fixed_amount', NULL, 'A fixed amount applied to each covered obligation.')
ON DUPLICATE KEY UPDATE name = VALUES(name), description = VALUES(description), is_active = 1;

CREATE TABLE IF NOT EXISTS student_scholarship_awards (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    student_id INT UNSIGNED NOT NULL,
    scholarship_program_id INT UNSIGNED NOT NULL,
    academic_year_id INT UNSIGNED NOT NULL,
    coverage_type ENUM('full','percentage','fixed_amount') NOT NULL,
    coverage_percentage DECIMAL(5,2) NULL,
    coverage_amount DECIMAL(12,2) NULL,
    reason TEXT NOT NULL,
    starts_on DATE NULL,
    ends_on DATE NULL,
    status ENUM('active','revoked','expired') NOT NULL DEFAULT 'active',
    awarded_by INT UNSIGNED NOT NULL,
    revoked_by INT UNSIGNED NULL,
    revoked_at DATETIME NULL,
    notes TEXT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uk_student_year_scholarship (student_id, academic_year_id),
    KEY idx_scholarship_award_status (status),
    KEY idx_scholarship_award_year (academic_year_id),
    CONSTRAINT fk_scholarship_award_student FOREIGN KEY (student_id) REFERENCES students(id),
    CONSTRAINT fk_scholarship_award_program FOREIGN KEY (scholarship_program_id) REFERENCES scholarship_programs(id),
    CONSTRAINT fk_scholarship_award_year FOREIGN KEY (academic_year_id) REFERENCES academic_years(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
