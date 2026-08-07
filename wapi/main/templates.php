<?php

// templates.php — Template Manager (view / sync from Meta / manual draft)

require_once __DIR__ . '/../_includes/functions.inc.php';
require_once __DIR__ . '/../_includes/business_functions.inc.php';
require_once __DIR__ . '/../_includes/template_functions.inc.php';

$activeNav = 'templates';

$activeBusinesses = get_active_businesses('active');

$alert = null;

// ── Handle POST actions ─────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'sync') {
        $businessId = (int)($_POST['business_id'] ?? 0);
        if ($businessId <= 0) {
            $alert = ['type' => 'danger', 'title' => 'Sync Failed', 'message' => 'Select a sender business to sync templates for.'];
        } else {
            $result = sync_templates_from_meta($businessId);
            if ($result['success']) {
                $alert = ['type' => 'success', 'title' => 'Sync Complete', 'message' => "Imported or updated <strong>{$result['count']}</strong> template(s) from Meta."];
            } else {
                $alert = ['type' => 'danger', 'title' => 'Sync Failed', 'message' => htmlspecialchars($result['error'])];
            }
        }

    } elseif ($action === 'create') {
        $result = create_template($_POST);
        $alert = $result['success']
            ? ['type' => 'success', 'title' => 'Template Saved', 'message' => 'Draft template created. Submit and approve it in Meta Business Manager, then re-sync.']
            : ['type' => 'danger', 'title' => 'Save Failed', 'message' => htmlspecialchars($result['error'])];

    } elseif ($action === 'delete') {
        $id = (int)($_POST['id'] ?? 0);
        $alert = delete_template($id)
            ? ['type' => 'success', 'title' => 'Template Deleted', 'message' => 'Template removed from the local manager.']
            : ['type' => 'danger', 'title' => 'Delete Failed', 'message' => 'Could not delete that template.'];
    }
}

// ── Filters ─────────────────────────────────────────────
$businessFilter = (int)($_GET['business_id'] ?? 0);
$statusFilter   = $_GET['status'] ?? '';

$templateRows = [];
if ($businessFilter > 0) {
    $stmt = $GLOBALS['mysqli']->prepare(
        "SELECT id, meta_template_id, name, language, category, status, body_text, variable_count, updated_at
         FROM message_templates
         WHERE business_id = ?
         " . ($statusFilter !== '' ? "AND status = ?" : "") . "
         ORDER BY name ASC"
    );
    if ($statusFilter !== '') {
        $stmt->bind_param("is", $businessFilter, $statusFilter);
    } else {
        $stmt->bind_param("i", $businessFilter);
    }
    $stmt->execute();
    $templateRows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();
}

function buildQ(array $overrides = []): string
{
    $params = [
        'business_id' => $_GET['business_id'] ?? '',
        'status'      => $_GET['status'] ?? '',
    ];
    $params = array_merge($params, $overrides);
    $params = array_filter($params, fn($v) => $v !== '' && $v !== null);
    return http_build_query($params);
}

$statusBadgeColors = [
    'approved' => 'success',
    'pending'  => 'warning',
    'rejected' => 'danger',
    'draft'    => 'secondary',
];

?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Templates - Netgrity WhatsApp API</title>

    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.5/font/bootstrap-icons.css" rel="stylesheet">
    <link href="../assets/css/app.css" rel="stylesheet">
</head>

<body class="bg-light min-vh-100 d-flex flex-column">

    <?php require __DIR__ . '/../_includes/sidebar.inc.php'; ?>

    <div class="container py-2 flex-grow-1" style="max-width:1200px;">

        <div class="d-flex align-items-center justify-content-between mb-4 flex-wrap gap-2">
            <h4 class="fw-bold mb-0 ng-title">
                <i class="bi bi-file-earmark-text text-success"></i> Template Manager
            </h4>
            <button type="button" class="btn btn-ng-black btn-sm fw-semibold" data-bs-toggle="modal" data-bs-target="#addModal">
                <i class="bi bi-plus-lg me-1"></i> New Draft Template
            </button>
        </div>

        <?php if ($alert): ?>
            <div class="alert alert-<?= $alert['type'] ?> border-0 shadow-sm">
                <strong><?= $alert['title'] ?></strong>
                <div class="small mt-1"><?= $alert['message'] ?></div>
            </div>
        <?php endif; ?>

        <!-- Toolbar -->
        <div class="card card-ng mb-4">
            <div class="card-body p-3">
                <form method="get" action="templates" class="row g-2 align-items-end">
                    <div class="col-12 col-md-4">
                        <label class="form-label small fw-semibold mb-1">Business</label>
                        <select name="business_id" class="form-select">
                            <option value="">Select a Business</option>
                            <?php foreach ($activeBusinesses as $biz): ?>
                                <option value="<?= $biz['id'] ?>" <?= $businessFilter === (int)$biz['id'] ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($biz['name']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-6 col-md-2">
                        <label class="form-label small fw-semibold mb-1">Status</label>
                        <select name="status" class="form-select">
                            <option value="">All</option>
                            <option value="approved" <?= $statusFilter === 'approved' ? 'selected' : '' ?>>Approved</option>
                            <option value="pending" <?= $statusFilter === 'pending' ? 'selected' : '' ?>>Pending</option>
                            <option value="rejected" <?= $statusFilter === 'rejected' ? 'selected' : '' ?>>Rejected</option>
                            <option value="draft" <?= $statusFilter === 'draft' ? 'selected' : '' ?>>Draft</option>
                        </select>
                    </div>
                    <div class="col-6 col-md-3 d-flex gap-2">
                        <button type="submit" class="btn btn-ng-secondary flex-grow-1"><i class="bi bi-funnel-fill"></i></button>
                        <a href="templates" class="btn btn-ng-black"><i class="bi bi-x-lg"></i></a>
                    </div>
                    <div class="col-12 col-md-3">
                        <form method="post" action="templates">
                            <input type="hidden" name="action" value="sync">
                            <select name="business_id" class="form-select mb-2" required>
                                <option value="" <?= $businessFilter <= 0 ? 'selected' : '' ?>>Business to sync…</option>
                                <?php foreach ($activeBusinesses as $biz): ?>
                                    <option value="<?= $biz['id'] ?>" <?= $businessFilter === (int)$biz['id'] ? 'selected' : '' ?>>
                                        <?= htmlspecialchars($biz['name']) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                            <button type="submit" class="btn btn-ng-primary w-100 fw-semibold">
                                <i class="bi bi-arrow-repeat me-1"></i> Sync from Meta
                            </button>
                        </form>
                    </div>
                </form>
            </div>
        </div>

        <!-- Template list -->
        <div class="card card-ng">
            <div class="card-header-ng py-3 px-3">
                <span class="fw-semibold">
                    <?= $businessFilter > 0 ? count($templateRows) . ' template(s) for this business' : 'Select a business to view its templates' ?>
                </span>
            </div>

            <?php if ($businessFilter <= 0): ?>
                <div class="card-body text-center py-5 text-muted">
                    <i class="bi bi-file-earmark-text display-4 d-block mb-2"></i>
                    Choose a sender business above to list, sync, or manage its WhatsApp templates.
                </div>
            <?php elseif (empty($templateRows)): ?>
                <div class="card-body text-center py-5 text-muted">
                    <i class="bi bi-file-earmark-x display-4 d-block mb-2"></i>
                    No templates found for this business.
                    <div class="mt-2 small">Click <strong>Sync from Meta</strong> after your templates are approved in Meta Business Manager.</div>
                </div>
            <?php else: ?>
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0">
                        <thead class="table-light">
                            <tr>
                                <th>Name</th>
                                <th>Category</th>
                                <th>Language</th>
                                <th>Status</th>
                                <th>Body Preview</th>
                                <th>Vars</th>
                                <th class="text-end pe-3">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($templateRows as $t): ?>
                                <tr>
                                    <td>
                                        <div class="fw-semibold"><?= htmlspecialchars($t['name']) ?></div>
                                        <div class="small text-muted font-monospace"><?= htmlspecialchars($t['meta_template_id'] ?: 'no meta id') ?></div>
                                    </td>
                                    <td><span class="badge bg-light text-dark border text-uppercase"><?= htmlspecialchars($t['category']) ?></span></td>
                                    <td class="text-muted font-monospace"><?= htmlspecialchars($t['language']) ?></td>
                                    <td>
                                        <span class="badge bg-<?= $statusBadgeColors[$t['status']] ?? 'secondary' ?> text-white text-uppercase">
                                            <?= htmlspecialchars($t['status']) ?>
                                        </span>
                                    </td>
                                    <td class="small text-muted" style="max-width:280px;">
                                        <span class="d-inline-block text-truncate align-middle" style="max-width:260px;"><?= htmlspecialchars($t['body_text']) ?></span>
                                    </td>
                                    <td class="text-center"><?= (int)$t['variable_count'] ?></td>
                                    <td class="text-end pe-3">
                                        <form method="post" action="templates" class="d-inline" onsubmit="return confirm('Delete this template?');">
                                            <input type="hidden" name="action" value="delete">
                                            <input type="hidden" name="id" value="<?= (int)$t['id'] ?>">
                                            <button type="submit" class="btn btn-ng-secondary btn-sm" title="Delete"><i class="bi bi-trash"></i></button>
                                        </form>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>

    </div>

    <!-- New Template Modal -->
    <div class="modal fade" id="addModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <form method="post" action="templates">
                    <input type="hidden" name="action" value="create">
                    <div class="modal-header">
                        <h5 class="modal-title"><i class="bi bi-file-earmark-plus me-1 text-success"></i> New Draft Template</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body">
                        <div class="mb-3">
                            <label class="form-label small fw-semibold">Business *</label>
                            <select name="business_id" class="form-select" required>
                                <?php foreach ($activeBusinesses as $biz): ?>
                                    <option value="<?= $biz['id'] ?>"><?= htmlspecialchars($biz['name']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="mb-3">
                            <label class="form-label small fw-semibold">Template Name *</label>
                            <input type="text" name="name" class="form-control" placeholder="appointment_reminder" required>
                            <div class="form-text">Lowercase letters, numbers, and underscores.</div>
                        </div>
                        <div class="row g-2 mb-3">
                            <div class="col-6">
                                <label class="form-label small fw-semibold">Category</label>
                                <select name="category" class="form-select">
                                    <option value="utility">Utility</option>
                                    <option value="marketing">Marketing</option>
                                    <option value="authentication">Authentication</option>
                                </select>
                            </div>
                            <div class="col-6">
                                <label class="form-label small fw-semibold">Language</label>
                                <input type="text" name="language" class="form-control" value="en_US">
                            </div>
                        </div>
                        <div class="mb-3">
                            <label class="form-label small fw-semibold">Body Text *</label>
                            <textarea name="body_text" class="form-control" rows="4" placeholder="Hi {{1}}, this is a reminder about {{2}}." required></textarea>
                            <div class="form-text">Use {{1}}, {{2}} placeholders for variables.</div>
                        </div>
                        <div class="alert alert-warning py-2 small mb-0">
                            <i class="bi bi-info-circle me-1"></i>
                            Draft templates are stored locally. To send them, submit + approve in Meta Business Manager, then <strong>Sync from Meta</strong>.
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-ng-black" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-ng-secondary fw-semibold">Save Draft</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/js/bootstrap.bundle.min.js"></script>
</body>

</html>
