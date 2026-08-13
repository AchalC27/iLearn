<?php


$perPage = 25;

$currentPage = max(
    1,
    (int)($_GET['p'] ?? 1)
);

$q = trim((string)($_GET['q'] ?? ''));

$userType = strtolower(
    trim((string)($_GET['user_type'] ?? 'all'))
);

$status = strtolower(
    trim((string)($_GET['status'] ?? 'all'))
);

$allowedUserTypes = ['all', '0', '1'];

if (!in_array($userType, $allowedUserTypes, true)) {
    $userType = 'all';
}

$allowedStatuses = ['all', 'active', 'inactive'];

if (!in_array($status, $allowedStatuses, true)) {
    $status = 'all';
}


/*
|--------------------------------------------------------------------------
| Status expression
|--------------------------------------------------------------------------
*/

$statusExpression = "
    CASE
        WHEN GREATEST(
            COALESCE(current_sign_in_at, TIMESTAMP 'epoch'),
            COALESCE(last_sign_in_at, TIMESTAMP 'epoch')
        ) >= NOW() - INTERVAL '30 days'
        THEN 'active'
        ELSE 'inactive'
    END
";


/*
|--------------------------------------------------------------------------
| Build WHERE conditions
|--------------------------------------------------------------------------
*/

$where = [];
$params = [];

if ($q !== '') {

    $where[] = "(
        id::text ILIKE :search
        OR COALESCE(username, '') ILIKE :search
        OR COALESCE(mobile, '') ILIKE :search
        OR COALESCE(display_name, '') ILIKE :search
        OR COALESCE(email, '') ILIKE :search
        OR user_type::text ILIKE :search
    )";

    $params[':search'] = '%' . $q . '%';
}

if ($userType !== 'all') {

    $where[] = "user_type = :user_type";

    $params[':user_type'] = (int)$userType;
}

if ($status !== 'all') {

    $where[] = "{$statusExpression} = :status";

    $params[':status'] = $status;
}

$whereSql = $where
    ? 'WHERE ' . implode(' AND ', $where)
    : '';


/*
|--------------------------------------------------------------------------
| Database connection
|--------------------------------------------------------------------------
*/

$dbError = null;
$totalUsers = 0;
$users = [];

try {

    $pdo = getUsersDb();


    /*
     * Total matching records.
     */

    $countSql = "
        SELECT COUNT(*)
        FROM public.users
        {$whereSql}
    ";

    $countStmt = $pdo->prepare($countSql);

    foreach ($params as $key => $value) {
        $countStmt->bindValue(
            $key,
            $value,
            is_int($value)
                ? PDO::PARAM_INT
                : PDO::PARAM_STR
        );
    }

    $countStmt->execute();

    $totalUsers = (int)$countStmt->fetchColumn();


    /*
     * Pagination.
     */

    $totalPages = max(
        1,
        (int)ceil($totalUsers / $perPage)
    );

    if ($currentPage > $totalPages) {
        $currentPage = $totalPages;
    }

    $offset = ($currentPage - 1) * $perPage;


    /*
     * Fetch only the current page.
     *
     * This is important because the table contains
     * more than one million users.
     */

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
        LIMIT :limit
        OFFSET :offset
    ";

    $stmt = $pdo->prepare($sql);

    foreach ($params as $key => $value) {
        $stmt->bindValue(
            $key,
            $value,
            is_int($value)
                ? PDO::PARAM_INT
                : PDO::PARAM_STR
        );
    }

    $stmt->bindValue(
        ':limit',
        $perPage,
        PDO::PARAM_INT
    );

    $stmt->bindValue(
        ':offset',
        $offset,
        PDO::PARAM_INT
    );

    $stmt->execute();

    $users = $stmt->fetchAll();

} catch (Throwable $e) {

    $dbError = $e->getMessage();

    $totalUsers = 0;
    $totalPages = 1;
}


/*
|--------------------------------------------------------------------------
| Helper functions
|--------------------------------------------------------------------------
*/

function multipieUserTypeLabel($type): string
{
    return ((int)$type === 1)
        ? 'Admin'
        : 'User';
}

function multipieFormatDate($value): string
{
    if (empty($value)) {
        return '-';
    }

    try {
        return (new DateTime((string)$value))
            ->format('d M Y');
    } catch (Throwable $e) {
        return (string)$value;
    }
}

function multipieQuery(array $overrides = []): string
{
    $query = [
        'page' => 'users',
        'q' => (string)($_GET['q'] ?? ''),
        'user_type' => (string)($_GET['user_type'] ?? 'all'),
        'status' => (string)($_GET['status'] ?? 'all'),
    ];

    foreach ($overrides as $key => $value) {
        $query[$key] = $value;
    }

    return http_build_query(
        array_filter(
            $query,
            static fn($value) => $value !== ''
        )
    );
}

?>

<section class="view-header">

    <div>

        <h1>
            User Directory &amp; Learner Records

            <span class="count-pill-navy">
                <?= number_format($totalUsers) ?> Total
            </span>
        </h1>

        <p class="sub">
            Manage MultiPie learner identities, user access, and administrative records.
        </p>

    </div>

    <button
        class="btn btn-orange"
        type="button"
        onclick="openAddUserModal()"
    >
        <svg class="icon icon-sm">
            <use href="#i-user-plus"/>
        </svg>

        Add New User
    </button>

</section>


<?php if ($dbError): ?>

    <div class="alert-box">

        <div class="row">

            <div>

                <h4>PostgreSQL connection failed</h4>

                <p>
                    <?= e($dbError) ?>
                </p>

            </div>

        </div>

    </div>

<?php endif; ?>


<form
    class="filter-bar"
    method="get"
    action="index.php"
>

    <input
        type="hidden"
        name="page"
        value="users"
    >

    <div class="filter-controls">

        <div class="search-box">

            <svg class="icon">
                <use href="#i-search"/>
            </svg>

            <input
                name="q"
                type="text"
                value="<?= e($q) ?>"
                placeholder="Search by ID, username, mobile, display name, email..."
            >

        </div>


        <select
            class="select-plain"
            name="user_type"
        >
            <option value="all" <?= $userType === 'all' ? 'selected' : '' ?>>
                All User Types
            </option>

            <option value="0" <?= $userType === '0' ? 'selected' : '' ?>>
                User
            </option>

            <option value="1" <?= $userType === '1' ? 'selected' : '' ?>>
                Admin
            </option>
        </select>


        <select
            class="select-plain"
            name="status"
        >
            <option value="all" <?= $status === 'all' ? 'selected' : '' ?>>
                All Status
            </option>

            <option value="active" <?= $status === 'active' ? 'selected' : '' ?>>
                Active
            </option>

            <option value="inactive" <?= $status === 'inactive' ? 'selected' : '' ?>>
                Inactive
            </option>
        </select>


        <button
            class="btn btn-outline btn-sm"
            type="submit"
        >
            Filter
        </button>


        <a
            class="btn btn-outline btn-sm"
            href="index.php?<?= e(multipieQuery(['export' => 'csv'])) ?>"
        >
            <svg class="icon icon-sm">
                <use href="#i-download"/>
            </svg>

            Export CSV
        </a>

    </div>

</form>


<div class="filter-count">

    Showing

    <b>
        <?= $totalUsers ? (($currentPage - 1) * $perPage + 1) : 0 ?>
        -
        <?= min($currentPage * $perPage, $totalUsers) ?>
    </b>

    of

    <span>
        <?= number_format($totalUsers) ?>
    </span>

    Users

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

                <td colspan="10">

                    <?= $dbError
                        ? 'Unable to load users from PostgreSQL.'
                        : 'No users found matching the selected filters.'
                    ?>

                </td>

            </tr>

        <?php endif; ?>


        <?php foreach ($users as $u): ?>

            <tr>

                <td>
                    <?= e($u['id']) ?>
                </td>


                <td>
                    <?= e($u['username'] ?? '-') ?>
                </td>


                <td>
                    <?= e($u['mobile'] ?? '-') ?>
                </td>


                <td>
                    <?= e($u['display_name'] ?? '-') ?>
                </td>


                <td>

                    <?php $typeLabel = multipieUserTypeLabel($u['user_type']); ?>

                    <span
                        class="role-badge <?= $typeLabel === 'Admin' ? 'Admin' : 'User' ?>"
                    >
                        <?= e($typeLabel) ?>
                    </span>

                </td>


                <td>
                    <?= e($u['email'] ?? '-') ?>
                </td>


                <td>

                    <?php $statusLabel = ucfirst((string)$u['status']); ?>

                    <span
                        class="status-badge <?= e($statusLabel) ?>"
                    >

                        <span
                            class="dot-status <?= e($statusLabel) ?>"
                        ></span>

                        <?= e($statusLabel) ?>

                    </span>

                </td>


                <td>
                    <?= e(multipieFormatDate($u['created_at'])) ?>
                </td>


                <td>
                    <?= e(multipieFormatDate($u['updated_at'])) ?>
                </td>


                <td class="right">

                    <div class="row-actions">

                        <button
                            type="button"
                            class="mini-btn"
                            onclick='openUserModal(<?= json_encode($u, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT) ?>)'
                        >
                            Edit
                        </button>


                        <button
                            type="button"
                            class="mini-btn danger"
                            disabled
                            title="Delete is intentionally disabled for corporate data."
                        >
                            Delete
                        </button>

                    </div>

                </td>

            </tr>

        <?php endforeach; ?>

        </tbody>

    </table>

</div>


<?php if (($totalPages ?? 1) > 1): ?>

    <div class="pagination">

        <?php if ($currentPage > 1): ?>

            <a
                class="btn btn-outline btn-sm"
                href="index.php?<?= e(multipieQuery(['p' => $currentPage - 1])) ?>"
            >
                Previous
            </a>

        <?php endif; ?>


        <span class="pagination-info">
            Page <?= $currentPage ?> of <?= $totalPages ?>
        </span>


        <?php if ($currentPage < $totalPages): ?>

            <a
                class="btn btn-outline btn-sm"
                href="index.php?<?= e(multipieQuery(['p' => $currentPage + 1])) ?>"
            >
                Next
            </a>

        <?php endif; ?>

    </div>

<?php endif; ?>


<!-- ================================================================
     ADD NEW USER MODAL
     ================================================================ -->

<div
    id="add-user-modal"
    class="user-popup-overlay"
    hidden
>
    <div
        class="user-popup"
        role="dialog"
        aria-modal="true"
        aria-labelledby="add-user-modal-title"
    >

        <div class="user-popup-header">

            <div>
                <h2 id="add-user-modal-title">
                    Add New User
                </h2>

                <p>
                    Enter user information for display only.
                </p>
            </div>

            <button
                type="button"
                class="user-popup-close"
                onclick="closeAddUserModal()"
                aria-label="Close"
            >
                &times;
            </button>

        </div>


        <div class="user-popup-grid">

            <div class="field">
                <label for="add-user-id">ID</label>
                <input
                    id="add-user-id"
                    type="text"
                    value="Auto generated"
                    disabled
                >
            </div>


            <div class="field">
                <label for="add-user-username">Username</label>
                <input
                    id="add-user-username"
                    type="text"
                    placeholder="Enter username"
                    disabled
                >
            </div>


            <div class="field">
                <label for="add-user-first-name">First Name</label>
                <input
                    id="add-user-first-name"
                    type="text"
                    placeholder="Enter first name"
                    disabled
                >
            </div>


            <div class="field">
                <label for="add-user-last-name">Last Name</label>
                <input
                    id="add-user-last-name"
                    type="text"
                    placeholder="Enter last name"
                    disabled
                >
            </div>


            <div class="field">
                <label for="add-user-mobile">Mobile</label>
                <input
                    id="add-user-mobile"
                    type="text"
                    placeholder="Enter mobile number"
                    disabled
                >
            </div>


            <div class="field">
                <label for="add-user-email">Email</label>
                <input
                    id="add-user-email"
                    type="email"
                    placeholder="Enter email address"
                    disabled
                >
            </div>


            <div class="field">
                <label for="add-user-role">Role</label>
                <select id="add-user-role" disabled>
                    <option value="0">User</option>
                    <option value="1">Admin</option>
                </select>
            </div>


            <div class="field">
                <label for="add-user-status">Status</label>
                <select id="add-user-status" disabled>
                    <option value="active">Active</option>
                    <option value="inactive">Inactive</option>
                </select>
            </div>


            <div class="field">
                <label for="add-user-uid">UID</label>
                <input
                    id="add-user-uid"
                    type="text"
                    placeholder="Enter UID"
                    disabled
                >
            </div>


            <div class="field">
                <label for="add-user-created">Created</label>
                <input
                    id="add-user-created"
                    type="text"
                    value="Not created yet"
                    disabled
                >
            </div>


            <div class="field">
                <label for="add-user-updated">Updated</label>
                <input
                    id="add-user-updated"
                    type="text"
                    value="Not updated yet"
                    disabled
                >
            </div>


            <div class="field">
                <label for="add-user-display-name">Display Name</label>
                <input
                    id="add-user-display-name"
                    type="text"
                    placeholder="Enter display name"
                    disabled
                >
            </div>

        </div>


        <div class="user-popup-footer">

            <button
                type="button"
                class="btn btn-outline"
                onclick="closeAddUserModal()"
            >
                Close
            </button>


            <button
                type="button"
                class="btn btn-navy"
                disabled
                title="User creation is intentionally disabled for corporate data."
            >
                Save User
            </button>

        </div>

    </div>
</div>


<!-- ================================================================
     EDIT USER MODAL
     ================================================================ -->

<div
    id="user-modal"
    class="user-popup-overlay"
    hidden
>

    <div
        class="user-popup"
        role="dialog"
        aria-modal="true"
        aria-labelledby="user-modal-title"
    >

        <div class="user-popup-header">

            <div>

                <h2 id="user-modal-title">
                    Edit User
                </h2>

                <p>
                    Existing user information — display only.
                </p>

            </div>


            <button
                type="button"
                class="user-popup-close"
                onclick="closeUserModal()"
                aria-label="Close"
            >
                &times;
            </button>

        </div>


        <div class="user-popup-grid">

            <div class="field">
                <label>ID</label>
                <input id="modal-id" type="text" disabled>
            </div>


            <div class="field">
                <label>Username</label>
                <input id="modal-username" type="text" disabled>
            </div>


            <div class="field">
                <label>Mobile</label>
                <input id="modal-mobile" type="text" disabled>
            </div>


            <div class="field">
                <label>Display Name</label>
                <input id="modal-display-name" type="text" disabled>
            </div>


            <div class="field">
                <label>User Type</label>
                <select id="modal-user-type" disabled>
                    <option value="0">User</option>
                    <option value="1">Admin</option>
                </select>
            </div>


            <div class="field">
                <label>Email</label>
                <input id="modal-email" type="text" disabled>
            </div>


            <div class="field">
                <label>Status</label>
                <input id="modal-status" type="text" disabled>
            </div>


            <div class="field">
                <label>Created</label>
                <input id="modal-created" type="text" disabled>
            </div>


            <div class="field">
                <label>Updated</label>
                <input id="modal-updated" type="text" disabled>
            </div>


            <div class="field">
                <label>Current Sign In</label>
                <input id="modal-current-sign-in" type="text" disabled>
            </div>


            <div class="field">
                <label>Last Sign In</label>
                <input id="modal-last-sign-in" type="text" disabled>
            </div>

        </div>


        <div class="user-popup-footer">

            <button
                type="button"
                class="btn btn-outline"
                onclick="closeUserModal()"
            >
                Close
            </button>


            <button
                type="button"
                class="btn btn-navy"
                disabled
                title="Save is intentionally disabled for corporate data."
            >
                Save Changes
            </button>

        </div>

    </div>

</div>


<script>

function openAddUserModal() {

    const modal = document.getElementById('add-user-modal');

    if (!modal) {
        return;
    }

    /*
     * Reset the display-only form every time it is opened.
     */

    document.getElementById('add-user-id').value =
        'Auto generated';

    document.getElementById('add-user-username').value =
        '';

    document.getElementById('add-user-first-name').value =
        '';

    document.getElementById('add-user-last-name').value =
        '';

    document.getElementById('add-user-mobile').value =
        '';

    document.getElementById('add-user-email').value =
        '';

    document.getElementById('add-user-role').value =
        '0';

    document.getElementById('add-user-status').value =
        'active';

    document.getElementById('add-user-uid').value =
        '';

    document.getElementById('add-user-created').value =
        'Not created yet';

    document.getElementById('add-user-updated').value =
        'Not updated yet';

    document.getElementById('add-user-display-name').value =
        '';

    modal.hidden = false;

    document.body.style.overflow = 'hidden';
}


function closeAddUserModal() {

    const modal = document.getElementById('add-user-modal');

    if (!modal) {
        return;
    }

    modal.hidden = true;

    document.body.style.overflow = '';
}


function openUserModal(user) {

    const modal = document.getElementById('user-modal');

    document.getElementById('modal-id').value =
        user.id ?? '';

    document.getElementById('modal-username').value =
        user.username ?? '';

    document.getElementById('modal-mobile').value =
        user.mobile ?? '';

    document.getElementById('modal-display-name').value =
        user.display_name ?? '';

    document.getElementById('modal-user-type').value =
        user.user_type ?? '0';

    document.getElementById('modal-email').value =
        user.email ?? '';

    document.getElementById('modal-status').value =
        user.status ?? '';

    document.getElementById('modal-created').value =
        user.created_at ?? '';

    document.getElementById('modal-updated').value =
        user.updated_at ?? '';

    document.getElementById('modal-current-sign-in').value =
        user.current_sign_in_at ?? '-';

    document.getElementById('modal-last-sign-in').value =
        user.last_sign_in_at ?? '-';

    modal.hidden = false;

    document.body.style.overflow = 'hidden';
}


function closeUserModal() {

    const modal = document.getElementById('user-modal');

    modal.hidden = true;

    document.body.style.overflow = '';

}


document.getElementById('user-modal')
    ?.addEventListener('click', function(event) {

        if (event.target === this) {
            closeUserModal();
        }

    });


document.getElementById('add-user-modal')
    ?.addEventListener('click', function(event) {

        if (event.target === this) {
            closeAddUserModal();
        }

    });


document.addEventListener('keydown', function(event) {

    if (event.key === 'Escape') {

        closeUserModal();
        closeAddUserModal();

    }

});

</script>
