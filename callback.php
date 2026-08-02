<?php

$config = require __DIR__ . '/config/config.php';

require_once __DIR__ . '/includes/env.php';
require_once __DIR__ . '/includes/crypto.php';

// Load Meta credentials from the shared app config / .env.
$app_id = env('META_APP_ID', $config['meta_app_id'] ?? '');
$app_secret = env('META_APP_SECRET', $config['meta_app_secret'] ?? '');
$redirect_uri = env('CALLBACK_URL', $config['callback_url'] ?? 'http://localhost/callback.php');

// Database connection from the app config.
try {
    $db = new PDO(
        'mysql:host=' . $config['db_host'] . ';port=' . $config['db_port'] . ';dbname=' . $config['db_name'] . ';charset=utf8mb4',
        $config['db_user'],
        $config['db_pass'],
        [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        ]
    );
} catch (Throwable $e) {
    die('Database connection failed: ' . $e->getMessage());
}

// 1. Get parameters returned by Meta
if (empty($app_id) || empty($app_secret)) {
    die('Missing Meta app credentials. Define META_APP_ID and META_APP_SECRET in your environment/config.');
}

if (!isset($_GET['code'])) {
    die("Authorization code missing");
}

$code = $_GET['code'];

// Used to identify the tenant that started the connection
$state = $_GET['state'] ?? null;

if (!$state) {
    die("Missing state");
}


// 2. Exchange authorization code for access token

$token_url = "https://graph.facebook.com/v23.0/oauth/access_token";

$data = [
    "client_id" => $app_id,
    "client_secret" => $app_secret,
    "redirect_uri" => $redirect_uri,
    "code" => $code
];


$ch = curl_init();

curl_setopt($ch, CURLOPT_URL, $token_url);
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($data));
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);

$response = curl_exec($ch);

curl_close($ch);


$token_response = json_decode($response, true);


if (!isset($token_response['access_token'])) {
    die("Token exchange failed: " . $response);
}


$access_token = $token_response['access_token'];


// 3. Get WhatsApp Business Accounts connected to this token

$url = "https://graph.facebook.com/v23.0/me/businesses";


$ch = curl_init();

curl_setopt($ch, CURLOPT_URL, $url);
curl_setopt($ch, CURLOPT_HTTPHEADER, [
    "Authorization: Bearer " . $access_token
]);

curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);

$response = curl_exec($ch);

curl_close($ch);


$business_data = json_decode($response, true);


// Example extraction
if (!isset($business_data['data'][0])) {
    die("No business account found");
}


$business_id = $business_data['data'][0]['id'];


// 4. Get WhatsApp Business Account details

$url = "https://graph.facebook.com/v23.0/" . $business_id .
    "?fields=id,name";


$ch = curl_init();

curl_setopt($ch, CURLOPT_URL, $url);

curl_setopt($ch, CURLOPT_HTTPHEADER, [
    "Authorization: Bearer " . $access_token
]);

curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);

$response = curl_exec($ch);

curl_close($ch);


$business = json_decode($response, true);


// 5. Save connection for this tenant

$storedAccessToken = encryptToken($access_token);

$stmt = $db->prepare("
    INSERT INTO whatsapp_accounts
    (
        tenant_id,
        waba_id,
        access_token
    )
    VALUES
    (
        :tenant,
        :waba,
        :token
    )
");


$stmt->execute([
    ":tenant" => $state,
    ":waba" => $business_id,
    ":token" => $storedAccessToken
]);


// 6. Redirect user back to CRM

header(
    "Location: https://netgrity.test/settings/whatsapp?connected=1"
);

exit;
