<?php
require __DIR__ . '/includes/bootstrap.php';

// This is the independent iLearn/MySQL application entry point.
$_SESSION['selected_app'] = 'ilearn_mysql';

$data = load_data();
$users = &$data['users'];
$posts = &$data['posts'];
$comments = &$data['comments'];

$page = $_GET['page'] ?? 'dashboard';
$allowedPages = ['dashboard', 'users', 'posts', 'comments', 'analytics', 'templates'];
if (!in_array($page, $allowedPages, true)) $page = 'dashboard';

$flash = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    switch ($action) {
        case 'reset':
            $data = require __DIR__ . '/data/defaults.php';
            $users = &$data['users'];
            $posts = &$data['posts'];
            $comments = &$data['comments'];
            save_data($data);
            flash('Demo data reset to defaults.', 'info');
            redirect_to('dashboard');

        case 'add_user':
            /*
             * Add User is currently display-only.
             * Do not insert into MySQL or the demo data file.
             */
            flash('Add User is currently display-only. No data was saved.', 'info');
            redirect_to('users');

        case 'edit_user':
            /*
             * Edit User is currently display-only.
             * Do not update MySQL or the demo data file.
             */
            flash('Edit User is currently display-only. No data was changed.', 'info');
            redirect_to('users');

        case 'toggle_user':
            $idx = find_index($users, $_POST['id'] ?? '');
            if ($idx >= 0) {
                $users[$idx]['status'] = $users[$idx]['status'] === 'Active' ? 'Suspended' : 'Active';
                save_data($data);
                flash($users[$idx]['name'] . ' is now ' . $users[$idx]['status'] . '.');
            }
            redirect_to('users');

        case 'delete_user':
            $idx = find_index($users, $_POST['id'] ?? '');
            if ($idx >= 0) {
                $name = $users[$idx]['name'];
                array_splice($users, $idx, 1);
                save_data($data);
                flash('User "' . $name . '" deleted.', 'error');
            }
            redirect_to('users');

        case 'add_post':
            $title = trim($_POST['title'] ?? '');
            if ($title === '') {
                flash('Post title is required.', 'error');
                redirect_to('posts');
            }
            $excerpt = trim($_POST['excerpt'] ?? '') ?: $title;
            array_unshift($posts, [
                'id' => new_id('post'),
                'title' => $title,
                'category' => $_POST['category'] ?? 'Stock Markets',
                'excerpt' => $excerpt,
                'content' => trim($_POST['content'] ?? '') ?: 'Full financial course lesson content...',
                'author' => 'Dinesh Bhatia',
                'authorRole' => 'Compliance Content Admin',
                'status' => $_POST['status'] ?? 'Draft',
                'views' => 0,
                'readTime' => '5 min read',
                'publishedDate' => 'Just now',
                'imageUrl' => 'https://images.unsplash.com/photo-1611974789855-9c2a0a7236a3?auto=format&fit=crop&w=600&q=80'
            ]);
            save_data($data);
            flash('Article "' . $title . '" created.');
            redirect_to('posts');

        case 'edit_post':
            $idx = find_index($posts, $_POST['id'] ?? '');
            if ($idx >= 0) {
                $posts[$idx]['title'] = trim($_POST['title'] ?? $posts[$idx]['title']);
                $posts[$idx]['category'] = $_POST['category'] ?? $posts[$idx]['category'];
                $posts[$idx]['status'] = $_POST['status'] ?? $posts[$idx]['status'];
                $posts[$idx]['excerpt'] = trim($_POST['excerpt'] ?? $posts[$idx]['excerpt']);
                save_data($data);
                flash('Post updated.');
            }
            redirect_to('posts');

        case 'toggle_post':
            $idx = find_index($posts, $_POST['id'] ?? '');
            if ($idx >= 0) {
                $posts[$idx]['status'] = $posts[$idx]['status'] === 'Published' ? 'Draft' : 'Published';
                save_data($data);
                flash('"' . $posts[$idx]['title'] . '" is now ' . $posts[$idx]['status'] . '.');
            }
            redirect_to('posts');

        case 'delete_post':
            $idx = find_index($posts, $_POST['id'] ?? '');
            if ($idx >= 0) {
                $title = $posts[$idx]['title'];
                array_splice($posts, $idx, 1);
                save_data($data);
                flash('Post "' . $title . '" deleted.', 'error');
            }
            redirect_to('posts');

        case 'add_draft':
            $title = trim($_POST['title'] ?? '');
            if ($title !== '') {
                $excerpt = trim($_POST['excerpt'] ?? '') ?: $title;
                array_unshift($posts, [
                    'id' => new_id('post'),
                    'title' => $title,
                    'category' => 'Stock Markets',
                    'excerpt' => $excerpt,
                    'content' => $excerpt,
                    'author' => 'Dinesh Bhatia',
                    'authorRole' => 'Compliance Admin',
                    'status' => 'Draft',
                    'views' => 0,
                    'readTime' => '3 min read',
                    'publishedDate' => 'Just now',
                    'imageUrl' => 'https://images.unsplash.com/photo-1611974789855-9c2a0a7236a3?auto=format&fit=crop&w=600&q=80'
                ]);
                save_data($data);
                flash('Draft saved to Posts.');
            }
            redirect_to('dashboard');

        case 'approve_comment':
        case 'flag_comment':
        case 'delete_comment':
            $idx = find_index($comments, $_POST['id'] ?? '');
            if ($idx >= 0) {
                if ($action === 'approve_comment') {
                    $comments[$idx]['status'] = 'Approved';
                    flash('Comment approved.');
                } elseif ($action === 'flag_comment') {
                    $comments[$idx]['status'] = 'Spam';
                    flash('Comment flagged as spam.', 'error');
                } else {
                    array_splice($comments, $idx, 1);
                    flash('Comment deleted.', 'error');
                }
                save_data($data);
            }
            redirect_to('comments');

        case 'approve_all':
            foreach ($comments as &$comment) {
                if ($comment['status'] === 'Pending') $comment['status'] = 'Approved';
            }
            unset($comment);
            save_data($data);
            flash('All pending comments approved.');
            redirect_to('comments');

        case 'reply_comment':
            $idx = find_index($comments, $_POST['id'] ?? '');
            $reply = trim($_POST['reply'] ?? '');
            if ($idx >= 0 && $reply !== '') {
                $comments[$idx]['status'] = 'Approved';
                $comments[$idx]['replies'][] = [
                    'id' => new_id('rep'),
                    'author' => 'Dinesh Bhatia (Compliance Admin)',
                    'content' => $reply,
                    'date' => 'Just now'
                ];
                save_data($data);
                flash('Reply sent and comment approved.');
            }
            redirect_to('comments');
    }
}

if (isset($_GET['export']) && $_GET['export'] === 'users') {
    csv_download('users.csv', ['Name','Email','Role','Status','Enrolled Courses','Joined Date'],
        array_map(fn($u) => [$u['name'],$u['email'],$u['role'],$u['status'],$u['enrolledCourses'],$u['joinedDate']], $users));
}
if (isset($_GET['export']) && $_GET['export'] === 'posts') {
    csv_download('posts.csv', ['Title','Category','Status','Author','Views','Published Date'],
        array_map(fn($p) => [$p['title'],$p['category'],$p['status'],$p['author'],$p['views'],$p['publishedDate']], $posts));
}

if (isset($_GET['download'])) {
    $downloadMap = [
        'header.php' => __DIR__ . '/components/header.php',
        'sidebar.php' => __DIR__ . '/components/sidebar.php',
        'dashboard.php' => __DIR__ . '/components/dashboard.php',
        'users.php' => __DIR__ . '/components/users.php',
        'posts.php' => __DIR__ . '/components/posts.php',
        'comments.php' => __DIR__ . '/components/comments.php',
        'analytics.php' => __DIR__ . '/components/analytics.php',
        'footer.php' => __DIR__ . '/components/footer.php',
        'style.css' => __DIR__ . '/assets/style.css'
    ];
    $name = $_GET['download'];
    if (isset($downloadMap[$name])) {
        header('Content-Type: text/plain; charset=utf-8');
        header('Content-Disposition: attachment; filename="' . basename($name) . '"');
        readfile($downloadMap[$name]);
        exit;
    }
}

$flash = consume_flash();
$pendingCount = count(array_filter($comments, fn($c) => $c['status'] === 'Pending'));
$publishedCount = count(array_filter($posts, fn($p) => $p['status'] === 'Published'));
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>iLearn CMS Portal — PHP</title>
<link rel="stylesheet" href="../shared/assets/main_header.css">
<link rel="stylesheet" href="../shared/assets/common.css">
<link rel="stylesheet" href="assets/style.css">
</head>
<body>
<?php include __DIR__ . '/../shared/components/main_header.php'; ?>
<?php include __DIR__ . '/components/icons.php'; ?>
<div class="cms-grid-layout">
<?php include __DIR__ . '/components/header.php'; ?>
<?php include __DIR__ . '/components/sidebar.php'; ?>

<main class="cms-main active">
<?php
switch ($page) {
    case 'users': include __DIR__ . '/components/users.php'; break;
    case 'posts': include __DIR__ . '/components/posts.php'; break;
    case 'comments': include __DIR__ . '/components/comments.php'; break;
    case 'analytics': include __DIR__ . '/components/analytics.php'; break;
    case 'templates': include __DIR__ . '/components/templates.php'; break;
    default: include __DIR__ . '/components/dashboard.php';
}
?>
</main>

<?php include __DIR__ . '/../shared/components/footer.php'; ?>
</div>

<?php if ($flash): ?>
<div class="toast" style="position:fixed;right:20px;bottom:20px;z-index:9999;">
  <span><?= e($flash['message']) ?></span>
</div>
<?php endif; ?>
</body>
</html>
