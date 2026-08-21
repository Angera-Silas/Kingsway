-- Production 2FA hardening. All user-factor data is decomposed by fact:
-- one row per factor, one row per login challenge, one row per audit event.
ALTER TABLE user_2fa_otp_sessions
    MODIFY otp_type ENUM('login','setup','setup_pending','disable','email','sms') NOT NULL DEFAULT 'login';

CREATE TABLE IF NOT EXISTS user_two_factor_methods (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    user_id INT UNSIGNED NOT NULL,
    method ENUM('totp','email','sms','whatsapp') NOT NULL,
    secret_ciphertext TEXT NULL,
    label VARCHAR(120) NULL,
    is_primary TINYINT(1) NOT NULL DEFAULT 0,
    is_enabled TINYINT(1) NOT NULL DEFAULT 1,
    verified_at DATETIME NULL,
    last_used_timestep BIGINT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_user_factor (user_id, method),
    KEY idx_factor_user_enabled (user_id, is_enabled),
    CONSTRAINT fk_factor_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS user_two_factor_challenges (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    user_id INT UNSIGNED NOT NULL,
    challenge_hash CHAR(64) NOT NULL,
    method ENUM('totp','email','sms','whatsapp','backup') NOT NULL,
    status ENUM('pending','verified','consumed','expired','locked') NOT NULL DEFAULT 'pending',
    expires_at DATETIME NOT NULL,
    attempts TINYINT UNSIGNED NOT NULL DEFAULT 0,
    ip_address VARCHAR(45) NULL,
    user_agent VARCHAR(512) NULL,
    verified_at DATETIME NULL,
    consumed_at DATETIME NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_challenge_hash (challenge_hash),
    KEY idx_challenge_user_status (user_id, status, expires_at),
    CONSTRAINT fk_challenge_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS user_two_factor_audit_events (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    user_id INT UNSIGNED NULL,
    event_type VARCHAR(50) NOT NULL,
    method VARCHAR(20) NULL,
    challenge_id BIGINT UNSIGNED NULL,
    success TINYINT(1) NOT NULL DEFAULT 0,
    ip_address VARCHAR(45) NULL,
    user_agent VARCHAR(512) NULL,
    metadata JSON NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_2fa_audit_user_time (user_id, created_at),
    KEY idx_2fa_audit_challenge (challenge_id),
    CONSTRAINT fk_2fa_audit_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL,
    CONSTRAINT fk_2fa_audit_challenge FOREIGN KEY (challenge_id) REFERENCES user_two_factor_challenges(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
