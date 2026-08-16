-- 000_init.sql
-- Bootstraps the migration tooling checksum table consumed by
-- api/services/MigrationService.php. The DDL below must stay in lockstep with
-- MigrationService::ensureMigrationsTable() (id AUTO_INCREMENT PRIMARY KEY,
-- filename UNIQUE, checksum, applied_at, duration_ms) so that the service's
-- CREATE TABLE IF NOT EXISTS is a no-op once this migration has run.
-- See docs/database_audit/14_LEGACY_REFERENCE_AUDIT_AND_MIGRATION_PLAN.md §5.11.

CREATE TABLE IF NOT EXISTS `migrations` (
    `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
    `filename` varchar(255) NOT NULL,
    `checksum` varchar(64) NOT NULL,
    `applied_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `duration_ms` int(11) NOT NULL DEFAULT 0,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uq_migrations_filename` (`filename`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
