<?php

// business/view.php — View Business Profile & Configuration Details

require_once __DIR__ . '/../includes/init.php';
require_once __DIR__ . '/../includes/messages.php';

$businessId = (int)($_GET['id'] ?? 0);
$business   = $businessId > 0 ? getBusinessById($businessId) : null;

if (!$business) {
    header("Location: ./");
    exit;
}

// Fetch stats for this business
$messagesResult = getMessages(['business_id' => $business['id']], 1, 5);
$totalSent      = $messagesResult['total'];

$lineClass   = 'badge-' . strtolower($business['product_line'] ?? 'other');
$statusClass = 'badge-status-' . strtolower($business['status'] ?? 'pending');
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= htmlspecialchars($business['name']) ?> - Business Details</title>

    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css" rel="stylesheet">
    <link href="../assets/css/app.css" rel="stylesheet">
    <style>
        .badge-hotel {
            background-color: #ffc107;
            color: #000;
        }

        .badge-school {
            background-color: #0dcaf0;
            color: #000;
        }

        .badge-hospital {
            background-color: #dc3545;
            color: #fff;
        }

        .badge-erp {
            background-color: #0d6efd;
            color: #fff;
        }

        .badge-crm {
            background-color: #198754;
            color: #fff;
        }

        .badge-other {
            background-color: #6c757d;
            color: #fff;
        }

        .badge-status-active {
            background-color: #198754;
            color: #fff;
        }

        .badge-status-pending {
            background-color: #ffc107;
            color: #000;
        }

        .badge-status-suspended {
            background-color: #fd7e14;
            color: #fff;
        }

        .badge-status-revoked {
            background-color: #dc3545;
            color: #fff;
        }
    </style>
</head>

<body class="bg-light min-vh-100 d-flex flex-column py-4">

    <!-- Top Navbar -->
    <?php $navBase = '../';
    $activeNav = 'business';
    require __DIR__ . '/../includes/partials/navbar.php'; ?>

    <div class="container my-auto" style="max-width: 960px;">

        <!-- Header Toolbar -->
        <div class="d-flex align-items-center justify-content-between flex-wrap gap-3 mb-4">
            <div>
                <a href="index" class="btn btn-link p-0 text-decoration-none text-muted mb-1">
                    <i class="bi bi-arrow-left"></i> Back to Business Directory
                </a>
                <div class="d-flex align-items-center gap-3">
                    <h2 class="fw-bold mb-0 text-dark"><?= htmlspecialchars($business['name']) ?></h2>
                    <span class="badge <?= $statusClass ?> px-3 py-2 text-uppercase fs-6"><?= htmlspecialchars($business['status']) ?></span>
                    <span class="badge <?= $lineClass ?> px-3 py-2 text-uppercase fs-6"><?= htmlspecialchars($business['product_line']) ?></span>
                </div>
                <p class="text-muted small font-monospace mb-0 mt-1">UUID: <?= htmlspecialchars($business['uuid']) ?></p>
            </div>

            <div class="d-flex align-items-center gap-2">
                <a href="edit?id=<?= $business['id'] ?>" class="btn btn-outline-primary shadow-sm">
                    <i class="bi bi-pencil me-1"></i> Edit Profile
                </a>
                <a href="../send?business_id=<?= $business['id'] ?>" class="btn btn-ng-secondary shadow-sm">
                    <i class="bi bi-paperplane-fill me-1"></i> Send WhatsApp Message
                </a>
            </div>
        </div>

        <div class="row g-4">

            <!-- Left Column: Business Configuration details -->
            <div class="col-lg-8">

                <!-- Meta WhatsApp Config Card -->
                <div class="card border-0 shadow-sm mb-4">
                    <div class="card-header bg-white border-bottom py-3">
                        <h5 class="fw-bold mb-0 text-dark">
                            <i class="bi bi-whatsapp text-success me-2"></i>WhatsApp API Configuration
                        </h5>
                    </div>
                    <div class="card-body p-4">
                        <div class="row g-4">

                            <div class="col-md-6">
                                <label class="text-muted small fw-semibold text-uppercase d-block mb-1">Phone Number ID</label>
                                <span class="fs-6 font-monospace fw-bold text-dark d-block"><?= htmlspecialchars($business['phone_number_id']) ?></span>
                            </div>

                            <div class="col-md-6">
                                <label class="text-muted small fw-semibold text-uppercase d-block mb-1">Display Phone Number</label>
                                <span class="fs-6 fw-bold text-dark d-block"><?= htmlspecialchars($business['display_phone_number'] ?: 'Not Provided') ?></span>
                            </div>

                            <div class="col-md-6">
                                <label class="text-muted small fw-semibold text-uppercase d-block mb-1">WABA Account ID</label>
                                <span class="fs-6 font-monospace text-dark d-block"><?= htmlspecialchars($business['waba_id'] ?: '—') ?></span>
                            </div>

                            <div class="col-md-6">
                                <label class="text-muted small fw-semibold text-uppercase d-block mb-1">Meta Business Manager ID</label>
                                <span class="fs-6 font-monospace text-dark d-block"><?= htmlspecialchars($business['meta_business_id'] ?: '—') ?></span>
                            </div>

                        </div>
                    </div>
                </div>

                <!-- API Credentials Card -->
                <div class="card border-0 shadow-sm mb-4">
                    <div class="card-header bg-white border-bottom py-3">
                        <h5 class="fw-bold mb-0 text-dark">
                            <i class="bi bi-key-fill text-warning me-2"></i>API Access Token & Authorization
                        </h5>
                    </div>
                    <div class="card-body p-4">
                        <div class="mb-3">
                            <label class="text-muted small fw-semibold text-uppercase d-block mb-1">Token Classification</label>
                            <span class="badge bg-secondary px-3 py-2 text-uppercase"><?= htmlspecialchars(str_replace('_', ' ', $business['token_type'])) ?></span>
                        </div>

                        <div>
                            <label class="text-muted small fw-semibold text-uppercase d-block mb-1">Meta Access Token</label>
                            <div class="input-group">
                                <input
                                    type="password"
                                    class="form-control font-monospace"
                                    id="tokenValue"
                                    value="<?= htmlspecialchars($business['access_token']) ?>"
                                    readonly>
                                <button class="btn btn-outline-secondary" type="button" id="toggleToken">
                                    <i class="bi bi-eye-fill me-1"></i> Show Token
                                </button>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Recent Messages for this Business -->
                <div class="card border-0 shadow-sm">
                    <div class="card-header bg-white border-bottom py-3 d-flex align-items-center justify-content-between">
                        <h5 class="fw-bold mb-0 text-dark">
                            <i class="bi bi-chat-left-text text-primary me-2"></i>Recent Outbound Activity
                        </h5>
                        <a href="../messages?search=<?= urlencode($business['name']) ?>" class="btn btn-sm btn-outline-secondary">View All Logs</a>
                    </div>
                    <div class="card-body p-0">
                        <?php if (empty($messagesResult['data'])): ?>
                            <div class="text-center py-4 text-muted">
                                <i class="bi bi-inbox fs-3 d-block mb-1"></i>
                                No messages sent from this business account yet.
                            </div>
                        <?php else: ?>
                            <div class="table-responsive">
                                <table class="table table-hover align-middle mb-0">
                                    <thead class="table-light">
                                        <tr>
                                            <th>Recipient</th>
                                            <th>Message Body</th>
                                            <th>Status</th>
                                            <th>Date</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($messagesResult['data'] as $m): ?>
                                            <tr>
                                                <td class="fw-semibold">+<?= htmlspecialchars($m['to_number']) ?></td>
                                                <td class="text-truncate text-muted" style="max-width: 250px;"><?= htmlspecialchars($m['body']) ?></td>
                                                <td><span class="badge bg-success-subtle text-success border border-success-subtle px-2 py-1"><?= htmlspecialchars($m['status']) ?></span></td>
                                                <td class="small text-muted"><?= htmlspecialchars($m['created_at']) ?></td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>

            </div>

            <!-- Right Column: Sidebar Stats -->
            <div class="col-lg-4">

                <div class="card border-0 shadow-sm mb-4">
                    <div class="card-header bg-white border-bottom py-3">
                        <h5 class="fw-bold mb-0 text-dark">
                            <i class="bi bi-activity text-info me-2"></i>Account Metadata
                        </h5>
                    </div>
                    <div class="card-body p-4">

                        <div class="d-flex align-items-center justify-content-between py-2 border-bottom">
                            <span class="text-muted">Total Sent</span>
                            <span class="fw-bold text-dark fs-5"><?= number_format($totalSent) ?></span>
                        </div>

                        <div class="d-flex align-items-center justify-content-between py-2 border-bottom">
                            <span class="text-muted">Onboarding Mode</span>
                            <span class="badge bg-light text-dark border"><?= htmlspecialchars(ucfirst(str_replace('_', ' ', $business['onboarding_method']))) ?></span>
                        </div>

                        <div class="d-flex align-items-center justify-content-between py-2 border-bottom">
                            <span class="text-muted">Created Date</span>
                            <span class="small font-monospace text-dark"><?= htmlspecialchars(date('M d, Y H:i', strtotime($business['created_at']))) ?></span>
                        </div>

                        <div class="d-flex align-items-center justify-content-between py-2">
                            <span class="text-muted">Last Updated</span>
                            <span class="small font-monospace text-dark"><?= htmlspecialchars(date('M d, Y H:i', strtotime($business['updated_at']))) ?></span>
                        </div>

                    </div>
                </div>

            </div>

        </div>

    </div>

    <!-- Footer -->
    <footer class="mt-auto py-3 bg-white border-top text-center text-muted small">
        <div class="container">
            Netgrity WhatsApp Cloud API &bull; Multi-Tenant Business Profile
        </div>
    </footer>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/js/bootstrap.bundle.min.js"></script>

    <script>
        document.getElementById('toggleToken').addEventListener('click', function() {
            const input = document.getElementById('tokenValue');
            if (input.type === 'password') {
                input.type = 'text';
                this.innerHTML = '<i class="bi bi-eye-slash-fill me-1"></i> Hide Token';
            } else {
                input.type = 'password';
                this.innerHTML = '<i class="bi bi-eye-fill me-1"></i> Show Token';
            }
        });
    </script>

</body>

</html>