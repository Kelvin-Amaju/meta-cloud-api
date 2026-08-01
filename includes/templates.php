<?php

// includes/templates.php
//
// Mock template data + template-send function. Swap MOCK_TEMPLATES / the
// body of getTemplatesForBusiness() for a real DB query or Meta API call
// once you have actual approved templates — call sites elsewhere don't
// need to change.

require_once __DIR__ . '/../config/config.php';

/**
 * Get the list of approved templates available for a given business.
 *
 * Reads from message_templates, populated by bin/sync_templates.php
 * (pulls from Meta's Graph API — see that script for the sync logic).
 * Run the sync any time templates are added/edited/approved in Meta
 * Business Manager to keep this table current.
 */
function getTemplatesForBusiness(int $businessId): array
{
    global $mysqli;

    $stmt = $mysqli->prepare(
        "SELECT id, name, language, category, status, body_text, variable_count
         FROM message_templates
         WHERE business_id = ? AND status = 'approved'
         ORDER BY name ASC"
    );
    $stmt->bind_param("i", $businessId);
    $stmt->execute();

    $result = $stmt->get_result();
    $templates = [];

    while ($row = $result->fetch_assoc()) {
        $row['variable_count'] = (int)$row['variable_count'];
        $templates[] = $row;
    }

    $stmt->close();
    return $templates;
}

/**
 * Find a single template by name within a business's available templates.
 */
function getTemplateByName(int $businessId, string $name): ?array
{
    foreach (getTemplatesForBusiness($businessId) as $tpl) {
        if ($tpl['name'] === $name) {
            return $tpl;
        }
    }
    return null;
}

/**
 * Send a WhatsApp template message via Meta Cloud API.
 *
 * ASSUMPTION: mirrors the presumed shape of your existing sendTextMessage()
 * — same return contract (['success', 'status', 'data', 'error']) so
 * send.php can handle both the same way. If your real sendTextMessage()
 * builds requests differently (shared cURL wrapper, different header
 * setup, api_version pulled from config differently), point me at it and
 * I'll align this exactly instead of duplicating the request logic.
 *
 * @param string $phone       Recipient phone, digits only
 * @param array  $template    ['name' => string, 'language' => string]
 * @param array  $variables   Ordered list of values for {{1}}, {{2}}, ...
 * @param array  $business    Business row (needs phone_number_id, access_token)
 */
function sendTemplateMessage(string $phone, array $template, array $variables, array $business): array
{
    $apiVersion = env('META_API_VERSION', 'v25.0');
    $url = "https://graph.facebook.com/{$apiVersion}/{$business['phone_number_id']}/messages";

    $components = [];
    if (!empty($variables)) {
        $components[] = [
            'type'       => 'body',
            'parameters' => array_map(
                fn($value) => ['type' => 'text', 'text' => (string)$value],
                $variables
            ),
        ];
    }

    $payload = [
        'messaging_product' => 'whatsapp',
        'to'                => $phone,
        'type'              => 'template',
        'template'          => [
            'name'       => $template['name'],
            'language'   => ['code' => $template['language'] ?? 'en_US'],
            'components' => $components,
        ],
    ];

    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST           => true,
        CURLOPT_POSTFIELDS     => json_encode($payload),
        CURLOPT_HTTPHEADER     => [
            'Authorization: Bearer ' . $business['access_token'],
            'Content-Type: application/json',
        ],
        CURLOPT_TIMEOUT        => 20,
    ]);

    $responseBody = curl_exec($ch);
    $curlError    = curl_error($ch);
    $httpCode     = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($responseBody === false) {
        return [
            'success' => false,
            'status'  => 0,
            'data'    => null,
            'error'   => 'cURL error: ' . $curlError,
        ];
    }

    $data = json_decode($responseBody, true);

    return [
        'success' => $httpCode >= 200 && $httpCode < 300,
        'status'  => $httpCode,
        'data'    => $data,
        'error'   => $data['error']['message'] ?? null,
    ];
}