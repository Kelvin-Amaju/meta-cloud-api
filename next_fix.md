# Next Fix — Status

- [x] **Encryption fail-open** — `getEncryptionKey()` now logs loudly on a missing/invalid key; `encryptToken()` **throws** rather than storing plaintext; `decryptToken()` returns `null` on missing key or tampered payload instead of the raw ciphertext. (`includes/crypto.php`)
- [x] **`.env.example`** — rewritten (deduplicated) with `APP_ENCRYPTION_KEY` (generation command included), `META_APP_ID`, `META_APP_SECRET`, `CALLBACK_URL`, and all runtime keys.
- [x] **OAuth persistence target** — `callback.php` and the new `business_signup_callback.php` persist tokens into the **`businesses`** table (the app's actual source of truth) via the shared `metaExchangeOauthCode()` in `includes/oauth.php`. The unused `whatsapp_accounts` table is no longer written to.
- [x] **`settings/whatsapp.php` + `business_signup_callback.php`** — both built. Settings lists businesses with connection/encryption status and a per-business **Connect via Meta** Embedded Signup launcher; the signup callback exchanges the code, updates the business row, and closes the popup. A **Settings** item was added to the shared navbar.
- [x] **`decryptToken()` silent failure** — fixed (see first item): returns `null` on bad/tampered value, fail-closed.
- [x] **Broadcast/campaign sending logic** — already implemented (`includes/broadcasts.php` `runCampaign()`), done in the feature build-out.
- [x] **Docs referenced by README** — created `docs/API.md`, `docs/SECURITY.md`, `ARCHITECTURE.md`; README rewritten to match the real codebase and link them (no more 404s).

Remaining known gaps (tracked in `summary.md` / `ARCHITECTURE.md` §7): REST API in `api/api.php`,
`message_template_status_update` webhook parsing, inbound media download, and UI authentication.
