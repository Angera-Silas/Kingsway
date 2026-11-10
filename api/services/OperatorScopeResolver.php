<?php

declare(strict_types=1);

namespace App\API\Services;

use App\API\Modules\users\UserPermissionManager;
use PDO;

/**
 * Operator scope resolution — the leaf of the governed-assistant stack.
 *
 * This exists to break a dependency cycle. The draft repository and the
 * assistant both need "what may this operator do right now?", and the only
 * place that logic lived was on the assistant itself — which sits ABOVE both
 * of them. That produced a genuine cycle:
 *
 *   AgentAssistant -> AgentToolExecutor -> DraftRepository -> AgentAssistant
 *
 * and a second one into the AI engine (DraftRepository -> PythonAiBridge ->
 * AgentAssistant). Both are now gone: this class depends on nothing but the
 * user permission module, so anything may depend on it safely.
 *
 * Grants are always resolved from CURRENT server state, never from a queue
 * hint or a caller's claim. A role change takes effect on the next tool call,
 * and a revoked grant can never widen a task that was queued minutes ago.
 */
final class OperatorScopeResolver
{
    private PDO $db;

    public function __construct(PDO $db)
    {
        $this->db = $db;
    }

    /**
     * The operator's CURRENT effective permission codes.
     *
     * @return list<string>
     */
    public function permissions(int $userId): array
    {
        if ($userId < 1) {
            return [];
        }
        $codes = (new UserPermissionManager($this->db))->effectivePermissionsByUser([$userId]);
        $resolved = $codes[$userId] ?? [];
        return array_values(array_unique(array_filter(array_map('strval', (array) $resolved))));
    }

    /**
     * The operator's active role names, used only so the engine can tailor
     * wording. Never carries any authorization weight on its own.
     *
     * @return list<string>
     */
    public function roleNames(int $userId): array
    {
        if ($userId < 1) {
            return [];
        }
        return (new UserPermissionManager($this->db))->activeRoleNames($userId);
    }

    /**
     * Role rows for callers that need ids alongside names.
     *
     * @return list<array{id:int,name:string}>
     */
    public function roles(int $userId): array
    {
        if ($userId < 1) {
            return [];
        }
        $names = $this->roleNames($userId);
        if ($names === []) {
            return [];
        }
        $rows = [];
        foreach ($names as $index => $name) {
            $rows[] = ['id' => $index + 1, 'name' => $name];
        }
        return $rows;
    }
}
