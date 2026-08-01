<?php

// includes/whatsapp.php

$config = require __DIR__ . '/../config/config.php';

/**
 * Send a text message via WhatsApp Cloud API.
 *
 * @param string $to Recipient phone number with country code (e.g., "2348012345678")
 * @param string $message Text content to send
 * @param array|null $tenantCredentials Optional tenant credentials array containing phone_number_id, access_token, api_version
 * @return array Response structure ['success' => bool, 'status' => int, 'data' => array, 'error' => string|null]
 */
function sendTextMessage($to, $message, ?array $tenantCredentials = null)
{
    global $config;

    $apiVersion    = $tenantCredentials['api_version'] ?? $config['api_version'] ?? 'v25.0';
    
    // Resolve Phone Number ID (tenant specific -> fallback to env config)
    $phoneNumberId = !empty($tenantCredentials['phone_number_id']) 
        ? $tenantCredentials['phone_number_id'] 
        : ($config['phone_number_id'] ?? '');

    // Resolve Access Token (tenant specific -> fallback to env config)
    // Note: If tenant token is placeholder 'EAAG...', fall back to .env if available
    $tenantToken = $tenantCredentials['access_token'] ?? '';
    if (!empty($tenantToken) && $tenantToken !== 'EAAG...') {
        $accessToken = $tenantToken;
    } else {
        $accessToken = $config['access_token'] ?? '';
    }

    if (empty($phoneNumberId)) {
        return [
            'success' => false,
            'status'  => 400,
            'error'   => 'Missing WhatsApp Phone Number ID for the selected business sender.'
        ];
    }

    if (empty($accessToken)) {
        return [
            'success' => false,
            'status'  => 401,
            'error'   => 'Missing WhatsApp Access Token for the selected business sender.'
        ];
    }

    $url = "https://graph.facebook.com/{$apiVersion}/{$phoneNumberId}/messages";

    $payload = [
        "messaging_product" => "whatsapp",
        "recipient_type"    => "individual",
        "to"                => $to,
        "type"              => "text",
        "text"              => [
            "preview_url" => false,
            "body"        => $message
        ]
    ];

    $ch = curl_init($url);

    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST           => true,
        CURLOPT_HTTPHEADER     => [
            "Authorization: Bearer {$accessToken}",
            "Content-Type: application/json"
        ],
        CURLOPT_POSTFIELDS     => json_encode($payload),
        CURLOPT_TIMEOUT        => 15, // Prevents hanging requests if Meta is slow
        CURLOPT_CONNECTTIMEOUT => 5
    ]);

    $response  = curl_exec($ch);
    $curlError = curl_error($ch);
    $httpCode  = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    // Network / cURL connection error handling
    if ($response === false) {
        return [
            'success' => false,
            'status'  => 0,
            'error'   => 'cURL error: ' . $curlError
        ];
    }

    $decodedResponse = json_decode($response, true) ?? [];

    return [
        'success' => ($httpCode >= 200 && $httpCode < 300),
        'status'  => $httpCode,
        'data'    => $decodedResponse
    ];
}
