<?php

declare(strict_types=1);

/**
 * Bounded local resilience evidence for the AI platform.
 *
 * This command creates one uniquely-keyed queue row, verifies duplicate
 * suppression, and cancels only that row. It also verifies offline replay
 * idempotency in an isolated temporary directory. No prompts, credentials,
 * learner data, or provider responses are printed.
 */

require_once dirname(__DIR__, 2) . '/vendor/autoload.php';

use App\API\Services\JobQueue;
use App\API\Services\OfflineReplayBuffer;

$jobIds = [];
$tempDirectory = sys_get_temp_dir() . '/kingsway-ai-resilience-' . bin2hex(random_bytes(6));

$cleanupDirectory = static function (string $directory): void {
    if (!is_dir($directory)) return;
    $iterator = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($directory, FilesystemIterator::SKIP_DOTS),
        RecursiveIteratorIterator::CHILD_FIRST
    );
    foreach ($iterator as $file) {
        $file->isDir() ? @rmdir($file->getPathname()) : @unlink($file->getPathname());
    }
    @rmdir($directory);
};

try {
    $payloadLimitEnforced = false;
    try {
        // JobQueue validates the serialized size before acquiring a database
        // connection, so this oversized sentinel cannot create a queue row.
        JobQueue::push('ai.resilience.payload_probe', ['sentinel' => str_repeat('x', 131073)], 86400, 1, 5);
    } catch (InvalidArgumentException $error) {
        $payloadLimitEnforced = str_contains($error->getMessage(), '128 KB limit');
    }
    if (!$payloadLimitEnforced) {
        throw new RuntimeException('Queue payload limit was not enforced before insertion.');
    }

    $idempotencyKey = 'ai-resilience-' . bin2hex(random_bytes(8));
    $payload = ['idempotency_key' => $idempotencyKey, 'request_id' => 'local-resilience-smoke'];
    $jobIds[] = JobQueue::push('rebuild.read.replica', $payload, 3600, 1, 5);
    $jobIds[] = JobQueue::push('rebuild.read.replica', $payload, 3600, 1, 5);

    if ($jobIds[0] !== $jobIds[1]) {
        throw new RuntimeException('Live queue idempotency suppression failed.');
    }
    $liveConflictRejected = false;
    try {
        JobQueue::push('rebuild.read.replica', [
            'idempotency_key' => $idempotencyKey,
            'request_id' => 'conflicting-local-resilience-smoke',
        ], 3600, 1, 5);
    } catch (RuntimeException $error) {
        $liveConflictRejected = str_contains($error->getMessage(), 'Idempotency key conflicts');
    }
    if (!$liveConflictRejected) {
        throw new RuntimeException('Live queue accepted a conflicting idempotency payload.');
    }

    $buffer = new OfflineReplayBuffer($tempDirectory);
    $first = $buffer->enqueue('ai.scope', 'draft.save', ['status' => 'pending'], 'offline-resilience-1', 3600);
    $second = $buffer->enqueue('ai.scope', 'draft.save', ['status' => 'pending'], 'offline-resilience-1', 3600);
    if ($first !== $second) {
        throw new RuntimeException('Offline duplicate envelope suppression failed.');
    }
    $conflictRejected = false;
    try {
        $buffer->enqueue('ai.scope', 'draft.save', ['status' => 'changed'], 'offline-resilience-1', 3600);
    } catch (RuntimeException) {
        $conflictRejected = true;
    }
    if (!$conflictRejected) throw new RuntimeException('Offline conflicting idempotency payload was not rejected.');

    $calls = 0;
    $replayed = $buffer->replayPending('ai.scope', static function (array $entry) use (&$calls): array {
        $calls++;
        return ['accepted' => true];
    });
    $again = $buffer->replayPending('ai.scope', static function () use (&$calls): void {
        $calls++;
    });
    if (($replayed[0]['status'] ?? '') !== 'replayed' || $calls !== 1 || $again !== []) {
        throw new RuntimeException('Offline replay idempotency verification failed.');
    }

    foreach (array_unique($jobIds) as $jobId) {
        JobQueue::cancelJob((int) $jobId);
        $job = JobQueue::fetchJob((int) $jobId);
        if (($job['status'] ?? '') !== JobQueue::STATUS_CANCELLED) {
            throw new RuntimeException('Live queue probe could not be safely canceled.');
        }
    }
    $jobIds = [];

    echo json_encode([
        'queue_payload_limit_enforced' => $payloadLimitEnforced,
        'live_queue_duplicate_suppressed' => true,
        'live_queue_conflict_rejected' => $liveConflictRejected,
        'offline_duplicate_suppressed' => true,
        'offline_conflict_rejected' => $conflictRejected,
        'offline_replay_calls' => $calls,
    ], JSON_THROW_ON_ERROR) . PHP_EOL;
} catch (Throwable $error) {
    // Database/worker exceptions can contain query text, paths, or school data.
    // Keep command output safe to paste into a support report.
    fwrite(STDERR, json_encode([
        'error' => 'AI resilience smoke failed; details withheld.',
        'error_class' => get_class($error),
    ], JSON_THROW_ON_ERROR) . PHP_EOL);
    exit(1);
} finally {
    if ($jobIds !== []) {
        foreach (array_unique($jobIds) as $jobId) {
            try {
                JobQueue::cancelJob((int) $jobId);
            } catch (Throwable $ignored) {
                // Never hide the primary smoke failure; the ids are uniquely scoped.
            }
        }
    }
    $cleanupDirectory($tempDirectory);
}
