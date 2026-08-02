# Netgrity WhatsApp API — Session Summary

## Current State

Standalone multi-tenant WhatsApp Business Cloud API integration built on **Core PHP + MySQL** (procedural, Bootstrap 5 UI). It centralizes Meta-connected business senders, outbound messaging, webhook receipt tracking, and message logging for the Netgrity SaaS platform.

**Working today:**
- Business sender CRUD + manual onboarding (`business/`) with AES-encrypted tokens
- Outbound text, template, media, and interactive (buttons/list) sending via `send.php`
- Webhook verification (GET handshake) + inbound message / status-event intake (`webhook.php`)
- Inbound messages persisted to `business_messages` (`status='received'`)
- Outbound delivery/read/failed statuses persisted from webhooks
- Message history UI with filters, pagination, type/status badges (`messages.php`)
- WABA phone-number lookup wired into add/edit business forms
- Shared `.env` config bootstrap (`config/config.php`, `includes/init.php`)

**Known gaps (not addressed in this session):**
- `api/api.php` is empty (0 bytes) — the REST endpoints described in README are not implemented
- Meta OAuth/Embedded Signup (`callback.php`, `business_signup_callback.php`) exists but has no UI trigger and writes to a `whatsapp_accounts` table not used by the UI
- Orphaned helpers: `getLastOutgoingMessage()`, `getActiveTenants()` / `getTenantById()`
- `message_templates` table is read by `getTemplatesForBusiness()` but has no sync/create UI

## Changes Made This Session

### Bug fixes
- **`includes/whatsapp.php`** — media caption moved inside the media object (`image.link.caption`) instead of top-level payload.
- **`includes/messages.php`** — `getMessageStats()` now returns `today` (and `received`); added `received` to the status filter allow-list; inbound rows map `phone` from `from_number`.
- **`messages.php`** — stats cards now show real Total/Today/Delivered/Read (previously read undefined `twoWay`/`oneWay`); removed dead `$stats['error']` reference.

### New functionality
- **Inbound persistence** (`webhook.php`, `includes/webhook_parser.php`, `includes/messages.php`)
  - Parser now extracts `business_phone_id` (metadata `phone_number_id`), media URL/type, and captions.
  - Webhook saves inbound messages as `direction='inbound'` / `status='received'`, matched to a business via new `getBusinessByPhoneNumberId()` in `includes/businesses.php`.
- **Media send UI** (`send.php`) — Media mode: type picker (image/document/audio/video), public URL, optional caption → `sendMediaMessage()`.
- **Interactive send UI** (`send.php`) — Interactive mode: Buttons (up to 3, 25-char limit) and List (header/body/footer/list-button + dynamic add/remove sections & rows) → `sendInteractiveButtonsMessage()` / `sendInteractiveListMessage()`.
- **Message log UI** (`messages.php`) — Type + Status badge columns, inbound/outbound direction icons, media link in detail modal; template sends now logged as `message_type='template'`.
- **Schema migration** — `sql/migration_add_inbound_support.sql` adds `'received'` to the `business_messages.status` enum. **Applied to the live DB.**

### Verification
- `php -l` clean on all changed files: `send.php`, `messages.php`, `webhook.php`, `includes/whatsapp.php`, `includes/messages.php`, `includes/businesses.php`, `includes/webhook_parser.php`.
- Rollback-tested `saveInboundMessage()` (row inserted with `direction='inbound'`, `status='received'`), `getMessageStats()` keys, `getMessages()`, and `getBusinessByPhoneNumberId()` no-match.
- `send.php` and `messages.php` render without runtime errors.

## Next Steps (recommended)

1. **Apply migration to other environments** — run `sql/migration_add_inbound_support.sql` on any DB that imports the base schema.
2. **Webhook signature check** — confirm `META_APP_SECRET` is set so `X-Hub-Signature-256` verification works for real Meta payloads (currently required by `webhook.php`).
3. **Inbound media download** — webhook stores media id/url only; add Media API retrieval (GET `/media` with the stored id) to persist actual files/URLs.
4. **Templates** — implement template sync (`bin/sync_templates.php` mentioned in `includes/templates.php`) and a management UI, since `message_templates` is read but never populated.
5. **REST API** — implement the documented endpoints in `api/api.php` (connect/disconnect, text/template/media, status, template list/sync) with JWT/API-key auth.
6. **Embedded Signup UI** — add a button on `business/add.php` to launch Meta Embedded Signup and route `callback.php` into the `businesses` table (it currently writes to `whatsapp_accounts`).
7. **Broadcast/campaigns** — `broadcast_campaigns` table exists; build the bulk-send flow on top of the new send helpers.
8. **Cleanup** — decide whether to wire or remove the orphaned `getLastOutgoingMessage()` / `tenants.php` helpers.
