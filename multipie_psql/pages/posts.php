<?php

$perPage = 10;
$pageNumber = max(1, (int)($_GET['p'] ?? 1));

$userFilter    = trim((string)($_GET['user'] ?? ''));
$messageFilter = trim((string)($_GET['message'] ?? ''));
$typeFilter    = trim((string)($_GET['type'] ?? ''));
$statusFilter  = $_GET['status'] ?? 'all';

$posts = [];
$totalPosts = 0;
$totalPages = 1;
$dbError = null;
$userNames = [];

function postStatusLabel($status): string
{
    return (int)$status === 0 ? 'Active' : 'Inactive';
}

function getPostUserNames(PDO $appDb, array $userIds): array
{
    $userIds = array_values(array_unique(array_filter(array_map('intval', $userIds))));
    if (empty($userIds)) {
        return [];
    }

    $placeholders = implode(',', array_fill(0, count($userIds), '?'));
    $stmt = $appDb->prepare("
        SELECT id, display_name, username 
        FROM public.users 
        WHERE id IN ($placeholders)
    ");
    $stmt->execute($userIds);

    $result = [];
    while ($user = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $name = trim((string)($user['display_name'] ?? ''));
        if ($name === '') {
            $name = trim((string)($user['username'] ?? ''));
        }
        $result[(int)$user['id']] = $name !== '' ? $name : 'User #' . $user['id'];
    }

    return $result;
}

function postPageUrl(int $page, string $user, string $message, string $type, string $status): string
{
    $params = array_filter([
        'page'    => 'posts',
        'p'       => $page,
        'user'    => $user !== '' ? $user : null,
        'message' => $message !== '' ? $message : null,
        'type'    => $type !== '' ? $type : null,
        'status'  => $status !== 'all' ? $status : null,
    ], fn($val) => $val !== null);

    return 'index.php?' . http_build_query($params);
}

try {
    $pdo = getAppDb();
    $where = [];
    $params = [];

    if ($messageFilter !== '') {
        $where[] = 'message ILIKE :message';
        $params[':message'] = '%' . addcslashes($messageFilter, '%_') . '%';
    }

    if ($typeFilter !== '') {
        $where[] = 'type ILIKE :post_type';
        $params[':post_type'] = '%' . addcslashes($typeFilter, '%_') . '%';
    }

    if ($statusFilter !== '' && $statusFilter !== 'all') {
        $where[] = 'status = :status';
        $params[':status'] = (int)$statusFilter;
    }

    if ($userFilter !== '') {
        $userStmt = $pdo->prepare("
            SELECT id FROM public.users
            WHERE username ILIKE :user OR display_name ILIKE :user
        ");
        $userStmt->execute([':user' => '%' . addcslashes($userFilter, '%_') . '%']);
        $matchingUserIds = $userStmt->fetchAll(PDO::FETCH_COLUMN);

        if (empty($matchingUserIds)) {
            $where[] = '1 = 0';
        } else {
            $userKeys = [];
            foreach ($matchingUserIds as $i => $uid) {
                $k = ':uid_' . $i;
                $userKeys[] = $k;
                $params[$k] = (int)$uid;
            }
            $where[] = 'user_id IN (' . implode(',', $userKeys) . ')';
        }
    }

    $whereSql = !empty($where) ? 'WHERE ' . implode(' AND ', $where) : '';

    // Count Query
    $countStmt = $pdo->prepare("SELECT COUNT(*) FROM public.posts {$whereSql}");
    foreach ($params as $key => $value) {
        $countStmt->bindValue($key, $value, is_int($value) ? PDO::PARAM_INT : PDO::PARAM_STR);
    }
    $countStmt->execute();
    $totalPosts = (int)$countStmt->fetchColumn();

    $totalPages = max(1, (int)ceil($totalPosts / $perPage));
    $pageNumber = min($pageNumber, $totalPages);
    $offset = ($pageNumber - 1) * $perPage;

    // Posts Query
    $sql = "
        SELECT id, visibility, message, user_id, parent_post_id, created_at, 
               updated_at, likes_count, replies_count, widgets_data, meta_info, 
               type, reposts_count, root_post_id, status, views_count, propagation_info
        FROM public.posts
        {$whereSql}
        ORDER BY created_at DESC, id DESC
        LIMIT :limit OFFSET :offset
    ";

    $stmt = $pdo->prepare($sql);
    foreach ($params as $key => $value) {
        $stmt->bindValue($key, $value, is_int($value) ? PDO::PARAM_INT : PDO::PARAM_STR);
    }
    $stmt->bindValue(':limit', $perPage, PDO::PARAM_INT);
    $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
    $stmt->execute();

    $posts = $stmt->fetchAll(PDO::FETCH_ASSOC);

    if (!empty($posts)) {
        $userIds = array_column($posts, 'user_id');
        $userNames = getPostUserNames($pdo, $userIds);
    }

} catch (Throwable $e) {
    $dbError = $e->getMessage();
}
?>

<!-- Header -->
<section class="view-header">
    <div>
        <h1>Posts <span class="count-pill-navy"><?= number_format($totalPosts) ?> Total</span></h1>
        <p class="sub">Manage MultiPie posts and user-generated content.</p>
    </div>
</section>

<!-- Database Error -->
<?php if ($dbError): ?>
    <div class="alert-box">
        <strong>PostgreSQL connection failed</strong>
        <p><?= e($dbError) ?></p>
    </div>
<?php endif; ?>

<!-- Filters -->
<form class="filter-bar" method="get" action="index.php">
    <input type="hidden" name="page" value="posts">
    <div class="filter-controls">
        <input type="text" name="user" placeholder="User" value="<?= e($userFilter) ?>">
        <input type="text" name="message" placeholder="Message contains" value="<?= e($messageFilter) ?>">
        <input type="text" name="type" placeholder="Post Type" value="<?= e($typeFilter) ?>">

        <select class="select-plain" name="status">
            <option value="all" <?= $statusFilter === 'all' ? 'selected' : '' ?>>All Status</option>
            <option value="0" <?= $statusFilter === '0' ? 'selected' : '' ?>>Active</option>
            <option value="1" <?= $statusFilter === '1' ? 'selected' : '' ?>>Inactive</option>
        </select>

        <button type="submit" class="btn btn-outline btn-sm">Filter</button>
        <a href="index.php?page=posts" class="btn btn-outline btn-sm">View All</a>
    </div>
</form>

<!-- Result Count -->
<div class="filter-count">
    Showing <b><?= $totalPosts > 0 ? $offset + 1 : 0 ?></b> to <b><?= min($offset + $perPage, $totalPosts) ?></b> of <span><?= number_format($totalPosts) ?></span> Posts
</div>

<!-- Table -->
<div class="table-wrap">
    <table>
        <thead>
            <tr>
                <th>User</th>
                <th>Message</th>
                <th>Post Type</th>
                <th>Likes Count</th>
                <th>Repost Count</th>
                <th>Status</th>
                <th>Actions</th>
            </tr>
        </thead>
        <tbody>
            <?php if (empty($posts)): ?>
                <tr class="empty-row">
                    <td colspan="7">No Posts found.</td>
                </tr>
            <?php else: ?>
                <?php foreach ($posts as $post): 
                    $userId = (int)($post['user_id'] ?? 0);
                    $userName = $userNames[$userId] ?? 'User #' . $userId;
                    $statusText = postStatusLabel($post['status']);
                ?>
                    <tr>
                        <td><?= e($userName) ?></td>
                        <td>
                            <div style="max-width:360px; white-space:nowrap; overflow:hidden; text-overflow:ellipsis;" title="<?= e($post['message'] ?? '') ?>">
                                <?= e($post['message'] ?? '-') ?>
                            </div>
                        </td>
                        <td><?= e($post['type'] ?? '-') ?></td>
                        <td><?= number_format((int)($post['likes_count'] ?? 0)) ?></td>
                        <td><?= number_format((int)($post['reposts_count'] ?? 0)) ?></td>
                        <td>
                            <span class="status-badge <?= e($statusText) ?>">
                                <span class="dot-status <?= e($statusText) ?>"></span>
                                <?= e($statusText) ?>
                            </span>
                        </td>
                        <td>
                            <div class="table-actions">
                                <button type="button" class="mini-btn edit-post-btn" data-post='<?= htmlspecialchars(json_encode($post), ENT_QUOTES, 'UTF-8') ?>'>Edit</button>
                                <button type="button" class="mini-btn danger" onclick="postDeleteDisabled()">Delete</button>
                            </div>
                        </td>
                    </tr>
                <?php endforeach; ?>
            <?php endif; ?>
        </tbody>
    </table>
</div>

<!-- Pagination -->
<?php if ($totalPages > 1): 
    $startPage = max(1, $pageNumber - 2);
    $endPage   = min($totalPages, $pageNumber + 2);
?>
    <div class="pagination">
        <?php if ($pageNumber > 1): ?>
            <a href="<?= e(postPageUrl($pageNumber - 1, $userFilter, $messageFilter, $typeFilter, $statusFilter)) ?>" class="pagination-btn">Previous</a>
        <?php endif; ?>

        <?php for ($i = $startPage; $i <= $endPage; $i++): ?>
            <a href="<?= e(postPageUrl($i, $userFilter, $messageFilter, $typeFilter, $statusFilter)) ?>" class="pagination-btn <?= $i === $pageNumber ? 'active' : '' ?>"><?= $i ?></a>
        <?php endfor; ?>

        <?php if ($pageNumber < $totalPages): ?>
            <a href="<?= e(postPageUrl($pageNumber + 1, $userFilter, $messageFilter, $typeFilter, $statusFilter)) ?>" class="pagination-btn">Next</a>
        <?php endif; ?>
    </div>
<?php endif; ?>

<!-- Modal -->
<div id="post-modal" class="user-popup-overlay" hidden>
    <div class="user-popup" role="dialog" aria-modal="true">
        <div class="user-popup-header">
            <div>
                <h2>Edit Post</h2>
                <p>Post information — display only. Saving is disabled.</p>
            </div>
            <button type="button" class="user-popup-close" onclick="closePostModal()">&times;</button>
        </div>

        <div class="user-popup-grid">
            <div class="field"><label>ID</label><input id="post-id" type="text" disabled></div>
            <div class="field"><label>User ID</label><input id="post-user-id" type="text" disabled></div>
            <div class="field"><label>Post Type</label><input id="post-type" type="text"></div>
            <div class="field"><label>Visibility</label><input id="post-visibility" type="number"></div>
            <div class="field">
                <label>Status</label>
                <select id="post-status">
                    <option value="0">Active</option>
                    <option value="1">Inactive</option>
                </select>
            </div>
            <div class="field"><label>Likes Count</label><input id="post-likes" type="number"></div>
            <div class="field"><label>Repost Count</label><input id="post-reposts" type="number"></div>
            <div class="field"><label>Replies Count</label><input id="post-replies" type="number"></div>
            <div class="field"><label>Views Count</label><input id="post-views" type="number"></div>
            <div class="field"><label>Created</label><input id="post-created" type="text" disabled></div>
            <div class="field"><label>Updated</label><input id="post-updated" type="text" disabled></div>
            <div class="field" style="grid-column:1/-1;"><label>Message</label><textarea id="post-message" rows="5" placeholder="Enter message"></textarea></div>
            <div class="field" style="grid-column:1/-1;"><label>Meta Info</label><textarea id="post-meta-info" rows="4" placeholder="Meta information"></textarea></div>
        </div>

        <div class="user-popup-footer">
            <button type="button" class="btn btn-outline" onclick="closePostModal()">Close</button>
            <button type="button" class="btn btn-orange" onclick="postSaveDisabled()">Save Changes</button>
        </div>
    </div>
</div>

<script>
const fields = ['id', 'user-id', 'type', 'visibility', 'status', 'likes', 'reposts', 'replies', 'views', 'created', 'updated', 'message', 'meta-info'];
const modal = document.getElementById('post-modal');

document.addEventListener('click', (e) => {
    const btn = e.target.closest('.edit-post-btn');
    if (btn) {
        const post = JSON.parse(btn.dataset.post || '{}');
        const mapping = {
            'id': post.id,
            'user-id': post.user_id,
            'type': post.type,
            'visibility': post.visibility,
            'status': post.status ?? '0',
            'likes': post.likes_count ?? '0',
            'reposts': post.reposts_count ?? '0',
            'replies': post.replies_count ?? '0',
            'views': post.views_count ?? '0',
            'created': post.created_at,
            'updated': post.updated_at,
            'message': post.message,
            'meta-info': post.meta_info
        };

        Object.entries(mapping).forEach(([id, val]) => {
            const el = document.getElementById(`post-${id}`);
            if (el) el.value = val ?? '';
        });

        modal.hidden = false;
        document.body.style.overflow = 'hidden';
    }
});

function closePostModal() {
    modal.hidden = true;
    document.body.style.overflow = '';
}

function postSaveDisabled() {
    alert('Save is currently disabled. No changes have been made to the database.');
}

function postDeleteDisabled() {
    alert('Delete is currently disabled. No database record has been deleted.');
}

document.addEventListener('keydown', (e) => {
    if (e.key === 'Escape' && !modal.hidden) closePostModal();
});

modal?.addEventListener('click', (e) => {
    if (e.target === modal) closePostModal();
});
</script>