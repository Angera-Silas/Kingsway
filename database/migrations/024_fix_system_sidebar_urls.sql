-- 024_fix_system_sidebar_urls.sql
-- Repoints three System Administrator sidebar items whose target pages do not
-- exist (pages/role_definitions.php, pages/schema_registry.php,
-- pages/data_purge_policies.php were never created) to the existing pages
-- that implement the same concepts.
--
-- Applied to live DB: 2026-08-10

UPDATE `sidebar_menu_items`
SET `url` = 'manage_roles', `route_id` = (SELECT id FROM `routes_registry` WHERE `name` = 'manage_roles')
WHERE `id` = 1013 AND `label` = 'Role Definitions' AND `url` = 'role_definitions';

UPDATE `sidebar_menu_items`
SET `url` = 'migrations', `route_id` = (SELECT id FROM `routes_registry` WHERE `name` = 'migrations')
WHERE `id` = 1039 AND `label` = 'Schema Registry' AND `url` = 'schema_registry';

UPDATE `sidebar_menu_items`
SET `url` = 'data_retention', `route_id` = (SELECT id FROM `routes_registry` WHERE `name` = 'data_retention')
WHERE `id` = 1043 AND `label` = 'Data Purge Policies' AND `url` = 'data_purge_policies';
