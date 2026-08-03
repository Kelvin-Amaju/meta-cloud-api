# API.md — Netgrity WhatsApp API

This document describes the externally-visible contracts of the WhatsApp API module:
the **Meta webhook** (live) and the planned internal **REST API** (not yet implemented — `api/api.php` is an empty placeholder).

---

## 1. Meta Webhook (`/webhook`)

Meta posts events to this endpoint. It is the only live network endpoint in the module.

### 1.1 Verification (GET)

Meta calls this during webhook setup:

| Param | Expects |
|---|---|
| `hub.mode` | `subscribe` |
| `hub.verify_token` | the `META_VERIFY_TOKEN` from `.env` |
| `hub.challenge` | echoed back verbatim on success |

Failure returns `403`.

### 1.2 Event intake (POST)

Every POST **must** include a valid `X-Hub-Signature-256` header computed over the raw body
with the app secret (`META_APP_SECRET`). Requests without a valid signature are rejected with `401`.

On success the endpoint replies `200 EVENT_RECEIVED`.

Supported events (normalized by `includes/webhook_parser.php`):

**Inbound message**

```json
{
  "id": "wamid.HBgL...",
  "from": "2348012345678",
  "type_name": "text",
  "body": "Hello",
  "media_url": null,
  "media_type": null,
  "business_phone_id": "1270265856164756",
  "timestamp": 1700000000
}
```

Saved via `saveInboundMessage()` onto the business matched by `business_phone_id`
(`getBusinessByPhoneNumberId()`), then routed into `customers` + `conversations`.

**Delivery / read / failed status**

```json
{
  "type": "status",
  "wamid": "wamid.HBgL...",
  "status": "delivered",
  "business_phone_id": "1270265856164756",
  "timestamp": 1700000000
}
```

Applied via `updateMessageStatusByWamid()` (`sent`, `delivered`, `read`, `failed`).

---

## 2. Message Sending Helpers

Used by the UI (`send.php`, `broadcast.php`) and available to any PHP module that includes `includes/init.php`.

| Function | Signature | Notes |
|---|---|---|
| `sendTextMessage` | `(string $to, string $message, ?array $tenantCredentials = null)` | Free-form text (24h window only) |
| `sendTemplateMessage` | `(string $to, string $templateName, string $languageCode, array $components, ?array $tenantCredentials = null)` | Approved templates (outside 24h window) |
| `sendMediaMessage` | `(string $to, string $mediaType, string $mediaUrl, ?string $caption, ?array $tenantCredentials = null)` | image/document/audio/video |
| `sendInteractiveButtonsMessage` | `(string $to, string $header, string $body, string $footer, array $buttons, ?array $tenantCredentials = null)` | up to 3 buttons, 25-char labels |
| `sendInteractiveListMessage` | `(string $to, string $header, string $body, string $footer, string $listButton, array $sections, ?array $tenantCredentials = null)` | list + dynamic rows |

`$tenantCredentials` is the decrypted business row from `getBusinessById()` (keys `access_token`, `phone_number_id`, `api_version`). When omitted, the `.env` fallback is used.

Return value:

```json
{ "success": true, "status": 200, "data": { "id": "wamid.HBgL..." } }
```

or

```json
{ "success": false, "status": 4xx, "error": "Meta error text", "data": null }
```

---

## 3. Planned REST API (not implemented)

`api/api.php` is an **empty placeholder**. The intended contract (from the planning docs):

| Method | Endpoint | Description |
|---|---|---|
| `POST` | `/api/whatsapp/connect` | Onboard a business, store tenant credentials |
| `DELETE` | `/api/whatsapp/disconnect` | Disconnect a business's WhatsApp account |
| `POST` | `/api/whatsapp/messages/text` | Send a text message |
| `POST` | `/api/whatsapp/messages/template` | Send a template message |
| `POST` | `/api/whatsapp/messages/media` | Send image/document/audio/video |
| `GET` | `/api/whatsapp/messages/{id}` | Get message status |
| `GET` | `/api/whatsapp/templates` | List cached templates |
| `POST` | `/api/whatsapp/templates/sync` | Sync templates from Meta |

Planned auth: `Authorization: Bearer <api-key-or-jwt>` + rate limiting. Response envelope is
`{ "success": true, "data": ... }` / `{ "success": false, "error": { "code", "message", "details" } }`.

---

## 4. Template Sync

`syncTemplatesFromMeta(int $businessId)` pulls `GET /{waba_id}/message_templates` and upserts
into `message_templates`. Runnable from the Templates page or via CLI:

```bash
php bin/sync_templates.php            # all businesses
php bin/sync_templates.php 3          # business #3 only
```

---

## 5. OAuth / Embedded Signup

| Endpoint | Purpose |
|---|---|
| `settings/whatsapp` | Settings page; launches the Meta Embedded Signup popup per business (`state` = business id) |
| `business_signup_callback.php` | Embedded Signup popup callback — exchanges the code, saves the token onto the business row, closes the popup |
| `callback.php` | Classic OAuth callback — same exchange, then redirects to `settings/whatsapp` |

Both callbacks share `metaExchangeOauthCode($code, $state, $redirectUri)` in `includes/oauth.php`.
