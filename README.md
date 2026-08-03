# Netgrity WhatsApp API

A standalone, multi-tenant WhatsApp Business Cloud API integration layer built on **Core PHP + MySQL**, designed to be consumed by multiple SaaS products (Hotel, School, Hospital, ERP, CRM, etc.) through a single shared service — with **no application talking to Meta directly**.

See [`ARCHITECTURE.md`](./ARCHITECTURE.md) for the full directory tree, data model, and flow diagrams.

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

- ✅ Multi-tenant by design — one WhatsApp Business Account per tenant
- ✅ Meta Embedded Signup for onboarding (no manual token copy-paste)
- ✅ Encrypted token storage at rest (AES-256-GCM)
- ✅ Text, template, and media message sending
- ✅ Template sync from Meta (approval status, category, language)
- ✅ Webhook-driven delivery/read/failure tracking
- ✅ Standardized success/error JSON response envelope
- ✅ Store-then-process webhook handling (no inline processing on request thread)
- ✅ Framework-agnostic — pluggable into any Core PHP SaaS

---

## Project Structure

```
whatsapp-integration/
├── api/            # PHP backend (controllers, services, repositories, models)
├── frontend/        # Connect UI, dashboard, template manager
├── docs/           # API reference, onboarding guide, security notes
├── ARCHITECTURE.md
└── README.md
```

Full tree with every file: see [`ARCHITECTURE.md`](./ARCHITECTURE.md#1-directory-tree).

---

## Getting Started

### Prerequisites

- PHP 8.1+
- MySQL 8+
- A Meta Developer App with the WhatsApp product added (see `instruct.md`)

### Setup

1. **Clone / copy the module** into your platform, e.g. `modules/whatsapp-integration/`.

2. **Configure environment**
   ```bash
   cp api/.env.example api/.env
   ```
   Fill in:
   ```
   DB_HOST=
   DB_NAME=
   DB_USER=
   DB_PASS=

   META_APP_ID=
   META_APP_SECRET=
   META_GRAPH_VERSION=v20.0
   META_WEBHOOK_VERIFY_TOKEN=

   TOKEN_ENCRYPTION_KEY=      # 32-byte key for AES-256-GCM
   ```

3. **Run migrations**
   ```bash
   mysql -u root -p your_db < api/database/migrations/001_create_tenants_table.sql
   mysql -u root -p your_db < api/database/migrations/002_create_whatsapp_accounts_table.sql
   mysql -u root -p your_db < api/database/migrations/003_create_whatsapp_templates_table.sql
   mysql -u root -p your_db < api/database/migrations/004_create_whatsapp_messages_table.sql
   mysql -u root -p your_db < api/database/migrations/005_create_webhook_events_table.sql
   ```

4. **Configure the webhook** in the Meta App Dashboard:
   - Callback URL: `https://your-domain.com/webhook`
   - Verify Token: matches `META_WEBHOOK_VERIFY_TOKEN`
   - Subscribe to: `messages`, `message_template_status_update`

5. **Point a consuming app at the API**, passing its `tenant_id` on every request.

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

| Phase | Feature | Status |
|---|---|---|
| 1 | Project skeleton & config | ⬜ |
| 2 | Meta API HTTP client | ⬜ |
| 3 | Embedded Signup onboarding | ⬜ |
| 4 | Webhook verification & async processing | ⬜ |
| 5 | Template sync | ⬜ |
| 6 | Send template messages | ⬜ |
| 7 | Delivery/read/failure tracking | ⬜ |
| 8 | Media messaging | ⬜ |
| 9 | Interactive messages (buttons/lists) | ⬜ |
| 10 | Operational hardening & tests | ⬜ |

---

## License
