<?php

// _includes/functions2.inc.php
// Lightweight bootstrap for PUBLIC endpoints: webhook.php, callback.php,
// business_signup_callback.php. No sessions, no CSRF — Meta never gets one.

ini_set('display_errors', '0');
date_default_timezone_set('Africa/Lagos');

require_once __DIR__ . '/config.inc.php';
require_once __DIR__ . '/db.inc.php';
require_once __DIR__ . '/sanitize_functions.inc.php';
require_once __DIR__ . '/logger_functions.inc.php';
