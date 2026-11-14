<?php

declare(strict_types=1);

namespace App\API\Services;

use App\Config\Config;
use RuntimeException;

/**
 * Bridge for allowlisted read-projection refreshes.
 *
 * Python owns the refresh schedule. When enabled, this class provides an
 * internal endpoint that the Python refresh engine calls directly. The engine
 * is the sole writer of reads namespace objects; no PHP fallback exists.
 *
 * If Python is not configured, the projection is assumed to be being
 * rebuilt by another tick of the hourly cron. The read path will serve
 * from the source view via ReadReplicaService::qualifiedRef() when the
 * materialized table is unavailable or stale.
 */
final class ReadProjectionBridge
{
    private const ENDPOINT = '/api/read-models/refresh';

    private InterServiceClient $client;

    public function __construct(?InterServiceClient $client = null)
    {
        $this->client = $client ?? new InterServiceClient();
    }

    public function enabled(): bool
    {
        return filter_var(Config::get('KINGSWAY_PYTHON_READ_MODELS_ENABLED', false), FILTER_VALIDATE_BOOLEAN)
            && $this->client->isConfigured('python_ai')
            && trim((string) Config::get('AI_PYTHON_SECRET', (string) Config::get('AI_API_KEY', ''))) !== '';
    }

    /** @return array<string,mixed> */
    public function refresh(string $projection, int $timeoutSeconds = 900): array
    {
        if (!ReadProjectionSynchronizer::supports($projection)) {
            throw new RuntimeException('Read projection is not allowlisted.');
        }
        if (!$this->enabled()) {
            // Python refresh disabled: let the next cron tick rebuild it.
            // The read path falls back to the source view.
            throw new RuntimeException('Python read-model refresh is not configured.', 503);
        }

        $body = json_encode(['projection' => $projection], JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
        $secret = (string) Config::get('AI_PYTHON_SECRET', (string) Config::get('AI_API_KEY', ''));
        [$raw, $status] = $this->client->post('python_ai', self::ENDPOINT, [
            'Content-Type: application/json',
            'Accept: application/json',
            'Authorization: Bearer ' . $secret,
        ], $body, max(1, min(900, $timeoutSeconds)));

        if ($status === 503) {
            $failure = json_decode((string) $raw, true);
            if (is_array($failure)
                && str_contains(strtolower((string) ($failure['message'] ?? '')), 'already being refreshed')) {
                throw new RuntimeException('Read projection refresh is already in progress.', 409);
            }
        }

        if (!is_string($raw) || $status < 200 || $status >= 300) {
            throw new RuntimeException('Python read-model refresh failed.');
        }
        $decoded = json_decode($raw, true);
        if (!is_array($decoded) || !($decoded['success'] ?? false) || !is_array($decoded['data'] ?? null)) {
            throw new RuntimeException('Python read-model refresh returned an invalid response.');
        }
        $data = $decoded['data'];
        if (($data['projection'] ?? null) !== $projection
            || ($data['target'] ?? null) !== ReadProjectionSynchronizer::targetTable($projection)
            || ($data['status'] ?? null) !== 'published') {
            throw new RuntimeException('Python read-model refresh returned an unexpected result.');
        }
        return $data + ['engine' => 'python'];
    }

    /**
     * Refresh a projection; throws if Python is not available.
     * The scheduler handles this differently - it calls Python directly.
     */
    public function refreshForRead(string $projection): array
    {
        return $this->refresh($projection, 12);
    }
}