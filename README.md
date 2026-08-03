# Netgrity WhatsApp API

A standalone, multi-tenant WhatsApp Business Cloud API platform built on **Core PHP + MySQL** for managing Meta-connected sender businesses, outbound/inbound messaging, inbox & conversations, contacts, template sync, broadcast campaigns, and analytics.

The module is the runtime face of the Netgrity SaaS: instead of wiring WhatsApp into each product (Hotel, School, Hospital, ERP, CRM), this module centralizes everything and provides ready-to-use UI pages. Consuming products never talk to Meta directly.

Docs: [ARCHITECTURE.md](./ARCHITECTURE.md) · [docs/API.md](./docs/API.md) · [docs/SECURITY.md](./docs/SECURITY.md) · [instruct.md](./instruct.md) (Meta-side setup) · [get_meata.md](./get_meata.md) (Meta requirements)

---

## Why this exists

Centralizes in one place:

- Business onboarding & credential storage (AES-256-GCM encrypted)
- Outbound messaging (text, template, media, interactive)
- Inbound webhook intake (messages + delivery/read/failed statuses)
- Inbox & conversation threading with unread tracking
- Customer records + CSV import, auto-synced from traffic
- Template lifecycle (list, create, sync from Meta, delete)
- Broadcast campaigns (CSV recipients, per-recipient status)
- Analytics dashboards

---

## Tech Stack

| Layer | Choice |
|---|---|
| Backend | Core PHP (procedural), Bootstrap 5 UI |
| Database | MySQL (mysqli, prepared statements) |
| Frontend | Bootstrap 5.3 + Bootstrap Icons + Chart.js 4 (CDN) |
| External API | Meta WhatsApp Business Cloud API (Graph API v25.0) |
| Theme | White primary / orange (`#ff6b00`) secondary / black CTAs |

---

## Features

- ✅ Business sender CRUD with per-business encrypted credentials
- ✅ Text / template / media / interactive message helpers
- ✅ Webhook verification (GET handshake + `X-Hub-Signature-256`) and inbound/status intake
- ✅ **Inbox** — conversation list, threaded view, reply, unread badge
- ✅ **Contacts** — customer records CRUD + CSV import, auto-sync from traffic
- ✅ **Template manager** — list/sync from Meta/create/delete (`syncTemplatesFromMeta()`)
- ✅ **Broadcast** — campaigns, CSV upload, synchronous send, per-recipient status
- ✅ **Analytics** — messages over time, status/type/business breakdowns, template performance, top customers
- ✅ **Embedded Signup** — Meta onboarding popup launched from Settings, token saved per business
- ✅ Message history with filters, pagination (10/page), type/status badges
- ✅ CLI tooling (`bin/`) for migration, template sync, smoke testing

---

## Project Structure

```text
whatsapp-api/
├── business/            # business management pages
├── settings/whatsapp.php# settings + Embedded Signup launcher
├── includes/            # DB, messaging, webhook, customers, conversations, templates, broadcasts, analytics, oauth
├── sql/                 # base schema + feature migrations
├── config/              # runtime config bootstrap (.env)
├── assets/css/          # app.css theme
├── bin/                 # CLI: run_migration, sync_templates, smoke_test
├── docs/                # API.md, SECURITY.md
├── instruct.md          # step-by-step Meta-side setup guide
├── get_meata.md         # reference of everything required from Meta
├── home.php             # dashboard (8-card feature launcher)
├── inbox.php            # conversations + threaded inbox
├── contacts.php         # customer records + CSV import
├── templates.php        # template manager + Meta sync
├── broadcast.php        # campaigns + CSV upload + send
├── analytics.php        # Chart.js dashboards
├── send.php             # message composer (text/template/media/interactive)
├── messages.php         # message log UI
├── webhook.php          # webhook verification and event intake
├── callback.php         # classic OAuth callback
├── business_signup_callback.php # Embedded Signup popup callback
└── test.php             # Meta connectivity diagnostics
```

Full tree with every file: [ARCHITECTURE.md](./ARCHITECTURE.md#2-directory-tree).

---

## Getting Started

### Prerequisites

- PHP 8.1+
- MySQL 8+
- A Meta Developer App with the WhatsApp product added (see `instruct.md`)

### Setup

1. **Configure environment** — copy `.env.example` to `.env` and fill in the keys
   (`DB_*`, `META_APP_ID`, `META_APP_SECRET`, `META_VERIFY_TOKEN`, `META_ACCESS_TOKEN`,
   `META_PHONE_NUMBER_ID`, and the required **`APP_ENCRYPTION_KEY`** — see below).

   ```env
   APP_NAME="Netgrity WhatsApp API"
   APP_ENV=development
   APP_URL=http://localhost
   APP_ENCRYPTION_KEY=            # base64 32-byte key — REQUIRED (fail-closed)
   META_API_VERSION=v25.0
   META_VERIFY_TOKEN=
   META_ACCESS_TOKEN=
   META_PHONE_NUMBER_ID=
   META_APP_ID=
   META_APP_SECRET=
   CALLBACK_URL=http://localhost/callback.php
   DB_HOST=localhost
   DB_PORT=3306
   DB_NAME=netgrity_wa
   DB_USER=root
   DB_PASS=
   ```

   Generate the encryption key with:
   ```bash
   php -r "echo base64_encode(openssl_random_pseudo_bytes(32)), PHP_EOL;"
   ```
   Without it, access tokens are **refused at save time** (no plaintext is ever stored).

2. **Import the schema**
   ```bash
   mysql -u root -p netgrity_wa < sql/netgrity_wa.sql
   ```

3. **Apply the feature migration** (creates `customers`, `conversations`,
   `broadcast_recipients`, links `business_messages.customer_id`, backfills from traffic):
   ```bash
   php bin/run_migration.php
   ```

4. **Sanity-check pages**:
   ```bash
   php bin/smoke_test.php
   ```

5. **Meta-side setup** — follow `instruct.md` (§1–§7): create the app, register the phone
   number, wire `/webhook` + subscribe to fields, create + approve templates, then sync via
   the Templates page or `bin/sync_templates.php`. `test.php` validates connectivity.

---

## Webhook Configuration

- Callback URL: `https://your-domain.com/webhook`
- Verify Token: matches `META_VERIFY_TOKEN`
- Subscribe to: `messages`, `message_template_status_update`, `message_template_quality_update`, `account_update`, `phone_number_quality_update`

POSTs are HMAC-verified with `META_APP_SECRET` (`X-Hub-Signature-256`) — see [docs/SECURITY.md](./docs/SECURITY.md).

---

## API Overview

The Meta-facing webhook is live at `/webhook`. The internal REST API (`api/api.php`) is an
**empty placeholder** and is not yet implemented — see [docs/API.md](./docs/API.md).

| Endpoint | Status | Description |
|---|---|---|
| `GET` / `POST` | `/webhook` | ✅ Meta webhook verification / event intake |
| `GET` | `/settings/whatsapp` | ✅ Settings + Embedded Signup launcher |
| `GET` | `/business_signup_callback.php` | ✅ Embedded Signup popup callback |
| `GET` | `/callback.php` | ✅ Classic OAuth callback |

Planned REST endpoints: `POST /api/whatsapp/connect`, `POST /api/whatsapp/messages/text|template|media`,
`GET /api/whatsapp/messages/{id}`, `GET|POST /api/whatsapp/templates`.

---

## Security

- Access tokens AES-256-GCM encrypted at rest; encryption **fails closed** (no plaintext stored)
- Webhook POSTs verified via `X-Hub-Signature-256` before trust
- Prepared statements throughout the DB layer; output HTML-escaped
- Full notes: [docs/SECURITY.md](./docs/SECURITY.md)

---

## Database

| Table | Purpose |
|---|---|
| `businesses` | tenants/senders (encrypted token, waba_id, phone_number_id) |
| `business_messages` | traffic log with receipt timestamps, `customer_id` FK |
| `customers` | customer records, unique `(business_id, phone)` |
| `conversations` | per business+customer, unread counts, open/closed |
| `message_templates` | Meta template catalog (populated by sync) |
| `broadcast_campaigns` / `broadcast_recipients` | campaigns + per-recipient status |

Schema: `sql/netgrity_wa.sql` + `sql/migration_full_features.sql`.

---

## License
