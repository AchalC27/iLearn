<?php

/*
|--------------------------------------------------------------------------
| Subscribers
|--------------------------------------------------------------------------
| Database:
|   multipie_main_prod.public.subscribers
|
| READ ONLY
| No INSERT / UPDATE / DELETE operations are performed.
|--------------------------------------------------------------------------
*/

$pdo = getAppDb();

$perPage = 10;

$page = max(
    1,
    (int)($_GET['p'] ?? 1)
);

$emailFilter = trim(
    (string)($_GET['email'] ?? '')
);

$channelFilter = trim(
    (string)($_GET['channel'] ?? '')
);

$subscribers = [];

$totalSubscribers = 0;

$totalPages = 1;

$offset = 0;

$dbError = null;


/*
|--------------------------------------------------------------------------
| CHANNEL LABELS
|--------------------------------------------------------------------------
|
| The database stores channel as INTEGER.
| Actual enum mapping was not provided, so numeric values are
| displayed when a mapping is not known.
|
| Change these values later if you confirm the production mapping.
|--------------------------------------------------------------------------
*/

$channelMap = [
    0 => 'Email',
    1 => 'SMS',
    2 => 'Push',
    3 => 'Web'
];


/*
|--------------------------------------------------------------------------
| STATUS LABELS
|--------------------------------------------------------------------------
|
| Existing project convention:
| 0 = Inactive
| 1 = Active
|
|--------------------------------------------------------------------------
*/

$statusMap = [
    0 => 'Inactive',
    1 => 'Active'
];


/*
|--------------------------------------------------------------------------
| Helpers
|--------------------------------------------------------------------------
*/

function subscriberChannelLabel($channel): string
{
    global $channelMap;

    if ($channel === null || $channel === '') {
        return '-';
    }

    $channel = (int)$channel;

    return $channelMap[$channel]
        ?? (string)$channel;
}


function subscriberStatusLabel($status): string
{
    global $statusMap;

    if ($status === null || $status === '') {
        return '-';
    }

    $status = (int)$status;

    return $statusMap[$status]
        ?? (string)$status;
}


/*
|--------------------------------------------------------------------------
| META INFO
|--------------------------------------------------------------------------
*/

function subscriberMeta($meta): array
{
    if (
        $meta === null ||
        $meta === ''
    ) {
        return [];
    }

    if (is_array($meta)) {
        return $meta;
    }

    $decoded = json_decode(
        (string)$meta,
        true
    );

    return is_array($decoded)
        ? $decoded
        : [];
}


/*
|--------------------------------------------------------------------------
| Pagination URL
|--------------------------------------------------------------------------
*/

function subscriberPageUrl(
    int $page,
    string $email,
    string $channel
): string {

    $params = [
        'page' => 'subscribers',
        'p' => $page
    ];

    if ($email !== '') {
        $params['email'] = $email;
    }

    if ($channel !== '') {
        $params['channel'] = $channel;
    }

    return 'index.php?' .
        http_build_query($params);
}


/*
|--------------------------------------------------------------------------
| DATABASE QUERY
|--------------------------------------------------------------------------
*/

try {

    $where = [];

    $params = [];


    /*
    |--------------------------------------------------------------------------
    | EMAIL STARTS WITH
    |--------------------------------------------------------------------------
    */

    if ($emailFilter !== '') {

        $where[] =
            'email ILIKE :email';

        $params[':email'] =
            $emailFilter . '%';
    }


    /*
    |--------------------------------------------------------------------------
    | CHANNEL
    |--------------------------------------------------------------------------
    */

    if (
        $channelFilter !== '' &&
        strtolower($channelFilter) !== 'all'
    ) {

        /*
         * If numeric channel is supplied,
         * filter directly.
         */
        if (is_numeric($channelFilter)) {

            $where[] =
                'channel = :channel';

            $params[':channel'] =
                (int)$channelFilter;

        } else {

            /*
             * Try to find the numeric value from
             * the known display mapping.
             */
            $channelValue = null;

            foreach (
                $channelMap as $value => $label
            ) {

                if (
                    strtolower($label) ===
                    strtolower($channelFilter)
                ) {

                    $channelValue =
                        $value;

                    break;
                }
            }

            if ($channelValue !== null) {

                $where[] =
                    'channel = :channel';

                $params[':channel'] =
                    $channelValue;
            }
        }
    }


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
        FROM public.subscribers
        {$whereSql}
    ";

    $countStmt =
        $pdo->prepare(
            $countSql
        );


    foreach (
        $params as $key => $value
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


    $totalSubscribers =
        (int)$countStmt->fetchColumn();


    /*
    |--------------------------------------------------------------------------
    | PAGINATION
    |--------------------------------------------------------------------------
    */

    $totalPages = max(
        1,
        (int)ceil(
            $totalSubscribers /
            $perPage
        )
    );


    if ($page > $totalPages) {
        $page = $totalPages;
    }


    $offset =
        ($page - 1) *
        $perPage;


    /*
    |--------------------------------------------------------------------------
    | FETCH
    |--------------------------------------------------------------------------
    */

    $sql = "
        SELECT
            id,
            email,
            status,
            channel,
            meta_info,
            created_at,
            updated_at
        FROM public.subscribers
        {$whereSql}
        ORDER BY
            email ASC NULLS LAST,
            id ASC
        LIMIT :limit
        OFFSET :offset
    ";


    $stmt =
        $pdo->prepare($sql);


    foreach (
        $params as $key => $value
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


    $subscribers =
        $stmt->fetchAll(
            PDO::FETCH_ASSOC
        );


} catch (
    Throwable $e
) {

    $dbError =
        $e->getMessage();
}

?>


<!-- ============================================================
     PAGE HEADER
============================================================ -->

<section class="view-header">

    <div>

        <h1>

            List of Subscribers

            <span class="count-pill-navy">

                <?= number_format(
                    $totalSubscribers
                ) ?>

                Total

            </span>

        </h1>


        <p class="sub">

            Manage MultiPie subscribers.

        </p>

    </div>

</section>


<!-- ============================================================
     DATABASE ERROR
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
        value="subscribers"
    >


    <div class="filter-controls">


        <!-- EMAIL STARTS WITH -->

        <input
            type="text"
            name="email"
            value="<?= e(
                $emailFilter
            ) ?>"
            placeholder="Email starts with"
        >


        <!-- CHANNEL -->

        <select
            name="channel"
            class="select-plain"
        >

            <option value="">

                -Any Channel-

            </option>


            <?php foreach (
                $channelMap
                as $channelValue =>
                    $channelLabel
            ): ?>

                <option
                    value="<?= e(
                        $channelValue
                    ) ?>"
                    <?= $channelFilter ===
                        (string)$channelValue
                        ? 'selected'
                        : '' ?>
                >

                    <?= e(
                        $channelLabel
                    ) ?>

                </option>

            <?php endforeach; ?>


        </select>


        <!-- SEARCH -->

        <button
            type="submit"
            class="btn btn-outline btn-sm"
        >

            Search

        </button>


        <!-- VIEW ALL -->

        <a
            href="index.php?page=subscribers"
            class="btn btn-outline btn-sm"
        >

            View All

        </a>


        <!-- EXPORT -->

        <button
            type="button"
            class="btn btn-outline btn-sm"
            onclick="subscriberExportDisabled()"
        >

            Export CSV

        </button>


    </div>

</form>


<!-- ============================================================
     INVITE SECTION
============================================================ -->

<div
    class="card"
    style="
        margin-top:16px;
        padding:16px;
    "
>

    <h3
        style="
            margin:0 0 14px;
            color:#063b75;
        "
    >
        Invite Selected
    </h3>


    <div
        style="
            display:flex;
            align-items:center;
            gap:10px;
            flex-wrap:wrap;
        "
    >

        <input
            type="file"
            id="subscriberInviteFile"
            accept=".csv"
        >


        <button
            type="button"
            class="btn btn-outline"
            onclick="subscriberUploadDisabled()"
        >
            Upload
        </button>

    </div>

</div>


<!-- ============================================================
     RESULT COUNT
============================================================ -->

<div class="filter-count">

    Showing

    <b>

        <?= $totalSubscribers > 0
            ? ($offset + 1)
            : 0 ?>

        to

        <?= min(
            $offset + $perPage,
            $totalSubscribers
        ) ?>

    </b>

    of

    <span>

        <?= number_format(
            $totalSubscribers
        ) ?>

    </span>

    Subscribers

</div>


<!-- ============================================================
     TABLE
============================================================ -->

<div class="table-wrap">

    <table>

        <thead>

            <tr>

                <th>
                    Email
                </th>

                <th>
                    Name
                </th>

                <th>
                    Subscribed At
                </th>

                <th>
                    Channel
                </th>

                <th>
                    Status
                </th>

                <th>
                    Invite Code
                </th>

            </tr>

        </thead>


        <tbody>

        <?php if (
            empty($subscribers)
        ): ?>

            <tr class="empty-row">

                <td colspan="6">

                    No subscribers found.

                </td>

            </tr>

        <?php endif; ?>


        <?php foreach (
            $subscribers
            as $subscriber
        ): ?>

            <?php

            $meta =
                subscriberMeta(
                    $subscriber[
                        'meta_info'
                    ] ?? null
                );


            /*
            |--------------------------------------------------------------------------
            | Name
            |--------------------------------------------------------------------------
            */

            $name =
                $meta['name']
                ?? $meta['display_name']
                ?? $meta['full_name']
                ?? '-';


            /*
            |--------------------------------------------------------------------------
            | Subscribed At
            |--------------------------------------------------------------------------
            */

            $subscribedAt =
                $meta['subscribed_at']
                ?? $meta['subscribedAt']
                ?? $subscriber[
                    'created_at'
                ]
                ?? '-';


            /*
            |--------------------------------------------------------------------------
            | Invite Code
            |--------------------------------------------------------------------------
            */

            $inviteCode =
                $meta['invite_code']
                ?? $meta['inviteCode']
                ?? $meta['invitation_code']
                ?? '-';


            $channel =
                subscriberChannelLabel(
                    $subscriber[
                        'channel'
                    ] ?? null
                );


            $status =
                subscriberStatusLabel(
                    $subscriber[
                        'status'
                    ] ?? null
                );

            ?>


            <tr>


                <!-- EMAIL -->

                <td>

                    <?= e(
                        $subscriber[
                            'email'
                        ] ?? '-'
                    ) ?>

                </td>


                <!-- NAME -->

                <td>

                    <?= e(
                        $name
                    ) ?>

                </td>


                <!-- SUBSCRIBED AT -->

                <td
                    style="
                        color:var(--slate-500);
                        font-size:11px;
                    "
                >

                    <?= e(
                        $subscribedAt
                    ) ?>

                </td>


                <!-- CHANNEL -->

                <td>

                    <?= e(
                        $channel
                    ) ?>

                </td>


                <!-- STATUS -->

                <td>

                    <span
                        class="
                            status-badge
                            <?= strtolower(
                                $status
                            ) === 'active'
                                ? 'Active'
                                : 'Inactive' ?>
                        "
                    >

                        <span
                            class="
                                dot-status
                                <?= strtolower(
                                    $status
                                ) === 'active'
                                    ? 'Active'
                                    : 'Inactive' ?>
                            "
                        ></span>

                        <?= e(
                            $status
                        ) ?>

                    </span>

                </td>


                <!-- INVITE CODE -->

                <td>

                    <?= e(
                        $inviteCode
                    ) ?>

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
            $page > 1
        ): ?>

            <a
                class="pagination-btn"
                href="<?= e(
                    subscriberPageUrl(
                        $page - 1,
                        $emailFilter,
                        $channelFilter
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
                $page - 2
            );

        $endPage =
            min(
                $totalPages,
                $page + 2
            );

        ?>


        <?php if (
            $startPage > 1
        ): ?>

            <a
                class="pagination-btn"
                href="<?= e(
                    subscriberPageUrl(
                        1,
                        $emailFilter,
                        $channelFilter
                    )
                ) ?>"
            >
                1
            </a>


            <?php if (
                $startPage > 2
            ): ?>

                <span
                    class="pagination-dots"
                >
                    ...
                </span>

            <?php endif; ?>

        <?php endif; ?>


        <?php for (
            $i = $startPage;
            $i <= $endPage;
            $i++
        ): ?>

            <a
                class="
                    pagination-btn
                    <?= $i === $page
                        ? 'active'
                        : '' ?>
                "
                href="<?= e(
                    subscriberPageUrl(
                        $i,
                        $emailFilter,
                        $channelFilter
                    )
                ) ?>"
            >

                <?= $i ?>

            </a>

        <?php endfor; ?>


        <?php if (
            $endPage < $totalPages
        ): ?>

            <?php if (
                $endPage <
                $totalPages - 1
            ): ?>

                <span
                    class="pagination-dots"
                >
                    ...
                </span>

            <?php endif; ?>


            <a
                class="pagination-btn"
                href="<?= e(
                    subscriberPageUrl(
                        $totalPages,
                        $emailFilter,
                        $channelFilter
                    )
                ) ?>"
            >

                <?= $totalPages ?>

            </a>

        <?php endif; ?>


        <?php if (
            $page < $totalPages
        ): ?>

            <a
                class="pagination-btn"
                href="<?= e(
                    subscriberPageUrl(
                        $page + 1,
                        $emailFilter,
                        $channelFilter
                    )
                ) ?>"
            >

                Next

            </a>

        <?php endif; ?>


    </div>

<?php endif; ?>


<script>

/*
|--------------------------------------------------------------------------
| EXPORT
|--------------------------------------------------------------------------
|
| UI only for now.
|--------------------------------------------------------------------------
*/

function subscriberExportDisabled()
{
    alert(
        'Export CSV is currently disabled.'
    );
}


/*
|--------------------------------------------------------------------------
| UPLOAD
|--------------------------------------------------------------------------
|
| UI only for now.
|--------------------------------------------------------------------------
*/

function subscriberUploadDisabled()
{
    alert(
        'Subscriber upload is currently disabled. No database changes have been made.'
    );
}

</script>