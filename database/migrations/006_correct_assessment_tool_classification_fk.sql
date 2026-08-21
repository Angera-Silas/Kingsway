-- Correct the initial tool seed to use assessment_type_classifications.
-- The previous seed used assessment_types, which is a different table.
DELETE FROM assessment_tools
WHERE description LIKE 'Default assessment tool for Assignment%'
   OR description LIKE 'Default assessment tool for Homework%'
   OR description LIKE 'Default assessment tool for Quiz%';

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
