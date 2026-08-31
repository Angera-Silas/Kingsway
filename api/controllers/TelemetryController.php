<?php

namespace App\API\Controllers;

use App\API\Includes\BaseAPI;
use App\API\Services\Logger;

/**
 * Telemetry Controller
 *
 * Receives client-side error reports and performance/usage telemetry posted by
 * ErrorReporter (js/core/error_reporter.js). The browser batches these and sends
 * them fire-and-forget; this endpoint must acknowledge quickly and never 401
 * (it is registered as a public endpoint in AuthMiddleware so a mid-refresh
 * token can't trap the reporter in a retry loop).
 *
 * Entries are redacted and sent through the governed central logger. This
 * endpoint requires an authenticated user; anonymous telemetry is rejected by
 * AuthMiddleware to prevent log injection and disk exhaustion.
 */
class TelemetryController extends BaseAPI
{
    public function __construct()
    {
        parent::__construct('telemetry');
    }

    /**
     * POST /api/telemetry/data
     */
    public function postData($id = null, $data = [])
    {
        return $this->ingest('telemetry', $data);
    }

    /**
     * POST /api/telemetry/errors
     */
    public function postErrors($id = null, $data = [])
    {
        return $this->ingest('error', $data);
    }

    /**
     * Normalize and append the batch to the telemetry log file.
     * Failures are swallowed: a broken telemetry sink must never surface an
     * error to the client or trigger retry storms.
     */
    private function ingest(string $kind, $payload): array
    {
        try {
            $entries = $payload['telemetry'] ?? $payload['errors'] ?? $payload;
            if (!is_array($entries)) {
                $entries = [$entries];
            }
            $entries = array_slice($entries, 0, 25);
            Logger::log('client', $kind === 'error' ? 'error' : 'info', 'Browser telemetry batch received', [
                'telemetry_kind' => $kind,
                'entry_count' => count($entries),
                'entries' => Logger::redactFields($entries),
            ]);
        } catch (\Throwable $e) {
            // Swallow — telemetry must never fail loud.
            \App\API\Services\Logger::legacyError('TelemetryController ingest failed: ' . $e->getMessage());
        }

        return [
            'success' => true,
            'status'  => 'success',
            'data'    => null,
            'message' => 'Telemetry received',
            'errors'  => [],
            'code'    => 200,
        ];
    }
}
