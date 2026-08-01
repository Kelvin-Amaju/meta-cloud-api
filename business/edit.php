<?php

// business/edit.php — Edit / Update WhatsApp Business Profile

require_once __DIR__ . '/../includes/init.php';

$businessId = (int)($_GET['id'] ?? ($_POST['id'] ?? 0));
$business   = $businessId > 0 ? getBusinessById($businessId) : null;

if (!$business) {
    header("Location: index");
    exit;
}

$alert = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $updateResult = updateBusiness($businessId, $_POST);

    if (!empty($updateResult['success'])) {
        $alert = [
            'type'    => 'success',
            'title'   => 'Business Updated!',
            'message' => 'Successfully updated business profile settings and API credentials.'
        ];
        // Reload updated record
        $business = getBusinessById($businessId);
    } else {
        $alert = [
            'type'    => 'danger',
            'title'   => 'Update Failed',
            'message' => htmlspecialchars($updateResult['error'] ?? 'An error occurred while updating the account.')
        ];
    }
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Edit Business - <?= htmlspecialchars($business['name']) ?></title>

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
                <a href="view?id=<?= $business['id'] ?>" class="btn btn-outline-light btn-sm">
                    <i class="bi bi-eye me-1"></i> View Profile
                </a>
                <a href="index" class="btn btn-outline-light btn-sm">
                    <i class="bi bi-arrow-left me-1"></i> Directory
                </a>
            </div>
        </div>
    </nav>

    <div class="container my-auto" style="max-width: 860px;">

        <!-- Header -->
        <div class="d-flex align-items-center justify-content-between mb-4">
            <div>
                <a href="index" class="btn btn-link p-0 text-decoration-none text-muted mb-1">
                    <i class="bi bi-arrow-left"></i> Back to Business Directory
                </a>
                <h3 class="fw-bold mb-0 text-dark">
                    <i class="bi bi-pencil-square text-primary me-2"></i>Edit Business Profile
                </h3>
                <p class="text-muted small font-monospace mb-0">UUID: <?= htmlspecialchars($business['uuid']) ?></p>
            </div>
        </div>

        <?php if ($alert): ?>
            <div class="alert alert-<?= $alert['type'] ?> alert-dismissible fade show shadow-sm border-0 mb-4">
                <div class="d-flex align-items-center gap-2">
                    <i class="bi bi-<?= $alert['type'] === 'success' ? 'check-circle-fill' : 'exclamation-octagon-fill' ?> fs-5"></i>
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

                <form action="edit?id=<?= $business['id'] ?>" method="POST">
                    <input type="hidden" name="id" value="<?= $business['id'] ?>">

                    <!-- Section 1: General Information -->
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
                                value="<?= htmlspecialchars($_POST['name'] ?? $business['name']) ?>"
                                required>
                        </div>

                        <!-- Product Line -->
                        <div class="col-md-6">
                            <label for="product_line" class="form-label fw-semibold">Product Line <span class="text-danger">*</span></label>
                            <?php $currLine = $_POST['product_line'] ?? $business['product_line']; ?>
                            <select class="form-select" id="product_line" name="product_line" required>
                                <option value="hotel" <?= $currLine === 'hotel' ? 'selected' : '' ?>>Hotel</option>
                                <option value="school" <?= $currLine === 'school' ? 'selected' : '' ?>>School</option>
                                <option value="hospital" <?= $currLine === 'hospital' ? 'selected' : '' ?>>Hospital</option>
                                <option value="erp" <?= $currLine === 'erp' ? 'selected' : '' ?>>ERP System</option>
                                <option value="crm" <?= $currLine === 'crm' ? 'selected' : '' ?>>CRM Platform</option>
                                <option value="other" <?= $currLine === 'other' ? 'selected' : '' ?>>Other</option>
                            </select>
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
                                    value="<?= htmlspecialchars($_POST['meta_business_id'] ?? $business['meta_business_id']) ?>">
                            </div>
                        </div>

                        <!-- Account Status -->
                        <div class="col-md-6">
                            <label for="status" class="form-label fw-semibold">Account Status <span class="text-danger">*</span></label>
                            <?php $currStatus = $_POST['status'] ?? $business['status']; ?>
                            <select class="form-select" id="status" name="status" required>
                                <option value="active" <?= $currStatus === 'active' ? 'selected' : '' ?>>Active</option>
                                <option value="pending" <?= $currStatus === 'pending' ? 'selected' : '' ?>>Pending Verification</option>
                                <option value="suspended" <?= $currStatus === 'suspended' ? 'selected' : '' ?>>Suspended</option>
                                <option value="revoked" <?= $currStatus === 'revoked' ? 'selected' : '' ?>>Revoked</option>
                            </select>
                        </div>
                    </div>

                    <!-- Section 2: WhatsApp Account Configuration -->
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
                                    value="<?= htmlspecialchars($_POST['waba_id'] ?? $business['waba_id']) ?>">
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
                                    value="<?= htmlspecialchars($_POST['phone_number_id'] ?? $business['phone_number_id']) ?>"
                                    required>
                            </div>
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
                                    value="<?= htmlspecialchars($_POST['display_phone_number'] ?? $business['display_phone_number']) ?>">
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
                            <?php $currTokenType = $_POST['token_type'] ?? $business['token_type']; ?>
                            <select class="form-select" id="token_type" name="token_type" required>
                                <option value="system_user" <?= $currTokenType === 'system_user' ? 'selected' : '' ?>>System User Token (Permanent)</option>
                                <option value="temporary" <?= $currTokenType === 'temporary' ? 'selected' : '' ?>>Temporary Token (24 Hours)</option>
                            </select>
                        </div>

                        <!-- Access Token -->
                        <div class="col-md-8">
                            <label for="access_token" class="form-label fw-semibold">Access Token</label>
                            <div class="input-group">
                                <span class="input-group-text bg-light text-muted"><i class="bi bi-shield-lock-fill"></i></span>
                                <input
                                    type="password"
                                    class="form-control"
                                    id="access_token"
                                    name="access_token"
                                    placeholder="Leave blank to keep existing token">
                                <button class="btn btn-outline-secondary" type="button" id="toggleToken">
                                    <i class="bi bi-eye-fill"></i>
                                </button>
                            </div>
                            <div class="form-text">Only enter a value if you wish to overwrite the current access token.</div>
                        </div>
                    </div>

                    <!-- Actions -->
                    <div class="d-flex align-items-center justify-content-between pt-3 border-top">
                        <a href="view?id=<?= $business['id'] ?>" class="btn btn-outline-secondary px-4">Cancel</a>
                        <button type="submit" class="btn btn-primary px-4 fw-semibold d-flex align-items-center gap-2 shadow-sm">
                            <i class="bi bi-save-fill"></i> Update Business Account
                        </button>
                    </div>

                </form>

            </div>
        </div>

    </div>

    <!-- Footer -->
    <footer class="mt-auto py-3 bg-white border-top text-center text-muted small">
        <div class="container">
            Netgrity WhatsApp Cloud API &bull; Multi-Tenant Business Profile Manager
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
