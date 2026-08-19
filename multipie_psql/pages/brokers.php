<?php

/*
|--------------------------------------------------------------------------
| BROKERS
|--------------------------------------------------------------------------
| Database:
|   multipie_main_prod.public.brokers
|
| READ ONLY
| Enable buttons/links below are UI-only.
|--------------------------------------------------------------------------
*/

$pdo = getAppDb();

$perPage = 10;

$page = max(
    1,
    (int)($_GET['p'] ?? 1)
);

$identifierFilter = trim(
    (string)($_GET['identifier'] ?? '')
);

$displayNameFilter = trim(
    (string)($_GET['display_name'] ?? '')
);

$brokers = [];

$totalBrokers = 0;

$totalPages = 1;

$offset = 0;

$dbError = null;


/*
|--------------------------------------------------------------------------
| META INFO
|--------------------------------------------------------------------------
*/

function brokerMeta($meta): array
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
| FIND LOGO
|--------------------------------------------------------------------------
*/

function brokerLogo($meta): string
{
    $possibleKeys = [
        'logo',
        'logo_url',
        'logoUrl',
        'image',
        'image_url',
        'imageUrl'
    ];

    foreach ($possibleKeys as $key) {

        if (
            isset($meta[$key]) &&
            is_string($meta[$key]) &&
            trim($meta[$key]) !== ''
        ) {

            return trim($meta[$key]);
        }
    }

    return '';
}


/*
|--------------------------------------------------------------------------
| ENABLE TRANSACTIONS
|--------------------------------------------------------------------------
*/

function brokerTransactionEnabled($meta): ?bool
{
    $possibleKeys = [
        'enable_transactions',
        'enable_transaction',
        'transactions_enabled',
        'transaction_enabled'
    ];

    foreach ($possibleKeys as $key) {

        if (
            array_key_exists(
                $key,
                $meta
            )
        ) {

            return filter_var(
                $meta[$key],
                FILTER_VALIDATE_BOOLEAN,
                FILTER_NULL_ON_FAILURE
            );
        }
    }

    return null;
}


/*
|--------------------------------------------------------------------------
| ENABLE MOBILE TRANSACTIONS
|--------------------------------------------------------------------------
*/

function brokerMobileTransactionEnabled($meta): ?bool
{
    $possibleKeys = [
        'enable_transactions_mobile',
        'enable_transaction_mobile',
        'transactions_mobile_enabled',
        'transaction_mobile_enabled'
    ];

    foreach ($possibleKeys as $key) {

        if (
            array_key_exists(
                $key,
                $meta
            )
        ) {

            return filter_var(
                $meta[$key],
                FILTER_VALIDATE_BOOLEAN,
                FILTER_NULL_ON_FAILURE
            );
        }
    }

    return null;
}


/*
|--------------------------------------------------------------------------
| PAGINATION URL
|--------------------------------------------------------------------------
*/

function brokerPageUrl(
    int $page,
    string $identifier,
    string $displayName
): string {

    $params = [
        'page' => 'brokers',
        'p' => $page
    ];

    if ($identifier !== '') {
        $params['identifier'] =
            $identifier;
    }

    if ($displayName !== '') {
        $params['display_name'] =
            $displayName;
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

    $where = [];

    $params = [];


    /*
    |--------------------------------------------------------------------------
    | IDENTIFIER
    |--------------------------------------------------------------------------
    */

    if ($identifierFilter !== '') {

        $where[] =
            'identifier ILIKE :identifier';

        $params[':identifier'] =
            '%' . $identifierFilter . '%';
    }


    /*
    |--------------------------------------------------------------------------
    | DISPLAY NAME
    |--------------------------------------------------------------------------
    */

    if ($displayNameFilter !== '') {

        $where[] =
            'display_name ILIKE :display_name';

        $params[':display_name'] =
            '%' . $displayNameFilter . '%';
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
        FROM public.brokers
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
            PDO::PARAM_STR
        );
    }


    $countStmt->execute();


    $totalBrokers =
        (int)$countStmt->fetchColumn();


    /*
    |--------------------------------------------------------------------------
    | PAGINATION
    |--------------------------------------------------------------------------
    */

    $totalPages = max(
        1,
        (int)ceil(
            $totalBrokers /
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
            display_name,
            identifier,
            website_link,
            meta_info,
            created_at,
            updated_at
        FROM public.brokers
        {$whereSql}
        ORDER BY
            identifier ASC NULLS LAST,
            id ASC
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


    $brokers =
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

            List of Brokers

            <span class="count-pill-navy">

                <?= number_format(
                    $totalBrokers
                ) ?>

                Total

            </span>

        </h1>


        <p class="sub">
            Manage MultiPie broker configurations.
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
        value="brokers"
    >


    <div class="filter-controls">


        <input
            type="text"
            name="identifier"
            value="<?= e(
                $identifierFilter
            ) ?>"
            placeholder="Identifier contains"
        >


        <input
            type="text"
            name="display_name"
            value="<?= e(
                $displayNameFilter
            ) ?>"
            placeholder="Display Name contains"
        >


        <button
            type="submit"
            class="btn btn-outline btn-sm"
        >
            Filter
        </button>


        <a
            href="index.php?page=brokers"
            class="btn btn-outline btn-sm"
        >
            View All
        </a>

    </div>

</form>


<!-- ============================================================
     RESULT COUNT
============================================================ -->

<div class="filter-count">

    Showing

    <b>

        <?= $totalBrokers > 0
            ? ($offset + 1)
            : 0 ?>

        to

        <?= min(
            $offset + $perPage,
            $totalBrokers
        ) ?>

    </b>

    of

    <span>

        <?= number_format(
            $totalBrokers
        ) ?>

    </span>

    Brokers

</div>


<!-- ============================================================
     TABLE
============================================================ -->

<div class="table-wrap">

    <table>

        <thead>

            <tr>

                <th>
                    Identifier
                </th>

                <th>
                    Display Name
                </th>

                <th>
                    Logo
                </th>

                <th>
                    Website Link
                </th>

                <th>
                    Meta Info
                </th>

                <th>
                    Enable Transactions
                </th>

                <th>
                    Enable Transactions Mobile
                </th>

            </tr>

        </thead>


        <tbody>


        <?php if (
            empty($brokers)
        ): ?>

            <tr class="empty-row">

                <td colspan="7">

                    No brokers found.

                </td>

            </tr>

        <?php endif; ?>


        <?php foreach (
            $brokers
            as $broker
        ): ?>


            <?php

            $meta =
                brokerMeta(
                    $broker[
                        'meta_info'
                    ] ?? null
                );


            $logo =
                brokerLogo(
                    $meta
                );


            $transactionsEnabled =
                brokerTransactionEnabled(
                    $meta
                );


            $mobileEnabled =
                brokerMobileTransactionEnabled(
                    $meta
                );


            /*
            |--------------------------------------------------------------------------
            | Compact Meta Info
            |--------------------------------------------------------------------------
            */

            $metaDisplay =
                json_encode(
                    $meta,
                    JSON_UNESCAPED_SLASHES |
                    JSON_UNESCAPED_UNICODE
                );


            if (
                $metaDisplay === false ||
                $metaDisplay === ''
            ) {

                $metaDisplay = '{}';

            }


            /*
            |--------------------------------------------------------------------------
            | Limit large JSON in table
            |--------------------------------------------------------------------------
            */

            $metaPreview =
                mb_strimwidth(
                    $metaDisplay,
                    0,
                    100,
                    '...'
                );

            ?>


            <tr>


                <!-- IDENTIFIER -->

                <td>

                    <?= e(
                        $broker[
                            'identifier'
                        ] ?? '-'
                    ) ?>

                </td>


                <!-- DISPLAY NAME -->

                <td>

                    <?= e(
                        $broker[
                            'display_name'
                        ] ?? '-'
                    ) ?>

                </td>


                <!-- LOGO -->

                <td>

                    <?php if (
                        $logo !== ''
                    ): ?>

                        <img
                            src="<?= e(
                                $logo
                            ) ?>"
                            alt="<?= e(
                                $broker[
                                    'display_name'
                                ] ?? 'Broker'
                            ) ?>"
                            style="
                                max-width:140px;
                                max-height:80px;
                                object-fit:contain;
                                display:block;
                            "
                            onerror="this.style.display='none';"
                        >

                    <?php else: ?>

                        <span
                            class="text-muted"
                        >
                            -
                        </span>

                    <?php endif; ?>

                </td>


                <!-- WEBSITE -->

                <td>

                    <?php

                    $website =
                        trim(
                            (string)(
                                $broker[
                                    'website_link'
                                ] ?? ''
                            )
                        );

                    ?>

                    <?php if (
                        $website !== ''
                    ): ?>

                        <a
                            href="<?= e(
                                $website
                            ) ?>"
                            target="_blank"
                            rel="noopener noreferrer"
                        >

                            <?= e(
                                $website
                            ) ?>

                        </a>

                    <?php else: ?>

                        -

                    <?php endif; ?>

                </td>


                <!-- META INFO -->

                <td>

                    <span
                        title="<?= e(
                            $metaDisplay
                        ) ?>"
                        style="
                            display:inline-block;
                            max-width:250px;
                            white-space:nowrap;
                            overflow:hidden;
                            text-overflow:ellipsis;
                            vertical-align:middle;
                        "
                    >

                        <?= e(
                            $metaPreview
                        ) ?>

                    </span>

                </td>


                <!-- ENABLE TRANSACTIONS -->

                <td>

                    <?php if (
                        $transactionsEnabled === true
                    ): ?>

                        <span class="broker-enable">
                            Enabled
                        </span>

                    <?php elseif (
                        $transactionsEnabled === false
                    ): ?>

                        <span class="broker-disabled">
                            Disabled
                        </span>

                    <?php else: ?>

                        <button
                            type="button"
                            class="broker-enable-button"
                            onclick="brokerActionDisabled()"
                        >
                            Enable
                        </button>

                    <?php endif; ?>

                </td>


                <!-- ENABLE TRANSACTIONS MOBILE -->

                <td>

                    <?php if (
                        $mobileEnabled === true
                    ): ?>

                        <span class="broker-enable">
                            Enabled
                        </span>

                    <?php elseif (
                        $mobileEnabled === false
                    ): ?>

                        <span class="broker-disabled">
                            Disabled
                        </span>

                    <?php else: ?>

                        <button
                            type="button"
                            class="broker-enable-button"
                            onclick="brokerActionDisabled()"
                        >
                            Enable
                        </button>

                    <?php endif; ?>

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
                    brokerPageUrl(
                        $page - 1,
                        $identifierFilter,
                        $displayNameFilter
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
                    brokerPageUrl(
                        1,
                        $identifierFilter,
                        $displayNameFilter
                    )
                ) ?>"
            >
                1
            </a>


            <?php if (
                $startPage > 2
            ): ?>

                <span class="pagination-dots">
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
                    brokerPageUrl(
                        $i,
                        $identifierFilter,
                        $displayNameFilter
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

                <span class="pagination-dots">
                    ...
                </span>

            <?php endif; ?>


            <a
                class="pagination-btn"
                href="<?= e(
                    brokerPageUrl(
                        $totalPages,
                        $identifierFilter,
                        $displayNameFilter
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
                    brokerPageUrl(
                        $page + 1,
                        $identifierFilter,
                        $displayNameFilter
                    )
                ) ?>"
            >

                Next

            </a>

        <?php endif; ?>


    </div>

<?php endif; ?>


<style>

.broker-enable-button {
    border: 0;
    background: transparent;
    color: #551a8b;
    text-decoration: underline;
    cursor: pointer;
    font-size: 13px;
    font-weight: 600;
    padding: 0;
}

.broker-enable-button:hover {
    color: #003f7d;
}

.broker-enable {
    color: #138a5b;
    font-weight: 600;
}

.broker-disabled {
    color: #64748b;
    font-weight: 600;
}

</style>


<script>

function brokerActionDisabled()
{
    alert(
        'This action is currently disabled. No changes have been made to the corporate database.'
    );
}

</script>