<?php

require __DIR__ . '/../_includes/functions.inc.php';

$checks = [
    'broadcast_campaigns'   => "SHOW TABLES LIKE 'broadcast_campaigns'",
    'customers'             => "SHOW TABLES LIKE 'customers'",
    'conversations'         => "SHOW TABLES LIKE 'conversations'",
    'business_messages'     => "SHOW TABLES LIKE 'business_messages'",
    'messages.customer_id'  => "SHOW COLUMNS FROM business_messages LIKE 'customer_id'",
];

foreach ($checks as $label => $sql) {
    $r = $mysqli->query($sql);
    echo $label . ': ' . ($r && mysqli_num_rows($r) ? 'EXISTS' : 'MISSING') . PHP_EOL;
}

$r = $mysqli->query('SELECT COUNT(*) AS c FROM businesses');
$row = mysqli_fetch_assoc($r);
echo 'businesses count: ' . $row['c'] . PHP_EOL;
