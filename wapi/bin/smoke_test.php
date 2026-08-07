<?php

// wapi/bin/smoke_test.php — render each page in its own PHP process to catch runtime errors

$pages = [
    'wapi/main/home.php',
    'wapi/main/inbox.php',
    'wapi/main/contacts.php',
    'wapi/main/templates.php',
    'wapi/main/broadcast.php',
    'wapi/main/analytics.php',
    'wapi/main/messages.php',
    'wapi/main/send.php',
    'wapi/main/business_index.php',
    'wapi/main/business_add.php',
    'wapi/main/business_edit.php',
    'wapi/main/business_view.php',
    'wapi/main/lookup_phone_numbers.php',
    'wapi/main/settings_whatsapp.php',
    'wapi/main/test.php',
];

$failed = 0;

foreach ($pages as $page) {
    $cmd = escapeshellarg(PHP_BINARY) . ' ' . escapeshellarg(__DIR__ . '/smoke_one.php') . ' ' . escapeshellarg($page);
    $output = [];
    $code   = 0;
    exec($cmd . ' 2>&1', $output, $code);
    $out = implode("\n", $output);
    if (strpos($out, '[FAIL]') !== false || $code !== 0) {
        $failed++;
        echo "[FAIL] {$page}\n{$out}\n";
    } else {
        echo "[OK]   {$page}\n";
    }
}

echo $failed === 0 ? "All pages rendered.\n" : "{$failed} page(s) failed.\n";
exit($failed > 0 ? 1 : 0);
