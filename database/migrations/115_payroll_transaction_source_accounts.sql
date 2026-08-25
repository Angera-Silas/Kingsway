-- Allow each payroll transaction (payslip) to use its own school account.
ALTER TABLE payslips
    ADD COLUMN source_financial_account_id BIGINT UNSIGNED NULL AFTER payment_method,
    ADD KEY idx_payslip_source_financial_account (source_financial_account_id),
    ADD CONSTRAINT fk_payslip_source_financial_account FOREIGN KEY (source_financial_account_id) REFERENCES school_financial_accounts(id);
