<?php

// includes/whatsapp.php

$config = require __DIR__ . '/../config/config.php';

/**
 * Generic Meta Cloud API sender helper for WhatsApp-message payloads.
 */
function sendWhatsAppPayload(string $to, array $payload, ?array $tenantCredentials = null): array
{
    global $config;

    $apiVersion = $tenantCredentials['api_version'] ?? $config['api_version'] ?? 'v25.0';
    $phoneNumberId = !empty($tenantCredentials['phone_number_id'])
        ? $tenantCredentials['phone_number_id']
        : ($config['phone_number_id'] ?? '');

    $tenantToken = $tenantCredentials['access_token'] ?? '';
    if (!empty($tenantToken) && $tenantToken !== 'EAAG...') {
        $accessToken = $tenantToken;
    } else {
        $accessToken = $config['access_token'] ?? '';
    }

    if (empty($phoneNumberId)) {
        return [
            'success' => false,
            'status' => 400,
            'error' => 'Missing WhatsApp Phone Number ID for the selected business sender.',
        ];
    }

    if (empty($accessToken)) {
        return [
            'success' => false,
            'status' => 401,
            'error' => 'Missing WhatsApp Access Token for the selected business sender.',
        ];
    }

    $url = "https://graph.facebook.com/{$apiVersion}/{$phoneNumberId}/messages";
    $normalizedPayload = [
        'messaging_product' => 'whatsapp',
        'recipient_type' => 'individual',
        'to' => $to,
    ];
    $normalizedPayload = array_merge($normalizedPayload, $payload);

    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST => true,
        CURLOPT_HTTPHEADER => [
            "Authorization: Bearer {$accessToken}",
            "Content-Type: application/json",
        ],
        CURLOPT_POSTFIELDS => json_encode($normalizedPayload),
        CURLOPT_TIMEOUT => 15,
        CURLOPT_CONNECTTIMEOUT => 5,
    ]);

    $response = curl_exec($ch);
    $curlError = curl_error($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($response === false) {
        return [
            'success' => false,
            'status' => 0,
            'error' => 'cURL error: ' . $curlError,
        ];
    }

    $decodedResponse = json_decode($response, true) ?? [];

    return [
        'success' => ($httpCode >= 200 && $httpCode < 300),
        'status' => $httpCode,
        'data' => $decodedResponse,
        'error' => $decodedResponse['error']['message'] ?? null,
    ];
}

/**
 * Send a text message via WhatsApp Cloud API.
 */
function sendTextMessage($to, $message, ?array $tenantCredentials = null)
{
    return sendWhatsAppPayload($to, [
        'type' => 'text',
        'text' => [
            'preview_url' => false,
            'body' => $message,
        ],
    ], $tenantCredentials);
}

/**
 * Send a media message (image, video, audio, document) through Meta Graph API.
 */
function sendMediaMessage(string $to, string $mediaType, string $mediaUrl, ?string $caption = null, ?array $tenantCredentials = null): array
{
    $allowedMediaTypes = ['image', 'video', 'audio', 'document'];
    if (!in_array($mediaType, $allowedMediaTypes, true)) {
        $mediaType = 'image';
    }

    $mediaPayload = [
        'link' => $mediaUrl,
    ];

    if ($caption !== null && $caption !== '') {
        $mediaPayload['caption'] = $caption;
    }

    $payload = [
        'type' => $mediaType,
        $mediaType => $mediaPayload,
    ];

    return sendWhatsAppPayload($to, $payload, $tenantCredentials);
}

/**
 * Send a WhatsApp template message.
 */
function sendTemplateMessage(string $to, string $templateName, string $languageCode = 'en_US', array $components = [], ?array $tenantCredentials = null): array
{
    $payload = [
        'type' => 'template',
        'template' => [
            'name' => $templateName,
            'language' => [
                'code' => $languageCode,
            ],
        ],
    ];

    if ($components !== []) {
        $payload['template']['components'] = $components;
    }

    return sendWhatsAppPayload($to, $payload, $tenantCredentials);
}

/**
 * Send a button-style interactive button message.
 */
function sendInteractiveButtonsMessage(string $to, string $body, array $buttons, ?array $tenantCredentials = null): array
{
    $payload = [
        'type' => 'interactive',
        'interactive' => [
            'type' => 'button',
            'header' => [
                'type' => 'text',
                'text' => 'Menu',
            ],
            'body' => [
                'text' => $body,
            ],
            'footer' => [
                'text' => 'Choose an option',
            ],
            'action' => [
                'buttons' => $buttons,
            ],
        ],
    ];

    return sendWhatsAppPayload($to, $payload, $tenantCredentials);
}

/**
 * Send a list-style interactive message.
 */
function sendInteractiveListMessage(string $to, string $headerText, string $body, string $footerText, string $buttonText, array $sections, ?array $tenantCredentials = null): array
{
    $payload = [
        'type' => 'interactive',
        'interactive' => [
            'type' => 'list',
            'header' => [
                'type' => 'text',
                'text' => $headerText,
            ],
            'body' => [
                'text' => $body,
            ],
            'footer' => [
                'text' => $footerText,
            ],
            'action' => [
                'button' => $buttonText,
                'sections' => $sections,
            ],
        ],
    ];

    return sendWhatsAppPayload($to, $payload, $tenantCredentials);
}

/**
 * Fetch the list of phone numbers attached to a WABA ID.
 *
 * Meta Graph endpoint:
 * GET /{WABA_ID}/phone_numbers
 *
 * @param string $wabaId Meta WhatsApp Business Account ID
 * @param array|null $tenantCredentials Optional tenant credentials array containing access_token and api_version
 * @return array Response structure ['success' => bool, 'status' => int, 'data' => array, 'error' => string|null]
 */
function getWabaPhoneNumbers(string $wabaId, ?array $tenantCredentials = null): array
{
    global $config;

    $apiVersion = $tenantCredentials['api_version'] ?? $config['api_version'] ?? 'v25.0';

    $tenantToken = $tenantCredentials['access_token'] ?? '';
    if (!empty($tenantToken) && $tenantToken !== 'EAAG...') {
        $accessToken = $tenantToken;
    } else {
        $accessToken = $config['access_token'] ?? '';
    }

    if (empty($wabaId)) {
        return [
            'success' => false,
            'status'  => 400,
            'error'   => 'Missing WABA ID for the phone-number lookup request.'
        ];
    }

    if (empty($accessToken)) {
        return [
            'success' => false,
            'status'  => 401,
            'error'   => 'Missing WhatsApp Access Token for the selected business sender.'
        ];
    }

    $url = "https://graph.facebook.com/{$apiVersion}/{$wabaId}/phone_numbers";

    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HTTPGET        => true,
        CURLOPT_HTTPHEADER     => [
            "Authorization: Bearer {$accessToken}",
            "Content-Type: application/json"
        ],
        CURLOPT_TIMEOUT        => 15,
        CURLOPT_CONNECTTIMEOUT => 5,
    ]);

    $response  = curl_exec($ch);
    $curlError = curl_error($ch);
    $httpCode  = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($response === false) {
        return [
            'success' => false,
            'status'  => 0,
            'error'   => 'cURL error: ' . $curlError,
            'data'    => null,
        ];
    }

    $decodedResponse = json_decode($response, true) ?? [];

    $normalizedPhones = [];
    if (isset($decodedResponse['data']) && is_array($decodedResponse['data'])) {
        foreach ($decodedResponse['data'] as $phone) {
            $normalizedPhones[] = [
                'phone_id'             => $phone['id'] ?? null,
                'display_phone_number' => $phone['display_phone_number'] ?? null,
                'display_name'         => $phone['verified_name'] ?? $phone['display_name'] ?? $phone['name'] ?? null,
                'status'               => $phone['status'] ?? null,
                'quality_rating'       => $phone['quality_rating'] ?? null,
            ];
        }
    }

    return [
        'success' => ($httpCode >= 200 && $httpCode < 300),
        'status'  => $httpCode,
        'data'    => [
            'phone_numbers' => $normalizedPhones,
            'raw'           => $decodedResponse,
        ],
        'error'   => $decodedResponse['error']['message'] ?? null,
    ];
}
