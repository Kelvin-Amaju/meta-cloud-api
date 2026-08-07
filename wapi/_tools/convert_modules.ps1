# Convert whatsapp-api includes/*.php modules to convention-named _includes/*_functions.inc.php
# Usage: powershell -ExecutionPolicy Bypass -File wapi/_tools/convert_modules.ps1

$ErrorActionPreference = 'Stop'
$root = 'C:\laragon\www\netgrity\app\whatsapp-api'
$src  = Join-Path $root 'includes'
$dst  = Join-Path $root 'wapi\_includes'

if (-not (Test-Path $dst)) { New-Item -ItemType Directory -Force -Path $dst | Out-Null }

# source file -> target file
$modules = @(
  @{ src = 'businesses.php';    dst = 'business_functions.inc.php' },
  @{ src = 'customers.php';     dst = 'customer_functions.inc.php' },
  @{ src = 'conversations.php'; dst = 'conversation_functions.inc.php' },
  @{ src = 'messages.php';      dst = 'message_functions.inc.php' },
  @{ src = 'templates.php';     dst = 'template_functions.inc.php' },
  @{ src = 'broadcasts.php';    dst = 'broadcast_functions.inc.php' },
  @{ src = 'analytics.php';     dst = 'analytics_functions.inc.php' },
  @{ src = 'whatsapp.php';      dst = 'whatsapp_functions.inc.php' },
  @{ src = 'crypto.php';        dst = 'crypto_functions.inc.php' },
  @{ src = 'oauth.php';         dst = 'oauth_functions.inc.php' }
)

# require-path rewrites (quoted path strings)
$pathRewrites = @(
  @("'/../config/config.php'", "'/config.inc.php'"),
  @("'/database.php'", "'/db.inc.php'"),
  @("'/businesses.php'", "'/business_functions.inc.php'"),
  @("'/customers.php'", "'/customer_functions.inc.php'"),
  @("'/conversations.php'", "'/conversation_functions.inc.php'"),
  @("'/messages.php'", "'/message_functions.inc.php'"),
  @("'/templates.php'", "'/template_functions.inc.php'"),
  @("'/broadcasts.php'", "'/broadcast_functions.inc.php'"),
  @("'/analytics.php'", "'/analytics_functions.inc.php'"),
  @("'/whatsapp.php'", "'/whatsapp_functions.inc.php'"),
  @("'/crypto.php'", "'/crypto_functions.inc.php'"),
  @("'/oauth.php'", "'/oauth_functions.inc.php'"),
  @("'/env.php'", "'/config.inc.php'")
)

# function renames (application order matters: longest tokens first)
$renames = @(
  # businesses
  @('getBusinessByPhoneNumberId','get_business_by_phone_number_id'),
  @('getBusinessSummaryStats','get_business_summary_stats'),
  @('getActiveBusinesses','get_active_businesses'),
  @('getBusinessByUuid','get_business_by_uuid'),
  @('getAllBusinesses','get_businesses'),
  @('getBusinessById','get_business'),
  @('createBusiness','create_business'),
  @('updateBusiness','update_business'),
  @('deleteBusiness','delete_business'),
  @('generateUuid','generate_uuid'),
  # customers
  @('findOrCreateCustomer','find_or_create_customer'),
  @('getCustomerStats','get_customer_stats'),
  @('getCustomerById','get_customer_by_id'),
  @('getCustomers','get_customers'),
  @('createCustomer','create_customer'),
  @('updateCustomer','update_customer'),
  @('deleteCustomer','delete_customer'),
  @('importCustomersCsv','import_customers_csv'),
  @('normalizePhone','normalize_phone'),
  # conversations
  @('syncCustomerConversation','sync_customer_conversation'),
  @('getConversationByBusiness','get_conversation_by_business'),
  @('getConversationMessages','get_conversation_messages'),
  @('markConversationRead','mark_conversation_read'),
  @('setConversationStatus','set_conversation_status'),
  @('getConversationById','get_conversation_by_id'),
  @('getConversations','get_conversations'),
  @('getUnreadCount','get_unread_count'),
  # messages
  @('getMessagesOverTime','get_messages_over_time'),
  @('updateMessageStatusByWamid','update_message_status_by_wamid'),
  @('saveOutgoingMessage','save_outgoing_message'),
  @('saveInboundMessage','save_inbound_message'),
  @('getMessageStats','get_message_stats'),
  @('getLastOutgoingMessage','get_last_outgoing_message'),
  @('getMessages','get_messages'),
  # templates
  @('getTemplatesForBusiness','get_templates_for_business'),
  @('getTemplateByName','get_template_by_name'),
  @('createTemplate','create_template'),
  @('deleteTemplate','delete_template'),
  @('syncTemplatesFromMeta','sync_templates_from_meta'),
  @('sendLegacyTemplateMessage','send_legacy_template_message'),
  @('buildTemplateComponents','build_template_components'),
  # broadcasts
  @('getCampaignRecipientCounts','get_campaign_recipient_counts'),
  @('getCampaignById','get_campaign_by_id'),
  @('saveCampaignRecipient','save_campaign_recipient'),
  @('createCampaign','create_campaign'),
  @('getCampaigns','get_campaigns'),
  @('runCampaign','run_campaign'),
  # analytics
  @('getStatusBreakdown','get_status_breakdown'),
  @('getBusinessBreakdown','get_business_breakdown'),
  @('getMessageTypeBreakdown','get_message_type_breakdown'),
  @('getTemplatePerformance','get_template_performance'),
  @('getTopCustomers','get_top_customers'),
  # whatsapp
  @('sendWhatsAppPayload','whatsapp_send_payload'),
  @('sendInteractiveButtonsMessage','whatsapp_send_interactive_buttons'),
  @('sendInteractiveListMessage','whatsapp_send_interactive_list'),
  @('sendTextMessage','whatsapp_send_text'),
  @('sendTemplateMessage','whatsapp_send_template'),
  @('sendMediaMessage','whatsapp_send_media'),
  @('getWabaPhoneNumbers','get_waba_phone_numbers'),
  # webhook (parser + security merged separately)
  @('parseWebhook','parse_webhook'),
  @('getRequestSignatureHeader','get_request_signature_header'),
  @('verifyWebhookSignature','verify_webhook_signature')
)

foreach ($m in $modules) {
  $content = Get-Content -Raw -LiteralPath (Join-Path $src $m.src)
  foreach ($pr in $pathRewrites) { $content = $content.Replace($pr[0], $pr[1]) }
  foreach ($r in $renames)    { $content = $content.Replace($r[0], $r[1]) }

  # --- module-specific fixes ---
  if ($m.src -eq 'whatsapp.php') {
    $content = [regex]::Replace($content, '/\*\*\s*\n \* Resolve a business.*?\z', '', 'Singleline')
    $content = $content.TrimEnd() + "`n"
  }

  if ($m.src -eq 'crypto.php') {
    $content = $content.Replace("env('APP_ENCRYPTION_KEY', '')", "(`$GLOBALS['config']['encryption_key'] ?? '')")
    $content = $content.Replace('APP_ENCRYPTION_KEY is not set in .env', 'encryption_key is not set in config.inc.php')
    $content = $content.Replace('APP_ENCRYPTION_KEY must be a base64-encoded 32-byte', 'encryption_key must be a base64-encoded 32-byte')
    $content = $content.Replace('Cannot encrypt token: APP_ENCRYPTION_KEY is missing or invalid in .env.', 'Cannot encrypt token: encryption_key is missing or invalid in config.inc.php.')
    $header = "<?php`n`n// _includes/crypto_functions.inc.php`n// Safe token encryption helper for stored Meta access tokens (AES-256-GCM).`n`n`$config = require __DIR__ . '/config.inc.php';`n`n"
    $content = $content -replace '^<\?php.*?\n\n', $header
  }

  if ($m.src -eq 'businesses.php') {
    $old = "`$ok = `$stmt->execute();`n    `$stmt->close();`n`n    return `$ok;"
    $new = "`$stmt->execute();`n    `$stmt->close();`n`n    return `$mysqli->affected_rows > 0;"
    $content = $content.Replace($old, $new)
  }

  if ($m.src -eq 'broadcasts.php') {
    $content = $content.Replace('function run_campaign(int $campaignId): array' + "`n{", "function run_campaign(int `$campaignId): array`n{`n    set_time_limit(0);")
  }

  $out = Join-Path $dst $m.dst
  Set-Content -LiteralPath $out -Value $content -Encoding UTF8 -NoNewline
  Write-Output "Wrote $out"
}

# --- merge webhook parser + security into one module ---
$parser   = Get-Content -Raw -LiteralPath (Join-Path $src 'webhook_parser.php')
$security = Get-Content -Raw -LiteralPath (Join-Path $src 'webhook_security.php')
foreach ($pr in $pathRewrites) { $parser = $parser.Replace($pr[0], $pr[1]); $security = $security.Replace($pr[0], $pr[1]) }
foreach ($r in $renames) { $parser = $parser.Replace($r[0], $r[1]); $security = $security.Replace($r[0], $r[1]) }
$security = $security.Replace("env('META_APP_SECRET', '')", "(`$GLOBALS['config']['meta_app_secret'] ?? '')")
$security = $security -replace '^<\?php.*?\n\n', ''
$parser   = $parser   -replace '^<\?php.*?\n\n', ''
$header = "<?php`n`n// _includes/webhook_functions.inc.php`n// Meta webhook intake: X-Hub-Signature-256 verification + payload normalization.`n`n`$config = require __DIR__ . '/config.inc.php';`n`n"
Set-Content -LiteralPath (Join-Path $dst 'webhook_functions.inc.php') -Value ($header + $security + $parser) -Encoding UTF8 -NoNewline
Write-Output 'Wrote webhook_functions.inc.php (merged)'

Write-Output 'Conversion complete.'
