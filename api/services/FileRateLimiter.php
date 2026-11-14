<?php
namespace App\API\Services;

use App\API\Includes\FileLogger;

/**
 * File-based rate limiter.
 *
 * Rate limiting is runtime state, not an application log, so it deliberately
 * does NOT use a database table (Hard Rule 2: logs are JSON-lines files, and
 * audit/log tables must not be reintroduced). It writes the same way the
 * journals do — flock the bucket file, append one JSON line, release — so
 * concurrent lsphp workers behind the load balancer cannot lose a count.
 *
 * Buckets are one file per minute, so counting a window only reads
 * ceil(window / 60) + 1 small files instead of an ever-growing log.
 */
final class FileRateLimiter
{
    private const DIR_NAME = 'ratelimit';

    /** @var string|null Resolved directory, cached per request. */
    private static $dir = null;

    private static function directory(): ?string
    {
        if (self::$dir !== null) return self::$dir ?: null;

        $root = dirname(__DIR__, 2) . '/logs/' . FileLogger::environment() . '/' . self::DIR_NAME;
        if (!is_dir($root)) {
            if (!@mkdir($root, 02770, true) && !is_dir($root)) {
                self::$dir = '';
                FileLogger::warning('security', ['action' => 'rate_limiter_dir_unavailable', 'path' => self::DIR_NAME]);
                return null;
            }
        }
        // The web server and the CLI run as different users (www-data vs the
        // deploy user). When the runtime user cannot write here the limiter
        // silently fails open and the protection disappears, so the bucket
        // directory is kept writable for whoever serves the request. The
        // directory holds counters only - no credentials or payloads.
        if (is_dir($root) && !is_writable($root)) {
            @chmod($root, 02770);
        }
        self::$dir = $root;
        return $root;
    }

    private static function bucketPath(int $timestamp): ?string
    {
        $dir = self::directory();
        if ($dir === null) return null;
        return $dir . '/' . gmdate('YmdHi', $timestamp) . '.jsonl';
    }

    /** Record one attempt for $key. Fails open (returns true) if unusable. */
    public static function hit(string $key, ?int $now = null): bool
    {
        $now = $now ?: time();
        $path = self::bucketPath($now);
        if ($path === null) return true;

        $line = json_encode([
            't' => $now,
            'k' => $key,
        ], JSON_UNESCAPED_SLASHES) . "\n";

        $written = @file_put_contents($path, $line, LOCK_EX | FILE_APPEND);
        if ($written === false) {
            @chmod($path, 0660);
            FileLogger::warning('security', ['action' => 'rate_limiter_write_failed', 'file' => basename($path)]);
            return true; // fail open: never block a sign-in on a logging fault
        }
        @chmod($path, 0660);
        return true;
    }

    /** Count attempts recorded for $key within the trailing $window seconds. */
    public static function attempts(string $key, int $window, ?int $now = null): int
    {
        $dir = self::directory();
        if ($dir === null) return 0;

        $now = $now ?: time();
        $count = 0;
        // Only the minute buckets that can still hold in-window entries.
        for ($offset = 0; $offset <= intdiv($window, 60); $offset++) {
            $path = $dir . '/' . gmdate('YmdHi', $now - ($offset * 60)) . '.jsonl';
            if (!is_file($path)) continue;
            $handle = @fopen($path, 'r');
            if ($handle === false) continue;
            @flock($handle, LOCK_SH);
            while (($line = fgets($handle)) !== false) {
                if (strpos($line, '"k":"' . $key . '"') === false) continue;
                $entry = json_decode($line, true);
                if (!is_array($entry)) continue;
                $at = (int) ($entry['t'] ?? 0);
                if ($at > 0 && $at >= ($now - $window)) $count++;
            }
            @flock($handle, LOCK_UN);
            fclose($handle);
        }
        return $count;
    }

    /**
     * Per-key mutex so the read-then-write is atomic.
     *
     * A plain FILE_APPEND is atomic per LINE, not per read-modify-write: two
     * concurrent requests could both read a count of 9, both append, and both
     * pass. Locking per key makes consume() correct. The lock is per key, so
     * two different IPs never contend; only one request per key serialises.
     */
    /** @return resource|null Open lock handle, or null when unavailable. */
    private static function keyLock(string $key)
    {
        $dir = self::directory();
        if ($dir === null) return null;
        $path = $dir . '/.lock_' . substr(preg_replace('/[^a-z0-9]/', '', strtolower($key)) !== ''
            ? preg_replace('/[^a-z0-9]/', '', strtolower($key))
            : 'key', 0, 48);
        if (strlen($path) > 200) $path = substr($path, 0, 200);
        $handle = @fopen($path, 'c');
        if ($handle === false) return null;
        // The lock file may have been created by the CLI user while the web
        // server serves the request; keep it writable for both so the mutex
        // never degrades into a permanent fail-open.
        @chmod($path, 0660);
        if (!@flock($handle, LOCK_EX)) {
            fclose($handle);
            return null;
        }
        return $handle;
    }

    /**
     * Record and test in one call.
     *
     * @return int Remaining attempts after this one, or -1 when over the limit
     *              (the returned count includes the current attempt).
     */
    public static function consume(string $key, int $limit, int $window, ?int $now = null): int
    {
        $now = $now ?: time();
        $lock = self::keyLock($key);
        if ($lock === null) {
            // No lock available: fail open rather than block a real sign-in.
            return max(0, $limit);
        }
        self::hit($key, $now);
        $used = self::attempts($key, $window, $now);
        @flock($lock, LOCK_UN);
        fclose($lock);
        return $used > $limit ? -1 : max(0, $limit - $used);
    }

    /** Remove bucket files older than the newest $keepDays (housekeeping). */
    public static function prune(int $keepDays = 2): int
    {
        $dir = self::directory();
        if ($dir === null) return 0;
        $cutoff = time() - ($keepDays * 86400);
        $removed = 0;
        foreach (glob($dir . '/*.jsonl') ?: [] as $file) {
            $name = basename($file);
            if (!preg_match('/^(\d{12})\.jsonl$/', $name, $m)) continue;
            $bucket = \DateTime::createFromFormat('YmdHi', $m[1], new \DateTimeZone('UTC'));
            if ($bucket && $bucket->getTimestamp() < $cutoff) {
                if (@unlink($file)) $removed++;
            }
        }
        return $removed;
    }
}
