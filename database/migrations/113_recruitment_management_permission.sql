-- Recruitment mutations are separate from read access.
INSERT IGNORE INTO permissions (code, description, entity, action, module)
VALUES ('website_applications_manage', 'Manage staff recruitment applications and interviews', 'website_applications', 'manage', 'website');

INSERT IGNORE INTO role_permissions (role_id, permission_id)
SELECT r.id, p.id
FROM roles r
JOIN permissions p ON p.code = 'website_applications_manage'
WHERE LOWER(r.name) IN ('system administrator', 'school administrator', 'director');
