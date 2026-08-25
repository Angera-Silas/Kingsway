-- Make the parent-facing fee payment doors explicit.
-- Provider channels remain internal; only the instructions below are printed.

UPDATE school_financial_accounts
SET bank_name = 'KCB Bank Kenya'
WHERE account_identifier = '1130991288'
  AND account_name = 'KCB School Fees Account';

UPDATE payment_collection_routes r
JOIN payment_providers p ON p.id = r.provider_id
SET r.show_on_fee_structure = 1,
    r.display_order = 20,
    r.display_title = 'KCB MOBILE APP - LIPA KARO',
    r.display_reference_label = 'ADMISSION NO.',
    r.display_reference_value = 'Admission number',
    r.display_instructions = 'In the KCB Mobile App, select the school payment option, search for Kingsway Preparatory School, enter school account number 1130991288 and use the learner admission number as the reference.'
WHERE p.code = 'kcb_buni'
  AND r.account_identifier = '1130991288'
  AND r.purpose = 'fees'
  AND r.collection_product = 'buni';

UPDATE payment_collection_routes r
JOIN payment_providers p ON p.id = r.provider_id
SET r.display_order = 10,
    r.display_title = 'LIPA NA M-PESA (PAYBILL)',
    r.display_reference_label = 'ACCOUNT NO.',
    r.display_reference_value = 'Admission number',
    r.display_instructions = 'Open M-Pesa, choose Lipa na M-Pesa, Pay Bill, enter Paybill number 600986 and use the learner admission number as the account number.'
WHERE p.code = 'mpesa_daraja'
  AND r.account_identifier = '600986'
  AND r.purpose = 'fees'
  AND r.collection_product = 'paybill';

UPDATE payment_collection_routes r
JOIN payment_providers p ON p.id = r.provider_id
SET r.display_order = 30,
    r.display_title = 'BANK PAYMENT (BRANCH / AUTHORISED AGENT)',
    r.display_reference_label = 'ADMISSION NO.',
    r.display_reference_value = 'Admission number',
    r.display_instructions = 'Pay into the school fees account at a KCB branch or authorised KCB agent and quote the learner admission number.'
WHERE p.code = 'generic_bank'
  AND r.account_identifier = '1130991288'
  AND r.purpose = 'fees'
  AND r.collection_product = 'bank_collection';
