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
 * production servers to 'production'). Development remains permissive for
 * mixed local CLI/web-server users; production uses group-only write access.
 * Each category file rotates when it exceeds the max size.
 */
class FileLogger
{
    /** @var int Maximum size (bytes) before a category file rotates. */
    private const MAX_SIZE = 10485760; // 10 MB

    /** @var bool Compress rotated archives with gzip. */
    private const COMPRESS = true;

    /** @var int Keep this many newest archived files per category. */
    private const MAX_ARCHIVES = 400;

    /** @var int Delete archives older than this many days (retention). */
    private const ARCHIVE_AGE_DAYS = 365;

    /** Maximum encoded bytes accepted for one JSON-lines record. */
    private const MAX_ENTRY_BYTES = 65536;

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
        $configured = getenv('LOG_ROOT_PATH') ?: ($_ENV['LOG_ROOT_PATH'] ?? '');
        $root = $configured !== '' ? $configured : dirname(__DIR__, 2) . '/logs';
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
            @mkdir($dir, self::dirMode(), true);
            @chmod($dir, self::dirMode());
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

        // Defense in depth for legacy modules that still call FileLogger
        // directly instead of the Logger facade.
        if (class_exists(\App\API\Services\Logger::class)) {
            $entry = \App\API\Services\Logger::redactFields($entry);
            if (isset($entry['message']) && is_string($entry['message'])) {
                $entry['message'] = \App\API\Services\Logger::redactText($entry['message']);
            }
        }

        try {
            $writeLockPath = $file . '.write.lock';
            if (is_file($writeLockPath)) @chmod($writeLockPath, self::fileMode());
            $writeLock = @fopen($writeLockPath, 'c');
            if ($writeLock === false || !@flock($writeLock, LOCK_EX)) {
                if (is_resource($writeLock)) fclose($writeLock);
                error_log('FileLogger: unable to acquire write lock for ' . basename($file));
                return;
            }
            @chmod($writeLockPath, self::fileMode());
            self::rotate($file);
            $encoded = json_encode($entry, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_PARTIAL_OUTPUT_ON_ERROR);
            if (!is_string($encoded)) {
                $encoded = '{"level":"error","message":"Log entry encoding failed","context_truncated":true}';
            }
            if (strlen($encoded) > self::MAX_ENTRY_BYTES) {
                $keep = array_flip([
                    'timestamp', 'env', 'level', 'type', 'message', 'request_id',
                    'session_id', 'browser_session_id', 'user_id', 'ip',
                    'method', 'route', 'status', 'success', 'duration_ms',
                    'action', 'entity', 'entity_id', 'event',
                ]);
                $entry = array_intersect_key($entry, $keep);
                $entry['message'] = mb_substr((string) ($entry['message'] ?? 'Oversized log entry'), 0, 4000);
                $entry['context_truncated'] = true;
                $encoded = (string) json_encode($entry, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_PARTIAL_OUTPUT_ON_ERROR);
            }

            $key = self::integrityKey();
            if ($key !== '') {
                $previousHash = self::readIntegrityState($file);
                $entry['previous_hash'] = $previousHash;
                $canonical = (string) json_encode($entry, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_PARTIAL_OUTPUT_ON_ERROR);
                $entry['entry_hash'] = hash_hmac('sha256', $canonical, $key);
                $encoded = (string) json_encode($entry, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_PARTIAL_OUTPUT_ON_ERROR);
            }
            $line = $encoded . "\n";
            $flags = LOCK_EX | FILE_APPEND;
            $bytes = @file_put_contents($file, $line, $flags);
            if ($bytes === false) {
                // Ensure the file is creatable by both daemon and CLI users.
                @chmod($file, self::fileMode());
                error_log('FileLogger: failed to write ' . $file);
                @flock($writeLock, LOCK_UN);
                fclose($writeLock);
                return;
            }
            if (!empty($entry['entry_hash'])) {
                @file_put_contents($file . '.integrity', (string) $entry['entry_hash'], LOCK_EX);
                @chmod($file . '.integrity', self::fileMode());
            }
            @chmod($file, self::fileMode());
            @flock($writeLock, LOCK_UN);
            fclose($writeLock);
        } catch (\Throwable $e) {
            error_log('FileLogger: ' . $e->getMessage());
        }
    }

    private static function integrityKey(): string
    {
        $configured = getenv('LOG_INTEGRITY_KEY') ?: ($_ENV['LOG_INTEGRITY_KEY'] ?? '');
        if ($configured !== '') return (string) $configured;
        return defined('JWT_SECRET') ? (string) JWT_SECRET : '';
    }

    private static function readIntegrityState(string $file): string
    {
        $state = @file_get_contents($file . '.integrity');
        return is_string($state) && preg_match('/^[a-f0-9]{64}$/', trim($state))
            ? trim($state)
            : str_repeat('0', 64);
    }

    /** Verify HMAC signatures and chaining for the current category journal. */
    public static function verifyIntegrity(string $category): array
    {
        $file = self::path($category);
        return self::verifyJournalFile($file, self::sanitizeCategory($category), true);
    }

    /** Verify a live or compressed archive without modifying it. */
    public static function verifyJournalFile(string $file, ?string $label = null, bool $checkState = false): array
    {
        $key = self::integrityKey();
        $result = ['category' => $label ?: basename($file), 'file' => basename($file), 'archive' => str_ends_with($file, '.gz'), 'status' => 'unsealed', 'sealed_entries' => 0, 'legacy_entries' => 0, 'invalid_entries' => 0, 'state_mismatch' => false];
        if ($key === '' || !is_file($file)) return $result;
        $gzip = str_ends_with($file, '.gz');
        $fh = $gzip ? @gzopen($file, 'rb') : @fopen($file, 'rb');
        if ($fh === false) return $result;
        $previousSeen = null;
        while (($line = $gzip ? gzgets($fh, self::MAX_ENTRY_BYTES + 2048) : fgets($fh, self::MAX_ENTRY_BYTES + 2048)) !== false) {
            $entry = json_decode(trim($line), true);
            if (!is_array($entry) || empty($entry['entry_hash']) || empty($entry['previous_hash'])) {
                $result['legacy_entries']++;
                continue;
            }
            $actual = (string) $entry['entry_hash'];
            unset($entry['entry_hash']);
            $canonical = (string) json_encode($entry, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_PARTIAL_OUTPUT_ON_ERROR);
            $expected = hash_hmac('sha256', $canonical, $key);
            if (!hash_equals($expected, $actual) || ($previousSeen !== null && !hash_equals($previousSeen, (string) $entry['previous_hash']))) {
                $result['invalid_entries']++;
            }
            $previousSeen = $actual;
            $result['sealed_entries']++;
        }
        $gzip ? gzclose($fh) : fclose($fh);
        $state = $checkState ? self::readIntegrityState($file) : $previousSeen;
        if ($checkState && $previousSeen !== null && !hash_equals($previousSeen, $state)) {
            $result['invalid_entries']++;
            $result['state_mismatch'] = true;
        }
        $result['status'] = $result['invalid_entries'] > 0 ? 'failed' : ($result['sealed_entries'] > 0 ? 'verified' : 'legacy_unsealed');
        return $result;
    }

    /** Reset cached paths only for isolated development/test processes. */
    public static function resetForTesting(): void
    {
        if (self::environment() !== 'development') throw new \RuntimeException('Logger test reset is development-only.');
        self::$rootDir = null; self::$environment = null; self::$handles = [];
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
     * Rotate a category log file using a modern daily + size policy:
     *  - rotate when the file exceeds MAX_SIZE (size-based), OR
     *  - rotate when the file was last written on a previous day (daily).
     * Rotated files are gzip-compressed (falling back to plain if gz functions
     * are unavailable) and old archives are pruned by retention policy.
     *
     * The category reader globs only `*.log`, so compressed `.log.gz` archives
     * never appear as live categories in the viewer.
     */
    private static function rotate(string $file, bool $force = false): void
    {
        if (!is_file($file)) {
            return;
        }

        $size = @filesize($file);
        $mtime = @filemtime($file);
        $today = (int) strtotime(date('Y-m-d'));
        $mtimeDay = $mtime ? (int) strtotime(date('Y-m-d', $mtime)) : null;

        $sizeOver = ($size !== false) && $size >= self::MAX_SIZE;
        $dayOver = ($mtimeDay !== null) && $mtimeDay < $today;
        if (!$force && !$sizeOver && !$dayOver) {
            return;
        }

        // Serialize rotation between concurrent PHP-FPM/CLI writers.
        $rotationLockPath = $file . '.rotation.lock';
        if (is_file($rotationLockPath)) @chmod($rotationLockPath, self::fileMode());
        $lockHandle = @fopen($rotationLockPath, 'c');
        if ($lockHandle === false || !@flock($lockHandle, LOCK_EX)) {
            if (is_resource($lockHandle)) fclose($lockHandle);
            return;
        }
        @chmod($rotationLockPath, self::fileMode());

        clearstatcache(true, $file);
        $size = @filesize($file);
        $mtime = @filemtime($file);
        $mtimeDay = $mtime ? (int) strtotime(date('Y-m-d', $mtime)) : null;
        $sizeOver = ($size !== false) && $size >= self::MAX_SIZE;
        $dayOver = ($mtimeDay !== null) && $mtimeDay < $today;
        if (!$force && !$sizeOver && !$dayOver) {
            @flock($lockHandle, LOCK_UN);
            fclose($lockHandle);
            return;
        }

        // Atomically move the current file to a timestamped archive name.
        $stamp = date('Y-m-d-His');
        $archive = $file . '.' . $stamp . '-' . getmypid();
        if (!@rename($file, $archive)) {
            @flock($lockHandle, LOCK_UN);
            fclose($lockHandle);
            return;
        }

        // Compress the rotated archive.
        if (self::COMPRESS && function_exists('gzopen') && function_exists('gzwrite')) {
            $gzPath = $archive . '.gz';
            self::gzipArchive($archive, $gzPath);
            @unlink($archive);
        }

        self::prune($file);
        @flock($lockHandle, LOCK_UN);
        fclose($lockHandle);
    }

    /** Close the current category period and retain it as a compressed archive. */
    public static function archiveNow(string $category): bool
    {
        $file = self::path($category);
        if (!is_file($file) || (int) @filesize($file) === 0) return false;
        self::rotate($file, true);
        return !is_file($file);
    }

    /**
     * Compress an archive file to gzip. Logs failures but never throws.
     */
    private static function gzipArchive(string $source, string $gzPath): void
    {
        $in = @fopen($source, 'rb');
        $out = @gzopen($gzPath, 'wb9');
        if ($in === false || $out === false) {
            if (is_resource($in)) {
                fclose($in);
            }
            return;
        }
        while (!feof($in)) {
            $chunk = fread($in, 8192);
            if ($chunk === false || $chunk === '') {
                break;
            }
            gzwrite($out, $chunk);
        }
        gzclose($out);
        fclose($in);
        @chmod($gzPath, self::fileMode());
    }

    /**
     * Enforce the retention policy on a category's archives: delete archives
     * older than ARCHIVE_AGE_DAYS, then keep at most MAX_ARCHIVES newest.
     *
     * @param string $file The (now-rotated) live category path, used to derive
     *                     the archive glob pattern.
     */
    private static function prune(string $file): void
    {
        $pattern = $file . '.*';
        $archives = glob($pattern) ?: [];
        sort($archives); // timestamped names sort chronologically (oldest first)
        $now = time();

        // 1) Age-based retention.
        foreach ($archives as $arch) {
            $mtime = @filemtime($arch);
            $ageDays = $mtime ? (int) floor(($now - $mtime) / 86400) : 0;
            if ($mtime === false || $ageDays > self::retentionDays()) {
                @unlink($arch);
            }
        }

        // 2) Count-based retention: keep only the newest MAX_ARCHIVES.
        $remaining = glob($pattern) ?: [];
        sort($remaining);
        while (count($remaining) > self::maxArchives()) {
            @unlink(array_shift($remaining));
        }
    }

    private static function sanitizeCategory(string $category): string
    {
        return preg_replace('/[^a-z0-9_-]/i', '_', $category) ?: 'app';
    }

    private static function dirMode(): int
    {
        return self::environment() === 'production' ? 0770 : 0777;
    }

    private static function fileMode(): int
    {
        return self::environment() === 'production' ? 0660 : 0666;
    }

    private static function retentionDays(): int
    {
        $value = (int) (getenv('LOG_RETENTION_DAYS') ?: ($_ENV['LOG_RETENTION_DAYS'] ?? self::ARCHIVE_AGE_DAYS));
        return max(30, min(3650, $value));
    }

    private static function maxArchives(): int
    {
        $value = (int) (getenv('LOG_MAX_ARCHIVES') ?: ($_ENV['LOG_MAX_ARCHIVES'] ?? self::MAX_ARCHIVES));
        return max(14, min(5000, $value));
    }

    public static function policy(): array
    {
        return [
            'max_file_bytes' => self::MAX_SIZE,
            'retention_days' => self::retentionDays(),
            'max_archives_per_category' => self::maxArchives(),
            'compression' => self::COMPRESS ? 'gzip' : 'none',
            'integrity' => self::integrityKey() !== '' ? 'hmac-sha256' : 'not_configured',
        ];
    }

    /** Return governed live journals and, optionally, retained archives. */
    public static function journalPaths(string $category = '', bool $includeArchives = false): array
    {
        $dir = self::rootDir() . '/' . self::environment();
        if (!is_dir($dir)) return [];
        $clean = $category !== '' ? self::sanitizeCategory($category) : '*';
        $paths = glob($dir . '/' . $clean . '.log') ?: [];
        if ($includeArchives) {
            foreach (glob($dir . '/' . $clean . '.log.*') ?: [] as $path) {
                if (str_contains(basename($path), '.lock') || str_ends_with($path, '.integrity')) continue;
                if (is_file($path)) $paths[] = $path;
            }
        }
        $paths = array_values(array_unique($paths));
        usort($paths, static fn ($a, $b) => ((int) @filemtime($b)) <=> ((int) @filemtime($a)));
        return $paths;
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
            $lines = self::tailLines($file, max(1, min(5000, $limit * 20)));
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

    /** Read a bounded number of lines from the end without loading the file. */
    private static function tailLines(string $file, int $limit): array
    {
        $fh = @fopen($file, 'rb');
        if ($fh === false) return [];
        $position = @filesize($file);
        if ($position === false || $position === 0) {
            fclose($fh);
            return [];
        }
        $buffer = '';
        $lines = [];
        while ($position > 0 && count($lines) <= $limit) {
            $read = min(8192, $position);
            $position -= $read;
            fseek($fh, $position);
            $buffer = (string) fread($fh, $read) . $buffer;
            $parts = explode("\n", $buffer);
            $buffer = array_shift($parts);
            $lines = array_merge($parts, $lines);
        }
        if ($buffer !== '') array_unshift($lines, $buffer);
        fclose($fh);
        return array_slice(array_values(array_filter($lines, static fn ($line) => trim($line) !== '')), -$limit);
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
