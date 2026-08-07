<?php

// _includes/config.inc.php
// Runtime configuration — CREDENTIALS ARE HARDCODED (no .env loader).
// Single source of truth; never hard-code a DB name or credential inside a page.

return [
    // ── Application ─────────────────────────────────────────────
    'app_name'        => 'Netgrity WhatsApp API',
    'app_env'         => 'development',
    'app_debug'       => true,
    'app_url'         => 'http://localhost/netgrity/app/whatsapp-api/',
    'callback_url'    => 'http://localhost/netgrity/app/whatsapp-api/callback.php',

    // ── Security ────────────────────────────────────────────────
    // AES-256-GCM key (base64 32-byte). Do not rotate casually — it invalidates
    // every encrypted access token already stored in `businesses.access_token`.
    'encryption_key'  => 'WuYXX2Oe/bjTG1V0phtKyTtfWCFGGGsGBu6rWWzlYmg=',

    // ── Database ───────────────────────────────────────────────
    'db_host'         => 'localhost',
    'db_port'         => '3306',
    'db_user'         => 'root',
    'db_pass'         => '',
    'db_name'         => 'netgrity_wa',

    // ── Meta / WhatsApp Cloud API ──────────────────────────────
    'api_version'     => 'v25.0',
    'verify_token'    => '80466147',
    'access_token'    => 'EAAWrZAQ5m0XABSGPfvziTZAYm8V96BdT6GMXtcGRgtAzYCvIb57zul7w2M1i2KDhyq9NTeR6Q8jbOiJePDZBz1HVbCBqkgGbLEtTCt5jcbbIjZAFjPb8IDymfUA9HeZBUzjqkUU9L5EqFrU9Ecv5t0LXYKdEJS1g1eLXvJz661akaQgOyIBYeTUuHmZB2ZAxnPfuwSln4Hu4bz6m2ZA5v1dQgDFxk7cb2JX3ATZBho064ijHOGdVozTcVhglH2yc8c5z2BRD6k00lJ1OqqSzOMmLjSnIZC',
    'phone_number_id' => '1270265856164756',
    'meta_app_id'     => '1711462023424933',
    'meta_app_secret' => 'a1f45aa6bd7068fb4118350509544a56',
];
