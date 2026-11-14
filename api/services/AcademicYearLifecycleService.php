<?php
namespace App\API\Services;

/**
 * Academic year lifecycle states.
 *
 * Every year has status `active`; the state records where it sits in the
 * school calendar:
 *
 *   upcoming    many years may be upcoming (rollover prep, intake)
 *   current     exactly one year
 *   completed   teaching finished; readable by every user, editable only by
 *               school admin / system admin / director, or by a user the
 *               system administrator has granted EDIT_COMPLETED_PERMISSION
 *
 * There is no archiving. Every year the school has run stays visible and
 * its records stay available (decision of 2026-11-14: "every data matters").
 */
final class AcademicYearLifecycleService
{
    /**
     * Roles that may always write into a completed year. Both spellings are
     * accepted because callers may pass the role title ("School
     * Administrator") or its code ("school_admin").
     */
    public const EDIT_ROLES = [
        'system_administrator', 'system_admin',
        'school_administrator', 'school_admin',
        'director',
    ];

    /**
     * Permission a system administrator grants (per user, or per role) so
     * another user may edit records in a completed year.
     */
    public const EDIT_COMPLETED_PERMISSION = 'academic_years_edit_completed';

    public const STATE_UPCOMING = 'upcoming';
    public const STATE_CURRENT = 'current';
    public const STATE_COMPLETED = 'completed';

    public const STATUS_ACTIVE = 'active';

    /** Render the "active - completed" wording used across the UI. */
    public static function label(string $status, string $state): string
    {
        return 'active - ' . ($state !== '' ? $state : 'upcoming');
    }

    public static function allowedStates(): array
    {
        return [self::STATE_UPCOMING, self::STATE_CURRENT, self::STATE_COMPLETED];
    }

    public static function isCompleted(string $state): bool
    {
        return $state === self::STATE_COMPLETED;
    }

    /**
     * Whether the caller may write into a year in this state.
     *
     * Upcoming and current years are writable by anyone the route already
     * authorised. A completed year is writable by the EDIT_ROLES, or when
     * $hasEditCompletedPermission is true (the caller resolved
     * EDIT_COMPLETED_PERMISSION for the user).
     */
    public static function isYearEditable(
        string $status,
        string $state,
        array $roles,
        bool $hasEditCompletedPermission = false
    ): bool {
        if (!self::isCompleted($state)) return true;
        if ($hasEditCompletedPermission) return true;

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
