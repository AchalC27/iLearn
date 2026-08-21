
<?php
/*
|--------------------------------------------------------------------------
| MultiPie Auth Server - Users
|--------------------------------------------------------------------------
|
| Database:
|   multipie_auth_prod
|
| Table:
|   public.users
|
| Features:
|   - Display Name prefix filter
|   - Username prefix filter
|   - Pagination
|   - Excel export
|   - Edit user
|   - Lock / Unlock user
|   - Make Admin / Remove Admin
|
|--------------------------------------------------------------------------
*/

if (!function_exists('getAuthDb')) {
    require_once __DIR__ . '/../../includes/bootstrap.php';
}

/*
|--------------------------------------------------------------------------
| CSRF TOKEN
|--------------------------------------------------------------------------
*/

if (empty($_SESSION['auth_users_csrf'])) {
    $_SESSION['auth_users_csrf'] = bin2hex(random_bytes(32));
}

$csrfToken = $_SESSION['auth_users_csrf'];

/*
|--------------------------------------------------------------------------
| AJAX / ACTION HANDLER
|--------------------------------------------------------------------------
|
| This file also acts as the lightweight endpoint for user actions.
| Direct POST requests return JSON.
|
|--------------------------------------------------------------------------
*/

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {

    header('Content-Type: application/json; charset=utf-8');

    try {

        $postedToken = (string)($_POST['csrf_token'] ?? '');

        if (
            empty($_SESSION['auth_users_csrf']) ||
            !hash_equals($_SESSION['auth_users_csrf'], $postedToken)
        ) {
            throw new RuntimeException('Invalid security token. Please refresh the page.');
        }

        $action = trim((string)($_POST['action'] ?? ''));
        $userId = (int)($_POST['id'] ?? 0);

        if ($userId <= 0) {
            throw new InvalidArgumentException('Invalid user ID.');
        }

        $pdo = getAuthDb();

        /*
        |--------------------------------------------------------------------------
        | EDIT USER
        |--------------------------------------------------------------------------
        */

        if ($action === 'edit') {

            $username = trim((string)($_POST['username'] ?? ''));
            $displayName = trim((string)($_POST['display_name'] ?? ''));
            $email = trim((string)($_POST['email'] ?? ''));
            $mobile = trim((string)($_POST['mobile'] ?? ''));

            if ($username !== '' && mb_strlen($username) > 100) {
                throw new InvalidArgumentException('Username cannot exceed 100 characters.');
            }

            if ($displayName !== '' && mb_strlen($displayName) > 100) {
                throw new InvalidArgumentException('Display Name cannot exceed 100 characters.');
            }

            if ($mobile !== '' && mb_strlen($mobile) > 16) {
                throw new InvalidArgumentException('Mobile number cannot exceed 16 characters.');
            }

            if ($email !== '' && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
                throw new InvalidArgumentException('Please enter a valid email address.');
            }

            /*
             * Check unique username.
             */
            if ($username !== '') {
                $check = $pdo->prepare("
                    SELECT id
                    FROM public.users
                    WHERE username = :username
                      AND id <> :id
                    LIMIT 1
                ");

                $check->execute([
                    ':username' => $username,
                    ':id' => $userId
                ]);

                if ($check->fetch()) {
                    throw new RuntimeException('Username is already being used by another user.');
                }
            }

            /*
             * Check unique email.
             */
            if ($email !== '') {
                $check = $pdo->prepare("
                    SELECT id
                    FROM public.users
                    WHERE email = :email
                      AND id <> :id
                    LIMIT 1
                ");

                $check->execute([
                    ':email' => $email,
                    ':id' => $userId
                ]);

                if ($check->fetch()) {
                    throw new RuntimeException('Email is already being used by another user.');
                }
            }

            /*
             * Check unique mobile.
             */
            if ($mobile !== '') {
                $check = $pdo->prepare("
                    SELECT id
                    FROM public.users
                    WHERE mobile = :mobile
                      AND id <> :id
                    LIMIT 1
                ");

                $check->execute([
                    ':mobile' => $mobile,
                    ':id' => $userId
                ]);

                if ($check->fetch()) {
                    throw new RuntimeException('Mobile number is already being used by another user.');
                }
            }

            $stmt = $pdo->prepare("
                UPDATE public.users
                SET
                    username = NULLIF(:username, ''),
                    display_name = NULLIF(:display_name, ''),
                    email = NULLIF(:email, ''),
                    mobile = NULLIF(:mobile, ''),
                    updated_at = NOW()
                WHERE id = :id
            ");

            $stmt->execute([
                ':username' => $username,
                ':display_name' => $displayName,
                ':email' => $email,
                ':mobile' => $mobile,
                ':id' => $userId
            ]);

            echo json_encode([
                'success' => true,
                'message' => 'User details updated successfully.'
            ]);
            exit;
        }

        /*
        |--------------------------------------------------------------------------
        | LOCK / UNLOCK USER
        |--------------------------------------------------------------------------
        */

        if ($action === 'toggle_lock') {

            $stmt = $pdo->prepare("
                SELECT
                    id,
                    locked_at
                FROM public.users
                WHERE id = :id
                LIMIT 1
            ");

            $stmt->execute([
                ':id' => $userId
            ]);

            $user = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$user) {
                throw new RuntimeException('User not found.');
            }

            if (!empty($user['locked_at'])) {

                $update = $pdo->prepare("
                    UPDATE public.users
                    SET
                        locked_at = NULL,
                        unlock_token = NULL,
                        updated_at = NOW()
                    WHERE id = :id
                ");

                $update->execute([
                    ':id' => $userId
                ]);

                $message = 'User unlocked successfully.';
                $locked = false;

            } else {

                $update = $pdo->prepare("
                    UPDATE public.users
                    SET
                        locked_at = NOW(),
                        updated_at = NOW()
                    WHERE id = :id
                ");

                $update->execute([
                    ':id' => $userId
                ]);

                $message = 'User locked successfully.';
                $locked = true;
            }

            echo json_encode([
                'success' => true,
                'message' => $message,
                'locked' => $locked
            ]);
            exit;
        }

        /*
        |--------------------------------------------------------------------------
        | MAKE ADMIN / REMOVE ADMIN
        |--------------------------------------------------------------------------
        */

        if ($action === 'toggle_admin') {

            $stmt = $pdo->prepare("
                SELECT
                    id,
                    user_type
                FROM public.users
                WHERE id = :id
                LIMIT 1
            ");

            $stmt->execute([
                ':id' => $userId
            ]);

            $user = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$user) {
                throw new RuntimeException('User not found.');
            }

            $currentType = (int)$user['user_type'];

            /*
             * Existing convention in the project:
             *   0 = User
             *   1 = Admin
             */

            $newType = ($currentType === 1) ? 0 : 1;

            $update = $pdo->prepare("
                UPDATE public.users
                SET
                    user_type = :user_type,
                    updated_at = NOW()
                WHERE id = :id
            ");

            $update->execute([
                ':user_type' => $newType,
                ':id' => $userId
            ]);

            echo json_encode([
                'success' => true,
                'message' => $newType === 1
                    ? 'User has been made an Admin.'
                    : 'Admin access has been removed.',
                'user_type' => $newType
            ]);
            exit;
        }

        throw new RuntimeException('Unknown action.');

    } catch (Throwable $e) {

        http_response_code(400);

        echo json_encode([
            'success' => false,
            'message' => $e->getMessage()
        ]);

        exit;
    }
}

/*
|--------------------------------------------------------------------------
| EXCEL EXPORT
|--------------------------------------------------------------------------
|
| This is intentionally Excel-compatible .xls output.
| No PhpSpreadsheet dependency is required.
|
| The export uses ALL matching records, not only the current page.
|--------------------------------------------------------------------------
*/

if ($_SERVER['REQUEST_METHOD'] === 'GET' && ($_GET['export'] ?? '') === 'excel') {

    try {

        $displayNameFilter = trim((string)($_GET['display_name'] ?? ''));
        $usernameFilter = trim((string)($_GET['username'] ?? ''));

        $where = [];
        $params = [];

        if ($displayNameFilter !== '') {

            $where[] = "display_name ILIKE :display_name";

            $params[':display_name'] =
                addcslashes($displayNameFilter, '%_\\') . '%';
        }

        if ($usernameFilter !== '') {

            $where[] = "username ILIKE :username";

            $params[':username'] =
                addcslashes($usernameFilter, '%_\\') . '%';
        }

        $whereSql = $where
            ? 'WHERE ' . implode(' AND ', $where)
            : '';

        $pdo = getAuthDb();

        $stmt = $pdo->prepare("
            SELECT
                display_name,
                username,
                email,
                mobile,
                user_type,
                created_at
            FROM public.users
            {$whereSql}
            ORDER BY id DESC
        ");

        foreach ($params as $key => $value) {
            $stmt->bindValue($key, $value, PDO::PARAM_STR);
        }

        $stmt->execute();

        $exportUsers = $stmt->fetchAll(PDO::FETCH_ASSOC);

        /*
         * Excel-compatible HTML spreadsheet.
         */
        $filename = 'auth_users_' . date('Y-m-d_H-i-s') . '.xls';

        header('Content-Type: application/vnd.ms-excel; charset=UTF-8');
        header(
            'Content-Disposition: attachment; filename="' .
            $filename .
            '"'
        );
        header('Cache-Control: max-age=0');
        header('Pragma: public');

        echo "\xEF\xBB\xBF";

        echo '<html>';
        echo '<head>';
        echo '<meta charset="UTF-8">';
        echo '<style>';
        echo 'table{border-collapse:collapse;}';
        echo 'th{background:#0f172a;color:#ffffff;font-weight:bold;}';
        echo 'th,td{border:1px solid #d1d5db;padding:7px;}';
        echo '</style>';
        echo '</head>';
        echo '<body>';

        echo '<table>';

        echo '<tr>';
        echo '<th>Display Name</th>';
        echo '<th>Username</th>';
        echo '<th>Email</th>';
        echo '<th>Mobile</th>';
        echo '<th>User Type</th>';
        echo '<th>Created At</th>';
        echo '</tr>';

        foreach ($exportUsers as $user) {

            $typeLabel =
                ((int)$user['user_type'] === 1)
                    ? 'Admin'
                    : 'User';

            $createdAt = '-';

            if (!empty($user['created_at'])) {
                try {
                    $createdAt = (new DateTimeImmutable(
                        (string)$user['created_at']
                    ))->format('d M Y, h:i A');
                } catch (Throwable $e) {
                    $createdAt = (string)$user['created_at'];
                }
            }

            echo '<tr>';

            echo '<td>' .
                htmlspecialchars(
                    (string)($user['display_name'] ?? ''),
                    ENT_QUOTES,
                    'UTF-8'
                ) .
                '</td>';

            echo '<td>' .
                htmlspecialchars(
                    (string)($user['username'] ?? ''),
                    ENT_QUOTES,
                    'UTF-8'
                ) .
                '</td>';

            echo '<td>' .
                htmlspecialchars(
                    (string)($user['email'] ?? ''),
                    ENT_QUOTES,
                    'UTF-8'
                ) .
                '</td>';

            echo '<td>' .
                htmlspecialchars(
                    (string)($user['mobile'] ?? ''),
                    ENT_QUOTES,
                    'UTF-8'
                ) .
                '</td>';

            echo '<td>' .
                htmlspecialchars(
                    $typeLabel,
                    ENT_QUOTES,
                    'UTF-8'
                ) .
                '</td>';

            echo '<td>' .
                htmlspecialchars(
                    $createdAt,
                    ENT_QUOTES,
                    'UTF-8'
                ) .
                '</td>';

            echo '</tr>';
        }

        echo '</table>';

        echo '</body>';
        echo '</html>';

        exit;

    } catch (Throwable $e) {

        http_response_code(500);

        echo 'Unable to export users: ' .
            htmlspecialchars(
                $e->getMessage(),
                ENT_QUOTES,
                'UTF-8'
            );

        exit;
    }
}

/*
|--------------------------------------------------------------------------
| PAGE FILTERS
|--------------------------------------------------------------------------
*/

$perPage = 25;

$currentPage = max(
    1,
    (int)($_GET['p'] ?? 1)
);

$displayNameFilter = trim(
    (string)($_GET['display_name'] ?? '')
);

$usernameFilter = trim(
    (string)($_GET['username'] ?? '')
);

/*
|--------------------------------------------------------------------------
| BUILD WHERE
|--------------------------------------------------------------------------
*/

$where = [];
$params = [];

if ($displayNameFilter !== '') {

    $where[] = "display_name ILIKE :display_name";

    $params[':display_name'] =
        addcslashes($displayNameFilter, '%_\\') . '%';
}

if ($usernameFilter !== '') {

    $where[] = "username ILIKE :username";

    $params[':username'] =
        addcslashes($usernameFilter, '%_\\') . '%';
}

$whereSql = $where
    ? 'WHERE ' . implode(' AND ', $where)
    : '';

/*
|--------------------------------------------------------------------------
| DATABASE
|--------------------------------------------------------------------------
*/

$dbError = null;
$totalUsers = 0;
$totalPages = 1;
$users = [];

try {

    $pdo = getAuthDb();

    /*
     * Total count
     */
    $countStmt = $pdo->prepare("
        SELECT COUNT(*)
        FROM public.users
        {$whereSql}
    ");

    foreach ($params as $key => $value) {

        $countStmt->bindValue(
            $key,
            $value,
            PDO::PARAM_STR
        );
    }

    $countStmt->execute();

    $totalUsers = (int)$countStmt->fetchColumn();

    /*
     * Pagination
     */
    $totalPages = max(
        1,
        (int)ceil($totalUsers / $perPage)
    );

    $currentPage = min(
        $currentPage,
        $totalPages
    );

    $offset = ($currentPage - 1) * $perPage;

    /*
     * Fetch current page.
     */
    $stmt = $pdo->prepare("
        SELECT
            id,
            username,
            mobile,
            display_name,
            user_type,
            email,
            locked_at,
            created_at,
            updated_at
        FROM public.users
        {$whereSql}
        ORDER BY id DESC
        LIMIT :limit
        OFFSET :offset
    ");

    foreach ($params as $key => $value) {

        $stmt->bindValue(
            $key,
            $value,
            PDO::PARAM_STR
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

    $users = $stmt->fetchAll(PDO::FETCH_ASSOC);

} catch (Throwable $e) {

    $dbError = $e->getMessage();

    $totalUsers = 0;
    $totalPages = 1;
    $users = [];
}

/*
|--------------------------------------------------------------------------
| HELPERS
|--------------------------------------------------------------------------
*/

function authUserTypeLabel($type): string
{
    return ((int)$type === 1)
        ? 'Admin'
        : 'User';
}

function authUserFormatDate($value): string
{
    if (empty($value)) {
        return '-';
    }

    try {

        return (new DateTimeImmutable(
            (string)$value
        ))->format('d M Y, h:i A');

    } catch (Throwable $e) {

        return (string)$value;
    }
}

function authUsersQuery(array $overrides = []): string
{
    $query = array_merge(
        [
            'sidebar' => 'auth',
            'page' => 'auth_users',
            'display_name' => (string)($_GET['display_name'] ?? ''),
            'username' => (string)($_GET['username'] ?? ''),
        ],
        $overrides
    );

    return http_build_query(
        array_filter(
            $query,
            static function ($value) {
                return $value !== '' &&
                    $value !== null &&
                    $value !== 'all';
            }
        )
    );
}

?>

<!-- ================================================================
     PAGE HEADER
     ================================================================ -->

<section class="view-header">

    <div>

        <h1>
            Auth Users

            <span class="count-pill-navy">
                <?= number_format($totalUsers) ?> Total
            </span>
        </h1>

        <p class="sub">
            Manage authentication users, access permissions, and administrator roles.
        </p>

    </div>

</section>

<!-- ================================================================
     DATABASE ERROR
     ================================================================ -->

<?php if ($dbError): ?>

    <div class="alert-box">

        <div class="row">

            <div>

                <h4>
                    PostgreSQL connection failed
                </h4>

                <p>
                    <?= htmlspecialchars(
                        $dbError,
                        ENT_QUOTES,
                        'UTF-8'
                    ) ?>
                </p>

            </div>

        </div>

    </div>

<?php endif; ?>

<!-- ================================================================
     FILTER BAR
     ================================================================ -->

<form
    class="filter-bar"
    method="get"
    action="index.php"
>

    <input
        type="hidden"
        name="sidebar"
        value="auth"
    >

    <input
        type="hidden"
        name="page"
        value="auth_users"
    >

    <div class="filter-controls">

        <!-- Display Name -->
        <div class="search-box">

            <svg class="icon">
                <use href="#i-search"/>
            </svg>

            <input
                name="display_name"
                type="text"
                value="<?= htmlspecialchars(
                    $displayNameFilter,
                    ENT_QUOTES,
                    'UTF-8'
                ) ?>"
                placeholder="Display name starts with:"
                autocomplete="off"
            >

        </div>

        <!-- Username -->
        <div class="search-box">

            <svg class="icon">
                <use href="#i-search"/>
            </svg>

            <input
                name="username"
                type="text"
                value="<?= htmlspecialchars(
                    $usernameFilter,
                    ENT_QUOTES,
                    'UTF-8'
                ) ?>"
                placeholder="Username starts with:"
                autocomplete="off"
            >

        </div>

        <!-- Filter -->
        <button
            class="btn btn-outline btn-sm"
            type="submit"
        >
            Filter
        </button>

        <!-- Reset -->
        <a
            class="btn btn-outline btn-sm"
            href="index.php?sidebar=auth&page=auth_users"
        >
            Reset
        </a>

        <!-- Excel -->
        <a
            class="btn btn-outline btn-sm"
            href="pages/auth/auth_users.php?<?= htmlspecialchars(
                http_build_query(
                    [
                        'export' => 'excel',
                        'display_name' => $displayNameFilter,
                        'username' => $usernameFilter
                    ]
                ),
                ENT_QUOTES,
                'UTF-8'
            ) ?>"
        >
            <svg class="icon icon-sm">
                <use href="#i-download"/>
            </svg>

            Export Excel
        </a>

    </div>

</form>

<!-- ================================================================
     RESULT COUNT
     ================================================================ -->

<div class="filter-count">

    Showing

    <b>
        <?= $totalUsers
            ? (($currentPage - 1) * $perPage + 1)
            : 0
        ?>

        -

        <?= min(
            $currentPage * $perPage,
            $totalUsers
        ) ?>
    </b>

    of

    <span>
        <?= number_format($totalUsers) ?>
    </span>

    Users

</div>

<!-- ================================================================
     USERS TABLE
     ================================================================ -->

<div class="table-wrap">

    <table>

        <thead>

            <tr>

                <th>
                    Display Name (username)
                </th>

                <th>
                    Email
                </th>

                <th>
                    Mobile
                </th>

                <th>
                    User Type
                </th>

                <th>
                    Created At
                </th>

                <th class="right">
                    Actions
                </th>

            </tr>

        </thead>

        <tbody>

        <?php if (!$users): ?>

            <tr class="empty-row">

                <td colspan="6">

                    <?= $dbError
                        ? 'Unable to load users from PostgreSQL.'
                        : 'No users found matching the selected filters.'
                    ?>

                </td>

            </tr>

        <?php endif; ?>

        <?php foreach ($users as $user): ?>

            <?php

            $typeLabel = authUserTypeLabel(
                $user['user_type']
            );

            $isAdmin =
                ((int)$user['user_type'] === 1);

            $isLocked =
                !empty($user['locked_at']);

            ?>

            <tr>

                <!-- Display Name + Username -->
                <td>

                    <div class="user-cell">

                        <div class="name">

                            <?= htmlspecialchars(
                                $user['display_name'] ?: '-',
                                ENT_QUOTES,
                                'UTF-8'
                            ) ?>

                        </div>

                        <div class="email">

                            @<?= htmlspecialchars(
                                $user['username'] ?: '-',
                                ENT_QUOTES,
                                'UTF-8'
                            ) ?>

                        </div>

                    </div>

                </td>

                <!-- Email -->
                <td>

                    <?= htmlspecialchars(
                        $user['email'] ?: '-',
                        ENT_QUOTES,
                        'UTF-8'
                    ) ?>

                </td>

                <!-- Mobile -->
                <td>

                    <?= htmlspecialchars(
                        $user['mobile'] ?: '-',
                        ENT_QUOTES,
                        'UTF-8'
                    ) ?>

                </td>

                <!-- User Type -->
                <td>

                    <span
                        class="role-badge <?= $typeLabel ?>"
                    >
                        <?= htmlspecialchars(
                            $typeLabel,
                            ENT_QUOTES,
                            'UTF-8'
                        ) ?>
                    </span>

                </td>

                <!-- Created At -->
                <td>

                    <?= htmlspecialchars(
                        authUserFormatDate(
                            $user['created_at']
                        ),
                        ENT_QUOTES,
                        'UTF-8'
                    ) ?>

                </td>

                <!-- Actions -->
                <td class="right">

                    <div class="row-actions">

                        <!-- Edit -->
                        <button
                            type="button"
                            class="mini-btn"
                            onclick='openAuthUserModal(<?= json_encode(
                                $user,
                                JSON_HEX_TAG |
                                JSON_HEX_APOS |
                                JSON_HEX_AMP |
                                JSON_HEX_QUOT
                            ) ?>)'
                        >
                            Edit
                        </button>

                        <!-- Lock / Unlock -->
                        <button
                            type="button"
                            class="mini-btn <?= $isLocked ? 'green' : 'warn' ?>"
                            onclick="toggleAuthUserLock(
                                <?= (int)$user['id'] ?>,
                                <?= $isLocked ? 'true' : 'false' ?>
                            )"
                        >
                            <?= $isLocked ? 'Unlock' : 'Lock' ?>
                        </button>

                        <!-- Admin Toggle -->
                        <button
                            type="button"
                            class="mini-btn <?= $isAdmin ? 'danger' : 'green' ?>"
                            onclick="toggleAuthUserAdmin(
                                <?= (int)$user['id'] ?>,
                                <?= $isAdmin ? 'true' : 'false' ?>
                            )"
                        >
                            <?= $isAdmin
                                ? 'Remove Admin'
                                : 'Make Admin'
                            ?>
                        </button>

                    </div>

                </td>

            </tr>

        <?php endforeach; ?>

        </tbody>

    </table>

</div>

<!-- ================================================================
     PAGINATION
     ================================================================ -->

<?php if ($totalPages > 1): ?>

    <div class="pagination">

        <?php if ($currentPage > 1): ?>

            <a
                class="btn btn-outline btn-sm"
                href="index.php?<?= htmlspecialchars(
                    authUsersQuery([
                        'p' => $currentPage - 1
                    ]),
                    ENT_QUOTES,
                    'UTF-8'
                ) ?>"
            >
                Previous
            </a>

        <?php endif; ?>

        <span class="pagination-info">

            Page
            <?= $currentPage ?>
            of
            <?= $totalPages ?>

        </span>

        <?php if ($currentPage < $totalPages): ?>

            <a
                class="btn btn-outline btn-sm"
                href="index.php?<?= htmlspecialchars(
                    authUsersQuery([
                        'p' => $currentPage + 1
                    ]),
                    ENT_QUOTES,
                    'UTF-8'
                ) ?>"
            >
                Next
            </a>

        <?php endif; ?>

    </div>

<?php endif; ?>

<!-- ================================================================
     EDIT USER MODAL
     ================================================================ -->

<div
    id="auth-user-modal"
    class="user-popup-overlay"
    hidden
>

    <div
        class="user-popup"
        role="dialog"
        aria-modal="true"
        aria-labelledby="auth-user-modal-title"
    >

        <div class="user-popup-header">

            <div>

                <h2 id="auth-user-modal-title">
                    Edit Auth User
                </h2>

                <p>
                    Update the user's authentication profile.
                </p>

            </div>

            <button
                type="button"
                class="user-popup-close"
                onclick="closeAuthUserModal()"
                aria-label="Close"
            >
                &times;
            </button>

        </div>

        <form id="auth-user-edit-form">

            <input
                type="hidden"
                name="action"
                value="edit"
            >

            <input
                type="hidden"
                name="csrf_token"
                value="<?= htmlspecialchars(
                    $csrfToken,
                    ENT_QUOTES,
                    'UTF-8'
                ) ?>"
            >

            <input
                id="auth-modal-id"
                type="hidden"
                name="id"
            >

            <div class="user-popup-grid">

                <div class="field">

                    <label>
                        Username
                    </label>

                    <input
                        id="auth-modal-username"
                        name="username"
                        type="text"
                        maxlength="100"
                        required
                    >

                </div>

                <div class="field">

                    <label>
                        Display Name
                    </label>

                    <input
                        id="auth-modal-display-name"
                        name="display_name"
                        type="text"
                        maxlength="100"
                    >

                </div>

                <div class="field">

                    <label>
                        Email
                    </label>

                    <input
                        id="auth-modal-email"
                        name="email"
                        type="email"
                    >

                </div>

                <div class="field">

                    <label>
                        Mobile
                    </label>

                    <input
                        id="auth-modal-mobile"
                        name="mobile"
                        type="text"
                        maxlength="16"
                    >

                </div>

                <div class="field">

                    <label>
                        User Type
                    </label>

                    <input
                        id="auth-modal-user-type"
                        type="text"
                        disabled
                    >

                </div>

                <div class="field">

                    <label>
                        Status
                    </label>

                    <input
                        id="auth-modal-status"
                        type="text"
                        disabled
                    >

                </div>

                <div class="field">

                    <label>
                        Created At
                    </label>

                    <input
                        id="auth-modal-created"
                        type="text"
                        disabled
                    >

                </div>

                <div class="field">

                    <label>
                        Updated At
                    </label>

                    <input
                        id="auth-modal-updated"
                        type="text"
                        disabled
                    >

                </div>

            </div>

            <div class="user-popup-footer">

                <button
                    type="button"
                    class="btn btn-outline"
                    onclick="closeAuthUserModal()"
                >
                    Close
                </button>

                <button
                    type="submit"
                    class="btn btn-navy"
                    id="auth-user-save-btn"
                >
                    Save Changes
                </button>

            </div>

        </form>

    </div>

</div>

<!-- ================================================================
     TOAST
     ================================================================ -->

<div id="auth-user-toast-stack"></div>

<script>
/*
|--------------------------------------------------------------------------
| Auth Users JavaScript
|--------------------------------------------------------------------------
*/

const authUserModal =
    document.getElementById('auth-user-modal');

const authUserEditForm =
    document.getElementById('auth-user-edit-form');

const authUserSaveBtn =
    document.getElementById('auth-user-save-btn');

const authUserCsrf =
    <?= json_encode($csrfToken) ?>;


/*
|--------------------------------------------------------------------------
| Toast
|--------------------------------------------------------------------------
*/

function showAuthUserToast(message, type = 'success') {

    const stack =
        document.getElementById(
            'auth-user-toast-stack'
        );

    if (!stack) {
        alert(message);
        return;
    }

    const toast =
        document.createElement('div');

    toast.className =
        'toast' +
        (type === 'error' ? ' error' : '');

    toast.textContent = message;

    stack.appendChild(toast);

    setTimeout(() => {

        toast.remove();

    }, 3500);
}


/*
|--------------------------------------------------------------------------
| Modal
|--------------------------------------------------------------------------
*/

function openAuthUserModal(user) {

    document.getElementById(
        'auth-modal-id'
    ).value = user.id ?? '';

    document.getElementById(
        'auth-modal-username'
    ).value = user.username ?? '';

    document.getElementById(
        'auth-modal-display-name'
    ).value = user.display_name ?? '';

    document.getElementById(
        'auth-modal-email'
    ).value = user.email ?? '';

    document.getElementById(
        'auth-modal-mobile'
    ).value = user.mobile ?? '';

    document.getElementById(
        'auth-modal-user-type'
    ).value =
        Number(user.user_type) === 1
            ? 'Admin'
            : 'User';

    document.getElementById(
        'auth-modal-status'
    ).value =
        user.locked_at
            ? 'Locked'
            : 'Active';

    document.getElementById(
        'auth-modal-created'
    ).value =
        user.created_at ?? '-';

    document.getElementById(
        'auth-modal-updated'
    ).value =
        user.updated_at ?? '-';

    authUserModal.hidden = false;

    document.body.style.overflow = 'hidden';

    setTimeout(() => {

        document.getElementById(
            'auth-modal-username'
        )?.focus();

    }, 50);
}


function closeAuthUserModal() {

    authUserModal.hidden = true;

    document.body.style.overflow = '';

}


/*
|--------------------------------------------------------------------------
| Generic Action
|--------------------------------------------------------------------------
*/

async function executeAuthUserAction(
    action,
    id,
    extraData = {}
) {

    const formData =
        new FormData();

    formData.append(
        'action',
        action
    );

    formData.append(
        'id',
        id
    );

    formData.append(
        'csrf_token',
        authUserCsrf
    );

    Object.entries(extraData).forEach(
        ([key, value]) => {

            formData.append(
                key,
                value ?? ''
            );

        }
    );

    try {

        const response =
            await fetch(
                'pages/auth/auth_users.php',
                {
                    method: 'POST',
                    body: formData,
                    credentials: 'same-origin'
                }
            );

        const data =
            await response.json();

        if (!data.success) {

            throw new Error(
                data.message ||
                'Operation failed.'
            );
        }

        showAuthUserToast(
            data.message,
            'success'
        );

        setTimeout(() => {

            window.location.reload();

        }, 500);

    } catch (error) {

        showAuthUserToast(
            error.message ||
            'Unable to complete the operation.',
            'error'
        );
    }
}


/*
|--------------------------------------------------------------------------
| Lock / Unlock
|--------------------------------------------------------------------------
*/

async function toggleAuthUserLock(
    id,
    currentlyLocked
) {

    const actionText =
        currentlyLocked
            ? 'unlock this user'
            : 'lock this user';

    if (
        !confirm(
            'Are you sure you want to ' +
            actionText +
            '?'
        )
    ) {
        return;
    }

    await executeAuthUserAction(
        'toggle_lock',
        id
    );
}


/*
|--------------------------------------------------------------------------
| Make Admin / Remove Admin
|--------------------------------------------------------------------------
*/

async function toggleAuthUserAdmin(
    id,
    currentlyAdmin
) {

    const actionText =
        currentlyAdmin
            ? 'remove Admin access from this user'
            : 'make this user an Admin';

    if (
        !confirm(
            'Are you sure you want to ' +
            actionText +
            '?'
        )
    ) {
        return;
    }

    await executeAuthUserAction(
        'toggle_admin',
        id
    );
}


/*
|--------------------------------------------------------------------------
| Edit User
|--------------------------------------------------------------------------
*/

authUserEditForm?.addEventListener(
    'submit',
    async function (event) {

        event.preventDefault();

        if (!authUserSaveBtn) {
            return;
        }

        authUserSaveBtn.disabled = true;

        authUserSaveBtn.textContent =
            'Saving...';

        try {

            const formData =
                new FormData(
                    authUserEditForm
                );

            const response =
                await fetch(
                    'pages/auth/auth_users.php',
                    {
                        method: 'POST',
                        body: formData,
                        credentials: 'same-origin'
                    }
                );

            const data =
                await response.json();

            if (!data.success) {

                throw new Error(
                    data.message ||
                    'Unable to update user.'
                );
            }

            closeAuthUserModal();

            showAuthUserToast(
                data.message,
                'success'
            );

            setTimeout(() => {

                window.location.reload();

            }, 500);

        } catch (error) {

            showAuthUserToast(
                error.message ||
                'Unable to update user.',
                'error'
            );

        } finally {

            authUserSaveBtn.disabled = false;

            authUserSaveBtn.textContent =
                'Save Changes';
        }
    }
);


/*
|--------------------------------------------------------------------------
| Close modal when clicking overlay
|--------------------------------------------------------------------------
*/

authUserModal?.addEventListener(
    'click',
    function (event) {

        if (event.target === authUserModal) {

            closeAuthUserModal();

        }

    }
);


/*
|--------------------------------------------------------------------------
| ESC closes modal
|--------------------------------------------------------------------------
*/

document.addEventListener(
    'keydown',
    function (event) {

        if (
            event.key === 'Escape' &&
            authUserModal &&
            !authUserModal.hidden
        ) {

            closeAuthUserModal();

        }

    }
);

</script>
```
