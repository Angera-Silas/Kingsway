-- Headteacher web-management access is intentionally removed.
-- Web Management is an administrator/director workspace and must not be
-- granted merely because the Headteacher also has a teaching role.
DELETE rp
FROM role_permissions rp
JOIN roles r ON r.id = rp.role_id
JOIN permissions p ON p.id = rp.permission_id
WHERE r.id = 5
  AND p.code IN (
      'website_applications_view',
      'website_content_manage',
      'website_downloads_manage',
      'website_events_manage',
      'website_gallery_manage',
      'website_inquiries_view',
      'website_jobs_manage',
      'website_news_manage',
      'website_view'
  );
