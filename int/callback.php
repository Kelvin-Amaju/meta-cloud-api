<?php

// Meta App credentials
$app_id = "YOUR_META_APP_ID";
$app_secret = "YOUR_META_APP_SECRET";

$redirect_uri = "https://yourdomain.com/callback.php";

// Database connection (example)
$db = new PDO(
    "mysql:host=localhost;dbname=crm",
    "username",
    "password"
);

// 1. Get parameters returned by Meta
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
    ":token" => $access_token
]);


// 6. Redirect user back to CRM

header(
    "Location: https://yourdomain.com/settings/whatsapp?connected=1"
);

exit;
