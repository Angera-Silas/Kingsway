-- Device-neutral gate attendance boundary.  Devices submit signed events;
-- Kingsway stores credential references and timestamps, never fingerprints.
CREATE TABLE IF NOT EXISTS staff_gate_devices (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    device_code VARCHAR(100) NOT NULL,
    device_name VARCHAR(160) NOT NULL,
    location VARCHAR(160) DEFAULT NULL,
    provider VARCHAR(100) DEFAULT NULL,
    shared_secret_hash CHAR(64) DEFAULT NULL,
    status ENUM('active','disabled') NOT NULL DEFAULT 'active',
    last_seen_at DATETIME DEFAULT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id), UNIQUE KEY uq_staff_gate_device_code (device_code),
    KEY idx_staff_gate_device_status (status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS staff_gate_events (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    provider_event_id VARCHAR(160) NOT NULL,
    device_id BIGINT UNSIGNED NOT NULL,
    staff_id INT UNSIGNED DEFAULT NULL,
    credential_type ENUM('qr','barcode','fingerprint','other') NOT NULL,
    credential_reference VARCHAR(255) NOT NULL,
    event_type ENUM('check_in','check_out') DEFAULT NULL,
    captured_at DATETIME NOT NULL,
    received_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    processing_status ENUM('processed','rejected','duplicate') NOT NULL,
    rejection_reason VARCHAR(255) DEFAULT NULL,
    payload_hash CHAR(64) DEFAULT NULL,
    PRIMARY KEY (id), UNIQUE KEY uq_staff_gate_provider_event (provider_event_id),
    KEY idx_staff_gate_events_staff_date (staff_id, captured_at),
    KEY idx_staff_gate_events_device (device_id, captured_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
