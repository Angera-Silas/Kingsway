-- 4NF parent refund workflow. A fee credit is the source ledger object;
-- the refund request and destination account remain separate facts.
CREATE TABLE IF NOT EXISTS parent_payment_accounts (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    parent_id INT UNSIGNED NOT NULL,
    provider ENUM('mpesa','bank') NOT NULL,
    phone_number VARCHAR(20) DEFAULT NULL,
    bank_name VARCHAR(120) DEFAULT NULL,
    bank_code VARCHAR(20) DEFAULT NULL,
    account_name VARCHAR(160) NOT NULL,
    account_number VARCHAR(50) DEFAULT NULL,
    verification_status ENUM('unverified','pending','verified','rejected') NOT NULL DEFAULT 'pending',
    is_primary TINYINT(1) NOT NULL DEFAULT 0,
    active TINYINT(1) NOT NULL DEFAULT 1,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_parent_payment_account (parent_id, provider, phone_number, account_number),
    KEY idx_parent_payment_account_active (parent_id, active, verification_status),
    CONSTRAINT fk_parent_payment_account_parent FOREIGN KEY (parent_id) REFERENCES parents (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS parent_refund_requests (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    fee_credit_note_id INT UNSIGNED NOT NULL,
    parent_id INT UNSIGNED NOT NULL,
    parent_payment_account_id INT UNSIGNED NOT NULL,
    amount DECIMAL(12,2) NOT NULL,
    reason VARCHAR(255) NOT NULL,
    channel ENUM('mpesa_b2c','kcb_bank','manual') NOT NULL,
    status ENUM('pending_approval','approved','processing','paid','failed','rejected','cancelled') NOT NULL DEFAULT 'pending_approval',
    disbursement_id INT UNSIGNED DEFAULT NULL,
    provider_reference VARCHAR(150) DEFAULT NULL,
    approved_by INT UNSIGNED DEFAULT NULL,
    created_by INT UNSIGNED NOT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    completed_at DATETIME DEFAULT NULL,
    PRIMARY KEY (id),
    KEY idx_parent_refund_status (status, created_at),
    UNIQUE KEY uq_parent_refund_disbursement (disbursement_id),
    CONSTRAINT fk_parent_refund_credit FOREIGN KEY (fee_credit_note_id) REFERENCES fee_credit_notes (id),
    CONSTRAINT fk_parent_refund_parent FOREIGN KEY (parent_id) REFERENCES parents (id),
    CONSTRAINT fk_parent_refund_account FOREIGN KEY (parent_payment_account_id) REFERENCES parent_payment_accounts (id),
    CONSTRAINT fk_parent_refund_disbursement FOREIGN KEY (disbursement_id) REFERENCES disbursement_transactions (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

ALTER TABLE disbursement_transactions
    ADD COLUMN refund_request_id BIGINT UNSIGNED DEFAULT NULL AFTER payslip_id,
    ADD KEY idx_disbursement_refund (refund_request_id);
