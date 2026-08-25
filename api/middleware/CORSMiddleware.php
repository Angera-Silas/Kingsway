<?php

namespace App\API\Middleware;

class CORSMiddleware
{
    public static function handle()
    {
        $origin = $_SERVER['HTTP_ORIGIN'] ?? '';
        $isProduction = (($_ENV['APP_ENV'] ?? 'production') === 'production');

        $allowedOrigins = defined('ALLOWED_ORIGINS') ? ALLOWED_ORIGINS : [];
        if (empty($allowedOrigins)) {
            if ($isProduction) {
                $allowedOrigins = [
                    'https://kingswaypreparatoryschool.sc.ke',
                ];
            } else {
                $allowedOrigins = [
                    'http://localhost',
                    'http://127.0.0.1',
                    'http://localhost:8080',
                    'http://127.0.0.1:8080',
                    'https://localhost',
                    'https://127.0.0.1',
                    'https://localhost:8080',
                    'https://127.0.0.1:8080',
                    // ngrok tunnels used for external demos/testing
                    'https://privately-amazing-glider.ngrok-free.app',
                ];
            }
        }

        if (in_array($origin, $allowedOrigins)) {
            header("Access-Control-Allow-Origin: $origin");
            header("Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS");
            header("Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With, X-CSRF-Token");
            header("Access-Control-Allow-Credentials: true");
            header("Access-Control-Max-Age: 86400");
        }

        if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
            http_response_code(204);
            exit;
        }
    }
}
