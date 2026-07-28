<?php

// includes/whatsapp.php

$config = require __DIR__ . '/../config/config.php';

/**
 * Send a text message via WhatsApp Cloud API.
 *
 * @param string $to Recipient phone number with country code (e.g., "2348012345678")
 * @param string $message Text content to send
 * @return array
 */
function sendTextMessage($to, $message)
{
    global $config;

    $apiVersion   = $config['api_version'] ?? 'v25.0';
    $phoneNumberId = $config['phone_number_id'] ?? '';
    $accessToken   = $config['access_token'] ?? '';

    $url = "https://graph.facebook.com/{$apiVersion}/{$phoneNumberId}/messages";

    //$url = "http://localhost/netgrity/whatsapp-api/mock_meta.php/{$apiVersion}/{$phoneNumberId}/messages"; // Mock endpoint for testing

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
