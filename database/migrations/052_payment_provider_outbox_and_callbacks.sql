-- Payment provider boundary and immutable callback ledger.
--
-- These tables deliberately keep provider facts separate from fee allocations,
-- ledger rows, and disbursement rows. A provider can therefore retry a request
-- without creating a second business transaction. Secrets are not stored here.

CREATE TABLE IF NOT EXISTS payment_providers (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    code VARCHAR(40) NOT NULL,
    display_name VARCHAR(120) NOT NULL,
    environment ENUM('sandbox','production') NOT NULL,
    active TINYINT(1) NOT NULL DEFAULT 1,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_payment_provider_code_environment (code, environment)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS payment_provider_accounts (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    provider_id INT UNSIGNED NOT NULL,
    account_role ENUM('collection','debit','settlement') NOT NULL,
    account_identifier VARCHAR(120) NOT NULL,
    currency CHAR(3) NOT NULL DEFAULT 'KES',
    active TINYINT(1) NOT NULL DEFAULT 1,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_provider_account_role (provider_id, account_role, account_identifier),
    CONSTRAINT fk_provider_accounts_provider
        FOREIGN KEY (provider_id) REFERENCES payment_providers (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS payment_provider_attempts (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    provider_id INT UNSIGNED NOT NULL,
    payment_id INT UNSIGNED DEFAULT NULL,
    disbursement_id INT UNSIGNED DEFAULT NULL,
    operation ENUM('fee_collection','stk_push','c2b_collection','funds_transfer','status_query') NOT NULL,
    idempotency_key VARCHAR(150) NOT NULL,
    provider_request_id VARCHAR(150) DEFAULT NULL,
    provider_transaction_id VARCHAR(150) DEFAULT NULL,
    request_payload JSON NOT NULL,
    response_payload JSON DEFAULT NULL,
    status ENUM('created','submitted','accepted','pending','succeeded','failed','unknown') NOT NULL DEFAULT 'created',
    attempt_number SMALLINT UNSIGNED NOT NULL DEFAULT 1,
    error_code VARCHAR(80) DEFAULT NULL,
    error_message VARCHAR(500) DEFAULT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    submitted_at DATETIME DEFAULT NULL,
    completed_at DATETIME DEFAULT NULL,
    PRIMARY KEY (id),
    UNIQUE KEY uq_provider_attempt_idempotency (provider_id, idempotency_key),
    UNIQUE KEY uq_provider_attempt_transaction (provider_id, provider_transaction_id),
    KEY idx_provider_attempt_payment (payment_id),
    KEY idx_provider_attempt_disbursement (disbursement_id),
    KEY idx_provider_attempt_status (status, created_at),
    CONSTRAINT fk_provider_attempt_provider
        FOREIGN KEY (provider_id) REFERENCES payment_providers (id),
    CONSTRAINT fk_provider_attempt_payment
        FOREIGN KEY (payment_id) REFERENCES payments (id),
    CONSTRAINT fk_provider_attempt_disbursement
        FOREIGN KEY (disbursement_id) REFERENCES disbursement_transactions (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS payment_provider_callbacks (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    provider_id INT UNSIGNED NOT NULL,
    callback_type VARCHAR(60) NOT NULL,
    provider_event_id VARCHAR(150) DEFAULT NULL,
    idempotency_hash CHAR(64) NOT NULL,
    raw_payload JSON NOT NULL,
    request_headers JSON DEFAULT NULL,
    signature_valid TINYINT(1) NOT NULL DEFAULT 0,
    processing_status ENUM('received','processed','duplicate','rejected','failed') NOT NULL DEFAULT 'received',
    processing_error VARCHAR(500) DEFAULT NULL,
    received_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    processed_at DATETIME DEFAULT NULL,
    PRIMARY KEY (id),
    UNIQUE KEY uq_provider_callback_hash (provider_id, idempotency_hash),
    UNIQUE KEY uq_provider_callback_event (provider_id, callback_type, provider_event_id),
    KEY idx_provider_callback_status (processing_status, received_at),
    CONSTRAINT fk_provider_callback_provider
        FOREIGN KEY (provider_id) REFERENCES payment_providers (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO payment_providers (code, display_name, environment)
VALUES
    ('mpesa_daraja', 'Safaricom M-Pesa Daraja', 'sandbox'),
    ('kcb_buni', 'KCB Buni', 'sandbox')
ON DUPLICATE KEY UPDATE display_name = VALUES(display_name);
