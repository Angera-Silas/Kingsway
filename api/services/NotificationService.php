<?php

namespace App\API\Services;

use App\Database\Database;
use PDO;

/**
 * NotificationService — central push pipeline for the Kingsway notification
 * system.
 *
 * Modules push notifications here instead of writing the `notifications`
 * table directly, so every notification is:
 *
 *   - addressed to a real audience (users, staff members, roles, everyone);
 *   - human-readable ("Your leave request was approved by Jennifer");
 *   - optionally de-duplicated so regenerations and re-sends never spam;
 *   - recorded once with one read_status, so "mark as read" works everywhere.
 *
 * Usage from any module:
 *   $service = new NotificationService($this->db);
 *   $service->push(42, 'leave_request', 'Leave approved', 'Your leave request was approved by Jennifer', 'medium');
 *   $service->push('all_staff', 'fee_structure', 'Fee structure released', 'The 2026 fee structure is now out.', 'high');
 *   $service->push('role:3', 'announcement', 'Results released', 'Term 2 results are available.', 'medium');
 *   $service->push([7, 9], 'expense', 'Expense approved', 'Your expense was approved by David.');
 */
class NotificationService
{
    private PDO $db;

    public function __construct(?PDO $db = null)
    {
        $this->db = $db ?? Database::getInstance()->getConnection();
    }

    /**
     * Push a notification to one or many recipients.
     *
     * $recipients accepts:
     *   - int            a single user id
     *   - int[]          a list of user ids
     *   - 'all_staff'    every active user linked to a staff record
     *   - 'all_users'    every active user
     *   - 'role:{id}'    every active user with the role id
     *   - 'role:{name}'  every active user with the role name
     *   - 'staff:{id}'   the active user(s) linked to a staff member id
     *
     * $options:
     *   - dedup_minutes: skip if an identical (user_id, type, title) row was
     *                    created within this window (e.g. 60 for calendar
     *                    regeneration jobs). Default: no dedup.
     *   - created_at:    override the timestamp (MySQL 'Y-m-d H:i:s').
     *
     * @return int number of notification rows inserted
     */
    public function push($recipients, string $type, string $title, string $message, string $priority = 'medium', array $options = []): int
    {
        $priority = in_array($priority, ['low', 'medium', 'high'], true) ? $priority : 'medium';
        $userIds = $this->resolveRecipients($recipients);
        if (empty($userIds)) {
            return 0;
        }

        $dedupMinutes = (int) ($options['dedup_minutes'] ?? 0);
        $createdAt = $options['created_at'] ?? date('Y-m-d H:i:s');

        $inserted = 0;
        $hasContext = isset($options['action_url']) || isset($options['reference_type'])
            || isset($options['reference_id']) || isset($options['reminder_window']);
        $insert = $this->db->prepare($hasContext
            ? "INSERT INTO notifications
                (user_id, type, title, message, action_url, reference_type, reference_id,
                 reminder_window, priority, read_status, created_at)
               VALUES (:uid, :type, :title, :message, :action_url, :reference_type,
                       :reference_id, :reminder_window, :priority, 'unread', :created_at)"
            : "INSERT INTO notifications (user_id, type, title, message, priority, read_status, created_at)
               VALUES (:uid, :type, :title, :message, :priority, 'unread', :created_at)");
        $dedup = $dedupMinutes > 0
            ? $this->db->prepare(
                "SELECT COUNT(*) FROM notifications
                  WHERE user_id = :uid AND type = :type AND title = :title
                    AND created_at >= NOW() - INTERVAL :minutes MINUTE"
            )
            : null;

        foreach (array_unique(array_map('intval', $userIds)) as $uid) {
            if ($dedup !== null) {
                $dedup->execute([
                    ':uid' => $uid,
                    ':type' => $type,
                    ':title' => $title,
                    ':minutes' => $dedupMinutes,
                ]);
                if ((int) $dedup->fetchColumn() > 0) {
                    continue;
                }
            }
            $params = [
                ':uid' => $uid,
                ':type' => $type,
                ':title' => $title,
                ':message' => $message,
                ':priority' => $priority,
                ':created_at' => $createdAt,
            ];
            if ($hasContext) {
                $params[':action_url'] = $options['action_url'] ?? null;
                $params[':reference_type'] = $options['reference_type'] ?? null;
                $params[':reference_id'] = isset($options['reference_id']) ? (int) $options['reference_id'] : null;
                $params[':reminder_window'] = $options['reminder_window'] ?? null;
            }
            try {
                $insert->execute($params);
            } catch (\PDOException $e) {
                if ((int) ($e->errorInfo[1] ?? 0) !== 1062) throw $e;
                continue;
            }
            $inserted++;
        }

        return $inserted;
    }

    /**
     * Convenience alias for a single push with a shorter call site.
     */
    public static function pushNotification($recipients, string $type, string $title, string $message, string $priority = 'medium', array $options = []): int
    {
        return (new self())->push($recipients, $type, $title, $message, $priority, $options);
    }

    /**
     * Human-readable message builders — keep notification copy consistent.
     */
    public static function messageText(string $senderName, string $snippet = ''): string
    {
        $text = "New message from {$senderName}.";
        if ($snippet !== '') {
            $text .= ' "' . mb_substr($snippet, 0, 120) . '"';
        }
        return $text;
    }

    public static function approvedText(string $requestLabel, string $byName): string
    {
        return "Your {$requestLabel} was approved by {$byName}.";
    }

    public static function deniedText(string $requestLabel, string $byName, string $reason = ''): string
    {
        $text = "Your {$requestLabel} was declined by {$byName}.";
        if ($reason !== '') {
            $text .= ' Reason: ' . mb_substr($reason, 0, 200) . '.';
        }
        return $text;
    }

    public static function publishedText(string $label): string
    {
        return $label . ' is now available.';
    }

    // ========================================================================
    // Recipient resolution
    // ========================================================================

    public function resolveRecipients($recipients): array
    {
        if (is_string($recipients)) {
            if ($recipients === 'all_staff') {
                return $this->allStaffUserIds();
            }
            if ($recipients === 'all_users') {
                return $this->allActiveUserIds();
            }
            if (strpos($recipients, 'role:') === 0) {
                return $this->userIdsForRole(substr($recipients, 5));
            }
            if (strpos($recipients, 'staff:') === 0) {
                return $this->userIdsForStaff([(int) substr($recipients, 6)]);
            }
            return is_numeric($recipients) ? [(int) $recipients] : [];
        }

        if (is_int($recipients)) {
            return [$recipients];
        }

        if (is_array($recipients)) {
            $ids = [];
            foreach ($recipients as $entry) {
                foreach ($this->resolveRecipients($entry) as $uid) {
                    $ids[] = (int) $uid;
                }
            }
            return array_values(array_unique(array_filter($ids)));
        }

        return [];
    }

    /**
     * Active user ids linked to staff member ids (users.person_id = staff.person_id).
     */
    public function userIdsForStaff(array $staffIds): array
    {
        $staffIds = array_values(array_unique(array_map('intval', array_filter($staffIds))));
        if (empty($staffIds)) {
            return [];
        }
        $in = implode(',', array_fill(0, count($staffIds), '?'));
        $stmt = $this->db->prepare(
            "SELECT DISTINCT u.id
               FROM users u
               JOIN staff s ON s.person_id = u.person_id
              WHERE s.id IN ($in) AND u.status = 'active'"
        );
        $stmt->execute($staffIds);
        return array_map('intval', $stmt->fetchAll(PDO::FETCH_COLUMN));
    }

    /**
     * Active user ids holding a role (id or name).
     */
    public function userIdsForRole($roleIdOrName): array
    {
        if (is_numeric($roleIdOrName)) {
            $stmt = $this->db->prepare(
                "SELECT DISTINCT ur.user_id
                   FROM user_roles ur
                   JOIN users u ON u.id = ur.user_id
                  WHERE ur.role_id = ? AND u.status = 'active'"
            );
            $stmt->execute([(int) $roleIdOrName]);
        } else {
            $stmt = $this->db->prepare(
                "SELECT DISTINCT ur.user_id
                   FROM user_roles ur
                   JOIN roles r ON r.id = ur.role_id
                   JOIN users u ON u.id = ur.user_id
                  WHERE r.name = ? AND u.status = 'active'"
            );
            $stmt->execute([(string) $roleIdOrName]);
        }
        return array_map('intval', $stmt->fetchAll(PDO::FETCH_COLUMN));
    }

    /**
     * Active users linked to a staff record (the "staff audience").
     */
    public function allStaffUserIds(int $excludeUserId = 0): array
    {
        $sql =
            "SELECT DISTINCT u.id
               FROM users u
               JOIN staff s ON s.person_id = u.person_id
              WHERE u.status = 'active'";
        $params = [];
        if ($excludeUserId > 0) {
            $sql .= ' AND u.id <> ?';
            $params[] = $excludeUserId;
        }
        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        return array_map('intval', $stmt->fetchAll(PDO::FETCH_COLUMN));
    }

    /**
     * Every active user.
     */
    public function allActiveUserIds(int $excludeUserId = 0): array
    {
        $sql = "SELECT id FROM users WHERE status = 'active'";
        $params = [];
        if ($excludeUserId > 0) {
            $sql .= ' AND id <> ?';
            $params[] = $excludeUserId;
        }
        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        return array_map('intval', $stmt->fetchAll(PDO::FETCH_COLUMN));
    }

    /**
     * Display name for a user id (first + last name from persons), or null.
     */
    public function userName(int $userId): ?string
    {
        $stmt = $this->db->prepare(
            "SELECT CONCAT_WS(' ', p.first_name, p.last_name)
               FROM users u
               JOIN persons p ON p.id = u.person_id
              WHERE u.id = ?"
        );
        $stmt->execute([$userId]);
        $name = $stmt->fetchColumn();
        return $name !== null && $name !== '' ? (string) $name : null;
    }
}
