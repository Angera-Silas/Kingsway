CREATE TABLE IF NOT EXISTS financial_statement_imports (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    import_reference CHAR(32) NOT NULL,
    provider_code VARCHAR(40) NOT NULL,
    financial_account_id BIGINT UNSIGNED NOT NULL,
    imported_by INT UNSIGNED NOT NULL,
    row_count INT UNSIGNED NOT NULL DEFAULT 0,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id), UNIQUE KEY uq_statement_import_reference (import_reference),
    KEY idx_statement_import_account (financial_account_id),
    CONSTRAINT fk_statement_import_account FOREIGN KEY (financial_account_id) REFERENCES school_financial_accounts(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS financial_statement_lines (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    import_id BIGINT UNSIGNED NOT NULL,
    provider_transaction_id VARCHAR(150) DEFAULT NULL,
    transaction_date DATETIME DEFAULT NULL,
    value_date DATE DEFAULT NULL,
    amount DECIMAL(14,2) NOT NULL,
    currency CHAR(3) NOT NULL DEFAULT 'KES',
    payer_name VARCHAR(180) DEFAULT NULL,
    payer_phone VARCHAR(30) DEFAULT NULL,
    raw_reference VARCHAR(150) DEFAULT NULL,
    normalized_reference VARCHAR(150) DEFAULT NULL,
    raw_payload JSON NOT NULL,
    matching_status ENUM('unmatched','matched','duplicate','conflict','partial','overpayment','underpayment','reversed','needs_review','rejected') NOT NULL DEFAULT 'unmatched',
    matched_reference VARCHAR(150) DEFAULT NULL,
    resolved_by INT UNSIGNED DEFAULT NULL,
    resolved_at DATETIME DEFAULT NULL,
    resolution_reason VARCHAR(500) DEFAULT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id), UNIQUE KEY uq_statement_line_provider_tx (import_id,provider_transaction_id),
    KEY idx_statement_line_status (matching_status,created_at), KEY idx_statement_line_reference (normalized_reference),
    CONSTRAINT fk_statement_line_import FOREIGN KEY (import_id) REFERENCES financial_statement_imports(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
