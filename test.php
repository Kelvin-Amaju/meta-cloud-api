<?php

// test.php

require_once __DIR__ . '/includes/init.php';

$config = require __DIR__ . '/config/config.php';

// Diagnostic state variables
$apiVersion    = $config['api_version'] ?? 'v18.0';
$phoneNumberId = $config['phone_number_id'] ?? '';
$accessToken   = $config['access_token'] ?? '';

$configError = null;
$error       = null;
$status      = null;
$data        = [];

// Guard against missing config credentials
if (empty($phoneNumberId) || empty($accessToken)) {
    $configError = "Missing configuration values. Ensure 'phone_number_id' and 'access_token' are defined in your environment / config file.";
} else {
    $url = "https://graph.facebook.com/{$apiVersion}/{$phoneNumberId}";

    $ch = curl_init($url);

    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HTTPHEADER     => [
            "Authorization: Bearer {$accessToken}",
            "Content-Type: application/json"
        ],
        CURLOPT_TIMEOUT        => 10,
        CURLOPT_CONNECTTIMEOUT => 5
    ]);

    $response = curl_exec($ch);
    $status   = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $error    = curl_error($ch);
    curl_close($ch);

    if ($response !== false) {
        $data = json_decode($response, true) ?? [];
    }
}

// Function to map Quality Rating to Bootstrap color badges
function getQualityBadge(string $rating): string
{
    switch (strtoupper($rating)) {
        case 'GREEN':
            return '<span class="badge bg-success"><i class="bi bi-shield-check me-1"></i> High (Green)</span>';
        case 'YELLOW':
            return '<span class="badge bg-warning text-dark"><i class="bi bi-shield-exclamation me-1"></i> Medium (Yellow)</span>';
        case 'RED':
            return '<span class="badge bg-danger"><i class="bi bi-shield-x me-1"></i> Low (Red)</span>';
        default:
            return '<span class="badge bg-secondary"><i class="bi bi-question-circle me-1"></i> ' . htmlspecialchars($rating ?: 'UNKNOWN') . '</span>';
    }
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Meta Cloud API Diagnostics - Netgrity</title>
    <!-- Bootstrap 5 CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Bootstrap Icons -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.5/font/bootstrap-icons.css" rel="stylesheet">
    <link href="assets/css/app.css" rel="stylesheet">
</head>

<body class="bg-light min-vh-100 d-flex flex-column">

    <!-- Top Navbar -->
    <?php $activeNav = 'home';
    require __DIR__ . '/includes/partials/navbar.php'; ?>

    <div class="container my-auto py-4" style="max-width: 760px;">

        <div class="card border-0 shadow-sm">
            <!-- Header -->
            <div class="card-header bg-white py-3 border-0 d-flex align-items-center justify-content-between">
                <h4 class="card-title fw-bold mb-0 text-dark d-flex align-items-center gap-2">
                    <i class="bi bi-cpu text-primary"></i> Meta Cloud API Diagnostics
                </h4>
                <a href="test" class="btn btn-sm btn-outline-secondary">
                    <i class="bi bi-arrow-clockwise me-1"></i> Re-test
                </a>
            </div>

            <div class="card-body p-4">

                <!-- Case 1: Configuration Error -->
                <?php if ($configError): ?>
                    <div class="alert alert-warning border-0 shadow-sm d-flex align-items-start gap-3">
                        <i class="bi bi-exclamation-triangle-fill fs-3 text-warning"></i>
                        <div>
                            <h5 class="alert-heading fw-bold">Configuration Missing</h5>
                            <p class="mb-0"><?= htmlspecialchars($configError) ?></p>
                        </div>
                    </div>

                <!-- Case 2: Network / cURL Connection Error -->
                <?php elseif ($error): ?>
                    <div class="alert alert-danger border-0 shadow-sm d-flex align-items-start gap-3">
                        <i class="bi bi-wifi-off fs-3 text-danger"></i>
                        <div>
                            <h5 class="alert-heading fw-bold">Network Connection Error</h5>
                            <p class="mb-0">Failed to establish connection with Meta Graph API.</p>
                            <div class="font-monospace text-dark bg-white p-2 rounded mt-2 border">
                                <?= htmlspecialchars($error) ?>
                            </div>
                        </div>
                    </div>

                <!-- Case 3: API Connection Successful (200 OK) -->
                <?php elseif ($status >= 200 && $status < 300): ?>
                    <div class="alert alert-success border-0 shadow-sm d-flex align-items-center gap-3 mb-4">
                        <i class="bi bi-check-circle-fill fs-3 text-success"></i>
                        <div>
                            <h5 class="alert-heading fw-bold mb-0">Connection Successful</h5>
                            <small>Meta Graph API responded with HTTP <strong><?= $status ?> OK</strong></small>
                        </div>
                    </div>

                    <!-- API Metadata Display Table -->
                    <div class="table-responsive">
                        <table class="table table-hover align-middle border rounded">
                            <tbody class="table-group-divider">
                                <tr>
                                    <th class="bg-light text-secondary w-35" scope="row">
                                        <i class="bi bi-hash me-1"></i> Phone Number ID
                                    </th>
                                    <td class="font-monospace fw-semibold"><?= htmlspecialchars($data['id'] ?? 'N/A') ?></td>
                                </tr>
                                <tr>
                                    <th class="bg-light text-secondary" scope="row">
                                        <i class="bi bi-patch-check-fill me-1 text-primary"></i> Verified Name
                                    </th>
                                    <td class="fw-bold"><?= htmlspecialchars($data['verified_name'] ?? 'Unverified / Sandbox') ?></td>
                                </tr>
                                <tr>
                                    <th class="bg-light text-secondary" scope="row">
                                        <i class="bi bi-telephone me-1"></i> Display Number
                                    </th>
                                    <td><?= htmlspecialchars($data['display_phone_number'] ?? 'N/A') ?></td>
                                </tr>
                                <tr>
                                    <th class="bg-light text-secondary" scope="row">
                                        <i class="bi bi-shield-check me-1"></i> Quality Rating
                                    </th>
                                    <td><?= getQualityBadge($data['quality_rating'] ?? '') ?></td>
                                </tr>
                                <tr>
                                    <th class="bg-light text-secondary" scope="row">
                                        <i class="bi bi-code-inline me-1"></i> Graph API Version
                                    </th>
                                    <td><span class="badge bg-light text-dark border"><?= htmlspecialchars($apiVersion) ?></span></td>
                                </tr>
                            </tbody>
                        </table>
                    </div>

                <!-- Case 4: Meta Graph API Error Response (e.g. 401, 400, 403) -->
                <?php else: ?>
                    <?php 
                        $metaErrorMsg  = $data['error']['message'] ?? 'Unknown Meta API error.';
                        $metaErrorCode = $data['error']['code'] ?? $status;
                        $metaErrorType = $data['error']['type'] ?? 'OAuthException';
                    ?>
                    <div class="alert alert-danger border-0 shadow-sm d-flex align-items-start gap-3 mb-4">
                        <i class="bi bi-exclamation-octagon-fill fs-3 text-danger"></i>
                        <div>
                            <h5 class="alert-heading fw-bold">Meta API Request Failed (HTTP <?= htmlspecialchars((string)$status) ?>)</h5>
                            <p class="mb-1"><strong>Code <?= htmlspecialchars((string)$metaErrorCode) ?>:</strong> <?= htmlspecialchars($metaErrorMsg) ?></p>
                            <small class="text-muted">Type: <?= htmlspecialchars($metaErrorType) ?></small>
                        </div>
                    </div>

                    <!-- Collapsible JSON Payload for Raw Debugging -->
                    <div class="mb-4">
                        <button class="btn btn-sm btn-outline-secondary" type="button" data-bs-toggle="collapse" data-bs-target="#debugJson">
                            <i class="bi bi-code-slash me-1"></i> Inspect Raw API Error Payload
                        </button>
                        <div class="collapse mt-2" id="debugJson">
                            <pre class="bg-dark text-light p-3 rounded fs-7 mb-0"><code><?= htmlspecialchars(json_encode($data, JSON_PRETTY_PRINT)) ?></code></pre>
                        </div>
                    </div>
                <?php endif; ?>

                <!-- Action Footer Buttons -->
                <div class="d-flex align-items-center justify-content-between pt-3 border-top">
                    <a href="index" class="btn btn-light border text-muted">
                        <i class="bi bi-arrow-left me-1"></i> Dashboard
                    </a>
                    <a href="send" class="btn btn-ng-secondary fw-semibold">
                        <i class="bi bi-paperplane-fill me-1"></i> Send Test Message
                    </a>
                </div>

            </div>
        </div>

    </div>

    <!-- Bootstrap 5 JS Bundle -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/js/bootstrap.bundle.min.js"></script>
</body>

</html>