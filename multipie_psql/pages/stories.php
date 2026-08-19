<?php
/*
 |--------------------------------------------------------------------------
 | Stories
 |--------------------------------------------------------------------------
 | Database: multipie_main_prod
 | Table:    public.stories
 |
 | User information is resolved from multipie_auth_prod.public.users.
 | This page is intentionally read-only. Delete/Edit controls are UI only
 | and do not modify the corporate database.
 */

$dbError = null;
$stories = [];
$totalStories = 0;
$perPage = 10;
$currentPage = max(1, (int)($_GET['p'] ?? 1));
$totalPages = 1;

$usernameStarts = trim((string)($_GET['username'] ?? ''));
$idEquals = trim((string)($_GET['id'] ?? ''));
$statusFilter = trim((string)($_GET['status'] ?? ''));

function storyStatusLabel($status): string
{
    $value = strtolower(trim((string)$status));

    if ($value === 'published' || $value === 'active' || $value === '1') {
        return 'Published';
    }

    if ($value === 'draft' || $value === '0') {
        return 'Draft';
    }

    if ($value === 'inactive' || $value === '2') {
        return 'Inactive';
    }

    return $status === null || $status === '' ? '-' : ucfirst((string)$status);
}

function storyVisibilityLabel($visibility): string
{
    $value = strtolower(trim((string)$visibility));

    if ($value === '1') return 'Public Visible';
    if ($value === '0') return 'Private';
    if ($value === 'public_visible') return 'Public Visible';
    if ($value === 'private') return 'Private';

    return $visibility === null || $visibility === '' ? '-' : ucfirst(str_replace('_', ' ', (string)$visibility));
}

function storyPromoted($metaInfo): bool
{
    if (is_string($metaInfo)) {
        $decoded = json_decode($metaInfo, true);
        $metaInfo = is_array($decoded) ? $decoded : [];
    }

    if (!is_array($metaInfo)) {
        return false;
    }

    foreach (['promoted', 'is_promoted', 'isPromoted'] as $key) {
        if (array_key_exists($key, $metaInfo)) {
            return filter_var($metaInfo[$key], FILTER_VALIDATE_BOOLEAN);
        }
    }

    return false;
}

function storyDate($value): string
{
    if (!$value) return '-';

    try {
        return (new DateTime((string)$value))->format('d M Y H:i');
    } catch (Throwable $e) {
        return (string)$value;
    }
}

function storyQuery(array $overrides = []): string
{
    $query = [
        'page' => 'stories',
        'p' => (int)($_GET['p'] ?? 1),
        'username' => $_GET['username'] ?? '',
        'id' => $_GET['id'] ?? '',
        'status' => $_GET['status'] ?? '',
    ];

    foreach ($overrides as $key => $value) {
        $query[$key] = $value;
    }

    return http_build_query($query);
}

try {
    $appDb = getAppDb();
    $usersDb = getUsersDb();

    $where = [];
    $params = [];

    if ($usernameStarts !== '') {
        $where[] = 'LOWER(COALESCE(u.username, \'\')) LIKE LOWER(:username_prefix)';
        $params[':username_prefix'] = $usernameStarts . '%';
    }

    if ($idEquals !== '' && ctype_digit($idEquals)) {
        $where[] = 's.id = :story_id';
        $params[':story_id'] = (int)$idEquals;
    }

    if ($statusFilter !== '') {
        if (ctype_digit($statusFilter)) {
            $where[] = 's.status = :status';
            $params[':status'] = (int)$statusFilter;
        } elseif ($statusFilter === 'published') {
            $where[] = 's.status IN (1, 0)';
        }
    }

    $whereSql = $where ? 'WHERE ' . implode(' AND ', $where) : '';

    /*
     * Users live in auth_prod, while stories live in main_prod. PostgreSQL
     * cannot directly join two separate databases, so story rows are first
     * loaded from main_prod and usernames are resolved from auth_prod.
     */
    $countSql = "SELECT COUNT(*) FROM public.stories s $whereSql";
    $countStmt = $appDb->prepare($countSql);
    foreach ($params as $key => $value) {
        $countStmt->bindValue($key, $value, is_int($value) ? PDO::PARAM_INT : PDO::PARAM_STR);
    }
    $countStmt->execute();
    $totalStories = (int)$countStmt->fetchColumn();

    $totalPages = max(1, (int)ceil($totalStories / $perPage));
    if ($currentPage > $totalPages) {
        $currentPage = $totalPages;
    }

    $offset = ($currentPage - 1) * $perPage;

    $sql = "
        SELECT
            s.id,
            s.user_id,
            s.post_id,
            s.created_at,
            s.updated_at,
            s.visibility,
            s.message,
            s.likes_count,
            s.widgets_data,
            s.meta_info,
            s.status,
            s.views_count
        FROM public.stories s
        $whereSql
        ORDER BY s.id DESC
        LIMIT :limit OFFSET :offset
    ";

    $stmt = $appDb->prepare($sql);
    foreach ($params as $key => $value) {
        $stmt->bindValue($key, $value, is_int($value) ? PDO::PARAM_INT : PDO::PARAM_STR);
    }
    $stmt->bindValue(':limit', $perPage, PDO::PARAM_INT);
    $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
    $stmt->execute();
    $stories = $stmt->fetchAll(PDO::FETCH_ASSOC);

    /* Resolve the user IDs from the auth database. */
    $userIds = [];
    foreach ($stories as $story) {
        if ($story['user_id'] !== null) {
            $userIds[(string)$story['user_id']] = (int)$story['user_id'];
        }
    }

    $usersById = [];

    if ($userIds) {
        $placeholders = [];
        $userParams = [];
        $i = 0;

        foreach ($userIds as $userId) {
            $key = ':uid' . $i++;
            $placeholders[] = $key;
            $userParams[$key] = $userId;
        }

        $userStmt = $usersDb->prepare(
            'SELECT id, username, email, mobile, display_name FROM public.users WHERE id IN (' . implode(',', $placeholders) . ')'
        );

        foreach ($userParams as $key => $value) {
            $userStmt->bindValue($key, $value, PDO::PARAM_INT);
        }

        $userStmt->execute();

        foreach ($userStmt->fetchAll(PDO::FETCH_ASSOC) as $user) {
            $usersById[(string)$user['id']] = $user;
        }
    }

    foreach ($stories as &$story) {
        $user = $usersById[(string)$story['user_id']] ?? null;
        $story['username'] = $user['username'] ?? ('User - ' . ($story['user_id'] ?? '-'));
        $story['email'] = $user['email'] ?? '';
        $story['mobile'] = $user['mobile'] ?? '';
        $story['display_name'] = $user['display_name'] ?? '';
        $story['status_label'] = storyStatusLabel($story['status']);
        $story['visibility_label'] = storyVisibilityLabel($story['visibility']);
        $story['promoted'] = storyPromoted($story['meta_info']);
    }
    unset($story);

} catch (Throwable $e) {
    $dbError = $e->getMessage();
    $stories = [];
    $totalStories = 0;
    $totalPages = 1;
    $currentPage = 1;
}
?>

<section class="view-header">
    <div>
        <h1>
            List of Stories
            <span class="count-pill-navy"><?= number_format($totalStories) ?> Total</span>
        </h1>
        <p class="sub">Manage MultiPie stories, users, visibility and publication status.</p>
    </div>
</section>

<?php if ($dbError): ?>
    <div class="alert-box">
        <div class="row">
            <div>
                <h4>PostgreSQL connection failed</h4>
                <p><?= e($dbError) ?></p>
            </div>
        </div>
    </div>
<?php endif; ?>

<form class="filter-bar" method="get" action="index.php">
    <input type="hidden" name="page" value="stories">
    <input type="hidden" name="p" value="1">

    <div class="filter-controls">
        <div class="search-box">
            <svg class="icon"><use href="#i-search"/></svg>
            <input
                name="username"
                type="text"
                value="<?= e($usernameStarts) ?>"
                placeholder="User Username starts with"
            >
        </div>

        <input
            class="input-plain"
            name="id"
            type="text"
            value="<?= e($idEquals) ?>"
            placeholder="ID equals"
        >

        <select class="select-plain" name="status">
            <option value="">-Any Status-</option>
            <option value="1" <?= $statusFilter === '1' ? 'selected' : '' ?>>Published</option>
            <option value="0" <?= $statusFilter === '0' ? 'selected' : '' ?>>Draft</option>
            <option value="2" <?= $statusFilter === '2' ? 'selected' : '' ?>>Inactive</option>
        </select>

        <button class="btn btn-outline btn-sm" type="submit">Search</button>
        <a class="btn btn-outline btn-sm" href="index.php?page=stories">View All</a>
    </div>
</form>

<div class="filter-count">
    Showing
    <b>
        <?= $totalStories > 0 ? (($currentPage - 1) * $perPage + 1) : 0 ?>
        -
        <?= min($currentPage * $perPage, $totalStories) ?>
    </b>
    of <span><?= number_format($totalStories) ?></span> Stories
</div>

<div class="table-wrap">
    <table>
        <thead>
            <tr>
                <th>User</th>
                <th>Message</th>
                <th>Status</th>
                <th>Promoted</th>
                <th class="right">Action</th>
            </tr>
        </thead>
        <tbody>
        <?php if (!$stories): ?>
            <tr class="empty-row">
                <td colspan="5">
                    <?= $dbError ? 'Unable to load stories from PostgreSQL.' : 'No stories found.' ?>
                </td>
            </tr>
        <?php endif; ?>

        <?php foreach ($stories as $story): ?>
            <?php
                $storyForJs = [
                    'id' => $story['id'],
                    'user_id' => $story['user_id'],
                    'username' => $story['username'],
                    'email' => $story['email'],
                    'mobile' => $story['mobile'],
                    'display_name' => $story['display_name'],
                    'message' => $story['message'],
                    'visibility' => $story['visibility_label'],
                    'status' => $story['status_label'],
                    'likes_count' => $story['likes_count'],
                    'created_at' => $story['created_at'],
                    'updated_at' => $story['updated_at'],
                    'views_count' => $story['views_count'],
                    'post_id' => $story['post_id'],
                    'promoted' => $story['promoted'] ? 'Yes' : 'No',
                ];
            ?>
            <tr>
                <td>
                    <?= e($story['username']) ?>
                </td>

                <td>
                    <div class="truncate-cell" title="<?= e((string)$story['message']) ?>">
                        <?= e($story['message'] ?? '-') ?>
                    </div>
                </td>

                <td>
                    <span class="status-badge <?= e($story['status_label']) ?>">
                        <span class="dot-status <?= e($story['status_label']) ?>"></span>
                        <?= e($story['status_label']) ?>
                    </span>
                </td>

                <td><?= $story['promoted'] ? 'Yes' : 'No' ?></td>

                <td class="right">
                    <div class="row-actions">
                        <button
                            type="button"
                            class="mini-btn"
                            onclick='openStoryModal(<?= json_encode($storyForJs, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT) ?>)'
                        >View</button>

                        <button
                            type="button"
                            class="mini-btn danger"
                            onclick="displayOnlyStoryDelete()"
                        >Delete</button>
                    </div>
                </td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
</div>

<?php if ($totalPages > 1): ?>
    <div class="pagination">
        <?php if ($currentPage > 1): ?>
            <a
                class="btn btn-outline btn-sm"
                href="index.php?<?= e(storyQuery(['p' => $currentPage - 1])) ?>"
            >Previous</a>
        <?php endif; ?>

        <span class="pagination-info">
            Page <?= $currentPage ?> of <?= $totalPages ?>
        </span>

        <?php if ($currentPage < $totalPages): ?>
            <a
                class="btn btn-outline btn-sm"
                href="index.php?<?= e(storyQuery(['p' => $currentPage + 1])) ?>"
            >Next</a>
        <?php endif; ?>
    </div>
<?php endif; ?>

<!-- ================================================================
     VIEW STORY MODAL
     Read-only. No corporate database mutation is performed.
================================================================ -->
<div id="story-modal" class="user-popup-overlay" hidden>
    <div
        class="user-popup story-popup"
        role="dialog"
        aria-modal="true"
        aria-labelledby="story-modal-title"
    >
        <div class="user-popup-header">
            <div>
                <h2 id="story-modal-title">Viewing Story</h2>
                <p>Existing story information — display only.</p>
            </div>

            <button
                type="button"
                class="user-popup-close"
                onclick="closeStoryModal()"
                aria-label="Close"
            >&times;</button>
        </div>

        <div class="user-popup-grid story-form-grid">
            <div class="field">
                <label>ID</label>
                <input id="story-id" type="text" disabled>
            </div>

            <div class="field">
                <label>User</label>
                <input id="story-user" type="text" disabled>
            </div>

            <div class="field">
                <label>User Email</label>
                <input id="story-email" type="text" disabled>
            </div>

            <div class="field">
                <label>Mobile</label>
                <input id="story-mobile" type="text" disabled>
            </div>

            <div class="field field-full">
                <label>Message</label>
                <textarea id="story-message" rows="4" disabled></textarea>
            </div>

            <div class="field">
                <label>Visibility</label>
                <input id="story-visibility" type="text" disabled>
            </div>

            <div class="field">
                <label>Status</label>
                <input id="story-status" type="text" disabled>
            </div>

            <div class="field">
                <label>Likes Count</label>
                <input id="story-likes" type="text" disabled>
            </div>

            <div class="field">
                <label>Promoted</label>
                <input id="story-promoted" type="text" disabled>
            </div>

            <div class="field">
                <label>Post ID</label>
                <input id="story-post-id" type="text" disabled>
            </div>

            <div class="field">
                <label>Views Count</label>
                <input id="story-views" type="text" disabled>
            </div>

            <div class="field">
                <label>Created</label>
                <input id="story-created" type="text" disabled>
            </div>

            <div class="field">
                <label>Updated</label>
                <input id="story-updated" type="text" disabled>
            </div>
        </div>

        <div class="user-popup-footer">
            <button
                type="button"
                class="btn btn-outline"
                onclick="closeStoryModal()"
            >Close</button>

            <button
                type="button"
                class="btn btn-outline btn-danger"
                onclick="displayOnlyStoryDelete()"
            >Delete Story</button>
        </div>
    </div>
</div>

<script>
function openStoryModal(story)
{
    if (!story) return;

    document.getElementById('story-id').value = story.id ?? '';
    document.getElementById('story-user').value = story.username ?? '';
    document.getElementById('story-email').value = story.email ?? '-';
    document.getElementById('story-mobile').value = story.mobile ?? '-';
    document.getElementById('story-message').value = story.message ?? '';
    document.getElementById('story-visibility').value = story.visibility ?? '-';
    document.getElementById('story-status').value = story.status ?? '-';
    document.getElementById('story-likes').value = story.likes_count ?? '0';
    document.getElementById('story-promoted').value = story.promoted ?? 'No';
    document.getElementById('story-post-id').value = story.post_id ?? '-';
    document.getElementById('story-views').value = story.views_count ?? '0';
    document.getElementById('story-created').value = formatStoryDate(story.created_at);
    document.getElementById('story-updated').value = formatStoryDate(story.updated_at);

    const modal = document.getElementById('story-modal');
    modal.hidden = false;
    document.body.style.overflow = 'hidden';
}

function closeStoryModal()
{
    const modal = document.getElementById('story-modal');
    if (!modal) return;

    modal.hidden = true;
    document.body.style.overflow = '';
}

function displayOnlyStoryDelete()
{
    // Intentionally empty. This button must not modify the corporate database.
}

function formatStoryDate(value)
{
    if (!value) return '-';

    const date = new Date(value);
    if (Number.isNaN(date.getTime())) return value;

    return date.toLocaleDateString('en-GB', {
        day: '2-digit',
        month: 'short',
        year: 'numeric'
    }) + ' ' + date.toLocaleTimeString('en-GB', {
        hour: '2-digit',
        minute: '2-digit'
    });
}

document.addEventListener('click', function(event) {
    const modal = document.getElementById('story-modal');

    if (modal && event.target === modal) {
        closeStoryModal();
    }
});

document.addEventListener('keydown', function(event) {
    if (event.key === 'Escape') {
        const modal = document.getElementById('story-modal');

        if (modal && !modal.hidden) {
            closeStoryModal();
        }
    }
});
</script>
