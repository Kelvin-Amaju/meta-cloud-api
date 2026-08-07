<?php

// wapi/bin/sync_templates.php â€” CLI: sync approved templates from Meta for all (or one) business(es).
//
// Usage:
//   php wapi/bin/sync_templates.php              # sync every business
//   php wapi/bin/sync_templates.php 3            # sync only business id 3

require_once __DIR__ . '/../_includes/functions.inc.php';
require_once __DIR__ . '/../_includes/template_functions.inc.php';
require_once __DIR__ . '/../_includes/business_functions.inc.php';

$targetBusinessId = isset($argv[1]) ? (int)$argv[1] : 0;

$businesses = $targetBusinessId > 0
    ? [get_business($targetBusinessId)]
    : get_active_businesses('all');

$ran  = 0;
$ok   = 0;
$fail = 0;

foreach ($businesses as $business) {
    if (!$business) {
        continue;
    }

    $ran++;
    $result = sync_templates_from_meta((int)$business['id']);

    if ($result['success']) {
        $ok++;
        echo "[OK]   Business #{$business['id']} {$business['name']} â€” {$result['count']} template(s)\n";
    } else {
        $fail++;
        echo "[FAIL] Business #{$business['id']} {$business['name']} â€” {$result['error']}\n";
    }
}

echo "Done: {$ran} business(es) checked, {$ok} succeeded, {$fail} failed.\n";
exit($fail > 0 ? 1 : 0);
