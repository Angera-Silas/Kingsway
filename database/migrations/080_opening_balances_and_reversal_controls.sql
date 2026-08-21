-- Controlled migration of historical balances. Existing operational rows are
-- not reposted automatically; finance must approve an opening-balance batch.
CREATE TABLE IF NOT EXISTS accounting_opening_balances (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    balance_code VARCHAR(60) NOT NULL,
    as_of_date DATE NOT NULL,
    status ENUM('draft','submitted','approved','posted','rejected') NOT NULL DEFAULT 'draft',
    source_description VARCHAR(500) NOT NULL,
    source_hash CHAR(64) NOT NULL,
    created_by INT UNSIGNED NOT NULL,
    approved_by INT UNSIGNED NULL,
    approved_at DATETIME NULL,
    journal_batch_id BIGINT UNSIGNED NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id), UNIQUE KEY uq_opening_balance_code (balance_code),
    UNIQUE KEY uq_opening_balance_hash (source_hash),
    CONSTRAINT fk_opening_balance_journal FOREIGN KEY (journal_batch_id) REFERENCES accounting_journal_batches(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS accounting_opening_balance_lines (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    opening_balance_id BIGINT UNSIGNED NOT NULL,
    chart_account_id BIGINT UNSIGNED NOT NULL,
    debit_amount DECIMAL(14,2) NOT NULL DEFAULT 0.00,
    credit_amount DECIMAL(14,2) NOT NULL DEFAULT 0.00,
    description VARCHAR(255) NULL,
    PRIMARY KEY (id),
    UNIQUE KEY uq_opening_line_account (opening_balance_id, chart_account_id),
    CONSTRAINT chk_opening_line_one_side CHECK ((debit_amount > 0 AND credit_amount = 0) OR (credit_amount > 0 AND debit_amount = 0)),
    CONSTRAINT fk_opening_line_batch FOREIGN KEY (opening_balance_id) REFERENCES accounting_opening_balances(id),
    CONSTRAINT fk_opening_line_account FOREIGN KEY (chart_account_id) REFERENCES chart_of_accounts(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS accounting_reversal_reasons (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    original_journal_batch_id BIGINT UNSIGNED NOT NULL,
    reversal_journal_batch_id BIGINT UNSIGNED NULL,
    reason VARCHAR(500) NOT NULL,
    requested_by INT UNSIGNED NOT NULL,
    approved_by INT UNSIGNED NULL,
    status ENUM('requested','approved','posted','rejected') NOT NULL DEFAULT 'requested',
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id), UNIQUE KEY uq_reversal_request (original_journal_batch_id),
    CONSTRAINT fk_reversal_request_original FOREIGN KEY (original_journal_batch_id) REFERENCES accounting_journal_batches(id),
    CONSTRAINT fk_reversal_request_batch FOREIGN KEY (reversal_journal_batch_id) REFERENCES accounting_journal_batches(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
