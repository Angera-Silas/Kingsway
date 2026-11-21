<?php

declare(strict_types=1);

namespace App\API\Services;

use App\API\Includes\FileLogger;
use App\Config\Config;
use App\Database\Database;
use PDO;

/**
 * Round-the-clock log watch for the system administrator.
 *
 * The ticker calls the worker endpoint every few seconds. Each run reads only
 * the bytes appended since the last run to the error journals
 * (errors.log, security.log and today's php-errors file), grades every new
 * entry and alerts the system administrators:
 *
 *   critical  fatal/critical errors, database down, disk full, memory
 *             exhausted, or any one problem repeating fast (a spike):
 *             SMS and email at once;
 *   error     application errors and security warnings: an email digest at
 *             most every DIGEST_MINUTES;
 *   warning   PHP warnings: included in the digest, never SMS.
 *
 * Floods are impossible by design: one alert per problem signature per
 * COOLDOWN_MINUTES, at most SMS_PER_HOUR text messages an hour, and the
 * monitor ignores its own entries so a failed alert cannot loop.
 *
 * Positions and alert history live in a small state file beside the logs
 * (state, not a log). Recent findings are kept there for the system admin
 * assistant and the GET endpoint. Nothing here reads personal records;
 * journal messages are already redacted by Logger.
 */
final class SystemLogMonitorService
{
    private const MAX_BYTES_PER_FILE = 524288; // per run, so a log storm cannot stall the worker
    private const COOLDOWN_MINUTES = 30;
    private const DIGEST_MINUTES = 15;
    private const SMS_PER_HOUR = 6;
    private const SPIKE_COUNT = 20;          // same signature this many times in one run = critical
    private const RECENT_KEEP = 50;
    private const STATE_FILE = '.log_monitor_state.json';

    private const CRITICAL_PATTERNS = [
        '/\b(PHP )?(Fatal error|Parse error)\b/i' => 'PHP fatal error',
        '/Uncaught (Error|Exception|TypeError)/i' => 'Uncaught exception',
        '/SQLSTATE\[HY000\] \[(2002|2003|2006|1045)\]|MySQL server has gone away|Connection refused/i' => 'Database unavailable',
        '/Too many connections/i' => 'Database connection limit reached',
        '/No space left on device|disk (is )?full/i' => 'Disk full',
        '/Allowed memory size .* exhausted|Out of memory/i' => 'Memory exhausted',
    ];

    private string $logDir;
    /** @var callable(string $channel, array $recipients, string $subject, string $body): bool */
    private $sender;
    /** @var callable(): int */
    private $clock;
    private ?PDO $pdo;
    private ?array $recipientsOverride;

    public function __construct(
        ?string $logDir = null,
        ?callable $sender = null,
        ?callable $clock = null,
        ?PDO $pdo = null,
        ?array $recipients = null
    ) {
        $this->logDir = $logDir ?? dirname(FileLogger::path('errors'));
        $this->sender = $sender ?? [$this, 'deliver'];
        $this->clock = $clock ?? static fn (): int => time();
        $this->pdo = $pdo;
        $this->recipientsOverride = $recipients;
    }

    /** One monitoring pass. Returns counts for the worker response. */
    public function run(): array
    {
        $now = ($this->clock)();
        $state = $this->loadState();
        $findings = [];
        foreach ($this->watchedFiles($now) as $name => $path) {
            foreach ($this->readNew($path, $name, $state) as $line) {
                $finding = $this->classify($line, $name);
                if ($finding !== null) {
                    $findings[] = $finding;
                }
            }
        }

        // Group by signature; a fast repeat is a spike and becomes critical.
        $groups = [];
        foreach ($findings as $f) {
            $key = $f['signature'];
            if (!isset($groups[$key])) {
                $groups[$key] = $f + ['count' => 0];
            }
            $groups[$key]['count']++;
            if ($groups[$key]['count'] >= self::SPIKE_COUNT && $groups[$key]['severity'] !== 'critical') {
                $groups[$key]['severity'] = 'critical';
                $groups[$key]['title'] = 'Spike: ' . $groups[$key]['title'];
            }
        }

        $sent = ['sms' => 0, 'email' => 0];
        foreach ($groups as $key => $g) {
            $state['recent'][] = [
                'at' => date('c', $now), 'severity' => $g['severity'], 'title' => $g['title'],
                'detail' => $g['detail'], 'source' => $g['source'], 'count' => $g['count'],
            ];
            if ($g['severity'] === 'critical') {
                if ($this->coolingDown($state, $key, $now)) {
                    continue;
                }
                $state['last_alert'][$key] = $now;
                $sent = $this->alertCritical($g, $state, $now, $sent);
            } else {
                $state['digest'][$key] = [
                    'severity' => $g['severity'], 'title' => $g['title'], 'detail' => $g['detail'],
                    'source' => $g['source'],
                    'count' => (int) (($state['digest'][$key]['count'] ?? 0) + $g['count']),
                ];
            }
        }
        if (!empty($state['digest']) && $now - (int) ($state['last_digest'] ?? 0) >= self::DIGEST_MINUTES * 60) {
            if ($this->send('email', 'Kingsway system digest: ' . count($state['digest']) . ' issue(s)', $this->digestBody($state['digest'], $now))) {
                $sent['email']++;
            }
            $state['digest'] = [];
            $state['last_digest'] = $now;
        }

        $state['recent'] = array_slice($state['recent'] ?? [], -self::RECENT_KEEP);
        $state['last_run'] = $now;
        $this->saveState($state);
        return ['findings' => count($findings), 'problems' => count($groups), 'sms_sent' => $sent['sms'], 'emails_sent' => $sent['email']];
    }

    /** Recent findings and health of the monitor itself, for admins and the assistant. */
    public function status(): array
    {
        $state = $this->loadState();
        $now = ($this->clock)();
        $lastRun = (int) ($state['last_run'] ?? 0);
        return [
            'last_run' => $lastRun > 0 ? date('c', $lastRun) : null,
            'running' => $lastRun > 0 && ($now - $lastRun) < 120,
            'pending_digest' => array_values($state['digest'] ?? []),
            'recent' => array_reverse($state['recent'] ?? []),
        ];
    }

    // ------------------------------------------------------------------

    /** @return array<string,string> name => path */
    private function watchedFiles(int $now): array
    {
        return [
            'errors' => $this->logDir . '/errors.log',
            'security' => $this->logDir . '/security.log',
            'php-errors' => $this->logDir . '/php-errors-' . date('Y-m-d', $now) . '.log',
        ];
    }

    /** New complete lines since the stored offset (handles rotation and truncation). */
    private function readNew(string $path, string $name, array &$state): array
    {
        if (!is_file($path) || !is_readable($path)) {
            return [];
        }
        $size = (int) filesize($path);
        $key = $name . ':' . basename($path);
        $offset = $state['offsets'][$key] ?? null;
        if ($offset === null) {
            // First ever run: start at the end, never replay old history.
            // After that, a file we have not seen is new (today's php-errors
            // file after midnight), so read it from the top.
            $firstRun = empty($state['last_run']);
            $offset = $firstRun ? $size : 0;
        }
        if ($size < $offset) {
            $offset = 0; // rotated or truncated
        }
        if ($size === $offset) {
            $state['offsets'][$key] = $offset;
            return [];
        }
        $handle = @fopen($path, 'rb');
        if ($handle === false) {
            return [];
        }
        fseek($handle, $offset);
        $chunk = (string) fread($handle, min(self::MAX_BYTES_PER_FILE, $size - $offset));
        fclose($handle);
        $end = strrpos($chunk, "\n");
        if ($end === false) {
            return []; // wait for the line to finish
        }
        $state['offsets'][$key] = $offset + $end + 1;
        // Forget offsets of earlier daily files.
        foreach (array_keys($state['offsets']) as $k) {
            if (str_starts_with($k, $name . ':') && $k !== $key) {
                unset($state['offsets'][$k]);
            }
        }
        return array_filter(explode("\n", substr($chunk, 0, $end)), static fn ($l) => trim($l) !== '');
    }

    /** @return array{severity:string,title:string,detail:string,source:string,signature:string}|null */
    private function classify(string $line, string $source): ?array
    {
        $entry = json_decode($line, true);
        if (is_array($entry)) {
            $type = (string) ($entry['type'] ?? '');
            if (str_starts_with($type, 'system_log_monitor')) {
                return null; // never alert on our own entries
            }
            $level = strtolower((string) ($entry['level'] ?? 'info'));
            $message = trim((string) ($entry['message'] ?? $entry['error'] ?? $type));
            $text = $message . ' ' . (string) ($entry['error'] ?? '');
        } else {
            $level = preg_match('/PHP (Fatal error|Parse error)/i', $line) ? 'critical'
                : (preg_match('/PHP Warning/i', $line) ? 'warning'
                : (preg_match('/PHP (Notice|Deprecated)/i', $line) ? 'info' : 'error'));
            $message = trim(preg_replace('/^\[[^\]]+\]\s*/', '', $line) ?? $line);
            $text = $message;
            $type = '';
        }

        $title = null;
        $severity = null;
        foreach (self::CRITICAL_PATTERNS as $pattern => $label) {
            if (preg_match($pattern, $text)) {
                $severity = 'critical';
                $title = $label;
                break;
            }
        }
        if ($severity === null) {
            if (in_array($level, ['critical', 'alert', 'emergency'], true)) {
                $severity = 'critical';
            } elseif ($level === 'error') {
                $severity = 'error';
            } elseif ($level === 'warning') {
                $severity = $source === 'security' ? 'error' : 'warning';
            } else {
                return null;
            }
            $title = $source === 'security'
                ? 'Security: ' . ($type !== '' ? str_replace('_', ' ', $type) : 'warning')
                : ($type !== '' && $type !== 'error' ? str_replace('_', ' ', $type) : 'Application error');
        }
        $detail = mb_substr($message, 0, 300);
        // Signature without numbers/ids so repeats group together.
        $signature = hash('sha1', $source . '|' . $title . '|' . preg_replace('/\d+|0x[0-9a-f]+|[0-9a-f]{16,}/i', '#', mb_substr($message, 0, 160)));
        return ['severity' => $severity, 'title' => $title, 'detail' => $detail, 'source' => $source, 'signature' => $signature];
    }

    private function coolingDown(array $state, string $key, int $now): bool
    {
        return $now - (int) ($state['last_alert'][$key] ?? 0) < self::COOLDOWN_MINUTES * 60;
    }

    private function alertCritical(array $g, array &$state, int $now, array $sent): array
    {
        $env = (string) ($_ENV['APP_ENV'] ?? 'production');
        $sms = mb_substr(sprintf(
            'Kingsway %s ALERT: %s (x%d) at %s. Check logs/%s/%s.log.',
            strtoupper($env), $g['title'], $g['count'], date('H:i', $now), $env, $g['source']
        ), 0, 300);
        $hourAgo = $now - 3600;
        $state['sms_times'] = array_values(array_filter($state['sms_times'] ?? [], static fn ($t) => $t > $hourAgo));
        if (count($state['sms_times']) < self::SMS_PER_HOUR && $this->send('sms', 'Kingsway system alert', $sms)) {
            $state['sms_times'][] = $now;
            $sent['sms']++;
        }
        $body = '<p><strong>' . htmlspecialchars($g['title']) . '</strong> occurred ' . (int) $g['count']
            . ' time(s) at ' . date('Y-m-d H:i:s', $now) . ' (' . htmlspecialchars($env) . ').</p>'
            . '<p>Source: logs/' . htmlspecialchars($env . '/' . $g['source']) . '.log</p>'
            . '<pre style="white-space:pre-wrap">' . htmlspecialchars($g['detail']) . '</pre>'
            . '<p>Further alerts for this problem are paused for ' . self::COOLDOWN_MINUTES . ' minutes.</p>';
        if ($this->send('email', 'CRITICAL: ' . $g['title'] . ' (Kingsway ' . $env . ')', $body)) {
            $sent['email']++;
        }
        return $sent;
    }

    private function digestBody(array $digest, int $now): string
    {
        $rows = '';
        foreach ($digest as $d) {
            $rows .= '<tr><td>' . htmlspecialchars(strtoupper($d['severity'])) . '</td><td>' . htmlspecialchars($d['title'])
                . '</td><td>' . (int) $d['count'] . '</td><td>' . htmlspecialchars($d['source']) . '</td><td>'
                . htmlspecialchars(mb_substr($d['detail'], 0, 200)) . '</td></tr>';
        }
        return '<p>System issues logged in the last ' . self::DIGEST_MINUTES . ' minutes (up to ' . date('Y-m-d H:i', $now) . '):</p>'
            . '<table border="1" cellpadding="4" cellspacing="0"><tr><th>Severity</th><th>Problem</th><th>Count</th><th>Log</th><th>Latest detail</th></tr>'
            . $rows . '</table>';
    }

    private function send(string $channel, string $subject, string $body): bool
    {
        $recipients = $this->recipients();
        $list = $channel === 'sms' ? $recipients['phones'] : $recipients['emails'];
        if ($list === []) {
            return false;
        }
        try {
            $ok = (bool) ($this->sender)($channel, $list, $subject, $body);
        } catch (\Throwable $e) {
            $ok = false;
        }
        FileLogger::write('jobs', [
            'type' => 'system_log_monitor_alert', 'channel' => $channel,
            'recipients' => count($list), 'delivered' => $ok, 'subject' => mb_substr($subject, 0, 120),
        ], $ok ? 'info' : 'warning');
        return $ok;
    }

    /** Default delivery through the existing communications stack. */
    private function deliver(string $channel, array $recipients, string $subject, string $body): bool
    {
        $mail = Mailer::to($recipients)->via($channel)->category('system_alert')->body($body);
        if ($channel === 'email') {
            $mail = $mail->subject($subject);
        }
        $result = $mail->send();
        $status = strtolower((string) ($result['status'] ?? ''));
        return !in_array($status, ['error', 'unavailable'], true);
    }

    /**
     * SYSTEM_ALERT_EMAILS / SYSTEM_ALERT_PHONES (comma lists) when set, else
     * the active System Administrators' email and phone.
     *
     * @return array{emails:list<string>,phones:list<string>}
     */
    private function recipients(): array
    {
        if ($this->recipientsOverride !== null) {
            return $this->recipientsOverride + ['emails' => [], 'phones' => []];
        }
        $split = static fn (string $v): array => array_values(array_filter(array_map('trim', explode(',', $v))));
        $emails = $split((string) Config::get('SYSTEM_ALERT_EMAILS', ''));
        $phones = $split((string) Config::get('SYSTEM_ALERT_PHONES', ''));
        if ($emails !== [] || $phones !== []) {
            return $this->recipientsOverride = ['emails' => $emails, 'phones' => $phones];
        }
        try {
            $db = $this->pdo ?? Database::getInstance()->getConnection();
            // Single-table keyed reads (Hard Rule 1).
            $role = $db->prepare("SELECT id FROM roles WHERE name = 'System Administrator' LIMIT 1");
            $role->execute();
            $roleId = (int) $role->fetchColumn();
            $userIds = [];
            if ($roleId > 0) {
                $stmt = $db->prepare('SELECT user_id FROM user_roles WHERE role_id = ?');
                $stmt->execute([$roleId]);
                $userIds = array_map('intval', $stmt->fetchAll(PDO::FETCH_COLUMN));
            }
            $personIds = [];
            if ($userIds !== []) {
                $stmt = $db->query("SELECT person_id FROM users WHERE status = 'active' AND id IN (" . implode(',', $userIds) . ')');
                $personIds = array_filter(array_map('intval', $stmt->fetchAll(PDO::FETCH_COLUMN)));
            }
            if ($personIds !== []) {
                $stmt = $db->query('SELECT email, phone FROM persons WHERE id IN (' . implode(',', $personIds) . ')');
                foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $p) {
                    if (filter_var((string) $p['email'], FILTER_VALIDATE_EMAIL)) $emails[] = (string) $p['email'];
                    if (trim((string) $p['phone']) !== '') $phones[] = trim((string) $p['phone']);
                }
            }
        } catch (\Throwable $e) {
            // Database down is itself a critical finding; alerts then need the env lists.
        }
        return $this->recipientsOverride = ['emails' => array_values(array_unique($emails)), 'phones' => array_values(array_unique($phones))];
    }

    private function loadState(): array
    {
        $raw = @file_get_contents($this->logDir . '/' . self::STATE_FILE);
        $state = is_string($raw) ? json_decode($raw, true) : null;
        return is_array($state) ? $state : ['offsets' => [], 'last_alert' => [], 'digest' => [], 'recent' => [], 'sms_times' => []];
    }

    private function saveState(array $state): void
    {
        $path = $this->logDir . '/' . self::STATE_FILE;
        $tmp = $path . '.tmp';
        if (@file_put_contents($tmp, json_encode($state, JSON_UNESCAPED_SLASHES)) !== false) {
            @rename($tmp, $path);
        }
    }
}
