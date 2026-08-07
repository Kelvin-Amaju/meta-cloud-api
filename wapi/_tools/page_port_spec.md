# Page Port Spec — whatsapp-api → wapi/main/

You are porting an existing whatsapp-api page into the new `wapi/main/` module.
The work is MECHANICAL: bootstrap swap + function renames + path fixes. Behavior must stay identical.

## Reference files you must read to confirm names (do NOT edit them)
- `wapi/_includes/functions.inc.php` — main bootstrap for pages (ob_start, sessions+CSRF, loads config/db/helpers).
- `wapi/_includes/functions2.inc.php` — public bootstrap (NOT used by pages).
- `wapi/_includes/sidebar.inc.php` — replaces `includes/partials/navbar.php`.
- `wapi/_includes/*_functions.inc.php` — the renamed domain modules.
- The ORIGINAL page under the whatsapp-api root / `business/` / `settings/`.

## Target mapping (write your page here)
- root `home.php` → `wapi/main/home.php`
- root `send.php` → `wapi/main/send.php`
- root `inbox.php` → `wapi/main/inbox.php`
- root `contacts.php` → `wapi/main/contacts.php`
- root `templates.php` → `wapi/main/templates.php`
- root `broadcast.php` → `wapi/main/broadcast.php`
- root `messages.php` → `wapi/main/messages.php`
- root `analytics.php` → `wapi/main/analytics.php`
- root `test.php` → `wapi/main/test.php`
- `business/index.php` → `wapi/main/business_index.php`
- `business/add.php` → `wapi/main/business_add.php`
- `business/edit.php` → `wapi/main/business_edit.php`
- `business/view.php` → `wapi/main/business_view.php`
- `business/lookup_phone_numbers.php` → `wapi/main/lookup_phone_numbers.php`
- `settings/whatsapp.php` → `wapi/main/settings_whatsapp.php`

## 1. Bootstrap swap
Replace the opening `require_once ...init.php;` + `require_once 'includes/*.php';` block with:

```php
require_once __DIR__ . '/../_includes/functions.inc.php';
```

Then add `require_once` lines ONLY for the domain modules the page actually uses:
```php
require_once __DIR__ . '/../_includes/business_functions.inc.php';
require_once __DIR__ . '/../_includes/message_functions.inc.php';
require_once __DIR__ . '/../_includes/customer_functions.inc.php';
require_once __DIR__ . '/../_includes/conversation_functions.inc.php';
require_once __DIR__ . '/../_includes/template_functions.inc.php';
require_once __DIR__ . '/../_includes/broadcast_functions.inc.php';
require_once __DIR__ . '/../_includes/analytics_functions.inc.php';
require_once __DIR__ . '/../_includes/whatsapp_functions.inc.php';
```

Keep the page's `$dispage = '...';` line if it exists.

## 2. Navbar / partials swap
- Replace `require __DIR__ . '/../includes/partials/navbar.php';` (and the one-level variant `.../includes/partials/navbar.php`) with `require __DIR__ . '/../_includes/sidebar.inc.php';`.
- Keep `$activeNav = '...';` set BEFORE the require (set it to the matching key if the original had it).
- DELETE any `$navBase = '../';` line (sidebar.inc.php no longer uses navBase).
- `includes/partials/messaging_limit_banner.php` → `__DIR__ . '/../_includes/messaging_limit_banner.inc.php'`. If that partial does not exist yet, CREATE it at `wapi/_includes/messaging_limit_banner.inc.php` by copying `includes/partials/messaging_limit_banner.php` and applying the same path/function fixes.

## 3. Relative path fixes (pages live one level deeper in main/)
- `assets/...` → `../assets/...`
- `includes/...` → `../_includes/...`
- `../includes/...` → `../_includes/...`
- `ajax/...` → `../ajax/...`
- `settings/whatsapp` → `settings_whatsapp` (same main/ dir)
- `business/index` → `business_index`; `business/add` → `business_add`; `business/edit` → `business_edit`; `business/view` → `business_view`
- `../business/index` (from business/ pages) → `business_index`, etc.
- Keep the extensionless clean-URL style for cross-page links (the .htaccess resolves them).
- Form `action` attributes that POST back to the SAME page keep the same page's clean name (e.g. `action="business_add"`).

## 4. Function rename map — apply EVERYWHERE (declarations, calls, strings)
| Old | New |
|---|---|
| getBusinessById | get_business |
| getActiveBusinesses | get_active_businesses |
| getAllBusinesses | get_businesses |
| getBusinessByUuid | get_business_by_uuid |
| getBusinessByPhoneNumberId | get_business_by_phone_number_id |
| getBusinessSummaryStats | get_business_summary_stats |
| createBusiness | create_business |
| updateBusiness | update_business |
| deleteBusiness | delete_business |
| generateUuid | generate_uuid |
| getCustomers | get_customers |
| getCustomerById | get_customer_by_id |
| createCustomer | create_customer |
| updateCustomer | update_customer |
| deleteCustomer | delete_customer |
| importCustomersCsv | import_customers_csv |
| getCustomerStats | get_customer_stats |
| normalizePhone | normalize_phone |
| findOrCreateCustomer | find_or_create_customer |
| getConversations | get_conversations |
| getConversationByBusiness | get_conversation_by_business |
| getConversationById | get_conversation_by_id |
| getConversationMessages | get_conversation_messages |
| syncCustomerConversation | sync_customer_conversation |
| markConversationRead | mark_conversation_read |
| setConversationStatus | set_conversation_status |
| getUnreadCount | get_unread_count |
| getMessages | get_messages |
| getMessageStats | get_message_stats |
| getLastOutgoingMessage | get_last_outgoing_message |
| saveOutgoingMessage | save_outgoing_message |
| saveInboundMessage | save_inbound_message |
| updateMessageStatusByWamid | update_message_status_by_wamid |
| getTemplatesForBusiness | get_templates_for_business |
| getTemplateByName | get_template_by_name |
| createTemplate | create_template |
| deleteTemplate | delete_template |
| syncTemplatesFromMeta | sync_templates_from_meta |
| getCampaigns | get_campaigns |
| getCampaignById | get_campaign_by_id |
| createCampaign | create_campaign |
| saveCampaignRecipient | save_campaign_recipient |
| getCampaignRecipientCounts | get_campaign_recipient_counts |
| runCampaign | run_campaign |
| getMessagesOverTime | get_messages_over_time |
| getStatusBreakdown | get_status_breakdown |
| getBusinessBreakdown | get_business_breakdown |
| getMessageTypeBreakdown | get_message_type_breakdown |
| getTemplatePerformance | get_template_performance |
| getTopCustomers | get_top_customers |
| sendTextMessage | whatsapp_send_text |
| sendTemplateMessage | whatsapp_send_template |
| sendMediaMessage | whatsapp_send_media |
| sendInteractiveButtonsMessage | whatsapp_send_interactive_buttons |
| sendInteractiveListMessage | whatsapp_send_interactive_list |
| getWabaPhoneNumbers | get_waba_phone_numbers |
| getRequestSignatureHeader | get_request_signature_header |
| verifyWebhookSignature | verify_webhook_signature |
| parseWebhook | parse_webhook |
| metaExchangeOauthCode | meta_exchange_oauth_code |
| getEncryptionKey | get_encryption_key |
| encryptToken | encrypt_token |
| decryptToken | decrypt_token |

Order matters where one name is a prefix of another — apply longest tokens first
(e.g. `getBusinessByPhoneNumberId` before `getBusinessById`; `getMessagesOverTime` before `getMessages`).
`sendLegacyTemplateMessage` → `send_legacy_template_message`; `getTemplateByName` stays (already correct) — check against `template_functions.inc.php`.

## 5. Do NOT do (later phases)
- Do NOT convert POST handlers to AJAX endpoints. Keep the page's existing POST handling.
- Do NOT add CSRF fields.
- Do NOT change the DB layer or add new tables.
- Do NOT modify root pages, `wapi/webhook.php`, `wapi/callback.php`, `wapi/business_signup_callback.php`, `wapi/index.php`, or anything in `includes/`.

## 6. Security nit (apply, it is cheap)
Any `json_encode(...)` embedded inside an inline `<script>` must use:
`json_encode($x, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT)`.
Otherwise leave the code as-is.

## 7. Verification + report
1. Lint: `php -l <target>` — ignore the "Module mysqli is already loaded" / "zip is deprecated" startup warnings; a REAL parse error looks like `Parse error: syntax error ... in <file> on line N`. Fix until clean.
2. Report back (concise): each file written + `php -l` result (OK / error lines), plus a short list of (a) leftover old function names or old `includes/` paths you could NOT resolve, and (b) any page-specific ambiguity you had to decide on.
