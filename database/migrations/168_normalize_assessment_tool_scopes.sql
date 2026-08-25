-- An assessment tool can apply to more than one learning area (for example
-- Kiswahili / Indigenous Language). The old single learning_area_id column
-- cannot represent that relationship.
CREATE TABLE IF NOT EXISTS assessment_tool_learning_areas (
    assessment_tool_id INT UNSIGNED NOT NULL,
    learning_area_id INT UNSIGNED NOT NULL,
    grade_level VARCHAR(20) DEFAULT NULL,
    is_primary TINYINT(1) NOT NULL DEFAULT 0,
    source VARCHAR(100) NOT NULL DEFAULT 'verified_tool_code',
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (assessment_tool_id, learning_area_id, grade_level),
    KEY idx_atla_learning_area (learning_area_id),
    CONSTRAINT fk_atla_tool FOREIGN KEY (assessment_tool_id) REFERENCES assessment_tools(id) ON DELETE CASCADE,
    CONSTRAINT fk_atla_learning_area FOREIGN KEY (learning_area_id) REFERENCES learning_areas(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
