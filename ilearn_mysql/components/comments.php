<?php
$statusFilter = $_GET['status'] ?? 'Pending';
$q = trim($_GET['q'] ?? '');
$filteredComments = array_values(array_filter($comments, function($c) use ($statusFilter,$q) {
    $statusMatch = $statusFilter === 'all' || $c['status'] === $statusFilter;
    $searchMatch = $q === '' || stripos($c['authorName'],$q)!==false || stripos($c['content'],$q)!==false || stripos($c['postTitle'],$q)!==false;
    return $statusMatch && $searchMatch;
}));
$counts = [
 'Pending'=>count(array_filter($comments,fn($c)=>$c['status']==='Pending')),
 'Approved'=>count(array_filter($comments,fn($c)=>$c['status']==='Approved')),
 'Spam'=>count(array_filter($comments,fn($c)=>$c['status']==='Spam'))
];
?>
<section class="view-header">
<div><div style="display:flex;align-items:center;gap:8px;"><h1>Community Discussion &amp; Moderation Queue</h1><span class="count-pill-amber"><?= $counts['Pending'] ?> Pending SEBI Audit</span></div><p class="sub">Student questions, unverified financial advice filtering, and regulatory compliance checks.</p></div>
<?php if ($counts['Pending']): ?><form method="post"><input type="hidden" name="action" value="approve_all"><button class="btn btn-green" type="submit"><svg class="icon icon-sm"><use href="#i-check2"/></svg> Approve All Pending</button></form><?php endif; ?>
</section>
<form class="filter-bar" method="get" action="index.php">
<input type="hidden" name="page" value="comments">
<div class="chip-row">
<?php foreach(['Pending'=>'Pending','Approved'=>'Approved','Spam'=>'Spam/Flagged','all'=>'All Comments'] as $v=>$label): ?><a class="chip <?= $statusFilter===$v?'active':'' ?>" href="index.php?page=comments&status=<?= urlencode($v) ?>"><?= e($label) ?><?= $v!=='all'?' ('.$counts[$v].')':'' ?></a><?php endforeach; ?>
</div>
<div class="search-box"><svg class="icon"><use href="#i-search"/></svg><input name="q" value="<?= e($q) ?>" placeholder="Search commenter or keyword..."></div><button class="btn btn-outline btn-sm" type="submit">Search</button>
</form>
<div class="comments-list">
<?php if (!$filteredComments): ?><div style="background:#fff;padding:24px;text-align:center;border-radius:6px;border:1px solid var(--slate-200);color:var(--slate-500);font-size:12px;">No comments in this moderation view.</div><?php endif; ?>
<?php foreach ($filteredComments as $c): $initials=''; foreach(preg_split('/\s+/',trim($c['authorName'])) as $n){$initials.=substr($n,0,1);} ?>
<article class="comment-card">
<div class="comment-top"><div class="comment-author"><div class="avatar-square"><?= e($initials) ?></div><div><h4><?= e($c['authorName']) ?> <span>• <?= e($c['authorRole']) ?></span></h4><p class="on">On Article: <span><?= e($c['postTitle']) ?></span></p></div></div><span class="comment-status <?= e($c['status']) ?>"><?= e($c['status']) ?></span></div>
<div class="comment-body">&quot;<?= e($c['content']) ?>&quot;</div>
<?php if (!empty($c['replies'])): ?><div class="reply-thread"><?php foreach($c['replies'] as $r): ?><div class="reply-item"><div class="row"><span><?= e($r['author']) ?></span><span><?= e($r['date']) ?></span></div><p><?= e($r['content']) ?></p></div><?php endforeach; ?></div><?php endif; ?>
<div class="comment-controls"><span class="submitted">Submitted <?= e($c['submittedTime']) ?></span><div class="actions">
<?php if ($c['status']!=='Approved'): ?><form method="post" style="display:inline"><input type="hidden" name="action" value="approve_comment"><input type="hidden" name="id" value="<?= e($c['id']) ?>"><button class="mini-btn green" type="submit">Approve</button></form><?php endif; ?>
<a class="mini-btn" href="index.php?page=comments&reply=<?= e($c['id']) ?>">Reply</a>
<?php if ($c['status']!=='Spam'): ?><form method="post" style="display:inline"><input type="hidden" name="action" value="flag_comment"><input type="hidden" name="id" value="<?= e($c['id']) ?>"><button class="mini-btn warn" type="submit">Flag Spam</button></form><?php endif; ?>
<form method="post" style="display:inline"><input type="hidden" name="action" value="delete_comment"><input type="hidden" name="id" value="<?= e($c['id']) ?>"><button class="mini-btn danger" type="submit">Delete</button></form>
</div></div>
<?php if (($_GET['reply'] ?? '') === $c['id']): ?><form method="post" class="reply-form" style="margin-top:12px;"><input type="hidden" name="action" value="reply_comment"><input type="hidden" name="id" value="<?= e($c['id']) ?>"><p>Replying as Official iLearn Instructor:</p><textarea name="reply" rows="2" placeholder="Write authoritative, compliant answer..." required></textarea><div class="btns"><a class="txt-btn" href="index.php?page=comments">Cancel</a><button class="btn btn-orange btn-sm" type="submit">Send Reply</button></div></form><?php endif; ?>
</article>
<?php endforeach; ?>
</div>
