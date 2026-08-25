-- Parent-facing payment instructions belong to the collection point,
-- because each Paybill, Till, Buni route or bank route can display differently.
ALTER TABLE payment_collection_routes
    ADD COLUMN show_on_fee_structure TINYINT(1) NOT NULL DEFAULT 0 AFTER display_name,
    ADD COLUMN display_order INT NOT NULL DEFAULT 0 AFTER show_on_fee_structure,
    ADD COLUMN display_title VARCHAR(160) NULL AFTER display_order,
    ADD COLUMN display_reference_label VARCHAR(100) NULL AFTER display_title,
    ADD COLUMN display_reference_value VARCHAR(160) NULL AFTER display_reference_label,
    ADD COLUMN display_instructions TEXT NULL AFTER display_reference_value,
    ADD COLUMN display_updated_by INT UNSIGNED NULL AFTER display_instructions,
    ADD KEY idx_collection_route_fee_display (show_on_fee_structure, display_order);
