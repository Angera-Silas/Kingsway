CREATE TABLE IF NOT EXISTS user_passkeys (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    user_id INT UNSIGNED NOT NULL,
    credential_id VARCHAR(512) NOT NULL,
    credential_public_key TEXT NOT NULL,
    signature_count INT UNSIGNED NOT NULL DEFAULT 0,
    transports JSON NULL,
    label VARCHAR(120) NOT NULL DEFAULT 'Passkey',
    last_used_at DATETIME NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_passkey_credential (credential_id),
    KEY idx_passkey_user (user_id),
    CONSTRAINT fk_passkey_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS user_passkey_challenges (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    user_id INT UNSIGNED NULL,
    challenge_hash CHAR(64) NOT NULL,
    challenge_value TEXT NOT NULL,
    purpose ENUM('registration','authentication') NOT NULL,
    expires_at DATETIME NOT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_passkey_challenge (challenge_hash),
    KEY idx_passkey_challenge_expiry (expires_at),
    CONSTRAINT fk_passkey_challenge_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

ALTER TABLE user_two_factor_challenges MODIFY method ENUM('totp','email','sms','whatsapp','backup','passkey') NOT NULL;
ALTER TABLE users MODIFY two_factor_method ENUM('totp','email','sms','whatsapp','passkey','none') NULL;
