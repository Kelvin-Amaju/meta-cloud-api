<?php

require __DIR__ . '/../_includes/functions.inc.php';
require_once __DIR__ . '/../_includes/oauth_functions.inc.php';

// No META_APP_ID/SECRET configured â†’ must fail fast with a clear error (no network).
$r = metaExchangeOauthCode('abc123', '5', 'http://localhost/business_signup_callback.php');
echo 'missing-creds => success=' . var_export($r['success'], true)
    . ' error=' . ($r['error'] ?? '') . PHP_EOL;

exit($r['success'] === false && str_contains($r['error'], 'META_APP_ID') ? 0 : 1);
