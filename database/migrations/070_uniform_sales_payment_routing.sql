ALTER TABLE payment_routing_references
    MODIFY purpose ENUM('fees','transport','uniforms') NOT NULL,
    ADD COLUMN uniform_sale_id INT UNSIGNED DEFAULT NULL AFTER transport_intent_id,
    ADD KEY idx_payment_routing_uniform_sale (uniform_sale_id);

ALTER TABLE payment_collection_routes
    MODIFY purpose ENUM('fees','transport','uniforms') NOT NULL;

ALTER TABLE payment_unmatched_cases
    MODIFY purpose_candidate ENUM('fees','transport','uniforms','unknown','conflict') NOT NULL DEFAULT 'unknown';

CREATE TABLE IF NOT EXISTS uniform_payment_intents (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    sale_id INT UNSIGNED NOT NULL,
    student_id INT UNSIGNED NOT NULL,
    channel ENUM('daraja_mpesa','buni_mpesa','c2b_mpesa','bank_transfer','cash','cheque') NOT NULL,
    amount DECIMAL(12,2) NOT NULL,
    idempotency_reference VARCHAR(150) NOT NULL,
    provider_request_id VARCHAR(150) DEFAULT NULL,
    provider_transaction_id VARCHAR(150) DEFAULT NULL,
    status ENUM('pending','accepted','manual_review','confirmed','failed','reversed','cancelled') NOT NULL DEFAULT 'pending',
    request_payload JSON DEFAULT NULL,
    response_payload JSON DEFAULT NULL,
    created_by INT UNSIGNED DEFAULT NULL,
    confirmed_at DATETIME DEFAULT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id), UNIQUE KEY uq_uniform_intent_reference (idempotency_reference),
    UNIQUE KEY uq_uniform_intent_provider_tx (channel, provider_transaction_id),
    KEY idx_uniform_intent_status (status, created_at),
    CONSTRAINT fk_uniform_intent_sale FOREIGN KEY (sale_id) REFERENCES uniform_sales(id),
    CONSTRAINT fk_uniform_intent_student FOREIGN KEY (student_id) REFERENCES students(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
