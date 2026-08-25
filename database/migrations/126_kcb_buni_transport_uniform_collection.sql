-- Sandbox KCB Buni collection setup for transport and temporary uniforms.
-- The same KCB account is intentionally reused for uniforms until KCB issues
-- a separate uniforms settlement account. Purpose-specific routes and GL
-- receivables still keep transport and uniforms separate in the application.

INSERT INTO school_financial_accounts
    (account_name, account_kind_id, provider_id, ledger_account_id,
     account_identifier, normalized_account_identifier, bank_name, currency,
     status, is_primary)
SELECT
    'KCB Transport Test Collection Account', 1, p.id, c.id,
    '1327111713', '1327111713', 'KCB Bank', 'KES', 'active', 0
FROM payment_providers p
JOIN chart_of_accounts c ON c.account_code = '110002'
WHERE p.code = 'kcb_buni'
  AND NOT EXISTS (
      SELECT 1 FROM school_financial_accounts a
      WHERE a.account_identifier = '1327111713'
        AND a.provider_id = p.id
  );

SET @kcb_transport_account_id = (
    SELECT id FROM school_financial_accounts
    WHERE account_identifier = '1327111713'
      AND provider_id = (SELECT id FROM payment_providers WHERE code = 'kcb_buni' LIMIT 1)
    ORDER BY id DESC LIMIT 1
);

INSERT IGNORE INTO school_financial_account_purposes (financial_account_id, purpose_id)
SELECT @kcb_transport_account_id, id
FROM financial_account_purposes
WHERE code IN ('transport', 'uniforms');

INSERT IGNORE INTO school_financial_account_channels (financial_account_id, channel_id)
SELECT @kcb_transport_account_id, id
FROM financial_channels
WHERE code IN ('buni_ipn', 'buni_transfer');

INSERT IGNORE INTO school_financial_account_permissions
    (financial_account_id, role_id, can_receive, can_disburse)
SELECT @kcb_transport_account_id, id, 1, 1
FROM roles
WHERE id = 3;

INSERT INTO payment_collection_routes
    (provider_id, display_name, show_on_fee_structure, display_order,
     display_title, display_reference_label, display_reference_value,
     display_instructions, financial_account_id,
     settlement_financial_account_id, account_identifier,
     normalized_account_identifier, collection_product, reference_policy,
     reference_label, purpose, reference_prefix, active)
SELECT p.id,
       'KCB Buni Transport Collections', 1, 20,
       'KCB TRANSPORT COLLECTIONS', 'ACCOUNT REFERENCE',
       'TRN reference supplied by the school system',
       'Pay through KCB Buni using the transport reference shown in the parent portal.',
       @kcb_transport_account_id, @kcb_transport_account_id,
       '1327111713', '1327111713', 'buni', 'transport_reference',
       'TRANSPORT REFERENCE', 'transport', 'TRN', 1
FROM payment_providers p
WHERE p.code = 'kcb_buni'
  AND NOT EXISTS (
      SELECT 1 FROM payment_collection_routes r
      WHERE r.provider_id = p.id
        AND r.account_identifier = '1327111713'
        AND r.purpose = 'transport'
  );

INSERT INTO payment_collection_routes
    (provider_id, display_name, show_on_fee_structure, display_order,
     display_title, display_reference_label, display_reference_value,
     display_instructions, financial_account_id,
     settlement_financial_account_id, account_identifier,
     normalized_account_identifier, collection_product, reference_policy,
     reference_label, purpose, reference_prefix, active)
SELECT p.id,
       'KCB Buni Uniform Test Collections', 0, 21,
       'KCB UNIFORM TEST COLLECTIONS', 'ACCOUNT REFERENCE',
       'Temporary uniform collection route using the transport test account.',
       'Temporary sandbox route. Pay through KCB Buni using the uniform reference shown in the parent portal.',
       @kcb_transport_account_id, @kcb_transport_account_id,
       '1327111713', '1327111713', 'buni', 'uniform_reference',
       'UNIFORM REFERENCE', 'uniforms', 'U', 1
FROM payment_providers p
WHERE p.code = 'kcb_buni'
  AND NOT EXISTS (
      SELECT 1 FROM payment_collection_routes r
      WHERE r.provider_id = p.id
        AND r.account_identifier = '1327111713'
        AND r.purpose = 'uniforms'
  );

INSERT INTO payment_collection_route_channels (route_id, channel_id)
SELECT r.id, c.id
FROM payment_collection_routes r
JOIN financial_channels c ON c.code = 'buni_ipn'
WHERE r.provider_id = (SELECT id FROM payment_providers WHERE code = 'kcb_buni' LIMIT 1)
  AND r.account_identifier = '1327111713'
  AND r.purpose IN ('transport', 'uniforms')
  AND NOT EXISTS (
      SELECT 1 FROM payment_collection_route_channels x
      WHERE x.route_id = r.id AND x.channel_id = c.id
  );

