-- Sandbox-only collection points. These identifiers can be replaced in this
-- route configuration when the school receives its production details.
UPDATE payment_collection_routes
SET account_identifier = '600986', normalized_account_identifier = '600986',
    display_name = 'Sandbox M-Pesa C2B Fees',
    display_title = 'SANDBOX M-PESA FEES',
    display_reference_label = 'ACCOUNT NO.',
    display_reference_value = 'Admission number',
    display_instructions = 'Sandbox testing only. Use the learner admission number as the account reference.'
WHERE id = 7 AND purpose = 'fees' AND collection_product = 'paybill';

DELETE FROM payment_collection_route_channels WHERE route_id = 7 AND channel_id = (SELECT id FROM financial_channels WHERE code = 'mpesa_stk');

INSERT INTO payment_collection_routes
    (provider_id, display_name, show_on_fee_structure, display_order, display_title,
     display_reference_label, display_reference_value, display_instructions,
     financial_account_id, settlement_financial_account_id, account_identifier,
     normalized_account_identifier, collection_product, reference_policy,
     reference_label, purpose, reference_prefix, active)
SELECT p.id, 'Sandbox M-Pesa STK Fees', 0, 99, 'SANDBOX M-PESA STK FEES',
       'ACCOUNT NO.', 'Admission number', 'Sandbox STK testing only.',
       1, 1, '174379', '174379', 'paybill', 'admission_no',
       'ACCOUNT NO.', 'fees', 'FEE', 1
FROM payment_providers p
WHERE p.code = 'mpesa_daraja'
  AND NOT EXISTS (
      SELECT 1 FROM payment_collection_routes r
      WHERE r.account_identifier = '174379'
        AND r.purpose = 'fees'
        AND r.collection_product = 'paybill'
  );

INSERT INTO payment_collection_route_channels (route_id, channel_id)
SELECT r.id, c.id
FROM payment_collection_routes r
JOIN financial_channels c ON c.code = 'mpesa_stk'
WHERE r.account_identifier = '174379'
  AND r.purpose = 'fees'
  AND r.collection_product = 'paybill'
  AND NOT EXISTS (
      SELECT 1 FROM payment_collection_route_channels x
      WHERE x.route_id = r.id AND x.channel_id = c.id
  );
