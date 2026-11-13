<?php
declare(strict_types=1);

namespace App\API\Services;

use App\Database\Database;

/**
 * Device blocklist lookups (blocked_devices). Lives in the services layer so
 * middleware holds no SQL (Hard Rule 1).
 */
final class DeviceBlocklistService
{
    /** True when the user agent matches any blocked_devices.user_agent_pattern. */
    public static function isUserAgentBlocked(string $userAgent): bool
    {
        if ($userAgent === '') {
            return false;
        }
        $stmt = Database::getInstance()->query(
            "SELECT id FROM blocked_devices WHERE ? LIKE CONCAT('%', user_agent_pattern, '%') LIMIT 1",
            [$userAgent]
        );
        return (bool) $stmt->fetchColumn();
    }
}
