<?php

/*
|--------------------------------------------------------------------------
| MultiPie - AMC Directory
|--------------------------------------------------------------------------
|
| Database:
|   multipie_main_prod
|
| Table:
|   public.amcs
|
| Read-only for now.
|
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
| Search
|--------------------------------------------------------------------------
|
| Search across:
|   name
|   short_name
|   identifier
|   bos_code
|   ext_id
|
|--------------------------------------------------------------------------
*/

$q = trim(
    (string)($_GET['q'] ?? '')
);


/*
|--------------------------------------------------------------------------
| Status filter
|--------------------------------------------------------------------------
*/

$status = trim(
    (string)($_GET['status'] ?? 'all')
);


/*
|--------------------------------------------------------------------------
| Allowed status values
|--------------------------------------------------------------------------
|
| We don't know the business meaning of every integer yet,
| so keep the actual database value visible.
|
|--------------------------------------------------------------------------
*/

if ($status !== 'all' && !is_numeric($status)) {
    $status = 'all';
}


/*
|--------------------------------------------------------------------------
| Build WHERE clause
|--------------------------------------------------------------------------
*/

$where = [];

$params = [];


if ($q !== '') {

    $where[] = "(
        COALESCE(name, '') ILIKE :search
        OR COALESCE(short_name, '') ILIKE :search
        OR COALESCE(identifier, '') ILIKE :search
        OR COALESCE(bos_code, '') ILIKE :search
        OR COALESCE(ext_id, '') ILIKE :search
    )";

    $params[':search'] = '%' . $q . '%';
}


if ($status !== 'all') {

    $where[] = "status = :status";

    $params[':status'] = (int)$status;
}


$whereSql = $where
    ? 'WHERE ' . implode(' AND ', $where)
    : '';


/*
|--------------------------------------------------------------------------
| Database
|--------------------------------------------------------------------------
*/

$dbError = null;

$totalAmcs = 0;

$amcs = [];

$totalPages = 1;


try {

    /*
     * AMCs belong to the application database.
     */

    $pdo = getAppDb();


    /*
    |--------------------------------------------------------------------------
    | Total records
    |--------------------------------------------------------------------------
    */

    $countSql = "
        SELECT COUNT(*)
        FROM public.amcs
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


    $totalAmcs = (int)$countStmt->fetchColumn();


    /*
    |--------------------------------------------------------------------------
    | Pagination
    |--------------------------------------------------------------------------
    */

    $totalPages = max(
        1,
        (int)ceil($totalAmcs / $perPage)
    );


    if ($currentPage > $totalPages) {
        $currentPage = $totalPages;
    }


    $offset = ($currentPage - 1) * $perPage;


    /*
    |--------------------------------------------------------------------------
    | Fetch AMC records
    |--------------------------------------------------------------------------
    */

    $sql = "
        SELECT
            id,
            name,
            short_name,
            identifier,
            bos_code,
            ext_id,
            status,
            created_at,
            updated_at
        FROM public.amcs
        {$whereSql}
        ORDER BY name ASC NULLS LAST, id ASC
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


    $amcs = $stmt->fetchAll();


} catch (Throwable $e) {

    $dbError = $e->getMessage();

    $totalAmcs = 0;

    $totalPages = 1;

    $amcs = [];

}


/*
|--------------------------------------------------------------------------
| Helpers
|--------------------------------------------------------------------------
*/

function multipieAmcDate($value): string
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


function multipieAmcQuery(array $overrides = []): string
{
    $query = [

        'page' => 'amcs',

        'q' => (string)($_GET['q'] ?? ''),

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


<!-- ================================================================
     AMC HEADER
     ================================================================ -->

<section class="view-header">

    <div>

        <h1>

            AMC Directory

            <span class="count-pill-navy">
                <?= number_format($totalAmcs) ?> Total
            </span>

        </h1>


        <p class="sub">

            Manage Asset Management Companies registered in MultiPie.

        </p>

    </div>


    <!--

        No Add button for now.

        We are only displaying the corporate database.

    -->

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
    class="filter-bar"
    method="get"
    action="index.php"
>

    <input
        type="hidden"
        name="page"
        value="amcs"
    >


    <div class="filter-controls">


        <!-- Search -->

        <div class="search-box">

            <svg class="icon">

                <use href="#i-search"/>

            </svg>


            <input
                name="q"
                type="text"
                value="<?= e($q) ?>"
                placeholder="Search by name, short name, identifier, BOS code, Ext ID..."
            >

        </div>


        <!-- Status -->

        <select
            class="select-plain"
            name="status"
        >

            <option
                value="all"
                <?= $status === 'all' ? 'selected' : '' ?>
            >
                All Status
            </option>


            <?php

            /*
             * Display actual integer statuses found in the table.
             *
             * This avoids guessing what 0 / 1 / other values mean.
             */

            try {

                $statusStmt = getAppDb()->query("
                    SELECT DISTINCT status
                    FROM public.amcs
                    WHERE status IS NOT NULL
                    ORDER BY status
                ");

                $statusValues = $statusStmt->fetchAll(PDO::FETCH_COLUMN);

            } catch (Throwable $e) {

                $statusValues = [];

            }

            ?>


            <option
                value="all"
                <?= $status === 'all' ? 'selected' : '' ?>
            >
                All Status
            </option>

            <option
                value="0"
                <?= $status === '0' ? 'selected' : '' ?>
            >
                Active
            </option>

            <option
                value="1"
                <?= $status === '1' ? 'selected' : '' ?>
            >
                Inactive
            </option>

        </select>


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
            href="index.php?page=amcs"
        >

            Reset

        </a>


    </div>

</form>


<!-- ================================================================
     RECORD COUNT
     ================================================================ -->

<div class="filter-count">

    Showing

    <b>
        <?= $totalAmcs
            ? (($currentPage - 1) * $perPage + 1)
            : 0
        ?>
        -
        <?= min(
            $currentPage * $perPage,
            $totalAmcs
        ) ?>
    </b>

    of

    <span>
        <?= number_format($totalAmcs) ?>
    </span>

    AMCs

</div>


<!-- ================================================================
     AMC TABLE
     ================================================================ -->

<div class="table-wrap">

    <table>

        <thead>

            <tr>

                <th>Name</th>

                <th>Short Name</th>

                <th>Identifier</th>

                <th>BOS Code</th>

                <th>Ext ID</th>

                <th>Status</th>

            </tr>

        </thead>


        <tbody>


        <?php if (!$amcs): ?>

            <tr class="empty-row">

                <td colspan="6">

                    <?= $dbError
                        ? 'Unable to load AMCs from PostgreSQL.'
                        : 'No AMCs found matching the selected filters.'
                    ?>

                </td>

            </tr>

        <?php endif; ?>


        <?php foreach ($amcs as $amc): ?>

            <tr>


                <!-- Name -->

                <td>

                    <strong>

                        <?= e(
                            $amc['name'] ?? '-'
                        ) ?>

                    </strong>

                </td>


                <!-- Short Name -->

                <td>

                    <?= e(
                        $amc['short_name'] ?? '-'
                    ) ?>

                </td>


                <!-- Identifier -->

                <td>

                    <?= e(
                        $amc['identifier'] ?? '-'
                    ) ?>

                </td>


                <!-- BOS Code -->

                <td>

                    <?= e(
                        $amc['bos_code'] ?? '-'
                    ) ?>

                </td>


                <!-- Ext ID -->

                <td>

                    <?= e(
                        $amc['ext_id'] ?? '-'
                    ) ?>

                </td>


                <!-- Status -->

                

                    <td >

                        <?php
                        $amcStatus = (int)($amc['status'] ?? 1);
                        $isActive = ($amcStatus === 0);
                        ?>

                        <span  class="status-badge <?= $isActive ? 'Active' : 'Inactive' ?>">

                            <span
                                class="dot-status <?= $isActive ? 'Active' : 'Inactive' ?>"
                            ></span>

                            <?= $isActive ? 'Active' : 'Inactive' ?>

                        </span>

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
                href="index.php?<?= e(
                    multipieAmcQuery([
                        'p' => $currentPage - 1
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


        <?php if ($currentPage < $totalPages): ?>

            <a
                class="btn btn-outline btn-sm"
                href="index.php?<?= e(
                    multipieAmcQuery([
                        'p' => $currentPage + 1
                    ])
                ) ?>"
            >

                Next

            </a>

        <?php endif; ?>


    </div>

<?php endif; ?>