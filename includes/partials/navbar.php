<?php

// includes/partials/navbar.php — Shared navbar for the whole app.
// Usage: $activeNav = 'inbox'; require __DIR__ . '/includes/partials/navbar.php';
// Pages in a subdirectory (e.g. business/) must set $navBase = '../' first.

$activeNav = $activeNav ?? '';
$navBase   = $navBase ?? '';

$navItems = [
    'home'      => ['label' => 'Dashboard',   'href' => 'home',        'icon' => 'bi-speedometer2'],
    'inbox'     => ['label' => 'Inbox',       'href' => 'inbox',       'icon' => 'bi-inbox'],
    'contacts'  => ['label' => 'Contacts',    'href' => 'contacts',    'icon' => 'bi-people'],
    'templates' => ['label' => 'Templates',   'href' => 'templates',   'icon' => 'bi-file-earmark-text'],
    'broadcast' => ['label' => 'Broadcast',   'href' => 'broadcast',   'icon' => 'bi-megaphone'],
    'messages'  => ['label' => 'Messages',    'href' => 'messages',    'icon' => 'bi-chat-left-text'],
    'analytics' => ['label' => 'Analytics',   'href' => 'analytics',   'icon' => 'bi-bar-chart-line'],
    'business'  => ['label' => 'Business',    'href' => 'business',    'icon' => 'bi-building'],
];

$unreadCount = function_exists('getUnreadCount') ? getUnreadCount() : 0;

?>
<nav class="navbar navbar-expand-lg navbar-ng mb-4 shadow-sm">
    <div class="container">
        <a class="navbar-brand d-flex align-items-center gap-2 fw-bold" href="<?= $navBase ?>home">
            <i class="bi bi-whatsapp fs-4" style="color: var(--ng-orange);"></i>
            <span>Netgrity</span> <span class="ng-accent">WhatsApp API</span>
        </a>

        <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#ngNavbar">
            <span class="navbar-toggler-icon"></span>
        </button>

        <div class="collapse navbar-collapse" id="ngNavbar">
            <ul class="navbar-nav me-auto mb-2 mb-lg-0">
                <?php foreach ($navItems as $key => $item): ?>
                    <li class="nav-item">
                        <a class="nav-link <?= $activeNav === $key ? 'active' : '' ?>" href="<?= $navBase . $item['href'] ?>">
                            <i class="bi <?= $item['icon'] ?> me-1"></i><?= $item['label'] ?>
                            <?php if ($key === 'inbox' && $unreadCount > 0): ?>
                                <span class="badge bg-danger rounded-pill ms-1"><?= $unreadCount ?></span>
                            <?php endif; ?>
                        </a>
                    </li>
                <?php endforeach; ?>
            </ul>
            <a href="<?= $navBase ?>send" class="btn btn-ng-secondary btn-sm fw-semibold">
                <i class="bi bi-send me-1"></i> Send Message
            </a>
        </div>
    </div>
</nav>
