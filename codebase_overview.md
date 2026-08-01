# Netgrity WhatsApp API — Codebase Overview

## At a Glance

| Item | Value |
|---|---|
| **Language** | PHP (procedural) |
| **Database** | MySQL (via `mysqli`) |
| **External API** | Meta WhatsApp Cloud API (Graph API v25.0) |
| **UI** | Bootstrap 5.2.3 + Bootstrap Icons |
| **Status** | Milestone 1 complete — foundation ready for feature expansion |

---

## Two Layers in One Repo

The workspace contains **two overlapping implementation layers**:

| Layer | Root | Purpose |
|---|---|---|
| **Procedural (active)** | `c:\laragon\www\netgrity\whatsapp-api\` | Working integration: send messages, webhook, message log UI |
| **MVC (planned)** | `mvc/` | Blueprint for the full multi-tenant SaaS module (no code files yet, only scaffolding docs) |

---

## Directory Structure

```
whatsapp-api/
├── .env                  ← Live secrets (gitignored)
├── .env.example          ← Template
├── .htaccess             ← Apache rewrite rules
├── config/
│   └── config.php        ← Loads .env → returns config array
├── includes/
│   ├── init.php          ← Single bootstrapper (require once per page)
│   ├── env.php           ← .env file parser
│   ├── database.php      ← mysqli connection ($mysqli global)
│   ├── helpers.php       ← response(), sanitize(), post(), get(), redirect(), dd()
│   ├── logger.php        ← logWebhook(), logRequest(), logError() → storage/logs/
│   ├── whatsapp.php      ← sendTextMessage() via cURL → Meta Graph API
│   ├── webhook_parser.php← parseWebhook() normalizes Meta JSON payload
│   └── messages.php      ← DB CRUD: saveOutgoingMessage(), getMessages(), getMessageStats()
├── storage/logs/         ← webhook.log, requests.log, errors.log
├── sql/
│   └── whatsapp-api.sql  ← Schema: messages, tenants + ALTER stubs
│
│   — Public pages —
├── index.php             ← Entry point (redirects)
├── home.php              ← Dashboard UI
├── send.php              ← Send message form + handler
├── messages.php          ← Message history listing (filterable, paginated)
├── webhook.php           ← Meta webhook endpoint (GET verification + POST events)
├── test.php              ← API connectivity test page
├── mock_meta.php         ← Local mock of Meta's /messages endpoint
│
└── mvc/                  ← Future MVC rewrite (planning docs only)
    ├── README.md
    ├── architecture.md
    ├── composer.json
    └── app/              ← Empty scaffold dirs (Controllers, Services, Repositories…)
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
| `db_*` | `DB_HOST / DB_USER / DB_PASS / DB_NAME` |

---

### `includes/init.php` — Bootstrapper
Single `require_once` that loads all modules in order:
```
session_start → env.php → database.php → helpers.php → logger.php → whatsapp.php → webhook_parser.php
```
Pages only need one line: `require_once 'includes/init.php';`

---

### `includes/whatsapp.php` — API Client
`sendTextMessage(string $to, string $message): array`

- Builds the Meta Graph API URL: `https://graph.facebook.com/{version}/{phone_number_id}/messages`
- Posts a JSON payload with `messaging_product: whatsapp`, `type: text`
- Returns `['success' => bool, 'status' => int, 'data' => array]`
- A commented-out mock URL is available for local testing

---

### `includes/webhook_parser.php` — Parser
`parseWebhook(string $payload): ?array`

Normalizes Meta's deeply-nested JSON to a flat internal format:
```php
['id' => '...', 'from' => '234...', 'type' => 'text', 'body' => '...']
```
Returns `null` for non-message events (status updates, etc.).

---

### `webhook.php` — Meta Webhook Endpoint
- **GET** → verifies `hub_mode=subscribe` + `hub_verify_token` → echoes `hub_challenge`
- **POST** → reads raw body → `logWebhook()` → `parseWebhook()` → responds `200 EVENT_RECEIVED`
- Currently has a `// TODO: handle incoming message` stub (save to DB, auto-reply)

---

### `includes/messages.php` — Data Layer
| Function | Description |
|---|---|
| `saveOutgoingMessage($phone, $message, $wamid, $allowReply)` | INSERT to `messages` table |
| `getLastOutgoingMessage($phone)` | Latest message per phone number |
| `getMessages($filters, $page, $perPage)` | Paginated list with search, reply-type & date filters |
| `getMessageStats()` | COUNT aggregates: total, today, one-way, two-way |

---

### `messages.php` (public) — Message History UI
Full Bootstrap table with:
- Stats header (4 cards)
- Search / type / date-range filter form
- Paginated table (20 per page, ±2 page window)
- Click-to-open Bootstrap modal with full message details
- `timeAgo()` helper for human-readable timestamps

---

### Database Schema (`sql/whatsapp-api.sql`)

**`messages`** — outbound messages (currently in use)

| Column | Type |
|---|---|
| `id` | `BIGINT AUTO_INCREMENT` |
| `wa_message_id` | `VARCHAR(120) UNIQUE` |
| `direction` | `ENUM('inbound','outbound')` |
| `phone`, `type`, `body`, `status` | varchar / longtext |
| `media_id`, `raw_payload` | JSON |
| `created_at` | `TIMESTAMP` |

**`tenants`** — multi-tenant ready (planned)

Stores per-tenant Meta credentials (WABA ID, phone_number_id, access_token, verify_token), billing plan, and status.

> [!NOTE]
> The schema SQL also includes `ALTER TABLE` stubs to add `tenant_id` FK to `contacts`, `messages`, `templates`, `campaigns`, and `logs` — these reference tables not yet created.

---

### `mvc/` — Future MVC Architecture (Planning Stage)

Per `mvc/README.md`, this is a **standalone multi-tenant WhatsApp module** intended to be consumed by other SaaS products (Hotel, School, Hospital, ERP, CRM). Key design goals:

- One Meta App → multiple tenants, each with their own WABA + phone number
- Meta Embedded Signup for onboarding (no manual token copy-paste)
- AES-256-GCM encrypted token storage
- Store-then-process async webhook handling
- JWT/API key auth on its own endpoints

**Planned API endpoints:**
| Method | Endpoint |
|---|---|
| `POST` | `/api/whatsapp/connect` |
| `POST` | `/api/whatsapp/messages/text` |
| `POST` | `/api/whatsapp/messages/template` |
| `POST` | `/api/whatsapp/messages/media` |
| `GET`  | `/api/whatsapp/templates` |
| `POST` | `/api/whatsapp/templates/sync` |

---

## Current Status

### ✅ Working (Milestone 1)
- Config management via `.env`
- MySQL database connectivity
- `sendTextMessage()` via Meta Cloud API
- Webhook verification (GET handshake)
- Webhook payload reception + logging
- Message persistence (outbound)
- Message history UI with filters/pagination
- API connectivity test page (`test.php`)

### ⬜ Deferred / Planned
- Incoming message persistence (webhook `// TODO` stub)
- Inbound/outbound conversation threads
- Template messaging
- Media messaging (image, audio, video, document)
- Interactive messages (buttons, lists)
- Delivery/read receipt tracking
- Broadcast / bulk messaging
- Multi-tenant isolation
- User authentication & role management
- Analytics dashboard
- Chatbot / auto-reply engine

---

## Key Observations & Gotchas

> [!WARNING]
> `includes/database.php` creates `$mysqli` as a **global variable**. All DB functions use `global $mysqli;`. Be aware of this when adding new modules.

> [!WARNING]
> `whatsapp.php` also uses `global $config;` from a top-level `require`. If `sendTextMessage()` is called from a context where `$config` wasn't loaded in the same scope, it will silently use empty defaults.

> [!NOTE]
> The current `messages` table in `database.php`/`messages.php` has a different schema from `sql/whatsapp-api.sql`. The SQL file has `wa_message_id`, `direction`, `body`, `media_id`, `raw_payload`; the INSERT in `includes/messages.php` uses `phone`, `message`, `wamid`, `allow_reply`. These need to be reconciled before migration.

> [!NOTE]
> `webhook.php` does **not** verify the `X-Hub-Signature-256` HMAC signature from Meta. The MVC layer plans to add this, but the current procedural layer is vulnerable to spoofed webhook POSTs.

> [!TIP]
> `api/api.php` is **completely empty** (0 bytes). It appears to be a placeholder for the REST API entry point.
