# instruct.md — What YOU Still Need to Do on the Meta Side

Everything on the app side is built. This page is your step-by-step checklist for
the parts only you can do — creating the Meta app, getting credentials, wiring the
webhook, and approving templates. Once that is done, the whole platform (inbox,
contacts, templates, broadcast, analytics) works.

---

## 0. First run (already done, keep for reference)

1. The database migration was applied via `bin/run_migration.php`
   (creates `customers`, `conversations`, `broadcast_recipients` and links
   `business_messages.customer_id`). Re-run it only on a fresh DB.
2. Sanity-check any page with `bin/smoke_test.php`.

---

## 1. Create the Meta app (one-time, ~30 min)

Go to https://developers.facebook.com (log in with a **personal account** that has
2FA enabled, then create/join a **Business Portfolio** at business.facebook.com).

1. **Dashboard → Create App** → choose **Business** type.
2. Add the **WhatsApp** product to the app.
3. Click **Get started** under *"Connect a business phone number"*.

You'll be inside **WhatsApp → API Setup**.

---

## 2. Copy credentials into `.env`

From the app dashboard grab:

| You need | Where to find it | `.env` key |
|----------|------------------|------------|
| App ID | Dashboard header | `META_APP_ID` |
| App Secret | Dashboard → App settings → Basic | `META_APP_SECRET` (currently empty — webhook POSTs will be rejected until set) |
| API version | Graph API version dropdown | `META_API_VERSION` (default `v25.0`) |
| Verify Token | **Invent your own random string** (e.g. `ngv-9f2k4m`) | `META_VERIFY_TOKEN` |
| System User token | `business.facebook.com/settings/system-users` → create a System User with the app, generate a **permanent** token with `whatsapp_business_management` + `whatsapp_business_messaging` scopes | `META_ACCESS_TOKEN` (fallback token) |

Edit `C:\laragon\www\netgrity\app\whatsapp-api\.env` accordingly.

> **Per-business vs global:** the global `.env` token/phone id is the fallback.
> The recommended path is to store **per-client** values in **Business → Add/Edit**
> (`waba_id`, `phone_number_id`, `display_name`, `display_phone_number`,
> `access_token`). Template sync and broadcast use the per-business values; the
> `.env` fallback is used by the legacy send screen and `test.php`.

---

## 3. Register your phone number

1. In **API Setup**, add your business phone number (or use Meta's free **test number** first).
2. From **API Setup → To/From phone number** copy the **Phone number ID**
   (numeric) and the **WABA ID** (`111222333444555`).
3. Put those into the business form under **Business → Add Business / Edit**.
4. If the number isn't verified yet, finish phone verification in Meta Business Manager.

---

## 4. Wire the webhook

Your app needs a **public HTTPS** URL. The endpoint is already implemented —
it lives at `webhook.php`, reachable as `/webhook` (extensionless).

1. In **Meta App → WhatsApp → Configuration → Webhook**, click **Edit → Subscribe**.
2. **Callback URL:** `https://your-public-domain/webhook`
   (e.g. `https://api.netgrity.com/whatsapp/webhook` depending on how it's hosted).
   For local testing use a tunnel (ngrok / Cloudflare Tunnel) pointing at the folder.
3. **Verify token:** exactly the `META_VERIFY_TOKEN` string from `.env`.
4. Click **Verify and save** — the app already answers the `hub.challenge`
   handshake (GET handler in `webhook.php`).
5. On the next screen subscribe to these webhook fields:

| Field | Feeds |
|-------|-------|
| `messages` | Inbox, conversations, contacts, analytics |
| `message_template_status_update` | Template manager (auto approval status) |
| `message_template_quality_update` | Template manager quality |
| `account_update` | Sender health |
| `phone_number_quality_update` | Sender health / analytics |

6. **Test it:** open `test.php` (Diagnostics page). It shows whether the token and
   phone id are valid. Then send yourself a WhatsApp message to the business number
   and confirm it appears in **Inbox**.

> `META_APP_SECRET` must be set or `webhook.php` rejects every POST with
> `invalid signature`.

---

## 5. Create & approve templates (needed for broadcast + out-of-window sends)

Free-form messages only work **inside the 24-hour customer-service window**.
Anything outside it must use an **approved template**.

1. Go to **Meta Business Manager → WhatsApp Manager → Message templates → Create**.
2. Pick category: `utility` (fastest approval) / `marketing` / `authentication`.
3. Body must use variables as `{{1}}`, `{{2}}`, ... and end with a legal disclaimer
   (for marketing) or an opt-out note where required.
4. Wait for status → **APPROVED**.
5. In the app open **Templates → Sync from Meta** (button in the header). It calls
   `syncTemplatesFromMeta()` and fills the `message_templates` table for that
   business. You can also run it for all businesses via CLI:
   `php bin/sync_templates.php` (or `php bin/sync_templates.php 3` for one business).
6. Approved templates are then selectable in **Send Message** and **Broadcast**.

---

## 6. Send your first messages

1. **Inside the 24h window** — any customer who messaged you: use **Send Message**
   (free-form text is fine).
2. **Outside the 24h window** — pick an **approved template** and fill its variables.
3. **Broadcast** — create a campaign in **Broadcast**: upload a CSV with a `phone`
   column (E.164 format `+12223334444`), pick the business, choose an approved
   template, then **Send now**. Delivery is synchronous with a 200 ms delay per
   message; status per recipient is stored in `broadcast_recipients`.

---

## 7. Production go-live checklist

- [ ] Business verified and **App Review** approved for `whatsapp_business_messaging` / `whatsapp_business_management`
- [ ] `META_APP_SECRET` + `META_VERIFY_TOKEN` + permanent system-user token set
- [ ] HTTPS webhook reachable, fields subscribed (see §4), `messages` webhook confirmed in Inbox
- [ ] Templates approved and synced (§5)
- [ ] Messaging limit tier known (starts at 1K conversations/day)
- [ ] Opt-in consent captured for every recipient
- [ ] Test sends pass: free-form inside 24h, template outside
- [ ] Per-business credentials stored in the Business form (not just `.env`)

---

## Troubleshooting

| Symptom | Check |
|---------|-------|
| Webhook `403 invalid signature` | `META_APP_SECRET` missing in `.env` |
| Webhook GET fails | `META_VERIFY_TOKEN` in `.env` ≠ token used in Meta config |
| Template sync 401/403 | Business `access_token` wrong/expired; missing `whatsapp_business_management` scope |
| Send fails "message undeliverable" | Recipient must have opted in; number must be a real WhatsApp user |
| Send fails "template not approved" | Only APPROVED templates send outside the 24h window |
| Broadcast stalls / 429 | You hit your messaging-limit tier; the campaign marks recipients `failed` with the error |
| Nothing appears in Inbox | Webhook field `messages` not subscribed; or tunnel not forwarding |
