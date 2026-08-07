<?php

require __DIR__ . '/../_includes/functions.inc.php';

$sql = file_get_contents(__DIR__ . '/../../sql/migration_full_features.sql');

$lines = explode("\n", $sql);
$stmt  = '';
$ok    = 0;
$fail  = 0;

foreach ($lines as $line) {
    if (preg_match('/^\s*(--|#)/', $line) || trim($line) === '') {
        continue;
    }
    $stmt .= $line . "\n";
    if (substr(rtrim($line), -1) === ';') {
        $s = trim($stmt);
        $s = preg_replace('/^USE `netgrity_wa`;/', '', $s);
        if ($s !== '') {
            if ($mysqli->query($s) !== false) {
                $ok++;
                echo "[OK]   " . substr(strtok($s, "\n"), 0, 60) . PHP_EOL;
            } else {
                $fail++;
                echo "[FAIL] " . substr(strtok($s, "\n"), 0, 60) . " â€” " . $mysqli->error . PHP_EOL;
            }
        }
        $stmt = '';
    }
}

echo "Migration complete: {$ok} succeeded, {$fail} failed.\n";
exit($fail > 0 ? 1 : 0);
