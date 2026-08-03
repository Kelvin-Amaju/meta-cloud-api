<?php

require __DIR__ . '/../includes/oauth.php';

// No META_APP_ID/SECRET configured → must fail fast with a clear error (no network).
$r = metaExchangeOauthCode('abc123', '5', 'http://localhost/business_signup_callback.php');
echo 'missing-creds => success=' . var_export($r['success'], true)
    . ' error=' . ($r['error'] ?? '') . PHP_EOL;

exit($r['success'] === false && str_contains($r['error'], 'META_APP_ID') ? 0 : 1);
