-- Migration 172: atomic learner responses and resource-use evidence.
-- A lesson question is not the same as a learner outcome. This preserves
-- both the question asked and the learner's response/attainment.

CREATE TABLE IF NOT EXISTS lesson_plan_learner_evidence_questions (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    learner_evidence_id BIGINT UNSIGNED NOT NULL,
    lesson_plan_inquiry_question_id INT UNSIGNED DEFAULT NULL,
    question_text VARCHAR(1000) NOT NULL,
    response_text VARCHAR(2000) DEFAULT NULL,
    response_status ENUM('not_answered','correct','partially_correct','incorrect','not_applicable') NOT NULL DEFAULT 'not_answered',
    score DECIMAL(8,2) DEFAULT NULL,
    teacher_note VARCHAR(1000) DEFAULT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_learner_evidence_question (learner_evidence_id, lesson_plan_inquiry_question_id),
    KEY idx_lpeq_plan_question (lesson_plan_inquiry_question_id),
    CONSTRAINT fk_lpeq_evidence FOREIGN KEY (learner_evidence_id) REFERENCES lesson_plan_learner_evidence(id) ON DELETE CASCADE,
    CONSTRAINT fk_lpeq_question FOREIGN KEY (lesson_plan_inquiry_question_id) REFERENCES lesson_plan_inquiry_questions(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS lesson_plan_learner_evidence_resources (
    learner_evidence_id BIGINT UNSIGNED NOT NULL,
    lesson_plan_resource_id INT UNSIGNED NOT NULL,
    used TINYINT(1) NOT NULL DEFAULT 1,
    learner_note VARCHAR(500) DEFAULT NULL,
    PRIMARY KEY (learner_evidence_id, lesson_plan_resource_id),
    CONSTRAINT fk_lper_evidence FOREIGN KEY (learner_evidence_id) REFERENCES lesson_plan_learner_evidence(id) ON DELETE CASCADE,
    CONSTRAINT fk_lper_resource FOREIGN KEY (lesson_plan_resource_id) REFERENCES lesson_plan_resources(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
