-- Employer statutory identity used on P9 and payroll reports.
ALTER TABLE school_profile
    ADD COLUMN IF NOT EXISTS employer_kra_pin VARCHAR(30) NULL AFTER school_code;

UPDATE school_profile
SET employer_kra_pin = 'P052317446G'
WHERE employer_kra_pin IS NULL OR TRIM(employer_kra_pin) = '';
