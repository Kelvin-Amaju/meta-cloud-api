<?php

// wapi/bin/encrypt_existing_tokens.php
//
// Encrypts any access tokens still stored as plaintext in the businesses table.
// Idempotent â€” skips rows that are already `enc:v1:`-encrypted or empty.

require __DIR__ . '/../_includes/functions.inc.php';
require_once __DIR__ . '/../_includes/crypto_functions.inc.php';

$result = $mysqli->query('SELECT id, name, access_token FROM businesses ORDER BY id');

$migrated = 0;
$skipped  = 0;
$errors   = 0;

while ($row = $result->fetch_assoc()) {
    $raw = (string)$row['access_token'];

    if ($raw === '') {
        echo "[SKIP] #{$row['id']} {$row['name']} â€” no token\n";
        $skipped++;
        continue;
    }

    if (str_starts_with($raw, 'enc:v1:')) {
        echo "[SKIP] #{$row['id']} {$row['name']} â€” already encrypted\n";
        $skipped++;
        continue;
    }

    try {
        $enc = encryptToken($raw);
    } catch (RuntimeException $e) {
        echo "[ERROR] #{$row['id']} {$row['name']} â€” {$e->getMessage()}\n";
        $errors++;
        continue;
    }

    if (!is_string($enc) || !str_starts_with($enc, 'enc:v1:')) {
        echo "[ERROR] #{$row['id']} {$row['name']} â€” encryption produced no enc:v1: payload\n";
        $errors++;
        continue;
    }

    // Sanity: ensure the ciphertext decrypts back to the original before saving.
    if (decryptToken($enc) !== $raw) {
        echo "[ERROR] #{$row['id']} {$row['name']} â€” round-trip verification failed, not saving\n";
        $errors++;
        continue;
    }

    $stmt = $mysqli->prepare('UPDATE businesses SET access_token = ?, updated_at = NOW() WHERE id = ?');
    $id = (int)$row['id'];
    $stmt->bind_param('si', $enc, $id);
    if ($stmt->execute()) {
        echo "[OK]   #{$row['id']} {$row['name']} â€” encrypted\n";
        $migrated++;
    } else {
        echo "[ERROR] #{$row['id']} {$row['name']} â€” update failed: {$stmt->error}\n";
        $errors++;
    }
    $stmt->close();
}

echo "Done: {$migrated} encrypted, {$skipped} skipped, {$errors} errors.\n";
exit($errors > 0 ? 1 : 0);
