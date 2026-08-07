<?php

// _includes/logger_functions.inc.php
// File logger → storage/logs/ (reference §4.8: keep display_errors off, log to files).

define('LOG_PATH', dirname(__DIR__) . '/storage/logs/');

function writeLog(string $file, string $message): void
{
    $line = '[' . date('Y-m-d H:i:s') . '] ' . $message . PHP_EOL;
    file_put_contents(LOG_PATH . $file, $line, FILE_APPEND | LOCK_EX);
}

function logWebhook(string $payload): void
{
    writeLog('webhook.log', $payload);
}

function logRequest(string $payload): void
{
    writeLog('requests.log', $payload);
}

function logError(string $message): void
{
    writeLog('errors.log', $message);
}
