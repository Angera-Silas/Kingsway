-- 026_wire_orphan_features.sql
-- Registers eight orphan page features (files existed in pages/ with full JS
-- controllers but no route and no sidebar item) into routes_registry,
-- sidebar_menu_items and role_sidebar_menus (System Administrator, role 2) so
-- they become reachable. The same entries are mirrored in the rendered sidebar
-- via config/role_sidebars.php (System Administrator section).
--
-- Applied to live DB: 2026-08-10

INSERT IGNORE INTO `routes_registry` (`id`, `name`, `url`, `domain`, `description`, `is_active`) VALUES
(200001, 'assessment_overview',  'home.php?route=assessment_overview',  'SYSTEM', 'Teacher assessment overview and management', 1),
(200002, 'manage_whatsapp',      'home.php?route=manage_whatsapp',      'SYSTEM', 'WhatsApp messaging management', 1),
(200003, 'salary_advances',      'home.php?route=salary_advances',      'SYSTEM', 'Staff salary advances', 1),
(200004, 'school_initialization','home.php?route=school_initialization','SYSTEM', 'School provisioning wizard', 1),
(200005, 'staff_schedule',       'home.php?route=staff_schedule',       'SYSTEM', 'Staff duty schedules', 1),
(200006, 'student_timeline',     'home.php?route=student_timeline',     'SYSTEM', 'Student lifecycle timeline', 1),
(200007, 'term_transition',      'home.php?route=term_transition',      'SYSTEM', 'Term transition management', 1),
(200008, 'year_rollover',        'home.php?route=year_rollover',        'SYSTEM', 'Academic year rollover', 1);

INSERT IGNORE INTO `sidebar_menu_items` (`name`, `label`, `url`, `route_id`, `menu_type`, `display_order`, `domain`, `is_active`) VALUES
('assessment_overview',   'Assessment Overview',  'assessment_overview',   200001, 'sidebar', 900, 'SYSTEM', 1),
('manage_whatsapp',       'WhatsApp Management',  'manage_whatsapp',       200002, 'sidebar', 901, 'SYSTEM', 1),
('salary_advances',       'Salary Advances',      'salary_advances',       200003, 'sidebar', 902, 'SYSTEM', 1),
('school_initialization', 'Initialize School',    'school_initialization', 200004, 'sidebar', 903, 'SYSTEM', 1),
('staff_schedule',        'Staff Schedule',       'staff_schedule',        200005, 'sidebar', 904, 'SYSTEM', 1),
('student_timeline',      'Student Timeline',     'student_timeline',      200006, 'sidebar', 905, 'SYSTEM', 1),
('term_transition',       'Term Transition',      'term_transition',       200007, 'sidebar', 906, 'SYSTEM', 1),
('year_rollover',         'Year Rollover',        'year_rollover',         200008, 'sidebar', 907, 'SYSTEM', 1);

INSERT IGNORE INTO `role_sidebar_menus` (`role_id`, `menu_item_id`, `is_default`, `custom_order`)
SELECT 2, id, 1, display_order FROM `sidebar_menu_items` WHERE `route_id` BETWEEN 200001 AND 200008;
