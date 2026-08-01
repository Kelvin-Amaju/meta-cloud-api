<?php

// webhook.php

$config = require __DIR__ . '/config/config.php';

require __DIR__ . '/includes/logger.php';
require __DIR__ . '/includes/webhook_parser.php';

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

    // Log the raw payload for auditing / debugging
    logWebhook($payload);

    // Parse the payload into a normalised internal format
    $message = parseWebhook($payload);

    if ($message) {
        // TODO: handle the incoming message
        // e.g. save to DB, trigger auto-reply, etc.
        logWebhook('Parsed message from ' . $message['from'] . ': ' . $message['body']);
    }

    // Meta requires HTTP 200 + exactly this text; anything else triggers retries
    http_response_code(200);
    echo 'EVENT_RECEIVED';
    exit;
}

// Any other HTTP method → reject
http_response_code(405);
exit;