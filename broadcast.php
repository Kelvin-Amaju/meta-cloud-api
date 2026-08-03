<?php

// broadcast.php — Broadcast campaigns (create + CSV recipients + send now)

require_once 'includes/init.php';
require_once 'includes/broadcasts.php';
require_once 'includes/templates.php';
require_once 'includes/messages.php';

$activeNav = 'broadcast';

$activeBusinesses = getActiveBusinesses('active');

$alert = null;

// ── Handle POST actions ─────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'create') {
        $businessId = (int)($_POST['business_id'] ?? 0);
        $payloadType = $_POST['payload_type'] ?? 'template';

        // Parse recipient list from the CSV upload
        $phones = [];
        if (isset($_FILES['recipient_file']) && $_FILES['recipient_file']['error'] === UPLOAD_ERR_OK) {
            $handle = fopen($_FILES['recipient_file']['tmp_name'], 'r');
            if ($handle) {
                while (($line = fgetcsv($handle)) !== false) {
                    foreach ($line as $cell) {
                        $phone = preg_replace('/[^0-9]/', '', (string)$cell);
                        if ($phone !== '' && !in_array($phone, $phones, true)) {
                            $phones[] = $phone;
                        }
                    }
                }
                fclose($handle);
            }
        }

        if (empty($phones)) {
            $alert = ['type' => 'danger', 'title' => 'No Recipients', 'message' => 'Upload a CSV file containing recipient phone numbers (one per row).'];
        } else {
            $result = createCampaign([
                'business_id'      => $businessId,
                'campaign_name'    => $_POST['campaign_name'] ?? '',
                'payload_type'     => $payloadType,
                'template_name'    => $_POST['template_name'] ?? '',
                'message_body'     => $_POST['message_body'] ?? '',
                'media_url'        => $_POST['media_url'] ?? '',
                'media_type'       => $_POST['media_type'] ?? 'image',
                'recipient_file'   => $_FILES['recipient_file']['name'] ?? '',
                'total_recipients' => count($phones),
            ]);

            if (!$result['success']) {
                $alert = ['type' => 'danger', 'title' => 'Campaign Not Created', 'message' => htmlspecialchars($result['error'])];
            } else {
                $campaignId = (int)$result['id'];
                $saved = 0;
                foreach ($phones as $phone) {
                    if (saveCampaignRecipient($campaignId, $phone)) {
                        $saved++;
                    }
                }
                $alert = ['type' => 'success', 'title' => 'Campaign Created', 'message' => "Campaign created with <strong>{$saved}</strong> recipient(s). Use <em>Send Now</em> to dispatch."];
            }
        }

    } elseif ($action === 'send') {
        $campaignId = (int)($_POST['campaign_id'] ?? 0);
        $result = runCampaign($campaignId);
        if (!$result['success']) {
            $alert = ['type' => 'danger', 'title' => 'Send Failed', 'message' => htmlspecialchars($result['error'])];
        } else {
            $alert = ['type' => 'success', 'title' => 'Campaign Sent', 'message' => "Sent <strong>{$result['sent']}</strong> message(s) successfully; <strong>{$result['failed']}</strong> failed."];
        }
    }
}

// ── Load data ───────────────────────────────────────────
$page    = max(1, (int)($_GET['page'] ?? 1));
$perPage = 20;

$campaigns = getCampaigns($page, $perPage);
$rows       = $campaigns['data'];
$total      = $campaigns['total'];
$totalPages = $campaigns['totalPages'];

$recipientCounts = [];
foreach ($rows as $c) {
    $recipientCounts[$c['id']] = getCampaignRecipientCounts((int)$c['id']);
}

// Approved templates per business for the create form
$templatesByBusiness = [];
foreach ($activeBusinesses as $biz) {
    $templatesByBusiness[$biz['id']] = getTemplatesForBusiness($biz['id']);
}

$statusBadgeColors = [
    'draft'     => 'secondary',
    'queued'    => 'secondary',
    'running'   => 'info',
    'completed' => 'success',
    'failed'    => 'danger',
];

?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Broadcast - Netgrity WhatsApp API</title>

    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.5/font/bootstrap-icons.css" rel="stylesheet">
    <link href="assets/css/app.css" rel="stylesheet">
</head>

<body class="bg-light min-vh-100 d-flex flex-column">

    <?php require __DIR__ . '/includes/partials/navbar.php'; ?>

    <div class="container py-2 flex-grow-1" style="max-width:1200px;">

        <div class="d-flex align-items-center justify-content-between mb-4 flex-wrap gap-2">
            <h4 class="fw-bold mb-0 ng-title">
                <i class="bi bi-megaphone text-success"></i> Broadcast Campaigns
            </h4>
            <button type="button" class="btn btn-ng-secondary btn-sm fw-semibold" data-bs-toggle="modal" data-bs-target="#createModal">
                <i class="bi bi-plus-lg me-1"></i> New Campaign
            </button>
        </div>

        <?php if ($alert): ?>
            <div class="alert alert-<?= $alert['type'] ?> border-0 shadow-sm">
                <strong><?= $alert['title'] ?></strong>
                <div class="small mt-1"><?= $alert['message'] ?></div>
            </div>
        <?php endif; ?>

        <div class="alert alert-warning border-0 shadow-sm small">
            <i class="bi bi-lightning-charge-fill me-1"></i>
            Campaigns send <strong>synchronously</strong> in this request and must use <strong>approved templates</strong> (or free-form within the 24h customer-service window). A 200&nbsp;ms pause is applied between sends to stay within Meta rate limits.
        </div>

        <!-- Campaign list -->
        <div class="card card-ng">
            <div class="card-header-ng py-3 px-3">
                <span class="fw-semibold"><?= number_format($total) ?> campaign<?= $total === 1 ? '' : 's' ?></span>
            </div>

            <?php if (empty($rows)): ?>
                <div class="card-body text-center py-5 text-muted">
                    <i class="bi bi-megaphone display-4 d-block mb-2"></i>
                    No campaigns yet. Create one with <strong>New Campaign</strong> and upload a recipient CSV.
                </div>
            <?php else: ?>
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0">
                        <thead class="table-light">
                            <tr>
                                <th>Campaign</th>
                                <th>Business</th>
                                <th>Type</th>
                                <th>Recipients</th>
                                <th>Sent / Failed</th>
                                <th>Status</th>
                                <th>Created</th>
                                <th class="text-end pe-3">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($rows as $c):
                                $counts = $recipientCounts[$c['id']] ?? ['pending' => 0, 'sent' => 0, 'failed' => 0];
                            ?>
                                <tr>
                                    <td>
                                        <div class="fw-semibold"><?= htmlspecialchars($c['campaign_name']) ?></div>
                                        <div class="small text-muted">
                                            <?php if ($c['payload_type'] === 'template'): ?>
                                                <i class="bi bi-file-earmark-text me-1"></i><?= htmlspecialchars($c['template_name'] ?: '—') ?>
                                            <?php elseif ($c['payload_type'] === 'media'): ?>
                                                <i class="bi bi-paperclip me-1"></i><?= htmlspecialchars($c['media_type']) ?>
                                            <?php else: ?>
                                                <i class="bi bi-chat-left-text me-1"></i>text
                                            <?php endif; ?>
                                        </div>
                                    </td>
                                    <td><span class="badge bg-light text-dark border"><?= htmlspecialchars($c['business_name'] ?? 'Sender') ?></span></td>
                                    <td><span class="badge bg-white text-dark border text-uppercase" style="font-size:0.65rem;"><?= htmlspecialchars($c['payload_type']) ?></span></td>
                                    <td class="fw-semibold"><?= number_format((int)$c['total_recipients']) ?></td>
                                    <td class="small">
                                        <span class="text-success fw-semibold"><?= number_format($counts['sent']) ?> sent</span> /
                                        <span class="text-danger fw-semibold"><?= number_format($counts['failed']) ?> failed</span>
                                        <?php if ($counts['pending'] > 0): ?>
                                            <span class="text-muted">/ <?= number_format($counts['pending']) ?> pending</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <span class="badge bg-<?= $statusBadgeColors[$c['status']] ?? 'secondary' ?> text-white text-uppercase">
                                            <?= htmlspecialchars($c['status']) ?>
                                        </span>
                                    </td>
                                    <td class="small text-muted"><?= date('M j, Y g:i a', strtotime($c['created_at'])) ?></td>
                                    <td class="text-end pe-3">
                                        <?php if ($c['status'] === 'draft' || $c['status'] === 'failed'): ?>
                                            <form method="post" action="broadcast" class="d-inline" onsubmit="return confirm('Send this campaign to all pending recipients now?');">
                                                <input type="hidden" name="action" value="send">
                                                <input type="hidden" name="campaign_id" value="<?= (int)$c['id'] ?>">
                                                <button type="submit" class="btn btn-ng-black btn-sm fw-semibold">
                                                    <i class="bi bi-send me-1"></i> Send Now
                                                </button>
                                            </form>
                                        <?php else: ?>
                                            <span class="text-muted small">—</span>
                                        <?php endif; ?>
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
                                    <a class="page-link" href="?page=<?= max(1, $page - 1) ?>">&laquo;</a>
                                </li>
                                <?php for ($p = 1; $p <= $totalPages; $p++): ?>
                                    <li class="page-item <?= $p === $page ? 'active' : '' ?>">
                                        <a class="page-link" href="?page=<?= $p ?>"><?= $p ?></a>
                                    </li>
                                <?php endfor; ?>
                                <li class="page-item <?= $page >= $totalPages ? 'disabled' : '' ?>">
                                    <a class="page-link" href="?page=<?= min($totalPages, $page + 1) ?>">&raquo;</a>
                                </li>
                            </ul>
                        </nav>
                    </div>
                <?php endif; ?>
            <?php endif; ?>
        </div>

    </div>

    <!-- Create Campaign Modal -->
    <div class="modal fade" id="createModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-lg modal-dialog-centered">
            <div class="modal-content">
                <form method="post" action="broadcast" enctype="multipart/form-data">
                    <input type="hidden" name="action" value="create">
                    <div class="modal-header">
                        <h5 class="modal-title"><i class="bi bi-megaphone me-1 text-success"></i> New Broadcast Campaign</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body">
                        <div class="mb-3">
                            <label class="form-label small fw-semibold">Campaign Name *</label>
                            <input type="text" name="campaign_name" class="form-control" placeholder="August welcome campaign" required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label small fw-semibold">Sender Business *</label>
                            <select name="business_id" id="bc_business_id" class="form-select" required>
                                <?php foreach ($activeBusinesses as $biz): ?>
                                    <option value="<?= $biz['id'] ?>"><?= htmlspecialchars($biz['name']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="mb-3">
                            <label class="form-label small fw-semibold">Payload Type</label>
                            <select name="payload_type" id="bc_payload_type" class="form-select">
                                <option value="template">Template (recommended — works outside 24h window)</option>
                                <option value="text">Free-form text</option>
                                <option value="media">Media (image/document/audio/video)</option>
                            </select>
                        </div>

                        <div class="mb-3" id="bc_template_wrap">
                            <label class="form-label small fw-semibold">Approved Template</label>
                            <select name="template_name" id="bc_template_name" class="form-select">
                                <option value="">— select —</option>
                                <?php foreach ($activeBusinesses as $biz): ?>
                                    <optgroup label="<?= htmlspecialchars($biz['name']) ?>" data-business="<?= $biz['id'] ?>">
                                        <?php foreach ($templatesByBusiness[$biz['id']] ?? [] as $tpl): ?>
                                            <option value="<?= htmlspecialchars($tpl['name']) ?>" data-business="<?= $biz['id'] ?>">
                                                <?= htmlspecialchars($tpl['name']) ?> (<?= htmlspecialchars($tpl['language']) ?>)
                                            </option>
                                        <?php endforeach; ?>
                                    </optgroup>
                                <?php endforeach; ?>
                            </select>
                            <div class="form-text">Only templates in the <code>message_templates</code> table show here — run <a href="templates">Template Sync</a> if empty.</div>
                        </div>

                        <div class="mb-3 d-none" id="bc_text_wrap">
                            <label class="form-label small fw-semibold">Message Body</label>
                            <textarea name="message_body" class="form-control" rows="3" placeholder="Your broadcast text…"></textarea>
                        </div>

                        <div class="row g-2 mb-3 d-none" id="bc_media_wrap">
                            <div class="col-4">
                                <label class="form-label small fw-semibold">Media Type</label>
                                <select name="media_type" class="form-select">
                                    <option value="image">Image</option>
                                    <option value="document">Document</option>
                                    <option value="audio">Audio</option>
                                    <option value="video">Video</option>
                                </select>
                            </div>
                            <div class="col-8">
                                <label class="form-label small fw-semibold">Public Media URL</label>
                                <input type="text" name="media_url" class="form-control" placeholder="https://example.com/image.jpg">
                            </div>
                        </div>

                        <div class="mb-3">
                            <label class="form-label small fw-semibold">Recipient CSV *</label>
                            <input type="file" name="recipient_file" class="form-control" accept=".csv,text/csv" required>
                            <div class="form-text">CSV with recipient phone numbers — one per row, or a <code>phone</code> column header. Digits only; formatting is stripped.</div>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-ng-black" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-ng-secondary fw-semibold"><i class="bi bi-megaphone me-1"></i> Create Campaign</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        document.addEventListener('DOMContentLoaded', function () {
            var typeSelect = document.getElementById('bc_payload_type');
            var templateWrap = document.getElementById('bc_template_wrap');
            var textWrap = document.getElementById('bc_text_wrap');
            var mediaWrap = document.getElementById('bc_media_wrap');

            function syncWrap() {
                var type = typeSelect.value;
                templateWrap.classList.toggle('d-none', type !== 'template');
                textWrap.classList.toggle('d-none', type !== 'text');
                mediaWrap.classList.toggle('d-none', type !== 'media');
            }

            typeSelect.addEventListener('change', syncWrap);
            syncWrap();

            var businessSelect = document.getElementById('bc_business_id');
            var templateSelect = document.getElementById('bc_template_name');

            function filterTemplates() {
                var bizId = businessSelect.value;
                var options = templateSelect.querySelectorAll('option');
                options.forEach(function (opt) {
                    if (!opt.value) return;
                    opt.classList.toggle('d-none', opt.dataset.business !== bizId);
                });
                if (templateSelect.value) {
                    var sel = templateSelect.querySelector('option[value="' + templateSelect.value + '"]');
                    if (sel && sel.dataset.business !== bizId) templateSelect.value = '';
                }
            }

            businessSelect.addEventListener('change', filterTemplates);
            filterTemplates();
        });
    </script>
</body>

</html>
