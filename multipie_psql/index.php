<?php
require_once __DIR__ . '/includes/bootstrap.php';

$_SESSION['selected_app'] = 'multipie_psql';

$page = $_GET['page'] ?? 'dashboard';

$allowedPages = [
    'dashboard',
    'users',
    'posts',
    'comments',
    'analytics',
];

if (!in_array($page, $allowedPages, true)) {
    $page = 'dashboard';
}

$pageFile = __DIR__ . '/pages/' . $page . '.php';

if (!is_file($pageFile)) {
    http_response_code(404);
    die('Page not found.');
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

    <?php include __DIR__ . '/components/sidebar.php'; ?>

    <main class="cms-main active">
        <?php include $pageFile; ?>
    </main>

    <?php include __DIR__ . '/components/footer.php'; ?>

</div>

</body>
</html>
