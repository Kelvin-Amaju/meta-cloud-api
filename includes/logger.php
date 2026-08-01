<?php

//includes/logger.php

define(
    'LOG_PATH',
    __DIR__ . '/../storage/logs/'
);

function writeLog($file, $message)
{
    $filename = LOG_PATH . $file;

    $log = "[" . date('Y-m-d H:i:s') . "] ";

    $log .= $message;

    $log .= PHP_EOL;

    file_put_contents(
        $filename,
        $log,
        FILE_APPEND | LOCK_EX
    );
}

function logWebhook($payload)
{
    writeLog(
        'webhook.log',
        $payload
    );
}

function logRequest($payload)
{
    writeLog(
        'requests.log',
        $payload
    );
}

function logError($message)
{
    writeLog(
        'errors.log',
        $message
    );
}
