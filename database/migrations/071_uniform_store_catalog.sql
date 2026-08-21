/* Uniform store catalogue, parent cart/wishlist and multi-line checkout. */
CREATE TABLE IF NOT EXISTS uniform_catalog_products (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    item_id INT UNSIGNED NOT NULL,
    slug VARCHAR(160) NOT NULL,
    title VARCHAR(255) NOT NULL,
    description TEXT NULL,
    published TINYINT(1) NOT NULL DEFAULT 0,
    status ENUM('draft','active','archived') NOT NULL DEFAULT 'draft',
    created_by INT UNSIGNED NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id), UNIQUE KEY uq_uniform_catalog_item (item_id), UNIQUE KEY uq_uniform_catalog_slug (slug),
    KEY idx_uniform_catalog_status (status,published),
    CONSTRAINT fk_uniform_catalog_item FOREIGN KEY (item_id) REFERENCES inventory_items(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS uniform_catalog_images (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    product_id INT UNSIGNED NOT NULL,
    url VARCHAR(1000) NOT NULL,
    alt_text VARCHAR(255) NULL,
    is_primary TINYINT(1) NOT NULL DEFAULT 0,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id), KEY idx_uniform_catalog_image_product (product_id,is_primary),
    CONSTRAINT fk_uniform_catalog_image_product FOREIGN KEY (product_id) REFERENCES uniform_catalog_products(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS uniform_catalog_wishlists (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    parent_id INT UNSIGNED NOT NULL,
    product_id INT UNSIGNED NOT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id), UNIQUE KEY uq_uniform_wishlist (parent_id,product_id),
    CONSTRAINT fk_uniform_wishlist_product FOREIGN KEY (product_id) REFERENCES uniform_catalog_products(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS uniform_catalog_carts (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    parent_id INT UNSIGNED NOT NULL,
    status ENUM('open','converted','abandoned') NOT NULL DEFAULT 'open',
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id), KEY idx_uniform_cart_parent (parent_id,status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS uniform_catalog_cart_items (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    cart_id BIGINT UNSIGNED NOT NULL,
    product_id INT UNSIGNED NOT NULL,
    size_id INT UNSIGNED NOT NULL,
    quantity INT UNSIGNED NOT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id), UNIQUE KEY uq_uniform_cart_item (cart_id,product_id,size_id),
    CONSTRAINT fk_uniform_cart_item_cart FOREIGN KEY (cart_id) REFERENCES uniform_catalog_carts(id) ON DELETE CASCADE,
    CONSTRAINT fk_uniform_cart_item_product FOREIGN KEY (product_id) REFERENCES uniform_catalog_products(id) ON DELETE CASCADE,
    CONSTRAINT fk_uniform_cart_item_size FOREIGN KEY (size_id) REFERENCES uniform_sizes(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS uniform_catalog_orders (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    parent_id INT UNSIGNED NOT NULL,
    student_id INT UNSIGNED NOT NULL,
    cart_id BIGINT UNSIGNED NULL,
    order_reference VARCHAR(80) NOT NULL,
    status ENUM('pending_payment','partially_paid','paid','cancelled','fulfilled') NOT NULL DEFAULT 'pending_payment',
    total_amount DECIMAL(12,2) NOT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id), UNIQUE KEY uq_uniform_order_reference (order_reference), KEY idx_uniform_order_parent (parent_id,status),
    CONSTRAINT fk_uniform_order_cart FOREIGN KEY (cart_id) REFERENCES uniform_catalog_carts(id) ON DELETE SET NULL,
    CONSTRAINT fk_uniform_order_student FOREIGN KEY (student_id) REFERENCES students(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS uniform_catalog_order_items (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    order_id BIGINT UNSIGNED NOT NULL,
    product_id INT UNSIGNED NULL,
    size_id INT UNSIGNED NOT NULL,
    sale_id INT UNSIGNED NULL,
    quantity INT UNSIGNED NOT NULL,
    unit_price DECIMAL(10,2) NOT NULL,
    PRIMARY KEY (id), KEY idx_uniform_order_item_order (order_id), KEY idx_uniform_order_item_product (product_id), KEY idx_uniform_order_item_sale (sale_id),
    CONSTRAINT fk_uniform_order_line_order FOREIGN KEY (order_id) REFERENCES uniform_catalog_orders(id) ON DELETE CASCADE,
    CONSTRAINT fk_uniform_order_line_product FOREIGN KEY (product_id) REFERENCES uniform_catalog_products(id) ON DELETE RESTRICT,
    CONSTRAINT fk_uniform_order_line_size FOREIGN KEY (size_id) REFERENCES uniform_sizes(id),
    CONSTRAINT fk_uniform_order_line_sale FOREIGN KEY (sale_id) REFERENCES uniform_sales(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

ALTER TABLE uniform_payment_intents MODIFY sale_id INT UNSIGNED NULL;
ALTER TABLE uniform_payment_intents ADD COLUMN order_id BIGINT UNSIGNED NULL AFTER sale_id;
ALTER TABLE uniform_payment_intents ADD KEY idx_uniform_intent_order (order_id,status);
ALTER TABLE uniform_payment_intents ADD CONSTRAINT fk_uniform_intent_order FOREIGN KEY (order_id) REFERENCES uniform_catalog_orders(id) ON DELETE SET NULL;
