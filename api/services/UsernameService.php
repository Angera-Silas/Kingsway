<?php
namespace App\API\Services;

use PDO;

/** Central username policy for every account-creation workflow. */
class UsernameService
{
    public static function generate(PDO $db, string $email, string $firstName = '', string $lastName = ''): string
    {
        $emailLocalPart = explode('@', trim($email), 2)[0] ?? '';
        $source = $emailLocalPart !== '' ? $emailLocalPart : trim($firstName . '.' . $lastName);
        $base = strtolower((string) preg_replace('/[^a-z0-9]+/i', '_', $source));
        $base = trim($base, '_');

        if ($base === '') {
            $base = 'user';
        } elseif (!preg_match('/^[a-z]/', $base)) {
            $base = 'u_' . $base;
        }

        $base = substr($base, 0, 30);
        if (strlen($base) < 3) {
            $base .= str_repeat('0', 3 - strlen($base));
        }

        $username = $base;
        $suffix = 1;
        $stmt = $db->prepare('SELECT 1 FROM users WHERE username = ? LIMIT 1');
        while (true) {
            $stmt->execute([$username]);
            if (!$stmt->fetchColumn()) {
                return $username;
            }

            $suffixText = (string) $suffix++;
            $username = substr($base, 0, 30 - strlen($suffixText)) . $suffixText;
        }
    }
}
