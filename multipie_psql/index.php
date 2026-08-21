<?php
require_once __DIR__ . '/includes/bootstrap.php';

$_SESSION['selected_app'] = 'multipie_psql';

/*
|--------------------------------------------------------------------------
| Admin Sidebar / Section
|--------------------------------------------------------------------------
*/
if (isset($_GET['sidebar'])) {
    if ($_GET['sidebar'] === 'auth') {
        $_SESSION['admin_sidebar'] = 'auth';
    } elseif ($_GET['sidebar'] === 'main') {
        $_SESSION['admin_sidebar'] = 'main';
    }
}

$activeSidebar = $_SESSION['admin_sidebar'] ?? 'main';

/*
|--------------------------------------------------------------------------
| Page Routing
|--------------------------------------------------------------------------
*/
// Set the default landing page depending on the active sidebar section
$defaultPage = ($activeSidebar === 'auth') ? 'auth_dashboard' : 'dashboard';
$page = $_GET['page'] ?? $defaultPage;

// Clean out folder prefixes if passed via URL (e.g., 'auth/auth_dashboard' -> 'auth_dashboard')
$page = basename($page);

$allowedPages = [
    'dashboard',
    'users',
    'posts',
    'comments',
    'analytics',
    'amcs',
    'announcements',
    'brokers',
    'cams_feedbacks',
    'companies',
    'mutual_funds',
    'exchange_holidays',
    'external_links',
    'holdings_statements',
    'instrument_categories',
    'multi_boxes',
    'notification_settings',
    'products',
    'market_indices',
    'profane_words',
    'reports',
    'rewards',
    'settings',
    'showcases',
    'subscribers',
    'topics',
    'stories',
    'auth_dashboard',
    'auth_users',
    'auth_application',
    'auth_referral_codes',
    'auth_settings'
];

if (!in_array($page, $allowedPages, true)) {
    $page = $defaultPage;
}

// Build file path based on section
if ($activeSidebar === 'auth') {
    $pageFile = __DIR__ . '/pages/auth/' . $page . '.php';
} else {
    $pageFile = __DIR__ . '/pages/' . $page . '.php';
}

// Fallback to dashboard if the resolved file does not exist
if (!file_exists($pageFile)) {
    $pageFile = __DIR__ . '/pages/dashboard.php';
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>MultiPie CMS Portal — PostgreSQL</title>

    <link rel="stylesheet" href="assets/main_header.css">
    <link rel="stylesheet" href="assets/common.css">
    <link rel="stylesheet" href="assets/style.css">
</head>

<body>

<?php include __DIR__ . '/components/main_header.php'; ?>

<?php include __DIR__ . '/components/icons.php'; ?>

<div class="cms-grid-layout">

    <?php include __DIR__ . '/components/header.php'; ?>

    <?php
    if ($activeSidebar === 'auth') {
        include __DIR__ . '/components/sidebar_auth.php';
    } else {
        include __DIR__ . '/components/sidebar.php';
    }
    ?>

    <main class="cms-main active">
        <?php 
        if (file_exists($pageFile)) {
            include $pageFile;
        } else {
            echo '<div class="alert alert-danger p-4 m-3">Page not found: ' . htmlspecialchars($page) . '</div>';
        }
        ?>
    </main>

    <?php include __DIR__ . '/components/footer.php'; ?>

</div>

</body>
</html>