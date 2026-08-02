<?php

//includes/webhook_parser.php

function parseWebhook($payload)
{
    $data = json_decode($payload, true);
    if (!is_array($data) || !isset($data['entry'][0]['changes'][0]['value'])) {
        return null;
    }

    $value = $data['entry'][0]['changes'][0]['value'];

    if (isset($value['statuses'][0])) {
        $statusEvent = $value['statuses'][0];
        return [
            'type' => 'status',
            'wamid' => $statusEvent['id'] ?? ($statusEvent['message']['id'] ?? null),
            'status' => $statusEvent['status'] ?? null,
            'recipient' => $statusEvent['recipient_id'] ?? null,
            'timestamp' => $statusEvent['timestamp'] ?? null,
        ];
    }

    if (!isset($value['messages'][0])) {
        return null;
    }

    $message = $value['messages'][0];

    return [
        'type' => 'message',
        'id' => $message['id'] ?? null,
        'from' => $message['from'] ?? null,
        'type_name' => $message['type'] ?? null,
        'body' => $message['text']['body'] ?? '',
        'timestamp' => $message['timestamp'] ?? null,
    ];
}