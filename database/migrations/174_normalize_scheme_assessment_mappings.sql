-- Scheme rows select the canonical assessment relationships that describe
-- what the teacher intends to measure. Delivery resources, coverage and
-- learner evidence remain lesson-plan concerns.

CREATE TABLE IF NOT EXISTS scheme_workbook_item_competencies (
    workbook_item_id INT UNSIGNED NOT NULL,
    competency_id INT UNSIGNED NOT NULL,
    sort_order SMALLINT UNSIGNED NOT NULL DEFAULT 1,
    PRIMARY KEY (workbook_item_id, competency_id),
    CONSTRAINT fk_swic_item FOREIGN KEY (workbook_item_id) REFERENCES scheme_workbook_items(id) ON DELETE CASCADE,
    CONSTRAINT fk_swic_competency FOREIGN KEY (competency_id) REFERENCES core_competencies(id) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS scheme_workbook_item_assessment_tools (
    workbook_item_id INT UNSIGNED NOT NULL,
    assessment_tool_id INT UNSIGNED NOT NULL,
    sort_order SMALLINT UNSIGNED NOT NULL DEFAULT 1,
    PRIMARY KEY (workbook_item_id, assessment_tool_id),
    CONSTRAINT fk_swia_item FOREIGN KEY (workbook_item_id) REFERENCES scheme_workbook_items(id) ON DELETE CASCADE,
    CONSTRAINT fk_swia_tool FOREIGN KEY (assessment_tool_id) REFERENCES assessment_tools(id) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS scheme_workbook_item_sub_strand_rubrics (
    workbook_item_id INT UNSIGNED NOT NULL,
    sub_strand_rubric_id INT UNSIGNED NOT NULL,
    sort_order SMALLINT UNSIGNED NOT NULL DEFAULT 1,
    PRIMARY KEY (workbook_item_id, sub_strand_rubric_id),
    CONSTRAINT fk_swirs_item FOREIGN KEY (workbook_item_id) REFERENCES scheme_workbook_items(id) ON DELETE CASCADE,
    CONSTRAINT fk_swirs_rubric FOREIGN KEY (sub_strand_rubric_id) REFERENCES sub_strand_rubrics(id) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS scheme_workbook_item_assessment_rubrics (
    workbook_item_id INT UNSIGNED NOT NULL,
    assessment_rubric_id INT UNSIGNED NOT NULL,
    sort_order SMALLINT UNSIGNED NOT NULL DEFAULT 1,
    PRIMARY KEY (workbook_item_id, assessment_rubric_id),
    CONSTRAINT fk_swiar_item FOREIGN KEY (workbook_item_id) REFERENCES scheme_workbook_items(id) ON DELETE CASCADE,
    CONSTRAINT fk_swiar_rubric FOREIGN KEY (assessment_rubric_id) REFERENCES assessment_rubrics(id) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
