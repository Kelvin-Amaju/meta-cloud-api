# How to add a verified (approved) WhatsApp template from Meta

In this app, **Meta is the source of truth for templates**. A template is only usable
(and only counts as "verified") once Meta has **reviewed and approved** it. The app does
**not** approve templates — it only imports what Meta has already approved.

There are two sides to the workflow:

1. **In Meta** — you create the template and Meta reviews it (`APPROVED` / `PENDING` / `REJECTED`).
2. **In this app** — you *sync* the approved templates into the app, then they become
   available in **Send** and **Broadcast**.

---

## 1. Prerequisites (one-time setup)

Before any template can work, the business must be connected to Meta in the app:

- The business row must have a **`waba_id`** and a valid **`access_token`** for that WABA.
  (Set them up via **Business → Add/Edit**, or via the Meta OAuth connect flow in
  **Settings → WhatsApp**.)
- The token must have `whatsapp_business_messaging` and `whatsapp_business_management`
  permissions.
- `config.inc.php` must have the right `api_version` (e.g. `v25.0`) and, for sending,
  a `phone_number_id`.

If the app can't find a WABA/token for a business, sync will fail with:
`Business is missing waba_id or access_token — configure Meta credentials first.`

---

## 2. Create the template in Meta

### Option A — WhatsApp Manager (no code)

1. Go to **WhatsApp Manager** → **Account tools** → **Message templates**
   (inside Meta Business Suite).
2. Click **Create message template**.
3. Choose the **category** — the app supports `utility`, `marketing`, `authentication`:
   - **Utility** — account updates, confirmations, reminders (loosest rules).
   - **Marketing** — promotions, announcements (needs recipients to opt in).
   - **Authentication** — one-time passcodes (needs special setup).
4. **Template name** — lowercase letters, numbers, and underscores only, e.g.
   `appointment_reminder`. It must be unique within the WABA and **must match exactly**
   what you use in the app.
5. **Language** — pick a language code, e.g. `en_US` (use the same code in the app).
6. **Body** — write the message. Use variables as `{{1}}`, `{{2}}`, … e.g.
   `Hi {{1}}, this is a reminder for {{2}} at {{3}}.`
   - **Sample values** are required for every variable — Meta uses them during review.
   - Header (media/emoji/text) and buttons (quick replies, URL, phone) are optional.
7. Click **Submit**.

### Option B — Meta Cloud API (for programmatic creation)

```bash
curl -X POST "https://graph.facebook.com/v25.0/{WABA_ID}/message_templates" \
  -H "Authorization: Bearer {ACCESS_TOKEN}" \
  -d "name=appointment_reminder" \
  -d "language=en_US" \
  -d "category=UTILITY" \
  -d "components={\"body\":{\"text\":\"Hi {{1}}, this is a reminder for {{2}}.\"}}"
```

---

## 3. Wait for review

Meta reviews the template. Status transitions you'll see:

| Status | Meaning |
|---|---|
| `PENDING` | Under review. Not sendable yet. |
| `APPROVED` | **Verified / approved — sendable.** This is the state you want. |
| `REJECTED` | Failed review (see rejection reason). Fix and resubmit. |

Approved templates are the only ones this app lets you send.

---

## 4. Sync the templates into the app

### Option A — UI button (recommended)

1. Open the **Templates** page (`templates`).
2. Pick the business in the **"Business to sync…"** dropdown (top-right).
3. Click **Sync from Meta**.
4. You should see *"Imported or updated **N** template(s) from Meta."*

### Option B — CLI

```bash
php wapi/bin/sync_templates.php          # sync templates for every business
php wapi/bin/sync_templates.php 3        # sync only business id 3
```

The sync calls:
`GET /{api_version}/{waba_id}/message_templates?limit=1000&fields=id,name,language,category,status,components`

and upserts the results into the `message_templates` table (so re-running after any
change in Meta just updates existing rows).

### What you should see after sync

On the Templates page, filter by the business. Each template shows a **status badge**:

- `APPROVED` (green) → usable in **Send** and **Broadcast**.
- `PENDING` (yellow) → not sendable yet.
- `REJECTED` (red) → not sendable; fix in Meta and re-sync.
- `DRAFT` (grey) → a local-only draft (see §6) — never sendable as-is.

> Re-sync any time you add, edit, or get a template approved in Meta — the app does not
> watch Meta; it only reads on demand.

---

## 5. Send with an approved template

Once a template has `status = 'approved'` in the app, it appears in the template
dropdowns on **Send** and in **Broadcast** (template payload). When sending, fill in the
values for `{{1}}`, `{{2}}`, … in order. The app sends:

```
POST /{phone_number_id}/messages   type=template, name=<template>, language=<code>,
                                   components.body.parameters = [values in order]
```

Notes:
- The template **name and language must match Meta exactly**, or Meta rejects the send.
- If you get a Meta error like `Template does not exist`, the template is not approved
  or the name/language is wrong — re-check §3 and §4.

---

## 6. About "New Draft Template" in the app

The **Templates → New Draft Template** button only saves a **local draft** row with
`status = 'draft'`. It is **not** submitted to Meta and can **never be sent** on its own.
Use it as a scratchpad: create the draft locally, then create the real template in
Meta (§2), get it approved (§3), and **Sync from Meta** (§4).

---

## 7. Troubleshooting

| Problem | Likely cause / fix |
|---|---|
| Sync says *"Business is missing waba_id or access_token"* | Configure the business's Meta credentials first (§1). |
| Sync shows a Meta error message | Token invalid/expired, wrong `api_version`, or the token lacks WABA permissions — fix and retry. |
| Template synced but not sendable | Its status is `pending`/`rejected`/`draft` — only `approved` is sendable. Wait for approval or fix in Meta, then re-sync. |
| Template is `REJECTED` | Read the rejection reason in WhatsApp Manager, fix content/category, resubmit, re-sync. |
| Send fails with `Template does not exist` | Not approved, or name/language doesn't match Meta exactly. |
| Marketing template rejected | Recipients must have opted in; content must not be misleading. Switch to `utility` if it's a transactional message. |
| Template appears approved but empty body in app | The sync reads the `BODY` component from Meta's `components` — re-sync; if still empty, check the template's components in Meta. |

---

## Quick checklist for a new verified template

1. [ ] Business configured with valid `waba_id` + `access_token` (§1)
2. [ ] Template created in Meta with `utility`/`marketing`/`authentication` category (§2)
3. [ ] Status shows `APPROVED` in WhatsApp Manager (§3)
4. [ ] **Sync from Meta** on the Templates page (or CLI, §4)
5. [ ] Status badge is green `APPROVED` in the app (§4)
6. [ ] Pick it in **Send**/**Broadcast**, fill the variables, send (§5)
