<?php

// bin/smoke_test.php — render each page in its own PHP process to catch runtime errors

$pages = [
    'home.php',
    'inbox.php',
    'contacts.php',
    'templates.php',
    'broadcast.php',
    'analytics.php',
    'messages.php',
    'send.php',
    'business/index.php',
    'business/add.php',
    'test.php',
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
