-- 021_extend_payment_webhooks_log_source.sql
-- Extends the payment_webhooks_log.source enum so B2C result callbacks and
-- generic M-Pesa result/notification payloads (transaction status, account
-- balance, reversal, B2B) can be audited without tripping the enum constraint.

ALTER TABLE `payment_webhooks_log`
    MODIFY COLUMN `source` enum(
        'mpesa_stk',
        'mpesa_c2b_validation',
        'mpesa_c2b_confirmation',
        'mpesa_b2c',
        'mpesa_result',
        'kcb_bank',
        'generic_bank'
    ) NOT NULL;
