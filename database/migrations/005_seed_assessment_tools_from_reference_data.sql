-- Seed valid assessment-tool records from existing reference data.
-- This does not create curriculum, outcomes, rubrics, or competency mappings.
-- Safe to rerun: tool_code is unique and duplicate rows are ignored.
INSERT IGNORE INTO assessment_tools (
    tool_name,
    tool_code,
    description,
    assessment_type_id,
    learning_area_id,
    grade_level,
    competencies_assessed,
    created_by,
    status
)
SELECT
    CONCAT(at.name, ' - ', la.name),
    CONCAT('AT-', at.code, '-', la.code),
    CONCAT('Default assessment tool for ', at.name, ' in ', la.name, '.'),
    at.id,
    la.id,
    la.levels,
    NULL,
    u.id,
    'active'
FROM assessment_type_classifications at
JOIN learning_areas la
    ON la.status = 'active'
JOIN (
    SELECT MIN(id) AS id
    FROM users
    WHERE status = 'active'
) u ON 1 = 1
WHERE at.status = 'active';
