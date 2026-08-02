<?php

// includes/webhook_security.php
// Verify Meta webhook signature using the request body and X-Hub-Signature-256 header.

function getRequestSignatureHeader(): ?string
{
    foreach (getallheaders() as $name => $value) {
        if (strcasecmp($name, 'X-Hub-Signature-256') === 0) {
            return (string)$value;
        }
    }

    return null;
}

function verifyWebhookSignature(string $rawBody, ?string $signatureHeader): bool
{
    $secret = env('META_APP_SECRET', '');
    if ($secret === '') {
        return false;
    }

    if ($signatureHeader === null || !preg_match('/^sha256=([a-fA-F0-9]{64})$/', $signatureHeader, $matches)) {
        return false;
    }

    $computed = 'sha256=' . hash_hmac('sha256', $rawBody, $secret);
    return hash_equals($computed, $signatureHeader);
}
