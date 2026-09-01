<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/vendor/autoload.php';

use App\API\Services\payments\KcbTransferReconciliationService;
use App\API\Services\Logger;
use App\Config\Config;
use App\Database\Database;

Config::init();
$limit = isset($argv[1]) ? max(1, min(100, (int) $argv[1])) : 25;

$recordResult = static function (array $result, string $mode): void {
    $counts = [];
    foreach (['selected', 'completed', 'failed', 'pending', 'exceptions', 'errors'] as $key) {
        $counts[$key] = (int) ($result[$key] ?? 0);
    }
    Logger::event('kcb_reconciliation_worker', 'KCB reconciliation cycle completed', [
        'worker_mode' => $mode,
        'counts' => $counts,
    ]);
};

// Production CLI PHP may not have pdo_mysql. Delegate to Apache in that case,
// using a dedicated worker secret rather than a staff session.
if (!in_array('mysql', \PDO::getAvailableDrivers(), true)) {
    $url = rtrim((string) (defined('BASE_URL') ? BASE_URL : 'http://127.0.0.1/Kingsway'), '/')
        . '/api/finance/kcb-reconciliation-worker';
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_POST => true,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_CONNECTTIMEOUT => 10,
        CURLOPT_TIMEOUT => 55,
        CURLOPT_HTTPHEADER => [
            'Content-Type: application/json',
            'X-Kingsway-Worker-Secret: ' . (defined('KCB_RECONCILIATION_WORKER_SECRET') ? KCB_RECONCILIATION_WORKER_SECRET : ''),
        ],
        CURLOPT_POSTFIELDS => json_encode(['limit' => $limit]),
    ]);
    $response = curl_exec($ch);
    $status = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $error = curl_error($ch);
    curl_close($ch);
    if ($response === false || $status >= 400) {
        Logger::error('finance', 'KCB reconciliation worker request failed', [
            'status' => $status,
            'error' => $error ?: 'Remote worker request failed',
        ]);
        exit(1);
    }
    $decoded = json_decode((string) $response, true);
    $payload = is_array($decoded) ? ($decoded['data'] ?? $decoded) : [];
    $recordResult(is_array($payload) ? $payload : [], 'http_delegate');
    exit(0);
}

$result = (new KcbTransferReconciliationService(Database::getInstance()->getConnection()))->pollDue($limit);
$recordResult(is_array($result) ? $result : [], 'direct_database');
