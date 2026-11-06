<?php

namespace App\API\Middleware;

use PDO;

class RateLimitMiddleware
{
    // Anonymous traffic remains conservative, authenticated users get a larger
    // per-user bucket so normal multi-tab work does not collide on one IP.
    const ANONYMOUS_REQUESTS_LIMIT = 120;
    const AUTHENTICATED_REQUESTS_LIMIT = 600;
    const REFRESH_REQUESTS_LIMIT = 60;
    const LOGIN_REQUESTS_LIMIT = 60;
    const TELEMETRY_REQUESTS_LIMIT = 60;
    // Public AI is provider-backed and anonymous; keep its cost/abuse bucket
    // separate from ordinary public website traffic.
    const PUBLIC_AI_REQUESTS_LIMIT = 20;
    // Handoff leads are low-volume but write to the DB and send email.
    const PUBLIC_LEADS_REQUESTS_LIMIT = 5;
    const TIME_WINDOW = 60; // seconds

    // Login endpoints that get a stricter rate limit.
    const LOGIN_PATHS = [
        '/auth/login',
        '/users/login',
    ];

    // Each call to these can send a PAID SMS/WhatsApp message, so they get
    // their own tight bucket rather than the general authenticated default.
    const VERIFICATION_PATHS = [
        '/twofactor/challenge',
        '/twofactor/verify',
    ];
    const VERIFICATION_REQUESTS_LIMIT = 10;

    /**
     * Check rate limiting by authenticated user when a bearer token is present,
     * otherwise by IP address. This middleware intentionally does not authorize
     * the JWT; the decoded payload is only used to choose a fair rate bucket.
     */
    public static function handle()
    {
        $ipAddress = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
        $rateKey = self::resolveRateKey($ipAddress);
        $limit = self::resolveLimit($rateKey);
        $now = time();

        // Counters are JSON-lines files under logs/<env>/ratelimit, matching
        // Hard Rule 2 (no database log tables). The previous rate_limit_logs
        // table never existed, so this middleware silently failed open for
        // every request; the file limiter is always available and always
        // counts, so rotating IPs does not bypass it.
        $remaining = \App\API\Services\FileRateLimiter::consume(
            $rateKey,
            $limit,
            self::TIME_WINDOW
        );
        if ($remaining < 0) {
            self::deny(429, 'Too many requests. Rate limit exceeded.');
        }
    }

    private static function resolveLimit($rateKey)
    {
        if (self::isTelemetryEndpoint()) {
            return self::TELEMETRY_REQUESTS_LIMIT;
        }
        if (self::isPublicAiEndpoint()) {
            return self::PUBLIC_AI_REQUESTS_LIMIT;
        }
        if (self::isPublicLeadsEndpoint()) {
            return self::PUBLIC_LEADS_REQUESTS_LIMIT;
        }
        if (self::isRefreshEndpoint()) {
            return self::REFRESH_REQUESTS_LIMIT;
        }

        if (self::isLoginEndpoint()) {
            return self::LOGIN_REQUESTS_LIMIT;
        }

        if (self::isVerificationEndpoint()) {
            return self::VERIFICATION_REQUESTS_LIMIT;
        }

        return strpos($rateKey, 'user:') === 0
            ? self::AUTHENTICATED_REQUESTS_LIMIT
            : self::ANONYMOUS_REQUESTS_LIMIT;
    }

    private static function isVerificationEndpoint(): bool
    {
        $path = strtolower((string) parse_url($_SERVER['REQUEST_URI'] ?? '', PHP_URL_PATH));
        foreach (self::VERIFICATION_PATHS as $verificationPath) {
            if (strpos($path, $verificationPath) !== false) return true;
        }
        return false;
    }

    private static function isTelemetryEndpoint(): bool
    {
        $path = strtolower((string) parse_url($_SERVER['REQUEST_URI'] ?? '', PHP_URL_PATH));
        return strpos($path, '/system/client-log') !== false
            || strpos($path, '/telemetry/data') !== false
            || strpos($path, '/telemetry/errors') !== false;
    }

    private static function isPublicAiEndpoint(): bool
    {
        $path = strtolower((string) parse_url($_SERVER['REQUEST_URI'] ?? '', PHP_URL_PATH));
        return strpos($path, '/public/ai-faq') !== false;
    }

    private static function isPublicLeadsEndpoint(): bool
    {
        $path = strtolower((string) parse_url($_SERVER['REQUEST_URI'] ?? '', PHP_URL_PATH));
        return strpos($path, '/public/leads') !== false;
    }

    private static function isLoginEndpoint()
    {
        $path = strtolower((string) parse_url($_SERVER['REQUEST_URI'] ?? '', PHP_URL_PATH));
        foreach (self::LOGIN_PATHS as $loginPath) {
            if (strpos($path, $loginPath) !== false) {
                return true;
            }
        }
        return false;
    }

    private static function resolveRateKey($ipAddress)
    {
        $userId = self::bearerUserId();
        if ($userId > 0) {
            return 'user:' . $userId;
        }

        return 'ip:' . $ipAddress;
    }

    private static function bearerUserId()
    {
        $header = $_SERVER['HTTP_AUTHORIZATION']
            ?? $_SERVER['REDIRECT_HTTP_AUTHORIZATION']
            ?? '';

        if (!preg_match('/Bearer\s+(.+)/i', $header, $matches)) {
            return 0;
        }

        $parts = explode('.', trim($matches[1]));
        if (count($parts) !== 3) {
            return 0;
        }

        $payload = self::base64UrlDecode($parts[1]);
        if ($payload === false) {
            return 0;
        }

        $claims = json_decode($payload, true);
        if (!is_array($claims)) {
            return 0;
        }

        foreach (['user_id', 'id', 'sub'] as $key) {
            if (isset($claims[$key]) && is_numeric($claims[$key])) {
                return (int) $claims[$key];
            }
        }

        return 0;
    }

    private static function base64UrlDecode($value)
    {
        $remainder = strlen($value) % 4;
        if ($remainder > 0) {
            $value .= str_repeat('=', 4 - $remainder);
        }

        return base64_decode(strtr($value, '-_', '+/'));
    }

    private static function isRefreshEndpoint()
    {
        $path = strtolower((string) parse_url($_SERVER['REQUEST_URI'] ?? '', PHP_URL_PATH));
        return strpos($path, '/auth/refresh-token') !== false
            || strpos($path, '/auth/refresh-session') !== false;
    }

    /**
     * Deny request and exit with error response
     */
    private static function deny($code, $message)
    {
        \App\API\Includes\SecurityEventNotifier::securityIncident(
            $message,
            ['entity' => 'rate_limit', 'details' => ['status_code' => $code]]
        );
        http_response_code($code);
        header('Retry-After: ' . self::TIME_WINDOW);
        if (!headers_sent()) {
            header('Content-Type: application/json; charset=utf-8');
        }
        $payload = json_encode([
            'status'      => 'error',
            'message'     => $message,
            'code'        => $code,
            'retry_after' => self::TIME_WINDOW,
        ]);
        echo $payload !== false
            ? $payload
            : '{"status":"error","message":"Internal error","code":500}';
        exit;
    }
}
