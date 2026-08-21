-- Preserve the exact receiving account on every incoming business record.

ALTER TABLE transport_payment_intents
    ADD COLUMN financial_account_id BIGINT UNSIGNED NULL AFTER student_id,
    ADD KEY idx_transport_intent_financial_account (financial_account_id),
    ADD CONSTRAINT fk_transport_intent_financial_account FOREIGN KEY (financial_account_id) REFERENCES school_financial_accounts (id);

ALTER TABLE transport_entitlement_payments
    ADD COLUMN financial_account_id BIGINT UNSIGNED NULL AFTER student_id,
    ADD KEY idx_transport_payment_financial_account (financial_account_id),
    ADD CONSTRAINT fk_transport_payment_financial_account FOREIGN KEY (financial_account_id) REFERENCES school_financial_accounts (id);

ALTER TABLE uniform_payment_intents
    ADD COLUMN financial_account_id BIGINT UNSIGNED NULL AFTER student_id,
    ADD KEY idx_uniform_intent_financial_account (financial_account_id),
    ADD CONSTRAINT fk_uniform_intent_financial_account FOREIGN KEY (financial_account_id) REFERENCES school_financial_accounts (id);

ALTER TABLE uniform_payment_records
    ADD COLUMN financial_account_id BIGINT UNSIGNED NULL AFTER sale_id,
    ADD KEY idx_uniform_payment_financial_account (financial_account_id),
    ADD CONSTRAINT fk_uniform_payment_financial_account FOREIGN KEY (financial_account_id) REFERENCES school_financial_accounts (id);

ALTER TABLE payments
    ADD COLUMN financial_account_id BIGINT UNSIGNED NULL AFTER student_id,
    ADD COLUMN payment_purpose VARCHAR(40) NOT NULL DEFAULT 'fees' AFTER method,
    ADD KEY idx_payment_financial_account (financial_account_id),
    ADD CONSTRAINT fk_payment_financial_account FOREIGN KEY (financial_account_id) REFERENCES school_financial_accounts (id);
