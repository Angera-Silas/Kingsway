-- Central payment routing facts. Provider/channel and destination account are
-- kept separate from the business-purpose reference.
CREATE TABLE IF NOT EXISTS payment_routing_references (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    reference VARCHAR(150) NOT NULL,
    purpose ENUM('fees','transport') NOT NULL,
    student_id INT UNSIGNED NOT NULL,
    transport_intent_id BIGINT UNSIGNED DEFAULT NULL,
    status ENUM('active','consumed','cancelled') NOT NULL DEFAULT 'active',
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    expires_at DATETIME DEFAULT NULL,
    PRIMARY KEY (id), UNIQUE KEY uq_payment_routing_reference (reference),
    KEY idx_payment_routing_student (student_id, purpose, status),
    CONSTRAINT fk_routing_reference_student FOREIGN KEY (student_id) REFERENCES students(id),
    CONSTRAINT fk_routing_reference_transport_intent FOREIGN KEY (transport_intent_id) REFERENCES transport_payment_intents(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS payment_collection_routes (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    provider_id INT UNSIGNED NOT NULL,
    account_identifier VARCHAR(120) NOT NULL,
    purpose ENUM('fees','transport') NOT NULL,
    reference_prefix VARCHAR(20) NOT NULL,
    active TINYINT(1) NOT NULL DEFAULT 1,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_collection_route (provider_id, account_identifier, purpose),
    CONSTRAINT fk_collection_route_provider FOREIGN KEY (provider_id) REFERENCES payment_providers(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS payment_unmatched_cases (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    provider_id INT UNSIGNED NOT NULL,
    provider_transaction_id VARCHAR(150) DEFAULT NULL,
    external_reference VARCHAR(150) DEFAULT NULL,
    account_identifier VARCHAR(120) DEFAULT NULL,
    amount DECIMAL(12,2) NOT NULL,
    currency CHAR(3) NOT NULL DEFAULT 'KES',
    reference_value VARCHAR(150) DEFAULT NULL,
    purpose_candidate ENUM('fees','transport','unknown','conflict') NOT NULL DEFAULT 'unknown',
    reason VARCHAR(255) NOT NULL,
    raw_payload JSON NOT NULL,
    status ENUM('unmatched','resolved','rejected') NOT NULL DEFAULT 'unmatched',
    resolved_student_id INT UNSIGNED DEFAULT NULL,
    resolved_by INT UNSIGNED DEFAULT NULL,
    resolved_at DATETIME DEFAULT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_unmatched_provider_tx (provider_id, provider_transaction_id),
    KEY idx_unmatched_status (status, created_at),
    CONSTRAINT fk_unmatched_provider FOREIGN KEY (provider_id) REFERENCES payment_providers(id),
    CONSTRAINT fk_unmatched_student FOREIGN KEY (resolved_student_id) REFERENCES students(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO payment_providers (code, display_name, environment)
VALUES ('generic_bank', 'Configured bank statement/webhook', 'sandbox')
ON DUPLICATE KEY UPDATE display_name=VALUES(display_name);
