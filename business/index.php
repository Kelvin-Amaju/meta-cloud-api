<?php

// business/index.php — Business Directory & Overview

require_once __DIR__ . '/../includes/init.php';

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


$alert = null;

// Handle Delete POST action
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'delete') {
    $deleteId = (int)($_POST['business_id'] ?? 0);
    $targetBiz = getBusinessById($deleteId);

    if ($targetBiz && deleteBusiness($deleteId)) {
        $alert = [
            'type'    => 'success',
            'title'   => 'Business Deleted',
            'message' => "Successfully removed business profile <strong>" . htmlspecialchars($targetBiz['name']) . "</strong>."
        ];
    } else {
        $alert = [
            'type'    => 'danger',
            'title'   => 'Delete Failed',
            'message' => 'Unable to delete business account. Please try again.'
        ];
    }
}

// Read filters
$search      = trim($_GET['search'] ?? '');
$productLine = trim($_GET['product_line'] ?? '');
$status      = trim($_GET['status'] ?? '');

$filters = [
    'search'       => $search,
    'product_line' => $productLine,
    'status'       => $status,
];

$businesses = getAllBusinesses($filters);
$stats      = getBusinessSummaryStats();
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Business Accounts - Netgrity WhatsApp API</title>

    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css" rel="stylesheet">
    <style>
        .token-input {
            font-family: monospace;
        }

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

<body class="bg-light min-vh-100 d-flex flex-column">

    <!-- Top Navbar -->
    <nav class="navbar navbar-expand-lg navbar-dark bg-dark mb-4 shadow-sm py-3">
        <div class="container-fluid container-xl">
            <a class="navbar-brand d-flex align-items-center gap-2" href="../index">
                <i class="bi bi-whatsapp text-success fs-4"></i>
                <span class="fw-bold">Netgrity</span> WhatsApp Multi-Tenant
            </a>

            <div class="d-flex align-items-center gap-2">
                <a href="../send" class="btn btn-success btn-sm">
                    <i class="bi bi-paperplane-fill me-1"></i> Send Message
                </a>
                <a href="../messages" class="btn btn-outline-light btn-sm">
                    <i class="bi bi-chat-left-text me-1"></i> Logs
                </a>
                <a href="../home" class="btn btn-outline-light btn-sm">
                    <i class="bi bi-speedometer2 me-1"></i> Dashboard
                </a>
            </div>
        </div>
    </nav>

    <div class="container-fluid container-xl mb-5 my-auto">

        <!-- Header -->
        <div class="d-flex align-items-center justify-content-between flex-wrap gap-3 mb-4">
            <div>
                <h4 class="fw-bold mb-1">
                    <i class="bi bi-building text-success"></i> Businesses
                </h4>
                <p class="text-muted mb-0 small">Each business has its own Meta phone number, WABA, and access
                    token.</p>
            </div>

            <div>
                <a href="add" class="btn btn-success btn shadow-sm d-flex align-items-center gap-2">
                    <i class="bi bi-plus-lg"></i> Add New Business
                </a>
            </div>
        </div>

        <?php if ($flash): ?>
            <div class="alert alert-<?= htmlspecialchars($flash['type']) ?> border-0 shadow-sm">
                <?= htmlspecialchars($flash['text']) ?>
            </div>
        <?php endif; ?>

        <?php require __DIR__ . '/../includes/partials/messaging_limit_banner.php'; ?>


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

        <!-- Summary Metrics Cards -->
        <div class="row g-3 mb-4">

            <div class="col-6 col-md-3">
                <div class="card border-0 shadow-sm p-3">
                    <div class="d-flex align-items-center justify-content-between">
                        <div>
                            <span class="text-muted small fw-semibold text-uppercase d-block">Total Profiles</span>
                            <h3 class="fw-bold text-dark mb-0 mt-1"><?= number_format($stats['total']) ?></h3>
                        </div>
                        <div class="bg-primary-subtle text-primary p-3 rounded-3">
                            <i class="bi bi-building fs-4"></i>
                        </div>
                    </div>
                </div>
            </div>

            <div class="col-6 col-md-3">
                <div class="card border-0 shadow-sm p-3">
                    <div class="d-flex align-items-center justify-content-between">
                        <div>
                            <span class="text-muted small fw-semibold text-uppercase d-block">Active Accounts</span>
                            <h3 class="fw-bold text-success mb-0 mt-1"><?= number_format($stats['active']) ?></h3>
                        </div>
                        <div class="bg-success-subtle text-success p-3 rounded-3">
                            <i class="bi bi-check-circle fs-4"></i>
                        </div>
                    </div>
                </div>
            </div>

            <div class="col-6 col-md-3">
                <div class="card border-0 shadow-sm p-3">
                    <div class="d-flex align-items-center justify-content-between">
                        <div>
                            <span class="text-muted small fw-semibold text-uppercase d-block">Pending / Suspended</span>
                            <h3 class="fw-bold text-warning mb-0 mt-1"><?= number_format($stats['pending'] + $stats['suspended']) ?></h3>
                        </div>
                        <div class="bg-warning-subtle text-warning p-3 rounded-3">
                            <i class="bi bi-hourglass-split fs-4"></i>
                        </div>
                    </div>
                </div>
            </div>

            <div class="col-6 col-md-3">
                <div class="card border-0 shadow-sm p-3">
                    <div class="d-flex align-items-center justify-content-between">
                        <div>
                            <span class="text-muted small fw-semibold text-uppercase d-block">Product Lines</span>
                            <h3 class="fw-bold text-info mb-0 mt-1"><?= number_format($stats['product_lines']) ?></h3>
                        </div>
                        <div class="bg-info-subtle text-info p-3 rounded-3">
                            <i class="bi bi-diagram-3 fs-4"></i>
                        </div>
                    </div>
                </div>
            </div>

        </div>

        <!-- Filter & Search Toolbar -->
        <div class="card border-0 shadow-sm mb-4">
            <div class="card-body p-3">
                <form method="get" action="index" class="row g-2 align-items-center">

                    <!-- Search Input -->
                    <div class="col-12 col-md-5">
                        <div class="input-group">
                            <span class="input-group-text bg-white text-muted"><i class="bi bi-search"></i></span>
                            <input
                                type="text"
                                name="search"
                                class="form-control"
                                placeholder="Search by name, phone ID, WABA ID..."
                                value="<?= htmlspecialchars($search) ?>">
                        </div>
                    </div>

                    <!-- Product Line Filter -->
                    <div class="col-6 col-md-3">
                        <select name="product_line" class="form-select">
                            <option value="">All Product Lines</option>
                            <option value="hotel" <?= $productLine === 'hotel' ? 'selected' : '' ?>>Hotel</option>
                            <option value="school" <?= $productLine === 'school' ? 'selected' : '' ?>>School</option>
                            <option value="hospital" <?= $productLine === 'hospital' ? 'selected' : '' ?>>Hospital</option>
                            <option value="erp" <?= $productLine === 'erp' ? 'selected' : '' ?>>ERP</option>
                            <option value="crm" <?= $productLine === 'crm' ? 'selected' : '' ?>>CRM</option>
                            <option value="other" <?= $productLine === 'other' ? 'selected' : '' ?>>Other</option>
                        </select>
                    </div>

                    <!-- Status Filter -->
                    <div class="col-6 col-md-2">
                        <select name="status" class="form-select">
                            <option value="">All Statuses</option>
                            <option value="active" <?= $status === 'active' ? 'selected' : '' ?>>Active</option>
                            <option value="pending" <?= $status === 'pending' ? 'selected' : '' ?>>Pending</option>
                            <option value="suspended" <?= $status === 'suspended' ? 'selected' : '' ?>>Suspended</option>
                            <option value="revoked" <?= $status === 'revoked' ? 'selected' : '' ?>>Revoked</option>
                        </select>
                    </div>

                    <!-- Buttons -->
                    <div class="col-12 col-md-2 d-flex gap-2">
                        <button type="submit" class="btn btn-primary flex-grow-1">
                            <i class="bi bi-filter me-1"></i> Filter
                        </button>
                        <a href="index" class="btn btn-outline-secondary" title="Reset Filters">
                            <i class="bi bi-arrow-counterclockwise"></i>
                        </a>
                    </div>

                </form>
            </div>
        </div>

        <!-- Business Accounts Table -->
        <div class="card border-0 shadow-sm">
            <div class="card-header bg-white border-bottom py-3 d-flex align-items-center justify-content-between">
                <span class="fw-semibold text-dark">
                    Showing <?= count($businesses) ?> Business Account<?= count($businesses) === 1 ? '' : 's' ?>
                </span>
            </div>

            <?php if (empty($businesses)): ?>
                <div class="card-body text-center py-5 text-muted">
                    <i class="bi bi-building-exclamation display-4 d-block mb-3"></i>
                    <h5>No business accounts found</h5>
                    <p class="small text-muted mb-3">Try clearing your search filters or register a new business sender.</p>
                    <a href="add" class="btn btn-dark btn-sm px-4">Add Business Profile</a>
                </div>
            <?php else: ?>
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0">
                        <thead class="table-light">
                            <tr>
                                <th>Business Name & UUID</th>
                                <th>Product Line</th>
                                <th>Phone Number & ID</th>
                                <th>WABA ID</th>
                                <th>Status</th>
                                <th class="text-end pe-4">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($businesses as $b): ?>
                                <?php
                                $lineClass   = 'badge-' . strtolower($b['product_line'] ?? 'other');
                                $statusClass = 'badge-status-' . strtolower($b['status'] ?? 'pending');
                                ?>
                                <tr>
                                    <td>
                                        <div class="fw-bold text-dark fs-6"><?= htmlspecialchars($b['name']) ?></div>
                                        <small class="font-monospace text-muted" style="font-size: 0.72rem;">UUID: <?= htmlspecialchars($b['uuid']) ?></small>
                                    </td>

                                    <td>
                                        <span class="badge <?= $lineClass ?> px-2 py-1 text-uppercase"><?= htmlspecialchars($b['product_line']) ?></span>
                                    </td>

                                    <td>
                                        <div class="fw-semibold"><?= htmlspecialchars($b['display_phone_number'] ?: 'N/A') ?></div>
                                        <small class="font-monospace text-muted" style="font-size: 0.75rem;">ID: <?= htmlspecialchars($b['phone_number_id']) ?></small>
                                    </td>

                                    <td>
                                        <span class="font-monospace small text-dark"><?= htmlspecialchars($b['waba_id'] ?: '—') ?></span>
                                    </td>

                                    <td>
                                        <span class="badge <?= $statusClass ?> px-2 py-1 text-uppercase" style="font-size: 0.7rem;"><?= htmlspecialchars($b['status']) ?></span>
                                    </td>

                                    <td class="text-end pe-4">
                                        <div class="btn-group btn-group-sm">
                                            <a href="view?id=<?= $b['id'] ?>" class="btn btn-outline-secondary" title="View Profile">
                                                <i class="bi bi-eye"></i>
                                            </a>
                                            <a href="edit?id=<?= $b['id'] ?>" class="btn btn-outline-primary" title="Edit Profile">
                                                <i class="bi bi-pencil"></i>
                                            </a>
                                            <a href="../send?business_id=<?= $b['id'] ?>" class="btn btn-outline-success" title="Send WhatsApp Message">
                                                <i class="bi bi-send-fill"></i>
                                            </a>
                                            <button
                                                type="button"
                                                class="btn btn-outline-danger"
                                                data-bs-toggle="modal"
                                                data-bs-target="#deleteModal<?= $b['id'] ?>"
                                                title="Delete Business">
                                                <i class="bi bi-trash"></i>
                                            </button>
                                        </div>

                                        <!-- Delete Confirmation Modal -->
                                        <div class="modal fade text-start" id="deleteModal<?= $b['id'] ?>" tabindex="-1">
                                            <div class="modal-dialog modal-dialog-centered">
                                                <div class="modal-content">
                                                    <div class="modal-header">
                                                        <h5 class="modal-title text-danger fw-bold">
                                                            <i class="bi bi-exclamation-triangle-fill me-2"></i>Delete Business Profile
                                                        </h5>
                                                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                                                    </div>
                                                    <div class="modal-body">
                                                        Are you sure you want to delete <strong><?= htmlspecialchars($b['name']) ?></strong>?
                                                        <p class="text-muted small mt-2 mb-0">This action will remove the business profile and its associated API credentials from the system.</p>
                                                    </div>
                                                    <div class="modal-footer">
                                                        <button type="button" class="btn btn-light border" data-bs-dismiss="modal">Cancel</button>
                                                        <form method="post" action="index">
                                                            <input type="hidden" name="action" value="delete">
                                                            <input type="hidden" name="business_id" value="<?= $b['id'] ?>">
                                                            <button type="submit" class="btn btn-danger">Delete Business</button>
                                                        </form>
                                                    </div>
                                                </div>
                                            </div>
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

    <!-- Footer -->
    <footer class="mt-auto py-3 bg-white border-top text-center text-muted small">
        <div class="container">
            Netgrity WhatsApp Cloud API &bull; Multi-Tenant Business Directory
        </div>
    </footer>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>

</body>

</html>