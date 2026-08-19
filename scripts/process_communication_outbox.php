<?php

require_once dirname(__DIR__) . '/vendor/autoload.php';

use App\API\Services\CommunicationOutboxService;
use App\Config\Config;
use App\Database\Database;

Config::init();
$limit = isset($argv[1]) ? (int) $argv[1] : 25;

// The distribution CLI PHP may not have pdo_mysql even though Apache/LAMPP
// does. In that case delegate to the authenticated Apache worker endpoint.
if (!in_array('mysql', \PDO::getAvailableDrivers(), true)) {
    $url = rtrim((string) (defined('BASE_URL') ? BASE_URL : 'http://127.0.0.1/Kingsway'), '/') . '/api/communications/process-outbox';
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_POST => true,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => 55,
        CURLOPT_HTTPHEADER => [
            'Content-Type: application/json',
            'X-Kingsway-Worker-Secret: ' . (defined('COMMUNICATION_WORKER_SECRET') ? COMMUNICATION_WORKER_SECRET : ''),
        ],
        CURLOPT_POSTFIELDS => json_encode(['limit' => max(1, min(100, $limit))]),
    ]);
    $response = curl_exec($ch);
    $status = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $error = curl_error($ch);
    curl_close($ch);
    if ($response === false || $status >= 400) {
        fwrite(STDERR, $error ?: ('Worker HTTP request failed with status ' . $status) . PHP_EOL);
        exit(1);
    }
    echo $response . PHP_EOL;
    exit(0);
}

$db = Database::getInstance()->getConnection();
$result = (new CommunicationOutboxService($db))->processPending($limit);
echo json_encode($result, JSON_UNESCAPED_SLASHES) . PHP_EOL;
