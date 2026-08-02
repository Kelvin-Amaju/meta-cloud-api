<?php

// config/config.php

require_once __DIR__ . '/../includes/env.php';

loadEnv(__DIR__ . '/../.env');

return [

    // ── Meta / WhatsApp Cloud API ──────────────────────────────
    'api_version'     => env('META_API_VERSION', 'v25.0'),
    'verify_token'    => env('META_VERIFY_TOKEN', ''),
    'access_token'    => env('META_ACCESS_TOKEN', ''),   // Permanent System User token
    'phone_number_id' => env('META_PHONE_NUMBER_ID', ''),
    'meta_app_id'     => env('META_APP_ID', ''),
    'meta_app_secret' => env('META_APP_SECRET', ''),

    // ── Database ───────────────────────────────────────────────
    'db_host'         => env('DB_HOST', 'localhost'),
    'db_port'         => env('DB_PORT', '3306'),
    'db_user'         => env('DB_USER', 'root'),
    'db_pass'         => env('DB_PASS', ''),
    'db_name'         => env('DB_NAME', 'netgrity_wa'),

    // ── Application ────────────────────────────────────────────
    'app_name'        => env('APP_NAME',  'Netgrity WhatsApp API'),
    'app_env'         => env('APP_ENV',   'development'),
    'app_debug'       => env('APP_DEBUG',  true),
    'app_url'         => env('APP_URL',   'http://localhost'),
    'callback_url'    => env('CALLBACK_URL', 'http://localhost/callback.php')

];
