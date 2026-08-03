# Netgrity WhatsApp API

A standalone, multi-tenant WhatsApp Business Cloud API platform built on **Core PHP + MySQL** for managing Meta-connected sender businesses, outbound/inbound messaging, inbox & conversations, contacts, template sync, broadcast campaigns, and analytics.

The app is the runtime face of the Netgrity SaaS: instead of wiring WhatsApp into each product individually, this module centralizes everything and exposes ready-to-use UI pages.

---

## Why this exists

Rather than embedding WhatsApp logic into each SaaS product (Hotel, School, Hospital, ERP, CRM), this module centralizes:

- Business onboarding & credential storage (AES-encrypted)
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
| Database | MySQL (mysqli) |
| Frontend | Bootstrap 5.3 + Bootstrap Icons + Chart.js 4 (CDN) |
| External API | Meta WhatsApp Business Cloud API (Graph API v25.0) |
| Theme | White primary / orange (`#ff6b00`) secondary / black CTAs |

---

## Features

- ✅ Business sender CRUD with AES-encrypted per-business credentials
- ✅ Meta Graph API text, template, media, and interactive message helpers
- ✅ Webhook verification (GET handshake + `X-Hub-Signature-256`) and inbound/status intake
- ✅ **Inbox** — conversation list, threaded view, reply, unread badge
- ✅ **Contacts** — customer records CRUD + CSV import, auto-sync from message traffic
- ✅ **Template manager** — list/sync from Meta/create/delete (`syncTemplatesFromMeta()`)
- ✅ **Broadcast** — campaign creation, CSV upload, synchronous send with per-recipient status
- ✅ **Analytics** — messages over time, status/type/business breakdowns, template performance, top customers
- ✅ Message history with filters, pagination (10/page), type/status badges
- ✅ Unified theme + shared navbar across all pages
- ✅ CLI tooling (`bin/`) for migration, template sync, and smoke testing

---

## Project Structure

```text
whatsapp-api/
├── business/          # business management pages (index/add/edit/view)
├── includes/          # shared DB, messaging, webhook, customers, conversations, templates, broadcasts, analytics
├── sql/               # base schema + feature migrations
├── config/            # runtime config bootstrap (.env)
├── assets/css/        # app.css theme
├── bin/               # CLI: run_migration, sync_templates, smoke_test
├── instruct.md        # step-by-step Meta-side setup guide
├── get_meata.md       # reference of everything required from Meta
├── home.php           # dashboard (8-card feature launcher)
├── inbox.php          # conversations + threaded inbox
├── contacts.php       # customer records + CSV import
├── templates.php      # template manager + Meta sync
├── broadcast.php      # campaigns + CSV upload + send
├── analytics.php      # Chart.js dashboards
├── send.php           # message composer (text/template/media/interactive)
├── messages.php       # message log UI
├── webhook.php        # webhook verification and event intake
└── test.php           # Meta connectivity diagnostics
```

---

## Getting Started

### Prerequisites

- PHP 8.1+
- MySQL 8+
- A Meta Developer App with the WhatsApp product added (see `instruct.md`)

### Setup

1. **Configure environment** — copy `.env.example` to `.env` and fill in the keys:

   ```env
   APP_NAME="Netgrity WhatsApp API"
   APP_ENV=development
   APP_DEBUG=true
   APP_URL=http://localhost

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

2. **Import the base schema**
   ```bash
   mysql -u root -p netgrity_wa < sql/netgrity_wa.sql
   ```

3. **Apply the feature migration** (creates `customers`, `conversations`, `broadcast_recipients`, links `business_messages.customer_id`, backfills from existing traffic):
   ```bash
   php bin/run_migration.php
   ```
   (Equivalent to running `sql/migration_full_features.sql` manually. Re-run only on a fresh DB.)

4. **Sanity-check pages**:
   ```bash
   php bin/smoke_test.php
   ```

5. **Meta-side setup** — follow `instruct.md` (§1–§7): create the app, fill `.env`, register the phone number, wire the webhook, subscribe to fields, create + approve templates, then sync. `test.php` validates connectivity; `bin/sync_templates.php` (or the Templates page button) populates the template catalog.

### Webhook configuration

- Callback URL: `https://your-domain.com/webhook` (GET handshake + HMAC-signed POSTs)
- Verify Token: matches `META_VERIFY_TOKEN`
- Subscribe to: `messages`, `message_template_status_update`, `message_template_quality_update`, `account_update`, `phone_number_quality_update`

---

## API Overview

The Meta-facing webhook is live at `/webhook`. The internal REST API (`api/api.php`) is currently an **empty placeholder** and is not yet implemented.

| Endpoint | Status | Description |
|---|---|---|
| `GET` / `POST` | `/webhook` | ✅ Meta webhook verification / event intake |

Planned REST endpoints (see `mvc/` planning docs): `POST /api/whatsapp/connect`, `POST /api/whatsapp/messages/text|template|media`, `GET /api/whatsapp/messages/{id}`, `GET|POST /api/whatsapp/templates`.

---

## Security

- All traffic over HTTPS
- Access tokens AES-encrypted at rest, decrypted only server-side (`getBusinessById()`)
- Webhook POSTs verified via `X-Hub-Signature-256` before trust (requires `META_APP_SECRET`)
- Prepared statements throughout the DB layer

---

## Current Runtime Coverage

| Area | Status |
|---|---|
| Shared PHP config bootstrap | ✅ |
| Business CRUD and sender account management | ✅ |
| Text / template / media / interactive sending | ✅ |
| Webhook verification + inbound/status intake | ✅ |
| Inbox, conversations, unread counts | ✅ |
| Customer records + CSV import | ✅ |
| Template manager + Meta sync | ✅ |
| Broadcast campaigns (synchronous) | ✅ |
| Analytics dashboards (Chart.js) | ✅ |
| Message history with filters/pagination | ✅ |
| REST API (`api/api.php`) | ⬜ Empty placeholder |
| Embedded Signup UI trigger | ⬜ Planned |

---

## Database Notes

The full runtime schema is `sql/netgrity_wa.sql` plus `sql/migration_full_features.sql`:

- `businesses` — per-tenant sender credentials (encrypted access_token, waba_id, phone_number_id)
- `business_messages` — traffic log with receipt timestamps, `customer_id` FK
- `customers` — customer records (unique `business_id`+`phone`)
- `conversations` — one per business+customer, unread counts, open/closed status
- `message_templates` — Meta template catalog (populated by sync)
- `broadcast_campaigns` / `broadcast_recipients` — campaign + per-recipient status

---

## License
