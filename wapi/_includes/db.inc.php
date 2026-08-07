<?php

// _includes/db.inc.php
// Single shared mysqli connection. Sets the global $mysqli used by every
// *_functions.inc.php module. Dies with a message (not the raw error) on failure.

require_once __DIR__ . '/config.inc.php';

$config = require __DIR__ . '/config.inc.php';

$mysqli = @new mysqli(
    $config['db_host'],
    $config['db_user'],
    $config['db_pass'],
    $config['db_name']
);

if ($mysqli->connect_errno) {
    writeLog('errors.log', 'Database connection failed: ' . $mysqli->connect_errno);
    die('Database Connection Failed.');
}

$mysqli->set_charset('utf8mb4');
