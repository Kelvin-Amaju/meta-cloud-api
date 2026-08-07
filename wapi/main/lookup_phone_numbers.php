<?php

// lookup_phone_numbers.php — JSON endpoint for WABA phone-number lookup
// (ported from business/lookup_phone_numbers.php)
// Uses the server-side get_waba_phone_numbers() — no client-supplied token.

require_once __DIR__ . '/../_includes/functions.inc.php';
require_once __DIR__ . '/../_includes/whatsapp_functions.inc.php';

header('Content-Type: application/json');

$wabaId = trim((string)($_GET['waba_id'] ?? ''));
if ($wabaId === '') {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => 'Missing WABA ID in the request.',
        'phones'  => [],
    ]);
    exit;
}

$result = get_waba_phone_numbers($wabaId);

if (!$result['success']) {
    http_response_code((int)($result['status'] ?: 500));
    echo json_encode([
        'success' => false,
        'message' => $result['error'] ?? 'Unable to fetch phone numbers from Meta.',
        'phones'  => [],
    ]);
    exit;
}

$phones = $result['data']['phone_numbers'] ?? [];

http_response_code(200);
echo json_encode([
    'success' => true,
    'message' => 'Phone numbers retrieved successfully.',
    'phones'  => $phones,
]);
exit;
