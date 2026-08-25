-- Term-wide teacher scheme workspace. It stores incomplete weekly planning
-- until the teacher submits the finished workbook for senior review.
CREATE TABLE IF NOT EXISTS scheme_workbooks (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    academic_year_id INT UNSIGNED NOT NULL,
    academic_year_term_id INT UNSIGNED NOT NULL,
    academic_year_class_stream_learning_area_id INT UNSIGNED NOT NULL,
    teacher_id INT UNSIGNED NOT NULL,
    title VARCHAR(200) NULL,
    payload JSON NOT NULL,
    status ENUM('draft','submitted','approved','archived') NOT NULL DEFAULT 'draft',
    submitted_at DATETIME NULL,
    approved_by INT UNSIGNED NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uk_scheme_workbook_context (academic_year_term_id, academic_year_class_stream_learning_area_id, teacher_id, status),
    KEY idx_scheme_workbook_teacher (teacher_id),
    CONSTRAINT fk_scheme_workbook_stream_area FOREIGN KEY (academic_year_class_stream_learning_area_id) REFERENCES academic_year_class_stream_learning_areas(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

ALTER TABLE schemes_of_work
    ADD COLUMN IF NOT EXISTS scheme_workbook_id INT UNSIGNED NULL AFTER scheme_template_id,
    ADD KEY IF NOT EXISTS idx_schemes_workbook (scheme_workbook_id);
