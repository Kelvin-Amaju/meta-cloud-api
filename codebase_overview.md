# Netgrity WhatsApp API — Codebase Overview

## At a Glance

| Item | Value |
|---|---|
| **Language** | PHP (procedural) |
| **Database** | MySQL (via `mysqli`) |
| **External API** | Meta WhatsApp Cloud API (Graph API v25.0) |
| **UI** | Bootstrap 5 + Bootstrap Icons + Chart.js 4 |
| **Theme** | White primary / orange (`#ff6b00`) secondary / black CTAs |
| **Status** | Full feature set implemented — inbox, conversations, contacts, templates, broadcast, analytics |

---

## Directory Structure

```
whatsapp-api/
├── .env                     ← Live secrets (gitignored)
├── .env.example             ← Template
├── .htaccess                ← Apache rewrite rules (extensionless URLs)
├── index.php                ← Entry point (redirects to home)
├── home.php                 ← Dashboard: 8-card feature launcher + logs
├── send.php                 ← Send message form (text/template/media/interactive)
├── messages.php             ← Message history (10/page, status filter, badges)
├── inbox.php                ← Conversation list + thread view + reply + unread badge
├── contacts.php             ← Customer records CRUD + CSV import
├── templates.php            ← Template manager (list/sync/create/delete)
├── broadcast.php            ← Campaigns: CSV upload + synchronous send
├── analytics.php            ← Chart.js dashboards (time, status, business, template, top customers)
├── test.php                 ← Meta connectivity diagnostics
├── webhook.php              ← Meta webhook (GET verify + POST events, HMAC-signed)
├── callback.php             ← Meta OAuth callback flow (writes whatsapp_accounts — not wired to UI)
├── business_signup_callback.php
├── instruct.md              ← Step-by-step Meta-side setup guide for the user
├── get_meata.md             ← Reference: every requirement Meta must provide
├── config/
│   └── config.php           ← Loads .env → returns config array
├── includes/
│   ├── init.php             ← Single bootstrapper (require once per page)
│   ├── env.php              ← .env file parser
│   ├── database.php         ← mysqli connection ($mysqli global)
│   ├── helpers.php          ← response(), sanitize(), post(), get(), redirect(), dd()
│   ├── logger.php           ← logWebhook(), logRequest(), logError() → storage/logs/
│   ├── crypto.php           ← AES token encryption/decryption
│   ├── whatsapp.php         ← sendTextMessage()/sendTemplateMessage()/sendMediaMessage()/interactive via cURL
│   ├── webhook_parser.php   ← parseWebhook() normalizes Meta JSON payload
│   ├── webhook_security.php ← verifyWebhookSignature() (X-Hub-Signature-256)
│   ├── businesses.php       ← Business CRUD, getBusinessById() (decrypts token), getBusinessByPhoneNumberId()
│   ├── messages.php         ← saveOutgoingMessage(), saveInboundMessage(), getMessages(), getMessageStats(), updateMessageStatusByWamid()
│   ├── customers.php        ← Customer CRUD, findOrCreateCustomer(), importCustomersFromCsv(), getCustomerStats()
│   ├── conversations.php    ← syncCustomerConversation(), getConversations(), getThreadMessages(), markConversationRead(), getUnreadCount()
│   ├── templates.php        ← Template CRUD + syncTemplatesFromMeta() via Graph API
│   ├── broadcasts.php       ← createCampaign(), getCampaigns(), runCampaign() (synchronous)
│   ├── analytics.php        ← getMessagesOverTime(), getStatusBreakdown(), getTopCustomers(), ...
│   ├── tenants.php          ← Legacy compatibility shim
│   └── partials/
│       └── navbar.php       ← Shared navbar (uses $navBase for subdirectory pages, inbox unread badge)
├── assets/
│   └── css/app.css          ← Theme: --ng-primary/--ng-orange/--ng-black, .btn-ng-*, .navbar-ng, .card-ng
├── bin/
│   ├── run_migration.php    ← Applies sql/migration_full_features.sql to the live DB
│   ├── sync_templates.php   ← CLI: sync templates from Meta (all or one business)
│   ├── smoke_test.php       ← Renders every page in its own process to catch runtime errors
│   ├── smoke_one.php        ← Render a single page (used by smoke_test.php)
│   └── check_db.php         ← Checks for required tables/columns
├── storage/logs/            ← webhook.log, requests.log, errors.log
├── sql/
│   ├── netgrity_wa.sql      ← Base schema (businesses, business_messages, message_templates, broadcast_campaigns, ...)
│   ├── whatsapp-api.sql     ← Legacy schema + campaign-ready stubs
│   ├── migration_add_inbound_support.sql
│   ├── migration_add_business_display_name.sql
│   ├── migration_full_features.sql  ← customers, conversations, broadcast_recipients, business_messages.customer_id + backfills
│   └── seed_netgrity_wa.sql
├── api/                     ← Empty (api.php is 0 bytes) — REST endpoints not implemented
├── settings/                ← whatsapp.php (empty)
└── business/                ← index.php, add.php, edit.php, view.php, t.php
```

---

## Component Breakdown

### `config/config.php`
Returns a PHP array loaded from `.env` via `includes/env.php`.

| Key | Source Env Var |
|---|---|
| `api_version` | `META_API_VERSION` (default `v25.0`) |
| `phone_number_id` | `META_PHONE_NUMBER_ID` |
| `access_token` | `META_ACCESS_TOKEN` |
| `verify_token` | `META_VERIFY_TOKEN` |
| `meta_app_id` / `meta_app_secret` | `META_APP_ID` / `META_APP_SECRET` |
| `callback_url` | `CALLBACK_URL` |
| `db_*` | `DB_HOST / DB_USER / DB_PASS / DB_NAME` |

### `includes/init.php` — Bootstrapper
```
session_start → env.php → database.php → helpers.php → logger.php → businesses.php → tenants.php → whatsapp.php → webhook_parser.php
```
Feature modules (`messages.php`, `customers.php`, `conversations.php`, `templates.php`, `broadcasts.php`, `analytics.php`) are required per-page as needed.

### `includes/whatsapp.php` — API Client
`sendTextMessage(string $to, string $message, ?array $tenantCredentials): array`

- Builds `https://graph.facebook.com/{version}/{phone_number_id}/messages`
- Helpers: `sendTemplateMessage()`, `sendMediaMessage()`, `sendInteractiveButtonsMessage()`, `sendInteractiveListMessage()`
- Per-tenant credentials (decrypted access_token, phone_number_id) override the `.env` fallback.

### `includes/webhook_parser.php` + `includes/webhook_security.php`
`parseWebhook(string $payload): ?array` normalizes Meta JSON → `['id', 'from', 'type', 'body', 'business_phone_id', 'media_url', 'wamid', 'status', 'timestamp']`. `verifyWebhookSignature()` checks `X-Hub-Signature-256` using `META_APP_SECRET`.

### `webhook.php` — Meta Webhook Endpoint
- **GET** → `hub_mode`/`hub_verify_token` check → echoes `hub_challenge`
- **POST** → rejects without a valid HMAC signature → `parseWebhook()` → inbound messages saved via `saveInboundMessage()` (matched to business via `getBusinessByPhoneNumberId()`); status updates applied via `updateMessageStatusByWamid()`; replies `200 EVENT_RECEIVED`

### `includes/messages.php` — Message Data Layer
| Function | Description |
|---|---|
| `saveOutgoingMessage()` | INSERT outbound + sync customer/conversation |
| `saveInboundMessage()` | INSERT inbound (`status='received'`) + sync customer/conversation |
| `getMessages($filters, $page, $perPage)` | Paginated list, search/status/type/date filters |
| `getMessageStats()` | COUNT aggregates (total, today, delivered, read, ...) |
| `updateMessageStatusByWamid()` | Delivery/read/failed tracking from webhooks |

### `includes/customers.php` — Customer Records
| Function | Description |
|---|---|
| `findOrCreateCustomer()` | Get-or-create by business + normalized phone |
| `getCustomers()` / `getCustomerById()` | Listing/detail with stats |
| `createCustomer()` / `updateCustomer()` / `deleteCustomer()` | CRUD |
| `importCustomersFromCsv()` | CSV upload (phone, name, email, tags, notes) |
| `getCustomerStats()` | Totals, active 7-day, inbound counts |

### `includes/conversations.php` — Threads
| Function | Description |
|---|---|
| `syncCustomerConversation()` | Upsert conversation, bump preview, increment unread on inbound |
| `getConversations()` / `getConversation()` | List + single |
| `getThreadMessages()` | Message history for a conversation |
| `markConversationRead()` / `setConversationStatus()` | Open/close, clear unread |
| `getUnreadCount()` | Total unread (navbar badge) |

### `includes/templates.php` — Template Catalog
`getTemplatesForBusiness()` (filterable by status/type), `createTemplate()`, `deleteTemplate()`, and **`syncTemplatesFromMeta($businessId)`** — pulls the WABA's `message_templates` via Graph API and upserts into `message_templates` (also runnable via `bin/sync_templates.php`).

### `includes/broadcasts.php` — Campaigns
`createCampaign()`, `getCampaigns()`, `getCampaignById()`, `saveCampaignRecipient()`, `getCampaignRecipientCounts()`, `runCampaign()` — sends each recipient sequentially (text or approved template) with a 200 ms delay; per-recipient status/error stored in `broadcast_recipients`.

### `includes/analytics.php` — Dashboards
`getMessagesOverTime()`, `getStatusBreakdown()`, `getBusinessBreakdown()`, `getTypeBreakdown()`, `getTemplatePerformance()`, `getTopCustomers()` — all filtered by `days` + `business_id`.

---

## Database Schema

Core tables (see `sql/netgrity_wa.sql` + `sql/migration_full_features.sql`):

**`businesses`** — sender tenants. Per-business `waba_id`, `phone_number_id`, `display_name`, `display_phone_number`, `access_token` (AES-encrypted), `product_line`, `status`. Decrypted by `getBusinessById()`.

**`business_messages`** — all traffic. `direction` (`inbound`/`outbound`), `status` (`queued/sent/delivered/read/failed/received`), `type`/`message_type`, `body`, `media_url`/`media_type`, `wamid`, receipt timestamps, `customer_id` FK → `customers` (SET NULL).

**`customers`** *(added)* — `business_id` FK, `phone` (unique per business), `name`, `email`, `tags`, `notes`, `last_message_at`, `total_messages`.

**`conversations`** *(added)* — one per `business_id`+`customer_id`, `last_message_at`/`last_message_preview`/`last_direction`, `unread_count`, `status` (`open`/`closed`).

**`message_templates`** — Meta templates (name, language, body, category, status) populated by template sync.

**`broadcast_campaigns`** — campaign metadata (business, subject, message/template, recipient_count).

**`broadcast_recipients`** *(added)* — per-recipient `phone`, `status` (`pending/sent/failed`), `wamid`, `error_message`, `sent_at`.

---

## Current Status

### ✅ Working
- Config management via `.env`; per-business credentials with AES encryption
- Text / template / media / interactive sending
- Webhook verification (GET handshake + HMAC signature) and inbound/status intake
- Inbox, conversation threading, unread counts
- Customer records + CSV import, auto-sync from traffic
- Template manager with Meta sync (manual + CLI)
- Broadcast campaigns (CSV + synchronous send with status tracking)
- Analytics dashboards (Chart.js)
- Message history with filters/pagination (10/page)
- Unified theme + shared navbar across all pages
- `bin/run_migration.php` applied `migration_full_features.sql` to the live DB (7/7 OK)

### ⬜ Deferred / Planned
- REST API in `api/api.php` (JWT/API-key auth)
- Embedded Signup UI trigger on `business/add.php`; route `callback.php` into `businesses`
- `message_template_status_update` webhook parsing (automatic template approval sync)
- Inbound media download via Meta Media API
- Chatbot / auto-reply engine
- User authentication & role management
- Cleanup of orphaned helpers (`getLastOutgoingMessage()`, `tenants.php`)

---

## Key Observations & Gotchas

> [!WARNING]
> `includes/database.php` creates `$mysqli` as a **global variable**. All DB functions use `global $mysqli;`. Be aware of this when adding new modules.

> [!WARNING]
> `whatsapp.php` send helpers use `global $config;` and fall back to `.env` values when `$tenantCredentials` are not supplied. Prefer passing the decrypted per-business credentials array (from `getBusinessById()`).

> [!WARNING]
> The webhook POST handler **requires** a valid `X-Hub-Signature-256` (needs `META_APP_SECRET`). Without it, every real Meta POST is rejected with 401.

> [!NOTE]
> Access tokens are AES-encrypted at rest. Always read business credentials through `getBusinessById()` / `getBusinessByPhoneNumberId()`, which decrypt `access_token`.

> [!NOTE]
> The shared navbar uses relative links prefixed by `$navBase`. Pages in `business/` must set `$navBase = '../'` before requiring `includes/partials/navbar.php`, or nav links 404.

> [!NOTE]
> `sql/whatsapp-api.sql` is a legacy snapshot; the runtime schema lives in `sql/netgrity_wa.sql` + the `sql/migration_*.sql` files. Apply the full set on a fresh DB.

> [!TIP]
> `bin/smoke_test.php` renders every page in an isolated PHP process against the live DB — run it after schema or page changes.
