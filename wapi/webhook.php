<?php

// webhook.php
// Meta Cloud API webhook entry point (ported from root webhook.php).

require_once __DIR__ . '/_includes/functions2.inc.php';
require_once __DIR__ . '/_includes/webhook_functions.inc.php';
require_once __DIR__ . '/_includes/business_functions.inc.php';
require_once __DIR__ . '/_includes/message_functions.inc.php';

// ─────────────────────────────────────────────
// 1. WEBHOOK VERIFICATION (GET — Meta handshake)
// ─────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'GET') {

    $mode        = $_GET['hub_mode']         ?? '';
    $token       = $_GET['hub_verify_token'] ?? '';
    $challenge   = $_GET['hub_challenge']    ?? '';

    if ($mode === 'subscribe' && $token === $config['verify_token']) {
        http_response_code(200);
        echo $challenge;
        exit;
    }

    http_response_code(403);
    exit;
}

// ─────────────────────────────────────────────
// 2. INCOMING EVENTS (POST — Meta delivers events)
// ─────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    // Read the raw request body
    $payload = file_get_contents('php://input');
    $signatureHeader = get_request_signature_header();

    if (!verify_webhook_signature($payload, $signatureHeader)) {
        http_response_code(401);
        logWebhook('Rejected webhook POST: missing/invalid X-Hub-Signature-256 header.');
        exit;
    }

    // Log the raw payload for auditing / debugging
    logWebhook($payload);

    // Parse the payload into a normalised internal format
    $event = parse_webhook($payload);

    if ($event) {
        // Resolve the receiving business from the metadata phone_number_id
        $businessId = null;
        if (!empty($event['business_phone_id'])) {
            $business = get_business_by_phone_number_id($event['business_phone_id']);
            $businessId = $business['id'] ?? null;
        }

        if (($event['type'] ?? null) === 'status' && !empty($event['wamid'])) {
            $status = $event['status'] ?? 'sent';
            update_message_status_by_wamid($event['wamid'], $status, $businessId, $event['timestamp'] ?? null);
            logWebhook('Parsed status update for ' . $event['wamid'] . ' -> ' . $status);

        } elseif (($event['type'] ?? null) === 'message' && !empty($event['id'])) {
            $saved = false;
            if ($businessId) {
                $saved = save_inbound_message(
                    $businessId,
                    $event['id'],
                    $event['from'] ?? '',
                    $event['type_name'] ?? 'text',
                    $event['body'] ?? '',
                    $event['media_url'] ?? null,
                    $event['media_type'] ?? null,
                    $event['timestamp'] ?? null
                );
            }
            logWebhook('Parsed message from ' . ($event['from'] ?? 'unknown') . ': ' . ($event['body'] ?? '')
                . ($saved ? ' [saved]' : ' [NOT saved - no matching business phone_number_id]'));
        }
    }

    // Meta requires HTTP 200 + exactly this text; anything else triggers retries
    http_response_code(200);
    echo 'EVENT_RECEIVED';
    exit;
}

// Any other HTTP method → reject
http_response_code(405);
exit;
