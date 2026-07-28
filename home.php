<?php

// index.php

?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Netgrity WhatsApp API Dashboard</title>
    <!-- Bootstrap 5 CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.2.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Bootstrap Icons -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.5/font/bootstrap-icons.css" rel="stylesheet">
</head>

<body class="bg-light">

    <!-- Top Navbar -->
    <nav class="navbar navbar-expand-lg navbar-dark bg-dark mb-5 shadow-sm">
        <div class="container">
            <a class="navbar-brand d-flex align-items-center gap-2" href="#">
                <i class="bi bi-whatsapp text-success fs-4"></i>
                <span class="fw-bold">Netgrity</span> WhatsApp API
            </a>
            <span class="badge bg-success px-3 py-2 rounded-pill">
                <i class="bi bi-check-circle-fill me-1"></i> Active
            </span>
        </div>
    </nav>

    <div class="container">
        <!-- Header Title -->
        <div class="row mb-4">
            <div class="col-12">
                <h2 class="fw-bold text-secondary mb-1">Developer Dashboard</h2>
                <p class="text-muted">Manage message dispatches, run connection diagnostics, and view real-time system logs.</p>
            </div>
        </div>

        <!-- Quick Action Cards -->
        <div class="row g-4 mb-4">
            <!-- Send Message Card -->
            <div class="col-md-6">
                <div class="card h-100 border-0 shadow-sm hover-shadow transition">
                    <div class="card-body p-4 d-flex flex-column justify-content-between">
                        <div>
                            <div class="d-flex align-items-center mb-3">
                                <div class="bg-success bg-opacity-10 text-success p-3 rounded-3 me-3">
                                    <i class="bi bi-send-fill fs-3"></i>
                                </div>
                                <div>
                                    <h5 class="card-title fw-bold mb-0">Send Message</h5>
                                    <small class="text-muted">Outbound API trigger</small>
                                </div>
                            </div>
                            <p class="card-text text-muted">Dispatch dynamic text or template notifications directly to WhatsApp clients.</p>
                        </div>
                        <div class="pt-3">
                            <a href="send" class="btn btn-success w-100 d-flex align-items-center justify-content-center gap-2 fw-semibold">
                                Send Message <i class="bi bi-arrow-right"></i>
                            </a>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Test Connection Card -->
            <div class="col-md-6">
                <div class="card h-100 border-0 shadow-sm hover-shadow transition">
                    <div class="card-body p-4 d-flex flex-column justify-content-between">
                        <div>
                            <div class="d-flex align-items-center mb-3">
                                <div class="bg-primary bg-opacity-10 text-primary p-3 rounded-3 me-3">
                                    <i class="bi bi-cpu-fill fs-3"></i>
                                </div>
                                <div>
                                    <h5 class="card-title fw-bold mb-0">Test API Connection</h5>
                                    <small class="text-muted">Meta Graph verification</small>
                                </div>
                            </div>
                            <p class="card-text text-muted">Validate token validity, account IDs, and Meta Graph API connectivity status.</p>
                        </div>
                        <div class="pt-3">
                            <a href="test" class="btn btn-outline-primary w-100 d-flex align-items-center justify-content-center gap-2 fw-semibold">
                                Run Diagnostics <i class="bi bi-lightning-charge-fill"></i>
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- System Logs Section -->
        <div class="card border-0 shadow-sm">
            <div class="card-header bg-white py-3 border-0">
                <h5 class="card-title fw-bold mb-0 text-dark d-flex align-items-center gap-2">
                    <i class="bi bi-journal-code text-secondary"></i> Application Logs
                </h5>
            </div>
            <div class="card-body p-0">
                <div class="list-group list-group-flush border-top">
                    <!-- Webhook Log Link -->
                    <a href="storage/logs/webhook.log" target="_blank" class="list-group-item list-group-item-action p-3 d-flex align-items-center justify-content-between">
                        <div class="d-flex align-items-center gap-3">
                            <i class="bi bi-arrow-down-left-square fs-4 text-info"></i>
                            <div>
                                <h6 class="mb-0 fw-semibold text-dark">Webhook Incoming Logs</h6>
                                <small class="text-muted">View inbound message payloads and event delivery callbacks</small>
                            </div>
                        </div>
                        <span class="badge bg-light text-dark border">
                            <i class="bi bi-box-arrow-up-right me-1"></i> Open Log
                        </span>
                    </a>

                    <!-- Error Log Link -->
                    <a href="storage/logs/errors.log" target="_blank" class="list-group-item list-group-item-action p-3 d-flex align-items-center justify-content-between">
                        <div class="d-flex align-items-center gap-3">
                            <i class="bi bi-exclamation-triangle-fill fs-4 text-danger"></i>
                            <div>
                                <h6 class="mb-0 fw-semibold text-dark">System Error Logs</h6>
                                <small class="text-muted">Inspect cURL failures, missing env variables, and API errors</small>
                            </div>
                        </div>
                        <span class="badge bg-light text-dark border">
                            <i class="bi bi-box-arrow-up-right me-1"></i> Open Log
                        </span>
                    </a>
                </div>
            </div>
        </div>
    </div>

    <!-- Bootstrap 5 JS Bundle -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.2.3/dist/js/bootstrap.bundle.min.js"></script>
</body>

</html>