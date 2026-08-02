<?php

require_once __DIR__ . '/../includes/init.php';

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

$systemUserToken = $config['access_token'] ?? '';
if (empty($systemUserToken)) {
    http_response_code(401);
    echo json_encode([
        'success' => false,
        'message' => 'META_ACCESS_TOKEN is not configured in .env.',
        'phones'  => [],
    ]);
    exit;
}

$result = getWabaPhoneNumbers($wabaId, [
    'access_token' => $systemUserToken,
    'api_version'  => $config['api_version'] ?? 'v25.0',
]);

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
