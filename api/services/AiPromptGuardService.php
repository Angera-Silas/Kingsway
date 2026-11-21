<?php

declare(strict_types=1);

namespace App\API\Services;

use App\API\Includes\FileLogger;
use App\Config\Config;
use App\Database\Database;
use PDO;

/**
 * The gate every assistant question passes before it reaches a model.
 *
 * Staff, parent, research and public assistants all ask here first:
 *   1. a session (or visitor) already blocked is refused outright;
 *   2. the question and the visitor's earlier turns are screened by the
 *      Python guard (python_platform/app/guard.py, the single rule set);
 *   3. a malicious prompt gets the blunt refusal below and its session is
 *      blocked from prompting again.
 *
 * Blocks live in ai_prompt_blocks (enforcement state, not a log; the attempt
 * itself is journalled to logs/<env>/security.log). Subjects are stored as
 * keyed hashes, never raw IPs, cookies or session ids.
 */
final class AiPromptGuardService
{
    /** Kept identical to guard.MALICIOUS_PROMPT_MESSAGE in Python. */
    public const REFUSAL = 'That is a malicious prompt. I cannot run it. This session has been blocked '
        . 'from sending further prompts, and the attempt has been recorded. I only '
        . "work within Kingsway Preparatory School's rules, and no message can switch "
        . 'them off.';

    public const BLOCKED_NOTICE = 'This session has been blocked from sending prompts because a malicious '
        . 'prompt was sent from it. If you believe this is a mistake, contact the school administration.';

    private const STRIKES_FOR_USER_BLOCK = 3;
    public const VISITOR_COOKIE = 'kw_visitor';

    private ?PDO $pdo;
    /** @var callable|null fn(string $text, string $audience, array $conversation): array */
    private $screener;

    public function __construct(?PDO $pdo = null, ?callable $screener = null)
    {
        $this->pdo = $pdo;
        $this->screener = $screener;
    }

    private function db(): PDO
    {
        return $this->pdo ??= Database::getInstance()->getConnection();
    }

    /**
     * Subjects for a signed-in staff member or parent: the login session, the
     * chat session when no login session id exists, and the user (strikes).
     *
     * @return list<array{scope:string,hash:string,user_id:?int,user_type:string}>
     */
    public function memberSubjects(string $userType, int $userId, int $loginSessionId = 0, string $chatSessionId = ''): array
    {
        $subjects = [];
        if ($loginSessionId > 0) {
            $subjects[] = $this->subject('login_session', "{$userType}:session:{$loginSessionId}", $userId, $userType);
        } elseif ($chatSessionId !== '') {
            $subjects[] = $this->subject('login_session', "{$userType}:{$userId}:chat:{$chatSessionId}", $userId, $userType);
        }
        $subjects[] = $this->subject('user', "{$userType}:user:{$userId}", $userId, $userType);
        return $subjects;
    }

    /**
     * Subjects for an anonymous website visitor: their visitor id (sent by the
     * browser, else a cookie issued here) and their IP address.
     *
     * The public chat reaches PHP through the Node gateway, so REMOTE_ADDR is
     * Node's address: blocking it would block every visitor. The caller passes
     * the visitor's real IP, which the controller only trusts from Node.
     *
     * @return list<array{scope:string,hash:string,user_id:?int,user_type:string}>
     */
    public function visitorSubjects(string $visitorId = '', string $ip = ''): array
    {
        $subjects = [$this->subject('public_visitor', 'visitor:' . $this->visitorRef($visitorId), null, 'public')];
        if ($ip !== '' && filter_var($ip, FILTER_VALIDATE_IP)) {
            $subjects[] = $this->subject('public_ip', 'ip:' . $ip, null, 'public');
        }
        return $subjects;
    }

    /** Anonymous visitor id: the browser's random id, else an HttpOnly cookie. Never identifies a person. */
    public function visitorRef(string $visitorId = ''): string
    {
        if (preg_match('/^[a-f0-9]{32}$/', $visitorId) === 1) {
            return $visitorId;
        }
        $ref = (string) ($_COOKIE[self::VISITOR_COOKIE] ?? '');
        if (preg_match('/^[a-f0-9]{32}$/', $ref) !== 1) {
            $ref = bin2hex(random_bytes(16));
            $_COOKIE[self::VISITOR_COOKIE] = $ref;
            if (!headers_sent()) {
                setcookie(self::VISITOR_COOKIE, $ref, [
                    'expires' => time() + 180 * 86400,
                    'path' => '/',
                    'secure' => !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off',
                    'httponly' => true,
                    'samesite' => 'Lax',
                ]);
            }
        }
        return $ref;
    }

    /** Keyed hash of the visitor id, for feedback rows. */
    public function visitorHash(string $visitorId = ''): string
    {
        return $this->hash('visitor:' . $this->visitorRef($visitorId));
    }

    /**
     * The visitor's IP: X-Visitor-IP only when the request carries the Node
     * gateway's internal secret, else REMOTE_ADDR.
     */
    public static function clientIp(): string
    {
        $secret = (string) Config::get('KINGSWAY_WORKER_SECRET', '');
        $given = (string) ($_SERVER['HTTP_X_INTERNAL_SECRET'] ?? '');
        $forwarded = trim((string) ($_SERVER['HTTP_X_VISITOR_IP'] ?? ''));
        if ($secret !== '' && $given !== '' && hash_equals($secret, $given) && filter_var($forwarded, FILTER_VALIDATE_IP)) {
            return $forwarded;
        }
        return trim((string) ($_SERVER['REMOTE_ADDR'] ?? ''));
    }

    /**
     * Decide one question. Returns null to proceed, or the refusal payload.
     *
     * @param list<array{scope:string,hash:string,user_id:?int,user_type:string}> $subjects
     * @return array{blocked:bool,newly_blocked:bool,message:string}|null
     */
    public function check(array $subjects, string $question, string $audience, array $conversation = []): ?array
    {
        if ($this->isBlocked($subjects)) {
            return ['blocked' => true, 'newly_blocked' => false, 'message' => self::BLOCKED_NOTICE];
        }
        $verdict = $this->screen($question, $audience, $conversation);
        if (!($verdict['malicious'] ?? false)) {
            return null;
        }
        $this->block($subjects, (string) ($verdict['category'] ?? 'prompt_injection'));
        return ['blocked' => true, 'newly_blocked' => true, 'message' => self::REFUSAL];
    }

    /** @param list<array{scope:string,hash:string}> $subjects */
    public function isBlocked(array $subjects): bool
    {
        if ($subjects === []) {
            return false;
        }
        try {
            $stmt = $this->db()->prepare(
                'SELECT 1 FROM ai_prompt_blocks
                  WHERE scope = :scope AND subject_hash = :hash AND active = 1
                    AND released_at IS NULL
                    AND (expires_at IS NULL OR expires_at > :now)
                  LIMIT 1'
            );
            foreach ($subjects as $subject) {
                $stmt->execute([':scope' => $subject['scope'], ':hash' => $subject['hash'], ':now' => date('Y-m-d H:i:s')]);
                if ($stmt->fetchColumn()) {
                    return true;
                }
            }
        } catch (\Throwable $e) {
            // Table not migrated yet: the screen below still refuses attacks.
            FileLogger::write('security', ['type' => 'ai_prompt_block_lookup_failed', 'error' => $e->getMessage()], 'warning');
        }
        return false;
    }

    /** @param list<array{scope:string,hash:string,user_id:?int,user_type:string}> $subjects */
    public function block(array $subjects, string $category): void
    {
        $now = date('Y-m-d H:i:s');
        foreach ($subjects as $subject) {
            [$active, $expires] = match ($subject['scope']) {
                'login_session' => [1, null],
                'public_visitor' => [1, date('Y-m-d H:i:s', strtotime('+30 days'))],
                'public_ip' => [1, date('Y-m-d H:i:s', strtotime('+24 hours'))],
                default => [0, date('Y-m-d H:i:s', strtotime('+24 hours'))], // user: strikes decide
            };
            try {
                $existing = $this->db()->prepare(
                    'SELECT id, strikes, blocked_at FROM ai_prompt_blocks WHERE scope = :scope AND subject_hash = :hash LIMIT 1'
                );
                $existing->execute([':scope' => $subject['scope'], ':hash' => $subject['hash']]);
                $row = $existing->fetch(PDO::FETCH_ASSOC) ?: null;
                // Strikes count within 24 hours of the last one.
                $strikes = 1;
                if ($row !== null && strtotime((string) $row['blocked_at']) >= strtotime('-24 hours')) {
                    $strikes = (int) $row['strikes'] + 1;
                }
                if ($subject['scope'] === 'user') {
                    $active = $strikes >= self::STRIKES_FOR_USER_BLOCK ? 1 : 0;
                }
                if ($row === null) {
                    $this->db()->prepare(
                        'INSERT INTO ai_prompt_blocks
                            (scope, subject_hash, user_id, user_type, category, strikes, active, blocked_at, expires_at)
                         VALUES (:scope, :hash, :uid, :utype, :category, :strikes, :active, :now, :expires)'
                    )->execute([
                        ':scope' => $subject['scope'], ':hash' => $subject['hash'],
                        ':uid' => $subject['user_id'], ':utype' => $subject['user_type'],
                        ':category' => mb_substr($category, 0, 40), ':strikes' => $strikes,
                        ':active' => $active, ':now' => $now, ':expires' => $expires,
                    ]);
                } else {
                    $this->db()->prepare(
                        'UPDATE ai_prompt_blocks
                            SET category = :category, strikes = :strikes, active = :active,
                                blocked_at = :now, expires_at = :expires,
                                released_at = NULL, released_by = NULL
                          WHERE id = :id'
                    )->execute([
                        ':category' => mb_substr($category, 0, 40), ':strikes' => $strikes,
                        ':active' => $active, ':now' => $now, ':expires' => $expires, ':id' => (int) $row['id'],
                    ]);
                }
            } catch (\Throwable $e) {
                FileLogger::write('security', ['type' => 'ai_prompt_block_write_failed', 'error' => $e->getMessage()], 'error');
            }
        }
        FileLogger::write('security', [
            'type' => 'ai_malicious_prompt_blocked',
            'category' => $category,
            'user_type' => $subjects[0]['user_type'] ?? 'public',
            'user_id' => $subjects[0]['user_id'] ?? null,
            'scopes' => array_values(array_unique(array_column($subjects, 'scope'))),
            'request_id' => $_SERVER['REQUEST_ID'] ?? null,
        ], 'warning');
    }

    /** @return array{malicious:bool,category:string} */
    private function screen(string $question, string $audience, array $conversation): array
    {
        try {
            if ($this->screener !== null) {
                return ($this->screener)($question, $audience, $conversation);
            }
            $bridge = new PythonAiBridge();
            if (!$bridge->available()) {
                return ['malicious' => false, 'category' => ''];
            }
            $result = $bridge->call('/api/guard/screen', [
                'text' => mb_substr($question, 0, 4000),
                'audience' => $audience,
                'conversation' => array_slice($conversation, -6),
            ], 'POST', [], 5);
            return ['malicious' => (bool) ($result['malicious'] ?? false), 'category' => (string) ($result['category'] ?? '')];
        } catch (\Throwable $e) {
            // The engine refuses attacks itself too; only the block is skipped.
            FileLogger::write('security', ['type' => 'ai_prompt_screen_unavailable', 'error' => $e->getMessage()], 'warning');
            return ['malicious' => false, 'category' => ''];
        }
    }

    /** @return array{scope:string,hash:string,user_id:?int,user_type:string} */
    private function subject(string $scope, string $value, ?int $userId, string $userType): array
    {
        return ['scope' => $scope, 'hash' => $this->hash($value), 'user_id' => $userId, 'user_type' => $userType];
    }

    private function hash(string $value): string
    {
        $key = (string) Config::get('JWT_SECRET', '');
        return hash_hmac('sha256', $value, $key !== '' ? $key : 'kingsway-ai-guard');
    }
}
