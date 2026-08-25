-- Migration 165: atomic lesson-delivery planning and learner evidence.
--
-- The legacy lesson_templates.resources/assessment columns remain readable for
-- old records. New lesson plans use the tables below as the source of truth.

CREATE TABLE IF NOT EXISTS sub_strand_resources (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    sub_strand_id INT UNSIGNED NOT NULL,
    resource_name VARCHAR(255) NOT NULL,
    resource_type VARCHAR(80) DEFAULT NULL,
    resource_url VARCHAR(500) DEFAULT NULL,
    description VARCHAR(500) DEFAULT NULL,
    source_document VARCHAR(255) DEFAULT NULL,
    source_page VARCHAR(20) DEFAULT NULL,
    status ENUM('active','archived') NOT NULL DEFAULT 'active',
    created_by INT UNSIGNED DEFAULT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_sub_strand_resource (sub_strand_id, resource_name),
    KEY idx_ss_resource_sub_strand (sub_strand_id),
    CONSTRAINT fk_ss_resource_sub_strand FOREIGN KEY (sub_strand_id) REFERENCES sub_strands(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS sub_strand_assessment_tools (
    sub_strand_id INT UNSIGNED NOT NULL,
    assessment_tool_id INT UNSIGNED NOT NULL,
    is_recommended TINYINT(1) NOT NULL DEFAULT 1,
    sort_order SMALLINT UNSIGNED NOT NULL DEFAULT 1,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (sub_strand_id, assessment_tool_id),
    CONSTRAINT fk_ss_tool_sub_strand FOREIGN KEY (sub_strand_id) REFERENCES sub_strands(id) ON DELETE CASCADE,
    CONSTRAINT fk_ss_tool_tool FOREIGN KEY (assessment_tool_id) REFERENCES assessment_tools(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS lesson_plan_outcomes (
    lesson_plan_id INT UNSIGNED NOT NULL,
    learning_outcome_id INT UNSIGNED NOT NULL,
    sort_order SMALLINT UNSIGNED NOT NULL DEFAULT 1,
    teacher_note VARCHAR(500) DEFAULT NULL,
    PRIMARY KEY (lesson_plan_id, learning_outcome_id),
    CONSTRAINT fk_lpo_plan FOREIGN KEY (lesson_plan_id) REFERENCES lesson_plans(id) ON DELETE CASCADE,
    CONSTRAINT fk_lpo_outcome FOREIGN KEY (learning_outcome_id) REFERENCES learning_outcomes(id) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS lesson_plan_experiences (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    lesson_plan_id INT UNSIGNED NOT NULL,
    suggested_experience_id INT UNSIGNED DEFAULT NULL,
    experience_text VARCHAR(1000) NOT NULL,
    is_custom TINYINT(1) NOT NULL DEFAULT 0,
    sort_order SMALLINT UNSIGNED NOT NULL DEFAULT 1,
    PRIMARY KEY (id),
    KEY idx_lpe_plan (lesson_plan_id),
    CONSTRAINT fk_lpe_plan FOREIGN KEY (lesson_plan_id) REFERENCES lesson_plans(id) ON DELETE CASCADE,
    CONSTRAINT fk_lpe_suggested FOREIGN KEY (suggested_experience_id) REFERENCES sub_strand_suggested_experiences(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS lesson_plan_activities (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    lesson_plan_id INT UNSIGNED NOT NULL,
    activity_text VARCHAR(1000) NOT NULL,
    activity_stage ENUM('introduction','development','practice','closure','homework','other') NOT NULL DEFAULT 'development',
    sort_order SMALLINT UNSIGNED NOT NULL DEFAULT 1,
    PRIMARY KEY (id),
    KEY idx_lpa_plan (lesson_plan_id),
    CONSTRAINT fk_lpa_plan FOREIGN KEY (lesson_plan_id) REFERENCES lesson_plans(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS lesson_plan_resources (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    lesson_plan_id INT UNSIGNED NOT NULL,
    sub_strand_resource_id INT UNSIGNED DEFAULT NULL,
    resource_name VARCHAR(255) NOT NULL,
    resource_type VARCHAR(80) DEFAULT NULL,
    resource_url VARCHAR(500) DEFAULT NULL,
    is_custom TINYINT(1) NOT NULL DEFAULT 0,
    sort_order SMALLINT UNSIGNED NOT NULL DEFAULT 1,
    PRIMARY KEY (id),
    KEY idx_lpr_plan (lesson_plan_id),
    CONSTRAINT fk_lpr_plan FOREIGN KEY (lesson_plan_id) REFERENCES lesson_plans(id) ON DELETE CASCADE,
    CONSTRAINT fk_lpr_master FOREIGN KEY (sub_strand_resource_id) REFERENCES sub_strand_resources(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS lesson_plan_assessment_tools (
    lesson_plan_id INT UNSIGNED NOT NULL,
    assessment_tool_id INT UNSIGNED NOT NULL,
    sort_order SMALLINT UNSIGNED NOT NULL DEFAULT 1,
    PRIMARY KEY (lesson_plan_id, assessment_tool_id),
    CONSTRAINT fk_lpat_plan FOREIGN KEY (lesson_plan_id) REFERENCES lesson_plans(id) ON DELETE CASCADE,
    CONSTRAINT fk_lpat_tool FOREIGN KEY (assessment_tool_id) REFERENCES assessment_tools(id) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS lesson_plan_competencies (
    lesson_plan_id INT UNSIGNED NOT NULL,
    competency_id INT UNSIGNED NOT NULL,
    PRIMARY KEY (lesson_plan_id, competency_id),
    CONSTRAINT fk_lpc_plan FOREIGN KEY (lesson_plan_id) REFERENCES lesson_plans(id) ON DELETE CASCADE,
    CONSTRAINT fk_lpc_competency FOREIGN KEY (competency_id) REFERENCES core_competencies(id) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS lesson_plan_rubrics (
    lesson_plan_id INT UNSIGNED NOT NULL,
    sub_strand_rubric_id INT UNSIGNED NOT NULL,
    PRIMARY KEY (lesson_plan_id, sub_strand_rubric_id),
    CONSTRAINT fk_lpru_plan FOREIGN KEY (lesson_plan_id) REFERENCES lesson_plans(id) ON DELETE CASCADE,
    CONSTRAINT fk_lpru_rubric FOREIGN KEY (sub_strand_rubric_id) REFERENCES sub_strand_rubrics(id) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS lesson_plan_inquiry_questions (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    lesson_plan_id INT UNSIGNED NOT NULL,
    question_text VARCHAR(1000) NOT NULL,
    is_custom TINYINT(1) NOT NULL DEFAULT 0,
    sort_order SMALLINT UNSIGNED NOT NULL DEFAULT 1,
    PRIMARY KEY (id),
    KEY idx_lpiq_plan (lesson_plan_id),
    CONSTRAINT fk_lpiq_plan FOREIGN KEY (lesson_plan_id) REFERENCES lesson_plans(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS lesson_plan_coverage_items (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    lesson_plan_id INT UNSIGNED NOT NULL,
    coverage_text VARCHAR(1000) NOT NULL,
    expected TINYINT(1) NOT NULL DEFAULT 1,
    delivered TINYINT(1) NOT NULL DEFAULT 0,
    deviation_reason VARCHAR(500) DEFAULT NULL,
    reflection VARCHAR(1000) DEFAULT NULL,
    sort_order SMALLINT UNSIGNED NOT NULL DEFAULT 1,
    PRIMARY KEY (id),
    KEY idx_lpcv_plan (lesson_plan_id),
    CONSTRAINT fk_lpcv_plan FOREIGN KEY (lesson_plan_id) REFERENCES lesson_plans(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS lesson_plan_learner_evidence (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    lesson_plan_id INT UNSIGNED NOT NULL,
    student_academic_enrollment_id INT UNSIGNED NOT NULL,
    learning_outcome_id INT UNSIGNED NOT NULL,
    assessment_tool_id INT UNSIGNED DEFAULT NULL,
    competency_id INT UNSIGNED DEFAULT NULL,
    sub_strand_rubric_id INT UNSIGNED DEFAULT NULL,
    performance_level_id INT UNSIGNED DEFAULT NULL,
    attainment_status ENUM('not_assessed','achieved','partially_achieved','not_achieved','not_applicable') NOT NULL DEFAULT 'not_assessed',
    score DECIMAL(8,2) DEFAULT NULL,
    evidence_note VARCHAR(1000) DEFAULT NULL,
    teacher_note VARCHAR(1000) DEFAULT NULL,
    assessed_by INT UNSIGNED DEFAULT NULL,
    assessed_at DATETIME DEFAULT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_lple_plan_learner_outcome (lesson_plan_id, student_academic_enrollment_id, learning_outcome_id),
    KEY idx_lple_enrollment (student_academic_enrollment_id),
    KEY idx_lple_outcome (learning_outcome_id),
    CONSTRAINT fk_lple_plan FOREIGN KEY (lesson_plan_id) REFERENCES lesson_plans(id) ON DELETE CASCADE,
    CONSTRAINT fk_lple_enrollment FOREIGN KEY (student_academic_enrollment_id) REFERENCES student_academic_enrollments(id) ON DELETE CASCADE,
    CONSTRAINT fk_lple_outcome FOREIGN KEY (learning_outcome_id) REFERENCES learning_outcomes(id) ON DELETE RESTRICT,
    CONSTRAINT fk_lple_tool FOREIGN KEY (assessment_tool_id) REFERENCES assessment_tools(id) ON DELETE SET NULL,
    CONSTRAINT fk_lple_competency FOREIGN KEY (competency_id) REFERENCES core_competencies(id) ON DELETE SET NULL,
    CONSTRAINT fk_lple_rubric FOREIGN KEY (sub_strand_rubric_id) REFERENCES sub_strand_rubrics(id) ON DELETE SET NULL,
    CONSTRAINT fk_lple_performance FOREIGN KEY (performance_level_id) REFERENCES performance_levels_cbc(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
