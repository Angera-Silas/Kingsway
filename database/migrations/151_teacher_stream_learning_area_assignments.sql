-- Stream-specific subject ownership for Grade 4-9 and any future parallel
-- streams. Existing class-level assignments remain valid as a fallback for
-- schools that intentionally assign a learning area to every stream.
CREATE TABLE IF NOT EXISTS academic_year_class_stream_learning_area_teachers (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    academic_year_class_stream_id INT UNSIGNED NOT NULL,
    academic_year_term_id INT UNSIGNED NOT NULL,
    learning_area_id INT UNSIGNED NOT NULL,
    staff_id INT UNSIGNED NOT NULL,
    role ENUM('subject_teacher','assistant','hod') NOT NULL DEFAULT 'subject_teacher',
    status ENUM('active','ended') NOT NULL DEFAULT 'active',
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uk_stream_term_area_staff_role (
        academic_year_class_stream_id, academic_year_term_id,
        learning_area_id, staff_id, role
    ),
    KEY idx_stream_term (academic_year_class_stream_id, academic_year_term_id),
    KEY idx_stream_teacher (staff_id, academic_year_term_id),
    CONSTRAINT fk_stream_la_teacher_stream
        FOREIGN KEY (academic_year_class_stream_id)
        REFERENCES academic_year_class_streams (id)
        ON DELETE CASCADE,
    CONSTRAINT fk_stream_la_teacher_term
        FOREIGN KEY (academic_year_term_id)
        REFERENCES academic_year_terms (id)
        ON DELETE CASCADE,
    CONSTRAINT fk_stream_la_teacher_area
        FOREIGN KEY (learning_area_id)
        REFERENCES learning_areas (id)
        ON DELETE RESTRICT,
    CONSTRAINT fk_stream_la_teacher_staff
        FOREIGN KEY (staff_id)
        REFERENCES staff (id)
        ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
