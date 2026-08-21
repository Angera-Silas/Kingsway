-- Normalized supplier payout and statutory remittance records.

CREATE TABLE IF NOT EXISTS supplier_bank_accounts (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    supplier_id INT UNSIGNED NOT NULL,
    bank_name VARCHAR(120) NOT NULL,
    bank_code VARCHAR(20) DEFAULT NULL,
    account_name VARCHAR(160) NOT NULL,
    account_number VARCHAR(50) NOT NULL,
    currency CHAR(3) NOT NULL DEFAULT 'KES',
    is_primary TINYINT(1) NOT NULL DEFAULT 0,
    verification_status ENUM('unverified','pending','verified','rejected') NOT NULL DEFAULT 'unverified',
    active TINYINT(1) NOT NULL DEFAULT 1,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_supplier_bank_account (supplier_id, account_number, bank_code),
    KEY idx_supplier_bank_active (supplier_id, active, is_primary),
    CONSTRAINT fk_supplier_bank_supplier FOREIGN KEY (supplier_id) REFERENCES suppliers (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS supplier_payment_requests (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    supplier_id INT UNSIGNED NOT NULL,
    purchase_order_id INT UNSIGNED DEFAULT NULL,
    expense_id INT UNSIGNED DEFAULT NULL,
    supplier_bank_account_id INT UNSIGNED NOT NULL,
    disbursement_id INT UNSIGNED DEFAULT NULL,
    amount DECIMAL(12,2) NOT NULL,
    currency CHAR(3) NOT NULL DEFAULT 'KES',
    payment_reference VARCHAR(100) NOT NULL,
    status ENUM('draft','approved','payment_pending','paid','failed','cancelled') NOT NULL DEFAULT 'draft',
    created_by INT UNSIGNED NOT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_supplier_payment_reference (payment_reference),
    KEY idx_supplier_payment_status (status, created_at),
    CONSTRAINT fk_supplier_payment_supplier FOREIGN KEY (supplier_id) REFERENCES suppliers (id),
    CONSTRAINT fk_supplier_payment_bank FOREIGN KEY (supplier_bank_account_id) REFERENCES supplier_bank_accounts (id),
    CONSTRAINT fk_supplier_payment_disbursement FOREIGN KEY (disbursement_id) REFERENCES disbursement_transactions (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS statutory_agency_accounts (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    agency ENUM('KRA','NHIF','NSSF','Housing Levy') NOT NULL,
    account_name VARCHAR(160) NOT NULL,
    account_number VARCHAR(50) NOT NULL,
    bank_name VARCHAR(120) DEFAULT NULL,
    bank_code VARCHAR(20) DEFAULT NULL,
    payment_reference_rule VARCHAR(255) DEFAULT NULL,
    active TINYINT(1) NOT NULL DEFAULT 1,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_statutory_agency_account (agency, account_number)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS statutory_remittance_attempts (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    remittance_id INT UNSIGNED NOT NULL,
    agency_account_id INT UNSIGNED NOT NULL,
    provider_id INT UNSIGNED DEFAULT NULL,
    idempotency_key VARCHAR(120) NOT NULL,
    amount DECIMAL(12,2) NOT NULL,
    channel ENUM('kcb_bank','manual','other') NOT NULL DEFAULT 'manual',
    provider_reference VARCHAR(150) DEFAULT NULL,
    status ENUM('created','pending','paid','failed','unknown') NOT NULL DEFAULT 'created',
    request_payload JSON DEFAULT NULL,
    response_payload JSON DEFAULT NULL,
    created_by INT UNSIGNED NOT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    completed_at DATETIME DEFAULT NULL,
    PRIMARY KEY (id),
    UNIQUE KEY uq_statutory_attempt_idempotency (idempotency_key),
    KEY idx_statutory_attempt_status (status, created_at),
    CONSTRAINT fk_statutory_attempt_remittance FOREIGN KEY (remittance_id) REFERENCES statutory_remittances (id),
    CONSTRAINT fk_statutory_attempt_agency_account FOREIGN KEY (agency_account_id) REFERENCES statutory_agency_accounts (id),
    CONSTRAINT fk_statutory_attempt_provider FOREIGN KEY (provider_id) REFERENCES payment_providers (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

ALTER TABLE disbursement_transactions
    ADD COLUMN expense_id INT UNSIGNED NULL AFTER payslip_id;

ALTER TABLE expenses
    MODIFY status ENUM('draft','pending_approval','approved','payment_pending','paid','rejected','cancelled') NOT NULL DEFAULT 'draft';
