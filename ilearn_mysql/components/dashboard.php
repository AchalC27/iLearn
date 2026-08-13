<section class="view-header">
<div>
<h1><span>Executive CMS Dashboard</span><span class="title-pill">PHP Backend</span></h1>
<p class="sub">Financial learning content, learner records, compliance audits, and community moderation.</p>
</div>
<div><a class="btn btn-orange" href="index.php?page=posts&action=create"><svg class="icon icon-sm"><use href="#i-plus"/></svg><span>New Financial Article</span></a></div>
</section>

<div class="metrics-grid">
<div class="metric-card"><p class="metric-label">Total Learners</p><div class="metric-value"><?= number_format(count($users)) ?></div><p class="metric-sub">Registered CMS users</p></div>
<div class="metric-card"><p class="metric-label">CMS Articles &amp; Posts</p><div class="metric-value"><?= count($posts) ?></div><p class="metric-sub"><?= $publishedCount ?> Published</p></div>
<div class="metric-card"><p class="metric-label">Pending Moderation</p><div class="metric-value"><?= $pendingCount ?></div><p class="metric-sub">Comments requiring review</p></div>
<div class="metric-card"><p class="metric-label">Learning Index</p><div class="metric-value">92.4%</div><p class="metric-sub">Overall engagement score</p></div>
</div>

<section class="card" style="margin-top:14px;">
<header class="view-header" style="margin-bottom:8px;">
<div><h2 style="font-size:13px;font-weight:800;color:var(--navy);">Recent Posts</h2><p class="sub">Latest CMS content</p></div>
<a class="btn btn-outline btn-sm" href="index.php?page=posts">View All</a>
</header>
<div>
<?php foreach (array_slice($posts, 0, 4) as $p): ?>
<div class="recent-post-row">
<div class="left">
<span class="cat-chip"><?= e(substr($p['category'],0,4)) ?></span>
<div><h4><?= e($p['title']) ?></h4><p class="meta">By <?= e($p['author']) ?> • Category: <?= e($p['category']) ?> • <?= e($p['publishedDate']) ?></p></div>
</div>
<span class="status-chip <?= $p['status']==='Published'?'published':($p['status']==='Draft'?'draft':'scheduled') ?>"><?= e($p['status']) ?></span>
</div>
<?php endforeach; ?>
</div>
</section>

<section class="card" style="margin-top:14px;">
<h2 style="font-size:13px;font-weight:800;color:var(--navy);margin-bottom:12px;">Quick Draft</h2>
<form method="post" action="index.php" class="form-grid">
<input type="hidden" name="action" value="add_draft">
<div class="field"><label>Article Title</label><input id="draft-title" name="title" type="text" placeholder="Article Title..." required></div>
<div class="field"><label>Key Takeaways</label><textarea id="draft-excerpt" name="excerpt" rows="2" placeholder="Key takeaways..."></textarea></div>
<div><button class="btn btn-orange" type="submit">Save Draft</button></div>
</form>
</section>
