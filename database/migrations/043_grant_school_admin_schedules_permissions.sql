-- Grant the School Administrator role permission to manage school calendar
-- events (create/update/delete). The frontend RBAC contract maps:
--   POST   /schedules/events-create  -> schedules_create
--   PUT    /schedules/events-update  -> schedules_update
--   DELETE /schedules/events-delete  -> schedules_update
--
-- School Administrators operate the school calendar/events pages, so the
-- role previously limited to schedules_view now also receives create/update.
-- Idempotent: only inserts rows that are not already granted.

INSERT INTO role_permissions (role_id, permission_id, created_at)
SELECT r.id, p.id, NOW()
FROM roles r
JOIN permissions p ON p.code IN ('schedules_create', 'schedules_update')
WHERE r.name = 'School Administrator'
  AND NOT EXISTS (
    SELECT 1
    FROM role_permissions rp
    WHERE rp.role_id = r.id AND rp.permission_id = p.id
  );
