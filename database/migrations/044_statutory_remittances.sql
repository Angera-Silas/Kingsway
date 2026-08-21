-- Migration 044: Statutory Remittances
-- Tracks PAYE (KRA), NHIF, NSSF, and Housing Levy remittances to government agencies.
-- Each row represents one monthly remittance per agency.

DROP TABLE IF EXISTS `statutory_remittance_items`;
DROP TABLE IF EXISTS `statutory_remittances`;

CREATE TABLE IF NOT EXISTS `statutory_remittances` (
  `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT,
  `agency` enum('KRA','NHIF','NSSF','Housing Levy') NOT NULL COMMENT 'Government agency',
  `period_month` int(11) NOT NULL COMMENT '1-12',
  `period_year` int(11) NOT NULL,
  `total_deducted` decimal(12,2) NOT NULL DEFAULT 0.00 COMMENT 'Sum of deductions from payslips for this period',
  `amount_remitted` decimal(12,2) NOT NULL DEFAULT 0.00 COMMENT 'Amount actually paid to agency',
  `remittance_date` date DEFAULT NULL COMMENT 'Date payment was made',
  `due_date` date DEFAULT NULL COMMENT 'Filing/payment deadline',
  `filing_reference` varchar(100) DEFAULT NULL COMMENT 'Agency payment receipt / acknowledgement number',
  `status` enum('pending','filed','paid','overdue','partial') NOT NULL DEFAULT 'pending',
  `notes` text DEFAULT NULL,
  `filed_by` int(10) UNSIGNED DEFAULT NULL COMMENT 'User who recorded the remittance',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_agency_period` (`agency`, `period_month`, `period_year`),
  KEY `idx_status` (`status`),
  KEY `idx_due_date` (`due_date`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE IF NOT EXISTS `statutory_remittance_items` (
  `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT,
  `remittance_id` int(10) UNSIGNED NOT NULL,
  `payslip_id` int(10) UNSIGNED NOT NULL COMMENT 'Reference to payslips.id',
  `staff_id` int(10) UNSIGNED NOT NULL,
  `amount` decimal(12,2) NOT NULL COMMENT 'Individual staff deduction amount for this agency',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_remittance` (`remittance_id`),
  KEY `idx_payslip` (`payslip_id`),
  KEY `idx_staff` (`staff_id`),
  CONSTRAINT `fk_remittance_items_remittance` FOREIGN KEY (`remittance_id`) REFERENCES `statutory_remittances` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_remittance_items_payslip` FOREIGN KEY (`payslip_id`) REFERENCES `payslips` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
