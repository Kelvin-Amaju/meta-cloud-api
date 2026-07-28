<?php

//includes/webhook_parser.php

function parseWebhook($payload)
{
    $data = json_decode($payload, true);

    if (!isset($data['entry'][0]['changes'][0]['value']['messages'][0])) {

        return null;

    }

    $message = $data['entry'][0]['changes'][0]['value']['messages'][0];

    return [

        'id' => $message['id'],

        'from' => $message['from'],

        'type' => $message['type'],

        'body' => $message['text']['body'] ?? ''

    ];
}