# implement.md — Applying the Netgrity Coding Conventions to `whatsapp-api`

Implementation plan for migrating the existing `whatsapp-api` module to the structure,
bootstrap, naming and AJAX conventions documented in `wapi/netgrity_structure.md`.

**No authentication.** This module is intentionally unauthenticated — there is no login,
no user/role tables, no `role_check.inc.php`, no generated `_menu/`, no `MM_*` session keys.
Sessions are used only for lightweight CSRF tokens and flash messages.

The plan is **incremental and reversible**: each phase lands independently, keeps the app
working, and is verified with `bin/` smoke checks before the next phase starts.

---

## 1. Goal & Scope

Adopt the reference conventions (structure, bootstrap, naming, AJAX, config) so the
WhatsApp module matches the house style and can be maintained like every other Netgrity app.

**What we adopt as-is:**
- Folder layout: `main/`, `ajax/`, `_includes/`, `assets/`, `dbBackUp/`
- "Every file is a complete request handler"; no router / MVC / ORM
- Front-door bootstrap `_includes/functions.inc.php`
- All writes go through `ajax/ajax_<entity>_<action>.php` endpoints
- AJAX contract: plain text replies (`echo 1;` / `echo 0|error`) or HTML fragments
- File naming: lowercase `snake_case`, shared code ends in `_functions.inc.php` / `.inc.php`
- Lookup helpers `get_<entity>(...)`, config out of logic, one connection, output escaping

**What we do NOT adopt (this project is unauthenticated):**
- `welcome.php` / `check.php` / `check21.php` / `check22.php` login + access-denied pages
- `role_check.inc.php` menu-based authorization gate
- `users`, `roles`, `menu_items`, `role_menu` tables
- `build.php` + generated `_menu/menu_<role>.inc.php` per-role sidebars
- `MM_*` session keys / session timeout guard

**What we keep (deliberate deviations — see §10):**
- **Prepared statements** (`mysqli`), not `sprintf` + `GetSQLValueString` (reference rule 20
  already prefers prepared statements)
- **AES-256-GCM** token encryption (`crypto.php` stays, renamed)
- `.env` config loader (re-exposed through a single `_includes/config.inc.php`)
- PHP 8.1+ language features already in use
- The Meta **webhook** and **OAuth callbacks** stay public and use the lightweight public
  bootstrap (`functions2.inc.php`)

---

## 2. Target Directory Structure

```
whatsapp-api/                        <- web root
├── index.php                        <- entry point → main/home (as today)
├── webhook.php                      <- PUBLIC: Meta webhook (functions2 bootstrap)
├── callback.php                     <- PUBLIC: classic OAuth callback (functions2)
├── business_signup_callback.php     <- PUBLIC: Embedded Signup popup callback (functions2)
├── main/                            <- UI pages (one file per screen)
│   ├── home.php                     <- was root home.php
│   ├── send.php                     <- was root send.php (writes moved to ajax/)
│   ├── inbox.php                    <- was root inbox.php
│   ├── contacts.php                 <- was root contacts.php
│   ├── templates.php                <- was root templates.php
│   ├── broadcast.php                <- was root broadcast.php
│   ├── analytics.php                <- was root analytics.php
│   ├── messages.php                 <- was root messages.php
│   ├── settings_whatsapp.php        <- was settings/whatsapp.php
│   ├── business_index.php           <- was business/index.php
│   ├── business_add.php             <- was business/add.php
│   ├── business_edit.php            <- was business/edit.php
│   ├── business_view.php            <- was business/view.php
│   ├── lookup_phone_numbers.php     <- was business/lookup_phone_numbers.php
│   └── test.php                     <- was root test.php (Diagnostics)
├── ajax/                            <- ALL AJAX endpoints (business logic lives here)
│   ├── ajax_send_message.php        <- freeform / template / media / interactive sends
│   ├── ajax_inbox_reply.php         <- reply from the thread view
│   ├── ajax_inbox_markread.php      <- markConversationRead
│   ├── ajax_inbox_status.php        <- open/close conversation
│   ├── ajax_customer_save.php       <- create/update customer
│   ├── ajax_customer_delete.php     <- delete customer
│   ├── ajax_customer_import.php     <- CSV import
│   ├── ajax_template_sync.php       <- syncTemplatesFromMeta
│   ├── ajax_template_create.php     <- createTemplate
│   ├── ajax_template_delete.php     <- deleteTemplate
│   ├── ajax_broadcast_create.php    <- createCampaign (+ CSV upload)
│   ├── ajax_broadcast_run.php       <- runCampaign
│   ├── ajax_business_save.php       <- create/update business (server-side token lookup)
│   ├── ajax_business_delete.php     <- deleteBusiness
│   ├── ajax_business_status.php     <- toggle status
│   ├── ajax_business_lookup.php     <- server-side getWabaPhoneNumbers
│   ├── ajax_analytics_data.php      <- chart data feed (JSON)
│   └── export_messages.php          <- optional XLSX message export
├── _includes/                       <- shared code (NEVER duplicate inside pages)
│   ├── functions.inc.php            <- front-door bootstrap for main/ + ajax/ (see §5)
│   ├── functions2.inc.php           <- public bootstrap for webhook/callbacks
│   ├── config.inc.php               <- single source of truth (loads .env, DB names, Meta creds)
│   ├── env.inc.php                  <- was includes/env.php (kept internal to config.inc.php)
│   ├── db.inc.php                   <- was includes/database.php ($mysqli global)
│   ├── crypto_functions.inc.php     <- was includes/crypto.php
│   ├── sanitize_functions.inc.php   <- test_input(), reyon_h(), fix_phone(), redirect(), json_out()
│   ├── logger_functions.inc.php     <- was includes/logger.php
│   ├── whatsapp_functions.inc.php   <- was includes/whatsapp.php (send helpers)
│   ├── business_functions.inc.php   <- was includes/businesses.php
│   ├── message_functions.inc.php    <- was includes/messages.php
│   ├── customer_functions.inc.php   <- was includes/customers.php
│   ├── conversation_functions.inc.php <- was includes/conversations.php
│   ├── template_functions.inc.php   <- was includes/templates.php
│   ├── broadcast_functions.inc.php  <- was includes/broadcasts.php
│   ├── analytics_functions.inc.php  <- was includes/analytics.php
│   ├── webhook_functions.inc.php    <- was includes/webhook_parser.php + webhook_security.php
│   ├── oauth_functions.inc.php      <- was includes/oauth.php
│   ├── head.inc.php                 <- <head> block (login / public pages)
│   ├── head_in.inc.php              <- <head> block for main/ pages
│   ├── sidebar.inc.php              <- static sidebar (was partials/navbar.php, no roles)
│   ├── messaging_limit_banner.inc.php <- was partials/messaging_limit_banner.php
│   ├── js_in.inc.php                <- JS bundle + common init (from partials/navbar.php JS)
│   └── SimpleXLSXWriterLite.php     <- NEW if message/analytics exports are required
├── assets/
│   ├── css/app.css                  <- keep theme (white/orange/black)
│   ├── js/app.js                    <- NEW: shared page JS
│   └── vendor/                      <- bootstrap/icons/chartjs bundles (local, not CDN)
├── dbBackUp/                        <- NEW: dated daily backups (YYYY-MM-DD/)
├── bin/                             <- keep CLI tooling (requires functions.inc.php)
├── sql/                             <- schema + migrations
├── storage/logs/                    <- keep runtime logs
├── api/                             <- REMOVE empty placeholder (superseded by ajax/)
├── business/  settings/             <- REMOVE after migration of their pages to main/
└── docs/                            <- keep API.md / SECURITY.md (update paths)
```

> Existing root pages move **into** `main/`; the old root files and `business/` + `settings/`
> folders are deleted only after every link (sidebar, redirects, `bin/verify_sidebar.php`)
> has been updated to the new paths.

---

## 3. File-by-File Migration Map

| Current | Target | Action |
|---|---|---|
| `index.php` | `index.php` | Keep (entry → `main/home`) |
| `home.php` | `main/home.php` | Move; prefix bootstrap lines; strip POST (if any) |
| `send.php` | `main/send.php` + `ajax/ajax_send_message.php` | Split: page renders composer, POST logic → AJAX |
| `inbox.php` | `main/inbox.php` + `ajax/ajax_inbox_*.php` | Split reply/read/status into AJAX |
| `contacts.php` | `main/contacts.php` + `ajax/ajax_customer_*.php` | Split CRUD + CSV import into AJAX |
| `templates.php` | `main/templates.php` + `ajax/ajax_template_*.php` | Split sync/create/delete into AJAX (also fixes the nested-form bug) |
| `broadcast.php` | `main/broadcast.php` + `ajax/ajax_broadcast_*.php` | Split create/upload/run into AJAX |
| `analytics.php` | `main/analytics.php` + `ajax/ajax_analytics_data.php` | Split data feed into AJAX (JSON), fix duplicate `<!DOCTYPE>` |
| `messages.php` | `main/messages.php` + `ajax/export_messages.php` | Split; clamp pagination |
| `test.php` | `main/test.php` | Move; wrap in `main/` bootstrap |
| `settings/whatsapp.php` | `main/settings_whatsapp.php` | Move; keep Embedded Signup launcher |
| `business/index.php` | `main/business_index.php` + `ajax/ajax_business_delete.php` | Fix delete (reads wrong POST field) |
| `business/add.php` | `main/business_add.php` + `ajax/ajax_business_lookup.php` | Remove client-side token |
| `business/edit.php` | `main/business_edit.php` + `ajax/ajax_business_save.php` | Fix duplicate `waba_id` id |
| `business/view.php` | `main/business_view.php` | Mask token in view |
| `business/lookup_phone_numbers.php` | `main/lookup_phone_numbers.php` | Server-side only |
| `webhook.php` | `webhook.php` | Rewrite on `functions2.inc.php`; drop duplicate logger require |
| `callback.php` | `callback.php` | Rewrite on `functions2.inc.php` |
| `business_signup_callback.php` | `business_signup_callback.php` | Rewrite on `functions2.inc.php` |
| `config/config.php` | `_includes/config.inc.php` | Move + expand (env, db, meta creds, app url) |
| `includes/database.php` | `_includes/db.inc.php` | Move (global `$mysqli`) |
| `includes/env.php` | `_includes/env.inc.php` | Move (used only by config.inc.php) |
| `includes/helpers.php` | `_includes/sanitize_functions.inc.php` | Replace `sanitize()/post()/get()` with `test_input()` + `reyon_h()` |
| `includes/logger.php` | `_includes/logger_functions.inc.php` | Move |
| `includes/crypto.php` | `_includes/crypto_functions.inc.php` | Move (AES-GCM stays) |
| `includes/whatsapp.php` | `_includes/whatsapp_functions.inc.php` | Move; rename `sendTextMessage` → `whatsapp_send_text()` etc. (§7) |
| `includes/businesses.php` | `_includes/business_functions.inc.php` | Move; rename functions to `get_business_*()` |
| `includes/messages.php` | `_includes/message_functions.inc.php` | Move |
| `includes/customers.php` | `_includes/customer_functions.inc.php` | Move |
| `includes/conversations.php` | `_includes/conversation_functions.inc.php` | Move |
| `includes/templates.php` | `_includes/template_functions.inc.php` | Move |
| `includes/broadcasts.php` | `_includes/broadcast_functions.inc.php` | Move; add `set_time_limit(0)` |
| `includes/analytics.php` | `_includes/analytics_functions.inc.php` | Move |
| `includes/webhook_parser.php` + `webhook_security.php` | `_includes/webhook_functions.inc.php` | Merge |
| `includes/oauth.php` | `_includes/oauth_functions.inc.php` | Move |
| `includes/tenants.php` | — | **Delete** (orphaned shim) |
| `includes/init.php` | `_includes/functions.inc.php` + `functions2.inc.php` | Replace |
| `includes/partials/navbar.php` | `_includes/sidebar.inc.php` + `_includes/js_in.inc.php` | Split (static, no roles) |
| `includes/partials/messaging_limit_banner.php` | `_includes/messaging_limit_banner.inc.php` | Move |
| `api/api.php` | — | **Delete** (empty placeholder; `ajax/` covers it) |
| `bin/*.php` | `bin/*.php` | Update requires to `_includes/functions.inc.php`; keep CLI usage |

---

## 4. Database

**No new tables.** The reference's auth/menu tables (`users`, `roles`, `menu_items`,
`role_menu`) are NOT created because this project is unauthenticated.

The only schema change carried over from the plan is the existing bug fix: add `'text'` to
`broadcast_campaigns.payload_type` enum so free-form-text campaigns can be created.

```sql
-- sql/migration_002_broadcast_text_payload.sql
ALTER TABLE `broadcast_campaigns`
  MODIFY `payload_type` enum('template','text','media','interactive') NOT NULL DEFAULT 'template';
```

---

## 5. Bootstrap Design

### 5.1 `_includes/functions.inc.php` — front door for `main/` + `ajax/`

Order of operations (reference §3.1, minus the auth steps):

1. `ob_start();`
2. `ini_set('display_errors', '0');` (respect `APP_DEBUG`), `date_default_timezone_set('Africa/Lagos');`,
   `ini_set('max_execution_time', '900');`
3. Start session **only if not already started** and **never in CLI** — used for CSRF token
   + flash messages only
4. `require_once __DIR__ . '/config.inc.php';` (loads `.env` once → `$config` global)
5. Connect once: `require_once __DIR__ . '/db.inc.php';` (`$mysqli`)
6. Define helpers: `test_input()`, `reyon_h()`, `fix_phone()`, `redirect()`, `json_out()`,
   and a `csrf_token()` / `verify_csrf()` pair

Every `main/` page then starts exactly like the reference:

```php
<?php require_once('../_includes/functions.inc.php');
$dispage = 'Inbox';
```

### 5.2 `_includes/functions2.inc.php` — public endpoints (`webhook.php`, `callback.php`, `business_signup_callback.php`)

Same config + DB + helpers, **without** `session_start()` and the CSRF/flash helpers.
Used so the Meta webhook and OAuth callbacks never depend on the UI bootstrap.
(Reference §3.2.)

### 5.3 Sidebar (no roles)

The current `includes/partials/navbar.php` (sidebar + inline JS) becomes a **static**
`_includes/sidebar.inc.php`:
- same nav item list (Dashboard, Inbox, Contacts, Templates, Broadcast, Messages, Analytics,
  Business, Settings) — **no role filtering**
- same unread-count badge (guarded with `function_exists('get_unread_count')`)
- the toggle/backdrop JS moves to `assets/js/app.js`
- `$navBase` handling is no longer needed because every `main/` page lives at the same depth
  (links are `../ajax/...` / `../assets/...` only)

---

## 6. No Authentication — What Replaces It

Because the module is unauthenticated by design (matches the current app and the user's
decision), the reference's session-auth layer is dropped entirely:

| Reference feature | Replacement in whatsapp-api |
|---|---|
| `welcome.php` + login AJAX | none — pages are directly reachable |
| `MM_*` session keys | none |
| `role_check.inc.php` gate | none — every `main/` page is accessible |
| `build.php` + `_menu/` | none — static `sidebar.inc.php` |
| session timeout guard | none |

**Remaining protection (cheap, not auth):**
- **CSRF token** per session, generated in `functions.inc.php` and verified on every
  `ajax/*` write endpoint. Sessions stay on purely for this + flash messages. This closes
  the cross-site form-submit exposure without adding logins.
- All the existing security that is not auth-related stays: AES-GCM tokens, HMAC-verified
  webhook, prepared statements, output escaping.

---

## 7. Naming Conventions Applied

### 7.1 Functions (reference §4.1 / §5.B)

| Old (whatsapp-api) | New (convention) |
|---|---|
| `sendTextMessage()` | `whatsapp_send_text()` |
| `sendTemplateMessage()` | `whatsapp_send_template()` |
| `sendMediaMessage()` | `whatsapp_send_media()` |
| `sendInteractiveButtonsMessage()` | `whatsapp_send_interactive_buttons()` |
| `sendInteractiveListMessage()` | `whatsapp_send_interactive_list()` |
| `getBusinessById()` | `get_business($id)` |
| `getActiveBusinesses()` | `get_businesses('active')` |
| `getMessages()` | `get_messages($filters, $page, $per)` |
| `getCustomers()` | `get_customers($filters, $page, $per)` |
| `getConversations()` | `get_conversations($filters, $page, $per)` |
| `getTemplatesForBusiness()` | `get_templates($business_id)` |
| `getCampaigns()` | `get_campaigns($filters)` |
| `syncCustomerConversation()` | `sync_customer_conversation()` |
| `updateMessageStatusByWamid()` | `update_message_status_by_wamid()` |

Lookup helpers keep the `get_<entity>($id)` → name-or-`'N/A'` pattern where the UI needs a
display label (e.g. `get_business_name($id)`).

### 7.2 Files

`ajax/ajax_<entity>_<action>.php`, `_includes/<domain>_functions.inc.php`,
`main/<feature>.php`. Lowercase `snake_case`, no spaces. `business/` and `settings/`
folders are removed after migration (§2).

### 7.3 Sanitize / escape

- `test_input($v)` = `trim` + `strip_tags` + `stripslashes` + `htmlspecialchars(...)` (reference)
- `reyon_h($v)` = `htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8')` for **all** output
- New `fix_phone($v)` = strip non-digits (used everywhere a WhatsApp number is read)
- Input is sanitized on read; **output is always escaped at the point of echo** (this closes
  the send.php/analytics XSS findings: escape every `json_encode` into inline JS with
  `JSON_HEX_TAG|JSON_HEX_AMP|JSON_HEX_APOS|JSON_HEX_QUOT`, or better, move all chart data to
  `ajax/ajax_analytics_data.php` so no inline JSON remains)

---

## 8. AJAX Conversion Plan (feature by feature)

Convention: page never POSTs synchronously for a write; forms `preventDefault()` and call
`ajax/ajax_<entity>_<action>.php`. Reply is `echo 1;` (success) or `echo 0|message` (failure);
client checks `data.trim() > 0` and shows `toastr`. Endpoints that return HTML fragments
(e.g. inbox thread refresh, campaign recipient list) return the fragment directly.

| Feature | AJAX endpoints | Page becomes |
|---|---|---|
| Send message | `ajax_send_message.php` (mode: freeform/template/media/interactive) | `main/send.php` |
| Inbox | `ajax_inbox_reply.php`, `ajax_inbox_markread.php`, `ajax_inbox_status.php` | `main/inbox.php` |
| Contacts | `ajax_customer_save.php`, `ajax_customer_delete.php`, `ajax_customer_import.php` | `main/contacts.php` |
| Templates | `ajax_template_sync.php`, `ajax_template_create.php`, `ajax_template_delete.php` | `main/templates.php` |
| Broadcast | `ajax_broadcast_create.php` (+CSV), `ajax_broadcast_run.php` | `main/broadcast.php` |
| Business | `ajax_business_save.php`, `ajax_business_delete.php`, `ajax_business_status.php`, `ajax_business_lookup.php` | `main/business_*.php` |
| Analytics | `ajax_analytics_data.php` (JSON for Chart.js) | `main/analytics.php` |
| Exports | `export_messages.php` (GET, xlsx optional) | links from `main/messages.php` |

**While moving POST handlers out of pages, fix the known bugs they carry:**
- `business delete` reads the wrong POST field → read `business_id`, drop dead handler
- `broadcast` free-form text enum → add `'text'` to `payload_type` (see §4)
- `templates` nested form → resolved by splitting sync into `ajax_template_sync.php`
- `inbox` id-space confusion → separate `conversation_id` (GET) vs `customer_id` (POST)
- `broadcast run` → `set_time_limit(0)` + resumable `running` status

---

## 9. Webhook, Callbacks & CLI

- `webhook.php`: rewritten on `functions2.inc.php`. GET handshake unchanged. POST unchanged
  (HMAC via `webhook_functions.inc.php::verify_webhook_signature()`). Remove the duplicate
  `require logger` (current `webhook.php:8,10`).
- `callback.php` / `business_signup_callback.php`: rewritten on `functions2.inc.php`;
  exchange via `oauth_functions.inc.php::meta_exchange_oauth_code()`.
- `bin/` scripts: change the top two lines to
  `require __DIR__ . '/../_includes/functions.inc.php';` — `functions.inc.php` must
  **skip `session_start()` when `php_sapi_name() === 'cli'`** so CLI usage is unaffected.
- Keep `bin/smoke_test.php`; extend it to render every `main/` page and hit every `ajax/`
  endpoint with a valid CSRF token.

---

## 10. Deviations & Rationale (what we do NOT copy)

| Reference rule | Decision | Why |
|---|---|---|
| Auth: `welcome.php`, `MM_*`, `role_check.inc.php`, `build.php`, `_menu/` | **Not adopted** | Project is intentionally unauthenticated (user decision) |
| `sprintf` + `GetSQLValueString` | **Keep prepared statements** | Reference rule 20 itself says "prefer prepared statements"; existing code is already correct |
| Single connection, `mysqli_select_db()` to switch schemas | Keep single connection; schema switch not needed | whatsapp-api uses one DB (`netgrity_wa`) |
| `md5()` password hashing | N/A (no auth) | No passwords stored at all |
| `die(mysqli_error())` in helpers | **Never adopt** | Leaks SQL on failure; use a shared error handler (reference §7 debt list) |
| `test_input()` strips tags on every input | Adopt, but keep raw body for message content | WhatsApp message bodies must be stored raw; escape at output with `reyon_h()` |
| jQuery for all interactivity | **Use vanilla JS** | Current app has no jQuery dependency; reference's jQuery is a legacy choice, not a rule |
| `sn`/`<entity>ID` primary keys | **Keep `id`** | Existing schema + FKs reference `id`; renaming would churn every query and break `netgrity_wa.sql` |
| Report 4-layer pattern | Adopt for analytics if exports are added | Charts already get data from `analytics_functions.inc.php`; wire to `ajax/` JSON feed |

---

## 11. Phased Rollout

Order is chosen so each phase is shippable and verifiable.

| Phase | Work | Verify with |
|---|---|---|
| **0. Scaffold** | Create `_includes/` (`env.inc.php`, `config.inc.php`, `db.inc.php`, `sanitize_functions.inc.php`, `functions.inc.php`, `functions2.inc.php`, `sidebar.inc.php`) + empty `main/`, `ajax/`, `dbBackUp/`; add the broadcast payload migration | `php -l`; `bin/check_db.php` |
| **1. Rename helpers** | Move + rename all `includes/*.php` → `_includes/*_functions.inc.php`; update all callers in pages in the same commit | `php -l` all files; `bin/smoke_test.php` |
| **2. Move pages** | Move root/business/settings pages into `main/`; update sidebar links + redirects + `bin/verify_sidebar.php`; delete `business/`, `settings/`, `api/`, `tenants.php` | click-through; smoke_test |
| **3. AJAX-ify writes** | Convert each POST handler (§8) to `ajax/ajax_*.php`; add CSRF token + verify; fix the carried bugs (delete field, broadcast enum, nested form, inbox ids) | manual + curl smoke per endpoint |
| **4. Exports (optional)** | `export_messages.php`; four-layer pattern if xlsx needed | download a sample export |
| **5. Security pass** | Fix remaining findings (token exposure, XSS via inline JSON, duplicate DOCTYPE, pagination clamp, duplicate `waba_id`) | `bin/smoke_test.php`, manual security review |
| **6. Docs & cleanup** | Update `README.md`, `ARCHITECTURE.md`, `codebase_overview.md`, `docs/API.md`, `docs/SECURITY.md` to new paths; delete leftover shims | grep for old paths/functions |

Rollback: each phase is a normal commit; since pages are moved (not rewritten) and requires
are updated in the same commit, the app stays runnable at every point.

---

## 12. Risks & Gotchas

- **Webhook must NOT load the UI bootstrap** — `webhook.php`/callbacks MUST use
  `functions2.inc.php`; if they load `functions.inc.php` they will start sessions and
  CSRF plumbing that Meta's requests don't need, and can buffer/redirect the response.
- **Relative paths after the move** — `main/` pages sit one level deeper; every
  `require`/link/asset path must change from `includes/...` to `../_includes/...` and
  `assets/...` to `../assets/...`. This is the highest-churn, highest-risk step (phase 2).
- **`bin/` under CLI** — `functions.inc.php` needs a `php_sapi_name() === 'cli'` guard to
  skip `session_start()`; otherwise every CLI script warns and the session layer is useless.
- **CSRF without auth** — the CSRF token must be exposed to JS (meta tag or `data-` attribute
  on `<body>`), verified in every `ajax/*` write endpoint, and excluded from public endpoints.
  It is the only protection against cross-site form submits, so it should not be skipped.
- **Schema drift** — keep `sql/netgrity_wa (2).sql` as the canonical full schema; apply the
  broadcast-payload migration on top of it, and retire the stale `sql/netgrity_wa.sql` /
  `whatsapp-api.sql` references in the README.
