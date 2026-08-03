# ARCHITECTURE.md — Netgrity WhatsApp API

## 1. Overview

Procedural **Core PHP + MySQL** module that sits between the Netgrity SaaS products and Meta's
WhatsApp Business Cloud API. It owns the Meta credentials, outbound sends, inbound webhook intake,
inbox/conversations, customer records, templates, broadcasts, and analytics — so no consuming
product ever talks to Meta directly.

Two Meta-facing entry points:
- **`webhook.php`** — Meta pushes events here (messages, statuses).
- **OAuth callbacks** — `callback.php` (classic) and `business_signup_callback.php` (Embedded Signup popup).

## 2. Directory Tree

```text
whatsapp-api/
├── config/config.php          # .env → config array
├── includes/
│   ├── init.php               # bootstrap order for UI pages
│   ├── env.php, database.php, helpers.php, logger.php
│   ├── crypto.php             # AES-256-GCM token encryption (fail-closed)
│   ├── whatsapp.php           # send helpers (text/template/media/interactive)
│   ├── webhook_parser.php     # normalize Meta JSON
│   ├── webhook_security.php   # X-Hub-Signature-256 verification
│   ├── businesses.php         # business CRUD, getBusinessById() (decrypts token)
│   ├── messages.php           # message persistence + status updates
│   ├── customers.php          # customer CRUD + CSV import
│   ├── conversations.php      # threads + unread counts
│   ├── templates.php          # template CRUD + syncTemplatesFromMeta()
│   ├── broadcasts.php         # campaigns + synchronous run
│   ├── analytics.php          # dashboard queries
│   ├── oauth.php              # metaExchangeOauthCode() shared exchange
│   └── partials/navbar.php    # shared navbar ($navBase-aware)
├── assets/css/app.css         # theme (white/orange/black)
├── bin/                       # run_migration, sync_templates, smoke_test
├── sql/                       # netgrity_wa.sql + migration_full_features.sql
├── business/                  # business management pages
├── settings/whatsapp.php      # settings + Embedded Signup launcher
├── home.php, inbox.php, contacts.php, templates.php, broadcast.php,
├── analytics.php, send.php, messages.php, test.php, webhook.php
└── docs/ (API.md, SECURITY.md)
```

## 3. Request Flow

### Outbound (send.php / broadcast.php)
```
UI form → sendTextMessage()/sendTemplateMessage() → Meta Graph API
       → saveOutgoingMessage() → syncCustomerConversation()
```

### Inbound (webhook.php)
```
Meta POST (HMAC-verified) → parseWebhook()
  → message?   saveInboundMessage() → findOrCreateCustomer() → syncCustomerConversation()
  → status?    updateMessageStatusByWamid()
→ 200 EVENT_RECEIVED
```

### Embedded Signup (settings/whatsapp.php)
```
popup: business.facebook.com/business/embedded_signup/?redirect_uri=…&state=<business_id>
 → business_signup_callback.php?code=…&state=<business_id>
 → metaExchangeOauthCode() → businesses.access_token (encrypted), waba_id, status=active
 → close popup → settings/whatsapp
```

## 4. Data Model

| Table | Purpose | Key links |
|---|---|---|
| `businesses` | tenants/senders; encrypted token, waba_id, phone_number_id | pk `id` |
| `business_messages` | all traffic + receipt timestamps | `customer_id` → `customers` |
| `customers` | customer records, unique `(business_id, phone)` | `business_id` → `businesses` |
| `conversations` | one per `(business_id, customer_id)`, unread, open/closed | fks → businesses, customers |
| `message_templates` | Meta template catalog | `business_id` → `businesses` |
| `broadcast_campaigns` | campaign metadata | `business_id` → `businesses` |
| `broadcast_recipients` | per-recipient status | `campaign_id` → `broadcast_campaigns` |

Schema: `sql/netgrity_wa.sql` + `sql/migration_full_features.sql`.

## 5. Security Notes

See `docs/SECURITY.md`. Highlights: AES-256-GCM token encryption (fail-closed),
HMAC-verified webhooks, prepared statements everywhere.

## 6. Multi-Tenancy

Tenancy = **business row** in `businesses`. Every query is scoped by `business_id`
(filters default to a selected business or "all"). Per-business credentials override the
global `.env` fallback. Isolation hardening (per-business auth, scoped API keys) is planned.

## 7. Known Gaps

- `api/api.php` REST layer not implemented; UI pages are unauthenticated.
- `message_template_status_update` webhooks not yet parsed (template status sync is manual/CLI).
- Inbound media stored as id/URL only (no Media API download).
- `callback.php`/`business_signup_callback.php` rely on `APP_URL` for callback URLs.
