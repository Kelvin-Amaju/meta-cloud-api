<?php

// includes/crypto.php
//
// Safe token encryption helper for stored Meta access tokens.
// Uses AES-256-GCM to keep the app compatible with the current PHP runtime.

function getEncryptionKey(): string
{
    $key = env('APP_ENCRYPTION_KEY', '');

    if ($key === '') {
        error_log('[crypto] APP_ENCRYPTION_KEY is not set in .env — tokens cannot be encrypted/decrypted.');
        return '';
    }

    $decoded = base64_decode($key, true);
    if ($decoded === false || strlen($decoded) !== 32) {
        error_log('[crypto] APP_ENCRYPTION_KEY must be a base64-encoded 32-byte (256-bit) key.');
        return '';
    }

    return $decoded;
}

function isEncryptedToken(?string $value): bool
{
    return is_string($value) && str_starts_with($value, 'enc:v1:');
}

function encryptToken(?string $plaintext): ?string
{
    if ($plaintext === null || $plaintext === '') {
        return $plaintext;
    }

    $key = getEncryptionKey();
    if ($key === '') {
        // Fail closed: never silently store an access token as plaintext.
        throw new RuntimeException(
            'Cannot encrypt token: APP_ENCRYPTION_KEY is missing or invalid in .env. ' .
            'Refusing to store an access token as plaintext.'
        );
    }

    $ivLength = openssl_cipher_iv_length('AES-256-GCM');
    $iv = openssl_random_pseudo_bytes($ivLength);
    $tag = '';

    $cipherText = openssl_encrypt(
        $plaintext,
        'AES-256-GCM',
        $key,
        OPENSSL_RAW_DATA,
        $iv,
        $tag
    );

    if ($cipherText === false) {
        return $plaintext;
    }

    return 'enc:v1:' . base64_encode(
        json_encode([
            'iv' => base64_encode($iv),
            'tag' => base64_encode($tag),
            'ct' => base64_encode($cipherText),
        ], JSON_THROW_ON_ERROR)
    );
}

function decryptToken(?string $stored): ?string
{
    if ($stored === null || $stored === '') {
        return $stored;
    }

    if (!isEncryptedToken($stored)) {
        return $stored;
    }

    $key = getEncryptionKey();
    if ($key === '') {
        error_log('[crypto] Cannot decrypt token: APP_ENCRYPTION_KEY missing or invalid in .env.');
        return null;
    }

    $payload = substr($stored, strlen('enc:v1:'));
    $decodedJson = base64_decode($payload, true);
    if ($decodedJson === false) {
        error_log('[crypto] Invalid token payload (bad base64).');
        return null;
    }

    try {
        $parts = json_decode($decodedJson, true, 512, JSON_THROW_ON_ERROR);
    } catch (JsonException $e) {
        error_log('[crypto] Invalid token payload (bad JSON).');
        return null;
    }

    $iv = base64_decode($parts['iv'] ?? '', true);
    $tag = base64_decode($parts['tag'] ?? '', true);
    $ct = base64_decode($parts['ct'] ?? '', true);

    if ($iv === false || $tag === false || $ct === false) {
        error_log('[crypto] Invalid token payload (bad iv/tag/ct).');
        return null;
    }

    $plainText = openssl_decrypt(
        $ct,
        'AES-256-GCM',
        $key,
        OPENSSL_RAW_DATA,
        $iv,
        $tag
    );

    if ($plainText === false) {
        error_log('[crypto] Failed to decrypt token — payload tampered or encryption key mismatch.');
        return null;
    }

    return $plainText;
}
