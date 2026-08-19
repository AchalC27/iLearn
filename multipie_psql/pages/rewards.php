<?php

/*
|--------------------------------------------------------------------------
| REWARDS
|--------------------------------------------------------------------------
| Main DB:
|   multipie_main_prod.public.rewards
|
| User DB:
|   multipie_auth_prod.public.users
|
| READ ONLY PAGE
|--------------------------------------------------------------------------
*/


$perPage = 10;

$currentPage = max(
    1,
    (int)($_GET['p'] ?? 1)
);

$usernameFilter = trim(
    (string)($_GET['username'] ?? '')
);

$statusFilter = trim(
    (string)($_GET['status'] ?? '')
);

$rewards = [];

$users = [];

$totalRewards = 0;

$totalPages = 1;

$dbError = null;


/*
|--------------------------------------------------------------------------
| REWARD TYPE
|--------------------------------------------------------------------------
|
| The database stores reward_type as INTEGER.
|
| Keep/change these values if your production enum
| uses different values.
|
*/

$rewardTypeMap = [
    0 => 'march_bonanza',
    1 => 'referral',
    2 => 'cashback',
    3 => 'bonus'
];


/*
|--------------------------------------------------------------------------
| STATUS
|--------------------------------------------------------------------------
*/

$rewardStatusMap = [
    0 => 'pending',
    1 => 'eligible',
    2 => 'claimed',
    3 => 'revoked'
];


function rewardTypeLabel($value): string
{
    global $rewardTypeMap;

    $value = (int)$value;

    return $rewardTypeMap[$value]
        ?? (string)$value;
}


function rewardStatusLabel($value): string
{
    global $rewardStatusMap;

    $value = (int)$value;

    return $rewardStatusMap[$value]
        ?? (string)$value;
}


/*
|--------------------------------------------------------------------------
| PAGINATION URL
|--------------------------------------------------------------------------
*/

function rewardsPageUrl(
    int $page,
    string $username,
    string $status
): string {

    $params = [
        'page' => 'rewards',
        'p' => $page
    ];

    if ($username !== '') {
        $params['username'] = $username;
    }

    if ($status !== '') {
        $params['status'] = $status;
    }

    return 'index.php?' .
        http_build_query($params);
}


/*
|--------------------------------------------------------------------------
| DATABASE
|--------------------------------------------------------------------------
*/

try {

    $pdo = getAppDb();

    /*
    |--------------------------------------------------------------------------
    | FILTER CONDITIONS
    |--------------------------------------------------------------------------
    */

    $where = [];

    $params = [];


    /*
    |--------------------------------------------------------------------------
    | USER USERNAME STARTS WITH
    |--------------------------------------------------------------------------
    |
    | We first obtain matching users from auth DB.
    |
    */

    $matchingUserIds = null;


    if ($usernameFilter !== '') {

        $usersPdo = getUsersDb();


        $userStmt = $usersPdo->prepare(
            "
            SELECT
                id,
                username,
                display_name,
                email,
                mobile,
                confirmed_at,
                meta_info
            FROM public.users
            WHERE LOWER(username) LIKE LOWER(:username)
            ORDER BY id DESC
            "
        );


        $userStmt->execute([
            ':username' =>
                $usernameFilter . '%'
        ]);


        $matchingUsers =
            $userStmt->fetchAll(
                PDO::FETCH_ASSOC
            );


        $matchingUserIds = [];


        foreach (
            $matchingUsers
            as $user
        ) {

            $matchingUserIds[] =
                (int)$user['id'];

            $users[
                (int)$user['id']
            ] = $user;
        }


        /*
        |--------------------------------------------------------------------------
        | No matching users
        |--------------------------------------------------------------------------
        */

        if (
            empty($matchingUserIds)
        ) {

            $matchingUserIds = [-1];

        }

    }


    /*
    |--------------------------------------------------------------------------
    | USER FILTER
    |--------------------------------------------------------------------------
    */

    if (
        $matchingUserIds !== null
    ) {

        $placeholders = [];

        foreach (
            $matchingUserIds
            as $index => $userId
        ) {

            $placeholder =
                ':user_' . $index;

            $placeholders[] =
                $placeholder;

            $params[$placeholder] =
                $userId;
        }


        $where[] =
            'r.user_id IN (' .
            implode(
                ',',
                $placeholders
            ) .
            ')';
    }


    /*
    |--------------------------------------------------------------------------
    | STATUS FILTER
    |--------------------------------------------------------------------------
    */

    if ($statusFilter !== '') {

        $statusValue = null;


        foreach (
            $rewardStatusMap
            as $number => $label
        ) {

            if (
                strtolower($label) ===
                strtolower($statusFilter)
            ) {

                $statusValue =
                    (int)$number;

                break;
            }
        }


        /*
        |--------------------------------------------------------------------------
        | Allow numeric status too
        |--------------------------------------------------------------------------
        */

        if (
            $statusValue === null &&
            is_numeric($statusFilter)
        ) {

            $statusValue =
                (int)$statusFilter;
        }


        if (
            $statusValue !== null
        ) {

            $where[] =
                'r.status = :status';

            $params[':status'] =
                $statusValue;
        }
    }


    /*
    |--------------------------------------------------------------------------
    | WHERE
    |--------------------------------------------------------------------------
    */

    $whereSql = '';

    if (!empty($where)) {

        $whereSql =
            'WHERE ' .
            implode(
                ' AND ',
                $where
            );
    }


    /*
    |--------------------------------------------------------------------------
    | COUNT
    |--------------------------------------------------------------------------
    */

    $countSql = "
        SELECT COUNT(*)
        FROM public.rewards r
        {$whereSql}
    ";


    $countStmt =
        $pdo->prepare(
            $countSql
        );


    foreach (
        $params
        as $key => $value
    ) {

        $countStmt->bindValue(
            $key,
            $value,
            PDO::PARAM_INT
        );
    }


    $countStmt->execute();


    $totalRewards =
        (int)$countStmt->fetchColumn();


    /*
    |--------------------------------------------------------------------------
    | PAGINATION
    |--------------------------------------------------------------------------
    */

    $totalPages = max(
        1,
        (int)ceil(
            $totalRewards /
            $perPage
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
    | FETCH REWARDS
    |--------------------------------------------------------------------------
    */

    $sql = "
        SELECT
            r.id,
            r.amount,
            r.reward_type,
            r.status,
            r.voucher_id,
            r.user_id,
            r.expiry_date,
            r.eligible_on,
            r.claimed_on,
            r.meta_info,
            r.created_at,
            r.updated_at

        FROM public.rewards r

        {$whereSql}

        ORDER BY
            r.id DESC

        LIMIT :limit
        OFFSET :offset
    ";


    $stmt =
        $pdo->prepare(
            $sql
        );


    foreach (
        $params
        as $key => $value
    ) {

        $stmt->bindValue(
            $key,
            $value,
            PDO::PARAM_INT
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


    $rewards =
        $stmt->fetchAll(
            PDO::FETCH_ASSOC
        );


    /*
    |--------------------------------------------------------------------------
    | LOAD USERS WHEN USER FILTER IS NOT USED
    |--------------------------------------------------------------------------
    */

    if (
        $usernameFilter === ''
    ) {

        $userIds = [];


        foreach (
            $rewards
            as $reward
        ) {

            if (
                !empty(
                    $reward['user_id']
                )
            ) {

                $userIds[] =
                    (int)$reward['user_id'];
            }
        }


        $userIds =
            array_values(
                array_unique(
                    $userIds
                )
            );


        if (
            !empty($userIds)
        ) {

            $usersPdo =
                getUsersDb();


            $placeholders = [];

            $userParams = [];


            foreach (
                $userIds
                as $index => $userId
            ) {

                $placeholder =
                    ':uid_' . $index;

                $placeholders[] =
                    $placeholder;

                $userParams[
                    $placeholder
                ] = $userId;
            }


            $usersSql = "
                SELECT
                    id,
                    username,
                    display_name,
                    email,
                    mobile,
                    confirmed_at,
                    meta_info
                FROM public.users
                WHERE id IN (
                    " .
                    implode(
                        ',',
                        $placeholders
                    ) .
                ")
            ";


            $usersStmt =
                $usersPdo->prepare(
                    $usersSql
                );


            foreach (
                $userParams
                as $key => $value
            ) {

                $usersStmt->bindValue(
                    $key,
                    $value,
                    PDO::PARAM_INT
                );
            }


            $usersStmt->execute();


            $userRows =
                $usersStmt->fetchAll(
                    PDO::FETCH_ASSOC
                );


            foreach (
                $userRows
                as $user
            ) {

                $users[
                    (int)$user['id']
                ] = $user;
            }
        }
    }


} catch (
    Throwable $e
) {

    $dbError =
        $e->getMessage();
}

?>


<!-- ============================================================
     HEADER
============================================================ -->

<section class="view-header">

    <div>

        <h1>

            List of all Rewards

            <span class="count-pill-navy">

                <?= number_format(
                    $totalRewards
                ) ?>

                Total

            </span>

        </h1>


        <p class="sub">

            Manage MultiPie reward records,
            referrers, vouchers and reward status.

        </p>

    </div>

</section>


<!-- ============================================================
     ERROR
============================================================ -->

<?php if ($dbError): ?>

    <div class="alert-box">

        <strong>
            PostgreSQL connection failed
        </strong>

        <p>
            <?= e($dbError) ?>
        </p>

    </div>

<?php endif; ?>


<!-- ============================================================
     FILTER
============================================================ -->

<form
    class="filter-bar"
    method="get"
    action="index.php"
>

    <input
        type="hidden"
        name="page"
        value="rewards"
    >


    <div class="filter-controls">


        <!-- USERNAME -->

        <input
            type="text"
            name="username"
            value="<?= e(
                $usernameFilter
            ) ?>"
            placeholder="User Username starts with"
        >


        <!-- STATUS -->

        <select
            name="status"
            class="select-plain"
        >

            <option value="">
                -- All --
            </option>


            <?php foreach (
                $rewardStatusMap
                as $number =>
                    $label
            ): ?>

                <option
                    value="<?= e(
                        $label
                    ) ?>"
                    <?= strtolower(
                        $statusFilter
                    ) === strtolower(
                        $label
                    )
                        ? 'selected'
                        : '' ?>
                >

                    <?= e(
                        ucfirst(
                            $label
                        )
                    ) ?>

                </option>

            <?php endforeach; ?>


        </select>


        <button
            type="submit"
            class="btn btn-outline btn-sm"
        >

            Search

        </button>


        <a
            href="index.php?page=rewards"
            class="btn btn-outline btn-sm"
        >

            View All

        </a>


    </div>

</form>


<!-- ============================================================
     COUNT
============================================================ -->

<div class="filter-count">

    Showing

    <b>

        <?= $totalRewards > 0
            ? ($offset + 1)
            : 0 ?>

        to

        <?= min(
            $offset + $perPage,
            $totalRewards
        ) ?>

    </b>

    of

    <span>

        <?= number_format(
            $totalRewards
        ) ?>

    </span>

    Rewards

</div>


<!-- ============================================================
     TABLE
============================================================ -->

<div class="table-wrap">

    <table>

        <thead>

            <tr>

                <th>
                    Type
                </th>

                <th>
                    Referrer
                </th>

                <th>
                    Referred
                </th>

                <th>
                    User Ids
                </th>

                <th>
                    View Referrer
                </th>

                <th>
                    Voucher
                </th>

                <th>
                    Eligible On
                </th>

                <th>
                    Status
                </th>

                <th>
                    Actions
                </th>

            </tr>

        </thead>


        <tbody>


        <?php if (
            empty($rewards)
        ): ?>

            <tr class="empty-row">

                <td colspan="9">

                    No rewards found.

                </td>

            </tr>

        <?php endif; ?>


        <?php foreach (
            $rewards
            as $reward
        ): ?>


            <?php

            $userId =
                (int)(
                    $reward[
                        'user_id'
                    ] ?? 0
                );


            $user =
                $users[
                    $userId
                ] ?? null;


            $username =
                $user['username']
                ?? (
                    $user['display_name']
                    ?? (
                        $userId
                            ? 'User #' .
                                $userId
                            : '-'
                    )
                );


            /*
            |--------------------------------------------------------------------------
            | META INFO
            |--------------------------------------------------------------------------
            */

            $meta = [];

            if (
                !empty(
                    $reward['meta_info']
                )
            ) {

                $decoded =
                    json_decode(
                        $reward[
                            'meta_info'
                        ],
                        true
                    );


                if (
                    is_array(
                        $decoded
                    )
                ) {

                    $meta = $decoded;

                }

            }


            /*
            |--------------------------------------------------------------------------
            | REFERRED USER IDS
            |--------------------------------------------------------------------------
            */

            $referredUserIds =
                $meta[
                    'referred_user_ids'
                ]
                ?? (
                    $meta[
                        'referred_users'
                    ]
                    ?? (
                        $meta[
                            'referred_user_id'
                        ]
                        ?? '-'
                    )
                );


            if (
                is_array(
                    $referredUserIds
                )
            ) {

                $referredUserIds =
                    implode(
                        ', ',
                        $referredUserIds
                    );

            }


            /*
            |--------------------------------------------------------------------------
            | TYPE
            |--------------------------------------------------------------------------
            */

            $rewardType =
                rewardTypeLabel(
                    $reward[
                        'reward_type'
                    ] ?? ''
                );


            /*
            |--------------------------------------------------------------------------
            | STATUS
            |--------------------------------------------------------------------------
            */

            $rewardStatus =
                rewardStatusLabel(
                    $reward[
                        'status'
                    ] ?? ''
                );


            /*
            |--------------------------------------------------------------------------
            | ELIGIBLE DATE
            |--------------------------------------------------------------------------
            */

            $eligibleOn =
                $reward[
                    'eligible_on'
                ] ?? '';


            if (
                $eligibleOn !== ''
            ) {

                $timestamp =
                    strtotime(
                        $eligibleOn
                    );


                if (
                    $timestamp
                ) {

                    $eligibleOn =
                        date(
                            'd M Y H:i',
                            $timestamp
                        );
                }
            }

            ?>


            <tr>


                <!-- TYPE -->

                <td>

                    <?= e(
                        $rewardType
                    ) ?>

                </td>


                <!-- REFERRER -->

                <td>

                    <?php if ($user): ?>

                        <span class="reward-referrer-name">
                            <?= e($username) ?>
                        </span>

                        <span class="reward-user-id">
                            - <?= e($userId) ?>
                        </span>

                    <?php else: ?>

                        <?= $userId
                            ? 'User - ' . $userId
                            : '-' ?>

                    <?php endif; ?>

                </td>


                <!-- REFERRED -->

                <td>

                    <?= e(
                        $referredUserIds
                    ) ?>

                </td>


                <!-- USER IDS -->

                <td>

                    <?= e(
                        $userId
                    ) ?>

                </td>


                <!-- VIEW REFERRER -->

                <td>

                    <?php if ($user): ?>

                        <button
                            type="button"
                            class="table-link-button"
                            onclick='openRewardUserModal(
                                <?= json_encode(
                                    $user,
                                    JSON_HEX_TAG |
                                    JSON_HEX_APOS |
                                    JSON_HEX_AMP |
                                    JSON_HEX_QUOT
                                ) ?>
                            )'
                        >
                            View
                        </button>

                    <?php else: ?>

                        <span class="text-muted">-</span>

                    <?php endif; ?>

                </td>


                <!-- VOUCHER -->

                <td>

                    <?php if (
                        !empty(
                            $reward[
                                'voucher_id'
                            ]
                        )
                    ): ?>

                        <?= e(
                            $reward[
                                'voucher_id'
                            ]
                        ) ?>

                    <?php else: ?>

                        -

                    <?php endif; ?>

                </td>


                <!-- ELIGIBLE -->

                <td>

                    <?= e(
                        $eligibleOn
                    ) ?>

                </td>


                <!-- STATUS -->

                <td>

                    <span
                        class="status-badge"
                    >

                        <span
                            class="dot-status"
                        ></span>

                        <?= e(
                            ucfirst(
                                $rewardStatus
                            )
                        ) ?>

                    </span>

                </td>


                <!-- ACTIONS -->

                <td>

                    <span
                        style="
                        color:var(--slate-500);
                        font-size:12px;
                        "
                    >

                        Revoke action to come

                    </span>

                </td>


            </tr>


        <?php endforeach; ?>


        </tbody>

    </table>

</div>


<!-- ============================================================
     PAGINATION
============================================================ -->

<?php if (
    $totalPages > 1
): ?>

    <div class="pagination">


        <?php if (
            $currentPage > 1
        ): ?>

            <a
                class="pagination-btn"
                href="<?= e(
                    rewardsPageUrl(
                        $currentPage - 1,
                        $usernameFilter,
                        $statusFilter
                    )
                ) ?>"
            >

                Previous

            </a>

        <?php endif; ?>


        <?php

        $startPage =
            max(
                1,
                $currentPage - 2
            );


        $endPage =
            min(
                $totalPages,
                $currentPage + 2
            );

        ?>


        <?php for (
            $i = $startPage;
            $i <= $endPage;
            $i++
        ): ?>

            <a
                class="pagination-btn
                <?= $i === $currentPage
                    ? 'active'
                    : '' ?>"
                href="<?= e(
                    rewardsPageUrl(
                        $i,
                        $usernameFilter,
                        $statusFilter
                    )
                ) ?>"
            >

                <?= $i ?>

            </a>

        <?php endfor; ?>


        <?php if (
            $currentPage <
            $totalPages
        ): ?>

            <a
                class="pagination-btn"
                href="<?= e(
                    rewardsPageUrl(
                        $currentPage + 1,
                        $usernameFilter,
                        $statusFilter
                    )
                ) ?>"
            >

                Next

            </a>

        <?php endif; ?>


    </div>

<?php endif; ?>


<!-- ============================================================
     VIEWING USER MODAL
============================================================ -->

<div
    id="rewardUserModal"
    class="modal-overlay"
    style="display:none;"
>

    <div
        class="modal-box"
        style="max-width:1050px;"
    >


        <!-- HEADER -->

        <div class="modal-header">

            <div>

                <h2>
                    Viewing User
                </h2>

                <p>
                    User information associated with this reward.
                </p>

            </div>


            <button
                type="button"
                class="modal-close"
                onclick="closeRewardUserModal()"
            >

                ×

            </button>

        </div>


        <!-- BODY -->

        <div class="modal-body">


            <div class="form-grid">


                <!-- USERNAME -->

                <div class="form-group">

                    <label>
                        User
                    </label>

                    <input
                        id="rewardViewUsername"
                        type="text"
                        readonly
                    >

                </div>


                <!-- EMAIL -->

                <div class="form-group">

                    <label>
                        Email
                    </label>

                    <input
                        id="rewardViewEmail"
                        type="text"
                        readonly
                    >

                </div>


                <!-- MOBILE -->

                <div class="form-group">

                    <label>
                        Mobile
                    </label>

                    <input
                        id="rewardViewMobile"
                        type="text"
                        readonly
                    >

                </div>


                <!-- DISPLAY NAME -->

                <div class="form-group">

                    <label>
                        Display Name
                    </label>

                    <input
                        id="rewardViewDisplayName"
                        type="text"
                        readonly
                    >

                </div>


                <!-- VERIFICATION -->

                <div class="form-group">

                    <label>
                        Verification Status
                    </label>

                    <input
                        id="rewardViewVerification"
                        type="text"
                        readonly
                    >

                </div>


                <!-- USER ID -->

                <div class="form-group">

                    <label>
                        User ID
                    </label>

                    <input
                        id="rewardViewId"
                        type="text"
                        readonly
                    >

                </div>


            </div>


            <!-- =================================================
                 CONNECTED ACCOUNTS
            ================================================== -->

            <div
                class="detail-section"
                style="margin-top:30px;"
            >

                <h3>
                    Connected Accounts
                </h3>

                <div
                    id="rewardConnectedAccounts"
                    class="detail-value"
                >

                    - none -

                </div>

            </div>


            <!-- =================================================
                 DEMOGRAPHICS
            ================================================== -->

            <div
                class="detail-section"
                style="margin-top:25px;"
            >

                <h3>
                    Demographics
                </h3>


                <div class="detail-grid">


                    <div>

                        <strong>
                            Age group
                        </strong>

                        <span
                            id="rewardAgeGroup"
                        >
                            -
                        </span>

                    </div>


                    <div>

                        <strong>
                            Preferred Instruments
                        </strong>

                        <span
                            id="rewardPreferredInstruments"
                        >
                            -
                        </span>

                    </div>


                    <div>

                        <strong>
                            Investing Experience
                        </strong>

                        <span
                            id="rewardInvestingExperience"
                        >
                            -
                        </span>

                    </div>


                    <div>

                        <strong>
                            City
                        </strong>

                        <span
                            id="rewardCity"
                        >
                            -
                        </span>

                    </div>


                </div>

            </div>


            <!-- =================================================
                 REPORTS
            ================================================== -->

            <div
                class="detail-section"
                style="margin-top:30px;"
            >

                <h3>
                    Reports
                </h3>

                <p
                    class="sub"
                >

                    Reports associated with this user are
                    displayed here when available.

                </p>

            </div>


            <!-- =================================================
                 REWARDS
            ================================================== -->

            <div
                class="detail-section"
                style="margin-top:30px;"
            >

                <h3>
                    Rewards
                </h3>

                <p
                    class="sub"
                >

                    Reward records associated with this user.

                </p>

            </div>


        </div>


        <!-- FOOTER -->

        <div class="modal-footer">

            <button
                type="button"
                class="btn btn-outline"
                onclick="closeRewardUserModal()"
            >

                Close

            </button>

        </div>


    </div>

</div>


<style>
.reward-referrer-name {
    color: #1e293b;
    font-weight: 500;
}

.reward-user-id {
    color: #64748b;
    margin-left: 3px;
}

.table-link-button {
    border: 0;
    background: transparent;
    color: #063b75;
    cursor: pointer;
    font-size: 13px;
    font-weight: 600;
    padding: 0;
}

.table-link-button:hover {
    text-decoration: underline;
}

.text-muted {
    color: var(--slate-500);
}
</style>

<script>

/*
|--------------------------------------------------------------------------
| OPEN USER MODAL
|--------------------------------------------------------------------------
*/

function openRewardUserModal(
    user
) {

    const modal =
        document.getElementById(
            'rewardUserModal'
        );


    document.getElementById(
        'rewardViewUsername'
    ).value =
        user.username || '';


    document.getElementById(
        'rewardViewEmail'
    ).value =
        user.email || '';


    document.getElementById(
        'rewardViewMobile'
    ).value =
        user.mobile || '';


    document.getElementById(
        'rewardViewDisplayName'
    ).value =
        user.display_name || '';


    document.getElementById(
        'rewardViewId'
    ).value =
        user.id || '';


    /*
    |--------------------------------------------------------------------------
    | VERIFICATION STATUS
    |--------------------------------------------------------------------------
    */

    document.getElementById(
        'rewardViewVerification'
    ).value =
        user.confirmed_at
            ? 'Verified'
            : 'Not Verified';


    /*
    |--------------------------------------------------------------------------
    | META INFO
    |--------------------------------------------------------------------------
    */

    let meta = {};

    if (
        user.meta_info
    ) {

        try {

            meta =
                typeof user.meta_info ===
                'string'
                    ? JSON.parse(
                        user.meta_info
                    )
                    : user.meta_info;

        } catch (
            error
        ) {

            meta = {};

        }

    }


    /*
    |--------------------------------------------------------------------------
    | CONNECTED ACCOUNTS
    |--------------------------------------------------------------------------
    */

    const connectedAccounts =
        meta.connected_accounts
        ?? meta.connectedAccounts
        ?? '- none -';


    document.getElementById(
        'rewardConnectedAccounts'
    ).textContent =
        Array.isArray(
            connectedAccounts
        )
            ? connectedAccounts.join(
                ', '
            )
            : connectedAccounts;


    /*
    |--------------------------------------------------------------------------
    | DEMOGRAPHICS
    |--------------------------------------------------------------------------
    */

    document.getElementById(
        'rewardAgeGroup'
    ).textContent =
        meta.age_group
        ?? meta.ageGroup
        ?? '-';


    document.getElementById(
        'rewardPreferredInstruments'
    ).textContent =
        Array.isArray(
            meta.preferred_instruments
            ?? meta.preferredInstruments
        )
            ? (
                meta.preferred_instruments
                ?? meta.preferredInstruments
            ).join(', ')
            : (
                meta.preferred_instruments
                ?? meta.preferredInstruments
                ?? '-'
            );


    document.getElementById(
        'rewardInvestingExperience'
    ).textContent =
        meta.investing_experience
        ?? meta.investingExperience
        ?? '-';


    document.getElementById(
        'rewardCity'
    ).textContent =
        meta.city
        ?? '-';


    modal.style.display =
        'flex';
}


/*
|--------------------------------------------------------------------------
| CLOSE
|--------------------------------------------------------------------------
*/

function closeRewardUserModal()
{

    document.getElementById(
        'rewardUserModal'
    ).style.display =
        'none';

}


/*
|--------------------------------------------------------------------------
| CLICK OUTSIDE MODAL
|--------------------------------------------------------------------------
*/

document.addEventListener(
    'click',
    function(event)
    {

        const modal =
            document.getElementById(
                'rewardUserModal'
            );


        if (
            event.target === modal
        ) {

            closeRewardUserModal();

        }

    }
);

</script>