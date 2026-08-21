<?php

namespace App\API\Services\payments;

/** Verifies KCB Buni's SHA256withRSA callback signature. */
class KcbWebhookVerifier
{
    public function verify(array $headers, string $rawBody): bool
    {
        $signature = $headers['Signature'] ?? $headers['signature'] ?? '';
        $keyPath = defined('KCB_PUBLIC_KEY_PATH') ? (string) KCB_PUBLIC_KEY_PATH : '';
        if ($signature === '' || $rawBody === '' || $keyPath === '' || !is_file($keyPath)) {
            return false;
        }

        $publicKey = openssl_pkey_get_public((string) file_get_contents($keyPath));
        if ($publicKey === false) {
            return false;
        }
        $decodedSignature = base64_decode($signature, true);
        if ($decodedSignature === false) {
            return false;
        }

        return openssl_verify($rawBody, $decodedSignature, $publicKey, OPENSSL_ALGO_SHA256) === 1;
    }

    public function shouldEnforce(): bool
    {
        return defined('KCB_VERIFY_CALLBACK_SIGNATURE')
            ? (bool) KCB_VERIFY_CALLBACK_SIGNATURE
            : (defined('KCB_ENVIRONMENT') && KCB_ENVIRONMENT === 'production');
    }
}
