<?php
namespace App\API\Services;

use App\API\Services\AcademicYearLifecycleService;

/**
 * Write guard for closed academic years.
 *
 * Rule: a year marked active - completed is NOT sealed. Its data stays
 * readable for every user, but only school admin / system admin / director
 * may write to it. Years that are active - current or active - upcoming are
 * writable by anyone the route has already authorised.
 *
 * Archiving is deliberately absent: it is not a rollover action, and an
 * archived year is treated the same as a completed one for write access
 * (system admin only may write it).
 */
final class CompletedYearWriteGuard
{
    /**
     * @param int    $yearId      academic_years.id being written to
     * @param array  $roleNames   current user's role names (lowercase titles)
     * @param \PDO  $db           connection, if the caller has one
     *
     * @return array{allowed:bool, year:?array, reason:string}
     */
    public static function check(int $yearId, array $roleNames, $db = null): array
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

        if (AcademicYearLifecycleService::isYearEditable($status, $state, $roleNames)) {
            return ['allowed' => true, 'year' => $year, 'reason' => ''];
        }

        $label = AcademicYearLifecycleService::label($status, $state);
        return [
            'allowed' => false,
            'year' => $year,
            'reason' => sprintf(
                '%s is %s. Its records stay readable for every user, but only a school administrator, '
                . 'system administrator or director may edit them.',
                $year['year_code'],
                $label
            ),
        ];
    }
}
