# Netgrity WhatsApp API

A standalone, multi-tenant WhatsApp Business Cloud API integration layer built on **Core PHP + MySQL** for managing Meta-connected sender businesses, outbound message delivery, webhook receipt tracking, and template sync.

This repository now reflects the runtime shape used in the current app: shared environment config, per-business Meta credentials, webhook status processing, and schema support for media/interactivity metadata.

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

- ✅ Multi-tenant business sender model with database-backed business profiles
- ✅ Meta Graph API text, template, media, and interactive message helpers
- ✅ Webhook parsing for inbound messages and delivery/read receipt updates
- ✅ Message log persistence in `business_messages` with receipt timestamps
- ✅ Template catalog support via `message_templates`
- ✅ New campaign-ready schema support for bulk messaging flows
- ✅ Shared config loader using `.env` values and the app's PHP config bootstrap
- ✅ Business and sender management screens for onboarding and editing

---

## Project Structure

```text
whatsapp-api/
├── business/          # business management pages
├── includes/          # shared DB, messaging, webhook, template, and env helpers
├── sql/               # schema and seed scripts
├── config/            # runtime config bootstrap
├── callback.php       # Meta OAuth callback flow
├── webhook.php        # webhook verification and event intake
├── send.php           # message composer UI
├── messages.php       # message log UI
└── README.md
```

---

## Getting Started

### Prerequisites

- PHP 8.1+
- MySQL 8+
- A Meta Developer App with WhatsApp product added
- App Secret + Embedded Signup configuration ID (from Meta App Dashboard)

### Setup

1. **Configure environment**
   Copy the provided `.env` values into your local environment and fill in the application-specific keys:

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

2. **Import the schema**
   ```bash
   mysql -u root -p netgrity_wa < sql/netgrity_wa.sql
   mysql -u root -p netgrity_wa < sql/whatsapp-api.sql
   ```

3. **Seed demo data** if needed:
   ```bash
   mysql -u root -p netgrity_wa < sql/seed_netgrity_wa.sql
   ```

4. **Configure the webhook** in the Meta App Dashboard:
   - Callback URL: `https://your-domain.com/webhook`
   - Verify Token: matches `META_VERIFY_TOKEN`
   - Subscribe to: `messages`, `message_template_status_update`

5. **Use the callback page** for Meta OAuth/token exchange:
   - The current callback implementation reads its credentials from the shared app config and `.env`
   - The redirect target is now driven by `CALLBACK_URL`

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

## Current Runtime Coverage

| Area | Status |
|---|---|
| Shared PHP config bootstrap | ✅ |
| Business CRUD and sender account management | ✅ |
| Meta text sending | ✅ |
| Template message wrapper | ✅ |
| Media sending helpers | ✅ |
| Interactive button/list message helpers | ✅ |
| Webhook parsing for message and status events | ✅ |
| Delivery/read receipt timestamp persistence | ✅ |
| Bulk campaign-ready schema | ✅ |

---

## Database Notes

The current runtime expects these data-shape additions to be present in the live MySQL database:

- `business_messages.delivered_at`
- `business_messages.read_at`
- `business_messages.media_url`
- `business_messages.media_type`
- `business_messages.interactive_payload`
- `broadcast_campaigns`

The migration for the new message metadata and campaign table is provided in [sql/whatsapp-api.sql](sql/whatsapp-api.sql).

---

## License
