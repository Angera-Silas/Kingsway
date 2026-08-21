-- Communication platform normalization (4NF)
-- Repeating channel, template-version, consent, attempt, attachment, thread,
-- and audit facts are stored in separate relations.

CREATE TABLE IF NOT EXISTS communication_template_catalog (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    code VARCHAR(100) NOT NULL,
    name VARCHAR(150) NOT NULL,
    purpose VARCHAR(100) NOT NULL,
    status ENUM('draft','active','inactive','archived') NOT NULL DEFAULT 'draft',
    created_by INT UNSIGNED NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_comm_template_code (code),
    KEY idx_comm_template_status (status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS communication_template_versions (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    template_id INT UNSIGNED NOT NULL,
    version_no INT UNSIGNED NOT NULL,
    status ENUM('draft','approved','active','retired') NOT NULL DEFAULT 'draft',
    created_by INT UNSIGNED NULL,
    approved_by INT UNSIGNED NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    approved_at DATETIME NULL,
    PRIMARY KEY (id),
    UNIQUE KEY uq_comm_template_version (template_id, version_no),
    KEY idx_comm_template_version_status (template_id, status),
    CONSTRAINT fk_comm_template_version_catalog
        FOREIGN KEY (template_id) REFERENCES communication_template_catalog(id)
        ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS communication_template_channels (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    template_version_id INT UNSIGNED NOT NULL,
    channel ENUM('email','sms','whatsapp','portal','in_app') NOT NULL,
    language_code VARCHAR(10) NOT NULL DEFAULT 'en',
    subject VARCHAR(255) NULL,
    body LONGTEXT NOT NULL,
    provider_name VARCHAR(80) NULL,
    provider_template_id VARCHAR(255) NULL,
    provider_template_name VARCHAR(255) NULL,
    provider_template_status VARCHAR(50) NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_comm_template_channel (template_version_id, channel, language_code),
    KEY idx_comm_template_provider (provider_name, provider_template_id),
    CONSTRAINT fk_comm_template_channel_version
        FOREIGN KEY (template_version_id) REFERENCES communication_template_versions(id)
        ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS communication_template_variables (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    template_channel_id INT UNSIGNED NOT NULL,
    variable_name VARCHAR(100) NOT NULL,
    data_type ENUM('string','integer','decimal','date','datetime','url','boolean') NOT NULL DEFAULT 'string',
    is_required TINYINT(1) NOT NULL DEFAULT 0,
    example_value VARCHAR(500) NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_comm_template_variable (template_channel_id, variable_name),
    CONSTRAINT fk_comm_template_variable_channel
        FOREIGN KEY (template_channel_id) REFERENCES communication_template_channels(id)
        ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS communication_preferences (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    user_id INT UNSIGNED NOT NULL,
    channel ENUM('email','sms','whatsapp','portal','in_app') NOT NULL,
    purpose VARCHAR(100) NOT NULL,
    is_enabled TINYINT(1) NOT NULL DEFAULT 1,
    quiet_hours_start TIME NULL,
    quiet_hours_end TIME NULL,
    updated_by INT UNSIGNED NULL,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_comm_preference (user_id, channel, purpose),
    KEY idx_comm_preference_lookup (user_id, purpose, is_enabled)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS communication_consents (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    user_id INT UNSIGNED NOT NULL,
    channel ENUM('email','sms','whatsapp') NOT NULL,
    purpose VARCHAR(100) NOT NULL,
    decision ENUM('granted','denied','unknown','withdrawn') NOT NULL DEFAULT 'unknown',
    source VARCHAR(80) NOT NULL,
    captured_at DATETIME NOT NULL,
    expires_at DATETIME NULL,
    recorded_by INT UNSIGNED NULL,
    PRIMARY KEY (id),
    KEY idx_comm_consent_lookup (user_id, channel, purpose, captured_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS communication_delivery_attempts (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    endpoint_id INT UNSIGNED NOT NULL,
    attempt_no INT UNSIGNED NOT NULL,
    request_status ENUM('started','accepted','rejected','timeout','error') NOT NULL,
    provider_message_id VARCHAR(255) NULL,
    provider_status VARCHAR(100) NULL,
    error_code VARCHAR(100) NULL,
    error_message TEXT NULL,
    request_started_at DATETIME NOT NULL,
    request_finished_at DATETIME NULL,
    raw_response LONGTEXT NULL,
    PRIMARY KEY (id),
    UNIQUE KEY uq_comm_attempt_number (endpoint_id, attempt_no),
    KEY idx_comm_attempt_provider (provider_message_id),
    CONSTRAINT fk_comm_attempt_endpoint
        FOREIGN KEY (endpoint_id) REFERENCES communication_recipient_endpoints(id)
        ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS communication_attachment_channels (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    attachment_id INT UNSIGNED NOT NULL,
    channel ENUM('email','whatsapp','portal') NOT NULL,
    provider_media_id VARCHAR(255) NULL,
    provider_media_url VARCHAR(1000) NULL,
    status ENUM('pending','ready','sent','failed') NOT NULL DEFAULT 'pending',
    last_error TEXT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_comm_attachment_channel (attachment_id, channel),
    CONSTRAINT fk_comm_attachment_channel_attachment
        FOREIGN KEY (attachment_id) REFERENCES communication_attachments(id)
        ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS communication_threads (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    thread_type ENUM('public_inquiry','parent_portal','staff','parent_event','other') NOT NULL,
    subject VARCHAR(255) NULL,
    created_by INT UNSIGNED NULL,
    status ENUM('open','pending','closed','archived') NOT NULL DEFAULT 'open',
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_comm_thread_status (thread_type, status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS communication_thread_messages (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    thread_id INT UNSIGNED NOT NULL,
    sender_user_id INT UNSIGNED NULL,
    sender_address VARCHAR(255) NULL,
    direction ENUM('inbound','outbound') NOT NULL,
    subject VARCHAR(255) NULL,
    body LONGTEXT NOT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_comm_thread_message_order (thread_id, created_at),
    CONSTRAINT fk_comm_thread_message_thread
        FOREIGN KEY (thread_id) REFERENCES communication_threads(id)
        ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS communication_thread_inquiries (
    thread_id INT UNSIGNED NOT NULL,
    inquiry_id INT UNSIGNED NOT NULL,
    PRIMARY KEY (thread_id, inquiry_id),
    UNIQUE KEY uq_comm_inquiry_thread (inquiry_id),
    CONSTRAINT fk_comm_thread_inquiry_thread
        FOREIGN KEY (thread_id) REFERENCES communication_threads(id)
        ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS communication_thread_internal_conversations (
    thread_id INT UNSIGNED NOT NULL,
    conversation_id INT UNSIGNED NOT NULL,
    PRIMARY KEY (thread_id, conversation_id),
    UNIQUE KEY uq_comm_internal_thread (conversation_id),
    CONSTRAINT fk_comm_thread_internal_thread
        FOREIGN KEY (thread_id) REFERENCES communication_threads(id)
        ON DELETE CASCADE,
    CONSTRAINT fk_comm_thread_internal_conversation
        FOREIGN KEY (conversation_id) REFERENCES internal_conversations(id)
        ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS communication_audit_events (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    communication_id INT UNSIGNED NULL,
    endpoint_id INT UNSIGNED NULL,
    thread_id INT UNSIGNED NULL,
    template_channel_id INT UNSIGNED NULL,
    actor_user_id INT UNSIGNED NULL,
    event_type VARCHAR(80) NOT NULL,
    provider_message_id VARCHAR(255) NULL,
    rendered_subject VARCHAR(255) NULL,
    rendered_body LONGTEXT NULL,
    raw_payload LONGTEXT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_comm_audit_communication (communication_id, created_at),
    KEY idx_comm_audit_endpoint (endpoint_id, created_at),
    KEY idx_comm_audit_provider (provider_message_id),
    CONSTRAINT fk_comm_audit_communication
        FOREIGN KEY (communication_id) REFERENCES communications(id)
        ON DELETE SET NULL,
    CONSTRAINT fk_comm_audit_endpoint
        FOREIGN KEY (endpoint_id) REFERENCES communication_recipient_endpoints(id)
        ON DELETE SET NULL,
    CONSTRAINT fk_comm_audit_thread
        FOREIGN KEY (thread_id) REFERENCES communication_threads(id)
        ON DELETE SET NULL,
    CONSTRAINT fk_comm_audit_template_channel
        FOREIGN KEY (template_channel_id) REFERENCES communication_template_channels(id)
        ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

ALTER TABLE communications
    ADD COLUMN template_channel_id INT UNSIGNED NULL AFTER template_id,
    ADD COLUMN thread_id INT UNSIGNED NULL AFTER template_channel_id,
    ADD COLUMN audit_hash CHAR(64) NULL AFTER thread_id,
    ADD KEY idx_comm_template_channel (template_channel_id),
    ADD KEY idx_comm_thread (thread_id);

ALTER TABLE communication_attachments
    ADD COLUMN mime_type VARCHAR(100) NULL AFTER file_path,
    ADD COLUMN file_size BIGINT UNSIGNED NULL AFTER mime_type,
    ADD COLUMN public_url VARCHAR(1000) NULL AFTER file_size,
    ADD KEY idx_comm_attachment_type (mime_type);

ALTER TABLE communications
    ADD CONSTRAINT fk_comm_template_channel
        FOREIGN KEY (template_channel_id) REFERENCES communication_template_channels(id)
        ON DELETE SET NULL,
    ADD CONSTRAINT fk_comm_thread
        FOREIGN KEY (thread_id) REFERENCES communication_threads(id)
        ON DELETE SET NULL;
