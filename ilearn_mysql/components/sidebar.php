<aside id="ilearn-sidebar" class="cms-sidebar">
<nav id="sidebar-main-nav">
<div>
<p class="sidebar-group-title">Main Menu</p>
<ul role="list">
<li><a class="nav-btn <?= $page==='dashboard'?'active':'' ?>" href="index.php?page=dashboard"><span class="nav-btn-left"><svg class="icon"><use href="#i-layout"/></svg><span class="sidebar-label">Dashboard</span></span></a></li>
<li><a class="nav-btn <?= $page==='users'?'active':'' ?>" href="index.php?page=users"><span class="nav-btn-left"><svg class="icon"><use href="#i-users"/></svg><span class="sidebar-label">Users Directory</span></span><span class="sidebar-badge"><?= count($users) ?></span></a></li>
<li><a class="nav-btn <?= $page==='posts'?'active':'' ?>" href="index.php?page=posts"><span class="nav-btn-left"><svg class="icon"><use href="#i-file-text"/></svg><span class="sidebar-label">Posts &amp; Articles</span></span><span class="sidebar-badge"><?= count($posts) ?></span></a></li>
<li><a class="nav-btn <?= $page==='comments'?'active':'' ?>" href="index.php?page=comments"><span class="nav-btn-left"><svg class="icon"><use href="#i-message"/></svg><span class="sidebar-label">Comments</span></span><span class="sidebar-badge warn <?= $pendingCount===0?'hidden':'' ?>"><?= $pendingCount ?></span></a></li>
</ul>
</div>
<div>
<p class="sidebar-group-title">Analytics &amp; Inspector</p>
<ul role="list">
<li><a class="nav-btn <?= $page==='analytics'?'active':'' ?>" href="index.php?page=analytics"><span class="nav-btn-left"><svg class="icon"><use href="#i-bar-chart"/></svg><span class="sidebar-label">Learning Analytics</span></span></a></li>
<li><a class="nav-btn <?= $page==='templates'?'active':'' ?>" href="index.php?page=templates"><span class="nav-btn-left"><svg class="icon"><use href="#i-code"/></svg><span class="sidebar-label">HTML Code Inspector</span></span><span class="sidebar-pill-raw sidebar-label">Raw</span></a></li>
</ul>
</div>
</nav>
<div class="sidebar-footer-box">
<div class="row"><span class="dot"></span><span>iLearn Engine v4.2</span></div>
<p>High Density Architecture · PHP / HTML / CSS</p>
<form method="post" action="index.php">
<input type="hidden" name="action" value="reset">
<button class="sidebar-reset-btn" type="submit"><svg class="icon icon-sm"><use href="#i-refresh"/></svg> Reset Demo Data</button>
</form>
</div>
</aside>
