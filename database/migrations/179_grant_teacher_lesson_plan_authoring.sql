-- All teaching roles may author and maintain their own lesson plans.
-- Approval and administrative management remain leadership permissions.
-- This is intentionally idempotent for repeatable deployment.

INSERT IGNORE INTO role_permissions (role_id, permission_id)
SELECT r.id, p.id
FROM roles r
JOIN permissions p ON p.code IN (
    'lesson_plans_view',
    'lesson_plans_create',
    'lesson_plans_edit'
)
WHERE r.id IN (5, 6, 7, 8, 9, 63);

-- Headteacher and academic deputy are both teachers and academic reviewers.
-- Keep review/management authority limited to these leadership roles.
INSERT IGNORE INTO role_permissions (role_id, permission_id)
SELECT r.id, p.id
FROM roles r
JOIN permissions p ON p.code IN ('lesson_plans_approve', 'lesson_plans_manage')
WHERE r.id IN (5, 6);
