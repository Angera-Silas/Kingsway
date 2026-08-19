/* Align catalog ownership and legacy-sale references with the normalized model. */
ALTER TABLE uniform_catalog_carts
    ADD CONSTRAINT fk_uniform_cart_parent FOREIGN KEY (parent_id) REFERENCES parents(id) ON DELETE CASCADE;

ALTER TABLE uniform_catalog_wishlists
    ADD CONSTRAINT fk_uniform_wishlist_parent FOREIGN KEY (parent_id) REFERENCES parents(id) ON DELETE CASCADE;

ALTER TABLE uniform_catalog_orders
    ADD CONSTRAINT fk_uniform_order_parent FOREIGN KEY (parent_id) REFERENCES parents(id) ON DELETE RESTRICT;
