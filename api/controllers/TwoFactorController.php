<?php
/**
 * Two-Factor Authentication Controller
 *
 * Thin endpoint layer; all business logic lives in TwoFactorService
 * (TOTP/OTP, backup codes) and OTPDeliveryService (email/SMS delivery).
 *
 * API endpoints:
 *   POST /api/2fa/status          — Get 2FA status for current user
 *   POST /api/2fa/setup/totp      — Start TOTP setup (returns secret + QR URL)
 *   POST /api/2fa/setup/verify    — Verify first TOTP/OTP code to enable 2FA
 *   POST /api/2fa/setup/email     — Start email OTP setup (sends code)
 *   POST /api/2fa/setup/sms       — Start SMS OTP setup (sends code)
 *   POST /api/2fa/disable         — Disable 2FA (requires password + code)
 *   POST /api/2fa/backup/generate — Generate new backup codes
 *   POST /api/2fa/backup/verify   — Verify a backup code (used during login)
 *   POST /api/2fa/challenge       — Send OTP for login challenge
 *   POST /api/2fa/verify          — Verify OTP during login
 *
 * @package App\API\Controllers
 */

namespace App\API\Controllers;

use App\API\Services\TwoFactorService;
use App\API\Services\OTPDeliveryService;

class TwoFactorController extends BaseController
{
    private TwoFactorService $tfa;
    private OTPDeliveryService $otpDelivery;

    public function __construct()
    {
        parent::__construct();
        $this->tfa = new TwoFactorService();
        $this->otpDelivery = new OTPDeliveryService();
    }

    private function currentUser(): array
    {
        return $_SERVER['auth_user'] ?? [];
    }

    /**
     * POST /api/2fa/status
     */
    public function postStatus()
    {
        $userId = $this->getUserId();
        if (!$userId) return $this->unauthorized('Not authenticated');

        return $this->success($this->tfa->get2FAStatus($userId));
    }

    /**
     * POST /api/2fa/setup/totp
     * Returns the TOTP secret and otpauth:// URL for QR code generation.
     */
    public function postSetupTotp()
    {
        $userId = $this->getUserId();
        if (!$userId) return $this->unauthorized('Not authenticated');

        $secret = $this->tfa->generateSecret();
        $user = $this->currentUser();
        $email = $user['email'] ?? '';
        $uri = $this->tfa->getTOTPUri($secret, $email);

        $this->tfa->storePendingSecret($userId, $secret, 'totp');

        return $this->success([
            'secret' => $secret,
            'qr_code_url' => $uri,
            'manual_entry_key' => $secret,
            'digits' => 6,
            'period' => 30,
        ], 'Scan the QR code with your authenticator app, then verify with a code.');
    }

    /**
     * POST /api/2fa/setup/email
     * Sends an OTP to the user's email for email-based 2FA setup.
     */
    public function postSetupEmail()
    {
        $userId = $this->getUserId();
        if (!$userId) return $this->unauthorized('Not authenticated');

        $user = $this->currentUser();
        $email = $user['email'] ?? '';
        if (!$email) return $this->badRequest('No email address on file');

        $code = $this->tfa->generateOTP($userId, 'email', 'setup');
        if (!$code) return $this->serverError('Failed to generate OTP');

        $sent = $this->otpDelivery->sendEmailOTP($email, $code, 'setup');
        if (!$sent) return $this->serverError('Failed to send verification email');

        return $this->success(['method' => 'email', 'expires_in' => 600],
            'A verification code has been sent to your email.');
    }

    /**
     * POST /api/2fa/setup/sms
     * Sends an OTP to the user's phone for SMS-based 2FA setup.
     */
    public function postSetupSms()
    {
        $userId = $this->getUserId();
        if (!$userId) return $this->unauthorized('Not authenticated');

        $user = $this->currentUser();
        $phone = $user['phone'] ?? $user['phone_1'] ?? '';
        if (!$phone) return $this->badRequest('No phone number on file');

        $code = $this->tfa->generateOTP($userId, 'sms', 'setup');
        if (!$code) return $this->serverError('Failed to generate OTP');

        $sent = $this->otpDelivery->sendSMSOTP($phone, $code, 'setup');
        if (!$sent) return $this->serverError('Failed to send SMS');

        return $this->success(['method' => 'sms', 'expires_in' => 600],
            'A verification code has been sent to your phone.');
    }

    /**
     * POST /api/2fa/setup/verify
     * Verify the first code to complete 2FA setup.
     * Body: { code: "123456" }
     */
    public function postSetupVerify($id = null, $data = [])
    {
        $userId = $this->getUserId();
        if (!$userId) return $this->unauthorized('Not authenticated');

        $code = trim($data['code'] ?? '');
        if (!$code) return $this->badRequest('Verification code is required');

        $pending = $this->tfa->getPendingSecret($userId);
        if (!$pending) return $this->badRequest('No pending 2FA setup. Start setup again.');

        $verified = false;
        $secret = $pending['secret'];

        if ($pending['method'] === 'totp') {
            $verified = $this->tfa->verifyTOTP($secret, $code);
        } elseif (in_array($pending['method'], ['email', 'sms'])) {
            $verified = $this->tfa->verifyOTP($userId, $code, 'setup');
        }

        if (!$verified) {
            return $this->badRequest('Invalid verification code. Please try again.');
        }

        $storeSecret = $secret ?? strtoupper(bin2hex(random_bytes(16)));
        $this->tfa->enable2FA($userId, $pending['method'], $storeSecret);

        $backupCodes = $this->tfa->generateBackupCodes($userId);

        $user = $this->currentUser();
        if (!empty($user['email'])) {
            $this->otpDelivery->sendBackupCodesEmail($user['email'], $backupCodes);
        }

        $this->tfa->clearPendingSecret($userId);

        return $this->success([
            'method' => $pending['method'],
            'backup_codes' => $backupCodes,
            'backup_codes_remaining' => 10,
        ], 'Two-factor authentication has been enabled. Save your backup codes!');
    }

    /**
     * POST /api/2fa/disable
     * Disable 2FA. Requires password + current TOTP/OTP code.
     * Body: { password: "...", code: "123456" }
     */
    public function postDisable($id = null, $data = [])
    {
        $userId = $this->getUserId();
        if (!$userId) return $this->unauthorized('Not authenticated');

        $password = $data['password'] ?? '';
        $code = trim($data['code'] ?? '');

        if (!$password) return $this->badRequest('Password is required');
        if (!$code) return $this->badRequest('Verification code is required');

        if (!$this->tfa->verifyUserPassword($userId, $password)) {
            return $this->badRequest('Incorrect password');
        }

        $method = $this->tfa->getRequiredMethod($userId);
        $verified = false;

        if ($method === 'totp') {
            $secret = $this->tfa->getSecret($userId);
            $verified = $secret && $this->tfa->verifyTOTP($secret, $code);
        } elseif (in_array($method, ['email', 'sms'])) {
            $verified = $this->tfa->verifyOTP($userId, $code, 'disable');
        }

        if (!$verified) {
            return $this->badRequest('Invalid verification code');
        }

        $this->tfa->disable2FA($userId);

        return $this->success(null, 'Two-factor authentication has been disabled.');
    }

    /**
     * POST /api/2fa/backup/generate
     * Generate new backup codes (invalidates old ones).
     */
    public function postBackupGenerate()
    {
        $userId = $this->getUserId();
        if (!$userId) return $this->unauthorized('Not authenticated');

        if (!$this->tfa->is2FAEnabled($userId)) {
            return $this->badRequest('Enable 2FA first');
        }

        $codes = $this->tfa->rotateBackupCodes($userId);

        $user = $this->currentUser();
        if (!empty($user['email'])) {
            $this->otpDelivery->sendBackupCodesEmail($user['email'], $codes);
        }

        return $this->success([
            'backup_codes' => $codes,
            'backup_codes_remaining' => count($codes),
        ], 'New backup codes generated. Old codes have been invalidated.');
    }

    /**
     * POST /api/2fa/challenge
     * Send an OTP challenge during login (after password verified).
     * Body: { method: "email"|"sms", user_id: int }
     * Called by the login flow when 2FA is required.
     */
    public function postChallenge($id = null, $data = [])
    {
        $targetUserId = (int) ($data['user_id'] ?? 0);
        if (!$targetUserId) return $this->badRequest('User ID is required');

        $method = $this->tfa->getRequiredMethod($targetUserId);
        if (!$method) return $this->badRequest('2FA is not enabled for this user');

        if ($method === 'totp') {
            return $this->success(['method' => 'totp', 'challenge_sent' => false],
                'Enter the code from your authenticator app.');
        }

        $contact = $this->tfa->getUserContact($targetUserId);

        $code = $this->tfa->generateOTP($targetUserId, $method, 'login');
        if (!$code) return $this->serverError('Failed to generate OTP');

        $sent = false;
        if ($method === 'email' && !empty($contact['email'])) {
            $sent = $this->otpDelivery->sendEmailOTP($contact['email'], $code, 'login');
        } elseif ($method === 'sms' && !empty($contact['phone_1'])) {
            $sent = $this->otpDelivery->sendSMSOTP($contact['phone_1'], $code, 'login');
        }

        if (!$sent) return $this->serverError("Failed to send {$method} verification code");

        return $this->success([
            'method' => $method,
            'challenge_sent' => true,
            'expires_in' => 600,
        ], "A verification code has been sent to your {$method}.");
    }

    /**
     * POST /api/2fa/verify
     * Verify 2FA code during login.
     * Body: { code: "123456", user_id: int, method: "totp"|"email"|"sms"|"backup" }
     */
    public function postVerify($id = null, $data = [])
    {
        $targetUserId = (int) ($data['user_id'] ?? 0);
        $code = trim($data['code'] ?? '');
        $method = $data['method'] ?? 'totp';

        if (!$targetUserId) return $this->badRequest('User ID is required');
        if (!$code) return $this->badRequest('Verification code is required');

        $verified = false;

        switch ($method) {
            case 'totp':
                $secret = $this->tfa->getSecret($targetUserId);
                $verified = $secret && $this->tfa->verifyTOTP($secret, $code);
                break;

            case 'email':
            case 'sms':
                $verified = $this->tfa->verifyOTP($targetUserId, $code, 'login');
                break;

            case 'backup':
                $verified = $this->tfa->verifyBackupCode($targetUserId, $code);
                break;

            default:
                return $this->badRequest('Invalid 2FA method');
        }

        if (!$verified) {
            return $this->forbidden('Invalid verification code');
        }

        return $this->success([
            'verified' => true,
            'user_id' => $targetUserId,
        ], 'Verification successful.');
    }
}
