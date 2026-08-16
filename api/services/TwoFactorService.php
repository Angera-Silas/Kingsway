<?php
/**
 * Two-Factor Authentication Service
 *
 * Supports three 2FA methods:
 *  - TOTP (Time-based One-Time Password) for authenticator apps
 *  - Email OTP (6-digit code sent via PHPMailer/SMTP)
 *  - SMS OTP (6-digit code sent via Africa's Talking)
 *
 * Also manages backup recovery codes (10 one-time-use codes).
 *
 * @package App\Services
 */

namespace App\API\Services;

require_once dirname(__DIR__, 2) . '/database/Database.php';

use App\Database\Database;
use PDO;

class TwoFactorService
{
    private PDO $db;

    /** TOTP defaults (RFC 6238) */
    private const TOTP_PERIOD = 30;      // seconds per code
    private const TOTP_DIGITS = 6;       // code length
    private const TOTP_ALGORITHM = 'sha1';
    private const TOTP_ISSUER = 'Kingsway Academy';

    /** OTP delivery defaults */
    private const OTP_LENGTH = 6;
    private const OTP_TTL_MINUTES = 10;
    private const OTP_MAX_ATTEMPTS = 5;

    /** Backup codes */
    private const BACKUP_CODE_COUNT = 10;
    private const BACKUP_CODE_LENGTH = 8;

    public function __construct(?PDO $db = null)
    {
        $this->db = $db ?? Database::getInstance()->getConnection();
    }

    // ========================================================================
    // TOTP — Authenticator App
    // ========================================================================

    /**
     * Generate a random TOTP secret (Base32-encoded, 20 bytes).
     */
    public function generateSecret(): string
    {
        $bytes = random_bytes(20);
        return $this->base32Encode($bytes);
    }

    /**
     * Build the otpauth:// URI for QR code generation.
     */
    public function getTOTPUri(string $secret, string $email, ?string $label = null): string
    {
        $label = $label ?? $email;
        $issuer = rawurlencode(self::TOTP_ISSUER);
        $account = rawurlencode($label);
        $params = http_build_query([
            'secret' => $secret,
            'issuer' => self::TOTP_ISSUER,
            'algorithm' => 'SHA1',
            'digits' => self::TOTP_DIGITS,
            'period' => self::TOTP_PERIOD,
        ]);
        return "otpauth://totp/{$issuer}:{$account}?{$params}";
    }

    /**
     * Verify a TOTP code against a secret.
     * Allows ±1 time step drift (±30 seconds).
     */
    public function verifyTOTP(string $secret, string $code): bool
    {
        $decodedSecret = $this->base32Decode($secret);
        if ($decodedSecret === false) return false;

        $timeSlice = floor(time() / self::TOTP_PERIOD);

        // Check current, previous, and next time step
        for ($offset = -1; $offset <= 1; $offset++) {
            $calculatedCode = $this->generateTOTPCode(
                $decodedSecret,
                $timeSlice + $offset
            );
            if (hash_equals($calculatedCode, str_pad($code, self::TOTP_DIGITS, '0', STR_PAD_LEFT))) {
                return true;
            }
        }
        return false;
    }

    /**
     * Generate a TOTP code for a given time slice (RFC 6238).
     */
    private function generateTOTPCode(string $secret, int $timeSlice): string
    {
        // Pack time into 8-byte big-endian
        $time = pack('N*', 0) . pack('N*', $timeSlice);

        // HMAC-SHA1
        $hmac = hash_hmac('sha1', $time, $secret, true);

        // Dynamic truncation (RFC 4226)
        $offset = ord($hmac[strlen($hmac) - 1]) & 0x0F;
        $binary = ((ord($hmac[$offset]) & 0x7F) << 24)
                | ((ord($hmac[$offset + 1]) & 0xFF) << 16)
                | ((ord($hmac[$offset + 2]) & 0xFF) << 8)
                | (ord($hmac[$offset + 3]) & 0xFF);

        $otp = $binary % pow(10, self::TOTP_DIGITS);
        return str_pad((string) $otp, self::TOTP_DIGITS, '0', STR_PAD_LEFT);
    }

    // ========================================================================
    // Email / SMS OTP
    // ========================================================================

    /**
     * Generate and store an OTP for the given user and method.
     * Returns the plaintext code (to be sent by the delivery service).
     */
    public function generateOTP(int $userId, string $method, string $otpType = 'login'): ?string
    {
        if (!in_array($method, ['email', 'sms'], true)) return null;

        $code = str_pad(
            (string) random_int(0, pow(10, self::OTP_LENGTH) - 1),
            self::OTP_LENGTH,
            '0',
            STR_PAD_LEFT
        );

        $expires = date('Y-m-d H:i:s', time() + (self::OTP_TTL_MINUTES * 60));
        $ip = $_SERVER['REMOTE_ADDR'] ?? null;

        // Invalidate any previous unverified OTPs for this user/type
        $this->db->prepare(
            "UPDATE user_2fa_otp_sessions
             SET verified = 1
             WHERE user_id = ? AND otp_type = ? AND verified = 0"
        )->execute([$userId, $otpType]);

        $stmt = $this->db->prepare(
            "INSERT INTO user_2fa_otp_sessions
             (user_id, otp_code, otp_type, method, otp_expires_at, ip_address)
             VALUES (?, ?, ?, ?, ?, ?)"
        );
        $stmt->execute([
            $userId,
            password_hash($code, PASSWORD_DEFAULT),
            $otpType,
            $method,
            $expires,
            $ip,
        ]);

        return $code;
    }

    /**
     * Verify an OTP code. Returns true on success.
     */
    public function verifyOTP(int $userId, string $code, string $otpType = 'login'): bool
    {
        $stmt = $this->db->prepare(
            "SELECT id, otp_code, attempts
             FROM user_2fa_otp_sessions
             WHERE user_id = ? AND otp_type = ? AND verified = 0
             AND otp_expires_at > NOW()
             ORDER BY id DESC LIMIT 1"
        );
        $stmt->execute([$userId, $otpType]);
        $session = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$session) return false;

        if ((int) $session['attempts'] >= self::OTP_MAX_ATTEMPTS) {
            // Lock this OTP
            $this->db->prepare(
                "UPDATE user_2fa_otp_sessions SET verified = 1 WHERE id = ?"
            )->execute([$session['id']]);
            return false;
        }

        $attempts = (int) $session['attempts'] + 1;
        $this->db->prepare(
            "UPDATE user_2fa_otp_sessions SET attempts = ? WHERE id = ?"
        )->execute([$attempts, $session['id']]);

        if (password_verify($code, $session['otp_code'])) {
            $this->db->prepare(
                "UPDATE user_2fa_otp_sessions SET verified = 1 WHERE id = ?"
            )->execute([$session['id']]);
            return true;
        }

        return false;
    }

    // ========================================================================
    // Backup Codes
    // ========================================================================

    /**
     * Generate backup codes, store hashes, return plaintext codes.
     */
    public function generateBackupCodes(int $userId): array
    {
        $codes = [];
        $stmt = $this->db->prepare(
            "INSERT INTO user_2fa_backup_codes (user_id, code_hash) VALUES (?, ?)"
        );

        for ($i = 0; $i < self::BACKUP_CODE_COUNT; $i++) {
            // Format: XXXX-XXXX (8 chars with dash)
            $raw = bin2hex(random_bytes(4));
            $formatted = strtoupper(substr($raw, 0, 4) . '-' . substr($raw, 4, 4));
            $codes[] = $formatted;
            $stmt->execute([$userId, password_hash($formatted, PASSWORD_DEFAULT)]);
        }

        // Mark when backup codes were generated
        $this->db->prepare(
            "UPDATE users SET backup_codes_generated_at = NOW() WHERE id = ?"
        )->execute([$userId]);

        return $codes;
    }

    /**
     * Verify and consume a backup code. Returns true on success.
     */
    public function verifyBackupCode(int $userId, string $code): bool
    {
        $stmt = $this->db->prepare(
            "SELECT id, code_hash FROM user_2fa_backup_codes
             WHERE user_id = ? AND used_at IS NULL"
        );
        $stmt->execute([$userId]);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

        foreach ($rows as $row) {
            if (password_verify(strtoupper(trim($code)), $row['code_hash'])) {
                $this->db->prepare(
                    "UPDATE user_2fa_backup_codes SET used_at = NOW() WHERE id = ?"
                )->execute([$row['id']]);
                return true;
            }
        }
        return false;
    }

    /**
     * Get count of remaining backup codes.
     */
    public function getBackupCodeCount(int $userId): int
    {
        $stmt = $this->db->prepare(
            "SELECT COUNT(*) FROM user_2fa_backup_codes
             WHERE user_id = ? AND used_at IS NULL"
        );
        $stmt->execute([$userId]);
        return (int) $stmt->fetchColumn();
    }

    // ========================================================================
    // 2FA Enable / Disable / Status
    // ========================================================================

    /**
     * Check if 2FA is enabled for a user.
     */
    public function is2FAEnabled(int $userId): bool
    {
        $stmt = $this->db->prepare(
            "SELECT two_factor_enabled FROM users WHERE id = ?"
        );
        $stmt->execute([$userId]);
        return (bool) $stmt->fetchColumn();
    }

    /**
     * Get user's 2FA status.
     */
    public function get2FAStatus(int $userId): array
    {
        $stmt = $this->db->prepare(
            "SELECT two_factor_enabled, two_factor_method, two_factor_verified_at,
                    backup_codes_generated_at
             FROM users WHERE id = ?"
        );
        $stmt->execute([$userId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        return [
            'enabled' => (bool) ($row['two_factor_enabled'] ?? 0),
            'method' => $row['two_factor_method'] ?? null,
            'verified_at' => $row['two_factor_verified_at'] ?? null,
            'backup_codes_generated_at' => $row['backup_codes_generated_at'] ?? null,
            'backup_codes_remaining' => $this->getBackupCodeCount($userId),
        ];
    }

    /**
     * Enable 2FA for a user after they verify their first code.
     */
    public function enable2FA(int $userId, string $method, string $secret): bool
    {
        if (!in_array($method, ['totp', 'email', 'sms'], true)) return false;

        $stmt = $this->db->prepare(
            "UPDATE users
             SET two_factor_secret = ?,
                 two_factor_enabled = 1,
                 two_factor_method = ?,
                 two_factor_verified_at = NOW()
             WHERE id = ?"
        );
        return $stmt->execute([$secret, $method, $userId]);
    }

    /**
     * Disable 2FA for a user (requires password verification first).
     */
    public function disable2FA(int $userId): bool
    {
        $this->db->prepare(
            "DELETE FROM user_2fa_backup_codes WHERE user_id = ?"
        )->execute([$userId]);

        $this->db->prepare(
            "DELETE FROM user_2fa_otp_sessions WHERE user_id = ?"
        )->execute([$userId]);

        $stmt = $this->db->prepare(
            "UPDATE users
             SET two_factor_secret = NULL,
                 two_factor_enabled = 0,
                 two_factor_method = NULL,
                 two_factor_verified_at = NULL,
                 backup_codes_generated_at = NULL
             WHERE id = ?"
        );
        return $stmt->execute([$userId]);
    }

    /**
     * Get the TOTP secret for a user (encrypted in production).
     */
    public function getSecret(int $userId): ?string
    {
        $stmt = $this->db->prepare(
            "SELECT two_factor_secret FROM users WHERE id = ?"
        );
        $stmt->execute([$userId]);
        return $stmt->fetchColumn() ?: null;
    }

    // ========================================================================
    // 2FA Challenge — called during login flow
    // ========================================================================

    /**
     * Determine if a user needs 2FA verification after password login.
     * Returns the method or null if 2FA is not required.
     */
    public function getRequiredMethod(int $userId): ?string
    {
        $stmt = $this->db->prepare(
            "SELECT two_factor_enabled, two_factor_method
             FROM users WHERE id = ? AND status = 'active'"
        );
        $stmt->execute([$userId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$row || !$row['two_factor_enabled']) return null;

        return $row['two_factor_method'] ?? null;
    }

    /**
     * Check if the user is forced to use 2FA by school policy.
     */
    public function is2FARequiredByPolicy(int $userId): bool
    {
        // Check if any of the user's roles are in the forced-2FA list
        $stmt = $this->db->prepare(
            "SELECT setting_value FROM school_settings
             WHERE setting_key = 'security.require_2fa_roles'"
        );
        $stmt->execute();
        $requiredRoles = $stmt->fetchColumn();

        if (!$requiredRoles) return false;

        $roleIds = array_map('intval', array_filter(explode(',', $requiredRoles)));

        $stmt = $this->db->prepare(
            "SELECT ur.role_id FROM user_roles ur
             WHERE ur.user_id = ? AND ur.role_id IN (" .
             implode(',', array_fill(0, count($roleIds), '?')) . ")"
        );
        $stmt->execute(array_merge([$userId], $roleIds));

        return $stmt->fetchColumn() !== false;
    }

    // ========================================================================
    // Supporting operations (password check, backup rotation, pending setup)
    // ========================================================================

    /**
     * Verify a user's password against the stored hash.
     */
    public function verifyUserPassword(int $userId, string $password): bool
    {
        $stmt = $this->db->prepare("SELECT password FROM users WHERE id = ?");
        $stmt->execute([$userId]);
        $hash = $stmt->fetchColumn();

        return (bool) $hash && password_verify($password, $hash);
    }

    /**
     * Rotate backup codes: delete existing codes and generate a fresh set.
     *
     * @return string[] New backup codes.
     */
    public function rotateBackupCodes(int $userId): array
    {
        $stmt = $this->db->prepare("DELETE FROM user_2fa_backup_codes WHERE user_id = ?");
        $stmt->execute([$userId]);

        return $this->generateBackupCodes($userId);
    }

    /**
     * Fetch a user's contact channels for OTP delivery.
     *
     * @return array ['email' => ?string, 'phone_1' => ?string]
     */
    public function getUserContact(int $userId): array
    {
        $stmt = $this->db->prepare("SELECT p.email, p.phone AS phone_1 FROM users u JOIN persons p ON p.id = u.person_id WHERE u.id = ?");
        $stmt->execute([$userId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        return [
            'email'   => $row['email'] ?? null,
            'phone_1' => $row['phone_1'] ?? null,
        ];
    }

    /**
     * Store a pending TOTP setup secret (plaintext, 15-minute TTL).
     */
    public function storePendingSecret(int $userId, string $secret, string $method): void
    {
        if ($method !== 'totp') return;

        $stmt = $this->db->prepare(
            "DELETE FROM user_2fa_otp_sessions WHERE user_id = ? AND otp_type = 'setup_pending'"
        );
        $stmt->execute([$userId]);

        $expires = date('Y-m-d H:i:s', time() + 900);
        $stmt = $this->db->prepare(
            "INSERT INTO user_2fa_otp_sessions
             (user_id, otp_code, otp_type, method, otp_expires_at)
             VALUES (?, ?, 'setup_pending', 'totp', ?)"
        );
        $stmt->execute([$userId, $secret, $expires]);
    }

    /**
     * Retrieve a pending 2FA setup state.
     *
     * @return array|null ['secret' => ?string, 'method' => string]
     */
    public function getPendingSecret(int $userId): ?array
    {
        $stmt = $this->db->prepare(
            "SELECT otp_code AS secret, method FROM user_2fa_otp_sessions
             WHERE user_id = ? AND otp_type = 'setup_pending'
             AND otp_expires_at > NOW() ORDER BY id DESC LIMIT 1"
        );
        $stmt->execute([$userId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($row && $row['method'] === 'totp') {
            return $row;
        }

        $stmt = $this->db->prepare(
            "SELECT method FROM user_2fa_otp_sessions
             WHERE user_id = ? AND otp_type = 'setup'
             AND otp_expires_at > NOW() ORDER BY id DESC LIMIT 1"
        );
        $stmt->execute([$userId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($row) {
            return ['secret' => null, 'method' => $row['method']];
        }

        return null;
    }

    /**
     * Clear pending 2FA setup state for a user.
     */
    public function clearPendingSecret(int $userId): void
    {
        $stmt = $this->db->prepare(
            "DELETE FROM user_2fa_otp_sessions
             WHERE user_id = ? AND otp_type IN ('setup_pending', 'setup')"
        );
        $stmt->execute([$userId]);
    }

    // ========================================================================
    // Base32 helpers (RFC 4648)
    // ========================================================================

    private function base32Encode(string $data): string
    {
        $alphabet = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ234567';
        $bits = '';
        for ($i = 0; $i < strlen($data); $i++) {
            $bits .= str_pad(decbin(ord($data[$i])), 8, '0', STR_PAD_LEFT);
        }
        // Pad to multiple of 5
        $bits = str_pad($bits, ceil(strlen($bits) / 5) * 5, '0');

        $result = '';
        for ($i = 0; $i < strlen($bits); $i += 5) {
            $index = bindec(substr($bits, $i, 5));
            $result .= $alphabet[$index];
        }
        return $result;
    }

    private function base32Decode(string $input): ?string
    {
        $alphabet = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ234567';
        $input = rtrim(strtoupper($input), '=');

        $bits = '';
        for ($i = 0; $i < strlen($input); $i++) {
            $pos = strpos($alphabet, $input[$i]);
            if ($pos === false) return null;
            $bits .= str_pad(decbin($pos), 5, '0', STR_PAD_LEFT);
        }

        $result = '';
        for ($i = 0; $i + 8 <= strlen($bits); $i += 8) {
            $result .= chr(bindec(substr($bits, $i, 8)));
        }
        return $result;
    }
}
