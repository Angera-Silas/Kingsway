<?php

namespace App\API\Services\auth;

use App\API\Includes\BaseAPI;
use Exception;

/**
 * DeviceSessionManager
 *
 * Business logic for device-bound sessions: registration, validation,
 * blocking/unblocking, listing, and revoking user devices.
 *
 * Device records are tracked via user_sessions (the normalised session spine).
 */
class DeviceSessionManager extends BaseAPI
{
    public function __construct()
    {
        parent::__construct('auth');
    }

    /**
     * Register a device for a user (or refresh its last-used timestamp).
     *
     * @return int|null Session id, or null on failure.
     */
    public function registerDevice($userId, $deviceFingerprint, $deviceInfo)
    {
        try {
            $existing = $this->db->query(
                "SELECT id FROM user_sessions WHERE user_id = ? AND session_status = 'active' AND logout_time IS NULL ORDER BY last_activity DESC LIMIT 1",
                [$userId]
            )->fetch();

            if ($existing) {
                $this->db->query(
                    "UPDATE user_sessions SET last_activity = NOW() WHERE id = ?",
                    [$existing['id']]
                );
                return $existing['id'];
            }

            return null;
        } catch (Exception $e) {
            error_log("[DeviceSessionManager] Device registration failed: " . $e->getMessage());
            return null;
        }
    }

    /**
     * Validate a device for a user session.
     *
     * @return array ['valid' => bool, ...]
     */
    public function validateDevice($userId, $deviceFingerprint)
    {
        try {
            $session = $this->db->query(
                "SELECT id, session_status FROM user_sessions WHERE user_id = ? AND session_status = 'active' AND logout_time IS NULL ORDER BY last_activity DESC LIMIT 1",
                [$userId]
            )->fetch();

            if (!$session) {
                return ['valid' => false, 'reason' => 'No active session'];
            }

            $this->db->query(
                "UPDATE user_sessions SET last_activity = NOW() WHERE id = ?",
                [$session['id']]
            );

            return ['valid' => true, 'device_id' => $session['id']];
        } catch (Exception $e) {
            error_log("[DeviceSessionManager] Device validation failed: " . $e->getMessage());
            return ['valid' => false, 'reason' => 'Validation error'];
        }
    }

    /**
     * Block a device (log out its active session).
     */
    public function blockDevice($deviceId)
    {
        try {
            $this->db->query(
                "UPDATE user_sessions SET session_status = 'logged_out', logout_time = NOW() WHERE id = ?",
                [$deviceId]
            );
            return true;
        } catch (Exception $e) {
            error_log("[DeviceSessionManager] Device block failed: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Unblock a device (reactivate its session).
     */
    public function unblockDevice($deviceId)
    {
        try {
            $this->db->query(
                "UPDATE user_sessions SET session_status = 'active', logout_time = NULL WHERE id = ?",
                [$deviceId]
            );
            return true;
        } catch (Exception $e) {
            error_log("[DeviceSessionManager] Device unblock failed: " . $e->getMessage());
            return false;
        }
    }

    /**
     * List a user's sessions, most recently active first.
     *
     * @return array
     */
    public function getUserDevices($userId)
    {
        try {
            $sessions = $this->db->query(
                "SELECT id, user_id, ip_address, user_agent, login_time, last_activity, session_status
                 FROM user_sessions WHERE user_id = ? ORDER BY last_activity DESC",
                [$userId]
            )->fetchAll();

            return $sessions ?: [];
        } catch (Exception $e) {
            error_log("[DeviceSessionManager] Get user devices failed: " . $e->getMessage());
            return [];
        }
    }

    /**
     * Revoke a device (log out its session).
     */
    public function revokeDevice($deviceId)
    {
        try {
            $this->db->query(
                "UPDATE user_sessions SET session_status = 'logged_out', logout_time = NOW() WHERE id = ?",
                [$deviceId]
            );
            return true;
        } catch (Exception $e) {
            error_log("[DeviceSessionManager] Device revoke failed: " . $e->getMessage());
            return false;
        }
    }
}
