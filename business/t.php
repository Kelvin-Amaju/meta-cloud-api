<?php

// business.php
session_start();

require_once 'includes/businesses.php';

$flash = null;

// ── Handle form submissions ─────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'create') {
        $result = createBusiness($_POST);
        $flash = $result['success']
            ? ['type' => 'success', 'text' => 'Business added successfully.']
            : ['type' => 'danger', 'text' => $result['error']];

    } elseif ($action === 'update') {
        $id = (int)($_POST['id'] ?? 0);
        $result = updateBusiness($id, $_POST);
        $flash = $result['success']
            ? ['type' => 'success', 'text' => 'Business updated successfully.']
            : ['type' => 'danger', 'text' => $result['error']];

    } elseif ($action === 'toggle_status') {
        $id        = (int)($_POST['id'] ?? 0);
        $newStatus = $_POST['new_status'] ?? 'suspended';
        $result = updateBusiness($id, ['status' => $newStatus]);
        $flash = $result['success']
            ? ['type' => 'success', 'text' => 'Business status updated.']
            : ['type' => 'danger', 'text' => $result['error']];

    } elseif ($action === 'delete') {
        $id = (int)($_POST['id'] ?? 0);
        $ok = deleteBusiness($id);
        $flash = $ok
            ? ['type' => 'success', 'text' => 'Business deleted.']
            : ['type' => 'danger', 'text' => 'Could not delete that business.'];
    }

    // Post/Redirect/Get — avoids resubmission on refresh
    $_SESSION['business_flash'] = $flash;
    header('Location: business');
    exit;
}

if (isset($_SESSION['business_flash'])) {
    $flash = $_SESSION['business_flash'];
    unset($_SESSION['business_flash']);
}

// ── Load list ────────────────────────────────────────────────
$search       = trim($_GET['search'] ?? '');
$statusFilter = $_GET['status'] ?? '';
$lineFilter   = $_GET['product_line'] ?? '';

$businesses = getAllBusinesses([
    'search'       => $search,
    'status'       => $statusFilter,
    'product_line' => $lineFilter,
]);

$stats = getBusinessSummaryStats();

$statusBadge = [
    'active'    => 'success',
    'pending'   => 'warning',
    'suspended' => 'secondary',
    'revoked'   => 'danger',
];

$productLineLabel = [
    'hotel'    => 'Hotel',
    'school'   => 'School',
    'hospital' => 'Hospital',
    'erp'      => 'ERP',
    'crm'      => 'CRM',
    'other'    => 'Other',
];
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Businesses - Netgrity WhatsApp API</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.5/font/bootstrap-icons.css" rel="stylesheet">
    <style>
        .token-input { font-family: monospace; }
    </style>
</head>

<body class="bg-light min-vh-100 d-flex flex-column">

    <!-- Top Navbar -->
    <nav class="navbar navbar-expand-lg navbar-dark bg-dark mb-4 shadow-sm">
        <div class="container">
            <a class="navbar-brand d-flex align-items-center gap-2" href="index">
                <i class="bi bi-whatsapp text-success fs-4"></i>
                <span class="fw-bold">Netgrity</span> WhatsApp API
            </a>
            <div class="d-flex gap-2">
                <a href="messages" class="btn btn-outline-light btn-sm">
                    <i class="bi bi-chat-left-text me-1"></i> Messages
                </a>
                <a href="index" class="btn btn-outline-light btn-sm">
                    <i class="bi bi-arrow-left me-1"></i> Dashboard
                </a>
            </div>
        </div>
    </nav>

    <div class="container py-2 flex-grow-1" style="max-width:1200px;">

        <div class="d-flex align-items-center justify-content-between mb-4 flex-wrap gap-2">
            <div>
                <h4 class="fw-bold mb-1">
                    <i class="bi bi-building text-success"></i> Businesses
                </h4>
                <p class="text-muted mb-0 small">Each business has its own Meta phone number, WABA, and access
                    token.</p>
            </div>
            <button type="button" class="btn btn-success" data-bs-toggle="modal" data-bs-target="#businessModal"
                onclick="openCreateModal()">
                <i class="bi bi-plus-lg me-1"></i> Add Business
            </button>
        </div>

        <?php if ($flash): ?>
            <div class="alert alert-<?= htmlspecialchars($flash['type']) ?> border-0 shadow-sm">
                <?= htmlspecialchars($flash['text']) ?>
            </div>
        <?php endif; ?>

        <?php require __DIR__ . '/includes/partials/messaging_limit_banner.php'; ?>

        <!-- Stats -->
        <div class="row g-3 mb-4">
            <div class="col-6 col-md-3">
                <div class="card border-0 shadow-sm h-100">
                    <div class="card-body">
                        <div class="text-muted small text-uppercase fw-semibold">Total</div>
                        <div class="display-6 fw-bold"><?= number_format($stats['total']) ?></div>
                    </div>
                </div>
            </div>
            <div class="col-6 col-md-3">
                <div class="card border-0 shadow-sm h-100">
                    <div class="card-body">
                        <div class="text-muted small text-uppercase fw-semibold">Active</div>
                        <div class="display-6 fw-bold text-success"><?= number_format($stats['active']) ?></div>
                    </div>
                </div>
            </div>
            <div class="col-6 col-md-3">
                <div class="card border-0 shadow-sm h-100">
                    <div class="card-body">
                        <div class="text-muted small text-uppercase fw-semibold">Pending</div>
                        <div class="display-6 fw-bold text-warning"><?= number_format($stats['pending']) ?></div>
                    </div>
                </div>
            </div>
            <div class="col-6 col-md-3">
                <div class="card border-0 shadow-sm h-100">
                    <div class="card-body">
                        <div class="text-muted small text-uppercase fw-semibold">Suspended</div>
                        <div class="display-6 fw-bold text-secondary"><?= number_format($stats['suspended']) ?></div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Filters -->
        <div class="card border-0 shadow-sm mb-4">
            <div class="card-body p-3">
                <form method="get" action="business" class="row g-2 align-items-end">
                    <div class="col-12 col-md-5">
                        <label class="form-label small fw-semibold mb-1">Search</label>
                        <div class="input-group">
                            <span class="input-group-text"><i class="bi bi-search"></i></span>
                            <input type="text" name="search" class="form-control"
                                placeholder="Name, phone number, WABA ID"
                                value="<?= htmlspecialchars($search) ?>">
                        </div>
                    </div>
                    <div class="col-6 col-md-3">
                        <label class="form-label small fw-semibold mb-1">Status</label>
                        <select name="status" class="form-select">
                            <option value="" <?= $statusFilter === '' ? 'selected' : '' ?>>All</option>
                            <?php foreach ($statusBadge as $val => $color): ?>
                                <option value="<?= $val ?>" <?= $statusFilter === $val ? 'selected' : '' ?>>
                                    <?= ucfirst($val) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-6 col-md-2">
                        <label class="form-label small fw-semibold mb-1">Product Line</label>
                        <select name="product_line" class="form-select">
                            <option value="">All</option>
                            <?php foreach ($productLineLabel as $val => $label): ?>
                                <option value="<?= $val ?>" <?= $lineFilter === $val ? 'selected' : '' ?>>
                                    <?= $label ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-6 col-md-2 d-flex gap-2">
                        <button type="submit" class="btn btn-success flex-grow-1">
                            <i class="bi bi-funnel-fill"></i>
                        </button>
                        <a href="business" class="btn btn-outline-secondary" title="Clear filters">
                            <i class="bi bi-x-lg"></i>
                        </a>
                    </div>
                </form>
            </div>
        </div>

        <!-- Table -->
        <div class="card border-0 shadow-sm">
            <div class="card-header bg-white border-0 py-3">
                <span class="fw-semibold"><?= count($businesses) ?> business<?= count($businesses) === 1 ? '' : 'es' ?></span>
            </div>

            <?php if (empty($businesses)): ?>
                <div class="card-body text-center py-5 text-muted">
                    <i class="bi bi-building display-4 d-block mb-2"></i>
                    <?php if ($search !== '' || $statusFilter !== '' || $lineFilter !== ''): ?>
                        No businesses match your filters.
                        <div class="mt-2">
                            <a href="business" class="btn btn-sm btn-outline-secondary">Clear filters</a>
                        </div>
                    <?php else: ?>
                        No businesses added yet.
                    <?php endif; ?>
                </div>
            <?php else: ?>
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0">
                        <thead class="table-light">
                            <tr>
                                <th>Business</th>
                                <th>Product Line</th>
                                <th>Phone Number ID</th>
                                <th>WABA ID</th>
                                <th>Token Type</th>
                                <th>Status</th>
                                <th class="text-end pe-3">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($businesses as $b): ?>
                                <tr>
                                    <td>
                                        <div class="fw-semibold"><?= htmlspecialchars($b['name']) ?></div>
                                        <?php if (!empty($b['display_phone_number'])): ?>
                                            <div class="small text-muted"><?= htmlspecialchars($b['display_phone_number']) ?></div>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <span class="badge bg-light text-dark border">
                                            <?= htmlspecialchars($productLineLabel[$b['product_line']] ?? 'Other') ?>
                                        </span>
                                    </td>
                                    <td><code class="small"><?= htmlspecialchars($b['phone_number_id']) ?></code></td>
                                    <td><code class="small"><?= htmlspecialchars($b['waba_id'] ?: '—') ?></code></td>
                                    <td class="small text-muted"><?= htmlspecialchars(ucwords(str_replace('_', ' ', $b['token_type']))) ?></td>
                                    <td>
                                        <span class="badge bg-<?= $statusBadge[$b['status']] ?? 'secondary' ?>">
                                            <?= ucfirst($b['status']) ?>
                                        </span>
                                    </td>
                                    <td class="text-end pe-3">
                                        <div class="d-flex justify-content-end gap-1">
                                            <button type="button" class="btn btn-sm btn-outline-primary" title="Edit"
                                                onclick='openEditModal(<?= json_encode($b) ?>)'>
                                                <i class="bi bi-pencil"></i>
                                            </button>

                                            <form method="post" action="business" class="d-inline">
                                                <input type="hidden" name="action" value="toggle_status">
                                                <input type="hidden" name="id" value="<?= (int)$b['id'] ?>">
                                                <?php if ($b['status'] === 'active'): ?>
                                                    <input type="hidden" name="new_status" value="suspended">
                                                    <button type="submit" class="btn btn-sm btn-outline-secondary" title="Suspend">
                                                        <i class="bi bi-pause-fill"></i>
                                                    </button>
                                                <?php else: ?>
                                                    <input type="hidden" name="new_status" value="active">
                                                    <button type="submit" class="btn btn-sm btn-outline-success" title="Activate">
                                                        <i class="bi bi-play-fill"></i>
                                                    </button>
                                                <?php endif; ?>
                                            </form>

                                            <form method="post" action="business" class="d-inline"
                                                onsubmit="return confirm('Delete this business permanently? This cannot be undone.');">
                                                <input type="hidden" name="action" value="delete">
                                                <input type="hidden" name="id" value="<?= (int)$b['id'] ?>">
                                                <button type="submit" class="btn btn-sm btn-outline-danger" title="Delete">
                                                    <i class="bi bi-trash"></i>
                                                </button>
                                            </form>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>

    </div>

    <!-- Add / Edit Business Modal -->
    <div class="modal fade" id="businessModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered modal-lg">
            <form method="post" action="business" class="modal-content">
                <input type="hidden" name="action" id="formAction" value="create">
                <input type="hidden" name="id" id="formId" value="">

                <div class="modal-header">
                    <h5 class="modal-title" id="modalTitle">
                        <i class="bi bi-building text-success me-1"></i> Add Business
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>

                <div class="modal-body">
                    <div class="row g-3">

                        <div class="col-md-6">
                            <label class="form-label small fw-semibold">Business Name <span class="text-danger">*</span></label>
                            <input type="text" name="name" id="f_name" class="form-control" required>
                        </div>

                        <div class="col-md-6">
                            <label class="form-label small fw-semibold">Product Line</label>
                            <select name="product_line" id="f_product_line" class="form-select">
                                <?php foreach ($productLineLabel as $val => $label): ?>
                                    <option value="<?= $val ?>"><?= $label ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div class="col-md-6">
                            <label class="form-label small fw-semibold">Phone Number ID <span class="text-danger">*</span></label>
                            <input type="text" name="phone_number_id" id="f_phone_number_id" class="form-control token-input" required>
                        </div>

                        <div class="col-md-6">
                            <label class="form-label small fw-semibold">Display Phone Number</label>
                            <input type="text" name="display_phone_number" id="f_display_phone_number" class="form-control" placeholder="+234...">
                        </div>

                        <div class="col-md-6">
                            <label class="form-label small fw-semibold">WABA ID</label>
                            <input type="text" name="waba_id" id="f_waba_id" class="form-control token-input">
                        </div>

                        <div class="col-md-6">
                            <label class="form-label small fw-semibold">Meta Business ID</label>
                            <input type="text" name="meta_business_id" id="f_meta_business_id" class="form-control token-input">
                        </div>

                        <div class="col-12">
                            <label class="form-label small fw-semibold">
                                Access Token <span id="tokenRequired" class="text-danger">*</span>
                                <span id="tokenHint" class="text-muted fw-normal d-none">(leave blank to keep current token)</span>
                            </label>
                            <textarea name="access_token" id="f_access_token" class="form-control token-input" rows="2"></textarea>
                            <div class="form-text">Use a permanent System User token for production, not a 24-hour test token.</div>
                        </div>

                        <div class="col-md-6">
                            <label class="form-label small fw-semibold">Token Type</label>
                            <select name="token_type" id="f_token_type" class="form-select">
                                <option value="system_user">System User (permanent)</option>
                                <option value="temporary">Temporary (24h)</option>
                            </select>
                        </div>

                        <div class="col-md-6">
                            <label class="form-label small fw-semibold">Status</label>
                            <select name="status" id="f_status" class="form-select">
                                <option value="pending">Pending</option>
                                <option value="active">Active</option>
                                <option value="suspended">Suspended</option>
                                <option value="revoked">Revoked</option>
                            </select>
                        </div>

                    </div>
                </div>

                <div class="modal-footer">
                    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-success">
                        <i class="bi bi-check-lg me-1"></i> Save Business
                    </button>
                </div>
            </form>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        function openCreateModal() {
            document.getElementById('modalTitle').innerHTML = '<i class="bi bi-building text-success me-1"></i> Add Business';
            document.getElementById('formAction').value = 'create';
            document.getElementById('formId').value = '';

            ['f_name', 'f_phone_number_id', 'f_display_phone_number', 'f_waba_id', 'f_meta_business_id', 'f_access_token']
                .forEach(id => document.getElementById(id).value = '');

            document.getElementById('f_status').value = 'active';
            document.getElementById('f_token_type').value = 'system_user';

            document.getElementById('f_access_token').required = true;
            document.getElementById('tokenHint').classList.add('d-none');
            document.getElementById('tokenRequired').classList.remove('d-none');
        }

        function openEditModal(b) {
            document.getElementById('modalTitle').innerHTML = '<i class="bi bi-pencil text-primary me-1"></i> Edit Business';
            document.getElementById('formAction').value = 'update';
            document.getElementById('formId').value = b.id;

            document.getElementById('f_name').value = b.name || '';
            document.getElementById('f_product_line').value = b.product_line || 'other';
            document.getElementById('f_phone_number_id').value = b.phone_number_id || '';
            document.getElementById('f_display_phone_number').value = b.display_phone_number || '';
            document.getElementById('f_waba_id').value = b.waba_id || '';
            document.getElementById('f_meta_business_id').value = b.meta_business_id || '';
            document.getElementById('f_access_token').value = '';
            document.getElementById('f_token_type').value = b.token_type || 'system_user';
            document.getElementById('f_status').value = b.status || 'pending';

            // Access token optional on edit — only overwritten if re-typed
            document.getElementById('f_access_token').required = false;
            document.getElementById('tokenHint').classList.remove('d-none');
            document.getElementById('tokenRequired').classList.add('d-none');

            new bootstrap.Modal(document.getElementById('businessModal')).show();
        }
    </script>

</body>

</html>