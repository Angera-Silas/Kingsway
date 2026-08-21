-- 020_create_disbursement_tables.sql
-- Adds disbursement_transactions used to match M-Pesa B2C and KCB transfer
-- callbacks to the payroll/supplier disbursement they belong to. The previous
-- implementation referenced dead tables (staff_payments / supplier_payments)
-- that do not exist on the live KingsWayAcademy schema.

CREATE TABLE IF NOT EXISTS `disbursement_transactions` (
    `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
    `disbursement_type` enum('salary','supplier','refund','other') NOT NULL DEFAULT 'salary',
    `payroll_id` int(10) unsigned DEFAULT NULL,
    `payslip_id` int(10) unsigned DEFAULT NULL,
    `recipient_id` int(10) unsigned DEFAULT NULL,
    `recipient_name` varchar(150) DEFAULT NULL,
    `amount` decimal(12,2) NOT NULL,
    `phone_number` varchar(20) DEFAULT NULL,
    `account_number` varchar(50) DEFAULT NULL,
    `bank_code` varchar(10) DEFAULT NULL,
    `bank_name` varchar(100) DEFAULT NULL,
    `channel` enum('mpesa_b2c','kcb_bank','cash') NOT NULL DEFAULT 'mpesa_b2c',
    `conversation_id` varchar(100) DEFAULT NULL,
    `originator_conversation_id` varchar(100) DEFAULT NULL,
    `request_id` varchar(100) DEFAULT NULL,
    `transaction_ref` varchar(100) DEFAULT NULL,
    `transaction_id` varchar(100) DEFAULT NULL,
    `status` enum('pending','completed','failed','timeout','cancelled') NOT NULL DEFAULT 'pending',
    `result_description` varchar(500) DEFAULT NULL,
    `callback_data` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`callback_data`)),
    `bank_charges` decimal(12,2) DEFAULT NULL,
    `retry_count` int(11) NOT NULL DEFAULT 0,
    `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
    `completed_at` datetime DEFAULT NULL,
    `failed_at` datetime DEFAULT NULL,
    PRIMARY KEY (`id`),
    KEY `idx_disbursement_status` (`status`),
    KEY `idx_disbursement_payroll` (`payroll_id`),
    KEY `idx_disbursement_payslip` (`payslip_id`),
    KEY `idx_disbursement_recipient` (`recipient_id`),
    KEY `idx_disbursement_conv` (`conversation_id`),
    KEY `idx_disbursement_originator` (`originator_conversation_id`),
    KEY `idx_disbursement_request` (`request_id`),
    KEY `idx_disbursement_txn_ref` (`transaction_ref`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='Disbursement tracking used to match M-Pesa B2C / KCB transfer callbacks';
