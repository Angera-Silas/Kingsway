UPDATE payment_collection_routes r
JOIN payment_providers p ON p.id = r.provider_id
SET r.display_title = 'KCB MOBILE APP - LIPA KARO'
WHERE p.code = 'kcb_buni'
  AND r.purpose = 'fees'
  AND r.account_identifier = '1130991288'
  AND r.active = 1;
