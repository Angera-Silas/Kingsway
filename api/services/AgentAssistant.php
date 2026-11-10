<?php

declare(strict_types=1);

namespace App\API\Services;

use DomainException;
use PDO;

/**
 * Agent orchestration delegate.
 *
 * PHP responsibilities here: validate inputs, resolve the authenticated
 * operator, and hand the governed envelope to the Python platform. Nothing in
 * this class routes, prompts, executes a tool, or calls a provider — that work
 * is owned by python_platform/app/orchestrator.py.
 */
final class AgentAssistant
{
    public const WORKFLOW_CHAT = 'system.agent_chat';
    public const WORKFLOW_TRIAGE = 'system.agent_triage';
    public const JOB_TYPE = 'ai.agent.run';

    /** Cache prefix for agent results (non-briefing caching) */
    public const CACHE_PREFIX = 'kw_agent_cache:';

    private const MAX_QUESTION_CHARS = 500;

    private AgentEngineClient $bridge;

    public function __construct(?AgentEngineClient $bridge = null)
    {
        $this->bridge = $bridge ?? new PythonAiBridge();
    }

    /**
     * @param array<string,mixed> $context authenticated operator envelope
     * @return array<string,mixed> governed answer contract from Python
     */
    public function assist(PDO $pdo, array $context, string $question): array
    {
        $question = trim($question);
        if ($question === '' || mb_strlen($question) > self::MAX_QUESTION_CHARS) {
            throw new DomainException('A question of up to 500 characters is required.', 422);
        }
        $userId = (int) ($context['user_id'] ?? 0);
        if ($userId < 1) {
            throw new DomainException('An authenticated staff member is required.', 401);
        }
        $audience = (string) ($context['audience'] ?? 'staff');
        if ($audience !== 'staff') {
            throw new DomainException('This assistant only serves authenticated staff.', 403);
        }

        $permissions = $this->resolveEffectivePermissions($pdo, $userId);

        // Authorization stays in PHP before any token reaches the engine.
        (new WorkflowAuthorization())->authorize(self::WORKFLOW_CHAT, [
            'user_id' => $userId,
            'permissions' => $permissions,
            'audience' => 'staff',
        ]);

        return $this->bridge->assist([
            'user_id' => $userId,
            'permissions' => $permissions,
            'roles' => $context['roles'] ?? [],
            'role_names' => $context['role_names'] ?? [],
            'effective_permissions' => $permissions,
            'audience' => 'staff',
            'route' => mb_substr((string) ($context['route'] ?? ''), 0, 120),
            'module' => mb_substr((string) ($context['module'] ?? 'dashboard'), 0, 60),
            'request_id' => mb_substr((string) ($context['request_id'] ?? 'ai-assist'), 0, 100),
            'session_id' => (string) ($context['session_id'] ?? ''),
            'history' => is_array($context['history'] ?? null) ? array_slice($context['history'], -10) : [],
            'topics' => is_array($context['topics'] ?? null) ? array_slice($context['topics'], 0, 12) : [],
            'preferences' => is_array($context['preferences'] ?? null) ? $context['preferences'] : [],
        ], $question);
    }

    /**
     * Execute one governed tool request relayed by the Python engine.
     *
     * PHP EXECUTES; it must never re-delegate. The previous shape relayed
     * straight back out to the Python engine, which has no such route, so
     * every governed tool failed with "the Python AI platform rejected the
     * request" and the assistant could never read school data.
     *
     * The recorded operator's permissions are re-resolved here from the
     * database, and AgentToolExecutor applies the workflow's own permission
     * gate, so a subject teacher can never reach finance data even if the
     * engine asks for it.
     *
     * @param array<string,mixed> $payload
     * @return array<string,mixed>
     */
    public function executeToolRequest(PDO $pdo, array $payload): array
    {
        $operator = is_array($payload['operator'] ?? null) ? $payload['operator'] : [];
        $userId = (int) ($operator['user_id'] ?? 0);
        if ($userId < 1) {
            throw new DomainException('A recorded operator is required.', 422);
        }
        $permissions = $this->resolveEffectivePermissions($pdo, $userId);
        if ($permissions === []) {
            throw new DomainException('The recorded operator permissions could not be resolved.', 403);
        }

        return (new AgentToolExecutor())->execute(
            $userId,
            $permissions,
            (string) ($payload['tool'] ?? ''),
            is_array($payload['tool_input'] ?? null) ? $payload['tool_input'] : [],
            (string) ($payload['request_id'] ?? 'agent-tool'),
            $pdo
        );
    }

    /**
     * Execute one background ai.agent.run job through the Python engine.
     *
     * @param array<string,mixed> $payload
     * @return array<string,mixed>
     */
    public function runBackground(PDO $pdo, array $payload): array
    {
        $userId = (int) ($payload['user_id'] ?? 0);
        if ($userId < 1) {
            throw new DomainException('Background agent payload is incomplete.', 422);
        }
        $permissions = $this->resolveEffectivePermissions($pdo, $userId);
        if ($permissions === []) {
            throw new DomainException('The task owner has no currently resolved permissions.', 403);
        }

        return $this->bridge->runJob([
            'user_id' => $userId,
            'permissions' => $permissions,
            'roles' => $payload['roles'] ?? [],
            'role_names' => $payload['role_names'] ?? [],
            'effective_permissions' => $permissions,
            'request_id' => mb_substr((string) ($payload['request_id'] ?? 'ai-agent-run'), 0, 100),
            'audience' => 'staff',
            'route' => (string) ($payload['route'] ?? ''),
            'module' => (string) ($payload['module'] ?? 'dashboard'),
            'mode' => (string) ($payload['mode'] ?? 'digest'),
            'question' => (string) ($payload['question'] ?? ''),
            'cadence' => (string) ($payload['cadence'] ?? 'daily'),
        ], $pdo);
    }

    /** @return list<string> */
    public function resolveEffectivePermissions(PDO $pdo, int $userId): array
    {
        if ($userId < 1) {
            return [];
        }
        try {
            $stmt = $pdo->prepare(
                'SELECT DISTINCT permission_code FROM v_user_permissions_effective
                 WHERE user_id = ? AND permission_code IS NOT NULL AND LENGTH(permission_code) > 0'
            );
            $stmt->execute([$userId]);
            return array_values(array_map('strval', $stmt->fetchAll(PDO::FETCH_COLUMN)));
        } catch (\Throwable $e) {
            return [];
        }
    }

    /**
     * Resolve the operator's active roles for the engine's report authorization.
     *
     * @return list<array{id:int,name:string}>
     */
    public function resolveEffectiveRoles(PDO $pdo, int $userId): array
    {
        if ($userId < 1) {
            return [];
        }
        try {
            $stmt = $pdo->prepare(
                'SELECT r.id, r.name FROM roles r
                 JOIN user_roles ur ON ur.role_id = r.id
                 WHERE ur.user_id = ? AND r.is_active = 1
                 ORDER BY r.id'
            );
            $stmt->execute([$userId]);
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (\Throwable $e) {
            return [];
        }
    }

    /**
     * Retrieve a cached workspace briefing for a user and route.
     * Returns null if no cached briefing exists or is stale.
     *
     * @param int $userId
     * @param string $route
     * @return array<string,mixed>|null
     */
    public function cachedBriefing(int $userId, string $route): ?array
    {
        // The briefing cache is shared via SharedCache service.
        // This method provides a fallback when Python platform is unavailable.
        // In production with Python platform, briefings are generated through
        // the Python engine and cached there. This PHP-side cache is a resilience path.
        try {
            $cache = new SharedCache();
            $cacheKey = self::BRIEFING_CACHE_PREFIX . $userId . ':' . $route;
            $ttl = InsightScheduler::BRIEF_TTL;
            $cached = $cache->get($cacheKey);
            if (is_array($cached) && isset($cached['status']) && $cached['status'] === 'ready') {
                return $cached;
            }
            return null;
        } catch (\Throwable $e) {
            return null;
        }
    }

    /** Cache key prefix for briefings */
    public const BRIEFING_CACHE_PREFIX = 'agent_briefing:';
}