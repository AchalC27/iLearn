<?php

/*
|--------------------------------------------------------------------------
| MULTIPIE AUTH - REFERRAL CODES
|--------------------------------------------------------------------------
|
| Database:
|   multipie_auth_prod
|
| Tables:
|   public.referral_codes
|   public.users
|
| Features:
|   - Code equals filter
|   - User Username starts-with filter
|   - Pagination
|   - Excel export
|   - New Referral popup
|   - Edit Referral popup
|   - View Referral popup
|   - AJAX POST actions
|
|--------------------------------------------------------------------------
*/

if (!function_exists('getAuthDb')) {
    require_once __DIR__ . '/../../includes/bootstrap.php';
}


/*
|--------------------------------------------------------------------------
| SESSION / CSRF
|--------------------------------------------------------------------------
*/

if (session_status() !== PHP_SESSION_ACTIVE) {
    @session_start();
}

if (empty($_SESSION['auth_referral_csrf'])) {
    $_SESSION['auth_referral_csrf'] = bin2hex(random_bytes(32));
}

$csrfToken = $_SESSION['auth_referral_csrf'];


/*
|--------------------------------------------------------------------------
| HELPERS
|--------------------------------------------------------------------------
*/

function authReferralEscape($value): string
{
    return htmlspecialchars(
        (string)$value,
        ENT_QUOTES,
        'UTF-8'
    );
}


function authReferralFormatDate($value): string
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


function authReferralFormatDateTime($value): string
{
    if (empty($value)) {
        return '-';
    }

    try {
        return (new DateTime((string)$value))
            ->format('d M Y, h:i A');
    } catch (Throwable $e) {
        return (string)$value;
    }
}


/*
|--------------------------------------------------------------------------
| QUERY
|--------------------------------------------------------------------------
*/

function authReferralQuery(array $changes = []): string
{
    $params = [
        'sidebar' => 'auth',
        'page'    => 'auth/referral_codes',

        'code' => trim(
            (string)($_GET['code'] ?? '')
        ),

        'username' => trim(
            (string)($_GET['username'] ?? '')
        ),

        'p' => max(
            1,
            (int)($_GET['p'] ?? 1)
        )
    ];

    $params = array_merge(
        $params,
        $changes
    );

    foreach ($params as $key => $value) {
        if (
            $value === '' ||
            $value === null
        ) {
            unset($params[$key]);
        }
    }

    return http_build_query($params);
}


/*
|--------------------------------------------------------------------------
| FILTERS
|--------------------------------------------------------------------------
*/

$codeFilter = trim(
    (string)($_GET['code'] ?? '')
);

$usernameFilter = trim(
    (string)($_GET['username'] ?? '')
);


/*
|--------------------------------------------------------------------------
| PAGINATION
|--------------------------------------------------------------------------
*/

$perPage = 25;

$currentPage = max(
    1,
    (int)($_GET['p'] ?? 1)
);

$totalReferralCodes = 0;
$totalPages = 1;
$offset = 0;

$referralCodes = [];

$dbError = null;


/*
|--------------------------------------------------------------------------
| AJAX / POST ACTIONS
|--------------------------------------------------------------------------
|
| Same pattern as auth_users.php.
|
| Supported:
|
|   create
|   update
|   view
|
|--------------------------------------------------------------------------
*/

if (
    $_SERVER['REQUEST_METHOD'] === 'POST' &&
    isset($_POST['action'])
) {

    header(
        'Content-Type: application/json; charset=utf-8'
    );

    try {

        /*
        |--------------------------------------------------------------------------
        | CSRF
        |--------------------------------------------------------------------------
        */

        $postedToken = (string)(
            $_POST['csrf_token'] ?? ''
        );

        if (
            empty($_SESSION['auth_referral_csrf']) ||
            !hash_equals(
                $_SESSION['auth_referral_csrf'],
                $postedToken
            )
        ) {
            throw new RuntimeException(
                'Invalid security token. Please refresh the page.'
            );
        }


        $action = trim(
            (string)($_POST['action'] ?? '')
        );


        $pdo = getAuthDb();


        /*
        |--------------------------------------------------------------------------
        | CREATE REFERRAL
        |--------------------------------------------------------------------------
        */

        if ($action === 'create') {

            $code = trim(
                (string)($_POST['code'] ?? '')
            );

            $usageCapRaw = trim(
                (string)($_POST['usage_cap'] ?? '')
            );

            $expiryDate = trim(
                (string)($_POST['expiry_date'] ?? '')
            );

            $referrerId = (int)(
                $_POST['referrer_id'] ?? 0
            );


            /*
            |--------------------------------------------------------------------------
            | Validation
            |--------------------------------------------------------------------------
            */

            if ($code === '') {
                throw new InvalidArgumentException(
                    'Referral code is required.'
                );
            }

            if (mb_strlen($code) > 16) {
                throw new InvalidArgumentException(
                    'Referral code cannot exceed 16 characters.'
                );
            }

            if (
                $usageCapRaw === '' ||
                !ctype_digit($usageCapRaw)
            ) {
                throw new InvalidArgumentException(
                    'Usage cap must be a valid number.'
                );
            }

            $usageCap = (int)$usageCapRaw;

            if ($usageCap < 0) {
                throw new InvalidArgumentException(
                    'Usage cap cannot be negative.'
                );
            }


            /*
            |--------------------------------------------------------------------------
            | Expiry
            |--------------------------------------------------------------------------
            */

            if ($expiryDate !== '') {

                $dateObject =
                    DateTime::createFromFormat(
                        'Y-m-d',
                        $expiryDate
                    );

                if (
                    !$dateObject ||
                    $dateObject->format('Y-m-d') !== $expiryDate
                ) {
                    throw new InvalidArgumentException(
                        'Please enter a valid expiry date.'
                    );
                }

            } else {
                $expiryDate = null;
            }


            /*
            |--------------------------------------------------------------------------
            | Referrer
            |--------------------------------------------------------------------------
            */

            if ($referrerId <= 0) {
                $referrerId = null;
            }

            if ($referrerId !== null) {

                $userCheck = $pdo->prepare("
                    SELECT id
                    FROM public.users
                    WHERE id = :id
                    LIMIT 1
                ");

                $userCheck->execute([
                    ':id' => $referrerId
                ]);

                if (!$userCheck->fetch()) {
                    throw new RuntimeException(
                        'Selected referrer user does not exist.'
                    );
                }
            }


            /*
            |--------------------------------------------------------------------------
            | Duplicate Code
            |--------------------------------------------------------------------------
            */

            $check = $pdo->prepare("
                SELECT id
                FROM public.referral_codes
                WHERE LOWER(code) = LOWER(:code)
                LIMIT 1
            ");

            $check->execute([
                ':code' => $code
            ]);

            if ($check->fetch()) {
                throw new RuntimeException(
                    'This referral code already exists.'
                );
            }


            /*
            |--------------------------------------------------------------------------
            | Insert
            |--------------------------------------------------------------------------
            */

            $stmt = $pdo->prepare("
                INSERT INTO public.referral_codes
                (
                    referrer_id,
                    code,
                    usage_cap,
                    expiry_date,
                    created_at,
                    updated_at
                )
                VALUES
                (
                    :referrer_id,
                    :code,
                    :usage_cap,
                    :expiry_date,
                    NOW(),
                    NOW()
                )
                RETURNING id
            ");


            $stmt->bindValue(
                ':referrer_id',
                $referrerId,
                $referrerId === null
                    ? PDO::PARAM_NULL
                    : PDO::PARAM_INT
            );

            $stmt->bindValue(
                ':code',
                $code,
                PDO::PARAM_STR
            );

            $stmt->bindValue(
                ':usage_cap',
                $usageCap,
                PDO::PARAM_INT
            );

            $stmt->bindValue(
                ':expiry_date',
                $expiryDate,
                $expiryDate === null
                    ? PDO::PARAM_NULL
                    : PDO::PARAM_STR
            );

            $stmt->execute();

            $newId = (int)$stmt->fetchColumn();


            echo json_encode([
                'success' => true,
                'message' => 'Referral code created successfully.',
                'id'      => $newId
            ]);

            exit;
        }


        /*
        |--------------------------------------------------------------------------
        | UPDATE REFERRAL
        |--------------------------------------------------------------------------
        */

        if ($action === 'update') {

            $id = (int)(
                $_POST['id'] ?? 0
            );

            if ($id <= 0) {
                throw new InvalidArgumentException(
                    'Invalid referral code ID.'
                );
            }


            $code = trim(
                (string)($_POST['code'] ?? '')
            );

            $usageCapRaw = trim(
                (string)($_POST['usage_cap'] ?? '')
            );

            $expiryDate = trim(
                (string)($_POST['expiry_date'] ?? '')
            );


            /*
            |--------------------------------------------------------------------------
            | Validation
            |--------------------------------------------------------------------------
            */

            if ($code === '') {
                throw new InvalidArgumentException(
                    'Referral code is required.'
                );
            }

            if (mb_strlen($code) > 16) {
                throw new InvalidArgumentException(
                    'Referral code cannot exceed 16 characters.'
                );
            }

            if (
                $usageCapRaw === '' ||
                !ctype_digit($usageCapRaw)
            ) {
                throw new InvalidArgumentException(
                    'Usage cap must be a valid number.'
                );
            }

            $usageCap = (int)$usageCapRaw;

            if ($usageCap < 0) {
                throw new InvalidArgumentException(
                    'Usage cap cannot be negative.'
                );
            }


            /*
            |--------------------------------------------------------------------------
            | Expiry
            |--------------------------------------------------------------------------
            */

            if ($expiryDate !== '') {

                $dateObject =
                    DateTime::createFromFormat(
                        'Y-m-d',
                        $expiryDate
                    );

                if (
                    !$dateObject ||
                    $dateObject->format('Y-m-d') !== $expiryDate
                ) {
                    throw new InvalidArgumentException(
                        'Please enter a valid expiry date.'
                    );
                }

            } else {
                $expiryDate = null;
            }


            /*
            |--------------------------------------------------------------------------
            | Duplicate Code
            |--------------------------------------------------------------------------
            */

            $check = $pdo->prepare("
                SELECT id
                FROM public.referral_codes
                WHERE LOWER(code) = LOWER(:code)
                  AND id <> :id
                LIMIT 1
            ");

            $check->execute([
                ':code' => $code,
                ':id'   => $id
            ]);

            if ($check->fetch()) {
                throw new RuntimeException(
                    'This referral code already exists.'
                );
            }


            /*
            |--------------------------------------------------------------------------
            | Update
            |--------------------------------------------------------------------------
            */

            $stmt = $pdo->prepare("
                UPDATE public.referral_codes
                SET
                    code = :code,
                    usage_cap = :usage_cap,
                    expiry_date = :expiry_date,
                    updated_at = NOW()
                WHERE id = :id
            ");


            $stmt->bindValue(
                ':code',
                $code,
                PDO::PARAM_STR
            );

            $stmt->bindValue(
                ':usage_cap',
                $usageCap,
                PDO::PARAM_INT
            );

            $stmt->bindValue(
                ':expiry_date',
                $expiryDate,
                $expiryDate === null
                    ? PDO::PARAM_NULL
                    : PDO::PARAM_STR
            );

            $stmt->bindValue(
                ':id',
                $id,
                PDO::PARAM_INT
            );

            $stmt->execute();


            echo json_encode([
                'success' => true,
                'message' => 'Referral code updated successfully.'
            ]);

            exit;
        }


        /*
        |--------------------------------------------------------------------------
        | VIEW REFERRAL
        |--------------------------------------------------------------------------
        |
        | This is called by the View popup.
        |
        |--------------------------------------------------------------------------
        */

        if ($action === 'view') {

            $id = (int)(
                $_POST['id'] ?? 0
            );

            if ($id <= 0) {
                throw new InvalidArgumentException(
                    'Invalid referral code ID.'
                );
            }


            /*
            |--------------------------------------------------------------------------
            | Referral Details
            |--------------------------------------------------------------------------
            */

            $stmt = $pdo->prepare("
                SELECT
                    rc.id,
                    rc.referrer_id,
                    rc.code,
                    rc.usage_cap,
                    rc.expiry_date,
                    rc.created_at,
                    rc.updated_at,

                    u.username AS referrer_username,
                    u.display_name AS referrer_display_name,
                    u.email AS referrer_email,

                    COUNT(used_users.id) AS usage_count

                FROM public.referral_codes rc

                LEFT JOIN public.users u
                    ON u.id = rc.referrer_id

                LEFT JOIN public.users used_users
                    ON used_users.referral_code_id = rc.id

                WHERE rc.id = :id

                GROUP BY
                    rc.id,
                    rc.referrer_id,
                    rc.code,
                    rc.usage_cap,
                    rc.expiry_date,
                    rc.created_at,
                    rc.updated_at,
                    u.username,
                    u.display_name,
                    u.email

                LIMIT 1
            ");


            $stmt->execute([
                ':id' => $id
            ]);


            $referral =
                $stmt->fetch(PDO::FETCH_ASSOC);


            if (!$referral) {
                throw new RuntimeException(
                    'Referral code not found.'
                );
            }


            /*
            |--------------------------------------------------------------------------
            | Users Using Referral
            |--------------------------------------------------------------------------
            */

            $usersStmt = $pdo->prepare("
                SELECT
                    id,
                    display_name,
                    username,
                    email,
                    created_at
                FROM public.users
                WHERE referral_code_id = :referral_id
                ORDER BY created_at ASC, id ASC
            ");

            $usersStmt->execute([
                ':referral_id' => $id
            ]);


            $usedUsers =
                $usersStmt->fetchAll(PDO::FETCH_ASSOC);


            echo json_encode([
                'success'    => true,
                'referral'   => $referral,
                'used_users' => $usedUsers
            ]);

            exit;
        }


        throw new RuntimeException(
            'Unknown referral action.'
        );


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
*/

if (
    $_SERVER['REQUEST_METHOD'] === 'GET' &&
    ($_GET['export'] ?? '') === 'excel'
) {

    try {

        $pdo = getAuthDb();

        $where = [];
        $params = [];


        /*
        |--------------------------------------------------------------------------
        | Code filter
        |--------------------------------------------------------------------------
        */

        if ($codeFilter !== '') {

            $where[] = "
                LOWER(rc.code) = LOWER(:code)
            ";

            $params[':code'] =
                $codeFilter;
        }


        /*
        |--------------------------------------------------------------------------
        | Username filter
        |--------------------------------------------------------------------------
        */

        if ($usernameFilter !== '') {

            $where[] = "
                LOWER(COALESCE(u.username, ''))
                LIKE LOWER(:username)
            ";

            $params[':username'] =
                $usernameFilter . '%';
        }


        $whereSql = $where
            ? 'WHERE ' . implode(
                ' AND ',
                $where
            )
            : '';


        $stmt = $pdo->prepare("
            SELECT
                rc.id,
                rc.code,
                rc.usage_cap,

                COUNT(used_users.id)
                    AS usage_count,

                rc.expiry_date,

                COALESCE(
                    u.display_name,
                    u.username,
                    '-'
                ) AS referrer,

                u.username
                    AS referrer_username,

                rc.created_at,
                rc.updated_at

            FROM public.referral_codes rc

            LEFT JOIN public.users u
                ON u.id = rc.referrer_id

            LEFT JOIN public.users used_users
                ON used_users.referral_code_id = rc.id

            {$whereSql}

            GROUP BY
                rc.id,
                rc.code,
                rc.usage_cap,
                rc.expiry_date,
                u.display_name,
                u.username,
                rc.created_at,
                rc.updated_at

            ORDER BY rc.id DESC
        ");


        foreach ($params as $key => $value) {

            $stmt->bindValue(
                $key,
                $value,
                PDO::PARAM_STR
            );
        }


        $stmt->execute();

        $exportRows =
            $stmt->fetchAll(PDO::FETCH_ASSOC);


        /*
        |--------------------------------------------------------------------------
        | Excel-compatible XLS
        |--------------------------------------------------------------------------
        */

        $filename =
            'auth_referral_codes_' .
            date('Y-m-d_H-i-s') .
            '.xls';


        header(
            'Content-Type: application/vnd.ms-excel; charset=UTF-8'
        );

        header(
            'Content-Disposition: attachment; filename="' .
            $filename .
            '"'
        );

        header(
            'Cache-Control: max-age=0'
        );

        header(
            'Pragma: public'
        );


        echo "\xEF\xBB\xBF";

        echo '<html>';
        echo '<head>';
        echo '<meta charset="UTF-8">';

        echo '<style>';

        echo 'table{border-collapse:collapse;}';

        echo 'th{
            background:#0f172a;
            color:#ffffff;
            font-weight:bold;
        }';

        echo 'th,td{
            border:1px solid #d1d5db;
            padding:7px;
        }';

        echo '</style>';

        echo '</head>';

        echo '<body>';

        echo '<table>';


        echo '<tr>';

        echo '<th>Referral Code</th>';
        echo '<th>Usage Cap</th>';
        echo '<th>Usage Count</th>';
        echo '<th>Expiry</th>';
        echo '<th>Referrer</th>';
        echo '<th>Referrer Username</th>';
        echo '<th>Created At</th>';
        echo '<th>Updated At</th>';

        echo '</tr>';


        foreach ($exportRows as $row) {

            echo '<tr>';

            echo '<td>' .
                authReferralEscape(
                    $row['code']
                ) .
                '</td>';

            echo '<td>' .
                (int)$row['usage_cap'] .
                '</td>';

            echo '<td>' .
                (int)$row['usage_count'] .
                '</td>';

            echo '<td>' .
                authReferralEscape(
                    $row['expiry_date'] ?: '-'
                ) .
                '</td>';

            echo '<td>' .
                authReferralEscape(
                    $row['referrer']
                ) .
                '</td>';

            echo '<td>' .
                authReferralEscape(
                    $row['referrer_username'] ?: '-'
                ) .
                '</td>';

            echo '<td>' .
                authReferralEscape(
                    authReferralFormatDateTime(
                        $row['created_at']
                    )
                ) .
                '</td>';

            echo '<td>' .
                authReferralEscape(
                    authReferralFormatDateTime(
                        $row['updated_at']
                    )
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

        echo 'Unable to export referral codes: ' .
            authReferralEscape(
                $e->getMessage()
            );

        exit;
    }
}


/*
|--------------------------------------------------------------------------
| BUILD LIST QUERY
|--------------------------------------------------------------------------
*/

$where = [];
$params = [];


if ($codeFilter !== '') {

    $where[] = "
        LOWER(rc.code) = LOWER(:code)
    ";

    $params[':code'] =
        $codeFilter;
}


if ($usernameFilter !== '') {

    $where[] = "
        LOWER(COALESCE(u.username, ''))
        LIKE LOWER(:username)
    ";

    $params[':username'] =
        $usernameFilter . '%';
}


$whereSql = $where
    ? 'WHERE ' . implode(
        ' AND ',
        $where
    )
    : '';


/*
|--------------------------------------------------------------------------
| DATABASE
|--------------------------------------------------------------------------
*/

try {

    $pdo = getAuthDb();


    /*
    |--------------------------------------------------------------------------
    | COUNT
    |--------------------------------------------------------------------------
    */

    $countStmt = $pdo->prepare("
        SELECT COUNT(*)

        FROM public.referral_codes rc

        LEFT JOIN public.users u
            ON u.id = rc.referrer_id

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

    $totalReferralCodes =
        (int)$countStmt->fetchColumn();


    /*
    |--------------------------------------------------------------------------
    | TOTAL PAGES
    |--------------------------------------------------------------------------
    */

    $totalPages = max(
        1,
        (int)ceil(
            $totalReferralCodes /
            $perPage
        )
    );


    $currentPage = min(
        $currentPage,
        $totalPages
    );


    $offset =
        ($currentPage - 1) *
        $perPage;


    /*
    |--------------------------------------------------------------------------
    | FETCH REFERRAL CODES
    |--------------------------------------------------------------------------
    */

    $sql = "
        SELECT
            rc.id,
            rc.referrer_id,
            rc.code,
            rc.usage_cap,
            rc.expiry_date,
            rc.created_at,
            rc.updated_at,

            COALESCE(
                u.display_name,
                u.username,
                '-'
            ) AS referrer,

            u.username AS referrer_username,
            u.email AS referrer_email,

            COUNT(
                used_users.id
            ) AS usage_count

        FROM public.referral_codes rc

        LEFT JOIN public.users u
            ON u.id = rc.referrer_id

        LEFT JOIN public.users used_users
            ON used_users.referral_code_id = rc.id

        {$whereSql}

        GROUP BY
            rc.id,
            rc.referrer_id,
            rc.code,
            rc.usage_cap,
            rc.expiry_date,
            rc.created_at,
            rc.updated_at,
            u.display_name,
            u.username,
            u.email

        ORDER BY rc.id DESC

        LIMIT :limit
        OFFSET :offset
    ";


    $stmt = $pdo->prepare($sql);


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


    $referralCodes =
        $stmt->fetchAll(PDO::FETCH_ASSOC);


} catch (Throwable $e) {

    $dbError = $e->getMessage();

    $totalReferralCodes = 0;

    $totalPages = 1;

    $referralCodes = [];
}


?>


<!-- ================================================================
     PAGE HEADER
================================================================ -->

<section class="view-header">

    <div>

        <h1>

            Referral Codes

            <span class="count-pill-navy">
                <?= number_format(
                    $totalReferralCodes
                ) ?>
                Total
            </span>

        </h1>

        <p class="sub">
            Manage referral codes and their usage.
        </p>

    </div>


    <!-- NEW REFERRAL -->

    <button
        type="button"
        class="btn btn-orange"
        onclick="openReferralCreateModal()"
    >
        + New Referral
    </button>

</section>


<!-- ================================================================
     DATABASE ERROR
================================================================ -->

<?php if ($dbError): ?>

    <div class="alert-box">

        <strong>
            PostgreSQL connection failed
        </strong>

        <p>
            <?= authReferralEscape(
                $dbError
            ) ?>
        </p>

    </div>

<?php endif; ?>


<!-- ================================================================
     FILTERS
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
        value="auth/referral_codes"
    >


    <div class="filter-controls">


        <!-- CODE -->

        <div class="filter-group">

            <label>
                Code equals:
            </label>

            <input
                class="search-box"
                type="text"
                name="code"
                value="<?= authReferralEscape(
                    $codeFilter
                ) ?>"
                placeholder="Referral code"
            >

        </div>


        <!-- USERNAME -->

        <div class="filter-group">

            <label>
                User Username starts with:
            </label>

            <input
                class="search-box"
                type="text"
                name="username"
                value="<?= authReferralEscape(
                    $usernameFilter
                ) ?>"
                placeholder="Username"
            >

        </div>


        <!-- SEARCH -->

        <button
            type="submit"
            class="btn btn-navy"
        >
            Search
        </button>


        <!-- VIEW ALL -->

        <a
            class="btn btn-orange"
            href="index.php?<?= authReferralEscape(
                authReferralQuery([
                    'code' => '',
                    'username' => '',
                    'p' => 1
                ])
            ) ?>"
        >
            View All
        </a>


        <!-- RESET -->

        <a
            class="btn btn-outline btn-sm"
            href="index.php?sidebar=auth&page=auth/referral_codes"
        >
            Reset
        </a>


        <!-- EXPORT -->

        <a
            class="btn btn-outline btn-sm"
            href="pages/auth/auth_referral_codes.php?<?= authReferralEscape(
                http_build_query([
                    'export' => 'excel',
                    'code' => $codeFilter,
                    'username' => $usernameFilter
                ])
            ) ?>"
        >

            <svg class="icon icon-sm">
                <use href="#i-download"></use>
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

        <?= $totalReferralCodes
            ? (
                ($currentPage - 1) *
                $perPage + 1
            )
            : 0
        ?>

        -

        <?= min(
            $currentPage * $perPage,
            $totalReferralCodes
        ) ?>

    </b>

    of

    <span>
        <?= number_format(
            $totalReferralCodes
        ) ?>
    </span>

    Referral Codes

</div>


<!-- ================================================================
     TABLE
================================================================ -->

<div class="table-wrap">

    <table>

        <thead>

            <tr>

                <th>
                    Referral Code
                </th>

                <th>
                    Usage Cap
                </th>

                <th>
                    Usage Count
                </th>

                <th>
                    Expiry
                </th>

                <th>
                    Referrer
                </th>

                <th class="right">
                    Actions
                </th>

            </tr>

        </thead>


        <tbody>


        <?php if (!$referralCodes): ?>

            <tr class="empty-row">

                <td colspan="6">

                    <?= $dbError
                        ? 'Unable to load referral codes.'
                        : 'No referral codes found.'
                    ?>

                </td>

            </tr>

        <?php endif; ?>


        <?php foreach (
            $referralCodes
            as $referral
        ): ?>


            <?php

            /*
            |--------------------------------------------------------------------------
            | Prepare row JSON
            |--------------------------------------------------------------------------
            */

            $rowJson = json_encode(
                [
                    'id' => (int)$referral['id'],

                    'referrer_id' =>
                        $referral['referrer_id']
                            ? (int)$referral['referrer_id']
                            : null,

                    'code' =>
                        $referral['code'],

                    'usage_cap' =>
                        (int)($referral['usage_cap'] ?? 0),

                    'usage_count' =>
                        (int)($referral['usage_count'] ?? 0),

                    'expiry_date' =>
                        $referral['expiry_date'] ?? '',

                    'referrer' =>
                        $referral['referrer'] ?? '-',

                    'referrer_username' =>
                        $referral['referrer_username'] ?? '',

                    'referrer_email' =>
                        $referral['referrer_email'] ?? '',

                    'created_at' =>
                        $referral['created_at'] ?? '',

                    'updated_at' =>
                        $referral['updated_at'] ?? ''
                ],

                JSON_HEX_TAG |
                JSON_HEX_APOS |
                JSON_HEX_AMP |
                JSON_HEX_QUOT
            );

            ?>


            <tr>


                <!-- ==================================================
                     REFERRAL CODE
                =================================================== -->

                <td>
                        <?= authReferralEscape(
                            $referral['code']
                        ) ?>
                </td>


                <!-- ==================================================
                     USAGE CAP
                =================================================== -->

                <td>

                    <?= (int)(
                        $referral['usage_cap'] ?? 0
                    ) ?>

                </td>


                <!-- ==================================================
                     USAGE COUNT
                =================================================== -->

                <td>

                    <?= (int)(
                        $referral['usage_count'] ?? 0
                    ) ?>

                </td>


                <!-- ==================================================
                     EXPIRY
                =================================================== -->

                <td>

                    <?= authReferralEscape(
                        $referral['expiry_date']
                        ?: '-'
                    ) ?>

                </td>


                <!-- ==================================================
                     REFERRER
                =================================================== -->

                <td>

                    <?php if (
                        !empty(
                            $referral['referrer_username']
                        )
                    ): ?>

                        <a
                            class="referrer-btn"
                            href="index.php?sidebar=auth&page=auth/auth_users&username=<?= urlencode(
                                $referral['referrer_username']
                            ) ?>"
                        >

                            <?= authReferralEscape(
                                $referral['referrer']
                            ) ?>

                        </a>

                    <?php else: ?>

                        -

                    <?php endif; ?>

                </td>


                <!-- ==================================================
                     ACTIONS
                =================================================== -->

                <td class="right">

                    <div class="row-actions">


                        <!-- EDIT -->

                        <button
                            type="button"
                            class="mini-btn"
                            onclick='openReferralEditModal(
                                <?= $rowJson ?>
                            )'
                        >
                            Edit
                        </button>


                        <!-- VIEW -->

                        <button
                            type="button"
                            class="mini-btn"
                            onclick='openReferralViewModal(
                                <?= $rowJson ?>
                            )'
                        >
                            View
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
                href="index.php?<?= authReferralEscape(
                    authReferralQuery([
                        'p' =>
                            $currentPage - 1
                    ])
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


        <?php if (
            $currentPage < $totalPages
        ): ?>

            <a
                class="btn btn-outline btn-sm"
                href="index.php?<?= authReferralEscape(
                    authReferralQuery([
                        'p' =>
                            $currentPage + 1
                    ])
                ) ?>"
            >
                Next
            </a>

        <?php endif; ?>


    </div>

<?php endif; ?>


<!-- ================================================================
     CREATE REFERRAL MODAL
================================================================ -->

<div
    id="auth-referral-create-modal"
    class="user-popup-overlay"
    hidden
>

    <div
        class="user-popup"
        role="dialog"
        aria-modal="true"
        aria-labelledby="auth-referral-create-title"
    >


        <div class="user-popup-header">

            <div>

                <h2 id="auth-referral-create-title">
                    New Referral
                </h2>

                <p>
                    Create a new referral code.
                </p>

            </div>


            <button
                type="button"
                class="user-popup-close"
                onclick="closeReferralCreateModal()"
                aria-label="Close"
            >
                &times;
            </button>

        </div>


        <form
            id="auth-referral-create-form"
        >

            <input
                type="hidden"
                name="action"
                value="create"
            >

            <input
                type="hidden"
                name="csrf_token"
                value="<?= authReferralEscape(
                    $csrfToken
                ) ?>"
            >


            <div class="user-popup-grid">


                <div class="field">

                    <label for="new-referral-code">
                        Code
                    </label>

                    <input
                        id="new-referral-code"
                        name="code"
                        type="text"
                        maxlength="16"
                        placeholder="Referral code"
                        required
                    >

                </div>


                <div class="field">

                    <label for="new-referral-usage-cap">
                        Usage Cap
                    </label>

                    <input
                        id="new-referral-usage-cap"
                        name="usage_cap"
                        type="number"
                        min="0"
                        value="1"
                        required
                    >

                </div>


                <div class="field">

                    <label for="new-referral-expiry">
                        Expiry Date
                    </label>

                    <input
                        id="new-referral-expiry"
                        name="expiry_date"
                        type="date"
                    >

                </div>


                <div class="field">

                    <label for="new-referral-referrer">
                        Referrer User ID
                    </label>

                    <input
                        id="new-referral-referrer"
                        name="referrer_id"
                        type="number"
                        min="1"
                        placeholder="Optional"
                    >

                </div>


            </div>


            <div class="user-popup-footer">

                <button
                    type="button"
                    class="btn btn-outline"
                    onclick="closeReferralCreateModal()"
                >
                    Cancel
                </button>


                <button
                    type="submit"
                    class="btn btn-navy"
                    id="auth-referral-create-btn"
                >
                    Create Referral
                </button>

            </div>


        </form>

    </div>

</div>


<!-- ================================================================
     EDIT REFERRAL MODAL
================================================================ -->

<div
    id="auth-referral-edit-modal"
    class="user-popup-overlay"
    hidden
>

    <div
        class="user-popup"
        role="dialog"
        aria-modal="true"
        aria-labelledby="auth-referral-edit-title"
    >


        <div class="user-popup-header">

            <div>

                <h2 id="auth-referral-edit-title">
                    Edit Referral
                </h2>

                <p>
                    Update the referral code details.
                </p>

            </div>


            <button
                type="button"
                class="user-popup-close"
                onclick="closeReferralEditModal()"
                aria-label="Close"
            >
                &times;
            </button>

        </div>


        <form
            id="auth-referral-edit-form"
        >

            <input
                type="hidden"
                name="action"
                value="update"
            >

            <input
                type="hidden"
                name="csrf_token"
                value="<?= authReferralEscape(
                    $csrfToken
                ) ?>"
            >

            <input
                id="auth-referral-edit-id"
                type="hidden"
                name="id"
            >


            <div class="user-popup-grid">


                <div class="field">

                    <label for="auth-referral-edit-code">
                        Code
                    </label>

                    <input
                        id="auth-referral-edit-code"
                        name="code"
                        type="text"
                        maxlength="16"
                        required
                    >

                </div>


                <div class="field">

                    <label for="auth-referral-edit-usage-cap">
                        Usage Cap
                    </label>

                    <input
                        id="auth-referral-edit-usage-cap"
                        name="usage_cap"
                        type="number"
                        min="0"
                        required
                    >

                </div>


                <div class="field">

                    <label for="auth-referral-edit-expiry">
                        Expiry Date
                    </label>

                    <input
                        id="auth-referral-edit-expiry"
                        name="expiry_date"
                        type="date"
                    >

                </div>


                <div class="field">

                    <label for="auth-referral-edit-referrer">
                        Created By
                    </label>

                    <input
                        id="auth-referral-edit-referrer"
                        type="text"
                        disabled
                    >

                </div>


                <div class="field">

                    <label for="auth-referral-edit-created">
                        Created At
                    </label>

                    <input
                        id="auth-referral-edit-created"
                        type="text"
                        disabled
                    >

                </div>


                <div class="field">

                    <label for="auth-referral-edit-updated">
                        Updated At
                    </label>

                    <input
                        id="auth-referral-edit-updated"
                        type="text"
                        disabled
                    >

                </div>


            </div>


            <div class="user-popup-footer">

                <button
                    type="button"
                    class="btn btn-outline"
                    onclick="closeReferralEditModal()"
                >
                    Cancel
                </button>


                <button
                    type="submit"
                    class="btn btn-navy"
                    id="auth-referral-save-btn"
                >
                    Save Changes
                </button>

            </div>


        </form>

    </div>

</div>


<!-- ================================================================
     VIEW REFERRAL MODAL
================================================================ -->

<div
    id="auth-referral-view-modal"
    class="user-popup-overlay"
    hidden
>

    <div
        class="user-popup referral-page-card"
        role="dialog"
        aria-modal="true"
        aria-labelledby="auth-referral-view-title"
    >


        <div class="user-popup-header">

            <div>

                <h2 id="auth-referral-view-title">
                    Referral
                </h2>

                <p>
                    Referral code details and usage.
                </p>

            </div>


            <button
                type="button"
                class="user-popup-close"
                onclick="closeReferralViewModal()"
                aria-label="Close"
            >
                &times;
            </button>

        </div>


        <!-- DETAILS -->

        <div class="user-popup-grid">


            <div class="field">

                <label>
                    Referral Code
                </label>

                <input
                    id="auth-referral-view-code"
                    type="text"
                    disabled
                >

            </div>


            <div class="field">

                <label>
                    Usage Cap
                </label>

                <input
                    id="auth-referral-view-usage-cap"
                    type="text"
                    disabled
                >

            </div>


            <div class="field">

                <label>
                    Usage Count
                </label>

                <input
                    id="auth-referral-view-usage-count"
                    type="text"
                    disabled
                >

            </div>


            <div class="field">

                <label>
                    Expiry Date
                </label>

                <input
                    id="auth-referral-view-expiry"
                    type="text"
                    disabled
                >

            </div>


            <div class="field">

                <label>
                    Created By
                </label>

                <input
                    id="auth-referral-view-created-by"
                    type="text"
                    disabled
                >

            </div>


            <div class="field">

                <label>
                    Email
                </label>

                <input
                    id="auth-referral-view-email"
                    type="text"
                    disabled
                >

            </div>


            <div class="field">

                <label>
                    Created At
                </label>

                <input
                    id="auth-referral-view-created"
                    type="text"
                    disabled
                >

            </div>


            <div class="field">

                <label>
                    Updated At
                </label>

                <input
                    id="auth-referral-view-updated"
                    type="text"
                    disabled
                >

            </div>


        </div>


        <!-- USED BY -->

        <div class="referral-used-section">

            <h2>
                Used By
            </h2>


            <div
                id="auth-referral-used-users"
                class="table-wrap"
            >

                <div class="empty-row">
                    Loading users...
                </div>

            </div>

        </div>


        <div class="user-popup-footer">

            <button
                type="button"
                class="btn btn-outline"
                onclick="closeReferralViewModal()"
            >
                Close
            </button>


            <button
                type="button"
                class="btn btn-navy"
                id="auth-referral-view-edit-btn"
            >
                Edit Referral
            </button>

        </div>


    </div>

</div>


<!-- ================================================================
     TOAST
================================================================ -->

<div
    id="auth-referral-toast-stack"
></div>


<script>

/*
|--------------------------------------------------------------------------
| Auth Referral Codes JavaScript
|--------------------------------------------------------------------------
|
| Same modal/AJAX pattern as auth_users.php.
|
|--------------------------------------------------------------------------
*/


const authReferralCsrf =
    <?= json_encode($csrfToken) ?>;


const authReferralCreateModal =
    document.getElementById(
        'auth-referral-create-modal'
    );


const authReferralEditModal =
    document.getElementById(
        'auth-referral-edit-modal'
    );


const authReferralViewModal =
    document.getElementById(
        'auth-referral-view-modal'
    );


const authReferralCreateForm =
    document.getElementById(
        'auth-referral-create-form'
    );


const authReferralEditForm =
    document.getElementById(
        'auth-referral-edit-form'
    );


const authReferralCreateBtn =
    document.getElementById(
        'auth-referral-create-btn'
    );


const authReferralSaveBtn =
    document.getElementById(
        'auth-referral-save-btn'
    );


/*
|--------------------------------------------------------------------------
| Toast
|--------------------------------------------------------------------------
*/

function showAuthReferralToast(
    message,
    type = 'success'
) {

    const stack =
        document.getElementById(
            'auth-referral-toast-stack'
        );


    if (!stack) {
        alert(message);
        return;
    }


    const toast =
        document.createElement('div');


    toast.className =
        'toast' +
        (
            type === 'error'
                ? ' error'
                : ''
        );


    toast.textContent =
        message;


    stack.appendChild(
        toast
    );


    setTimeout(
        () => {
            toast.remove();
        },
        3500
    );
}


/*
|--------------------------------------------------------------------------
| BODY SCROLL
|--------------------------------------------------------------------------
*/

function lockReferralBody() {
    document.body.style.overflow =
        'hidden';
}


function unlockReferralBody() {
    document.body.style.overflow =
        '';
}


/*
|--------------------------------------------------------------------------
| CREATE MODAL
|--------------------------------------------------------------------------
*/

function openReferralCreateModal() {

    if (!authReferralCreateModal) {
        return;
    }


    authReferralCreateForm?.reset();


    const usageCap =
        document.getElementById(
            'new-referral-usage-cap'
        );


    if (usageCap) {
        usageCap.value = '1';
    }


    authReferralCreateModal.hidden =
        false;


    lockReferralBody();


    setTimeout(
        () => {

            document
                .getElementById(
                    'new-referral-code'
                )
                ?.focus();

        },
        50
    );
}


function closeReferralCreateModal() {

    if (!authReferralCreateModal) {
        return;
    }


    authReferralCreateModal.hidden =
        true;


    unlockReferralBody();
}


/*
|--------------------------------------------------------------------------
| EDIT MODAL
|--------------------------------------------------------------------------
*/

function openReferralEditModal(
    referral
) {

    if (!authReferralEditModal) {
        return;
    }


    document.getElementById(
        'auth-referral-edit-id'
    ).value =
        referral.id ?? '';


    document.getElementById(
        'auth-referral-edit-code'
    ).value =
        referral.code ?? '';


    document.getElementById(
        'auth-referral-edit-usage-cap'
    ).value =
        referral.usage_cap ?? 0;


    document.getElementById(
        'auth-referral-edit-expiry'
    ).value =
        referral.expiry_date ?? '';


    document.getElementById(
        'auth-referral-edit-referrer'
    ).value =
        referral.referrer ?? '-';


    document.getElementById(
        'auth-referral-edit-created'
    ).value =
        referral.created_at ?? '-';


    document.getElementById(
        'auth-referral-edit-updated'
    ).value =
        referral.updated_at ?? '-';


    authReferralEditModal.hidden =
        false;


    lockReferralBody();


    setTimeout(
        () => {

            document
                .getElementById(
                    'auth-referral-edit-code'
                )
                ?.focus();

        },
        50
    );
}


function closeReferralEditModal() {

    if (!authReferralEditModal) {
        return;
    }


    authReferralEditModal.hidden =
        true;


    unlockReferralBody();
}


/*
|--------------------------------------------------------------------------
| VIEW MODAL
|--------------------------------------------------------------------------
*/

async function openReferralViewModal(
    referral
) {

    if (!authReferralViewModal) {
        return;
    }


    /*
    |--------------------------------------------------------------------------
    | Fill immediately
    |--------------------------------------------------------------------------
    */

    document.getElementById(
        'auth-referral-view-code'
    ).value =
        referral.code ?? '';


    document.getElementById(
        'auth-referral-view-usage-cap'
    ).value =
        referral.usage_cap ?? 0;


    document.getElementById(
        'auth-referral-view-usage-count'
    ).value =
        referral.usage_count ?? 0;


    document.getElementById(
        'auth-referral-view-expiry'
    ).value =
        referral.expiry_date || '-';


    document.getElementById(
        'auth-referral-view-created-by'
    ).value =
        referral.referrer || '-';


    document.getElementById(
        'auth-referral-view-email'
    ).value =
        referral.referrer_email || '-';


    document.getElementById(
        'auth-referral-view-created'
    ).value =
        referral.created_at || '-';


    document.getElementById(
        'auth-referral-view-updated'
    ).value =
        referral.updated_at || '-';


    document.getElementById(
        'auth-referral-view-title'
    ).textContent =
        referral.code || 'Referral';


    /*
    |--------------------------------------------------------------------------
    | Edit button
    |--------------------------------------------------------------------------
    */

    const editBtn =
        document.getElementById(
            'auth-referral-view-edit-btn'
        );


    if (editBtn) {

        editBtn.onclick =
            function () {

                closeReferralViewModal();

                openReferralEditModal(
                    referral
                );
            };
    }


    /*
    |--------------------------------------------------------------------------
    | Open modal
    |--------------------------------------------------------------------------
    */

    authReferralViewModal.hidden =
        false;


    lockReferralBody();


    /*
    |--------------------------------------------------------------------------
    | Loading users
    |--------------------------------------------------------------------------
    */

    const usedUsersContainer =
        document.getElementById(
            'auth-referral-used-users'
        );


    if (usedUsersContainer) {

        usedUsersContainer.innerHTML =
            '<div class="empty-row">Loading users...</div>';
    }


    /*
    |--------------------------------------------------------------------------
    | Fetch view data
    |--------------------------------------------------------------------------
    */

    try {

        const formData =
            new FormData();


        formData.append(
            'action',
            'view'
        );


        formData.append(
            'id',
            referral.id
        );


        formData.append(
            'csrf_token',
            authReferralCsrf
        );


        const response =
            await fetch(
                'pages/auth/auth_referral_codes.php',
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
                'Unable to load referral details.'
            );
        }


        /*
        |--------------------------------------------------------------------------
        | Update usage count
        |--------------------------------------------------------------------------
        */

        document.getElementById(
            'auth-referral-view-usage-count'
        ).value =
            data.referral.usage_count ?? 0;


        /*
        |--------------------------------------------------------------------------
        | Update other details
        |--------------------------------------------------------------------------
        */

        document.getElementById(
            'auth-referral-view-email'
        ).value =
            data.referral.referrer_email || '-';


        document.getElementById(
            'auth-referral-view-created'
        ).value =
            data.referral.created_at || '-';


        document.getElementById(
            'auth-referral-view-updated'
        ).value =
            data.referral.updated_at || '-';


        /*
        |--------------------------------------------------------------------------
        | Used users table
        |--------------------------------------------------------------------------
        */

        renderReferralUsedUsers(
            data.used_users || []
        );


    } catch (error) {

        if (usedUsersContainer) {

            usedUsersContainer.innerHTML =
                '<div class="empty-row">' +
                authReferralEscapeJs(
                    error.message ||
                    'Unable to load users.'
                ) +
                '</div>';
        }
    }
}


/*
|--------------------------------------------------------------------------
| VIEW - USED USERS
|--------------------------------------------------------------------------
*/

function renderReferralUsedUsers(
    users
) {

    const container =
        document.getElementById(
            'auth-referral-used-users'
        );


    if (!container) {
        return;
    }


    if (!users.length) {

        container.innerHTML =
            '<div class="empty-row">' +
            'No users have used this referral code.' +
            '</div>';

        return;
    }


    let html = '';

    html += '<table>';

    html += '<thead>';

    html += '<tr>';

    html += '<th>Display Name</th>';

    html += '<th>Username</th>';

    html += '<th>Email</th>';

    html += '<th>Created At</th>';

    html += '</tr>';

    html += '</thead>';

    html += '<tbody>';


    users.forEach(
        function (user) {

            html += '<tr>';


            html += '<td>';

            html +=
                authReferralEscapeJs(
                    user.display_name ||
                    '-'
                );

            html += '</td>';


            html += '<td>';

            if (user.username) {

                html +=
                    '<a href="index.php?sidebar=auth&page=auth/auth_users&username=' +
                    encodeURIComponent(
                        user.username
                    ) +
                    '">';

                html +=
                    authReferralEscapeJs(
                        user.username
                    );

                html += '</a>';

            } else {

                html += '-';

            }

            html += '</td>';


            html += '<td>';

            html +=
                authReferralEscapeJs(
                    user.email ||
                    '-'
                );

            html += '</td>';


            html += '<td>';

            html +=
                authReferralEscapeJs(
                    user.created_at ||
                    '-'
                );

            html += '</td>';


            html += '</tr>';
        }
    );


    html += '</tbody>';

    html += '</table>';


    container.innerHTML =
        html;
}


/*
|--------------------------------------------------------------------------
| Safe HTML escaping for generated JS HTML
|--------------------------------------------------------------------------
*/

function authReferralEscapeJs(
    value
) {

    return String(
        value ?? ''
    )
        .replace(
            /&/g,
            '&amp;'
        )
        .replace(
            /</g,
            '&lt;'
        )
        .replace(
            />/g,
            '&gt;'
        )
        .replace(
            /"/g,
            '&quot;'
        )
        .replace(
            /'/g,
            '&#039;'
        );
}


/*
|--------------------------------------------------------------------------
| CLOSE VIEW
|--------------------------------------------------------------------------
*/

function closeReferralViewModal() {

    if (!authReferralViewModal) {
        return;
    }


    authReferralViewModal.hidden =
        true;


    unlockReferralBody();
}


/*
|--------------------------------------------------------------------------
| CREATE
|--------------------------------------------------------------------------
*/

authReferralCreateForm?.addEventListener(
    'submit',
    async function (event) {

        event.preventDefault();


        if (!authReferralCreateBtn) {
            return;
        }


        authReferralCreateBtn.disabled =
            true;


        authReferralCreateBtn.textContent =
            'Creating...';


        try {

            const formData =
                new FormData(
                    authReferralCreateForm
                );


            const response =
                await fetch(
                    'pages/auth/auth_referral_codes.php',
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
                    'Unable to create referral.'
                );
            }


            closeReferralCreateModal();


            showAuthReferralToast(
                data.message,
                'success'
            );


            setTimeout(
                () => {
                    window.location.reload();
                },
                500
            );


        } catch (error) {

            showAuthReferralToast(
                error.message ||
                'Unable to create referral.',
                'error'
            );


        } finally {

            authReferralCreateBtn.disabled =
                false;


            authReferralCreateBtn.textContent =
                'Create Referral';
        }
    }
);


/*
|--------------------------------------------------------------------------
| EDIT
|--------------------------------------------------------------------------
*/

authReferralEditForm?.addEventListener(
    'submit',
    async function (event) {

        event.preventDefault();


        if (!authReferralSaveBtn) {
            return;
        }


        authReferralSaveBtn.disabled =
            true;


        authReferralSaveBtn.textContent =
            'Saving...';


        try {

            const formData =
                new FormData(
                    authReferralEditForm
                );


            const response =
                await fetch(
                    'pages/auth/auth_referral_codes.php',
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
                    'Unable to update referral.'
                );
            }


            closeReferralEditModal();


            showAuthReferralToast(
                data.message,
                'success'
            );


            setTimeout(
                () => {
                    window.location.reload();
                },
                500
            );


        } catch (error) {

            showAuthReferralToast(
                error.message ||
                'Unable to update referral.',
                'error'
            );


        } finally {

            authReferralSaveBtn.disabled =
                false;


            authReferralSaveBtn.textContent =
                'Save Changes';
        }
    }
);


/*
|--------------------------------------------------------------------------
| OVERLAY CLICK - CREATE
|--------------------------------------------------------------------------
*/

authReferralCreateModal?.addEventListener(
    'click',
    function (event) {

        if (
            event.target ===
            authReferralCreateModal
        ) {
            closeReferralCreateModal();
        }
    }
);


/*
|--------------------------------------------------------------------------
| OVERLAY CLICK - EDIT
|--------------------------------------------------------------------------
*/

authReferralEditModal?.addEventListener(
    'click',
    function (event) {

        if (
            event.target ===
            authReferralEditModal
        ) {
            closeReferralEditModal();
        }
    }
);


/*
|--------------------------------------------------------------------------
| OVERLAY CLICK - VIEW
|--------------------------------------------------------------------------
*/

authReferralViewModal?.addEventListener(
    'click',
    function (event) {

        if (
            event.target ===
            authReferralViewModal
        ) {
            closeReferralViewModal();
        }
    }
);


/*
|--------------------------------------------------------------------------
| ESC
|--------------------------------------------------------------------------
*/

document.addEventListener(
    'keydown',
    function (event) {

        if (
            event.key !== 'Escape'
        ) {
            return;
        }


        if (
            authReferralCreateModal &&
            !authReferralCreateModal.hidden
        ) {

            closeReferralCreateModal();

            return;
        }


        if (
            authReferralEditModal &&
            !authReferralEditModal.hidden
        ) {

            closeReferralEditModal();

            return;
        }


        if (
            authReferralViewModal &&
            !authReferralViewModal.hidden
        ) {

            closeReferralViewModal();

            return;
        }

    }
);

</script>