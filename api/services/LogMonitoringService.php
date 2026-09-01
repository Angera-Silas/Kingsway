<?php

namespace App\API\Services;

use App\API\Includes\FileLogger;

/** Automated, stateful health/security rules over the governed journal. */
final class LogMonitoringService
{
    private const COOLDOWN_SECONDS = 900;

    public static function run(): array
    {
        $now = time();
        $statePath = FileLogger::rootDir() . '/' . FileLogger::environment() . '/.monitor-state.json';
        $state = self::loadState($statePath);
        $alerts = [];
        $recent = Logger::exportEntries(['date_from' => date('Y-m-d', $now - 3600), 'order' => 'desc'], 10000)['entries'] ?? [];

        $failedLogins = []; $errors = 0; $http = 0; $httpFailures = 0; $userIps = [];
        foreach ($recent as $row) {
            $ts = strtotime((string) ($row['timestamp'] ?? '')) ?: 0;
            if ($ts < $now - 3600) continue;
            $level = strtolower((string) ($row['level'] ?? 'info'));
            if ($ts >= $now - 600 && in_array($level, ['error', 'critical'], true)) $errors++;
            if (($row['_category'] ?? '') === 'auth' && ($row['type'] ?? '') === 'login_attempt' && strtolower((string) ($row['status'] ?? '')) !== 'success' && $ts >= $now - 600) {
                $key = (string) ($row['user_id'] ?? $row['username'] ?? $row['ip'] ?? 'unknown');
                $failedLogins[$key] = ($failedLogins[$key] ?? 0) + 1;
            }
            if (($row['_category'] ?? '') === 'http' && isset($row['status']) && $ts >= $now - 600) {
                $http++; if ((int) $row['status'] >= 400) $httpFailures++;
            }
            $uid = (int) ($row['user_id'] ?? 0); $ip = (string) ($row['ip'] ?? '');
            if ($uid > 0 && $ip !== '' && $ts >= $now - 3600) $userIps[$uid][$ip] = true;
        }
        foreach ($failedLogins as $identity => $count) if ($count >= 5) $alerts[] = ['repeated_login_failure:' . $identity, 'Repeated login failures', "$count failed login attempts were recorded for identity {$identity} during the last 10 minutes.", 'high'];
        if ($errors >= 10) $alerts[] = ['error_burst', 'Critical error volume', "$errors error or critical events were recorded during the last 10 minutes.", 'high'];
        if ($http >= 20 && ($httpFailures / $http) >= 0.25) $alerts[] = ['http_failure_rate', 'Abnormal request failure rate', "$httpFailures of $http completed requests failed during the last 10 minutes.", 'high'];
        foreach ($userIps as $uid => $ips) if (count($ips) >= 4) $alerts[] = ["multiple_ips:{$uid}", 'Abnormal account access', 'User #' . $uid . ' was observed from ' . count($ips) . ' IP addresses during the last hour.', 'high'];

        foreach (Logger::analytics([])['integrity'] ?? [] as $check) {
            if (($check['status'] ?? '') === 'failed') $alerts[] = ['integrity:' . ($check['category'] ?? 'unknown'), 'Log integrity failure', 'The ' . ($check['category'] ?? 'unknown') . ' journal failed its integrity check.', 'high'];
        }
        $dir = FileLogger::rootDir() . '/' . FileLogger::environment();
        $free = @disk_free_space($dir); $total = @disk_total_space($dir);
        if (is_numeric($free) && is_numeric($total) && $total > 0 && ($free / $total) < 0.10) $alerts[] = ['disk_space', 'Log storage running low', 'Less than 10% free disk space remains on the log volume.', 'high'];

        $sent = 0;
        foreach ($alerts as [$key, $title, $message, $priority]) {
            if (($state[$key] ?? 0) > $now - self::COOLDOWN_SECONDS) continue;
            Logger::warning('alerts', $title, ['alert_key' => $key, 'details' => $message]);
            try {
                (new NotificationService())->push('role:System Administrator', 'system_monitoring_alert', $title, $message, $priority, ['dedup_minutes' => 15, 'action_url' => 'home.php?route=log_viewer']);
            } catch (\Throwable $e) {
                Logger::error('alerts', 'Monitoring notification delivery failed', ['alert_key' => $key, 'error' => $e->getMessage()]);
            }
            $state[$key] = $now; $sent++;
        }
        foreach ($state as $key => $at) if ($at < $now - 86400 * 7) unset($state[$key]);
        self::saveState($statePath, $state);
        Logger::info('events', 'Automated log monitoring completed', ['rules_triggered' => count($alerts), 'alerts_sent' => $sent]);
        return ['rules_triggered' => count($alerts), 'alerts_sent' => $sent];
    }

    private static function loadState(string $path): array
    {
        $decoded = is_file($path) ? json_decode((string) @file_get_contents($path), true) : [];
        return is_array($decoded) ? $decoded : [];
    }

    private static function saveState(string $path, array $state): void
    {
        $handle = @fopen($path, 'c+');
        if (!$handle) return;
        if (flock($handle, LOCK_EX)) { ftruncate($handle, 0); rewind($handle); fwrite($handle, json_encode($state, JSON_UNESCAPED_SLASHES)); fflush($handle); flock($handle, LOCK_UN); }
        fclose($handle); @chmod($path, FileLogger::environment() === 'development' ? 0666 : 0660);
    }
}
