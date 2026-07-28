<?php
// mock_meta.php
header('Content-Type: application/json');

// Simulate successful Phone Number ID query
echo json_encode([
    'id' => '100609346354231',
    'verified_name' => 'Netgrity Test Sandbox',
    'display_phone_number' => '+234 703 303 4783',
    'quality_rating' => 'GREEN'
]);