<?php

// callback.php
//
// Meta OAuth callback (classic / Embedded Signup code exchange).
// Persists the returned token into the `businesses` table (the app's source of truth),
// then redirects to Settings > WhatsApp.
//
// Expected query params: ?code=...&state=<business_id>
//
// PUBLIC endpoint — uses the functions2 bootstrap (no sessions / CSRF).

require_once __DIR__ . '/_includes/functions2.inc.php';
require_once __DIR__ . '/_includes/oauth_functions.inc.php';

$code  = $_GET['code'] ?? '';
$state = $_GET['state'] ?? '';

if ($code === '' || $state === '') {
    die('Missing code or state parameter. Start the flow from Settings > WhatsApp.');
}

$redirectUri = rtrim($config['app_url'] ?? 'http://localhost', '/') . '/callback.php';

$result = metaExchangeOauthCode($code, $state, $redirectUri);

if (!$result['success']) {
    http_response_code(400);
    die('Connection failed: ' . htmlspecialchars($result['error']));
}

header('Location: main/settings_whatsapp.php?connected=1&business=' . (int)$result['business_id']);
exit;
