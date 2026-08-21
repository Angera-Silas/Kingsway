-- 027_bank_transactions_reconciled_flag.sql
-- Adds reconciled/reconciled_at flags to bank_transactions so the finance
-- module can mark a manual entry (source_type='manual_entry') as reconciled
-- against the bank statement. The flags are additive only; no existing data is
-- changed.

ALTER TABLE `bank_transactions`
    ADD COLUMN `reconciled` TINYINT(1) NOT NULL DEFAULT 0 AFTER `status`,
    ADD COLUMN `reconciled_at` DATETIME NULL DEFAULT NULL AFTER `reconciled`;

CREATE INDEX `idx_bank_transactions_reconciled` ON `bank_transactions` (`reconciled`);
