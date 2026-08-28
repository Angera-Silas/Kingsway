<?php

namespace App\API\Services;

/**
 * RealtimeScopeResolver - maps an authenticated user's roles to the real-time
 * buffer scopes they may poll.
 *
 * This is the server-side source of truth for which role-scoped static buffers
 * a user may be handed. It must stay in sync with the school's role model and
 * with the target_scope values writers use when dispatching events.
 *
 * Principle (AGENTS.md): least privilege. Staff are given only the scopes their
 * duty requires; 'all' covers non-sensitive operational ticks shared across
 * authenticated staff. Do not broaden these blindly.
 */
class RealtimeScopeResolver
{
    /**
     * Resolve the set of scopes a user may poll given their role ids.
     *
     * @param int[] $roleIds
     * @param bool  $includeAll Whether the non-sensitive 'all' scope is included
     *                          for every authenticated staff member.
     * @return string[] Normalized scope keys.
     */
    public static function scopesForRoles(array $roleIds, bool $includeAll = true): array
    {
        $roleIds = array_values(array_unique(array_map('intval', $roleIds)));

        $scopes = [];
        if ($includeAll) {
            $scopes[] = EventBroadcaster::DEFAULT_SCOPE;
        }

        $map = self::roleScopeMap();
        foreach ($roleIds as $roleId) {
            $assigned = $map[$roleId] ?? [];
            foreach ($assigned as $scope) {
                $scopes[] = EventBroadcaster::normalizeScope($scope);
            }
        }

        return array_values(array_unique($scopes));
    }

    /**
     * Role id => scopes map.
     *
     * Role ids follow the repo's canonical ints (see migration 183/188 comments):
     *   2  System Admin (infrastructure, minimal school content)
     *   3  Director / 4 School Administrator
     *   5  Headteacher
     *   6  Deputy Head - Academic
     *   10 Accountant / Finance
     *   12 or 63 Discipline
     *   14 Inventory Manager
     *   16 Cateress
     *   18 Boarding Master
     *   30+ Transport, Health, Counselling, Security, etc.
     *
     * Keep conservative: scope = the smallest set that lets a role do its job.
     *
     * @return array<int, string[]>
     */
    private static function roleScopeMap(): array
    {
        return [
            2  => [],
            3  => ['finance', 'discipline', 'academic', 'boarding'],
            4  => ['finance', 'discipline', 'academic', 'boarding'],
            5  => ['discipline', 'academic', 'boarding'],
            6  => ['academic'],
            10 => ['finance'],
            12 => ['discipline'],
            14 => ['inventory'],
            16 => ['catering'],
            18 => ['boarding'],
            30 => ['health'],
            31 => ['transport'],
            63 => ['discipline'],
        ];
    }
}
