<?php

require __DIR__ . '/../includes/init.php';

putenv('APP_ENCRYPTION_KEY');
$r = createBusiness([
    'name'         => 'Crypto Test Biz',
    'product_line' => 'other',
    'phone_number_id' => '9987654321',
    'access_token' => 'EAAfake-token-for-test',
]);

echo 'success=' . var_export($r['success'], true) . PHP_EOL;
echo 'error=' . ($r['error'] ?? '') . PHP_EOL;

$ok = $r['success'] === false
    && is_string($r['error'])
    && str_contains($r['error'], 'APP_ENCRYPTION_KEY')
    && $r['id'] === null;

$stored = $GLOBALS['mysqli']->query("SELECT COUNT(*) AS c FROM businesses WHERE phone_number_id = '9987654321'")->fetch_assoc()['c'];
echo 'stored rows=' . $stored . PHP_EOL;
$ok = $ok && (int)$stored === 0;

exit($ok ? 0 : 1);
