<?php

$perPage = 25;
$currentPage = max(1, (int)($_GET['p'] ?? 1));
$q = trim((string)($_GET['q'] ?? ''));
$userType = strtolower(trim((string)($_GET['user_type'] ?? 'all')));
$status = strtolower(trim((string)($_GET['status'] ?? 'all')));

$userType = in_array($userType, ['0', '1'], true) ? $userType : 'all';
$status = in_array($status, ['active', 'inactive'], true) ? $status : 'all';

/*
|--------------------------------------------------------------------------
| Build WHERE conditions
|--------------------------------------------------------------------------
*/
$where = [];
$params = [];

if ($q !== '') {
    // Escape wildcard characters
    $escapedQ = addcslashes($q, '%_\\');
    
    if (ctype_digit($q)) {
        $where[] = "(id = :search_id OR username ILIKE :search OR mobile ILIKE :search OR display_name ILIKE :search OR email ILIKE :search)";
        $params[':search_id'] = (int)$q;
    } else {
        $where[] = "(username ILIKE :search OR mobile ILIKE :search OR display_name ILIKE :search OR email ILIKE :search)";
    }
    $params[':search'] = '%' . $escapedQ . '%';
}

if ($userType !== 'all') {
    $where[] = "user_type = :user_type";
    $params[':user_type'] = (int)$userType;
}

// Optimization: Direct timestamp range filtering instead of row-by-row expressions
if ($status === 'active') {
    $where[] = "(current_sign_in_at >= NOW() - INTERVAL '30 days' OR last_sign_in_at >= NOW() - INTERVAL '30 days')";
} elseif ($status === 'inactive') {
    $where[] = "((current_sign_in_at IS NULL OR current_sign_in_at < NOW() - INTERVAL '30 days') AND (last_sign_in_at IS NULL OR last_sign_in_at < NOW() - INTERVAL '30 days'))";
}

$whereSql = $where ? 'WHERE ' . implode(' AND ', $where) : '';

$statusExpression = "
    CASE
        WHEN (current_sign_in_at >= NOW() - INTERVAL '30 days' OR last_sign_in_at >= NOW() - INTERVAL '30 days')
        THEN 'active'
        ELSE 'inactive'
    END
";

/*
|--------------------------------------------------------------------------
| Database execution
|--------------------------------------------------------------------------
*/
$dbError = null;
$totalUsers = 0;
$users = [];

try {
    $pdo = getAppDb();

    // 1. Total count
    $countStmt = $pdo->prepare("SELECT COUNT(*) FROM public.users {$whereSql}");
    foreach ($params as $key => $value) {
        $countStmt->bindValue($key, $value, is_int($value) ? PDO::PARAM_INT : PDO::PARAM_STR);
    }
    $countStmt->execute();
    $totalUsers = (int)$countStmt->fetchColumn();

    // 2. Pagination calculation
    $totalPages = max(1, (int)ceil($totalUsers / $perPage));
    $currentPage = min($currentPage, $totalPages);
    $offset = ($currentPage - 1) * $perPage;

    // 3. Page fetch
    $sql = "
        SELECT
            id,
            username,
            mobile,
            display_name,
            user_type,
            email,
            {$statusExpression} AS status,
            created_at,
            updated_at,
            current_sign_in_at,
            last_sign_in_at
        FROM public.users
        {$whereSql}
        ORDER BY id DESC
        LIMIT :limit OFFSET :offset
    ";

    $stmt = $pdo->prepare($sql);
    foreach ($params as $key => $value) {
        $stmt->bindValue($key, $value, is_int($value) ? PDO::PARAM_INT : PDO::PARAM_STR);
    }
    $stmt->bindValue(':limit', $perPage, PDO::PARAM_INT);
    $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
    $stmt->execute();

    $users = $stmt->fetchAll(PDO::FETCH_ASSOC);

} catch (Throwable $e) {
    $dbError = $e->getMessage();
    $totalUsers = 0;
    $totalPages = 1;
}

/*
|--------------------------------------------------------------------------
| View Helpers
|--------------------------------------------------------------------------
*/
function multipieUserTypeLabel($type): string {
    return ((int)$type === 1) ? 'Admin' : 'User';
}

function multipieFormatDate($value): string {
    if (empty($value)) return '-';
    try {
        return (new DateTimeImmutable((string)$value))->format('d M Y');
    } catch (Throwable $e) {
        return (string)$value;
    }
}

function multipieQuery(array $overrides = []): string {
    $query = array_merge([
        'page' => 'users',
        'q' => (string)($_GET['q'] ?? ''),
        'user_type' => (string)($_GET['user_type'] ?? 'all'),
        'status' => (string)($_GET['status'] ?? 'all'),
    ], $overrides);

    return http_build_query(array_filter($query, static fn($v) => $v !== '' && $v !== 'all'));
}
?>

<section class="view-header">
    <div>
        <h1>
            User Directory &amp; Learner Records
            <span class="count-pill-navy"><?= number_format($totalUsers) ?> Total</span>
        </h1>
        <p class="sub">Manage MultiPie learner identities, user access, and administrative records.</p>
    </div>
    <button class="btn btn-orange" type="button" onclick="openAddUserModal()">
        <svg class="icon icon-sm"><use href="#i-user-plus"/></svg>
        Add New User
    </button>
</section>

<?php if ($dbError): ?>
    <div class="alert-box">
        <div class="row">
            <div>
                <h4>PostgreSQL connection failed</h4>
                <p><?= htmlspecialchars($dbError, ENT_QUOTES, 'UTF-8') ?></p>
            </div>
        </div>
    </div>
<?php endif; ?>

<form class="filter-bar" method="get" action="index.php">
    <input type="hidden" name="page" value="users">
    <div class="filter-controls">
        <div class="search-box">
            <svg class="icon"><use href="#i-search"/></svg>
            <input name="q" type="text" value="<?= htmlspecialchars($q, ENT_QUOTES, 'UTF-8') ?>" placeholder="Search by ID, username, mobile, name, email...">
        </div>

        <select class="select-plain" name="user_type">
            <option value="all" <?= $userType === 'all' ? 'selected' : '' ?>>All User Types</option>
            <option value="0" <?= $userType === '0' ? 'selected' : '' ?>>User</option>
            <option value="1" <?= $userType === '1' ? 'selected' : '' ?>>Admin</option>
        </select>

        <select class="select-plain" name="status">
            <option value="all" <?= $status === 'all' ? 'selected' : '' ?>>All Status</option>
            <option value="active" <?= $status === 'active' ? 'selected' : '' ?>>Active</option>
            <option value="inactive" <?= $status === 'inactive' ? 'selected' : '' ?>>Inactive</option>
        </select>

        <button class="btn btn-outline btn-sm" type="submit">Filter</button>
        <a class="btn btn-outline btn-sm" href="index.php?<?= htmlspecialchars(multipieQuery(['export' => 'csv']), ENT_QUOTES, 'UTF-8') ?>">
            <svg class="icon icon-sm"><use href="#i-download"/></svg>
            Export CSV
        </a>
    </div>
</form>

<div class="filter-count">
    Showing
    <b><?= $totalUsers ? (($currentPage - 1) * $perPage + 1) : 0 ?> - <?= min($currentPage * $perPage, $totalUsers) ?></b>
    of <span><?= number_format($totalUsers) ?></span> Users
</div>

<div class="table-wrap">
    <table>
        <thead>
            <tr>
                <th>ID</th>
                <th>Username</th>
                <th>Mobile</th>
                <th>Display Name</th>
                <th>User Type</th>
                <th>Email</th>
                <th>Status</th>
                <th>Created</th>
                <th>Updated</th>
                <th class="right">Action</th>
            </tr>
        </thead>
        <tbody>
        <?php if (!$users): ?>
            <tr class="empty-row">
                <td colspan="10"><?= $dbError ? 'Unable to load users from PostgreSQL.' : 'No users found matching the selected filters.' ?></td>
            </tr>
        <?php endif; ?>

        <?php foreach ($users as $u): 
            $typeLabel = multipieUserTypeLabel($u['user_type']);
            $statusLabel = ucfirst((string)$u['status']);
        ?>
            <tr>
                <td><?= htmlspecialchars($u['id'], ENT_QUOTES, 'UTF-8') ?></td>
                <td><?= htmlspecialchars($u['username'] ?? '-', ENT_QUOTES, 'UTF-8') ?></td>
                <td><?= htmlspecialchars($u['mobile'] ?? '-', ENT_QUOTES, 'UTF-8') ?></td>
                <td><?= htmlspecialchars($u['display_name'] ?? '-', ENT_QUOTES, 'UTF-8') ?></td>
                <td>
                    <span class="role-badge <?= $typeLabel ?>"><?= htmlspecialchars($typeLabel, ENT_QUOTES, 'UTF-8') ?></span>
                </td>
                <td><?= htmlspecialchars($u['email'] ?? '-', ENT_QUOTES, 'UTF-8') ?></td>
                <td>
                    <span class="status-badge <?= htmlspecialchars($statusLabel, ENT_QUOTES, 'UTF-8') ?>">
                        <span class="dot-status <?= htmlspecialchars($statusLabel, ENT_QUOTES, 'UTF-8') ?>"></span>
                        <?= htmlspecialchars($statusLabel, ENT_QUOTES, 'UTF-8') ?>
                    </span>
                </td>
                <td><?= htmlspecialchars(multipieFormatDate($u['created_at']), ENT_QUOTES, 'UTF-8') ?></td>
                <td><?= htmlspecialchars(multipieFormatDate($u['updated_at']), ENT_QUOTES, 'UTF-8') ?></td>
                <td class="right">
                    <div class="row-actions">
                        <button type="button" class="mini-btn" onclick='openUserModal(<?= json_encode($u, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT) ?>)'>Edit</button>
                        <button type="button" class="mini-btn danger" disabled title="Delete is intentionally disabled for corporate data.">Delete</button>
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
            <a class="btn btn-outline btn-sm" href="index.php?<?= htmlspecialchars(multipieQuery(['p' => $currentPage - 1]), ENT_QUOTES, 'UTF-8') ?>">Previous</a>
        <?php endif; ?>

        <span class="pagination-info">Page <?= $currentPage ?> of <?= $totalPages ?></span>

        <?php if ($currentPage < $totalPages): ?>
            <a class="btn btn-outline btn-sm" href="index.php?<?= htmlspecialchars(multipieQuery(['p' => $currentPage + 1]), ENT_QUOTES, 'UTF-8') ?>">Next</a>
        <?php endif; ?>
    </div>
<?php endif; ?>

<!-- ================================================================
     ADD NEW USER MODAL
     ================================================================ -->
<div id="add-user-modal" class="user-popup-overlay" hidden>
    <div class="user-popup" role="dialog" aria-modal="true" aria-labelledby="add-user-modal-title">
        <div class="user-popup-header">
            <div>
                <h2 id="add-user-modal-title">Add New User</h2>
                <p>Enter user information for display only.</p>
            </div>
            <button type="button" class="user-popup-close" onclick="closeAddUserModal()" aria-label="Close">&times;</button>
        </div>
        <form id="add-user-form" class="user-popup-grid">
            <div class="field"><label for="add-user-id">ID</label><input id="add-user-id" type="text" value="Auto generated" disabled></div>
            <div class="field"><label for="add-user-username">Username</label><input id="add-user-username" name="username" type="text" placeholder="Enter username"></div>
            <div class="field"><label for="add-user-first-name">First Name</label><input id="add-user-first-name" name="first_name" type="text" placeholder="Enter first name"></div>
            <div class="field"><label for="add-user-last-name">Last Name</label><input id="add-user-last-name" name="last_name" type="text" placeholder="Enter last name"></div>
            <div class="field"><label for="add-user-mobile">Mobile</label><input id="add-user-mobile" name="mobile" type="text" placeholder="Enter mobile number"></div>
            <div class="field"><label for="add-user-email">Email</label><input id="add-user-email" name="email" type="email" placeholder="Enter email address"></div>
            <div class="field">
                <label for="add-user-role">Role</label>
                <select id="add-user-role" name="role"><option value="0">User</option><option value="1">Admin</option></select>
            </div>
            <div class="field">
                <label for="add-user-status">Status</label>
                <select id="add-user-status" name="status"><option value="active">Active</option><option value="inactive">Inactive</option></select>
            </div>
            <div class="field"><label for="add-user-uid">UID</label><input id="add-user-uid" name="uid" type="text" placeholder="Enter UID"></div>
            <div class="field"><label for="add-user-created">Created</label><input id="add-user-created" type="text" value="Not created yet" disabled></div>
            <div class="field"><label for="add-user-updated">Updated</label><input id="add-user-updated" type="text" value="Not updated yet" disabled></div>
            <div class="field"><label for="add-user-display-name">Display Name</label><input id="add-user-display-name" name="display_name" type="text" placeholder="Enter display name"></div>
        </form>
        <div class="user-popup-footer">
            <button type="button" class="btn btn-outline" onclick="closeAddUserModal()">Close</button>
            <button type="button" class="btn btn-navy" disabled title="User creation is intentionally disabled for corporate data.">Save User</button>
        </div>
    </div>
</div>

<!-- ================================================================
     EDIT USER MODAL
     ================================================================ -->
<div id="user-modal" class="user-popup-overlay" hidden>
    <div class="user-popup" role="dialog" aria-modal="true" aria-labelledby="user-modal-title">
        <div class="user-popup-header">
            <div>
                <h2 id="user-modal-title">Edit User</h2>
                <p>Existing user information — display only.</p>
            </div>
            <button type="button" class="user-popup-close" onclick="closeUserModal()" aria-label="Close">&times;</button>
        </div>
        <div class="user-popup-grid">
            <div class="field"><label>ID</label><input id="modal-id" disabled type="text"></div>
            <div class="field"><label>Username</label><input id="modal-username" type="text"></div>
            <div class="field"><label>Mobile</label><input id="modal-mobile" type="text"></div>
            <div class="field"><label>Display Name</label><input id="modal-display-name" type="text"></div>
            <div class="field">
                <label>User Type</label>
                <select id="modal-user-type"><option value="0">User</option><option value="1">Admin</option></select>
            </div>
            <div class="field"><label>Email</label><input id="modal-email" type="text"></div>
            <div class="field"><label>Status</label><input id="modal-status" type="text"></div>
            <div class="field"><label>Created</label><input id="modal-created" disabled type="text"></div>
            <div class="field"><label>Updated</label><input id="modal-updated" disabled type="text"></div>
            <div class="field"><label>Current Sign In</label><input id="modal-current-sign-in" disabled type="text"></div>
            <div class="field"><label>Last Sign In</label><input id="modal-last-sign-in" disabled type="text"></div>
        </div>
        <div class="user-popup-footer">
            <button type="button" class="btn btn-outline" onclick="closeUserModal()">Close</button>
            <button type="button" class="btn btn-navy" disabled title="Save is intentionally disabled.">Save Changes</button>
        </div>
    </div>
</div>

<script>
const editModal = document.getElementById('user-modal');
const addModal = document.getElementById('add-user-modal');
const addForm = document.getElementById('add-user-form');

const modalFields = {
    'modal-id': 'id',
    'modal-username': 'username',
    'modal-mobile': 'mobile',
    'modal-display-name': 'display_name',
    'modal-user-type': 'user_type',
    'modal-email': 'email',
    'modal-status': 'status',
    'modal-created': 'created_at',
    'modal-updated': 'updated_at',
    'modal-current-sign-in': 'current_sign_in_at',
    'modal-last-sign-in': 'last_sign_in_at'
};

function toggleModal(modal, show) {
    if (!modal) return;
    modal.hidden = !show;
    document.body.style.overflow = show ? 'hidden' : '';
}

function openAddUserModal() {
    if (addForm) addForm.reset();
    toggleModal(addModal, true);
}

function closeAddUserModal() {
    toggleModal(addModal, false);
}

function openUserModal(user) {
    for (const [elementId, key] of Object.entries(modalFields)) {
        const el = document.getElementById(elementId);
        if (el) {
            el.value = user[key] ?? (elementId.includes('sign-in') ? '-' : '');
        }
    }
    toggleModal(editModal, true);
}

function closeUserModal() {
    toggleModal(editModal, false);
}

[editModal, addModal].forEach(modal => {
    modal?.addEventListener('click', e => {
        if (e.target === modal) toggleModal(modal, false);
    });
});

document.addEventListener('keydown', e => {
    if (e.key === 'Escape') {
        closeUserModal();
        closeAddUserModal();
    }
});
</script>