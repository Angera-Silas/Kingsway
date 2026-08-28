<?php

namespace App\API\Services;

use App\Database\Database;
use PDO;

/**
 * JobQueue - Laravel-like background job dispatcher for shared hosting.
 *
 * Writers push jobs onto the jobs_queue table with status 'pending'. A cron
 * worker (scripts/cron/worker.php, run every minute via HostAfrica's
 * DirectAdmin cron) claims, processes and finalises them, keeping heavy work
 * (report-card/PDF generation, bulk SMS, provider callbacks) out of the
 * request path.
 *
 * The worker claims jobs with a single UPDATE ... WHERE status='pending' so
 * concurrent / overlapping cron invocations on shared hosting cannot claim the
 * same row twice.
 */
class JobQueue
{
    public const STATUS_PENDING = 'pending';
    public const STATUS_PROCESSING = 'processing';
    public const STATUS_DONE = 'done';
    public const STATUS_FAILED = 'failed';
    public const STATUS_CANCELLED = 'cancelled';

    /**
     * Enqueue a background job.
     *
     * @param string $jobType Stable job type key consumed by the worker switch.
     * @param array  $payload Arbitrary worker payload.
     * @param int    $delaySeconds Optional delay before the job is available.
     * @return int New job id.
     */
    public static function push(PDO $pdo, string $jobType, array $payload = [], int $delaySeconds = 0): int
    {
        if (!preg_match('/^[a-z][a-z0-9_.-]{2,99}$/', $jobType)) {
            throw new \InvalidArgumentException('Invalid background job type.');
        }
        $availableAt = date('Y-m-d H:i:s', time() + max(0, $delaySeconds));
        $encodedPayload = json_encode(
            $payload,
            JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR
        );
        $stmt = $pdo->prepare(
            "INSERT INTO jobs_queue (job_type, payload, status, available_at) VALUES (?, ?, ?, ?)"
        );
        $stmt->execute([
            $jobType,
            $encodedPayload,
            self::STATUS_PENDING,
            $availableAt,
        ]);
        return (int) $pdo->lastInsertId();
    }

    /**
     * Atomically claim a batch of pending jobs for processing.
     *
     * Each row is transitioned pending -> processing with a guarded UPDATE so
     * concurrent or overlapping cron workers cannot process the same job twice.
     *
     * @return int[] Claimed (now processing) job ids.
     */
    public static function claimBatch(PDO $pdo, int $limit = 10): array
    {
        $ids = [];
        // LIMIT cannot be bound as a parameter in MySQL/MariaDB (it must be a
        // literal), and $limit has already been cast to int so it is safe to
        // inline. The value is re-cast and clamped here as a defensive measure.
        $limit = max(1, min(500, $limit));
        // Atomic claim per row so concurrent workers never double-process.
        $stmt = $pdo->prepare(
            "SELECT id FROM jobs_queue
             WHERE status = ? AND available_at <= NOW()
             ORDER BY id ASC
             LIMIT {$limit}"
        );
        $stmt->execute([self::STATUS_PENDING]);
        $rows = $stmt->fetchAll(PDO::FETCH_COLUMN);

        foreach ($rows as $id) {
            $claim = $pdo->prepare(
                "UPDATE jobs_queue
                 SET status = ?, updated_at = NOW()
                 WHERE id = ? AND status = ?"
            );
            $claim->execute([self::STATUS_PROCESSING, $id, self::STATUS_PENDING]);
            if ($claim->rowCount() === 1) {
                $ids[] = (int) $id;
            }
        }
        return $ids;
    }

    /**
     * Recover jobs abandoned by a worker crash or hosting timeout.
     *
     * Normal jobs are expected to finish within the worker's 55-second HTTP
     * ceiling. A 15-minute lease therefore leaves ample room for slow work
     * while preventing rows from remaining in `processing` forever.
     */
    public static function recoverStale(PDO $pdo, int $leaseMinutes = 15, int $maxAttempts = 3): int
    {
        $leaseMinutes = max(5, min(1440, $leaseMinutes));
        $maxAttempts = max(1, min(20, $maxAttempts));
        $stmt = $pdo->prepare(
            "UPDATE jobs_queue
             SET attempts = attempts + 1,
                 status = CASE WHEN attempts + 1 >= ? THEN ? ELSE ? END,
                 available_at = NOW(),
                 failed_reason = CASE
                     WHEN attempts + 1 >= ? THEN 'Worker lease expired too many times'
                     ELSE 'Recovered after worker lease expired'
                 END,
                 updated_at = NOW()
             WHERE status = ?
               AND updated_at < NOW() - INTERVAL {$leaseMinutes} MINUTE"
        );
        $stmt->execute([
            $maxAttempts,
            self::STATUS_FAILED,
            self::STATUS_PENDING,
            $maxAttempts,
            self::STATUS_PROCESSING,
        ]);
        return $stmt->rowCount();
    }

    /**
     * Mark a job as completed.
     */
    public static function markDone(PDO $pdo, int $id): void
    {
        $stmt = $pdo->prepare(
            "UPDATE jobs_queue SET status = ?, failed_reason = NULL, updated_at = NOW() WHERE id = ?"
        );
        $stmt->execute([self::STATUS_DONE, $id]);
    }

    public static function markFailed(PDO $pdo, int $id, string $reason = '', int $maxAttempts = 3): string
    {
        $maxAttempts = max(1, min(20, $maxAttempts));
        $retryDelay = 60;
        $stmt = $pdo->prepare(
            "UPDATE jobs_queue
             SET status = CASE WHEN attempts + 1 >= ? THEN ? ELSE ? END,
                 attempts = attempts + 1,
                 available_at = CASE
                     WHEN attempts + 1 >= ? THEN available_at
                     ELSE DATE_ADD(NOW(), INTERVAL {$retryDelay} SECOND)
                 END,
                 failed_reason = ?, updated_at = NOW()
             WHERE id = ? AND status = ?"
        );
        $stmt->execute([
            $maxAttempts,
            self::STATUS_FAILED,
            self::STATUS_PENDING,
            $maxAttempts,
            self::truncate($reason),
            $id,
            self::STATUS_PROCESSING,
        ]);
        $status = $pdo->prepare('SELECT status FROM jobs_queue WHERE id = ?');
        $status->execute([$id]);
        return (string) ($status->fetchColumn() ?: self::STATUS_FAILED);
    }

    /**
     * Safely truncate a free-text reason to the DB column width.
     */
    private static function truncate(string $text): string
    {
        return mb_strlen($text) > 490 ? mb_substr($text, 0, 490) : $text;
    }
}
