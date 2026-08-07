<?php

// home.php — Developer Dashboard (feature launcher)

require_once 'includes/init.php';
require_once 'includes/businesses.php';
require_once 'includes/messages.php';
require_once 'includes/customers.php';
require_once 'includes/conversations.php';

$activeNav = 'home';

$businessStats = getBusinessSummaryStats();
$msgStats      = getMessageStats();
$contactStats  = getCustomerStats();
$unread        = getUnreadCount();

$featureCards = [
    [
        'title'    => 'WhatsApp Inbox',
        'subtitle' => 'Conversations & replies',
        'icon'     => 'bi-inbox-fill',
        'href'     => 'inbox',
        'cta'      => 'Open Inbox',
        'btnClass' => 'btn-ng-primary',
        'stat'     => $unread > 0 ? "$unread unread" : ($msgStats['received'] . ' inbound'),
    ],
    [
        'title'    => 'Contacts',
        'subtitle' => 'Customer records & sync',
        'icon'     => 'bi-people-fill',
        'href'     => 'contacts',
        'cta'      => 'Manage Contacts',
        'btnClass' => 'btn-ng-primary',
        'stat'     => $contactStats['total'] . ' customers',
    ],
    [
        'title'    => 'Template Manager',
        'subtitle' => 'Approved templates & sync',
        'icon'     => 'bi-file-earmark-text-fill',
        'href'     => 'templates',
        'cta'      => 'Open Templates',
        'btnClass' => 'btn-ng-primary',
        'stat'     => 'sync from Meta',
    ],
    [
        'title'    => 'Broadcast',
        'subtitle' => 'Campaigns & bulk sends',
        'icon'     => 'bi-megaphone-fill',
        'href'     => 'broadcast',
        'cta'      => 'New Campaign',
        'btnClass' => 'btn-ng-primary',
        'stat'     => 'CSV recipients',
    ],
    [
        'title'    => 'Message History',
        'subtitle' => 'Logs, filters & statuses',
        'icon'     => 'bi-chat-left-text-fill',
        'href'     => 'messages',
        'cta'      => 'View Messages',
        'btnClass' => 'btn-ng-primary',
        'stat'     => number_format($msgStats['total']) . ' total',
    ],
    [
        'title'    => 'Analytics',
        'subtitle' => 'Trends & performance',
        'icon'     => 'bi-bar-chart-line-fill',
        'href'     => 'analytics',
        'cta'      => 'Open Analytics',
        'btnClass' => 'btn-ng-primary',
        'stat'     => number_format($msgStats['today']) . ' today',
    ],
    [
        'title'    => 'Send Message',
        'subtitle' => 'Text / template / media',
        'icon'     => 'bi-send-fill',
        'href'     => 'send',
        'cta'      => 'Send Now',
        'btnClass' => 'btn-ng-secondary',
        'stat'     => 'outbound API',
    ],
    [
        'title'    => 'Business Senders',
        'subtitle' => 'Onboarding & credentials',
        'icon'     => 'bi-building-fill',
        'href'     => 'business',
        'cta'      => 'Manage Businesses',
        'btnClass' => 'btn-ng-black',
        'stat'     => $businessStats['active'] . ' active',
    ],
];

?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Dashboard - Netgrity WhatsApp API</title>

    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.5/font/bootstrap-icons.css" rel="stylesheet">
    <link href="assets/css/app.css" rel="stylesheet">
</head>

<body class="bg-light min-vh-100 d-flex flex-column">

    <?php require __DIR__ . '/includes/partials/navbar.php'; ?>

    <div class="container pb-5">

        <div class="mt-5 d-flex align-items-center justify-content-between mb-4 flex-wrap gap-2">
            <div>
                <h4 class="fw-bold mb-1 ng-title">Developer Dashboard</h4>
                <p class="ng-text-muted mb-0 fs-6">Manage messaging, conversations, templates, broadcasts, and analytics.</p>
            </div>
            <a href="test" class="btn btn-ng-black btn-sm fw-semibold">
                <i class="bi bi-cpu me-1"></i> Test API Connection
            </a>
        </div>

        <?php $bannerCompact = true;
        require __DIR__ . '/includes/partials/messaging_limit_banner.php'; ?>

        <!-- Feature grid -->
        <div class="row g-4">
            <?php foreach ($featureCards as $card): ?>
                <div class="col-md-6 col-lg-3">
                    <div class="card h-100 card-ng">
                        <div class="card-body p-4 d-flex flex-column">
                            <div class="d-flex align-items-center mb-3">
                                <div class="ng-icon-box p-3 rounded-3 me-3">
                                    <i class="bi <?= $card['icon'] ?> fs-4"></i>
                                </div>
                                <div>
                                    <h5 class="card-title fw-bold mb-0 ng-title fs-6"><?= $card['title'] ?></h5>
                                    <small class="ng-text-muted fs-6"><?= $card['subtitle'] ?></small>
                                </div>
                            </div>
                            <div class="small fw-semibold ng-text-muted mb-3">
                                <span class="badge-ng-soft badge rounded-pill"><?= htmlspecialchars($card['stat']) ?></span>
                            </div>
                            <div class="mt-auto">
                                <a href="<?= $card['href'] ?>" class="btn <?= $card['btnClass'] ?> w-100 fs-6 d-flex align-items-center justify-content-center gap-2 fw-semibold">
                                    <?= $card['cta'] ?> <i class="bi bi-arrow-right"></i>
                                </a>
                            </div>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>

        <!-- System Logs -->
        <div class="card card-ng mt-4">
            <div class="card-header-ng py-3 px-3">
                <h5 class="card-title fw-bold mb-0 ng-title"><i class="bi bi-journal-code me-1 ng-title-accent"></i> Application Logs</h5>
            </div>
            <div class="list-group list-group-flush">
                <a href="storage/logs/webhook.log" target="_blank" class="list-group-item list-group-item-action p-3 d-flex align-items-center justify-content-between">
                    <div class="d-flex align-items-center gap-3">
                        <i class="bi bi-arrow-down-left-square fs-4 ng-title-accent"></i>
                        <div>
                            <h6 class="mb-0 fw-semibold ng-title">Webhook Incoming Logs</h6>
                            <small class="ng-text-muted">Inbound payloads and delivery callbacks</small>
                        </div>
                    </div>
                    <span class="badge-ng-soft badge"><i class="bi bi-box-arrow-up-right me-1"></i> Open Log</span>
                </a>
                <a href="storage/logs/errors.log" target="_blank" class="list-group-item list-group-item-action p-3 d-flex align-items-center justify-content-between">
                    <div class="d-flex align-items-center gap-3">
                        <i class="bi bi-exclamation-triangle-fill fs-4 text-danger"></i>
                        <div>
                            <h6 class="mb-0 fw-semibold ng-title">System Error Logs</h6>
                            <small class="ng-text-muted">cURL failures, missing env variables, API errors</small>
                        </div>
                    </div>
                    <span class="badge-ng-soft badge"><i class="bi bi-box-arrow-up-right me-1"></i> Open Log</span>
                </a>
            </div>
        </div>

    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/js/bootstrap.bundle.min.js"></script>
</body>

</html>
