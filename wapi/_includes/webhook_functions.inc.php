<?php

// _includes/webhook_functions.inc.php
// Meta webhook intake: X-Hub-Signature-256 verification + payload normalization.

$config = require __DIR__ . '/config.inc.php';

function get_request_signature_header(): ?string
{
    foreach (getallheaders() as $name => $value) {
        if (strcasecmp($name, 'X-Hub-Signature-256') === 0) {
            return (string)$value;
        }
    }

    return null;
}

function verify_webhook_signature(string $rawBody, ?string $signatureHeader): bool
{
    $secret = ($GLOBALS['config']['meta_app_secret'] ?? '');
    if ($secret === '') {
        return false;
    }

    if ($signatureHeader === null || !preg_match('/^sha256=([a-fA-F0-9]{64})$/', $signatureHeader, $matches)) {
        return false;
    }

    $computed = 'sha256=' . hash_hmac('sha256', $rawBody, $secret);
    return hash_equals($computed, $signatureHeader);
}

function parse_webhook($payload)
{
    $data = json_decode($payload, true);
    if (!is_array($data) || !isset($data['entry'][0]['changes'][0]['value'])) {
        return null;
    }

    $value = $data['entry'][0]['changes'][0]['value'];

    $businessPhoneId = $value['metadata']['phone_number_id'] ?? null;

    if (isset($value['statuses'][0])) {
        $statusEvent = $value['statuses'][0];
        return [
            'type' => 'status',
            'wamid' => $statusEvent['id'] ?? ($statusEvent['message']['id'] ?? null),
            'status' => $statusEvent['status'] ?? null,
            'recipient' => $statusEvent['recipient_id'] ?? null,
            'timestamp' => $statusEvent['timestamp'] ?? null,
            'business_phone_id' => $businessPhoneId,
        ];
    }

    if (!isset($value['messages'][0])) {
        return null;
    }

    $message = $value['messages'][0];
    $type = $message['type'] ?? null;

    $body = $message['text']['body'] ?? null;
    if ($body === null && $type !== null && isset($message[$type]['caption'])) {
        $body = $message[$type]['caption'];
    }

    return [
        'type' => 'message',
        'id' => $message['id'] ?? null,
        'from' => $message['from'] ?? null,
        'type_name' => $type,
        'body' => $body ?? '',
        'timestamp' => $message['timestamp'] ?? null,
        'business_phone_id' => $businessPhoneId,
        'media_url' => (is_array($message[$type] ?? null) && isset($message[$type]['url'])) ? $message[$type]['url'] : null,
        'media_type' => in_array($type, ['image', 'video', 'audio', 'document', 'sticker'], true) ? $type : null,
    ];
}