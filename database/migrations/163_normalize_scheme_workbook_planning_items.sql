-- Migration 163: normalize term-workbook planning selections.
-- The JSON payload remains temporarily for backward compatibility, while all
-- new saves are also represented in these relational child tables.

CREATE TABLE IF NOT EXISTS scheme_workbook_weeks (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    workbook_id INT UNSIGNED NOT NULL,
    academic_year_calendar_week_id INT UNSIGNED NOT NULL,
    week_number SMALLINT UNSIGNED NOT NULL,
    sort_order SMALLINT UNSIGNED NOT NULL DEFAULT 1,
    PRIMARY KEY (id),
    UNIQUE KEY uk_workbook_week (workbook_id, academic_year_calendar_week_id),
    KEY idx_sww_workbook (workbook_id),
    CONSTRAINT fk_sww_workbook FOREIGN KEY (workbook_id) REFERENCES scheme_workbooks(id) ON DELETE CASCADE,
    CONSTRAINT fk_sww_calendar_week FOREIGN KEY (academic_year_calendar_week_id) REFERENCES academic_year_calendar(id) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS scheme_workbook_items (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    workbook_week_id INT UNSIGNED NOT NULL,
    strand_id INT UNSIGNED NOT NULL,
    sub_strand_id INT UNSIGNED NOT NULL,
    title VARCHAR(255) NULL,
    resources TEXT NULL,
    assessment_approach TEXT NULL,
    expected_coverage_notes TEXT NULL,
    sort_order SMALLINT UNSIGNED NOT NULL DEFAULT 1,
    PRIMARY KEY (id),
    KEY idx_sw_item_week (workbook_week_id),
    CONSTRAINT fk_sw_item_week FOREIGN KEY (workbook_week_id) REFERENCES scheme_workbook_weeks(id) ON DELETE CASCADE,
    CONSTRAINT fk_sw_item_strand FOREIGN KEY (strand_id) REFERENCES strands(id) ON DELETE RESTRICT,
    CONSTRAINT fk_sw_item_sub FOREIGN KEY (sub_strand_id) REFERENCES sub_strands(id) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS scheme_workbook_item_outcomes (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    workbook_item_id INT UNSIGNED NOT NULL,
    learning_outcome_id INT UNSIGNED NULL,
    outcome_text TEXT NOT NULL,
    is_custom TINYINT(1) NOT NULL DEFAULT 0,
    sort_order SMALLINT UNSIGNED NOT NULL DEFAULT 1,
    PRIMARY KEY (id),
    KEY idx_swio_item (workbook_item_id),
    CONSTRAINT fk_swio_item FOREIGN KEY (workbook_item_id) REFERENCES scheme_workbook_items(id) ON DELETE CASCADE,
    CONSTRAINT fk_swio_outcome FOREIGN KEY (learning_outcome_id) REFERENCES learning_outcomes(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS scheme_workbook_item_experiences (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    workbook_item_id INT UNSIGNED NOT NULL,
    suggested_experience_id INT UNSIGNED NULL,
    experience_text TEXT NOT NULL,
    is_custom TINYINT(1) NOT NULL DEFAULT 0,
    sort_order SMALLINT UNSIGNED NOT NULL DEFAULT 1,
    PRIMARY KEY (id),
    KEY idx_swix_item (workbook_item_id),
    CONSTRAINT fk_swix_item FOREIGN KEY (workbook_item_id) REFERENCES scheme_workbook_items(id) ON DELETE CASCADE,
    CONSTRAINT fk_swix_experience FOREIGN KEY (suggested_experience_id) REFERENCES sub_strand_suggested_experiences(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
