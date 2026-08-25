ALTER TABLE payment_collection_routes
    ADD COLUMN display_name VARCHAR(160) NULL AFTER provider_id;
