<?php

namespace App\API\Includes;

use App\API\Services\Logger;

/**
 * SecurityEventNotifier — canonical security/forensics audit emitter.
 *
 * All security-relevant events are written to the file-based audit journal
 * (logs/<env>/audit.log) via FileLogger with a stable, governed action name.
 * The System Admin "Audit & Forensics" console pages filter for exactly these
 * action names; without a producer they stay empty. Logs are NEVER written to
 * the database (migration 042 dropped the old audit_logs table; do not bring
 * log storage back into the database).
 *
 * Canonical actions emitted here:
 *   - unauthorized_access  — invalid/expired/missing token, bad issuer/audience
 *   - security_incident    — CSRF rejection, rate-limit throttle
 *   - permission_denied    — RBAC / module-action permission failures
 *   - policy_violation     — PolicyEngine deny / requirement failures
 *   - failed_login         — authentication failures (alias parity with auth.log)
 *   - login_failed         — legacy action name, emitted alongside failed_login
 *   - role_permission_*    — role/permission grants and revocations
 */
class SecurityEventNotifier
{
    /**
     * Emit an event with a canonical action name to the audit journal.
     *
     * @param string $action  Canonical action (e.g. 'unauthorized_access').
     * @param string $message Human-readable summary (never leaked client-side).
     * @param array  $context Entity / entity_id / route / details enrichment.
     * @param string $level   info|warning|error|critical.
     */
    public static function notify(string $action, string $message, array $context = [], string $level = 'warning'): void
    {
        $entry = array_merge(self::commonContext(), [
            'type' => 'audit',
            'action' => $action,
            'message' => $message,
            'status' => 'failure',
        ], $context);

        try {
            FileLogger::write('audit', $entry, $level);
        } catch (\Throwable $e) {
            Logger::legacyError('[SecurityEventNotifier] Failed to write ' . $action . ': ' . $e->getMessage());
        }
    }

    /** Unauthorized access: token/auth failures. */
    public static function unauthorizedAccess(string $message, array $context = []): void
    {
        self::notify('unauthorized_access', $message, $context, 'warning');
    }

    /** Security incident: CSRF rejection, throttling, anomalous behavior. */
    public static function securityIncident(string $message, array $context = []): void
    {
        self::notify('security_incident', $message, $context, 'critical');
    }

    /** Permission denied: RBAC / module-action permission failure. */
    public static function permissionDenied(string $message, array $context = []): void
    {
        self::notify('permission_denied', $message, $context, 'warning');
    }

    /** RBAC / route-level denial (alias used by the Policy Violations page). */
    public static function rbacDenied(string $message, array $context = []): void
    {
        self::notify('rbac_denied', $message, $context, 'warning');
    }

    /** Policy violation: PolicyEngine deny / requirement failure. */
    public static function policyViolation(string $message, array $context = []): void
    {
        self::notify('policy_violation', $message, $context, 'critical');
    }

    /** Failed login: writes both 'failed_login' and 'login_failed' for page parity. */
    public static function failedLogin(string $username, string $reason, array $context = []): void
    {
        $ctx = array_merge(['entity' => 'user', 'entity_id' => null, 'details' => ['reason' => $reason]], $context);
        self::notify('failed_login', 'Failed login attempt', $ctx, 'warning');
        self::notify('login_failed', 'Failed login attempt', $ctx, 'warning');
    }

    /** Successful permission/role mutation (parity for SystemAdminManager names). */
    public static function permissionChange(string $entity, $entityId, string $action, string $message, array $context = []): void
    {
        $ctx = array_merge(['entity' => $entity, 'entity_id' => $entityId, 'status' => 'success'], $context);
        self::notify($action, $message, $ctx, 'info');
    }

    /**
     * Resolve the authenticated performer (if any) plus HTTP context so every
     * audit entry is attributable even when the denial happens in middleware.
     */
    public static function commonContext(): array
    {
        $context = [
            'ip' => substr((string) ($_SERVER['REMOTE_ADDR'] ?? ''), 0, 45) ?: null,
            'user_agent' => substr((string) ($_SERVER['HTTP_USER_AGENT'] ?? ''), 0, 255) ?: null,
            'method' => strtoupper((string) ($_SERVER['REQUEST_METHOD'] ?? 'GET')),
            'route' => substr((string) ($_SERVER['REQUEST_URI'] ?? ''), 0, 500),
        ];

        $authUser = $_SERVER['auth_user'] ?? null;
        if (is_array($authUser) || is_object($authUser)) {
            $user = (array) $authUser;
            $context['user_id'] = (int) ($user['user_id'] ?? $user['id'] ?? 0) ?: null;
            $context['username'] = $user['username'] ?? null;
            $context['email'] = $user['email'] ?? null;
            $context['role_ids'] = $user['role_ids'] ?? null;
        } elseif (!empty($_SERVER['HTTP_AUTHORIZATION'])) {
            $userId = self::bearerSubjectUserId();
            if ($userId > 0) {
                $context['user_id'] = $userId;
            }
        }

        if (isset($_SERVER['auth_session_id'])) {
            $context['session_id'] = (int) $_SERVER['auth_session_id'];
        }

        return $context;
    }

    /** Best-effort user id from a bearer JWT (used when middleware denies before auth_user is set). */
    private static function bearerSubjectUserId(): int
    {
        $header = $_SERVER['HTTP_AUTHORIZATION']
            ?? $_SERVER['REDIRECT_HTTP_AUTHORIZATION']
            ?? '';
        if (!preg_match('/Bearer\s+(.+)/i', $header, $matches)) {
            return 0;
        }
        $parts = explode('.', trim($matches[1]));
        if (count($parts) !== 3) {
            return 0;
        }
        $payload = self::base64UrlDecode($parts[1]);
        if ($payload === false) {
            return 0;
        }
        $claims = json_decode($payload, true);
        if (!is_array($claims)) {
            return 0;
        }
        foreach (['user_id', 'id', 'sub'] as $key) {
            if (isset($claims[$key]) && is_numeric($claims[$key])) {
                return (int) $claims[$key];
            }
        }
        return 0;
    }

    private static function base64UrlDecode(string $value)
    {
        $remainder = strlen($value) % 4;
        if ($remainder > 0) {
            $value .= str_repeat('=', 4 - $remainder);
        }
        return base64_decode(strtr($value, '-_', '+/'));
    }
}