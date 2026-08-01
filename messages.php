<?php

// messages.php

require_once 'includes/messages.php';

// ---- Read & sanitize filters from query string ----
$search   = trim($_GET['search'] ?? '');
$reply    = $_GET['reply'] ?? '';          // '', '1', '0'
$dateFrom = trim($_GET['date_from'] ?? '');
$dateTo   = trim($_GET['date_to'] ?? '');
$page     = max(1, (int)($_GET['page'] ?? 1));
$perPage  = 20;

// Validate reply filter value strictly
if (!in_array($reply, ['', '0', '1'], true)) {
    $reply = '';
}

// Validate dates strictly (Y-m-d), silently drop if malformed
foreach (['dateFrom' => &$dateFrom, 'dateTo' => &$dateTo] as $var => &$val) {
    if ($val !== '' && !preg_match('/^\d{4}-\d{2}-\d{2}$/', $val)) {
        $val = '';
    }
}
unset($val);

// Guard against inverted date range
if ($dateFrom !== '' && $dateTo !== '' && $dateFrom > $dateTo) {
    [$dateFrom, $dateTo] = [$dateTo, $dateFrom];
}

$filters = [
    'search'    => $search,
    'reply'     => $reply,
    'date_from' => $dateFrom,
    'date_to'   => $dateTo,
];

$result = getMessages($filters, $page, $perPage);
$stats  = getMessageStats();

$messages   = $result['data'];
$total      = $result['total'];
$totalPages = $result['totalPages'];
$queryError = $result['error'];

// If the requested page is beyond the last page (e.g. filters changed), clamp and note it
$pageOutOfRange = ($totalPages > 0 && $page > $totalPages);

/**
 * Build a query string for pagination/sort links, preserving current filters
 * and overriding specific params.
 */
function buildQueryString(array $overrides = []): string
{
    $params = [
        'search'    => $_GET['search'] ?? '',
        'reply'     => $_GET['reply'] ?? '',
        'date_from' => $_GET['date_from'] ?? '',
        'date_to'   => $_GET['date_to'] ?? '',
        'page'      => $_GET['page'] ?? 1,
    ];

    $params = array_merge($params, $overrides);

    // Drop empty values for a cleaner URL
    $params = array_filter($params, fn($v) => $v !== '' && $v !== null);

    return http_build_query($params);
}

function timeAgo(string $datetime): string
{
    $ts  = strtotime($datetime);
    if ($ts === false) {
        return $datetime;
    }

    $diff = time() - $ts;

    if ($diff < 60) {
        return 'just now';
    }
    if ($diff < 3600) {
        $m = (int)floor($diff / 60);
        return $m . 'm ago';
    }
    if ($diff < 86400) {
        $h = (int)floor($diff / 3600);
        return $h . 'h ago';
    }
    if ($diff < 604800) {
        $d = (int)floor($diff / 86400);
        return $d . 'd ago';
    }

    return date('M j, Y', $ts);
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Messages - Netgrity WhatsApp API</title>

    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.2.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.5/font/bootstrap-icons.css" rel="stylesheet">

    <style>
        .msg-preview {
            max-width: 360px;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }

        .table-hover tbody tr {
            cursor: pointer;
        }

        .stat-card .display-6 {
            font-weight: 700;
        }

        @media (max-width: 767.98px) {
            .msg-preview {
                max-width: 160px;
            }
        }
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
                <a href="send" class="btn btn-success btn-sm">
                    <i class="bi bi-send me-1"></i> Send Message
                </a>
                <a href="index" class="btn btn-outline-light btn-sm">
                    <i class="bi bi-arrow-left me-1"></i> Dashboard
                </a>
            </div>
        </div>
    </nav>

    <div class="container py-2 flex-grow-1" style="max-width:1100px;">

        <div class="d-flex align-items-center justify-content-between mb-4 flex-wrap gap-2">
            <h4 class="fw-bold mb-0">
                <i class="bi bi-chat-left-text text-success"></i>
                Message History
            </h4>
        </div>

        <?php if ($queryError || $stats['error']): ?>
            <div class="alert alert-danger border-0 shadow-sm">
                <i class="bi bi-exclamation-octagon-fill me-1"></i>
                Couldn't load message data right now.
                <div class="small mt-1 font-monospace">
                    <?= htmlspecialchars($queryError ?: $stats['error']) ?>
                </div>
            </div>
        <?php endif; ?>

        <!-- Stats -->
        <div class="row g-3 mb-4">
            <div class="col-6 col-md-3">
                <div class="card border-0 shadow-sm h-100 stat-card">
                    <div class="card-body">
                        <div class="text-muted small text-uppercase fw-semibold">Total Sent</div>
                        <div class="display-6"><?= number_format($stats['total']) ?></div>
                    </div>
                </div>
            </div>
            <div class="col-6 col-md-3">
                <div class="card border-0 shadow-sm h-100 stat-card">
                    <div class="card-body">
                        <div class="text-muted small text-uppercase fw-semibold">Today</div>
                        <div class="display-6"><?= number_format($stats['today']) ?></div>
                    </div>
                </div>
            </div>
            <div class="col-6 col-md-3">
                <div class="card border-0 shadow-sm h-100 stat-card">
                    <div class="card-body">
                        <div class="text-muted small text-uppercase fw-semibold">Two-Way</div>
                        <div class="display-6 text-success"><?= number_format($stats['twoWay']) ?></div>
                    </div>
                </div>
            </div>
            <div class="col-6 col-md-3">
                <div class="card border-0 shadow-sm h-100 stat-card">
                    <div class="card-body">
                        <div class="text-muted small text-uppercase fw-semibold">One-Way</div>
                        <div class="display-6 text-warning"><?= number_format($stats['oneWay']) ?></div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Filters -->
        <div class="card border-0 shadow-sm mb-4">
            <div class="card-body p-3">
                <form method="get" action="messages" class="row g-2 align-items-end">

                    <div class="col-12 col-md-4">
                        <label class="form-label small fw-semibold mb-1">Search</label>
                        <div class="input-group">
                            <span class="input-group-text"><i class="bi bi-search"></i></span>
                            <input
                                type="text"
                                name="search"
                                class="form-control"
                                placeholder="Phone or message content"
                                value="<?= htmlspecialchars($search) ?>">
                        </div>
                    </div>

                    <div class="col-6 col-md-2">
                        <label class="form-label small fw-semibold mb-1">Type</label>
                        <select name="reply" class="form-select">
                            <option value="" <?= $reply === '' ? 'selected' : '' ?>>All</option>
                            <option value="1" <?= $reply === '1' ? 'selected' : '' ?>>Two-Way</option>
                            <option value="0" <?= $reply === '0' ? 'selected' : '' ?>>One-Way</option>
                        </select>
                    </div>

                    <div class="col-6 col-md-2">
                        <label class="form-label small fw-semibold mb-1">From</label>
                        <input type="date" name="date_from" class="form-control" value="<?= htmlspecialchars($dateFrom) ?>">
                    </div>

                    <div class="col-6 col-md-2">
                        <label class="form-label small fw-semibold mb-1">To</label>
                        <input type="date" name="date_to" class="form-control" value="<?= htmlspecialchars($dateTo) ?>">
                    </div>

                    <div class="col-6 col-md-2 d-flex gap-2">
                        <button type="submit" class="btn btn-success flex-grow-1">
                            <i class="bi bi-funnel-fill"></i>
                        </button>
                        <a href="messages" class="btn btn-outline-secondary" title="Clear filters">
                            <i class="bi bi-x-lg"></i>
                        </a>
                    </div>

                </form>
            </div>
        </div>

        <!-- Results -->
        <div class="card border-0 shadow-sm">

            <div class="card-header bg-white border-0 py-3 d-flex justify-content-between align-items-center flex-wrap gap-2">
                <span class="fw-semibold">
                    <?= number_format($total) ?> message<?= $total === 1 ? '' : 's' ?> found
                </span>
                <?php if ($total > 0): ?>
                    <span class="text-muted small">
                        Page <?= $pageOutOfRange ? $totalPages : $page ?> of <?= $totalPages ?>
                    </span>
                <?php endif; ?>
            </div>

            <?php if ($queryError): ?>

                <!-- Query failed: card stays empty, error already shown above -->

            <?php elseif (empty($messages)): ?>

                <div class="card-body text-center py-5 text-muted">
                    <i class="bi bi-inbox display-4 d-block mb-2"></i>
                    <?php if ($search !== '' || $reply !== '' || $dateFrom !== '' || $dateTo !== ''): ?>
                        No messages match your filters.
                        <div class="mt-2">
                            <a href="messages" class="btn btn-sm btn-outline-secondary">Clear filters</a>
                        </div>
                    <?php else: ?>
                        No messages sent yet.
                        <div class="mt-2">
                            <a href="send" class="btn btn-sm btn-success">Send your first message</a>
                        </div>
                    <?php endif; ?>
                </div>

            <?php else: ?>

                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0">
                        <thead class="table-light">
                            <tr>
                                <th>Sender Business</th>
                                <th>Recipient Phone</th>
                                <th>Message</th>
                                <th>Type</th>
                                <th>Sent</th>
                                <th class="text-end pe-3">Wamid</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($messages as $m): ?>
                                <tr
                                    data-bs-toggle="modal"
                                    data-bs-target="#messageModal"
                                    data-phone="<?= htmlspecialchars($m['phone']) ?>"
                                    data-message="<?= htmlspecialchars($m['message']) ?>"
                                    data-wamid="<?= htmlspecialchars($m['wamid']) ?>"
                                    data-allow-reply="<?= (int)$m['allow_reply'] ?>"
                                    data-created="<?= htmlspecialchars($m['created_at']) ?>">
                                    <td>
                                        <div class="d-flex flex-column align-items-start gap-1">
                                            <span class="badge bg-light text-dark border font-monospace">
                                                <i class="bi bi-building text-primary me-1"></i><?= htmlspecialchars($m['business_name'] ?? ($m['tenant_name'] ?? 'Default Sender')) ?>
                                            </span>
                                            <?php if (!empty($m['product_line'])): ?>
                                                <span class="badge bg-secondary-subtle text-secondary border px-1" style="font-size: 0.65rem;"><?= htmlspecialchars(strtoupper($m['product_line'])) ?></span>
                                            <?php endif; ?>
                                        </div>
                                    </td>
                                    <td class="fw-semibold">+<?= htmlspecialchars($m['phone']) ?></td>
                                    <td class="msg-preview text-muted" title="<?= htmlspecialchars($m['message']) ?>">
                                        <?= htmlspecialchars($m['message']) ?>
                                    </td>
                                    <td>
                                        <?php if ((int)$m['allow_reply'] === 1): ?>
                                            <span class="badge bg-success text-white border border-success">Two-Way</span>
                                        <?php else: ?>
                                            <span class="badge bg-warning text-white border border-warning">One-Way</span>
                                        <?php endif; ?>
                                    </td>
                                    <td class="text-muted small" title="<?= htmlspecialchars($m['created_at']) ?>">
                                        <?= htmlspecialchars(timeAgo($m['created_at'])) ?>
                                    </td>
                                    <td class="text-end pe-3">
                                        <code class="small text-muted"><?= htmlspecialchars(substr($m['wamid'], 0, 14)) ?><?= strlen($m['wamid']) > 14 ? '…' : '' ?></code>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>

            <?php endif; ?>

            <?php if ($totalPages > 1): ?>
                <div class="card-footer bg-white border-0 py-3">
                    <nav aria-label="Message pagination">
                        <ul class="pagination pagination-sm justify-content-center mb-0">

                            <li class="page-item <?= $page <= 1 ? 'disabled' : '' ?>">
                                <a class="page-link" href="?<?= buildQueryString(['page' => max(1, $page - 1)]) ?>">
                                    <i class="bi bi-chevron-left"></i>
                                </a>
                            </li>

                            <?php
                            // Show a bounded window of page links around the current page
                            $windowStart = max(1, $page - 2);
                            $windowEnd   = min($totalPages, $page + 2);
                            ?>

                            <?php if ($windowStart > 1): ?>
                                <li class="page-item"><a class="page-link" href="?<?= buildQueryString(['page' => 1]) ?>">1</a></li>
                                <?php if ($windowStart > 2): ?>
                                    <li class="page-item disabled"><span class="page-link">…</span></li>
                                <?php endif; ?>
                            <?php endif; ?>

                            <?php for ($p = $windowStart; $p <= $windowEnd; $p++): ?>
                                <li class="page-item <?= $p === $page ? 'active' : '' ?>">
                                    <a class="page-link" href="?<?= buildQueryString(['page' => $p]) ?>"><?= $p ?></a>
                                </li>
                            <?php endfor; ?>

                            <?php if ($windowEnd < $totalPages): ?>
                                <?php if ($windowEnd < $totalPages - 1): ?>
                                    <li class="page-item disabled"><span class="page-link">…</span></li>
                                <?php endif; ?>
                                <li class="page-item"><a class="page-link" href="?<?= buildQueryString(['page' => $totalPages]) ?>"><?= $totalPages ?></a></li>
                            <?php endif; ?>

                            <li class="page-item <?= $page >= $totalPages ? 'disabled' : '' ?>">
                                <a class="page-link" href="?<?= buildQueryString(['page' => min($totalPages, $page + 1)]) ?>">
                                    <i class="bi bi-chevron-right"></i>
                                </a>
                            </li>

                        </ul>
                    </nav>
                </div>
            <?php endif; ?>

        </div>

    </div>

    <!-- Message Detail Modal -->
    <div class="modal fade" id="messageModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">
                        <i class="bi bi-chat-left-text text-success me-1"></i>
                        Message Details
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">

                    <div class="mb-3">
                        <div class="text-muted small text-uppercase fw-semibold">Recipient</div>
                        <div class="fs-5 fw-semibold" id="modalPhone"></div>
                    </div>

                    <div class="mb-3">
                        <div class="text-muted small text-uppercase fw-semibold">Message</div>
                        <div class="border rounded p-2 bg-light" id="modalMessage" style="white-space: pre-wrap;"></div>
                    </div>

                    <div class="row g-3">
                        <div class="col-6">
                            <div class="text-muted small text-uppercase fw-semibold">Type</div>
                            <div id="modalType"></div>
                        </div>
                        <div class="col-6">
                            <div class="text-muted small text-uppercase fw-semibold">Sent At</div>
                            <div id="modalCreated" class="small"></div>
                        </div>
                    </div>

                    <div class="mt-3">
                        <div class="text-muted small text-uppercase fw-semibold">Message ID</div>
                        <code class="small d-block text-break" id="modalWamid"></code>
                    </div>

                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.2.3/dist/js/bootstrap.bundle.min.js"></script>

    <script>
        document.querySelectorAll('tbody tr[data-phone]').forEach(function (row) {
            row.addEventListener('click', function () {
                document.getElementById('modalPhone').textContent = '+' + row.dataset.phone;
                document.getElementById('modalMessage').textContent = row.dataset.message;
                document.getElementById('modalWamid').textContent = row.dataset.wamid;
                document.getElementById('modalCreated').textContent = row.dataset.created;

                var isTwoWay = row.dataset.allowReply === '1';
                document.getElementById('modalType').innerHTML = isTwoWay
                    ? '<span class="badge bg-success-subtle text-success border border-success-subtle">Two-Way</span>'
                    : '<span class="badge bg-warning text-white">One-Way</span>';
            });
        });
    </script>

</body>

</html>