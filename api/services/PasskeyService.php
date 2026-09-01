<?php
namespace App\API\Services;

use App\Database\Database;
use lbuchs\WebAuthn\WebAuthn;
use PDO;

class PasskeyService
{
    private PDO $db;
    private string $rpId;
    private TwoFactorService $crypto;

    public function __construct(?PDO $db = null)
    {
        $this->db = $db ?? Database::getInstance()->getConnection();
        $this->crypto = new TwoFactorService($this->db);
        $configured = defined('PASSKEY_RP_ID') ? (string) PASSKEY_RP_ID : '';
        $host = preg_replace('/:\d+$/', '', (string) ($_SERVER['HTTP_HOST'] ?? 'localhost'));
        $this->rpId = $configured ?: ($host ?: 'localhost');
    }

    private function server(): WebAuthn
    {
        return new WebAuthn(defined('SCHOOL_NAME') ? SCHOOL_NAME : 'Kingsway Preparatory School', $this->rpId, ['none'], true);
    }

    private function token(): string { return rtrim(strtr(base64_encode(random_bytes(32)), '+/', '-_'), '='); }
    private function b64(string $value): string { return rtrim(strtr(base64_encode($value), '+/', '-_'), '='); }
    private function raw(string $value): string { return base64_decode(strtr($value, '-_', '+/') . str_repeat('=', (4 - strlen($value) % 4) % 4), true) ?: ''; }

    public function startRegistration(int $userId, string $name, string $displayName): array
    {
        $server = $this->server();
        $args = $server->getCreateArgs((string) $userId, $name, $displayName, 60, 'required', 'required');
        $challenge = $server->getChallenge()->getBinaryString();
        $this->storeChallenge($userId, $challenge, 'registration');
        return json_decode(json_encode($args), true);
    }

    public function finishRegistration(int $userId, array $response, string $label = 'Passkey'): bool
    {
        $challenge = $this->takeChallenge($userId, 'registration');
        if (!$challenge) return false;
        $server = $this->server();
        $data = $server->processCreate($this->raw((string) ($response['clientDataJSON'] ?? '')), $this->raw((string) ($response['attestationObject'] ?? '')), $challenge, true, true, false);
        $credentialId = $data->credentialId->getBinaryString();
        $stmt = $this->db->prepare("INSERT INTO user_passkeys (user_id, credential_id, credential_public_key, signature_count, transports, label) VALUES (?, ?, ?, ?, ?, ?)");
        return $stmt->execute([$userId, $this->b64($credentialId), $data->credentialPublicKey, (int) ($data->signatureCounter ?? 0), json_encode($response['transports'] ?? []), substr($label, 0, 120)]);
    }

    public function startAuthentication(int $userId): array
    {
        $stmt = $this->db->prepare("SELECT credential_id FROM user_passkeys WHERE user_id=?"); $stmt->execute([$userId]);
        $ids = array_map(fn($v) => $this->raw($v), $stmt->fetchAll(PDO::FETCH_COLUMN));
        if (!$ids) throw new \RuntimeException('No passkey is enrolled');
        $server = $this->server(); $args = $server->getGetArgs($ids, 60, true, true, true, true, true);
        $this->storeChallenge($userId, $server->getChallenge()->getBinaryString(), 'authentication');
        return json_decode(json_encode($args), true);
    }

    public function startPasswordlessAuthentication(): array
    {
        $server = $this->server();
        $args = $server->getGetArgs([], 60, true, true, true, true, true);
        $this->storeChallenge(null, $server->getChallenge()->getBinaryString(), 'authentication');
        return json_decode(json_encode($args), true);
    }

    /** @return int|null authenticated user id */
    public function finishPasswordlessAuthentication(array $response): ?int
    {
        $credentialId = $this->b64($this->raw((string)($response['id'] ?? '')));
        $stmt = $this->db->prepare('SELECT id,user_id,credential_public_key,signature_count FROM user_passkeys WHERE credential_id=? LIMIT 1');
        $stmt->execute([$credentialId]); $row = $stmt->fetch(PDO::FETCH_ASSOC); if (!$row) return null;
        $clientData = $this->raw((string)($response['clientDataJSON'] ?? ''));
        $clientPayload = json_decode($clientData, true);
        $browserChallenge = is_array($clientPayload) ? (string)($clientPayload['challenge'] ?? '') : '';
        if ($browserChallenge === '') return null;
        $challenge = $this->takeAnonymousChallenge('authentication', $browserChallenge); if (!$challenge) return null;
        $server = $this->server();
        $server->processGet($clientData, $this->raw((string)($response['authenticatorData'] ?? '')), $this->raw((string)($response['signature'] ?? '')), $row['credential_public_key'], $challenge, (int)$row['signature_count'], true, true);
        $this->db->prepare('UPDATE user_passkeys SET signature_count=?,last_used_at=NOW() WHERE id=?')->execute([(int)($server->getSignatureCounter() ?? $row['signature_count']),$row['id']]);
        return (int)$row['user_id'];
    }

    public function finishAuthentication(int $userId, array $response): bool
    {
        $challenge = $this->takeChallenge($userId, 'authentication'); if (!$challenge) return false;
        $credentialId = $this->b64($this->raw((string) ($response['id'] ?? '')));
        $stmt = $this->db->prepare("SELECT id, credential_public_key, signature_count FROM user_passkeys WHERE user_id=? AND credential_id=? LIMIT 1");
        $stmt->execute([$userId, $credentialId]); $row = $stmt->fetch(PDO::FETCH_ASSOC); if (!$row) return false;
        $server = $this->server();
        $server->processGet($this->raw((string) ($response['clientDataJSON'] ?? '')), $this->raw((string) ($response['authenticatorData'] ?? '')), $this->raw((string) ($response['signature'] ?? '')), $row['credential_public_key'], $challenge, (int) $row['signature_count'], true, true);
        $this->db->prepare("UPDATE user_passkeys SET signature_count=?, last_used_at=NOW() WHERE id=?")->execute([(int) ($server->getSignatureCounter() ?? $row['signature_count']), $row['id']]);
        return true;
    }

    public function list(int $userId): array
    {
        $stmt = $this->db->prepare("SELECT id, label, last_used_at, created_at FROM user_passkeys WHERE user_id=? ORDER BY id DESC"); $stmt->execute([$userId]); return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function revoke(int $userId, int $passkeyId): bool
    {
        $stmt = $this->db->prepare('DELETE FROM user_passkeys WHERE id=? AND user_id=?');
        $stmt->execute([$passkeyId, $userId]);
        return $stmt->rowCount() === 1;
    }

    private function storeChallenge(?int $userId, string $challenge, string $purpose): void
    {
        $encoded = base64_encode($challenge);
        $this->db->prepare("INSERT INTO user_passkey_challenges (user_id, challenge_hash, challenge_value, purpose, expires_at) VALUES (?, ?, ?, ?, DATE_ADD(NOW(), INTERVAL 2 MINUTE))")->execute([$userId, hash('sha256', $this->b64($challenge)), $this->crypto->encryptSecret($encoded), $purpose]);
    }
    private function takeChallenge(int $userId, string $purpose): ?string
    {
        $stmt = $this->db->prepare("SELECT id, challenge_value FROM user_passkey_challenges WHERE user_id=? AND purpose=? AND expires_at>NOW() ORDER BY id DESC LIMIT 1"); $stmt->execute([$userId, $purpose]); $row = $stmt->fetch(PDO::FETCH_ASSOC); if (!$row) return null;
        $this->db->prepare("DELETE FROM user_passkey_challenges WHERE id=?")->execute([$row['id']]);
        $encoded = $this->crypto->decryptSecret($row['challenge_value']);
        return $encoded ? (base64_decode($encoded, true) ?: null) : null;
    }

    private function takeAnonymousChallenge(string $purpose, string $browserChallenge): ?string
    {
        $this->db->beginTransaction();
        try {
            $stmt = $this->db->prepare("SELECT id,challenge_value FROM user_passkey_challenges WHERE user_id IS NULL AND purpose=? AND challenge_hash=? AND expires_at>NOW() ORDER BY id DESC LIMIT 1 FOR UPDATE");
            $stmt->execute([$purpose, hash('sha256', $browserChallenge)]); $row=$stmt->fetch(PDO::FETCH_ASSOC);
            if (!$row) { $this->db->rollBack(); return null; }
            $this->db->prepare('DELETE FROM user_passkey_challenges WHERE id=?')->execute([$row['id']]);
            $this->db->commit();
            $encoded=$this->crypto->decryptSecret($row['challenge_value']);
            return $encoded ? (base64_decode($encoded,true) ?: null) : null;
        } catch (\Throwable $e) { if($this->db->inTransaction())$this->db->rollBack(); throw $e; }
    }
}
