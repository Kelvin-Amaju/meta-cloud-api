<?php

class MetaWebhookParser
{
    public static function parse(array $payload): ?array
    {
        if (
            !isset(
                $payload['entry'][0]['changes'][0]['value']['messages'][0]
            )
        ) {
            return null;
        }

        $message =
            $payload['entry'][0]['changes'][0]['value']['messages'][0];

        return [

            'message_id' => $message['id'],

            'from' => $message['from'],

            'timestamp' => $message['timestamp'],

            'type' => $message['type'],

            'body' => $message['text']['body'] ?? ''

        ];
    }
}