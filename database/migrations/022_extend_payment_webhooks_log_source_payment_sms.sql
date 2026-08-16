-- Add payment_sms to payment_webhooks_log.source so the payment-received
-- confirmation SMS audit rows can be recorded.
ALTER TABLE payment_webhooks_log
    MODIFY COLUMN source ENUM(
        'mpesa_stk',
        'mpesa_c2b_validation',
        'mpesa_c2b_confirmation',
        'mpesa_b2c',
        'mpesa_result',
        'kcb_bank',
        'generic_bank',
        'payment_sms'
    ) NOT NULL;
