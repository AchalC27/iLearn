<?php

/*
|--------------------------------------------------------------------------
| CAMS FEEDBACKS
|--------------------------------------------------------------------------
|
| Application DB:
|     multipie_main_prod
|     public.cams_feedbacks
|
| Users DB:
|     multipie_auth_prod
|     public.users
|
| cams_feedbacks.user_id
|        ↓
| users.id
|
| DISPLAY COLUMNS:
|     Display Name
|     Mobile Number
|     Username
|     Reason
|     Custom Reason
|
| FILTERS:
|     User Username starts with
|     User Mobile starts with
|     ID equals
|     Reason equals
|
| IMPORTANT:
| This page is READ-ONLY.
| No INSERT / UPDATE / DELETE is performed.
|--------------------------------------------------------------------------
*/


/*
|--------------------------------------------------------------------------
| Pagination
|--------------------------------------------------------------------------
*/

$perPage = 25;

$currentPage = max(
    1,
    (int)($_GET['p'] ?? 1)
);


/*
|--------------------------------------------------------------------------
| Filters
|--------------------------------------------------------------------------
*/

$usernameStartsWith = trim(
    (string)($_GET['username_starts_with'] ?? '')
);

$mobileStartsWith = trim(
    (string)($_GET['mobile_starts_with'] ?? '')
);

$idEquals = trim(
    (string)($_GET['id_equals'] ?? '')
);

$reasonEquals = trim(
    (string)($_GET['reason_equals'] ?? '')
);


/*
|--------------------------------------------------------------------------
| Validate ID
|--------------------------------------------------------------------------
*/

if (
    $idEquals !== '' &&
    !ctype_digit($idEquals)
) {
    $idEquals = '';
}


/*
|--------------------------------------------------------------------------
| Validate Reason
|--------------------------------------------------------------------------
|
| reason is INTEGER in PostgreSQL.
|--------------------------------------------------------------------------
*/

if (
    $reasonEquals !== '' &&
    !preg_match('/^-?\d+$/', $reasonEquals)
) {
    $reasonEquals = '';
}


/*
|--------------------------------------------------------------------------
| Database variables
|--------------------------------------------------------------------------
*/

$dbError = null;

$feedbacks = [];

$totalFeedbacks = 0;

$totalPages = 1;


/*
|--------------------------------------------------------------------------
| User ID filter
|--------------------------------------------------------------------------
|
| Username and mobile belong to:
|
| multipie_auth_prod.public.users
|
| We first find matching user IDs.
|
|--------------------------------------------------------------------------
*/

$matchingUserIds = null;


/*
|--------------------------------------------------------------------------
| Database processing
|--------------------------------------------------------------------------
*/

try {

    /*
    |--------------------------------------------------------------------------
    | Get both PostgreSQL connections
    |--------------------------------------------------------------------------
    */

    $appPdo = getAppDb();


    /*
    |--------------------------------------------------------------------------
    | USER FILTERS
    |--------------------------------------------------------------------------
    |
    | If username/mobile filters are used, find matching user IDs
    | from the authentication database.
    |--------------------------------------------------------------------------
    */

    if (
        $usernameStartsWith !== '' ||
        $mobileStartsWith !== ''
    ) {

        $userWhere = [];

        $userParams = [];


        /*
        |--------------------------------------------------------------------------
        | Username starts with
        |--------------------------------------------------------------------------
        */

        if ($usernameStartsWith !== '') {

            $userWhere[] =
                "COALESCE(username, '') ILIKE :username_prefix";

            $userParams[':username_prefix'] =
                $usernameStartsWith . '%';
        }


        /*
        |--------------------------------------------------------------------------
        | Mobile starts with
        |--------------------------------------------------------------------------
        */

        if ($mobileStartsWith !== '') {

            $userWhere[] =
                "COALESCE(mobile, '') ILIKE :mobile_prefix";

            $userParams[':mobile_prefix'] =
                $mobileStartsWith . '%';
        }


        /*
        |--------------------------------------------------------------------------
        | Find matching users
        |--------------------------------------------------------------------------
        */

        $userSql = "
            SELECT id
            FROM public.users
            WHERE " .
            implode(' AND ', $userWhere);


        $userStmt =
            $AppPdo->prepare($userSql);


        foreach (
            $userParams
            as $key => $value
        ) {

            $userStmt->bindValue(
                $key,
                $value,
                PDO::PARAM_STR
            );
        }


        $userStmt->execute();


        $matchingUserIds =
            $userStmt->fetchAll(
                PDO::FETCH_COLUMN
            );


        /*
        |--------------------------------------------------------------------------
        | No matching users
        |--------------------------------------------------------------------------
        |
        | If username/mobile filter finds nobody,
        | there can be no feedback records.
        |--------------------------------------------------------------------------
        */

        if (!$matchingUserIds) {

            $matchingUserIds = [];

        }

    }


    /*
    |--------------------------------------------------------------------------
    | Build feedback WHERE
    |--------------------------------------------------------------------------
    */

    $where = [];

    $params = [];


    /*
    |--------------------------------------------------------------------------
    | ID equals
    |--------------------------------------------------------------------------
    */

    if ($idEquals !== '') {

        $where[] =
            "id = :feedback_id";

        $params[':feedback_id'] =
            (int)$idEquals;
    }


    /*
    |--------------------------------------------------------------------------
    | Reason equals
    |--------------------------------------------------------------------------
    */

    if ($reasonEquals !== '') {

        $where[] =
            "reason = :reason";

        $params[':reason'] =
            (int)$reasonEquals;
    }


    /*
    |--------------------------------------------------------------------------
    | Username / Mobile matching users
    |--------------------------------------------------------------------------
    */

    if ($matchingUserIds !== null) {

        /*
        |--------------------------------------------------------------------------
        | No matching users
        |--------------------------------------------------------------------------
        */

        if (!$matchingUserIds) {

            /*
            | Force query to return zero records.
            */

            $where[] =
                "1 = 0";

        } else {

            /*
            |--------------------------------------------------------------------------
            | Build safe integer placeholders.
            |--------------------------------------------------------------------------
            */

            $placeholders = [];


            foreach (
                $matchingUserIds
                as $index => $userId
            ) {

                $placeholder =
                    ':user_id_' . $index;

                $placeholders[] =
                    $placeholder;

                $params[$placeholder] =
                    (int)$userId;
            }


            $where[] =
                "user_id IN (" .
                implode(
                    ', ',
                    $placeholders
                ) .
                ")";
        }

    }


    /*
    |--------------------------------------------------------------------------
    | WHERE SQL
    |--------------------------------------------------------------------------
    */

    $whereSql =
        $where
            ? 'WHERE ' . implode(
                ' AND ',
                $where
            )
            : '';


    /*
    |--------------------------------------------------------------------------
    | COUNT
    |--------------------------------------------------------------------------
    */

    $countSql = "
        SELECT COUNT(*)
        FROM public.cams_feedbacks
        {$whereSql}
    ";


    $countStmt =
        $appPdo->prepare(
            $countSql
        );


    foreach (
        $params
        as $key => $value
    ) {

        $countStmt->bindValue(
            $key,
            $value,
            is_int($value)
                ? PDO::PARAM_INT
                : PDO::PARAM_STR
        );
    }


    $countStmt->execute();


    $totalFeedbacks =
        (int)$countStmt->fetchColumn();


    /*
    |--------------------------------------------------------------------------
    | Pagination
    |--------------------------------------------------------------------------
    */

    $totalPages = max(
        1,
        (int)ceil(
            $totalFeedbacks / $perPage
        )
    );


    if (
        $currentPage >
        $totalPages
    ) {

        $currentPage =
            $totalPages;
    }


    $offset =
        ($currentPage - 1) *
        $perPage;


    /*
    |--------------------------------------------------------------------------
    | Fetch feedback records
    |--------------------------------------------------------------------------
    */

    $sql = "
        SELECT
            id,
            reason,
            custom_reason,
            user_id,
            created_at,
            updated_at
        FROM public.cams_feedbacks
        {$whereSql}
        ORDER BY id DESC
        LIMIT :limit
        OFFSET :offset
    ";


    $stmt =
        $appPdo->prepare(
            $sql
        );


    foreach (
        $params
        as $key => $value
    ) {

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


    $feedbackRows =
        $stmt->fetchAll(
            PDO::FETCH_ASSOC
        );


    /*
    |--------------------------------------------------------------------------
    | Get user IDs from current page
    |--------------------------------------------------------------------------
    */

    $pageUserIds = [];


    foreach (
        $feedbackRows
        as $row
    ) {

        if (
            isset($row['user_id']) &&
            $row['user_id'] !== null
        ) {

            $pageUserIds[] =
                (int)$row['user_id'];
        }
    }


    $pageUserIds =
        array_values(
            array_unique(
                $pageUserIds
            )
        );


    /*
    |--------------------------------------------------------------------------
    | Fetch users for current page
    |--------------------------------------------------------------------------
    */

    $usersById = [];


    if ($pageUserIds) {

        $userPlaceholders = [];

        $userQueryParams = [];


        foreach (
            $pageUserIds
            as $index => $userId
        ) {

            $placeholder =
                ':uid_' . $index;

            $userPlaceholders[] =
                $placeholder;

            $userQueryParams[$placeholder] =
                $userId;
        }


        $userDetailsSql = "
            SELECT
                id,
                username,
                mobile,
                display_name
            FROM public.users
            WHERE id IN (
                " .
                implode(
                    ', ',
                    $userPlaceholders
                ) .
                "
            )
        ";


        $userDetailsStmt =
            $appPdo->prepare(
                $userDetailsSql
            );


        foreach (
            $userQueryParams
            as $key => $value
        ) {

            $userDetailsStmt->bindValue(
                $key,
                $value,
                PDO::PARAM_INT
            );
        }


        $userDetailsStmt->execute();


        $userRows =
            $userDetailsStmt->fetchAll(
                PDO::FETCH_ASSOC
            );


        /*
        |--------------------------------------------------------------------------
        | Index users by ID
        |--------------------------------------------------------------------------
        */

        foreach (
            $userRows
            as $user
        ) {

            $usersById[
                (int)$user['id']
            ] = $user;
        }

    }


    /*
    |--------------------------------------------------------------------------
    | Combine feedback + user data
    |--------------------------------------------------------------------------
    */

    foreach (
        $feedbackRows
        as $row
    ) {

        $userId =
            (int)$row['user_id'];


        $user =
            $usersById[$userId]
            ?? [];


        $feedbacks[] = [

            'id' =>
                $row['id'],

            'display_name' =>
                $user['display_name']
                ?? '-',

            'mobile' =>
                $user['mobile']
                ?? '-',

            'username' =>
                $user['username']
                ?? '-',

            'reason' =>
                $row['reason'],

            'custom_reason' =>
                $row['custom_reason']
                ?? '',

            'created_at' =>
                $row['created_at'],

            'updated_at' =>
                $row['updated_at'],

            'user_id' =>
                $row['user_id'],
        ];
    }


} catch (Throwable $e) {

    $dbError =
        $e->getMessage();

    $feedbacks = [];

    $totalFeedbacks = 0;

    $totalPages = 1;

}


/*
|--------------------------------------------------------------------------
| Helper
|--------------------------------------------------------------------------
*/

function camsFeedbackFormatDate(
    $value
): string {

    if (
        empty($value)
    ) {

        return '-';
    }


    try {

        return
            (new DateTime(
                (string)$value
            ))->format(
                'd M Y'
            );

    } catch (
        Throwable $e
    ) {

        return (string)$value;
    }
}


/*
|--------------------------------------------------------------------------
| Preserve filter parameters
|--------------------------------------------------------------------------
*/

function camsFeedbackQuery(
    array $overrides = []
): string {

    $query = [

        'page' =>
            'cams_feedbacks',

        'username_starts_with' =>
            (string)(
                $_GET[
                    'username_starts_with'
                ] ?? ''
            ),

        'mobile_starts_with' =>
            (string)(
                $_GET[
                    'mobile_starts_with'
                ] ?? ''
            ),

        'id_equals' =>
            (string)(
                $_GET[
                    'id_equals'
                ] ?? ''
            ),

        'reason_equals' =>
            (string)(
                $_GET[
                    'reason_equals'
                ] ?? ''
            ),

        'p' =>
            (int)(
                $_GET['p'] ?? 1
            ),
    ];


    foreach (
        $overrides
        as $key => $value
    ) {

        $query[$key] =
            $value;
    }


    return http_build_query(
        array_filter(
            $query,
            static function ($value) {

                return
                    $value !== '';
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

            CAMS Feedbacks

            <span class="count-pill-navy">

                <?= number_format(
                    $totalFeedbacks
                ) ?>

                Total

            </span>

        </h1>


        <p class="sub">

            Manage and review CAMS user feedback records.

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
                    <?= e($dbError) ?>
                </p>

            </div>

        </div>

    </div>

<?php endif; ?>



<!-- ================================================================
     FILTER BAR
================================================================ -->

<form
    class="filter-bar cams-feedback-filter-bar"
    method="get"
    action="index.php"
>

    <input
        type="hidden"
        name="page"
        value="cams_feedbacks"
    >


    <div class="filter-controls cams-feedback-filters">


        <!-- ========================================================
             USER USERNAME STARTS WITH
        ========================================================= -->

        <div class="filter-field">

            <label>
                User Username starts with
            </label>


            <input
                class="filter-input"
                type="text"
                name="username_starts_with"
                value="<?= e(
                    $usernameStartsWith
                ) ?>"
                placeholder="Username starts with..."
            >

        </div>



        <!-- ========================================================
             USER MOBILE STARTS WITH
        ========================================================= -->

        <div class="filter-field">

            <label>
                User Mobile starts with
            </label>


            <input
                class="filter-input"
                type="text"
                name="mobile_starts_with"
                value="<?= e(
                    $mobileStartsWith
                ) ?>"
                placeholder="Mobile starts with..."
            >

        </div>



        <!-- ========================================================
             ID EQUALS
        ========================================================= -->

        <div class="filter-field">

            <label>
                ID equals
            </label>


            <input
                class="filter-input"
                type="number"
                name="id_equals"
                value="<?= e(
                    $idEquals
                ) ?>"
                placeholder="Feedback ID"
                min="1"
            >

        </div>



        <!-- ========================================================
             REASON EQUALS
        ========================================================= -->

        <div class="filter-field">

            <label>
                Reason equals
            </label>


            <input
                class="filter-input"
                type="number"
                name="reason_equals"
                value="<?= e(
                    $reasonEquals
                ) ?>"
                placeholder="Reason"
            >

        </div>



        <!-- ========================================================
             FILTER BUTTON
        ========================================================= -->

        <button
            class="btn btn-outline btn-sm cams-filter-button"
            type="submit"
        >

            Filter

        </button>



        <!-- ========================================================
             RESET
        ========================================================= -->

        <a
            class="btn btn-outline btn-sm cams-filter-button"
            href="index.php?page=cams_feedbacks"
        >

            Reset

        </a>


    </div>

</form>



<!-- ================================================================
     FILTER COUNT
================================================================ -->

<div class="filter-count">

    Showing

    <b>

        <?= $totalFeedbacks > 0
            ? (
                ($currentPage - 1)
                * $perPage
                + 1
            )
            : 0
        ?>

        -

        <?= min(
            $currentPage * $perPage,
            $totalFeedbacks
        ) ?>

    </b>

    of

    <span>
        <?= number_format(
            $totalFeedbacks
        ) ?>
    </span>

    Feedbacks

</div>



<!-- ================================================================
     TABLE
================================================================ -->

<div class="table-wrap">

    <table>

        <thead>

            <tr>

                <th>
                    Display Name
                </th>

                <th>
                    Mobile Number
                </th>

                <th>
                    Username
                </th>

                <th>
                    Reason
                </th>

                <th>
                    Custom Reason
                </th>

            </tr>

        </thead>


        <tbody>


        <?php if (!$feedbacks): ?>

            <tr class="empty-row">

                <td colspan="5">

                    <?= $dbError
                        ? 'Unable to load CAMS feedbacks from PostgreSQL.'
                        : 'No feedbacks found matching the selected filters.'
                    ?>

                </td>

            </tr>

        <?php endif; ?>



        <?php foreach (
            $feedbacks
            as $feedback
        ): ?>

            <tr>


                <!-- DISPLAY NAME -->

                <td>

                    <?= e(
                        $feedback[
                            'display_name'
                        ] ?? '-'
                    ) ?>

                </td>



                <!-- MOBILE -->

                <td>

                    <?= e(
                        $feedback[
                            'mobile'
                        ] ?? '-'
                    ) ?>

                </td>



                <!-- USERNAME -->

                <td>

                    <?= e(
                        $feedback[
                            'username'
                        ] ?? '-'
                    ) ?>

                </td>



                <!-- REASON -->

                <td>

                    <?= e(
                        $feedback[
                            'reason'
                        ] ?? '-'
                    ) ?>

                </td>



                <!-- CUSTOM REASON -->

                <td>

                    <?php

                    $customReason =
                        trim(
                            (string)(
                                $feedback[
                                    'custom_reason'
                                ] ?? ''
                            )
                        );

                    ?>


                    <?php if (
                        $customReason !== ''
                    ): ?>

                        <?= e(
                            $customReason
                        ) ?>

                    <?php else: ?>

                        <span
                            style="
                                color:
                                var(--slate-400);
                            "
                        >
                            -
                        </span>

                    <?php endif; ?>

                </td>


            </tr>

        <?php endforeach; ?>


        </tbody>

    </table>

</div>



<!-- ================================================================
     PAGINATION
================================================================ -->

<?php if (
    ($totalPages ?? 1) > 1
): ?>

    <div class="pagination">


        <?php if (
            $currentPage > 1
        ): ?>

            <a
                class="btn btn-outline btn-sm"
                href="index.php?<?= e(
                    camsFeedbackQuery([
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
                href="index.php?<?= e(
                    camsFeedbackQuery([
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
     PAGE-SPECIFIC CSS
================================================================ -->

<style>

/*
|--------------------------------------------------------------------------
| CAMS Feedback filter layout
|--------------------------------------------------------------------------
*/

.cams-feedback-filters {

    display: flex;

    align-items: flex-end;

    gap: 12px;

    flex-wrap: wrap;

}


/*
|--------------------------------------------------------------------------
| Individual filter
|--------------------------------------------------------------------------
*/

.filter-field {

    display: flex;

    flex-direction: column;

    gap: 6px;

}


.filter-field label {

    font-size: 12px;

    font-weight: 700;

    color:
        var(--navy-800);

    white-space: nowrap;

}


/*
|--------------------------------------------------------------------------
| Filter input
|--------------------------------------------------------------------------
*/

.filter-input {

    height: 40px;

    min-width: 180px;

    padding:
        0 12px;

    border:
        1px solid
        #cbd5e1;

    border-radius:
        6px;

    background:
        #ffffff;

    color:
        var(--slate-700);

    font-family:
        inherit;

    font-size:
        13px;

    outline:
        none;

    box-sizing:
        border-box;

}


.filter-input:focus {

    border-color:
        #0b477f;

    box-shadow:
        0 0 0 2px
        rgba(
            11,
            71,
            127,
            0.08
        );

}


/*
|--------------------------------------------------------------------------
| Filter / reset buttons
|--------------------------------------------------------------------------
*/

.cams-filter-button {

    height: 40px;

    white-space: nowrap;

}


/*
|--------------------------------------------------------------------------
| Responsive
|--------------------------------------------------------------------------
*/

@media (
    max-width: 1100px
) {

    .filter-field {

        flex: 1 1 200px;

    }


    .filter-input {

        width: 100%;

        min-width: 0;

    }

}


@media (
    max-width: 700px
) {

    .cams-feedback-filters {

        display:
            grid;

        grid-template-columns:
            1fr;

    }


    .filter-field {

        width: 100%;

    }


    .cams-filter-button {

        width: 100%;

    }

}

</style>