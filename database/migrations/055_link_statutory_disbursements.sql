ALTER TABLE disbursement_transactions
    ADD COLUMN statutory_remittance_attempt_id BIGINT UNSIGNED NULL AFTER expense_id,
    ADD KEY idx_disbursement_statutory_attempt (statutory_remittance_attempt_id);
