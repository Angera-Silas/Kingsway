-- Real school money-holding accounts may fund any configured school purpose.
-- Purpose rows remain for reporting/backward compatibility; runtime eligibility
-- is validated against the global purpose catalogue plus account channels.
INSERT IGNORE INTO school_financial_account_purposes (financial_account_id, purpose_id)
SELECT a.id, p.id
FROM school_financial_accounts a
CROSS JOIN financial_account_purposes p;
