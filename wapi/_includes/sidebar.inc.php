<?php

// _includes/sidebar.inc.php
// Static sidebar for every main/ page (was includes/partials/navbar.php).
// No role filtering — the module is unauthenticated by design.
// Usage: $activeNav = 'inbox'; require __DIR__ . '/../_includes/sidebar.inc.php';

$activeNav = $activeNav ?? '';

$navItems = [
    'home'      => ['label' => 'Dashboard',   'href' => 'home',              'icon' => 'bi-speedometer2'],
    'inbox'     => ['label' => 'Inbox',       'href' => 'inbox',             'icon' => 'bi-inbox'],
    'contacts'  => ['label' => 'Contacts',    'href' => 'contacts',          'icon' => 'bi-people'],
    'templates' => ['label' => 'Templates',   'href' => 'templates',         'icon' => 'bi-file-earmark-text'],
    'broadcast' => ['label' => 'Broadcast',   'href' => 'broadcast',         'icon' => 'bi-megaphone'],
    'messages'  => ['label' => 'Messages',    'href' => 'messages',          'icon' => 'bi-chat-left-text'],
    'analytics' => ['label' => 'Analytics',   'href' => 'analytics',         'icon' => 'bi-bar-chart-line'],
    'business'  => ['label' => 'Business',    'href' => 'business_index',    'icon' => 'bi-building'],
    'settings'  => ['label' => 'Settings',    'href' => 'settings_whatsapp', 'icon' => 'bi-gear'],
];

$unreadCount = function_exists('get_unread_count') ? get_unread_count() : 0;

?>
<aside class="ng-sidebar" id="ngSidebar" aria-label="Main navigation">
    <div class="ng-sidebar-header">
        <a class="ng-sidebar-brand" href="home">
            <i class="bi bi-whatsapp"></i>
            <span><span class="fw-bold">Netgrity</span> <span class="ng-accent">WhatsApp API</span></span>
        </a>
    </div>

    <nav class="ng-sidebar-nav">
        <?php foreach ($navItems as $key => $item): ?>
            <a class="ng-sidebar-link <?= $activeNav === $key ? 'active' : '' ?>" href="<?= $item['href'] ?>">
                <i class="bi <?= $item['icon'] ?>"></i>
                <span><?= $item['label'] ?></span>
                <?php if ($key === 'inbox' && $unreadCount > 0): ?>
                    <span class="badge ng-sidebar-badge"><?= $unreadCount ?></span>
                <?php endif; ?>
            </a>
        <?php endforeach; ?>
    </nav>

    <div class="ng-sidebar-footer">
        <a href="send" class="btn btn-ng-secondary w-100 fw-semibold">
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
