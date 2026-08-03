<?php

// ============================================================
// GLOBAL FAILSAFE — must be first, before any require or use
// ============================================================
ini_set('display_errors', 0);
ini_set('log_errors', 1);
error_reporting(E_ALL);

ob_start();

$emitError = function (array $payload): void {
    while (ob_get_level()) {
        ob_end_clean();
    }
    if (!headers_sent()) {
        http_response_code(500);
        header('Content-Type: application/json; charset=utf-8');
    }
    echo json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
};

set_exception_handler(function (\Throwable $e) use ($emitError) {
    error_log('Unhandled exception: ' . $e->getMessage() . ' in ' . $e->getFile() . ':' . $e->getLine());
    $emitError([
        'status'  => 'error',
        'message' => 'An internal error occurred',
        'code'    => 500,
    ]);
});

set_error_handler(function (int $severity, string $message, string $file, int $line): bool {
    if (!(error_reporting() & $severity)) {
        return false;
    }
    throw new \ErrorException($message, 0, $severity, $file, $line);
});

register_shutdown_function(function () use ($emitError) {
    $e = error_get_last();
    if ($e && in_array($e['type'], [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR], true)) {
        error_log('Fatal error: ' . $e['message'] . ' in ' . $e['file'] . ':' . $e['line']);
        $emitError([
            'status'  => 'error',
            'message' => 'An internal error occurred',
            'code'    => 500,
        ]);
    }
});
// ============================================================

use App\API\Router\Router;
use App\Config\Config;

require_once __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/includes/helpers.php';

Config::init();

if (!headers_sent()) {
    header('Content-Type: application/json; charset=utf-8');
    header('X-Content-Type-Options: nosniff');
    header('X-Frame-Options: DENY');
    header('X-XSS-Protection: 1; mode=block');
    header('Referrer-Policy: strict-origin');
    header('Permissions-Policy: camera=(), microphone=(), geolocation=()');
    header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
    header('Pragma: no-cache');
    header('Content-Security-Policy: default-src \'self\'; script-src \'self\' \'unsafe-inline\' https://cdnjs.cloudflare.com; style-src \'self\' \'unsafe-inline\' https://cdnjs.cloudflare.com https://fonts.googleapis.com; font-src \'self\' https://cdnjs.cloudflare.com https://fonts.gstatic.com; img-src \'self\' data: blob: https://placehold.co https://images.unsplash.com; connect-src \'self\'; frame-ancestors \'none\'');
    if (($_ENV['APP_ENV'] ?? 'production') === 'production') {
        header('Strict-Transport-Security: max-age=31536000; includeSubDomains');
    }
    header_remove('X-Powered-By');
}

$router = new Router();
$response = $router->handle();
$response = \App\API\Includes\ApiResponse::normalize(
    is_array($response) ? $response : ['data' => $response]
);
if (!headers_sent() && !$response['success']) {
    http_response_code((int) ($response['code'] ?? 500));
}

ob_end_clean();

$json = json_encode($response, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
if ($json === false) {
    http_response_code(500);
    echo json_encode([
        'status'  => 'error',
        'message' => 'Response serialization failed: ' . json_last_error_msg(),
        'code'    => 500,
    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
} else {
    echo $json;
}
