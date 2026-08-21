# Production 2FA

Kingsway supports independent enrolled factors per staff account. SMS and email are the practical default factors; staff may additionally enroll a TOTP authenticator (Google Authenticator, Microsoft Authenticator, or another RFC 6238-compatible app) and WhatsApp.

Passkeys are supported through WebAuthn using the configured `PASSKEY_RP_ID`. They use the device's PIN, fingerprint, face unlock, or security key and are verified by the server's stored public key. The production domain must be used consistently; changing the RP ID invalidates existing registrations.

The login sequence is:

1. Password verification creates a short-lived server-side challenge.
2. The user selects one of the enrolled factors.
3. OTP/TOTP or a recovery code verifies that challenge.
4. The challenge is marked verified and consumed exactly once before a JWT is issued.

Short-lived access-token renewal is silent while the refresh session remains active. This prevents an OTP prompt in the middle of active work every hour. The idle-session policy remains authoritative: after the configured inactivity window, the session is logged out and the next interactive login requires MFA again. High-risk operations can separately request step-up MFA.

Factor records, challenges, and audit events are normalized in:

- `user_two_factor_methods`
- `user_two_factor_challenges`
- `user_two_factor_audit_events`
- `user_2fa_backup_codes`

Set `TFA_ENCRYPTION_KEY` to a dedicated 32-byte hex key in the environment:

```bash
openssl rand -hex 32
```

Never commit this key or the TOTP secret. WhatsApp authentication must use an approved provider authentication template in production; configure `WHATSAPP_2FA_TEMPLATE_ID` and do not use plain-text fallback for production authentication.

Recovery codes recover a lost second factor. They do not replace the password-reset process. A forgotten password must use the normal single-use, expiring password-reset link or the school's verified account-recovery procedure; after the password is changed, an enrolled factor or recovery code is still required where MFA is enabled.

The 30-second TOTP format is compatible with Google and Microsoft Authenticator because both can enroll third-party RFC 6238/TOTP accounts through the QR code or manual secret.
