<?php
/**
 * Main application entry point.
 *
 * This file ONLY decides which application should be opened.
 * Each application has its own index.php and can be developed
 * independently.
 */

declare(strict_types=1);

$app = $_GET['app'] ?? 'ilearn_mysql';

$allowedApps = [
    'ilearn_mysql',
    'multipie_psql'
];

if (!in_array($app, $allowedApps, true)) {
    $app = 'ilearn_mysql';
}

// Keep the selected application in the session.
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$_SESSION['selected_app'] = $app;

// Preserve the page when switching applications.
$page = $_GET['page'] ?? 'dashboard';

// Redirect to the selected application's own entry point.
$target = $app . '/index.php';

$params = ['page' => $page];

// Preserve any additional query parameters that are useful to the app.
foreach ($_GET as $key => $value) {
    if ($key === 'app' || $key === 'page') {
        continue;
    }

    if (is_scalar($value)) {
        $params[$key] = $value;
    }
}

$query = http_build_query($params);

header('Location: ' . $target . ($query !== '' ? '?' . $query : ''));
exit;
