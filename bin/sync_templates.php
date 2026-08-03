<?php

// bin/sync_templates.php — CLI: sync approved templates from Meta for all (or one) business(es).
//
// Usage:
//   php bin/sync_templates.php              # sync every business
//   php bin/sync_templates.php 3            # sync only business id 3

require_once __DIR__ . '/../includes/init.php';
require_once __DIR__ . '/../includes/templates.php';

$targetBusinessId = isset($argv[1]) ? (int)$argv[1] : 0;

$businesses = $targetBusinessId > 0
    ? [getBusinessById($targetBusinessId)]
    : getActiveBusinesses('all');

$ran  = 0;
$ok   = 0;
$fail = 0;

foreach ($businesses as $business) {
    if (!$business) {
        continue;
    }

    $ran++;
    $result = syncTemplatesFromMeta((int)$business['id']);

    if ($result['success']) {
        $ok++;
        echo "[OK]   Business #{$business['id']} {$business['name']} — {$result['count']} template(s)\n";
    } else {
        $fail++;
        echo "[FAIL] Business #{$business['id']} {$business['name']} — {$result['error']}\n";
    }
}

echo "Done: {$ran} business(es) checked, {$ok} succeeded, {$fail} failed.\n";
exit($fail > 0 ? 1 : 0);
