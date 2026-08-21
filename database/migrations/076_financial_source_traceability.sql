-- Connect existing provider/operational records to the accounting foundation.

ALTER TABLE payment_collection_routes
    ADD COLUMN financial_account_id BIGINT UNSIGNED NULL AFTER provider_id,
    ADD COLUMN normalized_account_identifier VARCHAR(120) NULL AFTER account_identifier,
    ADD KEY idx_collection_route_financial_account (financial_account_id),
    ADD KEY idx_collection_route_normalized_account (provider_id, normalized_account_identifier, active),
    ADD CONSTRAINT fk_collection_route_financial_account FOREIGN KEY (financial_account_id) REFERENCES school_financial_accounts (id);

UPDATE payment_collection_routes
SET normalized_account_identifier = UPPER(REPLACE(REPLACE(REPLACE(TRIM(account_identifier), ' ', ''), '-', ''), '/', ''))
WHERE normalized_account_identifier IS NULL;

ALTER TABLE payment_provider_attempts
    ADD COLUMN source_financial_account_id BIGINT UNSIGNED NULL AFTER disbursement_id,
    ADD COLUMN normalized_reference VARCHAR(150) NULL AFTER provider_transaction_id,
    ADD KEY idx_provider_attempt_source_account (source_financial_account_id),
    ADD KEY idx_provider_attempt_normalized_reference (normalized_reference),
    ADD CONSTRAINT fk_provider_attempt_source_account FOREIGN KEY (source_financial_account_id) REFERENCES school_financial_accounts (id);

ALTER TABLE bank_transactions
    ADD COLUMN financial_account_id BIGINT UNSIGNED NULL AFTER account_number,
    ADD COLUMN normalized_reference VARCHAR(150) NULL AFTER bank_reference,
    ADD COLUMN matching_status ENUM('unmatched','matched','duplicate','conflict','partial','overpayment','underpayment','reversed','needs_review') NOT NULL DEFAULT 'unmatched' AFTER status,
    ADD KEY idx_bank_financial_account (financial_account_id),
    ADD KEY idx_bank_normalized_reference (normalized_reference),
    ADD CONSTRAINT fk_bank_transaction_financial_account FOREIGN KEY (financial_account_id) REFERENCES school_financial_accounts (id);

ALTER TABLE mpesa_transactions
    ADD COLUMN financial_account_id BIGINT UNSIGNED NULL AFTER third_party_trans_id,
    ADD COLUMN normalized_reference VARCHAR(150) NULL AFTER bill_ref_number,
    ADD COLUMN matching_status ENUM('unmatched','matched','duplicate','conflict','partial','overpayment','underpayment','reversed','needs_review') NOT NULL DEFAULT 'unmatched' AFTER status,
    ADD KEY idx_mpesa_financial_account (financial_account_id),
    ADD KEY idx_mpesa_normalized_reference (normalized_reference),
    ADD CONSTRAINT fk_mpesa_transaction_financial_account FOREIGN KEY (financial_account_id) REFERENCES school_financial_accounts (id);

INSERT INTO chart_of_accounts (account_code, account_name, account_type_id)
SELECT x.code, x.name, t.id
FROM (
    SELECT '110001' code, 'KCB Fees Bank Account' name, 'asset' type_code UNION ALL
    SELECT '110002', 'KCB Transport Bank Account', 'asset' UNION ALL
    SELECT '110003', 'Cooperative Uniforms Bank Account', 'asset' UNION ALL
    SELECT '110090', 'School Cash Account', 'asset' UNION ALL
    SELECT '110100', 'Payment Clearing Account', 'asset' UNION ALL
    SELECT '120001', 'School Fees Receivable', 'asset' UNION ALL
    SELECT '120002', 'Transport Receivable', 'asset' UNION ALL
    SELECT '120003', 'Uniform Receivable', 'asset' UNION ALL
    SELECT '210001', 'Parent Credit Liability', 'liability' UNION ALL
    SELECT '210010', 'Net Salary Payable', 'liability' UNION ALL
    SELECT '210011', 'PAYE Payable', 'liability' UNION ALL
    SELECT '210012', 'NSSF Payable', 'liability' UNION ALL
    SELECT '210013', 'SHIF Payable', 'liability' UNION ALL
    SELECT '210014', 'Housing Levy Payable', 'liability' UNION ALL
    SELECT '410001', 'Uniform Sales Revenue', 'revenue' UNION ALL
    SELECT '510001', 'Salary Expense', 'expense' UNION ALL
    SELECT '510002', 'Uniform Cost of Goods Sold', 'expense' UNION ALL
    SELECT '520001', 'General Operating Expense', 'expense'
) x
JOIN accounting_account_types t ON t.code = x.type_code
ON DUPLICATE KEY UPDATE account_name = VALUES(account_name), account_type_id = VALUES(account_type_id);

CREATE OR REPLACE VIEW vw_accounting_trial_balance AS
SELECT
    c.id AS chart_account_id,
    c.account_code,
    c.account_name,
    t.code AS account_type,
    COALESCE(SUM(CASE WHEN j.status = 'posted' THEN l.debit_amount ELSE 0 END), 0.00) AS total_debits,
    COALESCE(SUM(CASE WHEN j.status = 'posted' THEN l.credit_amount ELSE 0 END), 0.00) AS total_credits,
    COALESCE(SUM(CASE WHEN j.status = 'posted' THEN l.debit_amount - l.credit_amount ELSE 0 END), 0.00) AS balance
FROM chart_of_accounts c
JOIN accounting_account_types t ON t.id = c.account_type_id
LEFT JOIN accounting_journal_lines l ON l.chart_account_id = c.id
LEFT JOIN accounting_journal_batches j ON j.id = l.journal_batch_id
GROUP BY c.id, c.account_code, c.account_name, t.code;

CREATE OR REPLACE VIEW vw_financial_source_trace AS
SELECT
    l.source_type,
    l.source_id,
    l.source_reference,
    j.id AS journal_batch_id,
    j.batch_number,
    j.status AS journal_status,
    j.correlation_id,
    j.created_at,
    j.posted_at
FROM accounting_source_links l
JOIN accounting_journal_batches j ON j.id = l.journal_batch_id;
