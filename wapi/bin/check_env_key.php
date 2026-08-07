<?php

$GLOBALS['config'] = require __DIR__ . '/../_includes/config.inc.php';
require_once __DIR__ . '/../_includes/crypto_functions.inc.php';

echo 'key_configured=' . (getEncryptionKey() !== '' ? 'yes' : 'no') . PHP_EOL;

$enc = encryptToken('test-token-123');
echo 'encrypted=' . var_export(str_starts_with($enc, 'enc:v1:'), true) . PHP_EOL;
echo 'roundtrip=' . var_export(decryptToken($enc) === 'test-token-123', true) . PHP_EOL;
