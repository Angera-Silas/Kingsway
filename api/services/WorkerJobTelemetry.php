<?php

declare(strict_types=1);

namespace App\API\Services;

use App\API\Includes\FileLogger;
use Throwable;

/**
 * WorkerJobTelemetry - browser-visible completion signals for jobs a
 * specific staff member is waiting on.
 *
 * The minute-worker drains every job family (outbox, reminders, projections,
 * AI drafts, briefings, agent runs). Most of that work has no human watching;
 * toasting every completion to every connected staff browser would be pure
 * noise. The one family a human IS watching is AI generation: the staff
 * member who queued a draft/review/briefing sits on a panel polling for it.
 *
 * So this service announces completions ONLY for the AI job families, and
 * ONLY to the requesting operator's own `users:<id>` channel (minted on every
 * capability by RealtimeScopeResolver::scopesForUser). The requesting
 * browser's AI panel wakes immediately; every other browser stays quiet.
 *
 * Descriptor-only (job_id/status/domain/ai targets); fire-and-forget through
 * RealtimeGatewayPublisher, so a realtime outage never slows a job.
 */
final class WorkerJobTelemetry
{
    /** Job families a human actively waits on. Nothing else is announced. */
    private const ANNOUNCED_TYPE_PREFIXES = ['ai.'];
    private const ANNOUNCED_TYPES = ['curriculum.policy_interpret'];

    /**
     * Announce a job outcome to the operator who queued it.
     *
     * @param array<string, mixed> $job    Row as returned by JobQueue::fetchJob
     *                                      (job_type, payload, id).
     * @param string               $outcome 'completed'|'failed' (retry outcomes
     *                                      are silent: the job is still running).
     * @return bool True when an announcement was published.
     */
    public static function announceCompletion(array $job, string $outcome): bool
    {
        $type = (string) ($job['job_type'] ?? '');
        if (!self::isAnnounced($type)) {
            return false;
        }

        $payload = is_array($job['payload'] ?? null) ? $job['payload'] : [];
        $operatorId = (int) ($payload['user_id'] ?? $payload['operator_id'] ?? 0);
        if ($operatorId < 1) {
            // Scheduled/system-run jobs (no single requesting human) have no
            // per-user audience; announcing them to 'all' would be noise.
            return false;
        }

        $event = $outcome === 'completed' ? 'JOB_COMPLETE' : 'JOB_FAILED';
        return RealtimeGatewayPublisher::publish($event, 'users:' . $operatorId, [
            'job_id' => (int) ($job['id'] ?? 0),
            'status' => $outcome,
            'domain' => 'ai',
            'targets' => ['ai'],
        ]);
    }

    /** Write identity-minimized lifecycle evidence for governed AI queue work. */
    public static function recordLifecycle(array $job, string $status, ?Throwable $failure = null): void
    {
        $jobType = (string) ($job['job_type'] ?? '');
        if (!self::isAnnounced($jobType)) return;

        $payload = is_array($job['payload'] ?? null) ? $job['payload'] : [];
        $requestId = trim((string) ($payload['request_id'] ?? ''));
        $failureMessage = $failure === null ? '' : \RuntimeException::class;
        FileLogger::write('jobs', [
            'type' => 'ai_job_transition',
            'job_id' => (int) ($job['id'] ?? 0),
            'job_type' => $jobType,
            'status' => mb_substr($status, 0, 24),
            'attempt' => max(0, (int) ($job['attempts'] ?? 0)),
            'operator_id' => max(0, (int) ($payload['user_id'] ?? $payload['operator_id'] ?? 0)),
            'workflow_id' => mb_substr((string) ($payload['workflow_id'] ?? ''), 0, 80),
            'request_fingerprint' => $requestId === '' ? '' : self::fingerprint($requestId),
            'failure_class' => $failure === null ? '' : get_class($failure),
            'failure_fingerprint' => $failureMessage === '' ? '' : self::fingerprint($failureMessage),
        ]);
    }

    /** URL-safe digest avoids the logger's long-hex secret redaction pattern. */
    private static function fingerprint(string $value): string
    {
        return rtrim(strtr(base64_encode(hash('sha256', $value, true)), '+/', '-_'), '=');
    }

    private static function isAnnounced(string $type): bool
    {
        foreach (self::ANNOUNCED_TYPES as $known) {
            if ($type === $known) {
                return true;
            }
        }
        foreach (self::ANNOUNCED_TYPE_PREFIXES as $prefix) {
            if (str_starts_with($type, $prefix)) {
                return true;
            }
        }
        return false;
    }
}
