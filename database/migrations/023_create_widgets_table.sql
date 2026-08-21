-- 023_create_widgets_table.sql
-- Adds the `widgets` table backing the System Administrator widget registry
-- UI (js/pages/widget_registry.js, endpoints /system/widgets). Dashboard
-- registrations reuse the existing `dashboards` / `role_dashboards` tables.
--
-- Applied to live DB: 2026-08-10

CREATE TABLE IF NOT EXISTS `widgets` (
    `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `widget_key` VARCHAR(100) NOT NULL COMMENT 'Unique widget key e.g. student_count',
    `name` VARCHAR(150) NOT NULL COMMENT 'Human-readable widget name',
    `type` ENUM('chart','stat','table','list','custom') NOT NULL DEFAULT 'chart',
    `permission` VARCHAR(100) DEFAULT NULL COMMENT 'Permission required to view this widget',
    `description` VARCHAR(500) DEFAULT NULL,
    `is_active` TINYINT(1) NOT NULL DEFAULT 1,
    `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY `uk_widget_key` (`widget_key`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
