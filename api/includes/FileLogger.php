<?php

namespace App\API\Includes;

/**
 * FileLogger - central file-based logging service.
 *
 * Replaces every database-backed logger (audit_logs, system_events,
 * system_error_logs, term_transition_log, login_attempts, ...). All log
 * output is written to JSON-lines files under <project-root>/logs/<env>/.
 *
 * Environment is resolved once from APP_ENV (dev defaults to 'development',
 * production servers to 'production'). Files are created with world-writable
 * permissions (0666) so both the web server and CLI share them, and each
 * category file rotates when it exceeds the max size.
 */
class FileLogger
{
    /** @var int Maximum size (bytes) before a category file rotates. */
    private const MAX_SIZE = 10485760; // 10 MB

    /** @var string Root log directory (project root /logs). */
    private static $rootDir;

    /** @var string Resolved environment (development|production|staging). */
    private static $environment;

    /** @var array Per-category cached file handles. */
    private static $handles = [];

    /**
     * Resolve the environment from APP_ENV, defaulting to development.
     */
    public static function environment(): string
    {
        if (self::$environment !== null) {
            return self::$environment;
        }
        $env = getenv('APP_ENV');
        if (!$env && isset($_ENV['APP_ENV'])) {
            $env = $_ENV['APP_ENV'];
        }
        $env = $env ? strtolower(trim($env)) : 'development';
        self::$environment = ($env === 'production' || $env === 'staging') ? $env : 'development';
        return self::$environment;
    }

    /**
     * Resolve the root log directory: project root /logs.
     */
    public static function rootDir(): string
    {
        if (self::$rootDir !== null) {
            return self::$rootDir;
        }
        $root = dirname(__DIR__, 2) . '/logs';
        self::$rootDir = rtrim($root, '\/');
        return self::$rootDir;
    }

    /**
     * Absolute path to a category log file, ensuring the directory exists.
     */
    public static function path(string $category): string
    {
        $category = self::sanitizeCategory($category);
        $dir = self::rootDir() . '/' . self::environment();
        if (!is_dir($dir)) {
            @mkdir($dir, 0777, true);
            @chmod($dir, 0777);
        }
        return $dir . '/' . $category . '.log';
    }

    /**
     * Write a structured entry to a category log file.
     *
     * @param string $category  Log category (e.g. 'audit', 'errors', 'events', 'auth').
     * @param array  $data      Associative payload; timestamp and env are added.
     * @param string $level     info|warning|error|critical
     */
    public static function write(string $category, array $data, string $level = 'info'): void
    {
        $file = self::path($category);
        $entry = array_merge([
            'timestamp' => date('Y-m-d H:i:s'),
            'env' => self::environment(),
            'level' => $level,
        ], $data);

        try {
            self::rotateIfNeeded($file);
            $line = json_encode($entry, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) . "\n";
            $flags = LOCK_EX | FILE_APPEND;
            $bytes = @file_put_contents($file, $line, $flags);
            if ($bytes === false) {
                // Ensure the file is creatable by both daemon and CLI users.
                @chmod($file, 0666);
                error_log('FileLogger: failed to write ' . $file);
                return;
            }
            @chmod($file, 0666);
        } catch (\Throwable $e) {
            error_log('FileLogger: ' . $e->getMessage());
        }
    }

    /**
     * Convenience level helpers.
     */
    public static function info(string $category, array $data): void
    {
        self::write($category, $data, 'info');
    }

    public static function warning(string $category, array $data): void
    {
        self::write($category, $data, 'warning');
    }

    public static function error(string $category, array $data): void
    {
        self::write($category, $data, 'error');
    }

    public static function critical(string $category, array $data): void
    {
        self::write($category, $data, 'critical');
    }

    /**
     * Rotate a log file once it exceeds MAX_SIZE, keeping one .old file.
     */
    private static function rotateIfNeeded(string $file): void
    {
        if (!is_file($file)) {
            return;
        }
        if (@filesize($file) < self::MAX_SIZE) {
            return;
        }
        @rename($file, $file . '.old');
    }

    private static function sanitizeCategory(string $category): string
    {
        return preg_replace('/[^a-z0-9_-]/i', '_', $category) ?: 'app';
    }

    /**
     * Read the most recent entries from a category log file.
     *
     * @param string $category Log category.
     * @param int    $limit    Max entries to return (newest last, capped from tail).
     * @param array  $filter   Optional associative equality filter applied to decoded entries.
     * @return array List of decoded associative entries, newest first.
     */
    public static function recent(string $category, int $limit = 100, array $filter = []): array
    {
        $file = self::path($category);
        if (!is_file($file)) {
            return [];
        }

        try {
            $lines = @file($file, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
            if ($lines === false) {
                return [];
            }
            $entries = [];
            foreach ($lines as $line) {
                $decoded = json_decode(trim($line), true);
                if (!is_array($decoded)) {
                    continue;
                }
                if ($filter) {
                    $match = true;
                    foreach ($filter as $key => $value) {
                        if (($decoded[$key] ?? null) != $value) {
                            $match = false;
                            break;
                        }
                    }
                    if (!$match) {
                        continue;
                    }
                }
                $entries[] = $decoded;
            }
            // Keep only the most recent entries.
            $entries = array_slice($entries, -max(1, $limit));
            return array_reverse($entries);
        } catch (\Throwable $e) {
            error_log('FileLogger: read failed for ' . $category . ': ' . $e->getMessage());
            return [];
        }
    }

    /**
     * Close all open file handles (used by long-running CLI processes).
     */
    public static function close(): void
    {
        foreach (self::$handles as $handle) {
            if (is_resource($handle)) {
                fclose($handle);
            }
        }
        self::$handles = [];
    }
}
