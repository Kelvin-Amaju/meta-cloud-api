# Integrating the `wapi/` WhatsApp API module into a host codebase

Precise, step-by-step checklist for mounting the self-contained `wapi/` module into an
existing PHP application with a similar structure. Verified against the current codebase
(all file names, function names, config keys, and table names below are exact).

---

## 0. Requirements

| Requirement | Value |
|---|---|
| PHP | >= 8.0 (uses `str_starts_with`, `str_contains`, null coalescing; verified on 8.5) |
| PHP extensions | `mysqli`, `openssl`, `json` |
| Composer packages | **none** |
| Web server | Apache with `mod_rewrite` (clean URLs), or a host router that falls through to `.php` files |
| External assets | Bootstrap 5.3.8 + Bootstrap Icons 1.10.5 (loaded via jsdelivr CDN in every page `<head>`; local only asset is `assets/css/app.css`) |

---

## 1. Files to copy

Copy these two things, nothing else:

1. The whole `wapi/` folder (entry points, `main/`, `_includes/`, `bin/`, `storage/`).
2. `assets/css/app.css` — the only runtime asset referenced by the module
   (as `../assets/css/app.css` from `wapi/main/`).

Do **not** copy the legacy tree: root pages, `includes/`, `business/`, `settings/`,
`config/`, `api/`, root `bin/`, or the root `.htaccess` (see §6 for the rules you actually need).
`wapi/_tools/` (port spec + converter) and `wapi/bin/` (CLI tools) are dev-only and can be omitted.

---

## 2. Database

### 2.1 Tables

Seven tables are used at runtime. Source: `sql/migration_full_features.sql`
(contains `USE netgrity_wa;` — change to your DB name, or strip the line).

| Table | Purpose | Runtime writes |
|---|---|---|
| `businesses` | WABA/phone/token per connected business; `access_token` stored `enc:v1:` AES-256-GCM | insert/update |
| `customers` | contacts | insert/update |
| `conversations` | inbox threads (per business + phone) | insert/update |
| `business_messages` | outbound + inbound message log | insert/update |
| `message_templates` | synced Meta templates | insert/update |
| `broadcast_campaigns` | campaigns | insert/update |
| `broadcast_recipients` | per-recipient status | insert/update |

`business_webhook_events` is created by the schema but is **not written at runtime**
(currently unused) — you may omit it.

### 2.2 Encryption key constraint

Stored `access_token` values are encrypted with the key in `config.inc.php`:
AES-256-GCM, prefixed `enc:v1:`. `decryptToken()` returns `null` (fails closed) if the
key does not match. If you migrate an existing `businesses` table, the key MUST be the
one that encrypted the data, or every stored token becomes unusable.

---

## 3. Configuration — `wapi/_includes/config.inc.php`

The file is a single `return [...]` array. Replace the values to match the host, or
replace the whole `return` with the host's own config source. Keys (exact names):

| Key | Purpose | Integration action |
|---|---|---|
| `db_host`, `db_port`, `db_user`, `db_pass`, `db_name` | mysqli connection (see §5) | set to host DB |
| `app_url` | absolute base URL, e.g. `https://host/` | **required** — used by OAuth callbacks to build `redirect_uri` |
| `callback_url` | legacy; kept for reference | can leave as-is (`app_url` drives OAuth) |
| `encryption_key` | base64 32-byte AES key | generate with `base64_encode(random_bytes(32))`; must match existing encrypted data |
| `api_version` | Meta Graph API version, e.g. `v25.0` | set to the version you use |
| `verify_token` | string Meta must echo in webhook handshake | set to your own secret |
| `access_token` | default/system Meta token (graph base) | set; per-business tokens live in `businesses.access_token` instead |
| `phone_number_id` | default sending phone | set |
| `meta_app_id`, `meta_app_secret` | Meta app creds | set for OAuth + webhook signature verification; **empty ⇒ webhook POSTs return 401 and OAuth fails fast** |
| `app_name`, `app_env`, `app_debug` | cosmetic/debug flags | optional |

---

## 4. Bootstrap behavior to expect

`wapi/_includes/functions.inc.php` (used by `main/` pages and `bin/`):

- `ob_start()`, `ini_set('display_errors','0')`, `date_default_timezone_set('Africa/Lagos')`,
  `ini_set('max_execution_time','900')`.
- `session_start()` **only outside CLI** (CLI tools reuse this bootstrap safely).
- Requires `config.inc.php`, `db.inc.php`, `sanitize_functions.inc.php`,
  `logger_functions.inc.php`; defines `csrf_token()` / `verify_csrf()` (present but
  currently unused by pages — the module is unauthenticated by design).
- Declares global functions: `writeLog`, sanitizers, and every `*_functions` module
  helper. It also creates the global `$mysqli` (`db.inc.php`) and `die('Database Connection Failed.')`
  on connect error.

`wapi/_includes/functions2.inc.php` (used by `webhook.php`, `callback.php`,
`business_signup_callback.php`): same requires minus buffering/session; no CSRF.

Integration notes:

- **Collisions.** The module expects to own the `$mysqli` global and its helper function
  names. If the host already defines `$mysqli` or any same-named helper, load the module
  in a context where it owns them, or rename one side.
- **Auth.** Put the host's auth guard in front of `wapi/main/`. Webhook/callback must
  stay public.
- **Session.** If the host already started a session, that's fine (module uses the active
  session); don't start a second one.

---

## 5. Database connection (`wapi/_includes/db.inc.php`)

- Creates `$mysqli = new mysqli($config['db_host'], $config['db_user'], $config['db_pass'], $config['db_name'])`
  and calls `set_charset('utf8mb4')`.
- On failure it calls `writeLog('errors.log', ...)` and `die('Database Connection Failed.')`.
- If the host has its own DB layer, either let the module keep this connection or
  point `db.inc.php` at the host's connection (it must be a `mysqli` global named `$mysqli`).

---

## 6. URL / routing

All module links are extensionless (`send`, `home`, `business_index`, `settings_whatsapp`).
Two options:

### 6a. Mount under its own path (recommended)

Serve the module at a prefix such as `https://host/wapi/main/send`. Use a `.htaccess`
scoped to that folder (or equivalent host rules):

```apache
RewriteEngine On
# optional: 301 .php -> extensionless
RewriteCond %{THE_REQUEST} \s/([^\s?]+?)\.php[\s?] [NC]
RewriteRule ^ /%1 [R=301,L]
# extensionless -> .php
RewriteCond %{REQUEST_FILENAME} !-f
RewriteCond %{REQUEST_FILENAME}.php -f
RewriteRule ^(.*)$ $1.php [L]
```

Relative links then work unchanged because all pages live flat in `wapi/main/`.

### 6b. Host router

Keep the module under a prefix and let the host router fall through to the `.php`
files under `wapi/main/`. Do not map `wapi/*` to a front controller that would break
the pages' relative form actions and fetch targets.

### Public endpoints that must resolve (and their URLs)

| File | URL | Purpose |
|---|---|---|
| `wapi/webhook.php` | `.../wapi/webhook` | Meta webhook (GET handshake + signed POST) |
| `wapi/callback.php` | `.../wapi/callback` | Meta OAuth `redirect_uri` (classic / Embedded Signup) |
| `wapi/business_signup_callback.php` | `.../wapi/business_signup_callback` | Embedded Signup popup callback |
| `wapi/index.php` | `.../wapi/` | 301 redirect to `main/home.php` |

Known polish item: `callback.php` redirects to `main/settings_whatsapp.php?connected=1&...`
(a `.php` URL). It still works via the 301 rule; prefer extensionless if you care.

---

## 7. Layout and assets

- Every `main/` page `<head>` loads Bootstrap 5.3.8 CSS, Bootstrap Icons 1.10.5, and
  `../assets/css/app.css`, plus Bootstrap bundle JS — all via jsdelivr CDN.
- Every page renders the module's own sidebar via `_includes/sidebar.inc.php`.
  The sidebar toggle script is **inline** in that file — do not reference `assets/js/app.js`
  (that file does not exist in the module).
- To use the host layout instead of the module sidebar, replace the sidebar include and
  the `<body>` shell in the pages; the page bodies themselves are self-contained.

---

## 8. Entry-point behavior

- **`webhook.php`**: GET → verifies `hub.verify_token` against config `verify_token` and
  echoes the challenge. POST → requires a valid `X-Hub-Signature-256` computed with
  `meta_app_secret`; invalid/missing ⇒ `401` and a log line. End-to-end POST handling is
  only testable once `meta_app_secret` is set.
- **`callback.php` / `business_signup_callback.php`**: exchange the Meta `code` for a token,
  persist to `businesses`, redirect back. Require `meta_app_id` + `meta_app_secret`.
- **`index.php`**: `header('Location: main/home.php', true, 301)`.

---

## 9. Verification (run from the app root)

All commands should exit 0 / print `[OK]`.

```bash
# syntax check
php -l wapi/webhook.php   # ...repeat per file, or lint the folder

# DB connectivity + tables
php wapi/bin/check_db.php           # all "EXISTS", prints business count

# crypto round-trip (7 checks)
php wapi/bin/test_crypto.php        # "7 passed, 0 failed"

# OAuth fails fast without creds (no network)
php wapi/bin/test_oauth.php         # error contains META_APP_ID

# token storage state
php wapi/bin/check_tokens.php

# render every page (catches undefined functions / DB errors)
php wapi/bin/smoke_test.php         # all 15 pages "[OK]"

# sidebar links
php wapi/bin/verify_sidebar.php     # linkBase=ok
```

Then verify in a browser: load a `main/` page, and hit `wapi/webhook` with
`?hub.mode=subscribe&hub.verify_token=<token>&hub.challenge=123` — it must return `123`.

Do **not** run `encrypt_existing_tokens.php`, `run_migration.php`, or `sync_templates.php`
against production data without review (they write to the DB / hit the network).

---

## 10. Known gotchas

- `storage/logs/` must be writable by PHP (`logger_functions.inc.php` writes
  `errors.log` / `webhook.log`).
- `run_migration.php` reads the schema from `../../sql/` relative to `wapi/bin/`
  (i.e. the host must have `sql/migration_full_features.sql` at the same relative spot,
  or edit the path).
- `db.inc.php` hard-dies with a generic message on connect failure — it will mask the
  host's own error page.
- Tokens are `enc:v1:` AES-256-GCM; changing `encryption_key` invalidates every stored
  token (returns `null` on decrypt, never plaintext).
- Empty `meta_app_secret` ⇒ webhook POSTs always 401; empty `meta_app_id`/`meta_app_secret`
  ⇒ OAuth fails fast with a clear error.
- Keep the module files UTF-8 **without** BOM (a BOM before `<?php` emits bytes and breaks
  `header()`/JSON responses).
- The module's legacy root `.htaccess` `^api(/.*)?$` rule points at a nonexistent file —
  do not copy it; only the rewrite rules in §6 are needed.
