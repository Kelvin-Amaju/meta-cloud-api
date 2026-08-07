<?php

// includes/partials/navbar.php — Shared sidebar for the whole app.
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
    'settings'  => ['label' => 'Settings',    'href' => 'settings/whatsapp', 'icon' => 'bi-gear'],
];

$unreadCount = function_exists('getUnreadCount') ? getUnreadCount() : 0;

?>
<aside class="ng-sidebar" id="ngSidebar" aria-label="Main navigation">
    <div class="ng-sidebar-header">
        <a class="ng-sidebar-brand" href="<?= $navBase ?>home">
            <i class="bi bi-whatsapp"></i>
            <span><span class="fw-bold">Netgrity</span> <span class="ng-accent">WhatsApp API</span></span>
        </a>
    </div>

    <nav class="ng-sidebar-nav">
        <?php foreach ($navItems as $key => $item): ?>
            <a class="ng-sidebar-link <?= $activeNav === $key ? 'active' : '' ?>" href="<?= $navBase . $item['href'] ?>">
                <i class="bi <?= $item['icon'] ?>"></i>
                <span><?= $item['label'] ?></span>
                <?php if ($key === 'inbox' && $unreadCount > 0): ?>
                    <span class="badge ng-sidebar-badge"><?= $unreadCount ?></span>
                <?php endif; ?>
            </a>
        <?php endforeach; ?>
    </nav>

    <div class="ng-sidebar-footer">
        <a href="<?= $navBase ?>send" class="btn btn-ng-secondary w-100 fw-semibold">
            <i class="bi bi-send me-1"></i> Send Message
        </a>
    </div>
</aside>

<div class="ng-sidebar-backdrop" id="ngSidebarBackdrop"></div>
<button class="ng-sidebar-toggle" id="ngSidebarToggle" aria-label="Toggle navigation" aria-controls="ngSidebar">
    <i class="bi bi-list"></i>
</button>

<script>
(function () {
    document.body.classList.add('sidebar-ng');

    var sidebar  = document.getElementById('ngSidebar');
    var toggle   = document.getElementById('ngSidebarToggle');
    var backdrop = document.getElementById('ngSidebarBackdrop');

    function open() {
        sidebar.classList.add('open');
        if (backdrop) backdrop.classList.add('open');
    }

    function close() {
        sidebar.classList.remove('open');
        if (backdrop) backdrop.classList.remove('open');
    }

    if (toggle) {
        toggle.addEventListener('click', function () {
            sidebar.classList.contains('open') ? close() : open();
        });
    }

    if (backdrop) {
        backdrop.addEventListener('click', close);
    }

    sidebar.querySelectorAll('.ng-sidebar-link').forEach(function (link) {
        link.addEventListener('click', function () {
            if (window.innerWidth < 992) close();
        });
    });
})();
</script>
