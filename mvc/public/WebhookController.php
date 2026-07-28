<?php

require_once APP_PATH . '/Helpers/MetaWebhookParser.php';
require_once APP_PATH . '/Handlers/MessageHandler.php';

class WebhookController
{
    public function receive()
    {
        $payload = json_decode(file_get_contents('php://input'), true);

        file_put_contents(
            APP_PATH . '/../storage/logs/webhook.log',
            json_encode($payload, JSON_PRETTY_PRINT) . PHP_EOL,
            FILE_APPEND
        );

        $event = MetaWebhookParser::parse($payload);

        if ($event) {

            MessageHandler::handle($event);

        }

        http_response_code(200);
        echo "EVENT_RECEIVED";
    }
}