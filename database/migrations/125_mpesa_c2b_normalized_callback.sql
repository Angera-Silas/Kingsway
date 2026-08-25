-- Keep C2B callbacks in the normalized M-Pesa transaction table. The old
-- balance-maintenance triggers target the removed legacy student_fee_balances
-- table; fee balances and journals are now maintained by the normalized
-- payment workflow and FinancialPostingCoordinator.
DROP TRIGGER IF EXISTS trg_mpesa_payment_processed;
DROP TRIGGER IF EXISTS trg_bank_payment_processed;
