<?php

// index.php

require_once 'includes/businesses.php';
$businessStats = getBusinessSummaryStats();

?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Netgrity WhatsApp API Dashboard</title>
    <!-- Bootstrap 5 CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Bootstrap Icons -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.5/font/bootstrap-icons.css" rel="stylesheet">
    <style>
        :root {
            --ng-orange: #ff6b00;
            --ng-orange-dark: #e05f00;
            --ng-black: #272626ff;
            --ng-card: #111111;
            --ng-card-border: #222222;
            --ng-text: #ffffff;
            --ng-text-muted: #a0a0a0;
            --ng-text-secondary: #888888;
        }

        body {
            background-color: white;
            color: var(--ng-text);
        }

        .ng-navbar {
            background-color: #000000ff;
            border-bottom: 2px solid var(--ng-orange);
        }

        .ng-navbar .navbar-brand {
            color: var(--ng-text) !important;
        }

        .ng-navbar .navbar-brand span {
            color: var(--ng-orange);
        }

        .ng-btn-primary {
            background-color: var(--ng-orange);
            color: var(--ng-black);
            border: none;
        }

        .ng-btn-primary:hover {
            background-color: var(--ng-orange-dark);
            color: var(--ng-black);
        }

        .ng-btn-outline {
            background-color: transparent;
            color: var(--ng-orange);
            border: 2px solid var(--ng-orange);
        }

        .ng-btn-outline:hover {
            background-color: var(--ng-orange);
            color: var(--ng-black);
        }

        .ng-btn-dark {
            background-color: #1a1a1a;
            color: var(--ng-orange);
            border: 1px solid #333333;
        }

        .ng-btn-dark:hover {
            background-color: #252525;
            color: var(--ng-orange);
        }

        .ng-card {
            background-color: var(--ng-card);
            border: 1px solid var(--ng-card-border) !important;
            transition: transform 0.2s ease, box-shadow 0.2s ease;
        }

        .ng-card:hover {
            transform: translateY(-4px);
            box-shadow: 0 8px 24px rgba(255, 107, 0, 0.08) !important;
        }

        .ng-icon-box {
            background-color: rgba(255, 107, 0, 0.15);
            color: var(--ng-orange);
        }

        .ng-title {
            color: var(--ng-orange);
        }

        .ng-text-muted {
            color: var(--ng-text-muted) !important;
        }

        .ng-text-secondary {
            color: var(--ng-text-secondary) !important;
        }

        .ng-list-group .list-group-item {
            background-color: var(--ng-card);
            color: var(--ng-text);
            border-color: var(--ng-card-border);
        }

        .ng-list-group .list-group-item:hover {
            background-color: #1a1a1a;
        }

        .ng-badge {
            background-color: #1a1a1a;
            color: var(--ng-orange);
            border: 1px solid #333333;
        }

        .ng-card-header {
            background-color: var(--ng-card);
            border-bottom: 1px solid var(--ng-card-border);
        }

        .ng-badge-active {
            background-color: var(--ng-orange);
            color: var(--ng-black);
        }
    </style>
</head>

<body>

    <!-- Top Navbar -->
    <nav class="navbar navbar-expand-lg ng-navbar mb-5 shadow-sm">
        <div class="container">
            <a class="navbar-brand d-flex align-items-center gap-2 fw-bold" href="#">
                <i class="bi bi-whatsapp fs-4" style="color: var(--ng-orange);"></i>
                <span>Netgrity</span> WhatsApp API
            </a>

            <a href="messages" class="btn ng-btn-primary px-3 py-2 rounded-pill fw-semibold">
                <i class="bi bi-check-circle-fill me-1"></i> Messages
            </a>

            <span class="badge ng-badge-active px-3 py-2 rounded-pill">
                <i class="bi bi-check-circle-fill me-1"></i> Active
            </span>
        </div>
    </nav>

    <div class="container pb-5">
        <!-- Header Title -->
        <div class="row mb-4">
            <div class="col-12">
                <h2 class="fw-bold ng-title mb-1">Developer Dashboard</h2>
                <p class="ng-text-muted">Manage message dispatches, run connection diagnostics, and view real-time system logs.</p>
            </div>
        </div>

        <?php $bannerCompact = true;
        require __DIR__ . '/includes/partials/messaging_limit_banner.php'; ?>

        <!-- Quick Action Cards -->
        <div class="row g-4 mb-4">
            <!-- Send Message Card -->
            <div class="col-md-4">
                <div class="card h-100 border-0 shadow-sm ng-card">
                    <div class="card-body p-4 d-flex flex-column justify-content-between">
                        <div>
                            <div class="d-flex align-items-center mb-3">
                                <div class="ng-icon-box p-3 rounded-3 me-3">
                                    <i class="bi bi-send-fill fs-3"></i>
                                </div>
                                <div>
                                    <h5 class="card-title fw-bold ng-title mb-0">Send Message</h5>
                                    <small class="ng-text-secondary">Outbound API trigger</small>
                                </div>
                            </div>
                            <p class="card-text ng-text-secondary">Dispatch dynamic text or template notifications directly to WhatsApp clients.</p>
                        </div>
                        <div class="pt-3">
                            <a href="send" class="btn ng-btn-primary w-100 d-flex align-items-center justify-content-center gap-2 fw-semibold">
                                Send Message <i class="bi bi-arrow-right"></i>
                            </a>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Test Connection Card -->
            <div class="col-md-4">
                <div class="card h-100 border-0 shadow-sm ng-card">
                    <div class="card-body p-4 d-flex flex-column justify-content-between">
                        <div>
                            <div class="d-flex align-items-center mb-3">
                                <div class="ng-icon-box p-3 rounded-3 me-3">
                                    <i class="bi bi-cpu-fill fs-3"></i>
                                </div>
                                <div>
                                    <h5 class="card-title fw-bold ng-title mb-0">Test API Connection</h5>
                                    <small class="ng-text-secondary">Meta Graph verification</small>
                                </div>
                            </div>
                            <p class="card-text ng-text-secondary">Validate token validity, account IDs, and Meta Graph API connectivity status.</p>
                        </div>
                        <div class="pt-3">
                            <a href="test" class="btn ng-btn-outline w-100 d-flex align-items-center justify-content-center gap-2 fw-semibold">
                                Run Diagnostics <i class="bi bi-lightning-charge-fill"></i>
                            </a>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Add Business Card -->
            <div class="col-md-4">
                <div class="card h-100 border-0 shadow-sm ng-card">
                    <div class="card-body p-4 d-flex flex-column justify-content-between">
                        <div>
                            <div class="d-flex align-items-center mb-3">
                                <div class="ng-icon-box p-3 rounded-3 me-3">
                                    <i class="bi bi-building-add fs-3"></i>
                                </div>
                                <div>
                                    <h5 class="card-title fw-bold ng-title mb-0">Add Business</h5>
                                    <small class="ng-text-secondary"><?= $businessStats['active'] ?> active tenant<?= $businessStats['active'] === 1 ? '' : 's' ?></small>
                                </div>
                            </div>
                            <p class="card-text ng-text-secondary">Register and configure a new WhatsApp Business profile and phone number credentials.</p>
                        </div>
                        <div class="pt-3">
                            <a href="business" class="btn ng-btn-dark w-100 d-flex align-items-center justify-content-center gap-2 fw-semibold">
                                Business <i class="bi bi-plus-lg"></i>
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- System Logs Section -->
        <div class="card border-0 shadow-sm ng-card">
            <div class="card-header ng-card-header py-3 border-0">
                <h5 class="card-title fw-bold mb-0 ng-title d-flex align-items-center gap-2">
                    <i class="bi bi-journal-code ng-title"></i> Application Logs
                </h5>
            </div>
            <div class="card-body p-0">
                <div class="list-group list-group-flush border-top ng-list-group">
                    <!-- Webhook Log Link -->
                    <a href="storage/logs/webhook.log" target="_blank" class="list-group-item list-group-item-action p-3 d-flex align-items-center justify-content-between">
                        <div class="d-flex align-items-center gap-3">
                            <i class="bi bi-arrow-down-left-square fs-4 ng-title"></i>
                            <div>
                                <h6 class="mb-0 fw-semibold ng-title">Webhook Incoming Logs</h6>
                                <small class="ng-text-secondary">View inbound message payloads and event delivery callbacks</small>
                            </div>
                        </div>
                        <span class="badge ng-badge">
                            <i class="bi bi-box-arrow-up-right me-1"></i> Open Log
                        </span>
                    </a>

                    <!-- Error Log Link -->
                    <a href="storage/logs/errors.log" target="_blank" class="list-group-item list-group-item-action p-3 d-flex align-items-center justify-content-between">
                        <div class="d-flex align-items-center gap-3">
                            <i class="bi bi-exclamation-triangle-fill fs-4 ng-title"></i>
                            <div>
                                <h6 class="mb-0 fw-semibold ng-title">System Error Logs</h6>
                                <small class="ng-text-secondary">Inspect cURL failures, missing env variables, and API errors</small>
                            </div>
                        </div>
                        <span class="badge ng-badge">
                            <i class="bi bi-box-arrow-up-right me-1"></i> Open Log
                        </span>
                    </a>
                </div>
            </div>
        </div>
    </div>

    <!-- Bootstrap 5 JS Bundle -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/js/bootstrap.bundle.min.js"></script>
</body>

</html>