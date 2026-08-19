-- WhatsApp inbound messages and media metadata.
-- One inbound event is one external_inbound_messages row; media is a separate
-- relation so future provider payloads can contain more than one attachment.

ALTER TABLE external_inbound_messages
    MODIFY source_type ENUM('sms','email','whatsapp','web','other') NOT NULL,
    ADD COLUMN provider_message_id VARCHAR(255) NULL AFTER source_address,
    ADD COLUMN raw_payload LONGTEXT NULL AFTER processing_notes,
    ADD COLUMN thread_id INT UNSIGNED NULL AFTER linked_student_id,
    ADD UNIQUE KEY uq_external_inbound_provider_message (source_type, provider_message_id),
    ADD KEY idx_external_inbound_thread (thread_id),
    ADD CONSTRAINT fk_external_inbound_thread
        FOREIGN KEY (thread_id) REFERENCES communication_threads(id)
        ON DELETE SET NULL;

CREATE TABLE IF NOT EXISTS external_inbound_media (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    inbound_message_id INT UNSIGNED NOT NULL,
    media_type ENUM('image','video','audio','sticker','document','voice','other') NOT NULL DEFAULT 'other',
    media_url VARCHAR(1000) NOT NULL,
    caption VARCHAR(1000) NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_external_inbound_media_message (inbound_message_id),
    CONSTRAINT fk_external_inbound_media_message
        FOREIGN KEY (inbound_message_id) REFERENCES external_inbound_messages(id)
        ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
