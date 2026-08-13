<section class="view-header">
<div>
<h1><span>Executive MultiPie Dashboard</span><span class="title-pill">PHP Backend</span></h1>
<p class="sub">Financial learning content, learner records, compliance audits, and community moderation.</p>
</div>
<div><a class="btn btn-orange" href="index.php?page=dashboard"><svg class="icon icon-sm"><use href="#i-plus"/></svg><span>New Financial Article</span></a></div>
</section>

<div class="metrics-grid">
<div class="metric-card"><p class="metric-label">Total Learners</p><div class="metric-value"><?= number_format(count($users)) ?></div><p class="metric-sub">Registered MultiPie users</p></div>
<div class="metric-card"><p class="metric-label">CMS Articles &amp; Posts</p><div class="metric-value"><?= count($posts) ?></div><p class="metric-sub"><?= $publishedCount ?> Published</p></div>
<div class="metric-card"><p class="metric-label">Pending Moderation</p><div class="metric-value"><?= $pendingCount ?></div><p class="metric-sub">Comments requiring review</p></div>
<div class="metric-card"><p class="metric-label">Learning Index</p><div class="metric-value">92.4%</div><p class="metric-sub">Overall engagement score</p></div>
</div>

<section class="card" style="margin-top:14px;">
<header class="view-header" style="margin-bottom:8px;">
<div><h2 style="font-size:13px;font-weight:800;color:var(--navy);">Recent Posts</h2><p class="sub">Latest CMS content</p></div>
<a class="btn btn-outline btn-sm" href="index.php?page=dashboard">View All</a>
</header>
<div>
<?php foreach (array_slice($posts, 0, 4) as $p): ?>
<div class="recent-post-row">
<div class="left">
<span class="cat-chip"><?= e(substr((string)($p['category'] ?? 'POST'),0,4)) ?></span>
<div><h4><?= e($p['title'] ?? 'Untitled') ?></h4><p class="meta">By <?= e($p['author'] ?? 'Unknown') ?> • Category: <?= e($p['category'] ?? 'General') ?> • <?= e($p['publishedDate'] ?? ($p['date'] ?? '')) ?></p></div>
</div>
<span class="status-chip <?= strtolower((string)($p['status'] ?? ''))==='published'?'published':(strtolower((string)($p['status'] ?? ''))==='draft'?'draft':'scheduled') ?>"><?= e($p['status'] ?? 'Draft') ?></span>
</div>
<?php endforeach; ?>
</div>
</section>

<section class="card" style="margin-top:14px;">
<h2 style="font-size:13px;font-weight:800;color:var(--navy);margin-bottom:12px;">Quick Draft</h2>
<form class="form-grid" onsubmit="return false;">
<div class="field"><label>Article Title</label><input type="text" placeholder="Article Title..."></div>
<div class="field"><label>Key Takeaways</label><textarea rows="2" placeholder="Key takeaways..."></textarea></div>
<div><button class="btn btn-orange" type="button">Save Draft</button></div>
</form>
</section>
