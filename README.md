# WhatsApp Integration Module

A standalone, multi-tenant WhatsApp Business Cloud API integration layer built on **Core PHP + MySQL**, designed to be consumed by multiple SaaS products (Hotel, School, Hospital, ERP, CRM, etc.) through a single shared service — with **no application talking to Meta directly**.

See [`ARCHITECTURE.md`](./ARCHITECTURE.md) for the full directory tree, data model, and flow diagrams.

---

## Why this exists

Instead of wiring WhatsApp into each SaaS product individually, this module centralizes:

- Tenant onboarding (Meta Embedded Signup)
- Credential storage & encryption
- Outbound messaging (text, template, media, interactive)
- Inbound delivery/read/failure status via webhooks
- Template lifecycle (create, sync, list)

Any product on the platform integrates by calling this API with a `tenant_id` — nothing WhatsApp-specific needs to live in the Hotel/School/Hospital codebases.

---

## Tech Stack

| Layer | Choice |
|---|---|
| Backend | Core PHP (no framework), PSR-4 autoloading |
| Database | MySQL |
| Frontend | HTML / CSS / vanilla JavaScript |
| External API | Meta WhatsApp Business Cloud API (Graph API) |
| Auth (own API) | JWT or API key (pluggable via `AuthMiddleware`) |
| Async processing | Queue worker / cron-driven job runner (DB-polling or Redis) |

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
- A Meta Developer App with WhatsApp product added
- App Secret + Embedded Signup configuration ID (from Meta App Dashboard)

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

| Method | Endpoint | Description |
|---|---|---|
| `POST` | `/api/whatsapp/connect` | Complete onboarding, store tenant credentials |
| `DELETE` | `/api/whatsapp/disconnect` | Disconnect tenant's WhatsApp account |
| `POST` | `/api/whatsapp/messages/text` | Send a text message |
| `POST` | `/api/whatsapp/messages/template` | Send a template message |
| `POST` | `/api/whatsapp/messages/media` | Send image/document/audio/video |
| `GET` | `/api/whatsapp/messages/{id}` | Get message status |
| `GET` | `/api/whatsapp/templates` | List cached templates |
| `POST` | `/api/whatsapp/templates/sync` | Sync templates from Meta |
| `GET` / `POST` | `/webhook` | Meta webhook verification / event intake |

Full request/response contracts: [`docs/API.md`](./docs/API.md).

### Response format

**Success**
```json
{
  "success": true,
  "message": "Message sent successfully.",
  "data": { "message_id": "wamid.HBgL..." }
}
```

**Failure**
```json
{
  "success": false,
  "error": {
    "code": "META_API_ERROR",
    "message": "The WhatsApp Cloud API rejected the request.",
    "details": { "meta_code": 131026 }
  }
}
```

---

## Security

- All traffic over HTTPS
- Access tokens encrypted at rest, never exposed to the browser
- Webhook payloads verified via `X-Hub-Signature-256` before trust
- Webhook events stored first, processed asynchronously (idempotent)
- Own endpoints require API auth (JWT/API key) + rate limiting

Details: [`docs/SECURITY.md`](./docs/SECURITY.md) and [`ARCHITECTURE.md §7`](./ARCHITECTURE.md#7-security-checklist).

---

## Roadmap

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
