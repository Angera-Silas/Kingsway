-- Preserve the exact Daraja shortcode used for an STK request.
-- The settlement account remains financial_account_id; this is source traceability.
ALTER TABLE mpesa_transactions
    ADD COLUMN collection_account_identifier VARCHAR(120) NULL AFTER financial_account_id,
    ADD KEY idx_mpesa_collection_account_identifier (collection_account_identifier);
