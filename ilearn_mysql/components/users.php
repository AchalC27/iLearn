<?php

error_reporting(E_ALL);
ini_set('display_errors', '1');
require_once __DIR__ . '/../config.php';


/*
|--------------------------------------------------------------------------
| Helper Function
|--------------------------------------------------------------------------
*/

if (!function_exists('e')) {
    function e($value): string
    {
        return htmlspecialchars(
            (string)($value ?? ''),
            ENT_QUOTES,
            'UTF-8'
        );
    }
}


/*
|--------------------------------------------------------------------------
| Date Formatting Helper
|--------------------------------------------------------------------------
*/

if (!function_exists('formatDateOnly')) {
    function formatDateOnly($value): string
    {
        if (empty($value)) {
            return '';
        }

        $timestamp = strtotime((string)$value);

        if ($timestamp === false) {
            return '';
        }

        return strtolower(date('d M Y', $timestamp));
    }
}


/*
|--------------------------------------------------------------------------
| Get Filters
|--------------------------------------------------------------------------
*/

$q = trim($_GET['q'] ?? '');

$role = $_GET['role'] ?? 'all';

$status = $_GET['status'] ?? 'all';


/*
|--------------------------------------------------------------------------
| Pagination
|--------------------------------------------------------------------------
|
| We display 10 users on every page.
|
*/

$perPage = 10;


/*
|--------------------------------------------------------------------------
| Current Page
|--------------------------------------------------------------------------
|
| Example:
| index.php?page=users&page_num=2
|
| If page_num doesn't exist, page 1 is used.
|
*/

$currentPage = max(
    1,
    (int)($_GET['page_num'] ?? 1)
);


/*
|--------------------------------------------------------------------------
| Base User Query
|--------------------------------------------------------------------------
*/

$sql = "
    SELECT
        id,
        username,
        first_name,
        last_name,
        mobile,
        email,
        role,
        status,
        uid,
        created_at,
        updated_at,
        display_name
    FROM users
    WHERE 1 = 1
";

$params = [];


/*
|--------------------------------------------------------------------------
| Search Filter
|--------------------------------------------------------------------------
*/

if ($q !== '') {

    $sql .= "
        AND (
            CAST(id AS CHAR) LIKE :search
            OR username LIKE :search
            OR first_name LIKE :search
            OR last_name LIKE :search
            OR mobile LIKE :search
            OR email LIKE :search
            OR uid LIKE :search
            OR display_name LIKE :search
        )
    ";

    $params[':search'] = '%' . $q . '%';
}


/*
|--------------------------------------------------------------------------
| Role Filter
|--------------------------------------------------------------------------
*/

if ($role !== 'all') {

    $sql .= "
        AND role = :role
    ";

    $params[':role'] = $role;
}


/*
|--------------------------------------------------------------------------
| Status Filter
|--------------------------------------------------------------------------
*/

if ($status !== 'all') {

    $sql .= "
        AND status = :status
    ";

    $params[':status'] = $status;
}


/*
|--------------------------------------------------------------------------
| Count Users
|--------------------------------------------------------------------------
|
| Count only users matching the active filters.
|
*/

$countSql = "
    SELECT COUNT(*)
    FROM users
    WHERE 1 = 1
";

$countParams = [];


/*
|--------------------------------------------------------------------------
| Search For Count
|--------------------------------------------------------------------------
*/

if ($q !== '') {

    $countSql .= "
        AND (
            CAST(id AS CHAR) LIKE :search
            OR username LIKE :search
            OR first_name LIKE :search
            OR last_name LIKE :search
            OR mobile LIKE :search
            OR email LIKE :search
            OR uid LIKE :search
            OR display_name LIKE :search
        )
    ";

    $countParams[':search'] = '%' . $q . '%';
}


/*
|--------------------------------------------------------------------------
| Role For Count
|--------------------------------------------------------------------------
*/

if ($role !== 'all') {

    $countSql .= "
        AND role = :role
    ";

    $countParams[':role'] = $role;
}


/*
|--------------------------------------------------------------------------
| Status For Count
|--------------------------------------------------------------------------
*/

if ($status !== 'all') {

    $countSql .= "
        AND status = :status
    ";

    $countParams[':status'] = $status;
}


/*
|--------------------------------------------------------------------------
| Execute Count Query
|--------------------------------------------------------------------------
*/

$countStmt = $pdo->prepare($countSql);

$countStmt->execute($countParams);

$totalUsers = (int)$countStmt->fetchColumn();


/*
|--------------------------------------------------------------------------
| Calculate Total Pages
|--------------------------------------------------------------------------
*/

$totalPages = max(
    1,
    (int)ceil($totalUsers / $perPage)
);


/*
|--------------------------------------------------------------------------
| Prevent Invalid Page Number
|--------------------------------------------------------------------------
*/

$currentPage = min(
    $currentPage,
    $totalPages
);


/*
|--------------------------------------------------------------------------
| Calculate Offset
|--------------------------------------------------------------------------
|
| Page 1 -> 0
| Page 2 -> 10
| Page 3 -> 20
|
*/

$offset = ($currentPage - 1) * $perPage;


/*
|--------------------------------------------------------------------------
| Add Ordering And Pagination
|--------------------------------------------------------------------------
*/

$sql .= "
    ORDER BY id DESC
    LIMIT {$perPage}
    OFFSET {$offset}
";


/*
|--------------------------------------------------------------------------
| Fetch Users
|--------------------------------------------------------------------------
*/

$stmt = $pdo->prepare($sql);

$stmt->execute($params);

$filteredUsers = $stmt->fetchAll();


/*
|--------------------------------------------------------------------------
| Pagination Parameters
|--------------------------------------------------------------------------
|
| These values are kept when moving between pages.
|
*/

$paginationParams = [
    'page' => 'users',
    'q' => $q,
    'role' => $role,
    'status' => $status
];


/*
|--------------------------------------------------------------------------
| Get User For Editing
|--------------------------------------------------------------------------
*/

$editUser = null;

if (isset($_GET['edit'])) {

    $editId = (int)$_GET['edit'];

    $editStmt = $pdo->prepare("
        SELECT
            id,
            username,
            first_name,
            last_name,
            mobile,
            email,
            role,
            status,
            uid,
            created_at,
            updated_at,
            display_name
        FROM users
        WHERE id = :id
        LIMIT 10
    ");

    $editStmt->execute([
        ':id' => $editId
    ]);

    $editUser = $editStmt->fetch() ?: null;
}

?>


<section class="view-header">

    <div>

        <h1>

            User Directory &amp; Learner Records

            <span class="count-pill-blue">
                <?= $totalUsers ?> Total
            </span>

        </h1>

        <p class="sub">
            Manage learner identities, instructor permissions,
            and CMS administrative access.
        </p>

    </div>


    <a
        class="btn btn-orange"
        href="index.php?page=users&show_add_user=1"
    >

        <svg class="icon icon-sm">
            <use href="#i-user-plus"/>
        </svg>

        Add New User

    </a>

</section>



<!--
|--------------------------------------------------------------------------
| FILTER BAR
|--------------------------------------------------------------------------
-->

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


    <div class="search-box">

        <svg class="icon">
            <use href="#i-search"/>
        </svg>

        <input
            name="q"
            type="text"
            value="<?= e($q) ?>"
            placeholder="Search by ID, username, name, mobile, email, UID..."
        >

    </div>


    <select
        class="select-plain"
        name="role"
    >

        <option value="all">
            All Roles
        </option>

        <option
            value="User"
            <?= $role === 'User' ? 'selected' : '' ?>
        >
            User
        </option>

        <option
            value="Admin"
            <?= $role === 'Admin' ? 'selected' : '' ?>
        >
            Admin
        </option>

    </select>


    <select
        class="select-plain"
        name="status"
    >

        <option value="all">
            All Status
        </option>

        <option
            value="Active"
            <?= $status === 'Active' ? 'selected' : '' ?>
        >
            Active
        </option>

        <option
            value="Suspended"
            <?= $status === 'Suspended' ? 'selected' : '' ?>
        >
            Suspended
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
        href="index.php?page=users&export=users"
    >

        <svg class="icon icon-sm">
            <use href="#i-download"/>
        </svg>

        Export CSV

    </a>

</form>



<!--
|--------------------------------------------------------------------------
| RESULT COUNT
|--------------------------------------------------------------------------
-->

<div class="filter-count">

    Showing

    <b><?= count($filteredUsers) ?></b>

    of

    <span><?= $totalUsers ?></span>

    Users

</div>



<!--
|--------------------------------------------------------------------------
| USERS TABLE
|--------------------------------------------------------------------------
-->

<div class="table-wrap">

<table>

    <thead>

        <tr>

            <th>ID</th>

            <th>Username</th>

            <th>First Name</th>

            <th>Last Name</th>

            <th>Mobile</th>

            <th>Email</th>

            <th>Role</th>

            <th>Status</th>

            <th>UID</th>

            <th>Created</th>

            <th>Updated</th>

            <th>Display Name</th>

            <th class="right">Actions</th>

        </tr>

    </thead>


    <tbody>


    <?php if (!$filteredUsers): ?>

        <tr class="empty-row">

            <td colspan="13">

                No users found matching filter criteria.

            </td>

        </tr>

    <?php endif; ?>


    <?php foreach ($filteredUsers as $u): ?>

        <tr>


            <td>
                <?= e($u['id']) ?>
            </td>


            <td>
                <?= e($u['username']) ?>
            </td>


            <td>
                <?= e($u['first_name']) ?>
            </td>


            <td>
                <?= e($u['last_name']) ?>
            </td>


            <td>
                <?= e($u['mobile']) ?>
            </td>


            <td>
                <?= e($u['email']) ?>
            </td>


            <!-- ROLE -->

            <td>

                <span
                    class="role-badge <?= e($u['role']) ?>"
                >

                    <?= e($u['role']) ?>

                </span>

            </td>


            <!-- STATUS -->

            <td>

                <span
                    class="status-badge <?= e($u['status']) ?>"
                >

                    <span
                        class="dot-status <?= e($u['status']) ?>"
                    ></span>

                    <?= e($u['status']) ?>

                </span>

            </td>


            <td>
                <?= e($u['uid']) ?>
            </td>


            <td
                style="
                    color:var(--slate-500);
                    font-size:11px;
                "
            >
                <?= e(formatDateOnly($u['created_at'])) ?>
            </td>


            <td
                style="
                    color:var(--slate-500);
                    font-size:11px;
                "
            >
                <?= e(formatDateOnly($u['updated_at'])) ?>
            </td>


            <td>
                <?= e($u['display_name']) ?>
            </td>


            <!-- ACTIONS -->

            <td class="right">

                <div class="row-actions">


                    <!-- EDIT -->

                    <a
                        class="mini-btn"
                        href="index.php?page=users&edit=<?= e($u['id']) ?>&show_edit_user=1&page_num=<?= $currentPage ?>&q=<?= urlencode($q) ?>&role=<?= urlencode($role) ?>&status=<?= urlencode($status) ?>"
                    >

                        <svg class="icon icon-sm">
                            <use href="#i-edit"/>
                        </svg>

                        Edit

                    </a>


                    <!-- ACTIVATE / SUSPEND -->

                    <form
                        method="post"
                        style="display:inline"
                    >

                        <input
                            type="hidden"
                            name="action"
                            value="toggle_user"
                        >

                        <input
                            type="hidden"
                            name="id"
                            value="<?= e($u['id']) ?>"
                        >

                        <button
                            class="mini-btn <?= $u['status'] === 'Active' ? 'warn' : 'green' ?>"
                            type="submit"
                        >

                            <?= $u['status'] === 'Active'
                                ? 'Suspend'
                                : 'Activate'
                            ?>

                        </button>

                    </form>


                    <!-- DELETE -->

                    <form
                        method="post"
                        style="display:inline"
                    >

                        <input
                            type="hidden"
                            name="action"
                            value="delete_user"
                        >

                        <input
                            type="hidden"
                            name="id"
                            value="<?= e($u['id']) ?>"
                        >

                        <button
                            class="mini-btn danger"
                            type="submit"
                        >
                            Delete
                        </button>

                    </form>


                </div>

            </td>


        </tr>

    <?php endforeach; ?>


    </tbody>

</table>

</div>



<!--
|--------------------------------------------------------------------------
| PAGINATION
|--------------------------------------------------------------------------
-->

<?php if ($totalPages > 1): ?>

    <div
        class="pagination"
        style="
            display:flex;
            align-items:center;
            justify-content:center;
            gap:6px;
            margin-top:16px;
            flex-wrap:wrap;
        "
    >


        <!-- PREVIOUS -->

        <?php if ($currentPage > 1): ?>

            <?php

            $prevParams = $paginationParams;

            $prevParams['page_num'] = $currentPage - 1;

            $prevUrl = 'index.php?' . http_build_query($prevParams);

            ?>

            <a
                class="btn btn-outline btn-sm"
                href="<?= e($prevUrl) ?>"
            >
                &laquo; Previous
            </a>

        <?php endif; ?>



        <!-- PAGE NUMBERS -->

        <?php for ($i = 1; $i <= $totalPages; $i++): ?>

            <?php

            $pageParams = $paginationParams;

            $pageParams['page_num'] = $i;

            $pageUrl = 'index.php?' . http_build_query($pageParams);

            ?>


            <?php if ($i === $currentPage): ?>

                <span
                    class="btn btn-navy btn-sm"
                >
                    <?= $i ?>
                </span>

            <?php else: ?>

                <a
                    class="btn btn-outline btn-sm"
                    href="<?= e($pageUrl) ?>"
                >
                    <?= $i ?>
                </a>

            <?php endif; ?>


        <?php endfor; ?>



        <!-- NEXT -->

        <?php if ($currentPage < $totalPages): ?>

            <?php

            $nextParams = $paginationParams;

            $nextParams['page_num'] = $currentPage + 1;

            $nextUrl = 'index.php?' . http_build_query($nextParams);

            ?>

            <a
                class="btn btn-outline btn-sm"
                href="<?= e($nextUrl) ?>"
            >
                Next &raquo;
            </a>

        <?php endif; ?>


    </div>

<?php endif; ?>



<!--
|--------------------------------------------------------------------------
| EDIT USER DISPLAY-ONLY POPUP
|--------------------------------------------------------------------------
-->

<?php if ($editUser && isset($_GET['show_edit_user']) && $_GET['show_edit_user'] === '1'): ?>

<div class="user-popup-overlay" aria-hidden="false">
    <div class="user-popup" role="dialog" aria-modal="true" aria-labelledby="edit-user-title">

        <div class="user-popup-header">
            <div>
                <h2 id="edit-user-title">Edit User</h2>
                <p>Existing user information — display only.</p>
            </div>

            <a
                class="user-popup-close"
                href="index.php?page=users"
                aria-label="Close"
            >&times;</a>
        </div>

        <!-- No form and no submit action: database is not changed. -->
        <div class="user-popup-grid">

            <div class="field">
                <label>ID</label>
                <input type="text" value="<?= e($editUser['id']) ?>" disabled>
            </div>

            <div class="field">
                <label>Username</label>
                <input type="text" value="<?= e($editUser['username']) ?>" disabled>
            </div>

            <div class="field">
                <label>First Name</label>
                <input type="text" value="<?= e($editUser['first_name']) ?>" disabled>
            </div>

            <div class="field">
                <label>Last Name</label>
                <input type="text" value="<?= e($editUser['last_name']) ?>" disabled>
            </div>

            <div class="field">
                <label>Mobile</label>
                <input type="text" value="<?= e($editUser['mobile']) ?>" disabled>
            </div>

            <div class="field">
                <label>Email</label>
                <input type="email" value="<?= e($editUser['email']) ?>" disabled>
            </div>

            <div class="field">
                <label>Role</label>
                <select disabled>
                    <option selected><?= e($editUser['role']) ?></option>
                </select>
            </div>

            <div class="field">
                <label>Status</label>
                <select disabled>
                    <option selected><?= e($editUser['status']) ?></option>
                </select>
            </div>

            <div class="field">
                <label>UID</label>
                <input type="text" value="<?= e($editUser['uid']) ?>" disabled>
            </div>

            <div class="field">
                <label>Created</label>
                <input type="text" value="<?= e(formatDateOnly($editUser['created_at'])) ?>" disabled>
            </div>

            <div class="field">
                <label>Updated</label>
                <input type="text" value="<?= e(formatDateOnly($editUser['updated_at'])) ?>" disabled>
            </div>

            <div class="field">
                <label>Display Name</label>
                <input type="text" value="<?= e($editUser['display_name']) ?>" disabled>
            </div>

        </div>

        <div class="user-popup-footer">
            <a class="btn btn-outline" href="index.php?page=users">Close</a>

            <!-- Display only. Nothing is saved. -->
            <button type="button" class="btn btn-navy" onclick="return false;">
                Save Changes
            </button>
        </div>

    </div>
</div>

<?php endif; ?>

<!--
|--------------------------------------------------------------------------
| ADD USER DISPLAY-ONLY POPUP
|--------------------------------------------------------------------------
-->

<?php if (isset($_GET['show_add_user']) && $_GET['show_add_user'] === '1'): ?>

<div
    class="user-popup-overlay"
    aria-hidden="false"
>
    <div
        class="user-popup"
        role="dialog"
        aria-modal="true"
        aria-labelledby="add-user-title"
    >

        <div class="user-popup-header">

            <div>
                <h2 id="add-user-title">Add New User</h2>
                <p>Enter user information for display only.</p>
            </div>

            <a
                class="user-popup-close"
                href="index.php?page=users"
                aria-label="Close"
            >
                &times;
            </a>

        </div>


        <!--
        This is intentionally NOT a form.

        Nothing is submitted and no database action is performed.
        -->

        <div class="user-popup-grid">

            <div class="field">
                <label>ID</label>
                <input type="text" value="" placeholder="Auto generated" disabled>
            </div>

            <div class="field">
                <label>Username</label>
                <input type="text" placeholder="Enter username">
            </div>

            <div class="field">
                <label>First Name</label>
                <input type="text" placeholder="Enter first name">
            </div>

            <div class="field">
                <label>Last Name</label>
                <input type="text" placeholder="Enter last name">
            </div>

            <div class="field">
                <label>Mobile</label>
                <input type="text" placeholder="Enter mobile number">
            </div>

            <div class="field">
                <label>Email</label>
                <input type="email" placeholder="Enter email address">
            </div>

            <div class="field">
                <label>Role</label>
                <select>
                    <option value="User">User</option>
                    <option value="Admin">Admin</option>
                </select>
            </div>

            <div class="field">
                <label>Status</label>
                <select>
                    <option value="Active">Active</option>
                    <option value="Suspended">Suspended</option>
                </select>
            </div>

            <div class="field">
                <label>UID</label>
                <input type="text" placeholder="Enter UID">
            </div>

            <div class="field">
                <label>Created</label>
                <input type="text" value="" placeholder="Not created yet" disabled>
            </div>

            <div class="field">
                <label>Updated</label>
                <input type="text" value="" placeholder="Not updated yet" disabled>
            </div>

            <div class="field">
                <label>Display Name</label>
                <input type="text" placeholder="Enter display name">
            </div>

        </div>


        <div class="user-popup-footer">

            <a
                class="btn btn-outline"
                href="index.php?page=users"
            >
                Close
            </a>

            <!-- Display only: this button intentionally has no submit action. -->
            <button
                type="button"
                class="btn btn-navy"
                onclick="return false;"
            >
                Save User
            </button>

        </div>

    </div>
</div>



<?php endif; ?>
