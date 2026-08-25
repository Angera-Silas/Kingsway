-- Assessment-tool rubric criteria are distinct from the CBC sub-strand
-- expectation rubric. A lesson may use either or both.
CREATE TABLE IF NOT EXISTS lesson_plan_assessment_rubrics (
    lesson_plan_id INT UNSIGNED NOT NULL,
    assessment_rubric_id INT UNSIGNED NOT NULL,
    PRIMARY KEY (lesson_plan_id, assessment_rubric_id),
    CONSTRAINT fk_lpar_plan FOREIGN KEY (lesson_plan_id) REFERENCES lesson_plans(id) ON DELETE CASCADE,
    CONSTRAINT fk_lpar_rubric FOREIGN KEY (assessment_rubric_id) REFERENCES assessment_rubrics(id) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

ALTER TABLE lesson_plan_learner_evidence
    ADD COLUMN assessment_rubric_id INT UNSIGNED NULL AFTER sub_strand_rubric_id,
    ADD KEY idx_lple_assessment_rubric (assessment_rubric_id),
    ADD CONSTRAINT fk_lple_assessment_rubric FOREIGN KEY (assessment_rubric_id) REFERENCES assessment_rubrics(id) ON DELETE SET NULL;
