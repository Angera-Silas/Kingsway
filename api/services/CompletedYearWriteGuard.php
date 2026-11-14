<?php
namespace App\API\Services;

use App\API\Middleware\RBACMiddleware;

/**
 * Write guard for completed academic years.
 *
 * Rule (user, 2026-11-14): a completed year is NOT sealed. Its data stays
 * readable for every user. School admin, system admin and director may
 * always write to it; any other user may write to it only when the system
 * administrator has granted them AcademicYearLifecycleService::
 * EDIT_COMPLETED_PERMISSION (directly, or through one of their roles).
 * Upcoming and current years are writable by anyone the route has already
 * authorised.
 */
final class CompletedYearWriteGuard
{
    /**
     * @param int      $yearId    academic_years.id being written to
     * @param array    $roleNames current user's role names (titles or codes)
     * @param \PDO     $db        connection, if the caller has one
     * @param int|null $userId    current user, to resolve the grantable permission
     *
     * @return array{allowed:bool, year:?array, reason:string}
     */
    public static function check(int $yearId, array $roleNames, $db = null, ?int $userId = null): array
    {
        $db = $db ?: \App\Database\Database::getInstance()->getConnection();

        $stmt = $db->prepare(
            "SELECT id, year_code, status, state, is_current FROM academic_years WHERE id = ? LIMIT 1"
        );
        $stmt->execute([$yearId]);
        $year = $stmt->fetch(\PDO::FETCH_ASSOC);

        if (!$year) {
            return ['allowed' => false, 'year' => null, 'reason' => 'Academic year not found'];
        }

        $status = (string) $year['status'];
        $state = (string) $year['state'];

        if (AcademicYearLifecycleService::isYearEditable(
            $status,
            $state,
            $roleNames,
            self::hasEditCompletedPermission($state, $userId)
        )) {
            return ['allowed' => true, 'year' => $year, 'reason' => ''];
        }

        return [
            'allowed' => false,
            'year' => $year,
            'reason' => sprintf(
                '%s is completed. Its records stay readable for every user, but only a school administrator, '
                . 'system administrator, director or a user given permission by the system administrator may edit them.',
                $year['year_code']
            ),
        ];
    }

    /** Only looked up for completed years, so ordinary writes cost nothing. */
    private static function hasEditCompletedPermission(string $state, ?int $userId): bool
    {
        if (!AcademicYearLifecycleService::isCompleted($state)) return false;
        if (!$userId || $userId <= 0) return false;
        try {
            return (bool) RBACMiddleware::hasPermission(
                $userId,
                AcademicYearLifecycleService::EDIT_COMPLETED_PERMISSION
            );
        } catch (\Throwable $e) {
            return false;
        }
    }
}
