<?php
/**
 * Shared cache-busted asset helpers for PHP-rendered pages.
 *
 * Local assets are versioned from their modification time so every deployed
 * source change produces a new URL and cannot be hidden by HTTP caches.
 */
if (!function_exists('asset_version')) {
    function asset_version(string $relativePath): string
    {
        $root = defined('APP_BASE_PATH') ? APP_BASE_PATH : dirname(__DIR__);
        $relativePath = ltrim(str_replace('\\', '/', $relativePath), '/');
        $path = $root . '/' . $relativePath;

        return is_file($path) ? (string) filemtime($path) : '0';
    }
}

if (!function_exists('asset_script')) {
    function asset_script(string $appBase, string $path): void
    {
        $url = rtrim($appBase, '/') . '/' . ltrim($path, '/');
        echo '<script src="' . htmlspecialchars($url, ENT_QUOTES, 'UTF-8')
            . '?v=' . htmlspecialchars(asset_version($path), ENT_QUOTES, 'UTF-8')
            . '"></script>' . PHP_EOL;
    }
}
