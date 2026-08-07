<?php

// _includes/functions.inc.php
// Front-door bootstrap for main/ UI pages and ajax/ endpoints.
//
// NO authentication — this module is intentionally unauthenticated.
// Sessions are used ONLY for a lightweight CSRF token + flash messages.

// 1) Output buffering
ob_start();

// 2) Runtime settings
ini_set('display_errors', '0');
date_default_timezone_set('Africa/Lagos');
ini_set('max_execution_time', '900');

// 3) Session (skipped under CLI so bin/*.php can use this bootstrap)
if (PHP_SAPI !== 'cli' && session_status() === PHP_SESSION_NONE) {
    session_start();
}

// 4) Config, DB, helpers
require_once __DIR__ . '/config.inc.php';
require_once __DIR__ . '/db.inc.php';
require_once __DIR__ . '/sanitize_functions.inc.php';
require_once __DIR__ . '/logger_functions.inc.php';

// 5) CSRF helpers (session-backed; only meaningful for web requests)
function csrf_token(): string
{
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

function verify_csrf(?string $token): bool
{
    return is_string($token)
        && !empty($_SESSION['csrf_token'])
        && hash_equals($_SESSION['csrf_token'], $token);
}
