# SECURITY.md — Netgrity WhatsApp API

Security model, failure behavior, and deployment checklist.

---

## 1. Credential Storage

- **At rest:** every access token is encrypted with **AES-256-GCM** before it hits MySQL.
  Ciphertexts are stored as `enc:v1:<base64(JSON{iv,tag,ct})>` in `businesses.access_token`
  (`includes/crypto.php`).
- **Key management:** the key is the base64-encoded 32-byte `APP_ENCRYPTION_KEY` in `.env`.
  It is never written to the DB or the UI. Generate one with:
  ```bash
  php -r "echo base64_encode(openssl_random_pseudo_bytes(32)), PHP_EOL;"
  ```
- **Fail closed (no plaintext):**
  - `encryptToken()` **throws** if the key is missing/invalid — a business save is rejected
    rather than storing a token in plaintext.
  - `decryptToken()` returns **`null`** on a missing key or a tampered/corrupt payload instead
    of leaking the ciphertext back to the caller. Corrupted tokens surface as "missing token"
    errors in template sync / broadcast rather than being sent to Meta as garbage.
- **Decryption boundaries:** credentials are only decrypted inside `getBusinessById()` /
  `getBusinessByPhoneNumberId()` and are never echoed into page output.

## 2. Webhook Integrity

`webhook.php` rejects every POST without a valid `X-Hub-Signature-256`:

```
HMAC-SHA256(raw body, META_APP_SECRET)
```

- Implemented in `includes/webhook_security.php`.
- GET verification still requires `hub_verify_token` to match `META_VERIFY_TOKEN`.
- **Do not disable this check** — it is the only thing preventing forged inbound messages
  from being stored.

## 3. Database

- All queries use **prepared statements** (`mysqli`).
- `$mysqli` is a global connection created in `includes/database.php`; feature modules use
  `global $mysqli;`.
- Output is HTML-escaped with `htmlspecialchars()` in every view.

## 4. Deployment Checklist

- [ ] HTTPS only — no cleartext HTTP in production (webhook + callbacks must be reachable)
- [ ] `APP_ENCRYPTION_KEY` set and backed up (rotating it invalidates stored tokens)
- [ ] `META_APP_SECRET` set (webhook signature verification)
- [ ] `.env` outside the web root, gitignored
- [ ] Permissions on `storage/logs/` restricted; logs never expose tokens (log the ciphertext shape only)
- [ ] App Review / business verification done before live traffic

## 5. Known Limitations

- The internal REST API (`api/api.php`) is not implemented, so no API-key/JWT auth exists yet —
  all UI pages are currently unauthenticated. Add authentication before exposing this module publicly.
- Embedded Signup tokens are the short-lived exchange result; Meta's token-refresh/rotation
  flow is not yet automated.
