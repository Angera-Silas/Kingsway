<?php

namespace App\API\Services;

use PDO;
use RuntimeException;

/**
 * JobHandlerRegistry - maps job_type strings to worker handlers.
 *
 * Shared by the CLI cron worker and the Apache-delegated fallback endpoint so
 * both execution paths run identical business logic. Each handler receives the
 * decoded payload and the active PDO connection.
 *
 * A handler for a key MUST be registered here for the corresponding job to be
 * processed. Register real, durable handlers only — never no-op placeholders
 * that claim success without performing work.
 */
class JobHandlerRegistry
{
    /** @var array<string, callable>|null */
    private static $registry;

    /**
     * @return callable|null Handler for the given job type, or null when unknown.
     */
    public static function resolve(string $jobType)
    {
        self::build();
        return self::$registry[$jobType] ?? null;
    }

    /**
     * Build the static handler map once.
     */
    private static function build(): void
    {
        if (self::$registry !== null) {
            return;
        }

        self::$registry = [
            'rebake_realtime_buffer' => static function (array $payload, PDO $pdo): void {
                $scope = isset($payload['scope'])
                    ? EventBroadcaster::normalizeScope((string) $payload['scope'])
                    : EventBroadcaster::DEFAULT_SCOPE;
                EventBroadcaster::rebakeBuffer($pdo, $scope);
            },
            // Housekeeping: drop outbox events older than keep_hours and reset
            // the auto-increment, keeping the sync table small and fast.
            'purge_old_realtime_events' => static function (array $payload, PDO $pdo): void {
                $keepHours = isset($payload['keep_hours']) ? max(1, (int) $payload['keep_hours']) : 24;
                $cutoff = date('Y-m-d H:i:s', time() - ($keepHours * 3600));
                $stmt = $pdo->prepare("DELETE FROM system_realtime_events WHERE created_at < ?");
                $stmt->execute([$cutoff]);
                // ALTER TABLE takes a metadata lock. Only reset the identity
                // when the outbox is actually empty; doing it after every purge
                // would briefly block concurrent event writers at peak time.
                $remaining = (int) $pdo->query(
                    "SELECT COUNT(*) FROM system_realtime_events"
                )->fetchColumn();
                if ($remaining === 0) {
                    $pdo->exec("ALTER TABLE system_realtime_events AUTO_INCREMENT = 1");
                }
            },
            // Extend here with 'generate_report_card' => ..., 'send_bulk_sms' => ...
            // only once the producing workflow pushes and consumes them.
        ];
    }
}
