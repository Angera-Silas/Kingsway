-- 028_bank_transactions_status_recorded.sql
-- The KCB/generic bank webhook intentionally records incoming bank statement
-- rows with status 'recorded' so that trg_bank_payment_processed (which fires
-- on status='processed' and credits student_fee_balances) is NOT triggered a
-- second time: sp_process_student_payment already credited the student balance
-- at payment time. 'recorded' was missing from the enum, so the webhook insert
-- failed under strict SQL mode and bank payments never reached bank_transactions.

ALTER TABLE `bank_transactions`
    MODIFY COLUMN `status` ENUM('pending','processed','failed','recorded') NOT NULL DEFAULT 'pending';
