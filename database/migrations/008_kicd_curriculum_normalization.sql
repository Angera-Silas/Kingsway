-- =============================================================================
-- Migration 008: KICD 2024 curriculum standardization
--
-- Rebuilds the CBC curriculum model so it mirrors the KICD 2024 design structure
-- 1:1 and the grade-specific seed files import without modification:
--
--   Academic Year
--     -> Grade / Class-Stream
--        -> Learning Area (2024 rationalized codes; RE carries CRE/IRE/HRE variant)
--           -> Strand      (per grade, source-tagged)
--              -> Sub-Strand (per grade, source-tagged)
--                 -> Learning Outcomes            learning_outcomes (+ strand_id)
--                 -> Suggested Experiences        sub_strand_suggested_experiences
--                 -> Key Inquiry Questions        sub_strand_key_inquiry_questions
--                 -> Values                       sub_strand_values
--                 -> P&C Issues                   sub_strand_pci_issues   (joins pcis)
--                 -> Assessment Rubric            sub_strand_rubrics
--                 -> Core Competencies            sub_strand_competencies (joins core_competencies)
--           -> Teacher Coverage / Planning        class_curriculum_coverages (checkbox tree)
--              -> Scheme of Work                  schemes_of_work (+ strand/sub-strand/coverage FKs)
--              -> Lesson Plan                     lesson_plans (+ strand/sub-strand/coverage FKs; units deprecated)
--              -> Assignment                      assignments (+ learning_area/strand/sub-strand/coverage FKs)
--              -> Assessment                      assessments (+ strand/sub-strand/coverage FKs)
--              -> Learner Portfolio               portfolios (unchanged)
--
-- Destructive: purges all pre-2024 band-based curriculum rows (strands,
-- sub-strands, learning outcomes, competency mappings, curriculum units/topics,
-- and every learning_areas row). Run scripts/import_cbc_grade_seeds.php after
-- applying to load the KICD 2024 grade-specific dataset.
-- =============================================================================

-- ---------------------------------------------------------------------------
-- 0) Detach operational rows so the purge cannot cascade-delete them
--    (assessment_tools + exam_schedules are NOT NULL/CASCADE today).
-- ---------------------------------------------------------------------------
ALTER TABLE assessment_tools DROP FOREIGN KEY fk_at_learning_area;
ALTER TABLE assessment_tools MODIFY learning_area_id int(10) unsigned NULL COMMENT 'Detached by migration 008; re-link to 2024 areas';
UPDATE assessment_tools SET learning_area_id = NULL WHERE learning_area_id IS NOT NULL;

ALTER TABLE exam_schedules DROP FOREIGN KEY exam_schedules_ibfk_2;
ALTER TABLE exam_schedules MODIFY subject_id int(10) unsigned NULL;
UPDATE exam_schedules SET subject_id = NULL WHERE subject_id IS NOT NULL;

-- lesson_plans moves off curriculum_units; unit_id becomes a nullable legacy link.
ALTER TABLE lesson_plans DROP FOREIGN KEY lesson_plans_ibfk_4;
ALTER TABLE lesson_plans MODIFY unit_id int(10) unsigned NULL COMMENT 'Deprecated by migration 008';

-- ---------------------------------------------------------------------------
-- 1) Purge legacy pre-2024 curriculum rows
-- ---------------------------------------------------------------------------
DELETE FROM unit_topics;
DELETE FROM learning_outcomes;
DELETE FROM sub_strands;
DELETE FROM strand_competency;
DELETE FROM strands;
DELETE FROM curriculum_units;
DELETE FROM learning_areas;

-- ---------------------------------------------------------------------------
-- 2) learning_areas: per-band identity + KICD source provenance
-- ---------------------------------------------------------------------------
ALTER TABLE learning_areas
    ADD COLUMN level_band varchar(20) NOT NULL DEFAULT 'lower_primary'
        COMMENT 'playgroup|pp|lower_primary|upper_primary|junior_secondary' AFTER code,
    ADD COLUMN source_documents JSON NULL COMMENT 'KICD design PDF(s) this area was extracted from' AFTER description,
    DROP INDEX uk_code,
    ADD UNIQUE KEY uk_code_band (code, level_band);

-- ---------------------------------------------------------------------------
-- 3) strands: per-grade + provenance
-- ---------------------------------------------------------------------------
ALTER TABLE strands
    ADD COLUMN grade_level varchar(20) NOT NULL DEFAULT '' COMMENT 'e.g. PP1, Grade 5' AFTER learning_area_id,
    ADD COLUMN variant varchar(20) NULL COMMENT 'RE variant: CRE|IRE|HRE' AFTER name,
    ADD COLUMN source_subject varchar(100) NULL COMMENT '2024 source subject (e.g. Home Science)' AFTER variant,
    ADD COLUMN source_document varchar(255) NULL COMMENT 'KICD design PDF filename' AFTER source_subject,
    ADD COLUMN source_page varchar(20) NULL AFTER source_document,
    ADD COLUMN is_optional tinyint(1) NOT NULL DEFAULT 0 AFTER source_page,
    DROP INDEX uq_strand,
    ADD UNIQUE KEY uq_strand_grade (learning_area_id, grade_level, name, variant),
    ADD KEY idx_strand_grade (grade_level);

-- ---------------------------------------------------------------------------
-- 4) sub_strands: per-grade + provenance
-- ---------------------------------------------------------------------------
ALTER TABLE sub_strands
    ADD COLUMN grade_level varchar(20) NOT NULL DEFAULT '' COMMENT 'e.g. PP1, Grade 5' AFTER strand_id,
    ADD COLUMN variant varchar(20) NULL COMMENT 'RE variant: CRE|IRE|HRE' AFTER name,
    ADD COLUMN source_subject varchar(100) NULL AFTER variant,
    DROP INDEX uq_sub_strand,
    ADD UNIQUE KEY uq_sub_strand_grade (strand_id, grade_level, name, variant),
    ADD KEY idx_sub_strand_grade (grade_level);

-- ---------------------------------------------------------------------------
-- 5) learning_outcomes: add strand_id for direct joins
-- ---------------------------------------------------------------------------
ALTER TABLE learning_outcomes
    ADD COLUMN strand_id int(10) unsigned NULL AFTER learning_area_id,
    ADD KEY idx_lo_strand (strand_id);

-- ---------------------------------------------------------------------------
-- 6) Normalized sub-strand KICD dimensions
-- ---------------------------------------------------------------------------
CREATE TABLE sub_strand_suggested_experiences (
    id int(10) unsigned NOT NULL AUTO_INCREMENT,
    sub_strand_id int(10) unsigned NOT NULL,
    experience text NOT NULL,
    sort_order smallint(5) unsigned NOT NULL DEFAULT 1,
    source_document varchar(255) NULL,
    source_page varchar(20) NULL,
    created_at timestamp NOT NULL DEFAULT current_timestamp(),
    PRIMARY KEY (id),
    KEY idx_sse_sub (sub_strand_id),
    CONSTRAINT fk_sse_sub FOREIGN KEY (sub_strand_id) REFERENCES sub_strands (id) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci COMMENT='KICD suggested learning experiences per sub-strand';

CREATE TABLE sub_strand_key_inquiry_questions (
    id int(10) unsigned NOT NULL AUTO_INCREMENT,
    sub_strand_id int(10) unsigned NOT NULL,
    question text NOT NULL,
    sort_order smallint(5) unsigned NOT NULL DEFAULT 1,
    source_document varchar(255) NULL,
    source_page varchar(20) NULL,
    created_at timestamp NOT NULL DEFAULT current_timestamp(),
    PRIMARY KEY (id),
    KEY idx_ssq_sub (sub_strand_id),
    CONSTRAINT fk_ssq_sub FOREIGN KEY (sub_strand_id) REFERENCES sub_strands (id) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci COMMENT='KICD key inquiry questions per sub-strand';

CREATE TABLE sub_strand_values (
    id int(10) unsigned NOT NULL AUTO_INCREMENT,
    sub_strand_id int(10) unsigned NOT NULL,
    value_name varchar(150) NOT NULL,
    description text NULL,
    sort_order smallint(5) unsigned NOT NULL DEFAULT 1,
    source_document varchar(255) NULL,
    source_page varchar(20) NULL,
    created_at timestamp NOT NULL DEFAULT current_timestamp(),
    PRIMARY KEY (id),
    KEY idx_ssv_sub (sub_strand_id),
    CONSTRAINT fk_ssv_sub FOREIGN KEY (sub_strand_id) REFERENCES sub_strands (id) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci COMMENT='KICD values promoted per sub-strand';

CREATE TABLE sub_strand_pci_issues (
    id int(10) unsigned NOT NULL AUTO_INCREMENT,
    sub_strand_id int(10) unsigned NOT NULL,
    pci_id int(10) unsigned NOT NULL,
    note text NULL COMMENT 'Sub-strand specific note for this PCI',
    sort_order smallint(5) unsigned NOT NULL DEFAULT 1,
    created_at timestamp NOT NULL DEFAULT current_timestamp(),
    PRIMARY KEY (id),
    UNIQUE KEY uq_ss_pci (sub_strand_id, pci_id),
    KEY idx_ss_pci_pci (pci_id),
    CONSTRAINT fk_ss_pci_sub FOREIGN KEY (sub_strand_id) REFERENCES sub_strands (id) ON DELETE CASCADE ON UPDATE CASCADE,
    CONSTRAINT fk_ss_pci_pci FOREIGN KEY (pci_id) REFERENCES pcis (id) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci COMMENT='KICD pertinent & contemporary issues per sub-strand (joins pcis registry)';

CREATE TABLE sub_strand_rubrics (
    id int(10) unsigned NOT NULL AUTO_INCREMENT,
    sub_strand_id int(10) unsigned NOT NULL,
    level_number tinyint(3) unsigned NOT NULL COMMENT 'KICD expectation level 1..4',
    level_label varchar(50) NULL COMMENT 'Below/Approaching/Meeting/Exceeding expectation',
    descriptor text NOT NULL,
    sort_order smallint(5) unsigned NOT NULL DEFAULT 1,
    source_document varchar(255) NULL,
    source_page varchar(20) NULL,
    created_at timestamp NOT NULL DEFAULT current_timestamp(),
    PRIMARY KEY (id),
    KEY idx_ss_rubric_sub (sub_strand_id),
    CONSTRAINT fk_ss_rubric_sub FOREIGN KEY (sub_strand_id) REFERENCES sub_strands (id) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci COMMENT='KICD curriculum assessment rubric per sub-strand';

CREATE TABLE sub_strand_competencies (
    id int(10) unsigned NOT NULL AUTO_INCREMENT,
    sub_strand_id int(10) unsigned NOT NULL,
    competency_id int(10) unsigned NOT NULL,
    weight decimal(5,2) NOT NULL DEFAULT 1.00,
    source_document varchar(255) NULL,
    source_page varchar(20) NULL,
    created_at timestamp NOT NULL DEFAULT current_timestamp(),
    PRIMARY KEY (id),
    UNIQUE KEY uq_ss_comp (sub_strand_id, competency_id),
    KEY idx_ss_comp_comp (competency_id),
    CONSTRAINT fk_ss_comp_sub FOREIGN KEY (sub_strand_id) REFERENCES sub_strands (id) ON DELETE CASCADE ON UPDATE CASCADE,
    CONSTRAINT fk_ss_comp_comp FOREIGN KEY (competency_id) REFERENCES core_competencies (id) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci COMMENT='KICD core competencies per sub-strand';

-- ---------------------------------------------------------------------------
-- 7) Teacher Coverage / Planning (the checkbox tree)
-- ---------------------------------------------------------------------------
CREATE TABLE class_curriculum_coverages (
    id int(10) unsigned NOT NULL AUTO_INCREMENT,
    class_id int(10) unsigned NOT NULL,
    stream_id int(10) unsigned NULL,
    class_stream_id int(10) unsigned NULL,
    learning_area_id int(10) unsigned NOT NULL,
    strand_id int(10) unsigned NOT NULL,
    sub_strand_id int(10) unsigned NOT NULL,
    teacher_id int(10) unsigned NOT NULL COMMENT 'staff.id - the assigned/subject teacher',
    academic_year_id int(10) unsigned NOT NULL,
    term_id int(10) unsigned NULL,
    status enum('planned','in_progress','covered','skipped') NOT NULL DEFAULT 'planned',
    planned_weeks tinyint(3) unsigned NULL,
    covered_at datetime NULL,
    notes text NULL,
    created_at timestamp NOT NULL DEFAULT current_timestamp(),
    updated_at timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
    PRIMARY KEY (id),
    UNIQUE KEY uq_coverage (class_id, stream_id, class_stream_id, sub_strand_id, academic_year_id, term_id),
    KEY idx_cov_teacher_term (teacher_id, academic_year_id, term_id),
    KEY idx_cov_status (status),
    CONSTRAINT fk_cc_area FOREIGN KEY (learning_area_id) REFERENCES learning_areas (id) ON DELETE CASCADE ON UPDATE CASCADE,
    CONSTRAINT fk_cc_strand FOREIGN KEY (strand_id) REFERENCES strands (id) ON DELETE CASCADE ON UPDATE CASCADE,
    CONSTRAINT fk_cc_sub FOREIGN KEY (sub_strand_id) REFERENCES sub_strands (id) ON DELETE CASCADE ON UPDATE CASCADE,
    CONSTRAINT fk_cc_class FOREIGN KEY (class_id) REFERENCES classes (id) ON DELETE CASCADE ON UPDATE CASCADE,
    CONSTRAINT fk_cc_stream FOREIGN KEY (stream_id) REFERENCES class_streams (id) ON DELETE CASCADE ON UPDATE CASCADE,
    CONSTRAINT fk_cc_class_stream FOREIGN KEY (class_stream_id) REFERENCES class_streams (id) ON DELETE CASCADE ON UPDATE CASCADE,
    CONSTRAINT fk_cc_teacher FOREIGN KEY (teacher_id) REFERENCES staff (id) ON DELETE CASCADE ON UPDATE CASCADE,
    CONSTRAINT fk_cc_year FOREIGN KEY (academic_year_id) REFERENCES academic_years (id) ON DELETE CASCADE ON UPDATE CASCADE,
    CONSTRAINT fk_cc_term FOREIGN KEY (term_id) REFERENCES academic_terms (id) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci COMMENT='Teacher selection of strands/sub-strands to cover for a class + term';

-- ---------------------------------------------------------------------------
-- 8) schemes_of_work: reference the curriculum instead of free text
-- ---------------------------------------------------------------------------
ALTER TABLE schemes_of_work
    ADD COLUMN strand_id int(10) unsigned NULL AFTER learning_area_id,
    ADD COLUMN sub_strand_id int(10) unsigned NULL AFTER strand_id,
    ADD COLUMN coverage_id int(10) unsigned NULL AFTER sub_strand_id,
    ADD KEY idx_sow_strand (strand_id),
    ADD KEY idx_sow_sub_strand (sub_strand_id),
    ADD KEY idx_sow_coverage (coverage_id);

-- ---------------------------------------------------------------------------
-- 9) lesson_plans: route to Strand -> Sub-Strand (curriculum_units deprecated)
-- ---------------------------------------------------------------------------
ALTER TABLE lesson_plans
    ADD COLUMN strand_id int(10) unsigned NULL AFTER unit_id,
    ADD COLUMN sub_strand_id int(10) unsigned NULL AFTER strand_id,
    ADD COLUMN coverage_id int(10) unsigned NULL AFTER sub_strand_id,
    ADD KEY idx_lp_strand (strand_id),
    ADD KEY idx_lp_sub_strand (sub_strand_id),
    ADD KEY idx_lp_coverage (coverage_id);

-- ---------------------------------------------------------------------------
-- 10) assignments: link to the learning area + curriculum
-- ---------------------------------------------------------------------------
ALTER TABLE assignments
    ADD COLUMN learning_area_id int(10) unsigned NULL AFTER subject_id,
    ADD COLUMN strand_id int(10) unsigned NULL AFTER learning_area_id,
    ADD COLUMN sub_strand_id int(10) unsigned NULL AFTER strand_id,
    ADD COLUMN coverage_id int(10) unsigned NULL AFTER sub_strand_id,
    ADD KEY idx_asgn_la (learning_area_id),
    ADD KEY idx_asgn_strand (strand_id),
    ADD KEY idx_asgn_sub_strand (sub_strand_id),
    ADD KEY idx_asgn_coverage (coverage_id);

-- ---------------------------------------------------------------------------
-- 11) assessments: link to the curriculum
-- ---------------------------------------------------------------------------
ALTER TABLE assessments
    ADD COLUMN strand_id int(10) unsigned NULL AFTER learning_outcome_id,
    ADD COLUMN sub_strand_id int(10) unsigned NULL AFTER strand_id,
    ADD COLUMN coverage_id int(10) unsigned NULL AFTER sub_strand_id,
    ADD KEY idx_assess_strand (strand_id),
    ADD KEY idx_assess_sub_strand (sub_strand_id),
    ADD KEY idx_assess_coverage (coverage_id);

-- ---------------------------------------------------------------------------
-- 12) staff_class_assignments: canonical learning-area link (subject_id is legacy)
-- ---------------------------------------------------------------------------
ALTER TABLE staff_class_assignments
    ADD COLUMN learning_area_id int(10) unsigned NULL COMMENT '2024 learning area taught' AFTER subject_id,
    ADD KEY idx_sca_learning_area (learning_area_id);

-- ---------------------------------------------------------------------------
-- 13) classes: per-grade label (drives grade-scoped curriculum queries)
-- ---------------------------------------------------------------------------
ALTER TABLE classes
    ADD COLUMN grade_level varchar(20) NULL COMMENT 'e.g. PP1, Grade 5' AFTER level_id;

UPDATE classes
    SET grade_level = name
    WHERE name IN ('Playgroup','PP1','PP2','Grade 1','Grade 2','Grade 3','Grade 4','Grade 5','Grade 6','Grade 7','Grade 8','Grade 9');
