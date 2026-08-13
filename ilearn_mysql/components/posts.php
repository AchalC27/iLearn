<?php
$q = trim($_GET['q'] ?? '');
$cat = $_GET['cat'] ?? 'all';
$editPost = isset($_GET['edit']) ? find_by_id($posts, $_GET['edit']) : null;
$filteredPosts = array_values(array_filter($posts, function($p) use ($q,$cat) {
    return ($cat === 'all' || $p['category'] === $cat) &&
      ($q === '' || stripos($p['title'],$q)!==false || stripos($p['category'],$q)!==false || stripos($p['author'],$q)!==false);
}));
?>
<section class="view-header">
<div><div style="display:flex;align-items:center;gap:8px;"><h1>Financial Articles &amp; Educational Posts</h1><span class="count-pill-green"><?= $publishedCount ?> Published</span></div><p class="sub">Stock market tutorials, mutual fund guides, tax planning, and trading strategy posts.</p></div>
<a class="btn btn-orange" href="index.php?page=posts&action=create"><svg class="icon icon-sm"><use href="#i-plus"/></svg> Create New Post</a>
</section>
<form class="filter-bar" method="get" action="index.php" style="flex-direction:column;align-items:stretch;">
<input type="hidden" name="page" value="posts">
<div style="display:flex;flex-wrap:wrap;align-items:center;justify-content:space-between;gap:12px;">
<div class="chip-row">
<?php foreach (['all'=>'All Categories','Stock Markets'=>'Stock Markets','Mutual Funds'=>'Mutual Funds','Derivatives'=>'Derivatives','Tax & Wealth'=>'Tax & Wealth'] as $value=>$label): ?>
<a class="chip <?= $cat===$value?'active':'' ?>" href="index.php?page=posts&cat=<?= urlencode($value) ?>"><?= e($label) ?></a>
<?php endforeach; ?>
</div>
<div style="display:flex;gap:10px;align-items:center;"><div class="search-box"><svg class="icon"><use href="#i-search"/></svg><input name="q" value="<?= e($q) ?>" placeholder="Search post title or author..."></div><button class="btn btn-outline btn-sm" type="submit">Search</button><a class="btn btn-outline btn-sm" href="index.php?page=posts&export=posts">Export CSV</a></div>
</div></form>

<div class="posts-grid">
<?php foreach ($filteredPosts as $p): ?>
<article class="post-card">
<div><div class="post-thumb"><img src="<?= e($p['imageUrl']) ?>" alt="<?= e($p['title']) ?>"><span class="thumb-cat"><?= e($p['category']) ?></span><span class="thumb-status <?= e($p['status']) ?>"><?= e($p['status']) ?></span></div>
<div class="post-body"><p class="meta"><?= e($p['publishedDate']) ?> • <?= e($p['readTime']) ?></p><h3><?= e($p['title']) ?></h3><p><?= e($p['excerpt']) ?></p></div></div>
<div class="post-footer"><span class="author">By <?= e($p['author']) ?></span><div class="actions">
<a class="mini-btn" href="index.php?page=posts&edit=<?= e($p['id']) ?>">Edit</a>
<form method="post" style="display:inline"><input type="hidden" name="action" value="toggle_post"><input type="hidden" name="id" value="<?= e($p['id']) ?>"><button class="mini-btn <?= $p['status']==='Published'?'warn':'green' ?>" type="submit"><?= $p['status']==='Published'?'Unpublish':'Publish' ?></button></form>
<form method="post" style="display:inline"><input type="hidden" name="action" value="delete_post"><input type="hidden" name="id" value="<?= e($p['id']) ?>"><button class="mini-btn danger" type="submit">Delete</button></form>
</div></div>
</article>
<?php endforeach; ?>
<?php if (!$filteredPosts): ?><div style="grid-column:1/-1;background:#fff;padding:24px;text-align:center;border-radius:6px;border:1px solid var(--slate-200);color:var(--slate-500);font-size:12px;">No CMS posts match the selected category or search filter.</div><?php endif; ?>
</div>

<?php if ($editPost || (($_GET['action'] ?? '') === 'create')): ?>
<div class="card" style="margin-top:14px;">
<h2 style="font-size:13px;font-weight:800;color:var(--navy);margin-bottom:12px;"><?= $editPost ? 'Edit Post' : 'Create New Post' ?></h2>
<form method="post" action="index.php" class="form-grid">
<input type="hidden" name="action" value="<?= $editPost ? 'edit_post' : 'add_post' ?>">
<?php if ($editPost): ?><input type="hidden" name="id" value="<?= e($editPost['id']) ?>"><?php endif; ?>
<div class="field"><label>Title</label><input name="title" required value="<?= e($editPost['title'] ?? '') ?>"></div>
<div class="field"><label>Category</label><select name="category"><?php foreach(['Stock Markets','Mutual Funds','Derivatives','Tax & Wealth','Financial Literacy'] as $c): ?><option <?= (($editPost['category'] ?? '')===$c)?'selected':'' ?>><?= e($c) ?></option><?php endforeach; ?></select></div>
<div class="field"><label>Status</label><select name="status"><option <?= (($editPost['status'] ?? '')==='Published')?'selected':'' ?>>Published</option><option <?= (($editPost['status'] ?? '')!=='Published')?'selected':'' ?>>Draft</option></select></div>
<div class="field"><label>Excerpt</label><textarea name="excerpt" rows="3"><?= e($editPost['excerpt'] ?? '') ?></textarea></div>
<?php if (!$editPost): ?><div class="field"><label>Content</label><textarea name="content" rows="5"></textarea></div><?php endif; ?>
<div><button class="btn btn-navy" type="submit">Save Post</button> <a class="btn btn-outline" href="index.php?page=posts">Cancel</a></div>
</form></div>
<?php endif; ?>
