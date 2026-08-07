<?php

// send.php — Multi-Tenant WhatsApp Message Sender (netgrity_wa)

require_once 'includes/init.php';
require_once 'includes/messages.php';
require_once 'includes/templates.php';

// Retrieve active business accounts
$activeBusinesses = getActiveBusinesses('active');

// Determine initial selected business (from POST -> GET -> First available)
$selectedBusinessId = (int)($_POST['business_id'] ?? ($_POST['tenant_id'] ?? ($_GET['business_id'] ?? ($activeBusinesses[0]['id'] ?? 0))));
$selectedBusiness   = $selectedBusinessId > 0 ? getBusinessById($selectedBusinessId) : null;

// Build a per-business template map for the frontend toggle/dropdown
$templatesByBusiness = [];
foreach ($activeBusinesses as $biz) {
    $templatesByBusiness[$biz['id']] = getTemplatesForBusiness($biz['id']);
}

$sendMode = $_POST['send_mode'] ?? 'freeform'; // 'freeform' | 'template'

$alert = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    // Shared inputs
    $rawPhone   = trim($_POST['phone'] ?? '');
    $businessId = (int)($_POST['business_id'] ?? ($_POST['tenant_id'] ?? 0));
    $cleanPhone = preg_replace('/[^0-9]/', '', $rawPhone);

    // Fetch chosen business credentials
    $selectedBusiness = getBusinessById($businessId);

    if (empty($cleanPhone)) {
        $alert = [
            'type'    => 'danger',
            'title'   => 'Validation Error',
            'message' => 'Please provide a recipient phone number.'
        ];
    } elseif (!$selectedBusiness) {
        $alert = [
            'type'    => 'danger',
            'title'   => 'Business Sender Required',
            'message' => 'Please select a valid sender business account from the side panel.'
        ];

    } elseif ($sendMode === 'template') {

        // ── Template send ──────────────────────────────────────
        $templateName     = trim($_POST['template_name'] ?? '');
        $templateLanguage = trim($_POST['template_language'] ?? 'en_US');
        $variables        = array_map('trim', $_POST['variables'] ?? []);

        $template = $templateName ? getTemplateByName($selectedBusiness['id'], $templateName) : null;

        if (!$template) {
            $alert = [
                'type'    => 'danger',
                'title'   => 'Template Required',
                'message' => 'Please select a valid message template.'
            ];
        } else {

            $response = sendLegacyTemplateMessage($cleanPhone, [
                'name'     => $template['name'],
                'language' => $templateLanguage,
            ], $variables, $selectedBusiness);

            // Human-readable preview for the message log (variables substituted in)
            $previewText = $template['body_text'];
            foreach ($variables as $i => $val) {
                $previewText = str_replace('{{' . ($i + 1) . '}}', $val !== '' ? $val : '{{' . ($i + 1) . '}}', $previewText);
            }
            $previewText = "[Template: {$template['name']}] " . $previewText;

            if (!empty($response['success'])) {

                $wamid = $response['data']['messages'][0]['id'] ?? ('wamid.simulated_' . time());

                // Templates open a fresh 24h session — always logged as two-way
                saveOutgoingMessage($cleanPhone, $previewText, $wamid, 1, $selectedBusiness['id'], 'sent', null, 'template');

                $messageText = "Template <strong>{$template['name']}</strong> sent to <strong>+{$cleanPhone}</strong> via <strong>"
                    . htmlspecialchars($selectedBusiness['name']) . "</strong> (" . ucfirst($selectedBusiness['product_line']) . ").";
                $messageText .= "<br><small class='font-monospace text-muted mt-1 d-block'>Message ID: {$wamid}</small>";

                $alert = [
                    'type'    => 'success',
                    'title'   => 'Template Message Sent!',
                    'message' => $messageText,
                    'raw'     => $response
                ];

            } else {

                $errorMessage = $response['error']
                    ?? $response['data']['error']['message']
                    ?? 'An error occurred while communicating with Meta Graph API.';

                $errorCode = $response['status']
                    ?? $response['data']['error']['code']
                    ?? 'Error';

                saveOutgoingMessage($cleanPhone, $previewText, 'wamid.failed_' . time(), 1, $selectedBusiness['id'], 'failed', $errorMessage, 'template');

                $alert = [
                    'type'    => 'danger',
                    'title'   => "API Error (Code {$errorCode})",
                    'message' => htmlspecialchars($errorMessage) . "<br><small class='text-muted'>Sender Business: <strong>" . htmlspecialchars($selectedBusiness['name']) . "</strong></small>",
                    'raw'     => $response
                ];
            }
        }

    } elseif ($sendMode === 'media') {

        // ── Media send (image / video / audio / document) ───────
        $mediaType = trim($_POST['media_type'] ?? 'image');
        $mediaUrl  = trim($_POST['media_url'] ?? '');
        $caption   = trim($_POST['media_caption'] ?? '');

        $allowedMediaTypes = ['image', 'video', 'audio', 'document'];

        if (empty($mediaUrl)) {
            $alert = [
                'type'    => 'danger',
                'title'   => 'Validation Error',
                'message' => 'Please provide a public media URL.'
            ];
        } elseif (!in_array($mediaType, $allowedMediaTypes, true)) {
            $alert = [
                'type'    => 'danger',
                'title'   => 'Validation Error',
                'message' => 'Invalid media type selected.'
            ];
        } else {

            $response = sendMediaMessage($cleanPhone, $mediaType, $mediaUrl, $caption !== '' ? $caption : null, $selectedBusiness);

            $previewText = "[{$mediaType}: {$mediaUrl}]" . ($caption !== '' ? "\n" . $caption : '');

            if (!empty($response['success'])) {

                $wamid = $response['data']['messages'][0]['id'] ?? ('wamid.simulated_' . time());

                saveOutgoingMessage($cleanPhone, $previewText, $wamid, 1, $selectedBusiness['id'], 'sent', null, $mediaType, $mediaUrl, $mediaType);

                $messageText = ucfirst($mediaType) . " <strong>" . htmlspecialchars($mediaUrl) . "</strong> sent to <strong>+{$cleanPhone}</strong> via <strong>"
                    . htmlspecialchars($selectedBusiness['name']) . "</strong> (" . ucfirst($selectedBusiness['product_line']) . ").";
                if ($caption !== '') {
                    $messageText .= "<br><small class='text-muted'>Caption: " . htmlspecialchars($caption) . "</small>";
                }
                $messageText .= "<br><small class='font-monospace text-muted mt-1 d-block'>Message ID: {$wamid}</small>";

                $alert = [
                    'type'    => 'success',
                    'title'   => 'Media Message Sent!',
                    'message' => $messageText,
                    'raw'     => $response
                ];

            } else {

                $errorMessage = $response['error']
                    ?? $response['data']['error']['message']
                    ?? 'An error occurred while communicating with Meta Graph API.';

                $errorCode = $response['status']
                    ?? $response['data']['error']['code']
                    ?? 'Error';

                saveOutgoingMessage($cleanPhone, $previewText, 'wamid.failed_' . time(), 1, $selectedBusiness['id'], 'failed', $errorMessage, $mediaType, $mediaUrl, $mediaType);

                $alert = [
                    'type'    => 'danger',
                    'title'   => "API Error (Code {$errorCode})",
                    'message' => htmlspecialchars($errorMessage) . "<br><small class='text-muted'>Sender Business: <strong>" . htmlspecialchars($selectedBusiness['name']) . "</strong></small>",
                    'raw'     => $response
                ];
            }
        }

    } elseif ($sendMode === 'interactive') {

        // ── Interactive send (buttons / list) ───────────────────
        $interactiveType = trim($_POST['interactive_type'] ?? 'button');

        if ($interactiveType === 'list') {

            $headerText = trim($_POST['list_header'] ?? '');
            $bodyText   = trim($_POST['list_body'] ?? '');
            $footerText = trim($_POST['list_footer'] ?? '');
            $buttonText = trim($_POST['list_button'] ?? '');
            $buttonText = $buttonText !== '' ? $buttonText : 'Options';

            $sections = [];
            $sectionTitles    = $_POST['section_title'] ?? [];
            $sectionRowIds    = $_POST['section_row_id'] ?? [];
            $sectionRowTitles = $_POST['section_row_title'] ?? [];

            foreach ($sectionTitles as $idx => $title) {
                if (trim((string)$title) === '') {
                    continue;
                }
                $rows = [];
                $rowIds    = $sectionRowIds[$idx] ?? [];
                $rowTitles = $sectionRowTitles[$idx] ?? [];
                foreach ($rowIds as $ridx => $rowId) {
                    $rowTitle = $rowTitles[$ridx] ?? '';
                    if (trim((string)$rowId) !== '' && trim((string)$rowTitle) !== '') {
                        $rows[] = [
                            'id'    => trim((string)$rowId),
                            'title' => trim((string)$rowTitle),
                        ];
                    }
                }
                $sections[] = [
                    'title' => trim((string)$title),
                    'rows'  => $rows,
                ];
            }

            if (empty($bodyText)) {
                $alert = [
                    'type'    => 'danger',
                    'title'   => 'Validation Error',
                    'message' => 'Please provide a body message for the list.'
                ];
            } else {

                $response = sendInteractiveListMessage($cleanPhone, $headerText, $bodyText, $footerText, $buttonText, $sections, $selectedBusiness);

                $interactivePayload = [
                    'type'    => 'list',
                    'header'  => $headerText,
                    'body'    => $bodyText,
                    'footer'  => $footerText,
                    'button'  => $buttonText,
                    'sections'=> $sections,
                ];

                $previewText = '[Interactive List] ' . $bodyText;

                if (!empty($response['success'])) {

                    $wamid = $response['data']['messages'][0]['id'] ?? ('wamid.simulated_' . time());

                    saveOutgoingMessage($cleanPhone, $previewText, $wamid, 1, $selectedBusiness['id'], 'sent', null, 'interactive', null, null, $interactivePayload);

                    $messageText = "Interactive list sent to <strong>+{$cleanPhone}</strong> via <strong>"
                        . htmlspecialchars($selectedBusiness['name']) . "</strong> (" . ucfirst($selectedBusiness['product_line']) . ").";
                    $messageText .= "<br><small class='text-muted'>Sections: " . count($sections) . " &bull; Rows: " . array_sum(array_map('count', array_column($sections, 'rows'))) . "</small>";
                    $messageText .= "<br><small class='font-monospace text-muted mt-1 d-block'>Message ID: {$wamid}</small>";

                    $alert = [
                        'type'    => 'success',
                        'title'   => 'Interactive List Sent!',
                        'message' => $messageText,
                        'raw'     => $response
                    ];

                } else {

                    $errorMessage = $response['error']
                        ?? $response['data']['error']['message']
                        ?? 'An error occurred while communicating with Meta Graph API.';

                    $errorCode = $response['status']
                        ?? $response['data']['error']['code']
                        ?? 'Error';

                    saveOutgoingMessage($cleanPhone, $previewText, 'wamid.failed_' . time(), 1, $selectedBusiness['id'], 'failed', $errorMessage, 'interactive', null, null, $interactivePayload);

                    $alert = [
                        'type'    => 'danger',
                        'title'   => "API Error (Code {$errorCode})",
                        'message' => htmlspecialchars($errorMessage) . "<br><small class='text-muted'>Sender Business: <strong>" . htmlspecialchars($selectedBusiness['name']) . "</strong></small>",
                        'raw'     => $response
                    ];
                }
            }

        } else {

            // ── Interactive buttons ──
            $bodyText = trim($_POST['button_body'] ?? '');

            $buttonLabels = [];
            foreach (($_POST['button_label'] ?? []) as $label) {
                $label = trim((string)$label);
                if ($label !== '') {
                    $buttonLabels[] = $label;
                }
            }
            $buttonLabels = array_slice($buttonLabels, 0, 3);

            $buttons = [];
            foreach ($buttonLabels as $i => $label) {
                $buttons[] = [
                    'type'  => 'reply',
                    'reply' => [
                        'id'    => 'btn_' . ($i + 1),
                        'title' => $label,
                    ],
                ];
            }

            if (empty($bodyText) || empty($buttons)) {
                $alert = [
                    'type'    => 'danger',
                    'title'   => 'Validation Error',
                    'message' => 'Please provide a body message and at least one button label.'
                ];
            } else {

                $response = sendInteractiveButtonsMessage($cleanPhone, $bodyText, $buttons, $selectedBusiness);

                $interactivePayload = [
                    'type'    => 'button',
                    'body'    => $bodyText,
                    'buttons' => $buttons,
                ];

                $previewText = '[Interactive Buttons] ' . $bodyText;

                if (!empty($response['success'])) {

                    $wamid = $response['data']['messages'][0]['id'] ?? ('wamid.simulated_' . time());

                    saveOutgoingMessage($cleanPhone, $previewText, $wamid, 1, $selectedBusiness['id'], 'sent', null, 'interactive', null, null, $interactivePayload);

                    $messageText = "Interactive buttons (" . count($buttons) . ") sent to <strong>+{$cleanPhone}</strong> via <strong>"
                        . htmlspecialchars($selectedBusiness['name']) . "</strong> (" . ucfirst($selectedBusiness['product_line']) . ").";
                    $messageText .= "<br><small class='font-monospace text-muted mt-1 d-block'>Message ID: {$wamid}</small>";

                    $alert = [
                        'type'    => 'success',
                        'title'   => 'Interactive Message Sent!',
                        'message' => $messageText,
                        'raw'     => $response
                    ];

                } else {

                    $errorMessage = $response['error']
                        ?? $response['data']['error']['message']
                        ?? 'An error occurred while communicating with Meta Graph API.';

                    $errorCode = $response['status']
                        ?? $response['data']['error']['code']
                        ?? 'Error';

                    saveOutgoingMessage($cleanPhone, $previewText, 'wamid.failed_' . time(), 1, $selectedBusiness['id'], 'failed', $errorMessage, 'interactive', null, null, $interactivePayload);

                    $alert = [
                        'type'    => 'danger',
                        'title'   => "API Error (Code {$errorCode})",
                        'message' => htmlspecialchars($errorMessage) . "<br><small class='text-muted'>Sender Business: <strong>" . htmlspecialchars($selectedBusiness['name']) . "</strong></small>",
                        'raw'     => $response
                    ];
                }
            }
        }

    } else {

        // ── Free-form send (unchanged from before) ──────────────
        $message    = trim($_POST['message'] ?? '');
        $allowReply = isset($_POST['allow_reply']) ? 1 : 0;

        if (empty($message)) {
            $alert = [
                'type'    => 'danger',
                'title'   => 'Validation Error',
                'message' => 'Please provide message content.'
            ];
        } else {

            $outgoingMessage = $message;
            if (!$allowReply) {
                $outgoingMessage .= "\n\n*DO NOT REPLY TO THIS MESSAGE*";
            }

            $response = sendTextMessage($cleanPhone, $outgoingMessage, $selectedBusiness);

            if (!empty($response['success'])) {

                $wamid = $response['data']['messages'][0]['id'] ?? ('wamid.simulated_' . time());

                saveOutgoingMessage($cleanPhone, $outgoingMessage, $wamid, $allowReply, $selectedBusiness['id'], 'sent', null);

                $messageText = "Sent to <strong>+{$cleanPhone}</strong> via <strong>" . htmlspecialchars($selectedBusiness['name']) . "</strong> (" . ucfirst($selectedBusiness['product_line']) . ").";

                if (!$allowReply) {
                    $messageText .= '<br><span class="badge bg-warning text-dark mt-1">One-Way Message</span>';
                }

                $messageText .= "<br><small class='font-monospace text-muted mt-1 d-block'>Message ID: {$wamid}</small>";

                $alert = [
                    'type'    => 'success',
                    'title'   => 'Message Sent Successfully!',
                    'message' => $messageText,
                    'raw'     => $response
                ];
            } else {

                $errorMessage = $response['error']
                    ?? $response['data']['error']['message']
                    ?? 'An error occurred while communicating with Meta Graph API.';

                $errorCode = $response['status']
                    ?? $response['data']['error']['code']
                    ?? 'Error';

                saveOutgoingMessage($cleanPhone, $outgoingMessage, 'wamid.failed_' . time(), $allowReply, $selectedBusiness['id'], 'failed', $errorMessage);

                $alert = [
                    'type'    => 'danger',
                    'title'   => "API Error (Code {$errorCode})",
                    'message' => htmlspecialchars($errorMessage) . "<br><small class='text-muted'>Sender Business: <strong>" . htmlspecialchars($selectedBusiness['name']) . "</strong></small>",
                    'raw'     => $response
                ];
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Send Message | Netgrity WhatsApp API</title>

    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css" rel="stylesheet">
    <link href="assets/css/app.css" rel="stylesheet">
    <style>
        .business-card {
            cursor: pointer;
            transition: all 0.2s ease-in-out;
            border: 2px solid #e9ecef;
        }

        .business-card:hover {
            border-color: #0d6efd;
            box-shadow: 0 4px 12px rgba(13, 110, 253, 0.08);
        }

        .business-card.active-sender {
            border-color: #198754;
            background-color: #f8fff9;
            box-shadow: 0 4px 14px rgba(25, 135, 84, 0.12);
        }

        .business-card .form-check-input:checked {
            background-color: #198754;
            border-color: #198754;
        }

        .media-card {
            cursor: pointer;
            border: 2px solid #e9ecef;
            transition: all 0.2s ease-in-out;
        }

        .media-card:hover {
            border-color: #0d6efd;
            box-shadow: 0 4px 12px rgba(13, 110, 253, 0.08);
        }

        .media-card.active-sender {
            border-color: #198754;
            background-color: #f8fff9;
            box-shadow: 0 4px 14px rgba(25, 135, 84, 0.12);
        }

        .badge-hotel { background-color: #ffc107; color: #000; }
        .badge-school { background-color: #0dcaf0; color: #000; }
        .badge-hospital { background-color: #dc3545; color: #fff; }
        .badge-erp { background-color: #0d6efd; color: #fff; }
        .badge-crm { background-color: #198754; color: #fff; }
        .badge-other { background-color: #6c757d; color: #fff; }

        .mode-toggle .btn-check:checked + .btn-outline-success {
            background-color: #ff6b00;
            border-color: #ff6b00;
            color: #fff;
        }

        .template-preview {
            background: #fff;
            border: 1px dashed #ced4da;
            border-radius: .5rem;
            padding: .75rem 1rem;
            font-size: .9rem;
            white-space: pre-wrap;
        }

        .template-var-mark {
            background: #fff3cd;
            padding: 0 2px;
            border-radius: 3px;
        }
    </style>
</head>

<body class="bg-light min-vh-100 d-flex flex-column">

    <!-- Top Navbar -->
    <?php $activeNav = 'send';
    require __DIR__ . '/includes/partials/navbar.php'; ?>

    <div class="mt-5 container-fluid container-xl mb-5 my-auto">

        <!-- Header -->
        <div class="d-flex align-items-center justify-content-between mb-4">
            <div>
                <h5 class="fw-bold text-dark mb-1">
                    <i class="bi bi-send-fill text-success me-2"></i>Send WhatsApp Message
                </h5>
                <p class="text-muted mb-0 fs-6">Choose your sender business account and compose your WhatsApp message.</p>
            </div>
        </div>

        <?php if ($alert): ?>
            <div class="alert alert-<?= $alert['type'] ?> alert-dismissible fade show shadow-sm border-0 mb-4">
                <div class="d-flex align-items-center gap-2 mb-1">
                    <i class="bi bi-<?= $alert['type'] === 'success' ? 'check-circle-fill' : 'exclamation-octagon-fill' ?> fs-6"></i>
                    <h5 class="mb-0 fw-bold"><?= $alert['title'] ?></h5>
                </div>

                <div class="mt-2 fs-6">
                    <?= $alert['message'] ?>
                </div>

                <?php if (isset($alert['raw'])): ?>
                    <div class="mt-3">
                        <button
                            class="btn btn-sm btn-outline-<?= $alert['type'] ?>"
                            type="button"
                            data-bs-toggle="collapse"
                            data-bs-target="#debugResponse">
                            <i class="bi bi-code-slash me-1"></i> Toggle Meta Payload
                        </button>

                        <div class="collapse mt-2" id="debugResponse">
                            <pre class="bg-dark text-light p-3 rounded mb-0 text-start" style="max-height: 250px; overflow-y: auto;"><code><?= htmlspecialchars(json_encode($alert['raw'], JSON_PRETTY_PRINT)) ?></code></pre>
                        </div>
                    </div>
                <?php endif; ?>

                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>

        <form method="post" action="send" id="sendForm">

            <div class="row g-4">

                <!-- Left Column: Business / Sender Selection Panel -->
                <div class="col-lg-4 col-md-5">

                    <div class="card border-0 shadow-sm h-100">
                        <div class="card-header bg-white border-bottom py-3">
                            <div class="d-flex align-items-center justify-content-between">
                                <h5 class="fw-bold mb-0 text-dark">
                                    <i class="bi bi-building text-primary me-2"></i>Business Senders
                                </h5>
                                <span class="badge bg-primary rounded-pill"><?= count($activeBusinesses) ?> Active</span>
                            </div>
                        </div>

                        <div class="card-body p-3">

                            <?php if (empty($activeBusinesses)): ?>
                                <div class="text-center py-4 text-muted">
                                    <i class="bi bi-exclamation-circle fs-4 d-block mb-2"></i>
                                    No active business accounts found.
                                </div>
                            <?php else: ?>
                                <div class="d-flex flex-column gap-3">
                                    <?php foreach ($activeBusinesses as $biz): ?>
                                        <?php
                                            $isCheck = ($selectedBusiness && $selectedBusiness['id'] == $biz['id']);
                                            $badgeClass = 'badge-' . strtolower($biz['product_line'] ?? 'other');
                                        ?>
                                        <label class="card business-card p-3 rounded-3 position-relative <?= $isCheck ? 'active-sender' : '' ?>" for="biz_<?= $biz['id'] ?>">

                                            <div class="d-flex align-items-start gap-3">

                                                <div class="pt-1">
                                                    <input
                                                        class="form-check-input business-radio fs-6"
                                                        type="radio"
                                                        name="business_id"
                                                        id="biz_<?= $biz['id'] ?>"
                                                        value="<?= $biz['id'] ?>"
                                                        data-name="<?= htmlspecialchars($biz['name']) ?>"
                                                        data-phone="<?= htmlspecialchars($biz['display_phone_number'] ?: $biz['phone_number_id']) ?>"
                                                        data-line="<?= htmlspecialchars(ucfirst($biz['product_line'])) ?>"
                                                        <?= $isCheck ? 'checked' : '' ?>
                                                        required>
                                                </div>

                                                <div class="flex-grow-1">

                                                    <div class="d-flex align-items-center justify-content-between mb-1">
                                                        <span class="fw-bold text-dark fs-6"><?= htmlspecialchars($biz['name']) ?></span>
                                                        <span class="badge <?= $badgeClass ?> px-2 py-1 text-uppercase" style="font-size: 0.68rem;"><?= htmlspecialchars($biz['product_line']) ?></span>
                                                    </div>

                                                    <div class="text-muted small d-flex align-items-center gap-1 mb-1">
                                                        <i class="bi bi-telephone text-secondary"></i>
                                                        <span><?= htmlspecialchars($biz['display_phone_number'] ?: 'No Phone Number') ?></span>
                                                    </div>

                                                    <div class="text-muted small font-monospace" style="font-size: 0.75rem;">
                                                        ID: <?= htmlspecialchars($biz['phone_number_id']) ?>
                                                    </div>

                                                </div>

                                            </div>

                                        </label>
                                    <?php endforeach; ?>
                                </div>
                            <?php endif; ?>

                        </div>

                        <div class="card-footer bg-light border-top text-muted small p-3">
                            <i class="bi bi-info-circle text-primary me-1"></i> Messages are sent using Meta credentials specific to each product line business.
                        </div>

                    </div>

                </div>

                <!-- Right Column: Message Composer Form -->
                <div class="col-lg-8 col-md-7">

                    <div class="card border-0 shadow-sm">

                        <!-- Active Sender Banner -->
                        <div class="card-header bg-white border-bottom py-3">
                            <div class="d-flex align-items-center justify-content-between flex-wrap gap-2">
                                <h5 class="fw-bold mb-0 text-dark fs-6">
                                    <i class="bi bi-pencil-square text-success me-2"></i>Compose Message
                                </h5>

                                <div id="activeSenderDisplay" class="badge bg-success-subtle text-success fs-6 border border-success-subtle px-3 py-2 d-flex align-items-center gap-2">
                                    <i class="bi bi-check-circle-fill"></i>
                                    <span>Sending as: <strong id="activeCompanyName"><?= htmlspecialchars($selectedBusiness['name'] ?? 'Select Sender') ?></strong></span>
                                </div>
                            </div>
                        </div>

                        <div class="card-body p-4">

                            <!-- Mode Toggle -->
                            <div class="mb-4">
                                <label class="form-label fw-semibold text-dark d-block">Message Mode</label>
                                <div class="btn-group mode-toggle" role="group">
                                    <input type="radio" class="btn-check" name="send_mode" id="mode_freeform" value="freeform"
                                        <?= $sendMode === 'freeform' ? 'checked' : '' ?>>
                                    <label class="btn btn-outline-success px-4" for="mode_freeform">
                                        <i class="bi bi-chat-left-text me-1"></i> Free-form
                                    </label>

                                    <input type="radio" class="btn-check" name="send_mode" id="mode_template" value="template"
                                        <?= $sendMode === 'template' ? 'checked' : '' ?>>
                                    <label class="btn btn-outline-success px-4" for="mode_template">
                                        <i class="bi bi-layout-text-window me-1"></i> Template
                                    </label>

                                    <input type="radio" class="btn-check" name="send_mode" id="mode_media" value="media"
                                        <?= $sendMode === 'media' ? 'checked' : '' ?>>
                                    <label class="btn btn-outline-success px-4" for="mode_media">
                                        <i class="bi bi-image me-1"></i> Media
                                    </label>

                                    <input type="radio" class="btn-check" name="send_mode" id="mode_interactive" value="interactive"
                                        <?= $sendMode === 'interactive' ? 'checked' : '' ?>>
                                    <label class="btn btn-outline-success px-4" for="mode_interactive">
                                        <i class="bi bi-ui-radios me-1"></i> Interactive
                                    </label>
                                </div>
                                <div class="form-text text-muted mt-1">
                                    <i class="bi bi-clock-history me-1"></i>
                                    Free-form only works within 24 hours of the customer's last message. Outside that window, use a template.
                                </div>
                            </div>

                            <!-- Recipient Phone Number -->
                            <div class="mb-4">
                                <label class="form-label fw-semibold text-dark">
                                    Recipient Phone Number <span class="text-danger">*</span>
                                </label>

                                <div class="input-group input-group">
                                    <span class="input-group-text bg-white border-end-0 text-secondary">
                                        <i class="bi bi-telephone-fill"></i>
                                    </span>
                                    <input
                                        type="text"
                                        name="phone"
                                        class="form-control border-start-0 ps-0"
                                        placeholder="2349044313696"
                                        value="<?= htmlspecialchars($_POST['phone'] ?? '') ?>"
                                        required>
                                </div>
                                <div class="form-text text-muted mt-1">
                                    <i class="bi bi-globe me-1"></i> Enter country code without <code>+</code> or leading zeros (e.g. <code>2349044313696</code>).
                                </div>
                            </div>

                            <!-- Free-form Panel -->
                            <div id="freeformPanel">

                                <div class="mb-4">
                                    <label class="form-label fw-semibold text-dark">
                                        Message Body <span class="text-danger">*</span>
                                    </label>

                                    <textarea
                                        class="form-control"
                                        name="message"
                                        id="messageField"
                                        rows="5"
                                        placeholder="Type your WhatsApp message here..."><?= htmlspecialchars($_POST['message'] ?? '') ?></textarea>
                                </div>

                                <!-- Options Panel -->
                                <div class="card bg-light border-0 p-3 mb-4 rounded-3">
                                    <div class="form-check form-switch d-flex align-items-center gap-2 mb-1">
                                        <input
                                            class="form-check-input fs-6 mt-0"
                                            type="checkbox"
                                            id="allow_reply"
                                            name="allow_reply"
                                            value="1"
                                            <?= (isset($_POST['allow_reply']) || !$_POST) ? 'checked' : '' ?>>

                                        <label class="form-check-label fw-semibold text-dark" for="allow_reply">
                                            Allow Recipient Replies
                                        </label>
                                    </div>

                                    <small class="text-muted ps-4 d-block">
                                        Unchecking this marks the message as One-Way. A <em>"DO NOT REPLY TO THIS MESSAGE"</em> notice will be automatically appended.
                                    </small>
                                </div>

                            </div>

                            <!-- Template Panel -->
                            <div id="templatePanel" class="d-none">

                                <div class="mb-4">
                                    <label class="form-label fw-semibold text-dark">
                                        Template <span class="text-danger">*</span>
                                    </label>
                                    <select class="form-select form-select-lg" name="template_name" id="templateSelect">
                                        <option value="">Select a template…</option>
                                    </select>
                                    <input type="hidden" name="template_language" id="templateLanguage" value="en_US">
                                    <div class="form-text text-muted mt-1">
                                        <i class="bi bi-info-circle me-1"></i>
                                        Approved templates loaded from the <code>message_templates</code> table for the selected business.
                                    </div>
                                </div>

                                <div id="templateVariables" class="mb-3"></div>

                                <div class="mb-3">
                                    <label class="form-label small fw-semibold text-muted">Preview</label>
                                    <div class="template-preview" id="templatePreview">Select a template to see its preview.</div>
                                </div>

                            </div>

                            <!-- Media Panel -->
                            <div id="mediaPanel" class="d-none">

                                <div class="mb-4">
                                    <label class="form-label fw-semibold text-dark">
                                        Media Type <span class="text-danger">*</span>
                                    </label>
                                    <div class="row g-2">
                                        <?php
                                        $mediaOptions = [
                                            'image'    => ['bi-image', 'Image'],
                                            'document' => ['bi-file-earmark-text', 'Document'],
                                            'audio'    => ['bi-file-earmark-music', 'Audio'],
                                            'video'    => ['bi-file-earmark-play', 'Video'],
                                        ];
                                        ?>
                                        <?php foreach ($mediaOptions as $mType => $mInfo): ?>
                                            <div class="col-6 col-md-3">
                                                <label class="card media-card p-2 rounded-3 text-center <?= ($_POST['media_type'] ?? 'image') === $mType ? 'active-sender' : '' ?>">
                                                    <input
                                                        class="form-check-input media-type-radio d-none"
                                                        type="radio"
                                                        name="media_type"
                                                        value="<?= $mType ?>"
                                                        <?= ($_POST['media_type'] ?? 'image') === $mType ? 'checked' : '' ?>>
                                                    <i class="bi <?= $mInfo[0] ?> fs-6 d-block mb-1"></i>
                                                    <span class="small"><?= $mInfo[1] ?></span>
                                                </label>
                                            </div>
                                        <?php endforeach; ?>
                                    </div>
                                </div>

                                <div class="mb-4">
                                    <label class="form-label fw-semibold text-dark">
                                        Media URL <span class="text-danger">*</span>
                                    </label>
                                    <div class="input-group input-group-lg">
                                        <span class="input-group-text bg-white border-end-0 text-secondary">
                                            <i class="bi bi-link-45deg"></i>
                                        </span>
                                        <input
                                            type="url"
                                            name="media_url"
                                            id="mediaUrlField"
                                            class="form-control border-start-0 ps-0"
                                            placeholder="https://example.com/file.jpg"
                                            value="<?= htmlspecialchars($_POST['media_url'] ?? '') ?>">
                                    </div>
                                    <div class="form-text text-muted mt-1">
                                        <i class="bi bi-globe me-1"></i> Must be a publicly reachable HTTPS URL. For documents, include the file extension (e.g. <code>.pdf</code>).
                                    </div>
                                </div>

                                <div class="mb-4">
                                    <label class="form-label fw-semibold text-dark">
                                        Caption
                                    </label>
                                    <textarea
                                        class="form-control"
                                        name="media_caption"
                                        rows="3"
                                        placeholder="Optional caption text shown with the media..."><?= htmlspecialchars($_POST['media_caption'] ?? '') ?></textarea>
                                </div>

                            </div>

                            <!-- Interactive Panel -->
                            <div id="interactivePanel" class="d-none">

                                <div class="mb-4">
                                    <label class="form-label fw-semibold text-dark d-block">Interactive Type</label>
                                    <div class="btn-group mode-toggle" role="group">
                                        <input type="radio" class="btn-check" name="interactive_type" id="int_type_button" value="button"
                                            <?= ($_POST['interactive_type'] ?? 'button') === 'button' ? 'checked' : '' ?>>
                                        <label class="btn btn-outline-success px-4" for="int_type_button">
                                            <i class="bi bi-toggles me-1"></i> Buttons
                                        </label>

                                        <input type="radio" class="btn-check" name="interactive_type" id="int_type_list" value="list"
                                            <?= ($_POST['interactive_type'] ?? '') === 'list' ? 'checked' : '' ?>>
                                        <label class="btn btn-outline-success px-4" for="int_type_list">
                                            <i class="bi bi-list-ul me-1"></i> List
                                        </label>
                                    </div>
                                </div>

                                <!-- Interactive Buttons -->
                                <div id="interactiveButtonsPanel" class="d-none">
                                    <div class="mb-4">
                                        <label class="form-label fw-semibold text-dark">
                                            Body Message <span class="text-danger">*</span>
                                        </label>
                                        <textarea
                                            class="form-control"
                                            name="button_body"
                                            id="buttonBodyField"
                                            rows="3"
                                            placeholder="Prompt text above the buttons..."><?= htmlspecialchars($_POST['button_body'] ?? '') ?></textarea>
                                    </div>

                                    <div class="mb-3">
                                        <label class="form-label fw-semibold text-dark d-flex justify-content-between align-items-center">
                                            <span>Buttons</span>
                                            <button type="button" class="btn btn-sm btn-outline-primary" id="addButtonBtn">
                                                <i class="bi bi-plus-lg me-1"></i> Add Button
                                            </button>
                                        </label>
                                        <div id="buttonRows" class="mb-2"></div>
                                        <div class="form-text text-muted">
                                            <i class="bi bi-info-circle me-1"></i> Up to 3 buttons, each title limited to 25 characters.
                                        </div>
                                    </div>
                                </div>

                                <!-- Interactive List -->
                                <div id="interactiveListPanel" class="d-none">
                                    <div class="row g-3">
                                        <div class="col-md-6">
                                            <label class="form-label fw-semibold text-dark">Header</label>
                                            <input type="text" name="list_header" class="form-control" maxlength="60"
                                                placeholder="Optional header text" value="<?= htmlspecialchars($_POST['list_header'] ?? '') ?>">
                                        </div>
                                        <div class="col-md-6">
                                            <label class="form-label fw-semibold text-dark">List Button Text</label>
                                            <input type="text" name="list_button" class="form-control" maxlength="20"
                                                placeholder="Options" value="<?= htmlspecialchars($_POST['list_button'] ?? '') ?>">
                                        </div>
                                        <div class="col-12">
                                            <label class="form-label fw-semibold text-dark">
                                                Body Message <span class="text-danger">*</span>
                                            </label>
                                            <textarea class="form-control" name="list_body" id="listBodyField" rows="3"
                                                placeholder="Prompt text above the menu..."><?= htmlspecialchars($_POST['list_body'] ?? '') ?></textarea>
                                        </div>
                                        <div class="col-12">
                                            <label class="form-label fw-semibold text-dark">Footer</label>
                                            <input type="text" name="list_footer" class="form-control" maxlength="60"
                                                placeholder="Optional footer text" value="<?= htmlspecialchars($_POST['list_footer'] ?? '') ?>">
                                        </div>
                                        <div class="col-12">
                                            <label class="form-label fw-semibold text-dark d-flex justify-content-between align-items-center">
                                                <span>Sections</span>
                                                <button type="button" class="btn btn-sm btn-outline-primary" id="addSectionBtn">
                                                    <i class="bi bi-plus-lg me-1"></i> Add Section
                                                </button>
                                            </label>
                                            <div id="listSections"></div>
                                            <div class="form-text text-muted">
                                                <i class="bi bi-info-circle me-1"></i> Each section needs a title and at least one row (ID + title).
                                            </div>
                                        </div>
                                    </div>
                                </div>

                            </div>

                            <!-- Buttons -->
                            <div class="d-flex align-items-center justify-content-between pt-2">
                                <a href="home" class="btn btn-danger px-4">
                                    <i class="bi bi-x-circle me-1"></i> Cancel
                                </a>

                                <button type="submit" class="btn btn-ng-secondary btn px-4 shadow-sm">
                                     Send Message
                                </button>
                            </div>

                        </div>

                    </div>

                </div>

            </div>

        </form>

    </div>

    <!-- Footer -->
    <footer class="mt-auto py-3 bg-white border-top text-center text-muted small">
        <div class="container">
            Netgrity WhatsApp Cloud API &bull; Multi-Tenant Schema (netgrity_wa)
        </div>
    </footer>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/js/bootstrap.bundle.min.js"></script>

    <script>
        // Per-business template lists, injected from PHP (mock for now)
        const templatesByBusiness = <?= json_encode($templatesByBusiness) ?>;
        const seededButtons = <?= json_encode($_POST['button_label'] ?? []) ?>;
        const seededSectionTitles = <?= json_encode($_POST['section_title'] ?? []) ?>;
        const seededSectionRowIds = <?= json_encode($_POST['section_row_id'] ?? []) ?>;
        const seededSectionRowTitles = <?= json_encode($_POST['section_row_title'] ?? []) ?>;

        document.addEventListener('DOMContentLoaded', function () {
            const radios = document.querySelectorAll('.business-radio');
            const cards = document.querySelectorAll('.business-card');
            const activeCompanyName = document.getElementById('activeCompanyName');

            const freeformPanel = document.getElementById('freeformPanel');
            const templatePanel = document.getElementById('templatePanel');
            const mediaPanel = document.getElementById('mediaPanel');
            const interactivePanel = document.getElementById('interactivePanel');
            const interactiveButtonsPanel = document.getElementById('interactiveButtonsPanel');
            const interactiveListPanel = document.getElementById('interactiveListPanel');
            const messageField = document.getElementById('messageField');
            const templateSelect = document.getElementById('templateSelect');
            const templateVariables = document.getElementById('templateVariables');
            const templatePreview = document.getElementById('templatePreview');
            const templateLanguage = document.getElementById('templateLanguage');
            const mediaUrlField = document.getElementById('mediaUrlField');
            const buttonBodyField = document.getElementById('buttonBodyField');
            const listBodyField = document.getElementById('listBodyField');
            const buttonRows = document.getElementById('buttonRows');
            const listSections = document.getElementById('listSections');
            const addButtonBtn = document.getElementById('addButtonBtn');
            const addSectionBtn = document.getElementById('addSectionBtn');

            let sectionIndex = 0;
            let buttonCount = 0;

            function getSelectedBusinessId() {
                const checked = document.querySelector('.business-radio:checked');
                return checked ? checked.value : null;
            }

            function populateTemplateSelect() {
                const businessId = getSelectedBusinessId();
                const list = (businessId && templatesByBusiness[businessId]) ? templatesByBusiness[businessId] : [];

                templateSelect.innerHTML = '<option value="">Select a template…</option>';
                list.forEach(tpl => {
                    const opt = document.createElement('option');
                    opt.value = tpl.name;
                    opt.textContent = tpl.name + ' (' + tpl.variable_count + ' variable' + (tpl.variable_count === 1 ? '' : 's') + ')';
                    opt.dataset.language = tpl.language;
                    opt.dataset.body = tpl.body_text;
                    opt.dataset.variableCount = tpl.variable_count;
                    templateSelect.appendChild(opt);
                });

                renderTemplateVariables();
            }

            function renderTemplateVariables() {
                const opt = templateSelect.selectedOptions[0];
                templateVariables.innerHTML = '';

                if (!opt || !opt.value) {
                    templatePreview.textContent = 'Select a template to see its preview.';
                    return;
                }

                templateLanguage.value = opt.dataset.language || 'en_US';
                const count = parseInt(opt.dataset.variableCount || '0', 10);

                for (let i = 1; i <= count; i++) {
                    const wrap = document.createElement('div');
                    wrap.className = 'mb-2';
                    wrap.innerHTML = `
                        <label class="form-label small fw-semibold text-muted mb-1">Variable {{${i}}}</label>
                        <input type="text" class="form-control template-var-input" data-index="${i}" placeholder="Value for {{${i}}}">
                    `;
                    templateVariables.appendChild(wrap);
                }

                templateVariables.querySelectorAll('.template-var-input').forEach(input => {
                    input.addEventListener('input', updatePreview);
                });

                updatePreview();
            }

            function updatePreview() {
                const opt = templateSelect.selectedOptions[0];
                if (!opt || !opt.value) return;

                let body = opt.dataset.body || '';
                templateVariables.querySelectorAll('.template-var-input').forEach(input => {
                    const idx = input.dataset.index;
                    const val = input.value.trim() || `{{${idx}}}`;
                    body = body.split(`{{${idx}}}`).join(`<span class="template-var-mark">${val}</span>`);
                });

                templatePreview.innerHTML = body;
            }

            function applyMode() {
                const mode = document.querySelector('input[name="send_mode"]:checked').value;

                freeformPanel.classList.toggle('d-none', mode !== 'freeform');
                templatePanel.classList.toggle('d-none', mode !== 'template');
                mediaPanel.classList.toggle('d-none', mode !== 'media');
                interactivePanel.classList.toggle('d-none', mode !== 'interactive');

                // Only require fields belonging to the active mode
                messageField.required = mode === 'freeform';
                templateSelect.required = mode === 'template';
                mediaUrlField.required = mode === 'media';

                if (mode === 'interactive') {
                    applyInteractiveType();
                }
            }

            function applyInteractiveType() {
                const type = document.querySelector('input[name="interactive_type"]:checked').value;

                interactiveButtonsPanel.classList.toggle('d-none', type !== 'button');
                interactiveListPanel.classList.toggle('d-none', type !== 'list');

                buttonBodyField.required = type === 'button';
                listBodyField.required = type === 'list';
            }

            function addButtonRow(label) {
                if (buttonCount >= 3) return;
                buttonCount++;

                const wrap = document.createElement('div');
                wrap.className = 'input-group mb-2';

                const span = document.createElement('span');
                span.className = 'input-group-text';
                span.textContent = 'Button ' + buttonCount;

                const input = document.createElement('input');
                input.type = 'text';
                input.name = 'button_label[]';
                input.className = 'form-control';
                input.maxLength = 25;
                input.placeholder = 'Button label';
                input.value = label || '';

                const btn = document.createElement('button');
                btn.type = 'button';
                btn.className = 'btn btn-outline-danger remove-btn';
                btn.title = 'Remove button';
                btn.innerHTML = '<i class="bi bi-x-lg"></i>';
                btn.addEventListener('click', () => { wrap.remove(); buttonCount--; });

                wrap.append(span, input, btn);
                buttonRows.appendChild(wrap);
            }

            function addListSection(title, rows) {
                sectionIndex++;
                const idx = sectionIndex;

                const wrap = document.createElement('div');
                wrap.className = 'card bg-light border-0 p-3 mb-2 list-section';

                const head = document.createElement('div');
                head.className = 'd-flex justify-content-between align-items-center mb-2';

                const headLabel = document.createElement('label');
                headLabel.className = 'form-label small fw-semibold mb-0';
                headLabel.textContent = 'Section Title';

                const removeBtn = document.createElement('button');
                removeBtn.type = 'button';
                removeBtn.className = 'btn btn-sm btn-outline-danger remove-section';
                removeBtn.title = 'Remove section';
                removeBtn.innerHTML = '<i class="bi bi-x-lg"></i>';
                removeBtn.addEventListener('click', () => wrap.remove());

                head.append(headLabel, removeBtn);

                const titleInput = document.createElement('input');
                titleInput.type = 'text';
                titleInput.name = 'section_title[' + idx + ']';
                titleInput.className = 'form-control form-control-sm mb-2';
                titleInput.placeholder = 'Section title';
                titleInput.value = title || '';

                const rowsContainer = document.createElement('div');
                rowsContainer.className = 'list-rows';

                const addRowBtn = document.createElement('button');
                addRowBtn.type = 'button';
                addRowBtn.className = 'btn btn-sm btn-outline-primary add-row';
                addRowBtn.innerHTML = '<i class="bi bi-plus-lg me-1"></i> Add Row';
                addRowBtn.addEventListener('click', () => addListRow(rowsContainer, idx));

                wrap.append(head, titleInput, rowsContainer, addRowBtn);
                listSections.appendChild(wrap);

                (rows || []).forEach(r => addListRow(rowsContainer, idx, r.id, r.title));
            }

            function addListRow(container, sectionIdx, rowId, rowTitle) {
                const row = document.createElement('div');
                row.className = 'row g-2 mb-2 list-row';

                const colId = document.createElement('div');
                colId.className = 'col-4';
                const idInput = document.createElement('input');
                idInput.type = 'text';
                idInput.name = 'section_row_id[' + sectionIdx + '][]';
                idInput.className = 'form-control form-control-sm';
                idInput.placeholder = 'Row ID';
                idInput.value = rowId || '';
                colId.appendChild(idInput);

                const colTitle = document.createElement('div');
                colTitle.className = 'col-6';
                const titleInput = document.createElement('input');
                titleInput.type = 'text';
                titleInput.name = 'section_row_title[' + sectionIdx + '][]';
                titleInput.className = 'form-control form-control-sm';
                titleInput.placeholder = 'Row title';
                titleInput.value = rowTitle || '';
                colTitle.appendChild(titleInput);

                const colBtn = document.createElement('div');
                colBtn.className = 'col-2';
                const removeBtn = document.createElement('button');
                removeBtn.type = 'button';
                removeBtn.className = 'btn btn-sm btn-outline-danger remove-row';
                removeBtn.title = 'Remove row';
                removeBtn.innerHTML = '<i class="bi bi-x-lg"></i>';
                removeBtn.addEventListener('click', () => row.remove());
                colBtn.appendChild(removeBtn);

                row.append(colId, colTitle, colBtn);
                container.appendChild(row);
            }

            radios.forEach(radio => {
                radio.addEventListener('change', function () {
                    cards.forEach(card => card.classList.remove('active-sender'));
                    if (this.checked) {
                        const parentCard = this.closest('.business-card');
                        if (parentCard) parentCard.classList.add('active-sender');
                        if (activeCompanyName) {
                            activeCompanyName.textContent = this.dataset.name + ' (' + this.dataset.line + ')';
                        }
                    }
                    populateTemplateSelect();
                });
            });

            document.querySelectorAll('.media-type-radio').forEach(radio => {
                radio.addEventListener('change', function () {
                    document.querySelectorAll('.media-card').forEach(c => c.classList.remove('active-sender'));
                    if (this.checked && this.closest('.media-card')) {
                        this.closest('.media-card').classList.add('active-sender');
                    }
                });
            });

            document.querySelectorAll('input[name="send_mode"]').forEach(r => r.addEventListener('change', applyMode));
            document.querySelectorAll('input[name="interactive_type"]').forEach(r => r.addEventListener('change', applyInteractiveType));
            templateSelect.addEventListener('change', renderTemplateVariables);
            addButtonBtn.addEventListener('click', () => addButtonRow(''));
            addSectionBtn.addEventListener('click', () => addListSection('', []));

            // Seed dynamic builders from the previous (failed) submission
            seededButtons.forEach(label => addButtonRow(label));
            Object.keys(seededSectionTitles).forEach(idx => {
                addListSection(
                    seededSectionTitles[idx],
                    (seededSectionRowIds[idx] || []).map((rid, i) => ({ id: rid, title: seededSectionRowTitles[idx]?.[i] || '' }))
                );
            });

            // Init on load
            populateTemplateSelect();
            applyMode();
        });
    </script>

</body>

</html>