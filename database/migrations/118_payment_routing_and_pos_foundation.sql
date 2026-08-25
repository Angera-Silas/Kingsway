-- Canonical collection/settlement routing and POS foundation.
-- A collection instrument (Paybill, Till, bank webhook or cash desk) may
-- settle into a different real school financial account.

ALTER TABLE school_financial_accounts
    ADD COLUMN settlement_financial_account_id BIGINT UNSIGNED NULL AFTER provider_id,
    ADD KEY idx_school_financial_account_settlement (settlement_financial_account_id),
    ADD CONSTRAINT fk_school_financial_account_settlement
        FOREIGN KEY (settlement_financial_account_id) REFERENCES school_financial_accounts(id);

ALTER TABLE payment_collection_routes
    ADD COLUMN settlement_financial_account_id BIGINT UNSIGNED NULL AFTER financial_account_id,
    ADD COLUMN collection_product VARCHAR(40) NOT NULL DEFAULT 'collection' AFTER normalized_account_identifier,
    ADD COLUMN reference_policy VARCHAR(60) NOT NULL DEFAULT 'payment_reference' AFTER collection_product,
    ADD COLUMN reference_label VARCHAR(100) NULL AFTER reference_policy,
    ADD KEY idx_collection_route_settlement (settlement_financial_account_id),
    ADD KEY idx_collection_route_product_policy (collection_product, reference_policy),
    ADD CONSTRAINT fk_collection_route_settlement
        FOREIGN KEY (settlement_financial_account_id) REFERENCES school_financial_accounts(id);

UPDATE payment_collection_routes r
LEFT JOIN school_financial_accounts a ON a.id = r.financial_account_id
SET r.settlement_financial_account_id = COALESCE(a.settlement_financial_account_id, r.financial_account_id),
    r.collection_product = CASE
        WHEN r.provider_id IN (SELECT id FROM payment_providers WHERE code = 'mpesa_daraja') THEN 'paybill'
        WHEN r.provider_id IN (SELECT id FROM payment_providers WHERE code = 'kcb_buni') THEN 'buni'
        ELSE 'bank_collection'
    END,
    r.reference_policy = CASE
        WHEN r.purpose = 'fees' THEN 'admission_no'
        WHEN r.purpose = 'transport' THEN 'transport_reference'
        WHEN r.purpose = 'uniforms' THEN 'uniform_reference'
        ELSE 'payment_reference'
    END
WHERE r.settlement_financial_account_id IS NULL;

CREATE TABLE IF NOT EXISTS payment_pos_terminals (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    terminal_name VARCHAR(160) NOT NULL,
    provider_name VARCHAR(160) NOT NULL,
    merchant_id VARCHAR(120) NULL,
    terminal_id VARCHAR(120) NULL,
    purpose VARCHAR(40) NOT NULL DEFAULT 'uniforms',
    settlement_financial_account_id BIGINT UNSIGNED NOT NULL,
    store_location VARCHAR(160) NULL,
    credential_profile VARCHAR(100) NULL,
    status ENUM('active','inactive','pending_verification') NOT NULL DEFAULT 'pending_verification',
    created_by INT UNSIGNED NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_pos_terminal_provider_id (provider_name, terminal_id),
    KEY idx_pos_terminal_settlement (settlement_financial_account_id),
    CONSTRAINT fk_pos_terminal_settlement FOREIGN KEY (settlement_financial_account_id) REFERENCES school_financial_accounts(id),
    CONSTRAINT fk_pos_terminal_created_by FOREIGN KEY (created_by) REFERENCES users(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS payment_pos_transactions (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    terminal_id BIGINT UNSIGNED NOT NULL,
    uniform_payment_intent_id BIGINT UNSIGNED NULL,
    purpose VARCHAR(40) NOT NULL DEFAULT 'uniforms',
    amount DECIMAL(14,2) NOT NULL,
    currency CHAR(3) NOT NULL DEFAULT 'KES',
    terminal_reference VARCHAR(180) NOT NULL,
    status ENUM('pending','approved','declined','reversed','reconciled','manual_review') NOT NULL DEFAULT 'pending',
    transaction_date DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    raw_payload JSON NULL,
    created_by INT UNSIGNED NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_pos_terminal_reference (terminal_id, terminal_reference),
    KEY idx_pos_transaction_uniform_intent (uniform_payment_intent_id),
    KEY idx_pos_transaction_status (status, transaction_date),
    CONSTRAINT fk_pos_transaction_terminal FOREIGN KEY (terminal_id) REFERENCES payment_pos_terminals(id),
    CONSTRAINT fk_pos_transaction_uniform_intent FOREIGN KEY (uniform_payment_intent_id) REFERENCES uniform_payment_intents(id),
    CONSTRAINT fk_pos_transaction_created_by FOREIGN KEY (created_by) REFERENCES users(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT IGNORE INTO financial_channels(code, name)
VALUES ('card_pos', 'Card POS / PDQ');
