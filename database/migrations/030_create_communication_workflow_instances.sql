-- 030_create_communication_workflow_instances.sql
-- trg_auto_start_comm_workflow (AFTER INSERT on communications, status
-- 'sent'/'scheduled') inserts into communication_workflow_instances, but the
-- live legacy DB never created that table — so publishing any announcement /
-- sent communication failed with "Table ... doesn't exist" (HTTP 500).
-- This is the context table for the communication approval workflow. It is
-- child of communications (cascade delete) and records the initiating user.

CREATE TABLE IF NOT EXISTS `communication_workflow_instances` (
    `id` INT(10) UNSIGNED NOT NULL AUTO_INCREMENT,
    `communication_id` INT(10) UNSIGNED NOT NULL,
    `workflow_code` VARCHAR(50) NOT NULL DEFAULT 'communication_approval',
    `current_stage` VARCHAR(50) NOT NULL DEFAULT 'initiated',
    `status` ENUM('active', 'completed', 'cancelled', 'failed') NOT NULL DEFAULT 'active',
    `initiated_by` INT(10) UNSIGNED DEFAULT NULL,
    `initiated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `completed_at` TIMESTAMP NULL DEFAULT NULL,
    `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uq_comm_workflow` (`communication_id`, `workflow_code`),
    KEY `idx_comm_workflow_status` (`status`),
    KEY `idx_comm_workflow_initiator` (`initiated_by`),
    CONSTRAINT `fk_comm_workflow_communication` FOREIGN KEY (`communication_id`) REFERENCES `communications` (`id`) ON DELETE CASCADE,
    CONSTRAINT `fk_comm_workflow_initiator` FOREIGN KEY (`initiated_by`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
