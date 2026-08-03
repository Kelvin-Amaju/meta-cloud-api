<?php

require __DIR__ . '/../includes/env.php';
require_once __DIR__ . '/../includes/crypto.php';

$pass = 0;
$fail = 0;

function check(string $label, bool $cond): void
{
    global $pass, $fail;
    echo ($cond ? '[PASS] ' : '[FAIL] ') . $label . PHP_EOL;
    $cond ? $pass++ : $fail++;
}

// 1. Key missing → encryptToken must throw, decryptToken must return null (not ciphertext)
putenv('APP_ENCRYPTION_KEY');
$threw = false;
try {
    encryptToken('EAAsecret');
} catch (RuntimeException $e) {
    $threw = true;
}
check('encryptToken throws when key missing', $threw);

$fakeEnc = 'enc:v1:' . base64_encode(json_encode(['iv' => base64_encode('1234567890123456'), 'tag' => base64_encode('tagtagtagtag'), 'ct' => base64_encode('ciphertext')]));
check('decryptToken returns null (not ciphertext) when key missing', decryptToken($fakeEnc) === null);

// 2. With a valid key → round trip works
$key = base64_encode(str_repeat('K', 32));
putenv('APP_ENCRYPTION_KEY=' . $key);

$enc = encryptToken('EAAsecret123');
check('encryptToken produces enc:v1: with valid key', is_string($enc) && str_starts_with($enc, 'enc:v1:'));
check('decryptToken round-trips plaintext', decryptToken($enc) === 'EAAsecret123');

// 3. Tampered ciphertext → null, not garbage
$tampered = substr_replace($enc, 'X', -1, 1);
check('decryptToken returns null on tampered ciphertext', decryptToken($tampered) === null);

// 4. Legacy plaintext passes through unchanged
check('decryptToken passes legacy plaintext through', decryptToken('EAAplaintext') === 'EAAplaintext');

// 5. Invalid key (wrong length) → encrypt throws
putenv('APP_ENCRYPTION_KEY=' . base64_encode('short'));
$threw = false;
try {
    encryptToken('EAAsecret');
} catch (RuntimeException $e) {
    $threw = true;
}
check('encryptToken throws on invalid key length', $threw);

// cleanup
putenv('APP_ENCRYPTION_KEY');

echo "{$pass} passed, {$fail} failed" . PHP_EOL;
exit($fail > 0 ? 1 : 0);
