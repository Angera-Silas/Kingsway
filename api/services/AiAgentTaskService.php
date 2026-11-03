<?php

declare(strict_types=1);

namespace App\API\Services;

use DomainException;
use PDO;
use Throwable;

/** Durable, user-owned task facade over the canonical PHP JobQueue. */
final class AiAgentTaskService
{
    public const JOB_TYPE = 'ai.agent.task';

    public function create(int $userId, array $permissions, array $data, string $requestId): array
    {
        $question = $this->requestString($data['question'] ?? '', 'question', 500, true, false);
        $tool = $this->requestString($data['tool'] ?? 'assistant', 'tool', 40, true, false);
        $route = $this->requestString($data['route'] ?? 'dashboard', 'route', 120, false);
        $module = $this->requestString($data['module'] ?? 'dashboard', 'module', 60, false);
        $sessionId = $this->requestString($data['session_id'] ?? '', 'session_id', 64, false);
        if ($userId < 1) {
            throw new DomainException('An authenticated operator is required.', 401);
        }
        // This durable endpoint currently executes a routed co-worker request.
        // It does not accept direct tool execution: domain workflows have
        // separate PHP-owned adapters and approval contracts. Reject old UI
        // callers that would otherwise appear to select a tool we ignore.
        if ($tool !== 'assistant') {
            throw new DomainException('This task endpoint accepts general assistant requests only.', 422);
        }

        $permissionHint = AiAgentService::permissionHint($permissions, 60);
        $requestId = mb_substr(trim($requestId), 0, 100);
        $taskFingerprint = hash('sha256', json_encode([
            $question,
            $route,
            $module,
            $sessionId,
        ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?: '');
        $idempotencyKey = 'ai-task:' . hash('sha256', $userId . ':' . ($requestId !== '' ? $requestId : bin2hex(random_bytes(16))) . ':' . $taskFingerprint);
        $payload = [
            'user_id' => $userId,
            'permissions' => $permissionHint['permissions'],
            'mode' => 'assist',
            'task' => true,
            'task_fingerprint' => $taskFingerprint,
            'question' => $question,
            'route' => $route,
            'module' => $module,
            'session_id' => $sessionId,
            'assistant_domain' => in_array('system_admin', $permissions, true) ? 'system_admin' : 'staff',
            'request_id' => $requestId,
            'idempotency_key' => $idempotencyKey,
        ];
        $activeId = JobQueue::findActiveByTypeAndPayload(self::JOB_TYPE, 'idempotency_key', $idempotencyKey);
        if ($activeId !== null) {
            $existing = JobQueue::fetchJob($activeId);
            if ($existing !== null) return $this->publicTask($existing);
        }
        $id = JobQueue::push(self::JOB_TYPE, $payload, 0, 2, 20, JobQueue::PRIORITY_NORMAL);
        return $this->publicTask(JobQueue::fetchJob($id) ?? ['id' => $id, 'status' => JobQueue::STATUS_PENDING, 'payload' => $payload]);
    }

    private function requestString(mixed $value, string $field, int $maxLength, bool $required, bool $truncate = true): string
    {
        if (!is_string($value) && !is_int($value) && !is_float($value)) {
            throw new DomainException('The assistant task contains an invalid ' . $field . '.', 422);
        }
        $value = trim((string) $value);
        if (!mb_check_encoding($value, 'UTF-8')) {
            throw new DomainException('The assistant task contains invalid text.', 422);
        }
        if ($required && $value === '') {
            throw new DomainException('A question of up to 500 characters is required.', 422);
        }
        if (mb_strlen($value) > $maxLength) {
            if (!$truncate) {
                throw new DomainException('The assistant task ' . $field . ' exceeds its safe length.', 422);
            }
            $value = mb_substr($value, 0, $maxLength);
        }
        return $value;
    }

    /** Execute in the existing worker. Permissions are freshly resolved here. */
    public function execute(PDO $pdo, array $payload): void
    {
        $userId = (int) ($payload['user_id'] ?? 0);
        $jobId = (int) ($payload['_job_id'] ?? 0);
        if ($userId < 1 || $jobId < 1 || trim((string) ($payload['question'] ?? '')) === '') {
            throw new DomainException('The assistant task is incomplete.', 422);
        }
        $currentJob = JobQueue::fetchJob($jobId);
        if (is_array($currentJob['payload']['task_result'] ?? null)) {
            // If execution completed but the worker lost its lease before
            // acknowledging the queue row, do not repeat model/tool work.
            return;
        }

        $agent = new AiAgentService();
        $permissions = $agent->resolveEffectivePermissions($pdo, $userId);
        if ($permissions === []) {
            throw new DomainException('The task owner has no currently resolved permissions.', 403);
        }
        $roles = $agent->resolveEffectiveRoles($pdo, $userId);
        $domain = in_array('system_admin', $permissions, true) ? 'system_admin' : 'staff';
        $context = [
            'user_id' => $userId,
            'permissions' => $permissions,
            'effective_permissions' => $permissions,
            'roles' => $roles,
            'audience' => 'staff',
            'assistant_domain' => $domain,
            'system_admin' => $domain === 'system_admin',
            'route' => (string) ($payload['route'] ?? ''),
            'module' => (string) ($payload['module'] ?? 'dashboard'),
            'session_id' => (string) ($payload['session_id'] ?? ''),
            'request_id' => (string) ($payload['request_id'] ?? 'ai-agent-task'),
        ];

        $bridge = new AiPythonBridge();
        $result = $bridge->available()
            ? $bridge->assist($context, (string) $payload['question'])
            : $agent->assist($pdo, $context, (string) $payload['question']);
        $scopeChecks = self::normalizeScopeChecks($result['scope_checks'] ?? []);
        unset($result['scope_checks']);

        JobQueue::updateMeta($jobId, [
            'task_result' => $result,
            'task_access_fingerprint' => self::accessFingerprint($permissions, $roles),
            'task_scope_checks' => $scopeChecks,
        ]);
    }

    public function get(PDO $pdo, int $taskId, int $userId): ?array
    {
        $job = JobQueue::fetchJob($taskId);
        if ($job === null || ($job['job_type'] ?? '') !== self::JOB_TYPE || (int) ($job['payload']['user_id'] ?? 0) !== $userId) {
            return null;
        }
        $access = $this->currentAccessContext($pdo, $userId);
        $scopeCache = [];
        $scopeCurrent = $this->taskScopesCurrent($pdo, $userId, $access['roles'], $job['payload']['task_scope_checks'] ?? [], $scopeCache);
        return $this->publicTask($job, $access['fingerprint'], $scopeCurrent);
    }

    public function list(PDO $pdo, int $userId, int $limit = 30): array
    {
        $access = $this->currentAccessContext($pdo, $userId);
        $scopeCache = [];
        return array_map(function (array $job) use ($pdo, $userId, $access, &$scopeCache): array {
            $scopeCurrent = $this->taskScopesCurrent(
                $pdo,
                $userId,
                $access['roles'],
                $job['payload']['task_scope_checks'] ?? [],
                $scopeCache
            );
            return $this->publicTask($job, $access['fingerprint'], $scopeCurrent);
        }, JobQueue::listOwnedByType(self::JOB_TYPE, $userId, $limit));
    }

    public function cancel(int $taskId, int $userId): bool
    {
        $job = JobQueue::fetchJob($taskId);
        if ($job === null || ($job['job_type'] ?? '') !== self::JOB_TYPE || (int) ($job['payload']['user_id'] ?? 0) !== $userId) return false;
        return JobQueue::cancelJob($taskId);
    }

    public static function accessFingerprint(array $permissions, array $roles): string
    {
        $permissions = array_values(array_unique(array_map('strval', $permissions)));
        sort($permissions, SORT_STRING);
        $roleNames = array_values(array_unique(array_map(static function ($role): string {
            if (is_array($role)) $role = $role['name'] ?? $role['role_name'] ?? $role['label'] ?? '';
            return strtolower(trim((string) $role));
        }, $roles)));
        sort($roleNames, SORT_STRING);
        return hash('sha256', json_encode([$permissions, $roleNames], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?: '[]');
    }

    private function currentAccessContext(PDO $pdo, int $userId): array
    {
        $agent = new AiAgentService();
        $permissions = $agent->resolveEffectivePermissions($pdo, $userId);
        $roles = $agent->resolveEffectiveRoles($pdo, $userId);
        return ['fingerprint' => self::accessFingerprint($permissions, $roles), 'roles' => $roles];
    }

    /** Normalize report provenance before storing it in durable queue metadata. */
    public static function normalizeScopeChecks(mixed $checks): array
    {
        if (!is_array($checks)) return [];
        $normalized = [];
        foreach (array_slice($checks, 0, 4) as $check) {
            if (!is_array($check) || !is_array($check['scope'] ?? null)) continue;
            $scope = $check['scope'];
            $type = strtolower(trim((string) ($scope['type'] ?? '')));
            if (!in_array($type, ['school', 'own', 'class', 'learning_area'], true)) continue;
            $clean = ['type' => $type];
            foreach (['academic_year_id', 'academic_year_term_id', 'class_id', 'stream_id', 'learning_area_id', 'user_id'] as $key) {
                $value = filter_var($scope[$key] ?? null, FILTER_VALIDATE_INT);
                if ($value !== false && $value !== null && $value > 0) $clean[$key] = $value;
            }
            $normalized[] = [
                'report_code' => mb_substr(trim((string) ($check['report_code'] ?? '')), 0, 100),
                'scope' => $clean,
            ];
        }
        return $normalized;
    }

    /** Confirm that an academic report still falls within the user's current assignments. */
    private function taskScopesCurrent(PDO $pdo, int $userId, array $roles, mixed $checks, array &$scopeCache): bool
    {
        foreach (self::normalizeScopeChecks($checks) as $check) {
            $scope = $check['scope'];
            if ($scope['type'] === 'school') continue;
            if ($scope['type'] === 'own') {
                if ((int) ($scope['user_id'] ?? 0) !== $userId) return false;
                continue;
            }
            if (!in_array($scope['type'], ['class', 'learning_area'], true)) return false;
            foreach (['academic_year_id', 'academic_year_term_id', 'class_id', 'stream_id'] as $key) {
                if ((int) ($scope[$key] ?? 0) < 1) return false;
            }
            $cacheKey = (int) $scope['academic_year_id'] . ':' . (int) $scope['academic_year_term_id'];
            if (!array_key_exists($cacheKey, $scopeCache)) {
                try {
                    $scopeCache[$cacheKey] = (new TeacherScopeService($pdo))->forUser(
                        ['user_id' => $userId, 'roles' => $roles],
                        (int) $scope['academic_year_id'],
                        (int) $scope['academic_year_term_id']
                    );
                } catch (Throwable $e) {
                    $scopeCache[$cacheKey] = null;
                }
            }
            if (!is_array($scopeCache[$cacheKey]) || !self::teacherScopeContainsReportScope($scope, $scopeCache[$cacheKey])) {
                return false;
            }
        }
        return true;
    }

    /** Pure membership check, shared by retrieval logic and regression tests. */
    public static function teacherScopeContainsReportScope(array $scope, array $teacherScope): bool
    {
        $classId = (int) ($scope['class_id'] ?? 0);
        $streamId = (int) ($scope['stream_id'] ?? 0);
        $assigned = false;
        foreach ((array) ($teacherScope['class_stream_pairs'] ?? []) as $pair) {
            if (is_array($pair) && (int) ($pair['class_id'] ?? 0) === $classId && (int) ($pair['stream_id'] ?? 0) === $streamId) {
                $assigned = true;
                break;
            }
        }
        if (!$assigned) return false;
        if (($scope['type'] ?? '') === 'learning_area') {
            $areaId = (int) ($scope['learning_area_id'] ?? 0);
            if ($areaId < 1) return false;
            foreach ((array) ($teacherScope['subject_assignments'] ?? []) as $assignment) {
                if (is_array($assignment)
                    && (int) ($assignment['stream_id'] ?? 0) === $streamId
                    && (int) ($assignment['learning_area_id'] ?? 0) === $areaId) return true;
            }
            return false;
        }
        return ($scope['type'] ?? '') === 'class';
    }

    private function publicTask(array $job, ?string $currentAccessFingerprint = null, bool $scopeCurrent = true): array
    {
        $payload = is_array($job['payload'] ?? null) ? $job['payload'] : [];
        $status = match ((string) ($job['status'] ?? 'pending')) {
            JobQueue::STATUS_DONE => 'completed',
            JobQueue::STATUS_FAILED => 'failed',
            JobQueue::STATUS_CANCELLED => 'cancelled',
            JobQueue::STATUS_PROCESSING => 'processing',
            default => 'queued',
        };
        $hasResult = $status === 'completed' && is_array($payload['task_result'] ?? null);
        $scopeEvidenceComplete = !$hasResult
            || !self::requiresReportScopeCheck($payload['task_result'])
            || self::normalizeScopeChecks($payload['task_scope_checks'] ?? []) !== [];
        $accessCurrent = !$hasResult
            || ($currentAccessFingerprint !== null
                && hash_equals((string) ($payload['task_access_fingerprint'] ?? ''), $currentAccessFingerprint)
                && $scopeCurrent
                && $scopeEvidenceComplete);
        return [
            'task_id' => (string) ($job['id'] ?? ''),
            'status' => $status,
            'progress' => $status === 'completed' ? 100 : ($status === 'processing' ? 50 : 0),
            'message' => match ($status) {
                'completed' => $hasResult && !$accessCurrent
                    ? 'Your access changed, or your assignment scope changed while this task was processing. Start a new task to get a current answer.'
                    : 'Completed',
                'failed' => 'The assistant could not complete this task.',
                'cancelled' => 'Cancelled',
                'processing' => 'Working on your request',
                default => 'Queued for the assistant',
            },
            'result' => $hasResult && $accessCurrent ? $payload['task_result'] : null,
            'result_available' => $hasResult ? $accessCurrent : null,
            'error' => $status === 'failed' ? 'The assistant could not complete this task.' : null,
            'created_at' => (string) ($job['created_at'] ?? ''),
            'updated_at' => (string) ($job['updated_at'] ?? ''),
        ];
    }

    private static function requiresReportScopeCheck(array $result): bool
    {
        return array_intersect(
            ['reports.nlq', 'reports.aggregate', 'charts.generate'],
            array_map('strval', (array) ($result['tools_used'] ?? []))
        ) !== [];
    }
}
