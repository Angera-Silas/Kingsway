UPDATE payment_collection_routes r
JOIN payment_providers p ON p.id = r.provider_id
SET r.display_title = 'BANK PAYMENT'
WHERE p.code = 'generic_bank'
  AND r.purpose = 'fees'
  AND r.account_identifier = '1130991288'
  AND r.active = 1;
