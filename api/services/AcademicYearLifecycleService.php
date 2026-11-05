<?php
namespace App\API\Services;

use PDO;

/**
 * Academic year lifecycle states.
 *
 * Every year is `active` unless it is `archived`; the sub-state records
 * where it sits in the school calendar:
 *
 *   active - upcoming    many years may be upcoming (rollover prep, intake)
 *   active - current     exactly one year
 *   active - completed   teaching finished; readable by every user,
 *                        editable only by school admin / system admin /
 *                        director
 *   archived             rare, system-admin only, far future; NOT part of
 *                        the annual rollover
 *
 * Completed is deliberately NOT "sealed": a school's data must stay
 * editable by authorised staff, so completed only raises the bar on who
 * may write to that year's rows.
 */
final class AcademicYearLifecycleService
{
    /**
     * Roles permitted to write into a completed (or archived) year.
     * Both spellings are accepted because callers may pass either the role
     * title ("School Administrator") or its code ("school_admin").
     */
    public const EDIT_ROLES = [
        'system_administrator', 'system_admin',
        'school_administrator', 'school_admin',
        'director',
    ];

    public const STATE_UPCOMING = 'upcoming';
    public const STATE_CURRENT = 'current';
    public const STATE_COMPLETED = 'completed';
    public const STATE_ARCHIVED = 'archived';

    public const STATUS_ACTIVE = 'active';
    public const STATUS_ARCHIVED = 'archived';

    /** Render the "active - completed" wording used across the UI. */
    public static function label(string $status, string $state): string
    {
        if ($status === self::STATUS_ARCHIVED) return 'archived';
        return 'active - ' . ($state !== '' ? $state : 'upcoming');
    }

    public static function allowedStates(): array
    {
        return [self::STATE_UPCOMING, self::STATE_CURRENT, self::STATE_COMPLETED, self::STATE_ARCHIVED];
    }

    /**
     * A completed (or archived) year is writable only by the roles above.
     * Upcoming and current years are writable by anyone the route already
     * authorised.
     */
    public static function isYearEditable(string $status, string $state, array $roles): bool
    {
        $isClosed = $state === self::STATE_COMPLETED
            || $state === self::STATE_ARCHIVED
            || $status === self::STATUS_ARCHIVED;

        if (!$isClosed) return true;

        // Callers may pass either the role title ("School Administrator")
        // or its code ("school_admin"), so normalise both spellings.
        $normalised = [];
        foreach ($roles as $role) {
            $role = strtolower(trim((string) $role));
            if ($role === '') continue;
            $normalised[$role] = true;
            $normalised[str_replace(' ', '_', $role)] = true;
        }

        foreach (self::EDIT_ROLES as $allowed) {
            if (isset($normalised[$allowed])) return true;
        }
        return false;
    }
}
