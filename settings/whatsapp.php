<?php

require_once __DIR__ . '/../includes/init.php';
require_once __DIR__ . '/../includes/crypto.php';

$config    = require __DIR__ . '/../config/config.php';
$businesses = getAllBusinesses(['status' => 'all']);

$tokenMap = [];
$r = $mysqli->query('SELECT id, access_token, onboarding_method FROM businesses');
while ($row = $r->fetch_assoc()) {
    $tokenMap[(int)$row['id']] = $row;
}

$baseUrl     = rtrim($config['app_url'] ?? 'http://localhost', '/');
$signupRedirect = $baseUrl . '/business_signup_callback.php';

$encryptionConfigured = getEncryptionKey() !== '';

$activeNav = 'settings';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Settings — Netgrity WhatsApp API</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css" rel="stylesheet">
    <link href="../assets/css/app.css" rel="stylesheet">
</head>
<body class="bg-light min-vh-100 d-flex flex-column">

    <?php require __DIR__ . '/../includes/partials/navbar.php'; ?>

    <div class="mt-5 container py-2 flex-grow-1" style="max-width:1200px;">

        <div class="d-flex align-items-center justify-content-between mb-4 flex-wrap gap-2">
            <h4 class="fw-bold mb-0 ng-title">
                <i class="bi bi-gear text-primary"></i> WhatsApp Settings
            </h4>
            <div class="d-flex gap-2">
                <a href="../business/add" class="btn btn-ng-primary btn-sm fw-semibold">
                    <i class="bi bi-plus-lg me-1"></i> Add Business (manual)
                </a>
                <a href="../test" class="btn btn-ng-outline btn-sm">
                    <i class="bi bi-cpu me-1"></i> Diagnostics
                </a>
            </div>
        </div>

        <?php if (isset($_GET['connected']) && $_GET['connected'] === '1'): ?>
            <div class="alert alert-success border-0 shadow-sm d-flex align-items-center gap-2">
                <i class="bi bi-check-circle-fill"></i>
                Business #<?= (int)($_GET['business'] ?? 0) ?> connected to Meta successfully.
            </div>
        <?php endif; ?>

        <?php if (!$encryptionConfigured): ?>
            <div class="alert alert-warning border-0 shadow-sm">
                <strong>Encryption is not configured.</strong> Set a base64-encoded 32-byte
                <code>APP_ENCRYPTION_KEY</code> in <code>.env</code> before saving any access token —
                otherwise business saves are rejected (fail-closed).
            </div>
        <?php endif; ?>

        <div class="alert alert-info border-0 shadow-sm">
            <i class="bi bi-plug me-1"></i>
            <strong>Embedded Signup:</strong> click <i class="bi bi-link-45deg"></i> <em>Connect via Meta</em>
            on a business row to launch Meta's onboarding popup. When it completes, the token is saved
            automatically to that business (state = business id) and the row becomes <code>active</code>.
        </div>

        <div class="card card-ng border-0 shadow-sm">
            <div class="card-header bg-white py-3 border-0 d-flex align-items-center justify-content-between">
                <h5 class="fw-bold mb-0 text-dark">Connected Businesses</h5>
                <span class="badge text-bg-dark"><?= count($businesses) ?> total</span>
            </div>
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead class="table-light">
                        <tr>
                            <th>Business</th>
                            <th>WABA ID</th>
                            <th>Phone Number ID</th>
                            <th>Token</th>
                            <th>Onboarding</th>
                            <th>Status</th>
                            <th class="text-end">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($businesses as $biz):
                            $meta    = $tokenMap[(int)$biz['id']] ?? [];
                            $rawTok  = $meta['access_token'] ?? '';
                            $tokIcon = $rawTok === '' ? 'bi-x-circle text-danger' : (str_starts_with($rawTok, 'enc:v1:') ? 'bi-shield-lock-fill text-success' : 'bi-shield-exclamation text-warning');
                            $tokTip  = $rawTok === '' ? 'No token stored' : (str_starts_with($rawTok, 'enc:v1:') ? 'Encrypted (AES-256-GCM)' : 'Stored as plaintext — set APP_ENCRYPTION_KEY');
                            $signupQuery = [
                                'redirect_uri' => $signupRedirect,
                                'state'        => (int)$biz['id'],
                                'permissions'  => 'whatsapp_business_messaging,whatsapp_business_management',
                                't'            => time(),
                            ];
                            if (!empty($biz['meta_business_id'])) {
                                $signupQuery = ['business_id' => $biz['meta_business_id']] + $signupQuery;
                            }
                            $signupUrl = 'https://business.facebook.com/business/embedded_signup/?' . http_build_query($signupQuery);
                        ?>
                            <tr>
                                <td>
                                    <div class="fw-semibold text-dark"><?= htmlspecialchars($biz['name']) ?></div>
                                    <div class="small text-muted"><?= htmlspecialchars($biz['display_phone_number'] ?? '') ?></div>
                                </td>
                                <td class="text-muted"><?= htmlspecialchars($biz['waba_id'] ?? '') ?: '—' ?></td>
                                <td class="text-muted"><?= htmlspecialchars($biz['phone_number_id'] ?? '') ?: '—' ?></td>
                                <td><i class="bi <?= $tokIcon ?> fs-5" title="<?= $tokTip ?>"></i></td>
                                <td><span class="badge text-bg-secondary"><?= htmlspecialchars($meta['onboarding_method'] ?? 'manual') ?></span></td>
                                <td>
                                    <span class="badge <?= $biz['status'] === 'active' ? 'text-bg-success' : 'text-bg-secondary' ?>">
                                        <?= htmlspecialchars($biz['status']) ?>
                                    </span>
                                </td>
                                <td class="text-end">
                                    <button type="button" class="btn btn-ng-secondary btn-sm fw-semibold"
                                            onclick="window.open(<?= htmlspecialchars(json_encode($signupUrl), ENT_QUOTES) ?>, 'meta_signup', 'width=680,height=780')">
                                        <i class="bi bi-link-45deg me-1"></i>Connect via Meta
                                    </button>
                                    <a href="../business/edit?id=<?= (int)$biz['id'] ?>" class="btn btn-ng-outline btn-sm">
                                        <i class="bi bi-pencil"></i>
                                    </a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                        <?php if (count($businesses) === 0): ?>
                            <tr>
                                <td colspan="7" class="text-center text-muted py-4">
                                    No businesses yet — <a href="../business/add" class="fw-semibold">add your first business</a>.
                                </td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <div class="card card-ng border-0 shadow-sm mt-4">
            <div class="card-header bg-white py-3 border-0">
                <h6 class="fw-bold mb-0 text-dark">Environment</h6>
            </div>
            <div class="card-body">
                <dl class="row mb-0 small">
                    <dt class="col-sm-3">API version</dt><dd class="col-sm-9"><?= htmlspecialchars($config['api_version']) ?></dd>
                    <dt class="col-sm-3">Callback URL (classic OAuth)</dt><dd class="col-sm-9"><code><?= htmlspecialchars($baseUrl . '/callback.php') ?></code></dd>
                    <dt class="col-sm-3">Embedded Signup callback</dt><dd class="col-sm-9"><code><?= htmlspecialchars($signupRedirect) ?></code></dd>
                    <dt class="col-sm-3">Webhook</dt><dd class="col-sm-9"><code><?= htmlspecialchars($baseUrl . '/webhook') ?></code></dd>
                </dl>
            </div>
        </div>

    </div>
</body>
</html>
