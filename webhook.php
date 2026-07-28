<?php

$config = require 'config/config.php';

require 'includes/webhook_parser.php';

$message = parseWebhook($payload);

if ($message) {

    print_r($message);

}

if ($_SERVER['REQUEST_METHOD'] == 'GET') {

    if (
        $_GET['hub_mode'] == 'subscribe' &&
        $_GET['hub_verify_token'] == $config['verify_token']
    ) {

        echo $_GET['hub_challenge'];

        exit;
    }

    http_response_code(403);

    exit;
}

$payload = file_get_contents("php://input");

file_put_contents(

    "storage/logs/webhook.log",

    $payload . PHP_EOL,

    FILE_APPEND

);

echo "EVENT_RECEIVED";