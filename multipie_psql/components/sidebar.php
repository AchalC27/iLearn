<?php
/*
|--------------------------------------------------------------------------
| MultiPie Sidebar (Optimized)
|--------------------------------------------------------------------------
*/

// Initialize DB connections once
$usersDb = null;
$appDb = null;

try {
    $usersDb = getUsersDb();
} catch (Throwable $e) {
    $usersDb = null;
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
        'count' => $getCount($usersDb, 'users'),
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
















<!-- <?php
/*
|--------------------------------------------------------------------------
| MultiPie Sidebar
|--------------------------------------------------------------------------
|
| Users count is read directly from:
|   multipie_auth_prod.public.users
|
| No JSON/dummy data is used.
|--------------------------------------------------------------------------
*/

$user_Count = 0;

try {
    $usersDb = getUsersDb();

    $user_Count = (int)$usersDb
        ->query('SELECT COUNT(*) FROM public.users')
        ->fetchColumn();
} catch (Throwable $e) {
    $user_Count = 0;
}

$amcs_Count = 0;

try {
    $sppDb = getAppDb();

    $amcs_Count = (int)$sppDb
        ->query('SELECT COUNT(*) FROM public.amcs')
        ->fetchColumn();
} catch (Throwable $e) {
    $amcs_Count = 0;
} 

$announcements_Count = 0;

try {
    $sppDb = getAppDb();

    $announcements_Count = (int)$sppDb
        ->query('SELECT COUNT(*) FROM public.announcements')
        ->fetchColumn();
} catch (Throwable $e) {
    $announcements_Count = 0;
} 

$brokers_Count = 0;

try {
    $sppDb = getAppDb();

    $brokers_Count = (int)$sppDb
        ->query('SELECT COUNT(*) FROM public.brokers')
        ->fetchColumn();
} catch (Throwable $e) {
    $brokers_Count = 0;
} 


$cams_feedbacks_Count = 0;

try {
    $sppDb = getAppDb();

    $cams_feedbacks_Count = (int)$sppDb
        ->query('SELECT COUNT(*) FROM public.cams_feedbacks')
        ->fetchColumn();
} catch (Throwable $e) {
    $cams_feedbacks_Count = 0;
} 

$companies_Count = 0;

try {
    $sppDb = getAppDb();

    $companies_Count = (int)$sppDb
        ->query('SELECT COUNT(*) FROM public.companies')
        ->fetchColumn();
} catch (Throwable $e) {
    $companies_Count = 0;
} 

$mutual_funds_Count = 0;

try {
    $sppDb = getAppDb();

    $mutual_funds_Count = (int)$sppDb
        ->query('SELECT COUNT(*) FROM public.mutual_funds')
        ->fetchColumn();
} catch (Throwable $e) {
    $mutual_funds_Count = 0;
} 

$exchange_holidays_Count = 0;

try {
    $sppDb = getAppDb();

    $exchange_holidays_Count = (int)$sppDb
        ->query('SELECT COUNT(*) FROM public.exchange_holidays')
        ->fetchColumn();
} catch (Throwable $e) {
    $exchange_holidays_Count = 0;
} 

$amcsCount = 0;

try {
    $sppDb = getAppDb();

    $amcsCount = (int)$sppDb
        ->query('SELECT COUNT(*) FROM public.amcs')
        ->fetchColumn();
} catch (Throwable $e) {
    $amcsCount = 0;
} 


?>

<aside id="ilearn-sidebar" class="cms-sidebar">
    <nav id="sidebar-main-nav">

        <div>
            <p class="sidebar-group-title">Main Menu</p>

            <ul role="list">

                <li>
                    <a
                        class="nav-btn <?= $page === 'dashboard' ? 'active' : '' ?>"
                        href="index.php?page=dashboard"
                    >
                        <span class="nav-btn-left">
                            <svg class="icon">
                                <use href="#i-layout"/>
                            </svg>
                            <span class="sidebar-label">Dashboard</span>
                        </span>
                    </a>
                </li>

                <li>
                    <a
                        class="nav-btn <?= $page === 'users' ? 'active' : '' ?>"
                        href="index.php?page=users"
                    >
                        <span class="nav-btn-left">
                            <svg class="icon">
                                <use href="#i-users"/>
                            </svg>
                            <span class="sidebar-label">Users Directory</span>
                        </span>

                        <span class="sidebar-badge">
                            <?= number_format($user_Count) ?>
                        </span>
                    </a>
                </li>

                <li>
                    <a
                        class="nav-btn <?= $page === 'amcs' ? 'active' : '' ?>"
                        href="index.php?page=amcs"
                    >
                        <span class="nav-btn-left">
                            <svg class="icon">
                                <use href="#i-layout"/>
                            </svg>
                            <span class="sidebar-label">
                                AMCs
                            </span>
                        </span>

                        <span class="sidebar-badge">
                            <?= number_format($amcs_Count) ?>
                        </span>
                    </a>
                </li>

                <li>

                    <a
                        class="nav-btn <?= $page === 'announcements' ? 'active' : '' ?>"
                        href="index.php?page=announcements"
                    >
                        <span class="nav-btn-left">
                            <svg class="icon">
                                <use href="#i-bell"></use>
                            </svg>
                            <span class="sidebar-label">
                                Announcements
                            </span>
                        </span>

                        <span class="sidebar-badge">
                            <?= number_format($announcements_Count) ?>
                        </span>
                    </a>
                </li>

                <li>

                    <a
                        class="nav-btn <?= $page === 'brokers' ? 'active' : '' ?>"
                        href="index.php?page=brokers"
                    >
                        <span class="nav-btn-left">
                            <svg class="icon">
                                <use href="#i-bell"></use>
                            </svg>
                            <span class="sidebar-label">
                                Brokers
                            </span>
                        </span>

                        <span class="sidebar-badge">
                            <?= number_format($brokers_Count) ?>
                        </span>
                    </a>
                </li>

                <li>
                    <a
                        class="nav-btn <?= $page === 'cams_feedbacks' ? 'active' : '' ?>"
                        href="index.php?page=cams_feedbacks"
                    >
                        <span class="nav-btn-left">
                            <svg class="icon">
                                <use href="#i-file-text"></use>
                            </svg>
                            <span class="sidebar-label">
                                Cams Feedbacks
                            </span>
                        </span>
                        <span class="sidebar-badge">
                            <?= number_format($cams_feedbacks_Count) ?>
                        </span>
                    </a>
                </li>

                <li>
                    <a
                        class="nav-btn <?= $page === 'companies' ? 'active' : '' ?>"
                        href="index.php?page=companies"
                    >
                        <span class="nav-btn-left">
                            <svg class="icon">
                                <use href="#i-message"></use>
                            </svg>
                            <span class="sidebar-label">
                                Companies
                            </span>
                        </span>

                        <span class="sidebar-badge">
                            <?= number_format($companies_Count) ?>
                        </span>
                    </a>
                </li>

                <li>
                    <a
                        class="nav-btn <?= $page === 'mutual_funds' ? 'active' : '' ?>"
                        href="index.php?page=mutual_funds"
                    >
                        <span class="nav-btn-left">
                            <svg class="icon">
                                <use href="#i-message"></use>
                            </svg>
                            <span class="sidebar-label">
                                Mutual Funds
                            </span>
                        </span>
                        <span class="sidebar-badge">
                            <?= number_format($mutual_funds_Count) ?>
                        </span>
                    </a>
                </li>

                <li>
                    <a
                        class="nav-btn <?= $page === 'exchange_holidays' ? 'active' : '' ?>"
                        href="index.php?page=exchange_holidays"
                    >
                        <span class="nav-btn-left">
                            <svg class="icon">
                                <use href="#i-message"></use>
                            </svg>
                            <span class="sidebar-label">
                                Exchange Holidays
                            </span>
                        </span>
                        <span class="sidebar-badge">
                            <?= number_format($exchange_holidays_Count) ?>
                        </span>
                    </a>
                </li>

                <li>
                    <a
                        class="nav-btn <?= $page === 'external_links' ? 'active' : '' ?>"
                        href="index.php?page=external_links"
                    >
                        <span class="nav-btn-left">
                            <svg class="icon">
                                <use href="#i-message"></use>
                            </svg>
                            <span class="sidebar-label">
                                External Links
                            </span>
                        </span>
                    </a>
                </li>

                <li>
                    <a
                        class="nav-btn <?= $page === 'holdings_statements' ? 'active' : '' ?>"
                        href="index.php?page=holdings_statements"
                    >
                        <span class="nav-btn-left">
                            <svg class="icon">
                                <use href="#i-message"></use>
                            </svg>
                            <span class="sidebar-label">
                                Holdings Statements
                            </span>
                        </span>
                    </a>
                </li>


                <li>
                    <a
                        class="nav-btn <?= $page === 'instrument_categories' ? 'active' : '' ?>"
                        href="index.php?page=instrument_categories"
                    >
                        <span class="nav-btn-left">
                            <svg class="icon">
                                <use href="#i-message"></use>
                            </svg>
                            <span class="sidebar-label">
                                Instrument Categories
                            </span>
                        </span>
                    </a>
                </li>

                <li>
                    <a
                        class="nav-btn <?= $page === 'multi_boxes' ? 'active' : '' ?>"
                        href="index.php?page=multi_boxes"
                    >
                        <span class="nav-btn-left">
                            <svg class="icon">
                                <use href="#i-message"></use>
                            </svg>
                            <span class="sidebar-label">
                                Multi Boxes
                            </span>
                        </span>
                    </a>
                </li>

                <li>
                    <a
                        class="nav-btn <?= $page === 'market_indices' ? 'active' : '' ?>"
                        href="index.php?page=market_indices"
                    >
                        <span class="nav-btn-left">
                            <svg class="icon">
                                <use href="#i-message"></use>
                            </svg>
                            <span class="sidebar-label">
                                Market Indices
                            </span>
                        </span>
                    </a>
                </li>

                <li>
                    <a
                        class="nav-btn <?= $page === 'notification_settings' ? 'active' : '' ?>"
                        href="index.php?page=notification_settings"
                    >
                        <span class="nav-btn-left">
                            <svg class="icon">
                                <use href="#i-message"></use>
                            </svg>
                            <span class="sidebar-label">
                                Notification Settings
                            </span>
                        </span>
                    </a>
                </li>

                <li>
                    <a
                        class="nav-btn <?= $page === 'posts' ? 'active' : '' ?>"
                        href="index.php?page=posts"
                    >
                        <span class="nav-btn-left">
                            <svg class="icon">
                                <use href="#i-message"></use>
                            </svg>
                            <span class="sidebar-label">
                                Posts
                            </span>
                        </span>
                    </a>
                </li>

                <li>
                    <a
                        class="nav-btn <?= $page === 'products' ? 'active' : '' ?>"
                        href="index.php?page=products"
                    >
                        <span class="nav-btn-left">
                            <svg class="icon">
                                <use href="#i-message"></use>
                            </svg>
                            <span class="sidebar-label">
                                Products
                            </span>
                        </span>
                    </a>
                </li>

                <li>
                    <a
                        class="nav-btn <?= $page === 'profane_words' ? 'active' : '' ?>"
                        href="index.php?page=profane_words"
                    >
                        <span class="nav-btn-left">
                            <svg class="icon">
                                <use href="#i-message"></use>
                            </svg>
                            <span class="sidebar-label">
                                Profane Words
                            </span>
                        </span>
                    </a>
                </li>

                <li>
                    <a
                        class="nav-btn <?= $page === 'reports' ? 'active' : '' ?>"
                        href="index.php?page=reports"
                    >
                        <span class="nav-btn-left">
                            <svg class="icon">
                                <use href="#i-message"></use>
                            </svg>
                            <span class="sidebar-label">
                                Reports
                            </span>
                        </span>
                    </a>
                </li>

                <li>
                    <a
                        class="nav-btn <?= $page === 'settings' ? 'active' : '' ?>"
                        href="index.php?page=settings"
                    >
                        <span class="nav-btn-left">
                            <svg class="icon">
                                <use href="#i-message"></use>
                            </svg>
                            <span class="sidebar-label">
                                Settings
                            </span>
                        </span>
                    </a>
                </li>

                <li>
                    <a
                        class="nav-btn <?= $page === 'rewards' ? 'active' : '' ?>"
                        href="index.php?page=rewards"
                    >
                        <span class="nav-btn-left">
                            <svg class="icon">
                                <use href="#i-message"></use>
                            </svg>
                            <span class="sidebar-label">
                                Rewards
                            </span>
                        </span>
                    </a>
                </li>

                <li>
                    <a
                        class="nav-btn <?= $page === 'showcases' ? 'active' : '' ?>"
                        href="index.php?page=showcases"
                    >
                        <span class="nav-btn-left">
                            <svg class="icon">
                                <use href="#i-message"></use>
                            </svg>
                            <span class="sidebar-label">
                                Showcases
                            </span>
                        </span>
                    </a>
                </li>

                <li>
                    <a
                        class="nav-btn <?= $page === 'stories' ? 'active' : '' ?>"
                        href="index.php?page=stories"
                    >
                        <span class="nav-btn-left">
                            <svg class="icon">
                                <use href="#i-message"></use>
                            </svg>
                            <span class="sidebar-label">
                                Stories
                            </span>
                        </span>
                    </a>
                </li>

                <li>
                    <a
                        class="nav-btn <?= $page === 'subscribers' ? 'active' : '' ?>"
                        href="index.php?page=subscribers"
                    >
                        <span class="nav-btn-left">
                            <svg class="icon">
                                <use href="#i-message"></use>
                            </svg>
                            <span class="sidebar-label">
                                Subscribers
                            </span>
                        </span>
                    </a>
                </li>

                <li>
                    <a
                        class="nav-btn <?= $page === 'topics' ? 'active' : '' ?>"
                        href="index.php?page=topics"
                    >
                        <span class="nav-btn-left">
                            <svg class="icon">
                                <use href="#i-message"></use>
                            </svg>
                            <span class="sidebar-label">
                                Topics
                            </span>
                        </span>
                    </a>
                </li>


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
</aside> -->
