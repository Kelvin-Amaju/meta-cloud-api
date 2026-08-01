<?php

// business/add.php — Create New WhatsApp Business Profile

require_once __DIR__ . '/../includes/init.php';

$alert = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $result = createBusiness($_POST);

    if (!empty($result['success'])) {
        $newId = $result['id'];
        header("Location: index?created=1");
        exit;
    } else {
        $alert = [
            'type'    => 'danger',
            'title'   => 'Creation Failed',
            'message' => htmlspecialchars($result['error'] ?? 'An error occurred while creating the business account.')
        ];
    }
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Add Business Profile - Netgrity WhatsApp API</title>

    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css" rel="stylesheet">
</head>

<body class="bg-light min-vh-100 d-flex flex-column py-4">

    <!-- Top Navbar -->
    <nav class="navbar navbar-expand-lg navbar-dark bg-dark mb-4 shadow-sm py-3">
        <div class="container-fluid container-xl">
            <a class="navbar-brand d-flex align-items-center gap-2" href="../index">
                <i class="bi bi-whatsapp text-success fs-4"></i>
                <span class="fw-bold">Netgrity</span> WhatsApp Multi-Tenant
            </a>

            <div class="d-flex align-items-center gap-2">
                <a href="index" class="btn btn-outline-light btn-sm">
                    <i class="bi bi-arrow-left me-1"></i> Business Directory
                </a>
            </div>
        </div>
    </nav>

    <div class="container my-auto" style="max-width: 860px;">

        <!-- Back Link & Title -->
        <div class="d-flex align-items-center justify-content-between mb-4">
            <div>
                <a href="index" class="btn btn-link p-0 text-decoration-none text-muted mb-1">
                    <i class="bi bi-arrow-left"></i> Back to Businesses
                </a>
                <h3 class="fw-bold mb-0 text-dark">
                    <i class="bi bi-plus-circle text-primary me-2"></i>Add Business Profile
                </h3>
                <p class="text-muted small mb-0">Configure your Meta WhatsApp Business API credentials and sender metadata.</p>
            </div>
        </div>

        <?php if ($alert): ?>
            <div class="alert alert-<?= $alert['type'] ?> alert-dismissible fade show shadow-sm border-0 mb-4">
                <div class="d-flex align-items-center gap-2">
                    <i class="bi bi-exclamation-octagon-fill fs-5"></i>
                    <div>
                        <strong class="d-block"><?= $alert['title'] ?></strong>
                        <?= $alert['message'] ?>
                    </div>
                </div>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>

        <!-- Main Card -->
        <div class="card border-0 shadow-sm rounded-3">
            <div class="card-body p-4 p-md-5">

                <form action="add" method="POST">

                    <!-- Section 1: General Business Metadata -->
                    <h5 class="fw-bold text-dark mb-4 pb-2 border-bottom">
                        <i class="bi bi-building me-2 text-primary"></i>General Business Information
                    </h5>

                    <div class="row g-3 mb-4">
                        <!-- Business Name -->
                        <div class="col-md-6">
                            <label for="name" class="form-label fw-semibold">Business Name <span class="text-danger">*</span></label>
                            <input
                                type="text"
                                class="form-control"
                                id="name"
                                name="name"
                                placeholder="e.g. Grand Palace Hotel"
                                value="<?= htmlspecialchars($_POST['name'] ?? '') ?>"
                                required>
                            <div class="form-text">Display name for your business sender account.</div>
                        </div>

                        <!-- Product Line -->
                        <div class="col-md-6">
                            <label for="product_line" class="form-label fw-semibold">Product Line <span class="text-danger">*</span></label>
                            <select class="form-select" id="product_line" name="product_line" required>
                                <option value="hotel" <?= ($_POST['product_line'] ?? '') === 'hotel' ? 'selected' : '' ?>>Hotel</option>
                                <option value="school" <?= ($_POST['product_line'] ?? '') === 'school' ? 'selected' : '' ?>>School</option>
                                <option value="hospital" <?= ($_POST['product_line'] ?? '') === 'hospital' ? 'selected' : '' ?>>Hospital</option>
                                <option value="erp" <?= ($_POST['product_line'] ?? '') === 'erp' ? 'selected' : '' ?>>ERP System</option>
                                <option value="crm" <?= ($_POST['product_line'] ?? '') === 'crm' ? 'selected' : '' ?>>CRM Platform</option>
                                <option value="other" <?= ($_POST['product_line'] ?? 'other') === 'other' ? 'selected' : '' ?>>Other</option>
                            </select>
                            <div class="form-text">Select product line classification.</div>
                        </div>

                        <!-- Meta Business Manager ID -->
                        <div class="col-md-6">
                            <label for="meta_business_id" class="form-label fw-semibold">Meta Business ID</label>
                            <div class="input-group">
                                <span class="input-group-text bg-light text-muted"><i class="bi bi-briefcase-fill"></i></span>
                                <input
                                    type="text"
                                    class="form-control"
                                    id="meta_business_id"
                                    name="meta_business_id"
                                    placeholder="e.g. 102938475610293"
                                    value="<?= htmlspecialchars($_POST['meta_business_id'] ?? '') ?>">
                            </div>
                            <div class="form-text">Your Meta Business Manager Account ID.</div>
                        </div>

                        <!-- Status -->
                        <div class="col-md-6">
                            <label for="status" class="form-label fw-semibold">Account Status <span class="text-danger">*</span></label>
                            <select class="form-select" id="status" name="status" required>
                                <option value="active" <?= ($_POST['status'] ?? 'active') === 'active' ? 'selected' : '' ?>>Active</option>
                                <option value="pending" <?= ($_POST['status'] ?? '') === 'pending' ? 'selected' : '' ?>>Pending Verification</option>
                                <option value="suspended" <?= ($_POST['status'] ?? '') === 'suspended' ? 'selected' : '' ?>>Suspended</option>
                                <option value="revoked" <?= ($_POST['status'] ?? '') === 'revoked' ? 'selected' : '' ?>>Revoked</option>
                            </select>
                        </div>
                    </div>

                    <!-- Section 2: WhatsApp Account Credentials -->
                    <h5 class="fw-bold text-dark mb-4 pb-2 border-bottom pt-2">
                        <i class="bi bi-whatsapp me-2 text-success"></i>WhatsApp API Configuration
                    </h5>

                    <div class="row g-3 mb-4">
                        <!-- WABA ID -->
                        <div class="col-md-6">
                            <label for="waba_id" class="form-label fw-semibold">WABA ID (Account ID)</label>
                            <div class="input-group">
                                <span class="input-group-text bg-light text-muted"><i class="bi bi-qr-code"></i></span>
                                <input
                                    type="text"
                                    class="form-control"
                                    id="waba_id"
                                    name="waba_id"
                                    placeholder="e.g. 109876543210987"
                                    value="<?= htmlspecialchars($_POST['waba_id'] ?? '') ?>">
                            </div>
                        </div>

                        <!-- Phone Number ID -->
                        <div class="col-md-6">
                            <label for="phone_number_id" class="form-label fw-semibold">Phone Number ID <span class="text-danger">*</span></label>
                            <div class="input-group">
                                <span class="input-group-text bg-light text-muted"><i class="bi bi-hash"></i></span>
                                <input
                                    type="text"
                                    class="form-control"
                                    id="phone_number_id"
                                    name="phone_number_id"
                                    placeholder="e.g. 105432109876543"
                                    value="<?= htmlspecialchars($_POST['phone_number_id'] ?? '') ?>"
                                    required>
                            </div>
                            <div class="form-text">Meta Graph API Phone Number ID.</div>
                        </div>

                        <!-- Display Phone Number -->
                        <div class="col-md-12">
                            <label for="display_phone_number" class="form-label fw-semibold">Display Phone Number</label>
                            <div class="input-group">
                                <span class="input-group-text bg-light text-muted"><i class="bi bi-telephone-fill"></i></span>
                                <input
                                    type="tel"
                                    class="form-control"
                                    id="display_phone_number"
                                    name="display_phone_number"
                                    placeholder="+234 904 431 3696"
                                    value="<?= htmlspecialchars($_POST['display_phone_number'] ?? '') ?>">
                            </div>
                        </div>
                    </div>

                    <!-- Section 3: API Token -->
                    <h5 class="fw-bold text-dark mb-4 pb-2 border-bottom pt-2">
                        <i class="bi bi-key-fill me-2 text-warning"></i>API Access Token
                    </h5>

                    <div class="row g-3 mb-4">
                        <!-- Token Type -->
                        <div class="col-md-4">
                            <label for="token_type" class="form-label fw-semibold">Token Type <span class="text-danger">*</span></label>
                            <select class="form-select" id="token_type" name="token_type" required>
                                <option value="system_user" <?= ($_POST['token_type'] ?? 'system_user') === 'system_user' ? 'selected' : '' ?>>System User Token (Permanent)</option>
                                <option value="temporary" <?= ($_POST['token_type'] ?? '') === 'temporary' ? 'selected' : '' ?>>Temporary Token (24 Hours)</option>
                            </select>
                        </div>

                        <!-- Access Token -->
                        <div class="col-md-8">
                            <label for="access_token" class="form-label fw-semibold">Access Token <span class="text-danger">*</span></label>
                            <div class="input-group">
                                <span class="input-group-text bg-light text-muted"><i class="bi bi-shield-lock-fill"></i></span>
                                <input
                                    type="password"
                                    class="form-control"
                                    id="access_token"
                                    name="access_token"
                                    placeholder="EAAG..."
                                    value="<?= htmlspecialchars($_POST['access_token'] ?? '') ?>"
                                    required>
                                <button class="btn btn-outline-secondary" type="button" id="toggleToken">
                                    <i class="bi bi-eye-fill"></i>
                                </button>
                            </div>
                            <div class="form-text">Paste your Meta WhatsApp System User Access Token.</div>
                        </div>
                    </div>

                    <!-- Actions -->
                    <div class="d-flex align-items-center justify-content-end gap-2 pt-3 border-top">
                        <a href="index" class="btn btn-outline-secondary px-4">Cancel</a>
                        <button type="submit" class="btn btn-dark px-4 fw-semibold d-flex align-items-center gap-2 shadow-sm">
                            <i class="bi bi-check-circle-fill"></i> Save Business Account
                        </button>
                    </div>

                </form>

            </div>
        </div>

    </div>

    <!-- Footer -->
    <footer class="mt-auto py-3 bg-white border-top text-center text-muted small">
        <div class="container">
            Netgrity WhatsApp Cloud API &bull; Multi-Tenant Business Setup
        </div>
    </footer>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>

    <script>
        document.getElementById('toggleToken').addEventListener('click', function() {
            const tokenInput = document.getElementById('access_token');
            const icon = this.querySelector('i');
            if (tokenInput.type === 'password') {
                tokenInput.type = 'text';
                icon.classList.replace('bi-eye-fill', 'bi-eye-slash-fill');
            } else {
                tokenInput.type = 'password';
                icon.classList.replace('bi-eye-slash-fill', 'bi-eye-fill');
            }
        });
    </script>

</body>

</html>