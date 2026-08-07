<?php

require __DIR__ . '/../_includes/functions.inc.php';
require_once __DIR__ . '/../_includes/business_functions.inc.php';

$origKey = $GLOBALS['config']['encryption_key'];
$GLOBALS['config']['encryption_key'] = '';
$r = create_business([
    'name'         => 'Crypto Test Biz',
    'product_line' => 'other',
    'phone_number_id' => '9987654321',
    'access_token' => 'EAAfake-token-for-test',
]);
$GLOBALS['config']['encryption_key'] = $origKey;

echo 'success=' . var_export($r['success'], true) . PHP_EOL;
echo 'error=' . ($r['error'] ?? '') . PHP_EOL;

$ok = $r['success'] === false
    && is_string($r['error'])
    && str_contains($r['error'], 'encryption_key')
    && $r['id'] === null;

$stored = $GLOBALS['mysqli']->query("SELECT COUNT(*) AS c FROM businesses WHERE phone_number_id = '9987654321'")->fetch_assoc()['c'];
echo 'stored rows=' . $stored . PHP_EOL;
$ok = $ok && (int)$stored === 0;

exit($ok ? 0 : 1);
