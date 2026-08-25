-- Canonical link: approved exam timetable entry -> published schedule -> summative assessment.
-- Also hardens score entry for draft/submission/moderation and records an append-only audit trail.

ALTER TABLE exam_timetable_draft_entries
    ADD COLUMN assessment_type_id INT UNSIGNED NULL AFTER exam_type,
    ADD COLUMN max_marks DECIMAL(8,2) NULL AFTER assessment_type_id,
    ADD KEY idx_exam_draft_assessment_type (assessment_type_id),
    ADD CONSTRAINT fk_exam_draft_assessment_type
        FOREIGN KEY (assessment_type_id) REFERENCES assessment_types(id);

ALTER TABLE exam_schedules
    ADD COLUMN exam_timetable_draft_id BIGINT UNSIGNED NULL AFTER id,
    ADD COLUMN exam_timetable_draft_entry_id BIGINT UNSIGNED NULL AFTER exam_timetable_draft_id,
    ADD COLUMN assessment_id INT UNSIGNED NULL AFTER learning_area_id,
    ADD COLUMN published_by INT UNSIGNED NULL AFTER created_by,
    ADD COLUMN published_at DATETIME NULL AFTER published_by,
    ADD UNIQUE KEY uk_exam_schedule_draft_entry (exam_timetable_draft_entry_id),
    ADD UNIQUE KEY uk_exam_schedule_assessment (assessment_id),
    ADD KEY idx_exam_schedule_draft (exam_timetable_draft_id),
    ADD CONSTRAINT fk_exam_schedule_draft
        FOREIGN KEY (exam_timetable_draft_id) REFERENCES exam_timetable_drafts(id),
    ADD CONSTRAINT fk_exam_schedule_draft_entry
        FOREIGN KEY (exam_timetable_draft_entry_id) REFERENCES exam_timetable_draft_entries(id),
    ADD CONSTRAINT fk_exam_schedule_assessment
        FOREIGN KEY (assessment_id) REFERENCES assessments(id);

ALTER TABLE assessments
    ADD COLUMN submitted_by INT UNSIGNED NULL AFTER approved_by,
    ADD COLUMN submitted_at DATETIME NULL AFTER submitted_by,
    ADD COLUMN moderated_by INT UNSIGNED NULL AFTER submitted_at,
    ADD COLUMN moderated_at DATETIME NULL AFTER moderated_by,
    ADD COLUMN reopened_by INT UNSIGNED NULL AFTER moderated_at,
    ADD COLUMN reopened_at DATETIME NULL AFTER reopened_by,
    ADD COLUMN reopen_reason VARCHAR(255) NULL AFTER reopened_at;

ALTER TABLE assessment_results
    MODIFY COLUMN id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    MODIFY COLUMN marks_obtained DECIMAL(12,2) NULL,
    MODIFY COLUMN submitted_at DATETIME NULL DEFAULT NULL,
    ADD COLUMN entry_status ENUM('present','absent','exempted') NOT NULL DEFAULT 'present' AFTER marks_obtained,
    ADD COLUMN moderation_note VARCHAR(255) NULL AFTER remarks,
    ADD COLUMN updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP AFTER created_at;

-- The seeded scale used integer upper bounds (for example 75-89), which left
-- decimal percentages such as 89.50 ungraded. Make every configured scale
-- continuous while retaining its administrator-defined lower thresholds.
UPDATE grade_rules current_rule
LEFT JOIN grade_rules higher_rule
  ON higher_rule.scale_id = current_rule.scale_id
 AND higher_rule.sort_order = current_rule.sort_order - 1
SET current_rule.max_mark = CASE
    WHEN higher_rule.id IS NULL THEN 100.00
    ELSE higher_rule.min_mark - 0.01
END;

CREATE TABLE IF NOT EXISTS assessment_result_events (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    assessment_id INT UNSIGNED NOT NULL,
    assessment_result_id INT UNSIGNED NULL,
    student_academic_enrollment_id INT UNSIGNED NOT NULL,
    event_type ENUM('created','updated','submitted','approved','rejected','reopened') NOT NULL,
    old_values_json LONGTEXT NULL CHECK (old_values_json IS NULL OR JSON_VALID(old_values_json)),
    new_values_json LONGTEXT NULL CHECK (new_values_json IS NULL OR JSON_VALID(new_values_json)),
    reason VARCHAR(255) NULL,
    actor_user_id INT UNSIGNED NULL,
    actor_staff_id INT UNSIGNED NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_assessment_result_event_assessment (assessment_id, created_at),
    KEY idx_assessment_result_event_result (assessment_result_id),
    KEY idx_assessment_result_event_enrollment (student_academic_enrollment_id),
    CONSTRAINT fk_assessment_result_event_assessment
        FOREIGN KEY (assessment_id) REFERENCES assessments(id),
    CONSTRAINT fk_assessment_result_event_result
        FOREIGN KEY (assessment_result_id) REFERENCES assessment_results(id),
    CONSTRAINT fk_assessment_result_event_enrollment
        FOREIGN KEY (student_academic_enrollment_id) REFERENCES student_academic_enrollments(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
