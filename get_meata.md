# Get Meata — Requirements Needed From Meta to Complete the Platform

This document lists everything required from **Meta (WhatsApp Cloud API / Meta for Developers / Meta Business Suite)** to turn the current WhatsApp API scaffolding into the complete feature set:

- WhatsApp inbox
- Manage conversations
- Contact sync
- Maintain customer records
- Template manager
- Send approved messages
- Trigger messages automatically (broadcast/campaigns)
- Robust analytics dashboard

---

## 1. App & Account Setup (Meta for Developers)

| # | Requirement | Purpose |
|---|-------------|---------|
| 1.1 | A **Meta developer account** with email verified and 2FA enabled | Required to create/manage apps |
| 2.2 | A **Meta Business Portfolio (Business Manager) account** | Owner of the WABAs, phone numbers, and templates |
| 3.3 | A **Meta App** created at developers.facebook.com with **Business** app type | Container for the WhatsApp product and API credentials |
| 4.4 | Add the **WhatsApp** product to the app | Enables WhatsApp Cloud API |
| 5.5 | Complete **App Review** (business verification) for production access | Required before live customer traffic; dev/test mode only works with test numbers |
| 6.6 | **App Dashboard access** (developer/admin role on the app) | Needed to copy App ID / App Secret |

## 2. Credentials & Configuration (already partially used in `.env`)

| # | Requirement | Current `.env` key | Used by |
|---|-------------|-------------------|---------|
| 2.1 | **App ID** | `META_APP_ID` | OAuth/Embedded Signup, API calls |
| 2.2 | **App Secret** | `META_APP_SECRET` | Webhook `X-Hub-Signature-256` verification (mandatory — currently missing) |
| 2.3 | **Verify Token** (your own string) | `META_VERIFY_TOKEN` | Webhook GET handshake (already implemented) |
| 2.4 | **System User access token** (permanent) | `META_ACCESS_TOKEN` | All Graph API calls; must be long-lived (60–90 days or never-expiring) |
| 2.5 | **API version** | `META_API_VERSION` | Graph endpoint version (currently `v25.0`) |
| 2.6 | **WhatsApp Business Account (WABA) ID** | `waba_id` (per business row) | Phone-number lookup, template sync |
| 2.7 | **Phone Number ID** | `META_PHONE_NUMBER_ID` / `phone_number_id` | All send endpoints |
| 2.8 | **Public webhook callback URL** (HTTPS) | `CALLBACK_URL` | Meta delivers events to this URL |

## 3. Webhook Subscriptions (needed for inbox, status, conversations, contacts)

The app must subscribe to these webhook fields on the WABA / phone number:

| Webhook field | Events | Supports feature |
|---------------|--------|------------------|
| 3.1 | **messages** | `messages`, `sent`, `delivered`, `read`, `failed` | Inbox, conversation status, analytics |
| 3.2 | **message_template_status_update** | template `approved` / `rejected` / `paused` / `disabled` | Template manager (auto-sync status) |
| 3.3 | **message_template_quality_update** | template quality score changes | Template manager, analytics |
| 3.4 | **account_update** | number / WABA status changes | Business sender health |
| 3.5 | **phone_number_quality_update** | quality rating (green/yellow/red) | Analytics, sender health |
| 3.6 | **messages.reaction** / **messages.reply** | reactions, replies | Conversations |

## 4. Phone Numbers

| # | Requirement | Purpose |
|---|-------------|---------|
| 4.1 | At least one **verified business phone number** added to the WABA | To send/receive messages |
| 4.2 | A **test number** (from Meta) for dev sandbox before going live | Development/testing |
| 4.3 | **Display name** and **display phone number** configured | Shown to customers / used in UI |
| 4.4 | Phone numbers marked with correct **name/quality/status** | Prevent number flagging/bans |

## 5. Message Templates (Template Manager + Send Approved Messages)

| # | Requirement | Purpose |
|---|-------------|---------|
| 5.1 | Templates **created and submitted** in Meta Business Manager (or via Graph API) | Required for business-initiated messaging after the 24h window |
| 5.2 | **Approved** template status per language | Only approved templates can be sent |
| 5.3 | Template **category** (`utility`, `marketing`, `authentication`) | Category rules + analytics grouping |
| 5.4 | Template **languages** (e.g. `en_US`) | Multi-language delivery |
| 5.5 | Template **body with variables** `{{1}}`, `{{2}}`, ... | Variable substitution on send |
| 5.6 | **Meta `message_template_id`** for each template | Template sync / dedupe in DB |
| 5.7 | Graph API permission **`whatsapp_business_management`** | To pull templates via `GET /{waba_id}/message_templates` |
| 5.8 | Graph API permission **`whatsapp_business_messaging`** | To send messages |
| 5.9 | **App Review** approval for template-related API access in production | Template sync in production |

## 6. Contact Sync & Customer Records

> Note: WhatsApp does **not** expose a customer/contact database via Cloud API. Contact data must be owned by Netgrity.

| # | Requirement | Purpose |
|---|-------------|---------|
| 6.1 | **On-opt-in customer phone numbers** collected with the customer's consent | To legally message them |
| 6.2 | Recipient phone list (CSV/API) maintained **in Netgrity's DB** | Contact sync source |
| 6.3 | Meta **`contacts`** field is limited — only usable to check if a number is a valid WhatsApp user | Enrich customer records (best-effort) |
| 6.4 | **Phone Number Profile API** (name/profile info) where available | Customer record enrichment |

## 7. Automatic Messaging / Broadcasts / Campaigns

| # | Requirement | Purpose |
|---|-------------|---------|
| 7.1 | **Approved templates** (see §5) — the only way to reach users outside the 24h window | Bulk/campaign sends |
| 7.2 | **Messaging limits tier** per business portfolio (1K → 250K conversations/day) | Determines broadcast throughput |
| 7.3 | Respect **24-hour customer-service window** (free-form only inside it) | Prevent blocked sends |
| 7.4 | **Rate limiting / retry handling** on Meta's side (HTTP 429) | Reliable bulk delivery |
| 7.5 | **Embedded Signup / System User OAuth flow** (`business_signup_callback.php`) | Automatic per-client onboarding without manual token entry |
| 7.6 | **`whatsapp_business_messaging`** + `whatsapp_business_management` scopes | Programmatic access for automation |

## 8. Analytics & Insights (Robust Dashboard)

| # | Requirement | Purpose |
|---|-------------|---------|
| 8.1 | **Message Insights** via `GET /{waba_id}/insights` | Sent/delivered/read/opened counts over time |
| 8.2 | **Template Analytics** via `GET /{waba_id}/message_templates` + insights | Template send/read rates, quality scores |
| 8.3 | **Conversation pricing metric** (`GET /{waba_id}/analytics/conversations`) | Cost/conversation analytics |
| 8.4 | **Phone number quality & status** endpoints | Sender-health dashboard |
| 8.5 | **Webhook delivery timestamps** (already persisted in `business_messages`) | Funnel: sent → delivered → read |
| 8.6 | **App-level Graph API analytics** (if app is registered for it) | Aggregate platform stats |

## 9. Production Go-Live Checklist

- [ ] Business Manager verified & App Review **approved**
- [ ] `META_APP_SECRET` set (webhook signature currently **required** by `webhook.php`)
- [ ] Permanent system-user token configured
- [ ] HTTPS webhook callback URL reachable by Meta
- [ ] Webhook fields subscribed: `messages`, `message_template_status_update`, `message_template_quality_update`, `account_update`, `phone_number_quality_update`
- [ ] Templates created & approved in Meta Business Manager
- [ ] Messaging limit tier confirmed (start at 1K/day)
- [ ] Opt-in consent captured for all recipients
- [ ] Test sends pass (text inside 24h window, templates outside window)
- [ ] Embedded Signup OAuth wired to `callback.php`

---

## Mapping: Meta Requirements → Codebase

| Feature | Blocking Meta requirement(s) | Current code |
|---------|------------------------------|--------------|
| Inbox | 3.1 webhook `messages` | `webhook.php` + `saveInboundMessage()` |
| Conversations | 3.6 reply/reaction events | none (flat `business_messages`) |
| Contact sync / customer records | 6.1–6.4 opt-in + own DB | none |
| Template manager | 5.1–5.9 + `message_template_status_update` | `message_templates` table (never populated) |
| Send approved messages | 5.2, 7.3 | `sendTemplateMessage()` (empty table) |
| Auto-trigger / broadcasts | 5.1, 7.1–7.4 | `broadcast_campaigns` table (no code) |
| Analytics dashboard | 8.1–8.6 | `getMessageStats()` (basic only) |
