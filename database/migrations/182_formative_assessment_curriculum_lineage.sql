-- Preserve the complete CBC planning lineage for teacher-created formative
-- assessments. An assessment keeps one primary strand/sub-strand target while
-- learning outcomes and rubric criteria remain normalized many-to-many data.

ALTER TABLE assessments
    ADD COLUMN scheme_of_work_id INT UNSIGNED NULL AFTER coverage_id,
    ADD COLUMN lesson_plan_id INT UNSIGNED NULL AFTER scheme_of_work_id,
    ADD COLUMN assessment_tool_id INT UNSIGNED NULL AFTER assessment_type_id,
    ADD COLUMN description TEXT NULL AFTER title,
    ADD COLUMN updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
        ON UPDATE CURRENT_TIMESTAMP AFTER created_at,
    ADD KEY idx_assessment_scheme (scheme_of_work_id),
    ADD KEY idx_assessment_lesson_plan (lesson_plan_id),
    ADD KEY idx_assessment_tool (assessment_tool_id),
    ADD CONSTRAINT fk_assessment_scheme
        FOREIGN KEY (scheme_of_work_id) REFERENCES schemes_of_work(id)
        ON DELETE SET NULL,
    ADD CONSTRAINT fk_assessment_lesson_plan
        FOREIGN KEY (lesson_plan_id) REFERENCES lesson_plans(id)
        ON DELETE SET NULL,
    ADD CONSTRAINT fk_assessment_tool
        FOREIGN KEY (assessment_tool_id) REFERENCES assessment_tools(id)
        ON DELETE RESTRICT;

CREATE TABLE assessment_learning_outcomes (
    assessment_id INT UNSIGNED NOT NULL,
    learning_outcome_id INT UNSIGNED NOT NULL,
    sort_order INT UNSIGNED NOT NULL DEFAULT 0,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (assessment_id, learning_outcome_id),
    KEY idx_assessment_outcome_outcome (learning_outcome_id),
    CONSTRAINT fk_assessment_outcome_assessment
        FOREIGN KEY (assessment_id) REFERENCES assessments(id)
        ON DELETE CASCADE,
    CONSTRAINT fk_assessment_outcome_learning_outcome
        FOREIGN KEY (learning_outcome_id) REFERENCES learning_outcomes(id)
        ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE assessment_rubric_criteria (
    assessment_id INT UNSIGNED NOT NULL,
    assessment_rubric_id INT UNSIGNED NOT NULL,
    weight DECIMAL(5,2) NULL,
    sort_order INT UNSIGNED NOT NULL DEFAULT 0,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (assessment_id, assessment_rubric_id),
    KEY idx_assessment_rubric_rubric (assessment_rubric_id),
    CONSTRAINT fk_assessment_rubric_assessment
        FOREIGN KEY (assessment_id) REFERENCES assessments(id)
        ON DELETE CASCADE,
    CONSTRAINT fk_assessment_rubric_criterion
        FOREIGN KEY (assessment_rubric_id) REFERENCES assessment_rubrics(id)
        ON DELETE RESTRICT,
    CONSTRAINT chk_assessment_rubric_weight
        CHECK (weight IS NULL OR (weight > 0 AND weight <= 100))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
