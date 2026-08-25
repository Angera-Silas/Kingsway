-- Parent-facing fee instructions are optional. The printed fields already
-- identify the Paybill/account/reference and bank details.
UPDATE payment_collection_routes
SET display_instructions = NULL
WHERE purpose = 'fees'
  AND active = 1
  AND show_on_fee_structure = 1;
