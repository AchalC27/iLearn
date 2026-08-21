<?php
/*
|--------------------------------------------------------------------------
| MultiPie Sidebar 
|--------------------------------------------------------------------------
*/

// Initialize DB connections once
$AuthDb = null;
$appDb = null;

try {
    $AuthDb = getAuthDb();
} catch (Throwable $e) {
    $AuthDb = null;
}

try {
    $appDb = getAppDb();
} catch (Throwable $e) {
    $appDb = null;
}

// Helper to safely fetch table counts with try/catch
$getCount = static function (?PDO $db, string $table): int {
    if (!$db) {
        return 0;
    }
    try {
        return (int) $db->query("SELECT COUNT(*) FROM public.{$table}")->fetchColumn();
    } catch (Throwable $e) {
        return 0;
    }
};

// Menu items configuration
$menuItems = [
    // [
    //     'key'   => 'auth',
    //     'label' => 'Dashboard',
    //     'icon'  => '#i-layout',
    //     'count' => null, // No badge on dashboard
    // ],
    [
        'key'   => 'dashboard',
        'label' => 'Dashboard',
        'icon'  => '#i-layout',
        'count' => null, // No badge on dashboard
    ],
    [
        'key'   => 'users',
        'label' => 'Users Directory',
        'icon'  => '#i-users',
        'count' => $getCount($appDb, 'users'),
    ],
    [
        'key'   => 'amcs',
        'label' => 'AMCs',
        'icon'  => '#i-briefcase',
        'count' => $getCount($appDb, 'amcs'),
    ],
    [
        'key'   => 'announcements',
        'label' => 'Announcements',
        'icon'  => '#i-bell',
        'count' => $getCount($appDb, 'announcements'),
    ],
    [
        'key'   => 'brokers',
        'label' => 'Brokers',
        'icon'  => '#i-user-check',
        'count' => $getCount($appDb, 'brokers'),
    ],
    [
        'key'   => 'cams_feedbacks',
        'label' => 'Cams Feedbacks',
        'icon'  => '#i-file-text',
        'count' => $getCount($appDb, 'cams_feedbacks'),
    ],
    [
        'key'   => 'companies',
        'label' => 'Companies',
        'icon'  => '#i-building',
        'count' => $getCount($appDb, 'companies'),
    ],
    [
        'key'   => 'mutual_funds',
        'label' => 'Mutual Funds',
        'icon'  => '#i-pie-chart',
        'count' => $getCount($appDb, 'mutual_funds'),
    ],
    [
        'key'   => 'exchange_holidays',
        'label' => 'Exchange Holidays',
        'icon'  => '#i-calendar',
        'count' => $getCount($appDb, 'exchange_holidays'),
    ],
    [
        'key'   => 'external_links',
        'label' => 'External Links',
        'icon'  => '#i-external-link',
        'count' => $getCount($appDb, 'external_links'),
    ],
    [
        'key'   => 'holdings_statements',
        'label' => 'Holdings Statements',
        'icon'  => '#i-folder',
        'count' => $getCount($appDb, 'holdings_statements'),
    ],
    [
        'key'   => 'instrument_categories',
        'label' => 'Instrument Categories',
        'icon'  => '#i-tag',
        'count' => $getCount($appDb, 'instrument_categories'),
    ],
    [
        'key'   => 'multi_boxes',
        'label' => 'Multi Boxes',
        'icon'  => '#i-grid',
        'count' => $getCount($appDb, 'multi_boxes'),
    ],
    [
        'key'   => 'market_indices',
        'label' => 'Market Indices',
        'icon'  => '#i-trending-up',
        'count' => $getCount($appDb, 'market_indices'),
    ],
    [
        'key'   => 'notification_settings',
        'label' => 'Notification Settings',
        'icon'  => '#i-sliders',
        'count' => $getCount($appDb, 'notification_settings'),
    ],
    [
        'key'   => 'posts',
        'label' => 'Posts',
        'icon'  => '#i-edit',
        'count' => $getCount($appDb, 'posts'),
    ],
    [
        'key'   => 'products',
        'label' => 'Products',
        'icon'  => '#i-shopping-bag',
        'count' => $getCount($appDb, 'products'),
    ],
    [
        'key'   => 'profane_words',
        'label' => 'Profane Words',
        'icon'  => '#i-shield-alert',
        'count' => $getCount($appDb, 'profane_words'),
    ],
    [
        'key'   => 'reports',
        'label' => 'Reports',
        'icon'  => '#i-bar-chart-2',
        'count' => $getCount($appDb, 'reports'),
    ],
    [
        'key'   => 'settings',
        'label' => 'Settings',
        'icon'  => '#i-settings',
        'count' => $getCount($appDb, 'settings'),
    ],
    [
        'key'   => 'rewards',
        'label' => 'Rewards',
        'icon'  => '#i-award',
        'count' => $getCount($appDb, 'rewards'),
    ],
    [
        'key'   => 'showcases',
        'label' => 'Showcases',
        'icon'  => '#i-image',
        'count' => $getCount($appDb, 'showcases'),
    ],
    [
        'key'   => 'stories',
        'label' => 'Stories',
        'icon'  => '#i-book-open',
        'count' => $getCount($appDb, 'stories'),
    ],
    [
        'key'   => 'subscribers',
        'label' => 'Subscribers',
        'icon'  => '#i-user-plus',
        'count' => $getCount($appDb, 'subscribers'),
    ],
    [
        'key'   => 'topics',
        'label' => 'Topics',
        'icon'  => '#i-hash',
        'count' => $getCount($appDb, 'topics'),
    ],
];
?>

<aside id="ilearn-sidebar" class="cms-sidebar">
    <div class="sidebar-section-switch">
        <a
            class="sidebar-auth-admin-btn"
            href="index.php?sidebar=auth&page=auth/auth_dashboard"
        >
            <span class="sidebar-auth-admin-icon">
                <svg class="icon"><use href="#i-shield"/></svg>
            </span>
            <span>Auth Server Admin</span>
        </a>
    </div>

    <nav id="sidebar-main-nav">
        <div>
            <p class="sidebar-group-title">Main Menu</p>
            <ul role="list">
                <?php foreach ($menuItems as $item): ?>
                    <li>
                        <a 
                            class="nav-btn <?= ($page ?? '') === $item['key'] ? 'active' : '' ?>" 
                            href="index.php?page=<?= urlencode($item['key']) ?>"
                        >
                            <span class="nav-btn-left">
                                <svg class="icon">
                                    <use href="<?= htmlspecialchars($item['icon'], ENT_QUOTES, 'UTF-8') ?>"/>
                                </svg>
                                <span class="sidebar-label"><?= htmlspecialchars($item['label'], ENT_QUOTES, 'UTF-8') ?></span>
                            </span>

                            <?php if ($item['count'] !== null): ?>
                                <span class="sidebar-badge">
                                    <?= number_format($item['count']) ?>
                                </span>
                            <?php endif; ?>
                        </a>
                    </li>
                <?php endforeach; ?>
            </ul>
        </div>
    </nav>

    <div class="sidebar-footer-box">
        <div class="row">
            <span class="dot"></span>
            <span>MultiPie Engine v1.0</span>
        </div>
        <p>PostgreSQL / PHP / HTML / CSS</p>
    </div>
</aside>





