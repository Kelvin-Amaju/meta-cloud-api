<?php

$config = require '../config.php';

if ($_SERVER['REQUEST_METHOD'] === 'GET') {

    if (
        $_GET['hub_mode'] === 'subscribe' &&
        $_GET['hub_verify_token'] === $config['verify_token']
    ) {
        http_response_code(200);
        echo $_GET['hub_challenge'];
        exit;
    }

    http_response_code(403);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $payload = file_get_contents("php://input");

    file_put_contents(
        "../storage/logs/webhook.log",
        date('Y-m-d H:i:s') . PHP_EOL .
        $payload .
        PHP_EOL .
        str_repeat('-',80) .
        PHP_EOL,
        FILE_APPEND
    );

    http_response_code(200);
    echo "EVENT_RECEIVED";
}

$payload = json_decode(file_get_contents("php://input"), true);

$message = MetaWebhookParser::parse($payload);

if ($message) {

    print_r($message);

}