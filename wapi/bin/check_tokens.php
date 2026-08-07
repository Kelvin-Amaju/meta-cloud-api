<?php

require __DIR__ . '/../_includes/functions.inc.php';

$r = $mysqli->query('SELECT id, name, LEFT(access_token, 30) AS tok, waba_id, phone_number_id, status FROM businesses ORDER BY id');
while ($row = $r->fetch_assoc()) {
    $enc = str_starts_with((string)$row['tok'], 'enc:v1:') ? 'ENCRYPTED' : 'PLAINTEXT';
    echo "#{$row['id']} {$row['name']} | status={$row['status']} | waba={$row['waba_id']} | phone_id={$row['phone_number_id']} | token=" . $enc . " | " . $row['tok'] . PHP_EOL;
}
echo 'encryption_key set: ' . (($GLOBALS['config']['encryption_key'] ?? '') !== '' ? 'yes' : 'NO') . PHP_EOL;
