# Netgrity WhatsApp API — Session Summary

## Current State

Standalone multi-tenant WhatsApp Business Cloud API platform built on **Core PHP + MySQL** (procedural, Bootstrap 5 UI, white/orange/black theme). All 8 planned features are now implemented: inbox, conversations, contacts/customer records, template manager, approved-message sending, broadcast automation, and analytics.

**Working today:**
- Business sender CRUD + manual onboarding (`business/`) with AES-encrypted tokens
- Outbound text, template, media, and interactive (buttons/list) sending via `send.php`
- **Inbox** (`inbox.php`) — conversation list with unread badges, threaded view, reply; conversations upserted from every message via `includes/conversations.php`
- **Contacts** (`contacts.php`) — customer records CRUD + CSV import, auto-created/updated from inbound/outbound traffic (`includes/customers.php`)
- **Template manager** (`templates.php`) — lists/syncs/creates/deletes `message_templates`; `syncTemplatesFromMeta()` pulls approved templates via Graph API (per business)
- **Broadcast** (`broadcast.php`) — CSV recipient upload, campaign tracking in `broadcast_campaigns` + `broadcast_recipients`, synchronous send with 200 ms throttle (`includes/broadcasts.php`)
- **Analytics** (`analytics.php`) — Chart.js dashboards: messages over time, status/type/business breakdowns, template performance, top customers (`includes/analytics.php`)
- Webhook verification (GET handshake + `X-Hub-Signature-256` HMAC) and inbound/status intake (`webhook.php`)
- Message history UI with filters, pagination (10/page), type/status badges (`messages.php`)
- Shared navbar partial with live unread-inbox badge (`includes/partials/navbar.php`) + unified theme (`assets/css/app.css`)
- CLI tooling (`bin/`): `sync_templates.php`, `run_migration.php`, `smoke_test.php`, `smoke_one.php`, `check_db.php`

**Known gaps (not addressed this session):**
- `api/api.php` is empty (0 bytes) — the REST endpoints described in README are not implemented
- Meta OAuth/Embedded Signup (`callback.php`, `business_signup_callback.php`) exists but has no UI trigger and writes to a `whatsapp_accounts` table not used by the UI
- `message_template_status_update` webhooks are not yet parsed (template status sync is manual via the Templates page / `bin/sync_templates.php`)
- Inbound media is stored as id/URL only — Media API retrieval not implemented
- Orphaned helpers remain: `getLastOutgoingMessage()`, `getActiveTenants()` / `getTenantById()`

## Changes Made This Session

### Feature build-out (all 8 features)
- **`includes/customers.php`** — `normalizePhone()`, `findOrCreateCustomer()`, `getCustomers()`, `getCustomerById()`, `createCustomer()`, `updateCustomer()`, `deleteCustomer()`, `importCustomersFromCsv()`, `getCustomerStats()`.
- **`includes/conversations.php`** — `syncCustomerConversation()` (upsert + unread increment), `getConversations()`, `getConversation()`, `markConversationRead()`, `setConversationStatus()`, `getThreadMessages()`, `getUnreadCount()`.
- **`includes/messages.php`** — `saveOutgoingMessage()` / `saveInboundMessage()` now backfill `customer_id` and call `syncCustomerConversation()`.
- **`includes/templates.php`** — added `syncTemplatesFromMeta($businessId)`, `createTemplate()`, `deleteTemplate()`.
- **`includes/broadcasts.php`** — `createCampaign()`, `getCampaigns()`, `getCampaignById()`, `saveCampaignRecipient()`, `getCampaignRecipientCounts()`, `runCampaign()` (synchronous, 200 ms throttle).
- **`includes/analytics.php`** — `getMessagesOverTime()`, `getStatusBreakdown()`, `getBusinessBreakdown()`, `getTypeBreakdown()`, `getTemplatePerformance()`, `getTopCustomers()`.
- **New pages** — `inbox.php`, `contacts.php`, `templates.php`, `broadcast.php`, `analytics.php`.

### Theme & navigation
- `assets/css/app.css` (white primary / orange `#ff6b00` secondary / black CTAs, `.btn-ng-*`, `.navbar-ng`, `card-ng`) and shared `includes/partials/navbar.php`.
- Themed all existing pages: `home.php` (rewritten as an 8-card feature launcher), `messages.php`, `send.php`, `test.php`, `business/index.php`, `business/add.php`, `business/edit.php`, `business/view.php`, `business/t.php`.
- Fixed navbar relative links for subdirectory pages via `$navBase` (business pages pass `'../'`).

### Bug fixes & hardening
- **`analytics.php`** — days selector no longer emits a duplicate `days=` query param (first value was winning, so 7/14/30 switching was broken).
- **`messages.php`** — per-page count reduced to 10; status filter (queued/sent/delivered/read/failed/received) wired through the existing `getMessages()` filter.
- Verified `getBusinessById()` returns the **decrypted** access token (businesses.php decrypts via `includes/crypto.php`), so template sync and broadcasts authenticate correctly.
- `get_meata.md` (Meta requirements reference) and `instruct.md` (step-by-step user guide) created.

### Schema migration (applied to live DB)
- `sql/migration_full_features.sql` — creates `customers`, `conversations`, `broadcast_recipients`; adds `business_messages.customer_id` (FK, SET NULL); backfills customers/conversations from existing message traffic. **Applied via `bin/run_migration.php` — 7/7 statements OK.**

### Verification
- `php -l` clean on every new/changed file.
- `bin/smoke_test.php` renders all 11 pages (home, inbox, contacts, templates, broadcast, analytics, messages, send, business/index, business/add, test) with no runtime errors against the migrated DB.

## Next Steps (recommended)

1. **Meta setup (user action)** — follow `instruct.md`: create the app, fill `.env`, register the phone number, wire the `/webhook` callback + subscribe to fields, create + approve templates, then sync. `test.php` validates connectivity.
2. **Template status webhooks** — parse `message_template_status_update` in `webhook.php` so approval/rejection updates the DB automatically.
3. **Inbound media download** — retrieve actual files via Meta Media API (`GET /{media_id}`) using stored media ids.
4. **REST API** — implement the documented endpoints in `api/api.php` with JWT/API-key auth.
5. **Embedded Signup UI** — add a button on `business/add.php` to launch Meta Embedded Signup and route `callback.php` into the `businesses` table (currently writes to `whatsapp_accounts`).
6. **Cleanup** — decide whether to wire or remove the orphaned `getLastOutgoingMessage()` / `tenants.php` helpers.
