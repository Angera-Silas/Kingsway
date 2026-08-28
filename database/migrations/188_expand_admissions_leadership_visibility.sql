-- Give the Headteacher and both Deputy Headteachers school-wide, read-only
-- visibility across the admissions workflow. Existing stage processing and
-- approval grants remain authoritative and are not broadened here.

INSERT INTO role_permissions (role_id, permission_id)
SELECT r.id, p.id
FROM roles r
JOIN permissions p ON p.code IN (
    'admission_view',
    'admission_applications_view',
    'admission_applications_view_all',
    'admissions_academic_applications_view'
)
WHERE r.id IN (5, 6, 63)
ON DUPLICATE KEY UPDATE role_id = VALUES(role_id);

INSERT INTO role_routes (role_id, route_id, is_allowed)
SELECT r.id, rr.id, 1
FROM roles r
JOIN routes_registry rr ON rr.name = 'admissions_academic_applications'
WHERE r.id IN (5, 6, 63)
ON DUPLICATE KEY UPDATE is_allowed = 1;

INSERT INTO workflow_stage_permissions
    (workflow_stage_id, permission_id, role_id, can_view, can_process, can_approve, is_responsible, required_count)
SELECT ws.id, p.id, r.id, 1, 0, 0, 0, 1
FROM workflow_stages ws
JOIN workflow_definitions wd ON wd.id = ws.workflow_id AND wd.code = 'student_admission'
JOIN permissions p ON p.code = 'admission_applications_view_all'
JOIN roles r ON r.id IN (5, 6, 63)
WHERE ws.is_active = 1
ON DUPLICATE KEY UPDATE can_view = 1;
