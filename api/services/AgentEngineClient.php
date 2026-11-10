<?php

declare(strict_types=1);

namespace App\API\Services;

/**
 * The single PHP -> Python AI delegation seam.
 *
 * Every AI capability (agents, drafts, assistants, planning, web search,
 * provider health, readiness) is reached through this one surface so PHP
 * contains no provider, prompt, policy or guard logic of its own. The
 * interface (not the final implementation) is the injection point, which
 * keeps the transport mockable in tests without un-finalizing the bridge.
 */
interface AgentEngineClient
{
    /** Is the Python AI platform configured and reachable in this environment? */
    public function available(): bool;

    /**
     * Relay one governed request to a Python endpoint.
     *
     * @param array<string,mixed> $payload
     * @return array<string,mixed>
     */
    public function call(string $endpoint, array $payload, string $method = 'POST', array $query = [], ?int $timeout = null): array;

    /**
     * Full governed agent run for an already-authorized operator.
     *
     * @param array<string,mixed> $context
     * @return array<string,mixed>
     */
    public function assist(array $context, string $question): array;

    /**
     * Deterministic personal digest for one operator.
     *
     * @param array<int,int|string> $permissions
     * @return array<string,mixed>
     */
    public function digest(int $userId, array $permissions, string $cadence = 'daily', string $requestId = 'ai-engine', bool $broadcast = false): array;

    /**
     * Execute one background ai.agent.run job through the engine.
     *
     * @param array<string,mixed> $payload
     * @return array<string,mixed>
     */
    public function runJob(array $payload, \PDO $pdo): array;
}
