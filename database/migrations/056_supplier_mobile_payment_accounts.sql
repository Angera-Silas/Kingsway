CREATE TABLE IF NOT EXISTS supplier_mobile_accounts (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    supplier_id INT UNSIGNED NOT NULL,
    provider ENUM('mpesa') NOT NULL DEFAULT 'mpesa',
    phone_number VARCHAR(20) NOT NULL,
    account_name VARCHAR(160) NOT NULL,
    verification_status ENUM('unverified','pending','verified','rejected') NOT NULL DEFAULT 'unverified',
    is_primary TINYINT(1) NOT NULL DEFAULT 0,
    active TINYINT(1) NOT NULL DEFAULT 1,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_supplier_mobile_account (supplier_id, provider, phone_number),
    KEY idx_supplier_mobile_active (supplier_id, active, is_primary),
    CONSTRAINT fk_supplier_mobile_supplier FOREIGN KEY (supplier_id) REFERENCES suppliers (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

ALTER TABLE supplier_payment_requests
    MODIFY supplier_bank_account_id INT UNSIGNED NULL,
    ADD COLUMN supplier_mobile_account_id INT UNSIGNED NULL AFTER supplier_bank_account_id,
    MODIFY status ENUM('draft','approved','payment_pending','paid','failed','cancelled') NOT NULL DEFAULT 'draft';
