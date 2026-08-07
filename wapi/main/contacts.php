<?php

// contacts.php — Customer records + CSV contact sync

require_once __DIR__ . '/../_includes/functions.inc.php';
require_once __DIR__ . '/../_includes/business_functions.inc.php';
require_once __DIR__ . '/../_includes/customer_functions.inc.php';

$activeNav = 'contacts';

$activeBusinesses = get_active_businesses('active');

$alert = null;

// ── Handle POST actions ─────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'create') {
        $result = create_customer($_POST);
        $alert = $result['success']
            ? ['type' => 'success', 'title' => 'Contact Added', 'message' => 'Customer record created.']
            : ['type' => 'danger', 'title' => 'Add Failed', 'message' => htmlspecialchars($result['error'])];

    } elseif ($action === 'update') {
        $id = (int)($_POST['id'] ?? 0);
        $result = update_customer($id, $_POST);
        $alert = $result['success']
            ? ['type' => 'success', 'title' => 'Contact Updated', 'message' => 'Customer record updated.']
            : ['type' => 'danger', 'title' => 'Update Failed', 'message' => htmlspecialchars($result['error'])];

    } elseif ($action === 'delete') {
        $id = (int)($_POST['id'] ?? 0);
        $alert = delete_customer($id)
            ? ['type' => 'success', 'title' => 'Contact Deleted', 'message' => 'Customer record removed.']
            : ['type' => 'danger', 'title' => 'Delete Failed', 'message' => 'Could not delete that contact.'];

    } elseif ($action === 'import') {
        $businessId = (int)($_POST['business_id'] ?? 0);
        $rows = [];

        if ($businessId <= 0) {
            $alert = ['type' => 'danger', 'title' => 'Import Failed', 'message' => 'Select a sender business before importing.'];
        } elseif (!isset($_FILES['csv_file']) || $_FILES['csv_file']['error'] !== UPLOAD_ERR_OK) {
            $alert = ['type' => 'danger', 'title' => 'Import Failed', 'message' => 'Please upload a CSV file.'];
        } else {
            $path = $_FILES['csv_file']['tmp_name'];
            $handle = fopen($path, 'r');
            if (!$handle) {
                $alert = ['type' => 'danger', 'title' => 'Import Failed', 'message' => 'Could not read the uploaded file.'];
            } else {
                $header = fgetcsv($handle, escape: '\\');
                $cols = array_map('strtolower', array_map('trim', $header ?: []));

                $idxPhone = array_search('phone', $cols, true);
                $idxName  = array_search('name', $cols, true);
                $idxEmail = array_search('email', $cols, true);
                $idxTags  = array_search('tags', $cols, true);

                if ($idxPhone === false) {
                    $alert = ['type' => 'danger', 'title' => 'Import Failed', 'message' => 'CSV must include a "phone" column.'];
                    fclose($handle);
                } else {
                    while (($line = fgetcsv($handle, escape: '\\')) !== false) {
                        $rows[] = [
                            'phone' => $line[$idxPhone] ?? '',
                            'name'  => $idxName !== false ? ($line[$idxName] ?? '') : '',
                            'email' => $idxEmail !== false ? ($line[$idxEmail] ?? '') : '',
                            'tags'  => $idxTags !== false ? ($line[$idxTags] ?? '') : '',
                        ];
                    }
                    fclose($handle);

                    $result = importCustomersFromCsv($businessId, $rows);
                    $msg = "Imported <strong>{$result['imported']}</strong> contacts" .
                        ($result['skipped'] > 0 ? " ({$result['skipped']} skipped as duplicates/invalid)" : '') . '.';
                    if (!empty($result['errors'])) {
                        $msg .= '<ul class="small mt-1 mb-0"><li>' . implode('</li><li>', array_map('htmlspecialchars', array_slice($result['errors'], 0, 5))) . '</li></ul>';
                    }
                    $alert = ['type' => 'success', 'title' => 'Import Complete', 'message' => $msg];
                }
            }
        }
    }
}

// ── Filters ─────────────────────────────────────────────
$search        = trim($_GET['search'] ?? '');
$businessFilter = (int)($_GET['business_id'] ?? 0);
$page          = max(1, (int)($_GET['page'] ?? 1));
$perPage       = 20;

$filters = ['search' => $search, 'business_id' => $businessFilter];
$result  = get_customers($filters, $page, $perPage);
$customers    = $result['data'];
$total        = $result['total'];
$totalPages   = $result['totalPages'];
$queryError   = $result['error'];
$stats        = get_customer_stats();

// Editing record
$editing = null;
if (isset($_GET['edit'])) {
    $editing = get_customer_by_id((int)$_GET['edit']);
}

function buildQ(array $overrides = []): string
{
    $params = [
        'search'      => $_GET['search'] ?? '',
        'business_id' => $_GET['business_id'] ?? '',
        'page'        => $_GET['page'] ?? 1,
    ];
    $params = array_merge($params, $overrides);
    $params = array_filter($params, fn($v) => $v !== '' && $v !== null);
    return http_build_query($params);
}

?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Contacts | Netgrity WhatsApp API</title>

    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.5/font/bootstrap-icons.css" rel="stylesheet">
    <link href="../assets/css/app.css" rel="stylesheet">
</head>

<body class="bg-light min-vh-100 d-flex flex-column">

    <?php require __DIR__ . '/../_includes/sidebar.inc.php'; ?>

    <div class="container py-2 flex-grow-1" style="max-width:1200px;">

        <div class="mt-4 d-flex align-items-center justify-content-between mb-4 flex-wrap gap-2">
            <h4 class="fw-bold mb-0 ng-title">
                <i class="bi bi-people text-success"></i> Contacts &amp; Customers
            </h4>
            <div class="d-flex gap-2">
                <button type="button" class="btn btn-ng-black btn-sm fw-semibold" data-bs-toggle="modal" data-bs-target="#addModal">
                    <i class="bi bi-person-plus me-1"></i> Add Contact
                </button>
                <button type="button" class="btn btn-ng-secondary btn-sm fw-semibold" data-bs-toggle="modal" data-bs-target="#importModal">
                    <i class="bi bi-upload me-1"></i> Import CSV
                </button>
            </div>
        </div>

        <?php if ($alert): ?>
            <div class="alert alert-<?= $alert['type'] ?> border-0 shadow-sm">
                <strong><?= $alert['title'] ?></strong>
                <div class="small mt-1"><?= $alert['message'] ?></div>
            </div>
        <?php endif; ?>

        <?php if ($queryError): ?>
            <div class="alert alert-danger border-0 shadow-sm">
                Couldn't load contacts right now.<div class="small mt-1 font-monospace"><?= htmlspecialchars($queryError) ?></div>
            </div>
        <?php endif; ?>

        <!-- Stats -->
        <div class="row g-3 mb-4">
            <div class="col-6 col-md-3">
                <div class="card card-ng h-100 stat-card">
                    <div class="card-body">
                        <div class="text-muted small text-uppercase fw-semibold">Total Contacts</div>
                        <div class="display-6"><?= number_format($stats['total']) ?></div>
                    </div>
                </div>
            </div>
            <div class="col-6 col-md-3">
                <div class="card card-ng h-100 stat-card">
                    <div class="card-body">
                        <div class="text-muted small text-uppercase fw-semibold">With Email</div>
                        <div class="display-6"><?= number_format($stats['with_email']) ?></div>
                    </div>
                </div>
            </div>
            <div class="col-6 col-md-3">
                <div class="card card-ng h-100 stat-card">
                    <div class="card-body">
                        <div class="text-muted small text-uppercase fw-semibold">Active (7d)</div>
                        <div class="display-6"><?= number_format($stats['active_7d']) ?></div>
                    </div>
                </div>
            </div>
            <div class="col-6 col-md-3">
                <div class="card card-ng h-100 stat-card">
                    <div class="card-body">
                        <div class="text-muted small text-uppercase fw-semibold">Inbound Messages</div>
                        <div class="display-6"><?= number_format($stats['total_inbound']) ?></div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Filters -->
        <div class="card card-ng mb-4">
            <div class="card-body p-3">
                <form method="get" action="contacts" class="row g-2 align-items-end">
                    <div class="col-12 col-md-4">
                        <label class="form-label small fw-semibold mb-1">Search</label>
                        <input type="text" name="search" class="form-control" placeholder="Phone, name, email, or tags" value="<?= htmlspecialchars($search) ?>">
                    </div>
                    <div class="col-12 col-md-4">
                        <label class="form-label small fw-semibold mb-1">Business</label>
                        <select name="business_id" class="form-select">
                            <option value="">All Businesses</option>
                            <?php foreach ($activeBusinesses as $biz): ?>
                                <option value="<?= $biz['id'] ?>" <?= $businessFilter === (int)$biz['id'] ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($biz['name']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-6 col-md-4 d-flex gap-2">
                        <button type="submit" class="btn btn-ng-secondary flex-grow-1"><i class="bi bi-funnel-fill"></i></button>
                        <a href="contacts" class="btn btn-ng-black"><i class="bi bi-x-lg"></i></a>
                    </div>
                </form>
            </div>
        </div>

        <!-- Table -->
        <div class="card card-ng">
            <div class="card-header-ng py-3 px-3 d-flex justify-content-between align-items-center">
                <span class="fw-semibold"><?= number_format($total) ?> contact<?= $total === 1 ? '' : 's' ?></span>
                <a href="inbox" class="btn btn-ng-primary btn-sm"><i class="bi bi-chat-dots me-1"></i> Open Inbox</a>
            </div>

            <?php if (empty($customers)): ?>
                <div class="card-body text-center py-5 text-muted">
                    <i class="bi bi-people display-4 d-block mb-2"></i>
                    <?= ($search !== '' || $businessFilter > 0) ? 'No contacts match your filters.' : 'No contacts yet. Add one manually or import a CSV.' ?>
                </div>
            <?php else: ?>
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0">
                        <thead class="table-light">
                            <tr>
                                <th>Customer</th>
                                <th>Business</th>
                                <th>Email</th>
                                <th>Tags</th>
                                <th>Messages</th>
                                <th>Last Activity</th>
                                <th class="text-end pe-3">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($customers as $c): ?>
                                <tr>
                                    <td>
                                        <div class="fw-semibold"><?= htmlspecialchars($c['name'] ?: 'Unnamed') ?></div>
                                        <div class="small text-muted">+<?= htmlspecialchars($c['phone']) ?></div>
                                    </td>
                                    <td><span class="badge bg-light text-dark border"><?= htmlspecialchars($c['business_name'] ?? 'Sender') ?></span></td>
                                    <td class="text-muted"><?= htmlspecialchars($c['email'] ?: '—') ?></td>
                                    <td>
                                        <?php foreach (array_filter(array_map('trim', explode(',', (string)$c['tags']))) as $tag): ?>
                                            <span class="badge-ng-soft badge rounded-pill me-1" style="font-size:0.65rem;"><?= htmlspecialchars($tag) ?></span>
                                        <?php endforeach; ?>
                                    </td>
                                    <td class="fw-semibold"><?= number_format((int)$c['total_messages']) ?></td>
                                    <td class="small text-muted"><?= $c['last_message_at'] ? date('M j, Y g:i a', strtotime($c['last_message_at'])) : '—' ?></td>
                                    <td class="text-end pe-3">
                                        <div class="d-inline-flex gap-1">
                                            <a href="inbox?customer=<?= (int)$c['id'] ?>" class="btn btn-ng-primary btn-sm" title="Open inbox">
                                                <i class="bi bi-chat-dots"></i>
                                            </a>
                                            <a href="?<?= buildQ(['edit' => $c['id']]) ?>" class="btn btn-ng-black btn-sm" title="Edit">
                                                <i class="bi bi-pencil"></i>
                                            </a>
                                            <form method="post" action="contacts" class="d-inline" onsubmit="return confirm('Delete this contact?');">
                                                <input type="hidden" name="action" value="delete">
                                                <input type="hidden" name="id" value="<?= (int)$c['id'] ?>">
                                                <button type="submit" class="btn btn-ng-secondary btn-sm" title="Delete"><i class="bi bi-trash"></i></button>
                                            </form>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>

                <?php if ($totalPages > 1): ?>
                    <div class="card-footer bg-white py-3">
                        <nav>
                            <ul class="pagination pagination-sm justify-content-center mb-0">
                                <li class="page-item <?= $page <= 1 ? 'disabled' : '' ?>">
                                    <a class="page-link" href="?<?= buildQ(['page' => max(1, $page - 1)]) ?>">&laquo;</a>
                                </li>
                                <?php for ($p = 1; $p <= $totalPages; $p++): ?>
                                    <li class="page-item <?= $p === $page ? 'active' : '' ?>">
                                        <a class="page-link" href="?<?= buildQ(['page' => $p]) ?>"><?= $p ?></a>
                                    </li>
                                <?php endfor; ?>
                                <li class="page-item <?= $page >= $totalPages ? 'disabled' : '' ?>">
                                    <a class="page-link" href="?<?= buildQ(['page' => min($totalPages, $page + 1)]) ?>">&raquo;</a>
                                </li>
                            </ul>
                        </nav>
                    </div>
                <?php endif; ?>
            <?php endif; ?>
        </div>

    </div>

    <!-- Add Contact Modal -->
    <div class="modal fade" id="addModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <form method="post" action="contacts">
                    <input type="hidden" name="action" value="create">
                    <div class="modal-header">
                        <h5 class="modal-title"><i class="bi bi-person-plus me-1 text-success"></i> Add Contact</h5>
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
                            <label class="form-label small fw-semibold">Phone *</label>
                            <input type="text" name="phone" class="form-control" placeholder="e.g. 2349044313696" required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label small fw-semibold">Name</label>
                            <input type="text" name="name" class="form-control">
                        </div>
                        <div class="mb-3">
                            <label class="form-label small fw-semibold">Email</label>
                            <input type="email" name="email" class="form-control">
                        </div>
                        <div class="mb-3">
                            <label class="form-label small fw-semibold">Tags (comma separated)</label>
                            <input type="text" name="tags" class="form-control" placeholder="vip, repeat-customer">
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-ng-black" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-ng-secondary fw-semibold">Save Contact</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Import CSV Modal -->
    <div class="modal fade" id="importModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <form method="post" action="contacts" enctype="multipart/form-data">
                    <input type="hidden" name="action" value="import">
                    <div class="modal-header">
                        <h5 class="modal-title"><i class="bi bi-upload me-1 text-success"></i> Import Contacts (CSV)</h5>
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
                            <label class="form-label small fw-semibold">CSV File *</label>
                            <input type="file" name="csv_file" class="form-control" accept=".csv" required>
                            <div class="form-text">
                                Columns: <code>phone</code> (required), <code>name</code>, <code>email</code>, <code>tags</code>.
                                Duplicate phones for the same business are skipped.
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-ng-black" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-ng-secondary fw-semibold"><i class="bi bi-upload me-1"></i> Import</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <?php if ($editing): ?>
        <div class="modal fade show" id="editModal" tabindex="-1" aria-hidden="true" style="display:block;background:rgba(0,0,0,.5);">
            <div class="modal-dialog modal-dialog-centered">
                <div class="modal-content">
                    <form method="post" action="contacts">
                        <input type="hidden" name="action" value="update">
                        <input type="hidden" name="id" value="<?= (int)$editing['id'] ?>">
                        <div class="modal-header">
                            <h5 class="modal-title"><i class="bi bi-pencil me-1 text-success"></i> Edit Contact</h5>
                            <a href="contacts" class="btn-close" aria-label="Close"></a>
                        </div>
                        <div class="modal-body">
                            <div class="mb-3">
                                <label class="form-label small fw-semibold">Phone *</label>
                                <input type="text" name="phone" class="form-control" value="<?= htmlspecialchars($editing['phone']) ?>" required>
                            </div>
                            <div class="mb-3">
                                <label class="form-label small fw-semibold">Name</label>
                                <input type="text" name="name" class="form-control" value="<?= htmlspecialchars($editing['name'] ?? '') ?>">
                            </div>
                            <div class="mb-3">
                                <label class="form-label small fw-semibold">Email</label>
                                <input type="email" name="email" class="form-control" value="<?= htmlspecialchars($editing['email'] ?? '') ?>">
                            </div>
                            <div class="mb-3">
                                <label class="form-label small fw-semibold">Tags (comma separated)</label>
                                <input type="text" name="tags" class="form-control" value="<?= htmlspecialchars($editing['tags'] ?? '') ?>">
                            </div>
                            <div class="mb-3">
                                <label class="form-label small fw-semibold">Notes</label>
                                <textarea name="notes" class="form-control" rows="3"><?= htmlspecialchars($editing['notes'] ?? '') ?></textarea>
                            </div>
                        </div>
                        <div class="modal-footer">
                            <a href="contacts" class="btn btn-ng-black">Cancel</a>
                            <button type="submit" class="btn btn-ng-secondary fw-semibold">Save Changes</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    <?php endif; ?>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/js/bootstrap.bundle.min.js"></script>
</body>

</html>
