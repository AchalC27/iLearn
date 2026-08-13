<?php
require __DIR__ . '/includes/bootstrap.php';

// Independent MultiPie/PostgreSQL application entry point.
$_SESSION['selected_app'] = 'multipie_psql';

$data = load_data();
$users = &$data['users'];
$posts = &$data['posts'];
$comments = &$data['comments'];

$page = $_GET['page'] ?? 'dashboard';
$allowedPages = ['dashboard', 'users'];
if (!in_array($page, $allowedPages, true)) {
    $page = 'dashboard';
}

$pendingCount = count(array_filter($comments, fn($c) => strtolower((string)($c['status'] ?? '')) === 'pending'));
$publishedCount = count(array_filter($posts, fn($p) => strtolower((string)($p['status'] ?? '')) === 'published'));
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>MultiPie CMS Portal — PHP / PostgreSQL</title>
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
<?php
switch ($page) {
    case 'users':
        include __DIR__ . '/pages/users.php';
        break;
    default:
        include __DIR__ . '/pages/dashboard.php';
        break;
}
?>
</main>
<?php include __DIR__ . '/components/footer.php'; ?>
</div>
</body>
</html>
