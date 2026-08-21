-- Outbound maker/checker and source-account controls.

CREATE TABLE IF NOT EXISTS disbursement_approval_events (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    disbursement_id INT UNSIGNED NOT NULL,
    action ENUM('submitted','approved','rejected','cancelled','released') NOT NULL,
    actor_user_id INT UNSIGNED NOT NULL,
    reason VARCHAR(500) DEFAULT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_disbursement_approval_disbursement (disbursement_id, created_at),
    CONSTRAINT fk_disbursement_approval_disbursement FOREIGN KEY (disbursement_id) REFERENCES disbursement_transactions (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

ALTER TABLE disbursement_transactions
    ADD COLUMN payment_purpose VARCHAR(40) NULL AFTER disbursement_type,
    ADD COLUMN currency CHAR(3) NOT NULL DEFAULT 'KES' AFTER amount,
    ADD COLUMN submitted_by INT UNSIGNED NULL AFTER status,
    ADD COLUMN approved_by INT UNSIGNED NULL AFTER submitted_by,
    ADD COLUMN approved_at DATETIME NULL AFTER approved_by,
    ADD COLUMN released_at DATETIME NULL AFTER approved_at,
    ADD COLUMN provider_status VARCHAR(80) NULL AFTER status,
    ADD KEY idx_disbursement_purpose_status (payment_purpose, status),
    ADD KEY idx_disbursement_approval (approved_by, approved_at);

ALTER TABLE parent_refund_requests
    ADD COLUMN source_financial_account_id BIGINT UNSIGNED NULL AFTER parent_payment_account_id,
    ADD KEY idx_refund_source_account (source_financial_account_id),
    ADD CONSTRAINT fk_refund_source_account FOREIGN KEY (source_financial_account_id) REFERENCES school_financial_accounts (id);

ALTER TABLE payroll_runs
    ADD COLUMN source_financial_account_id BIGINT UNSIGNED NULL AFTER financial_period_id,
    ADD KEY idx_payroll_source_account (source_financial_account_id),
    ADD CONSTRAINT fk_payroll_source_account FOREIGN KEY (source_financial_account_id) REFERENCES school_financial_accounts (id);

ALTER TABLE statutory_remittance_attempts
    ADD COLUMN source_financial_account_id BIGINT UNSIGNED NULL AFTER agency_account_id,
    ADD KEY idx_statutory_source_account (source_financial_account_id),
    ADD CONSTRAINT fk_statutory_source_account FOREIGN KEY (source_financial_account_id) REFERENCES school_financial_accounts (id);

CREATE OR REPLACE VIEW vw_disbursement_audit_timeline AS
SELECT d.id AS disbursement_id,d.payment_purpose,d.disbursement_type,d.amount,d.currency,d.channel,d.status,
       d.source_financial_account_id,d.idempotency_reference,d.request_id,d.transaction_ref,d.transaction_id,
       a.action,a.actor_user_id,a.reason,a.created_at AS action_at
FROM disbursement_transactions d
LEFT JOIN disbursement_approval_events a ON a.disbursement_id=d.id;
