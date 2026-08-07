<?php

// includes/oauth.php
//
// Shared Meta OAuth / Embedded Signup token exchange.
// Both callback.php and business_signup_callback.php use this.
// Credentials are persisted into the `businesses` table (the app's source of truth).

require_once __DIR__ . '/config.inc.php';
require_once __DIR__ . '/db.inc.php';
require_once __DIR__ . '/crypto_functions.inc.php';

/**
 * Exchange an authorization code for a WhatsApp token and persist it on a business.
 *
 * @param string $code        Authorization code returned by Meta
 * @param string $state       Opaque state; MUST be the target business id (from `businesses`)
 * @param string $redirectUri The exact redirect_uri used when the flow was started
 * @return array ['success' => bool, 'business_id' => int|null, 'waba_id' => string|null, 'error' => string|null]
 */
function metaExchangeOauthCode(string $code, string $state, string $redirectUri): array
{
    global $mysqli;

    $config = require __DIR__ . '/config.inc.php';

    $appId     = $config['meta_app_id'];
    $appSecret = $config['meta_app_secret'];
    $version   = $config['api_version'];

    if ($appId === '' || $appSecret === '') {
        return ['success' => false, 'business_id' => null, 'waba_id' => null, 'error' => 'Missing Meta app credentials. Define META_APP_ID and META_APP_SECRET in config.inc.php.'];
    }

    $businessId = ctype_digit($state) ? (int)$state : 0;

    // 1. Exchange the authorization code for an access token
    $ch = curl_init();
    curl_setopt_array($ch, [
        CURLOPT_URL        => "https://graph.facebook.com/{$version}/oauth/access_token",
        CURLOPT_POST       => true,
        CURLOPT_POSTFIELDS => http_build_query([
            'client_id'     => $appId,
            'client_secret' => $appSecret,
            'redirect_uri'  => $redirectUri,
            'code'          => $code,
        ]),
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT        => 30,
    ]);
    $response = curl_exec($ch);
    $curlErr  = curl_error($ch);
    curl_close($ch);

    if ($curlErr !== '') {
        return ['success' => false, 'business_id' => null, 'waba_id' => null, 'error' => 'Token exchange failed: ' . $curlErr];
    }

    $tokenResponse = json_decode((string)$response, true);
    if (!isset($tokenResponse['access_token'])) {
        return ['success' => false, 'business_id' => null, 'waba_id' => null, 'error' => 'Token exchange failed: ' . (string)$response];
    }
    $accessToken = $tokenResponse['access_token'];

    // 2. Resolve the WhatsApp Business Account connected to this token
    $ch = curl_init();
    curl_setopt_array($ch, [
        CURLOPT_URL        => "https://graph.facebook.com/{$version}/me/businesses",
        CURLOPT_HTTPHEADER => ['Authorization: Bearer ' . $accessToken],
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT        => 30,
    ]);
    $response = curl_exec($ch);
    $curlErr  = curl_error($ch);
    curl_close($ch);

    if ($curlErr !== '') {
        return ['success' => false, 'business_id' => null, 'waba_id' => null, 'error' => 'WABA lookup failed: ' . $curlErr];
    }

    $businessData = json_decode((string)$response, true);
    $waba         = $businessData['data'][0] ?? null;

    if (!$waba || empty($waba['id'])) {
        return ['success' => false, 'business_id' => null, 'waba_id' => null, 'error' => 'No WhatsApp Business Account found for this token.'];
    }
    $wabaId   = $waba['id'];
    $wabaName = trim($waba['name'] ?? '');

    if ($businessId <= 0) {
        return ['success' => false, 'business_id' => null, 'waba_id' => $wabaId, 'error' => 'The OAuth `state` must be a valid business id. Create the business first (Business > Add), then connect it via Meta.'];
    }

    // 3. Persist into the businesses table
    try {
        $storedToken = encryptToken($accessToken);
    } catch (RuntimeException $e) {
        return ['success' => false, 'business_id' => $businessId, 'waba_id' => $wabaId, 'error' => $e->getMessage()];
    }

    $stmt = $mysqli->prepare(
        "UPDATE businesses
            SET waba_id = ?, access_token = ?, status = 'active',
                onboarding_method = 'embedded_signup', onboarded_at = NOW(), updated_at = NOW()
          WHERE id = ?"
    );
    if (!$stmt) {
        return ['success' => false, 'business_id' => $businessId, 'waba_id' => $wabaId, 'error' => 'Failed to prepare UPDATE: ' . $mysqli->error];
    }
    $stmt->bind_param('ssi', $wabaId, $storedToken, $businessId);
    $ok = $stmt->execute();
    $stmt->close();

    if (!$ok) {
        return ['success' => false, 'business_id' => $businessId, 'waba_id' => $wabaId, 'error' => 'Failed to save connection: ' . $mysqli->error];
    }

    return ['success' => true, 'business_id' => $businessId, 'waba_id' => $wabaId, 'error' => null];
}
