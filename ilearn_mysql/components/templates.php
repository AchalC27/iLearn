<?php
$files = [
 'header.php'=>'components/header.php',
 'sidebar.php'=>'components/sidebar.php',
 'dashboard.php'=>'components/dashboard.php',
 'users.php'=>'components/users.php',
 'posts.php'=>'components/posts.php',
 'comments.php'=>'components/comments.php',
 'analytics.php'=>'components/analytics.php',
 'footer.php'=>'components/footer.php',
 'style.css'=>'assets/style.css'
];
$selected = $_GET['file'] ?? 'header.php';
if (!isset($files[$selected])) $selected = 'header.php';
$path = __DIR__ . '/../' . $files[$selected];
$source = file_exists($path) ? file_get_contents($path) : '';
?>
<section class="view-header"><div><h1>PHP Component &amp; CSS Inspector</h1><p class="sub">Inspect the actual split files used by this PHP application.</p></div></section>
<div class="tmpl-body" style="height:70vh;border:1px solid var(--slate-200);border-radius:8px;overflow:hidden;">
<div class="tmpl-file-list">
<p class="title">Select Component File</p>
<?php foreach($files as $name=>$file): ?><a class="tmpl-file-btn <?= $name===$selected?'active':'' ?>" href="index.php?page=templates&file=<?= urlencode($name) ?>"><svg class="icon icon-sm"><use href="#i-file-code"/></svg><span><?= e($name) ?></span></a><?php endforeach; ?>
</div>
<div class="tmpl-code-area"><div class="tmpl-toolbar"><div><span class="fname"><?= e($selected) ?></span><p class="fdesc"><?= e($files[$selected]) ?></p></div><a class="tmpl-btn dl" href="index.php?page=templates&download=<?= urlencode($selected) ?>">Download</a></div><pre class="tmpl-pre"><code><?= e($source) ?></code></pre></div>
</div>
