-- Explicit presentation settings for payment details printed on fee structures.
-- Transaction permissions and incoming collection routes remain separate.
CREATE TABLE IF NOT EXISTS school_financial_account_fee_display (
    financial_account_id BIGINT UNSIGNED NOT NULL,
    show_on_fee_structure TINYINT(1) NOT NULL DEFAULT 0,
    display_order SMALLINT UNSIGNED NOT NULL DEFAULT 0,
    display_title VARCHAR(160) DEFAULT NULL,
    reference_label VARCHAR(100) DEFAULT NULL,
    reference_value VARCHAR(160) DEFAULT NULL,
    instructions VARCHAR(500) DEFAULT NULL,
    updated_by INT UNSIGNED DEFAULT NULL,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (financial_account_id),
    CONSTRAINT fk_fee_display_financial_account
        FOREIGN KEY (financial_account_id) REFERENCES school_financial_accounts(id),
    CONSTRAINT fk_fee_display_updated_by
        FOREIGN KEY (updated_by) REFERENCES users(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
