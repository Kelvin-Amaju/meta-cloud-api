<?php

// business_signup_callback.php
//
// Meta Embedded Signup popup callback. Meta redirects here (with ?code=&state=)
// after the user grants access in the popup. The token is exchanged and saved onto
// the business row (identified by `state` = business id), then the popup closes and
// the opener is bounced back to Settings > WhatsApp.
//
// Launched from: main/settings_whatsapp.php (per-business "Connect via Meta" button)
//
// PUBLIC endpoint — uses the functions2 bootstrap (no sessions / CSRF).

require_once __DIR__ . '/_includes/functions2.inc.php';
require_once __DIR__ . '/_includes/oauth_functions.inc.php';

$baseUrl     = rtrim($config['app_url'] ?? 'http://localhost', '/');
$callbackUrl = $baseUrl . '/business_signup_callback.php';

$code  = $_GET['code'] ?? '';
$state = $_GET['state'] ?? '';

$success = null;
$message = '';
$businessId = 0;

if ($code !== '' && $state !== '') {
    $result     = metaExchangeOauthCode($code, $state, $callbackUrl);
    $success    = (bool)$result['success'];
    $message    = $result['error'] ?? '';
    $businessId = (int)($result['business_id'] ?? 0);
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Meta Connection — Netgrity WhatsApp API</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="assets/css/app.css" rel="stylesheet">
</head>
<body class="bg-light min-vh-100 d-flex align-items-center justify-content-center p-3">
    <div class="card card-ng border-0 shadow-sm" style="max-width: 460px; width: 100%;">
        <div class="card-body p-4 text-center">
            <?php if ($code === '' || $state === ''): ?>
                <div class="mb-3">
                    <i class="bi bi-info-circle fs-1 text-primary"></i>
                </div>
                <h5 class="fw-bold mb-2">No authorization code</h5>
                <p class="text-muted mb-3">
                    This page is the Meta Embedded Signup callback. Start the connection from
                    <a href="main/settings_whatsapp.php" class="fw-semibold">Settings &rarr; WhatsApp</a>.
                </p>
                <a href="main/settings_whatsapp.php" class="btn btn-ng-primary fw-semibold">Go to Settings</a>
            <?php elseif ($success): ?>
                <div class="mb-3">
                    <i class="bi bi-check-circle-fill fs-1" style="color: var(--ng-orange);"></i>
                </div>
                <h5 class="fw-bold mb-2">WhatsApp connected!</h5>
                <p class="text-muted mb-3">
                    Business #<?= (int)$businessId ?> is now linked to Meta. You can close this window.
                </p>
                <a href="main/settings_whatsapp.php?connected=1&business=<?= (int)$businessId ?>" class="btn btn-ng-primary fw-semibold">
                    Back to Settings
                </a>
            <?php else: ?>
                <div class="mb-3">
                    <i class="bi bi-x-octagon-fill fs-1 text-danger"></i>
                </div>
                <h5 class="fw-bold mb-2">Connection failed</h5>
                <p class="text-muted mb-3"><?= htmlspecialchars($message) ?></p>
                <a href="main/settings_whatsapp.php" class="btn btn-ng-primary fw-semibold">Back to Settings</a>
            <?php endif; ?>
        </div>
    </div>

    <?php if ($code !== '' && $state !== '' && $success): ?>
        <script>
            if (window.opener && !window.opener.closed) {
                window.opener.location.href = 'main/settings_whatsapp.php?connected=1&business=<?= (int)$businessId ?>';
                window.close();
            }
        </script>
    <?php endif; ?>
</body>
</html>
