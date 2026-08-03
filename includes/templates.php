<?php

// includes/templates.php
//
// Mock template data + template-send function. Swap MOCK_TEMPLATES / the
// body of getTemplatesForBusiness() for a real DB query or Meta API call
// once you have actual approved templates — call sites elsewhere don't
// need to change.

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/businesses.php';

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
 * This keeps the legacy send.php contract intact while avoiding the duplicate
 * helper name that now exists in includes/whatsapp.php.
 *
 * @param string $phone       Recipient phone, digits only
 * @param array  $template    ['name' => string, 'language' => string]
 * @param array  $variables   Ordered list of values for {{1}}, {{2}}, ...
 * @param array  $business    Business row (needs phone_number_id, access_token)
 */
function sendLegacyTemplateMessage(string $phone, array $template, array $variables, array $business): array
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

/**
 * Pull approved/pending templates from Meta Graph API for a business
 * and upsert them into message_templates.
 *
 * @return array ['success' => bool, 'count' => int, 'error' => ?string]
 */
function syncTemplatesFromMeta(int $businessId): array
{
    global $mysqli;

    $business = getBusinessById($businessId);
    if (!$business) {
        return ['success' => false, 'count' => 0, 'error' => 'Business not found.'];
    }
    if (empty($business['waba_id']) || empty($business['access_token'])) {
        return ['success' => false, 'count' => 0, 'error' => 'Business is missing waba_id or access_token — configure Meta credentials first.'];
    }

    $apiVersion = env('META_API_VERSION', 'v25.0');
    $url = "https://graph.facebook.com/{$apiVersion}/{$business['waba_id']}/message_templates"
        . "?limit=1000&fields=id,name,language,category,status,components";

    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HTTPHEADER     => ['Authorization: Bearer ' . $business['access_token']],
        CURLOPT_TIMEOUT        => 30,
    ]);

    $body = curl_exec($ch);
    $curlError = curl_error($ch);
    $httpCode  = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($body === false) {
        return ['success' => false, 'count' => 0, 'error' => 'cURL error: ' . $curlError];
    }

    $data = json_decode($body, true);
    if ($httpCode < 200 || $httpCode >= 300 || !isset($data['data'])) {
        return ['success' => false, 'count' => 0, 'error' => $data['error']['message'] ?? ('HTTP ' . $httpCode)];
    }

    $templates = $data['data'];
    $count = 0;

    foreach ($templates as $tpl) {
        $bodyText = '';
        foreach (($tpl['components'] ?? []) as $comp) {
            if (($comp['type'] ?? '') === 'BODY') {
                $bodyText = $comp['text'] ?? '';
                break;
            }
        }

        preg_match_all('/\{\{\d+\}\}/', $bodyText, $m);
        $variableCount = count($m[0]);

        $status = strtolower($tpl['status'] ?? 'draft');
        $allowed = ['approved', 'pending', 'rejected', 'draft'];
        if (!in_array($status, $allowed, true)) {
            $status = 'draft';
        }

        $category = strtolower($tpl['category'] ?? 'utility');
        $allowedCategories = ['utility', 'marketing', 'authentication'];
        if (!in_array($category, $allowedCategories, true)) {
            $category = 'utility';
        }

        $stmt = $mysqli->prepare(
            "INSERT INTO message_templates
                (business_id, meta_template_id, name, language, category, status, body_text, variable_count, created_at)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, NOW())
             ON DUPLICATE KEY UPDATE
                meta_template_id = VALUES(meta_template_id),
                category         = VALUES(category),
                status           = VALUES(status),
                body_text        = VALUES(body_text),
                variable_count   = VALUES(variable_count),
                updated_at       = NOW()"
        );
        if (!$stmt) {
            continue;
        }

        $stmt->bind_param(
            "issssssi",
            $businessId,
            $tpl['id'] ?? null,
            $tpl['name'] ?? '',
            $tpl['language'] ?? 'en_US',
            $category,
            $status,
            $bodyText,
            $variableCount
        );
        if ($stmt->execute()) {
            $count++;
        }
        $stmt->close();
    }

    return ['success' => true, 'count' => $count, 'error' => null];
}

/**
 * Create a template record manually (saved as draft until approved in Meta).
 */
function createTemplate(array $data): array
{
    global $mysqli;

    $businessId = (int)($data['business_id'] ?? 0);
    $name       = trim($data['name'] ?? '');
    $language   = trim($data['language'] ?? 'en_US');
    $category   = trim($data['category'] ?? 'utility');
    $bodyText   = trim($data['body_text'] ?? '');

    if ($businessId <= 0) {
        return ['success' => false, 'id' => null, 'error' => 'Sender business is required.'];
    }
    if ($name === '') {
        return ['success' => false, 'id' => null, 'error' => 'Template name is required.'];
    }
    if ($bodyText === '') {
        return ['success' => false, 'id' => null, 'error' => 'Template body text is required.'];
    }

    $allowedCategories = ['utility', 'marketing', 'authentication'];
    if (!in_array($category, $allowedCategories, true)) {
        $category = 'utility';
    }

    preg_match_all('/\{\{\d+\}\}/', $bodyText, $m);
    $variableCount = count($m[0]);

    $stmt = $mysqli->prepare(
        "INSERT INTO message_templates
            (business_id, name, language, category, status, body_text, variable_count, created_at)
         VALUES (?, ?, ?, ?, 'draft', ?, ?, NOW())"
    );
    if (!$stmt) {
        return ['success' => false, 'id' => null, 'error' => 'Failed to prepare INSERT: ' . $mysqli->error];
    }

    $stmt->bind_param("issssi", $businessId, $name, $language, $category, $bodyText, $variableCount);
    $ok  = $stmt->execute();
    $id  = $ok ? $stmt->insert_id : null;
    $err = $ok ? null : $stmt->error;
    $stmt->close();

    return ['success' => $ok, 'id' => $id, 'error' => $err];
}

/**
 * Delete a template record by id.
 */
function deleteTemplate(int $id): bool
{
    global $mysqli;

    $stmt = $mysqli->prepare("DELETE FROM message_templates WHERE id = ?");
    if (!$stmt) return false;
    $stmt->bind_param("i", $id);
    $ok = $stmt->execute();
    $stmt->close();

    return $ok;
}