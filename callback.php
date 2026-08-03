<?php

// callback.php
//
// Meta OAuth callback (classic / Embedded Signup code exchange).
// Persists the returned token into the `businesses` table (the app's source of truth),
// then redirects to the Settings > WhatsApp page.
//
// Expected query params: ?code=...&state=<business_id>

require_once __DIR__ . '/includes/oauth.php';

$code  = $_GET['code'] ?? '';
$state = $_GET['state'] ?? '';

if ($code === '' || $state === '') {
    die('Missing code or state parameter. Start the flow from Settings > WhatsApp.');
}

$config       = require __DIR__ . '/config/config.php';
$redirectUri  = rtrim($config['app_url'] ?? 'http://localhost', '/') . '/callback.php';

$result = metaExchangeOauthCode($code, $state, $redirectUri);

if (!$result['success']) {
    http_response_code(400);
    die('Connection failed: ' . htmlspecialchars($result['error']));
}

header('Location: settings/whatsapp?connected=1&business=' . (int)$result['business_id']);
exit;
