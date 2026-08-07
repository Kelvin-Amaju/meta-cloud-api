<?php

// inbox.php — WhatsApp Inbox (conversations + thread + reply)

require_once __DIR__ . '/../_includes/functions.inc.php';
require_once __DIR__ . '/../_includes/business_functions.inc.php';
require_once __DIR__ . '/../_includes/whatsapp_functions.inc.php';
require_once __DIR__ . '/../_includes/message_functions.inc.php';
require_once __DIR__ . '/../_includes/conversation_functions.inc.php';
require_once __DIR__ . '/../_includes/customer_functions.inc.php';

$activeNav = 'inbox';

$activeBusinesses = get_active_businesses('active');

$businessFilter = (int)($_GET['business_id'] ?? 0);
$statusFilter   = $_GET['status'] ?? '';
$search         = trim($_GET['search'] ?? '');
$page           = max(1, (int)($_GET['page'] ?? 1));
$perPage        = 20;

$selectedCustomerId = (int)($_GET['customer'] ?? ($_POST['customer_id'] ?? 0));

$alert = null;

// ── Handle POST actions ─────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    $bizId      = (int)($_POST['business_id'] ?? 0);
    $customerId = (int)($_POST['customer_id'] ?? 0);

    if ($action === 'reply') {
        $message = trim($_POST['message'] ?? '');

        if ($bizId <= 0 || $customerId <= 0 || $message === '') {
            $alert = ['type' => 'danger', 'title' => 'Cannot Send', 'message' => 'Business, conversation, and message body are required.'];
        } else {
            $business = get_business($bizId);
            $customer = get_customer_by_id($customerId);

            if (!$business) {
                $alert = ['type' => 'danger', 'title' => 'Sender Missing', 'message' => 'The selected sender business no longer exists.'];
            } elseif (!$customer) {
                $alert = ['type' => 'danger', 'title' => 'Conversation Missing', 'message' => 'The selected conversation no longer exists.'];
            } else {
                $response = whatsapp_send_text($customer['phone'], $message, $business);

                if (!empty($response['success'])) {
                    $wamid = $response['data']['messages'][0]['id'] ?? ('wamid.simulated_' . time());
                    save_outgoing_message($customer['phone'], $message, $wamid, 1, $bizId, 'sent', null, 'text');
                    mark_conversation_read($bizId, $customerId);
                    $alert = ['type' => 'success', 'title' => 'Reply Sent', 'message' => 'Your reply was delivered to Meta.'];
                } else {
                    $errorMessage = $response['error'] ?? $response['data']['error']['message'] ?? 'Unknown API error.';
                    save_outgoing_message($customer['phone'], $message, 'wamid.failed_' . time(), 1, $bizId, 'failed', $errorMessage, 'text');
                    $alert = ['type' => 'danger', 'title' => 'Reply Failed', 'message' => htmlspecialchars($errorMessage)];
                }

                $selectedCustomerId = $customerId;
            }
        }

    } elseif ($action === 'toggle_status') {
        $newStatus = $_POST['new_status'] ?? 'open';
        if ($bizId > 0 && $customerId > 0) {
            set_conversation_status($bizId, $customerId, $newStatus);
        }
    }
}

// ── Build filters & load conversations ──────────────────
$filters = [
    'business_id' => $businessFilter,
    'status'      => $statusFilter,
    'search'      => $search,
];

$result   = get_conversations($filters, $page, $perPage);
$conversations = $result['data'];
$total         = $result['total'];
$totalPages    = $result['totalPages'];

// Resolve the selected conversation + thread
$selectedConversation = null;
$thread               = [];

if ($selectedCustomerId > 0) {
    $selectedConversation = get_conversation_by_id($selectedCustomerId);
    if (!$selectedConversation) {
        $selectedConversation = null;
        $selectedCustomerId = 0;
    }
}

if ($selectedConversation) {
    $selectedCustomerId = (int)$selectedConversation['customer_id'];
    $thread = getThreadMessages((int)$selectedConversation['business_id'], $selectedConversation['phone'], 100);
    mark_conversation_read((int)$selectedConversation['business_id'], (int)$selectedConversation['customer_id']);
}

function buildQ(array $overrides = []): string
{
    $params = [
        'business_id' => $_GET['business_id'] ?? '',
        'status'      => $_GET['status'] ?? '',
        'search'      => $_GET['search'] ?? '',
        'page'        => $_GET['page'] ?? 1,
    ];
    $params = array_merge($params, $overrides);
    $params = array_filter($params, fn($v) => $v !== '' && $v !== null);
    return http_build_query($params);
}

function timeAgo(string $datetime): string
{
    $ts = strtotime($datetime);
    if ($ts === false) return $datetime;
    $diff = time() - $ts;
    if ($diff < 60) return 'just now';
    if ($diff < 3600) return floor($diff / 60) . 'm ago';
    if ($diff < 86400) return floor($diff / 3600) . 'h ago';
    if ($diff < 604800) return floor($diff / 86400) . 'd ago';
    return date('M j, Y', $ts);
}

?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Inbox - Netgrity WhatsApp API</title>

    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.5/font/bootstrap-icons.css" rel="stylesheet">
    <link href="../assets/css/app.css" rel="stylesheet">
</head>

<body class="bg-light min-vh-100 d-flex flex-column">

    <?php require __DIR__ . '/../_includes/sidebar.inc.php'; ?>

    <div class="container py-2 flex-grow-1" style="max-width:1200px;">

        <div class="d-flex align-items-center justify-content-between mb-4 flex-wrap gap-2">
            <h4 class="fw-bold mb-0 ng-title">
                <i class="bi bi-inbox text-success"></i> WhatsApp Inbox
            </h4>
            <div class="d-flex gap-2">
                <a href="contacts" class="btn btn-ng-primary btn-sm fw-semibold">
                    <i class="bi bi-people me-1"></i> Contacts
                </a>
                <a href="send" class="btn btn-ng-secondary btn-sm fw-semibold">
                    <i class="bi bi-send me-1"></i> New Message
                </a>
            </div>
        </div>

        <?php if ($alert): ?>
            <div class="alert alert-<?= $alert['type'] ?> border-0 shadow-sm">
                <strong><?= $alert['title'] ?></strong>
                <div class="small mt-1"><?= $alert['message'] ?></div>
            </div>
        <?php endif; ?>

        <!-- Filters -->
        <div class="card card-ng mb-4">
            <div class="card-body p-3">
                <form method="get" action="inbox" class="row g-2 align-items-end">
                    <div class="col-12 col-md-3">
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
                    <div class="col-6 col-md-2">
                        <label class="form-label small fw-semibold mb-1">Status</label>
                        <select name="status" class="form-select">
                            <option value="">All</option>
                            <option value="open" <?= $statusFilter === 'open' ? 'selected' : '' ?>>Open</option>
                            <option value="closed" <?= $statusFilter === 'closed' ? 'selected' : '' ?>>Closed</option>
                        </select>
                    </div>
                    <div class="col-12 col-md-5">
                        <label class="form-label small fw-semibold mb-1">Search</label>
                        <input type="text" name="search" class="form-control" placeholder="Phone, name, or message preview" value="<?= htmlspecialchars($search) ?>">
                    </div>
                    <div class="col-6 col-md-2 d-flex gap-2">
                        <button type="submit" class="btn btn-ng-secondary flex-grow-1"><i class="bi bi-funnel-fill"></i></button>
                        <a href="inbox" class="btn btn-ng-black"><i class="bi bi-x-lg"></i></a>
                    </div>
                </form>
            </div>
        </div>

        <div class="row g-4">
            <!-- Conversation list -->
            <div class="col-lg-5">
                <div class="card card-ng">
                    <div class="card-header-ng py-3 px-3 d-flex justify-content-between align-items-center">
                        <span class="fw-semibold"><?= number_format($total) ?> conversations</span>
                        <span class="badge-ng-soft badge rounded-pill"><i class="bi bi-envelope me-1"></i>Inbox</span>
                    </div>

                    <?php if (empty($conversations)): ?>
                        <div class="card-body text-center py-5 text-muted">
                            <i class="bi bi-inbox display-4 d-block mb-2"></i>
                            <?php if ($search !== '' || $statusFilter !== '' || $businessFilter > 0): ?>
                                No conversations match your filters.
                            <?php else: ?>
                                No conversations yet. Inbound messages will appear here.
                            <?php endif; ?>
                        </div>
                    <?php else: ?>
                        <div class="list-group list-group-flush">
                            <?php foreach ($conversations as $cv):
                                $isSelected = $selectedConversation && (int)$selectedConversation['id'] === (int)$cv['id'];
                            ?>
                                <a href="?<?= buildQ(['customer' => $cv['id'], 'page' => 1]) ?>"
                                   class="list-group-item list-group-item-action p-3 <?= $isSelected ? 'active' : '' ?>"
                                   style="<?= $isSelected ? 'background-color:#ff6b00;border-color:#ff6b00;' : '' ?>">
                                    <div class="d-flex justify-content-between align-items-center">
                                        <div class="d-flex align-items-center gap-2">
                                            <?php if ($cv['unread_count'] > 0): ?>
                                                <span class="badge bg-danger rounded-pill"><?= $cv['unread_count'] ?></span>
                                            <?php endif; ?>
                                            <span class="fw-semibold">
                                                <?= htmlspecialchars($cv['customer_name'] ?: ('+' . $cv['phone'])) ?>
                                            </span>
                                        </div>
                                        <small class="text-muted <?= $isSelected ? 'opacity-75' : '' ?>"><?= timeAgo($cv['last_message_at']) ?></small>
                                    </div>
                                    <div class="small mt-1 <?= $isSelected ? 'opacity-75' : 'text-muted' ?> text-truncate">
                                        <?php if ($cv['last_direction'] === 'inbound'): ?>
                                            <i class="bi bi-arrow-down-left me-1"></i>
                                        <?php else: ?>
                                            <i class="bi bi-arrow-up-right me-1"></i>
                                        <?php endif; ?>
                                        <?= htmlspecialchars($cv['last_message_preview'] ?: '—') ?>
                                    </div>
                                    <div class="small <?= $isSelected ? 'opacity-75' : '' ?>">
                                        <span class="badge bg-light text-dark border"><?= htmlspecialchars($cv['business_name'] ?? 'Sender') ?></span>
                                        <?php if ($cv['status'] === 'closed'): ?>
                                            <span class="badge bg-secondary text-white">closed</span>
                                        <?php endif; ?>
                                    </div>
                                </a>
                            <?php endforeach; ?>
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

            <!-- Thread panel -->
            <div class="col-lg-7">
                <?php if (!$selectedConversation): ?>
                    <div class="card card-ng h-100">
                        <div class="card-body text-center py-5 text-muted">
                            <i class="bi bi-chat-dots display-4 d-block mb-3"></i>
                            <h5 class="ng-title">Select a conversation</h5>
                            <p class="small">Choose a conversation from the left to read the thread and reply.</p>
                        </div>
                    </div>
                <?php else: ?>
                    <div class="card card-ng">
                        <div class="card-header-ng py-3 px-3">
                            <div class="d-flex justify-content-between align-items-start flex-wrap gap-2">
                                <div>
                                    <h5 class="fw-bold mb-0 ng-title">
                                        <?= htmlspecialchars($selectedConversation['customer_name'] ?: ('+' . $selectedConversation['phone'])) ?>
                                    </h5>
                                    <small class="text-muted">
                                        +<?= htmlspecialchars($selectedConversation['phone']) ?>
                                        <?php if ($selectedConversation['email']): ?> &middot; <?= htmlspecialchars($selectedConversation['email']) ?><?php endif; ?>
                                        &middot; <span class="badge bg-light text-dark border"><?= htmlspecialchars($selectedConversation['business_name'] ?? 'Sender') ?></span>
                                    </small>
                                </div>
                                <form method="post" action="inbox" class="d-inline">
                                    <input type="hidden" name="action" value="toggle_status">
                                    <input type="hidden" name="business_id" value="<?= $selectedConversation['business_id'] ?>">
                                    <input type="hidden" name="customer_id" value="<?= $selectedConversation['customer_id'] ?>">
                                    <input type="hidden" name="new_status" value="<?= $selectedConversation['status'] === 'closed' ? 'open' : 'closed' ?>">
                                    <button type="submit" class="btn btn-ng-outline btn-sm">
                                        <i class="bi <?= $selectedConversation['status'] === 'closed' ? 'bi-check2-circle' : 'bi-lock' ?> me-1"></i>
                                        <?= $selectedConversation['status'] === 'closed' ? 'Reopen' : 'Close' ?>
                                    </button>
                                </form>
                            </div>
                        </div>

                        <div class="card-body" style="height:420px;overflow-y:auto;background:#f8f9fa;">
                            <?php if (empty($thread)): ?>
                                <div class="text-center text-muted py-5">
                                    <i class="bi bi-chat-left-text display-5 d-block mb-2"></i>
                                    No messages in this thread yet.
                                </div>
                            <?php else: ?>
                                <?php foreach ($thread as $msg): ?>
                                    <?php $isInbound = $msg['direction'] === 'inbound'; ?>
                                    <div class="d-flex mb-2 <?= $isInbound ? '' : 'justify-content-end' ?>">
                                        <div class="<?= $isInbound ? 'bg-white' : 'text-white' ?> rounded-3 px-3 py-2 shadow-sm"
                                             style="max-width:75%;<?= $isInbound ? 'border:1px solid #eee;' : 'background-color:#ff6b00;' ?>">
                                            <div class="small" style="white-space:pre-wrap;"><?= htmlspecialchars($msg['body'] ?: ('[' . ($msg['message_type'] ?: 'media') . ' message]')) ?></div>
                                            <?php if (!empty($msg['media_url'])): ?>
                                                <a href="<?= htmlspecialchars($msg['media_url']) ?>" target="_blank" rel="noopener"
                                                   class="small d-block text-break <?= $isInbound ? '' : 'text-white' ?>">
                                                    <i class="bi bi-paperclip me-1"></i><?= htmlspecialchars($msg['media_type'] ?: 'media') ?>
                                                </a>
                                            <?php endif; ?>
                                            <div class="small mt-1 <?= $isInbound ? 'text-muted' : 'opacity-75' ?>">
                                                <?= date('M j, g:i a', strtotime($msg['created_at'])) ?>
                                            </div>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </div>

                        <div class="card-footer bg-white py-3">
                            <form method="post" action="inbox">
                                <input type="hidden" name="action" value="reply">
                                <input type="hidden" name="business_id" value="<?= $selectedConversation['business_id'] ?>">
                                <input type="hidden" name="customer_id" value="<?= $selectedConversation['customer_id'] ?>">
                                <div class="input-group">
                                    <input type="text" name="message" class="form-control" placeholder="Type a reply… (works inside the 24h window)" required>
                                    <button type="submit" class="btn btn-ng-secondary fw-semibold">
                                        <i class="bi bi-send me-1"></i> Reply
                                    </button>
                                </div>
                            </form>
                        </div>
                    </div>
                <?php endif; ?>
            </div>
        </div>

    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/js/bootstrap.bundle.min.js"></script>
</body>

</html>
