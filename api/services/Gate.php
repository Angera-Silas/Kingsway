<?php

namespace App\API\Services;

use RuntimeException;

/**
 * Gate - Laravel-style authorization facade over the existing PolicyEngine.
 *
 * Provides the ergonomic allows()/denies()/check()/authorize() surface while
 * delegating all rule evaluation to App\Services\PolicyEngine so the school's
 * system_policies table stays the single source of truth.
 *
 *   Gate::check('seefinance', ['route' => ['domain' => 'FINANCE']]); // bool
 *   Gate::allows(...); Gate::denies(...);
 *   Gate::authorize(...); // throws AuthorizationException (RuntimeException)
 *
 * The context is auto-seeded from the request auth state (AuthMiddleware leaves
 * the user in $_SERVER['auth_user']) when not supplied, so callers can usually
 * omit identity and pass only the route/ability detail.
 */
class Gate
{
    public const UNKNOWN_ABILITY = 'unknown_ability';

    /** @var array|null Explicit user context override (via forUser()). */
    private static $userContext = null;

    /**
     * Bind an explicit user context for subsequent checks (fluent).
     *
     * @param array $user ['user_id'=>int,'role_id'=>int,'permissions'=>array]
     * @return class-string<static> static for chaining
     */
    public static function forUser(array $user): string
    {
        self::$userContext = $user;
        return static::class;
    }

    public static function resetUser(): void
    {
        self::$userContext = null;
    }

    /**
     * Whether the current user may perform an ability / route action.
     *
     * @return bool
     */
    public static function check(string $ability, array $context = []): bool
    {
        $result = self::evaluate($ability, $context);
        return (bool) ($result['allowed'] ?? false);
    }

    public static function allows(string $ability, array $context = []): bool
    {
        return self::check($ability, $context);
    }

    public static function denies(string $ability, array $context = []): bool
    {
        return !self::check($ability, $context);
    }

    /**
     * Authorize, throwing when denied.
     *
     * @throws RuntimeException When the ability is not allowed.
     */
    public static function authorize(string $ability, array $context = [], string $deniedMessage = 'Action not permitted.'): void
    {
        $result = self::evaluate($ability, $context);
        if (!($result['allowed'] ?? false)) {
            $reason = $result['policy'] ?? $result['reason'] ?? 'no_policy_match';
            throw new RuntimeException(($result['description'] ?? $deniedMessage) . " [{$reason}]");
        }
    }

    /**
     * Evaluate via the PolicyEngine, returning its full result array.
     */
    public static function evaluate(string $ability, array $context = []): array
    {
        $final = array_replace(self::baseContext(), $context);
        $final['ability'] = $final['ability'] ?? $ability;
        // Provide a route domain default if none given, so policies that match
        // on route.domain still behave predictably.
        $final['route'] = $final['route'] ?? [];
        $final['route']['domain'] = $final['route']['domain'] ?? ($final['domain'] ?? null);
        return PolicyEngine::getInstance()->evaluate($final);
    }

    /**
     * Seed identity context from the authenticated request user, falling back to
     * the explicit forUser() override.
     */
    private static function baseContext(): array
    {
        $user = self::$userContext;
        if ($user === null && !empty($_SERVER['auth_user']) && is_array($_SERVER['auth_user'])) {
            $user = $_SERVER['auth_user'];
        }
        $user = is_array($user) ? $user : [];

        $roleId = $user['role_id'] ?? $user['role']['id'] ?? null;
        if ($roleId === null && !empty($user['role_ids']) && is_array($user['role_ids'])) {
            $roleId = (int) reset($user['role_ids']);
        }

        return [
            'user_id'     => (int) ($user['user_id'] ?? $user['id'] ?? 0),
            'role_id'     => $roleId === null ? null : (int) $roleId,
            'permissions' => (array) ($user['permissions'] ?? []),
        ];
    }
}
