/* Preserve the originating legacy sale on aggregate catalogue orders. */
ALTER TABLE uniform_catalog_order_items
    ADD COLUMN sale_id INT UNSIGNED NULL AFTER size_id,
    ADD KEY idx_uniform_order_item_sale (sale_id),
    ADD CONSTRAINT fk_uniform_order_line_product FOREIGN KEY (product_id) REFERENCES uniform_catalog_products(id) ON DELETE RESTRICT,
    ADD CONSTRAINT fk_uniform_order_line_sale FOREIGN KEY (sale_id) REFERENCES uniform_sales(id) ON DELETE SET NULL;
