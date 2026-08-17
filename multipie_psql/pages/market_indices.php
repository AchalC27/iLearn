<?php

$perPage = 20;
$pageNumber = max(1, (int)($_GET['p'] ?? 1));

$identifierFilter = trim((string)($_GET['identifier'] ?? ''));
$displayNameFilter = trim((string)($_GET['display_name'] ?? ''));

$marketIndices = [];
$totalMarketIndices = 0;
$totalPages = 1;
$dbError = null;

try {

    $pdo = getAppDb();

    $where = [];
    $params = [];

    /*
    |--------------------------------------------------------------------------
    | Identifier Filter
    |--------------------------------------------------------------------------
    */

    if ($identifierFilter !== '') {

        $where[] = 'index_code ILIKE :identifier';

        $params[':identifier'] =
            '%' . $identifierFilter . '%';
    }


    /*
    |--------------------------------------------------------------------------
    | Display Name Filter
    |--------------------------------------------------------------------------
    */

    if ($displayNameFilter !== '') {

        $where[] = 'display_name ILIKE :display_name';

        $params[':display_name'] =
            '%' . $displayNameFilter . '%';
    }


    $whereSql = '';

    if (!empty($where)) {

        $whereSql =
            'WHERE ' . implode(' AND ', $where);
    }


    /*
    |--------------------------------------------------------------------------
    | Count
    |--------------------------------------------------------------------------
    */

    $countSql = "
        SELECT COUNT(*)
        FROM public.market_indices
        {$whereSql}
    ";

    $countStmt = $pdo->prepare($countSql);

    foreach ($params as $key => $value) {

        $countStmt->bindValue(
            $key,
            $value,
            PDO::PARAM_STR
        );
    }

    $countStmt->execute();

    $totalMarketIndices =
        (int)$countStmt->fetchColumn();


    /*
    |--------------------------------------------------------------------------
    | Pagination
    |--------------------------------------------------------------------------
    */

    $totalPages = max(
        1,
        (int)ceil(
            $totalMarketIndices / $perPage
        )
    );

    if ($pageNumber > $totalPages) {
        $pageNumber = $totalPages;
    }

    $offset =
        ($pageNumber - 1) * $perPage;


    /*
    |--------------------------------------------------------------------------
    | Fetch Market Indices
    |--------------------------------------------------------------------------
    */

    $sql = "
        SELECT
            id,
            display_name,
            index_code,
            created_at,
            updated_at
        FROM public.market_indices
        {$whereSql}
        ORDER BY display_name ASC NULLS LAST, id ASC
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

    $marketIndices =
        $stmt->fetchAll(PDO::FETCH_ASSOC);

} catch (Throwable $e) {

    $dbError = $e->getMessage();
}

?>


<!-- ============================================================
     PAGE HEADER
============================================================ -->

<section class="view-header">

    <div>

        <h1>

            Market Indices

            <span class="count-pill-navy">
                <?= number_format($totalMarketIndices) ?> Total
            </span>

        </h1>

        <p class="sub">
            Manage MultiPie market indices.
        </p>

    </div>


    <button
        type="button"
        class="btn btn-orange"
        onclick="openMarketIndexModal()"
    >
        + Add New Market Index
    </button>

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
        value="market_indices"
    >


    <div class="filter-controls">

        <input
            type="text"
            name="identifier"
            placeholder="Identifier contains"
            value="<?= e($identifierFilter) ?>"
        >


        <input
            type="text"
            name="display_name"
            placeholder="Display Name contains"
            value="<?= e($displayNameFilter) ?>"
        >


        <button
            type="submit"
            class="btn btn-outline btn-sm"
        >
            Filter
        </button>


        <a
            href="index.php?page=market_indices"
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
        <?= $totalMarketIndices > 0
            ? (($pageNumber - 1) * $perPage + 1)
            : 0 ?>
    </b>

    to

    <b>
        <?= min(
            $pageNumber * $perPage,
            $totalMarketIndices
        ) ?>
    </b>

    of

    <span>
        <?= number_format($totalMarketIndices) ?>
    </span>

    Market Indices

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
                    Value
                </th>

            </tr>

        </thead>


        <tbody>

        <?php if (empty($marketIndices)): ?>

            <tr class="empty-row">

                <td colspan="3">
                    No Market Indices found.
                </td>

            </tr>

        <?php endif; ?>


        <?php foreach ($marketIndices as $index): ?>

            <tr>

                <!-- IDENTIFIER -->

                <td>
                    <?= e(
                        $index['index_code'] ?? '-'
                    ) ?>
                </td>


                <!-- DISPLAY NAME -->

                <td>
                    <?= e(
                        $index['display_name'] ?? '-'
                    ) ?>
                </td>


                <!-- VALUE -->

                <td>
                    -
                </td>

            </tr>

        <?php endforeach; ?>

        </tbody>

    </table>

</div>


<!-- ============================================================
     PAGINATION
============================================================ -->

<?php if ($totalPages > 1): ?>

    <div class="pagination">

        <?php if ($pageNumber > 1): ?>

            <a
                href="index.php?page=market_indices&identifier=<?= urlencode($identifierFilter) ?>&display_name=<?= urlencode($displayNameFilter) ?>&p=<?= $pageNumber - 1 ?>"
                class="pagination-btn"
            >
                Previous
            </a>

        <?php endif; ?>


        <?php for (
            $i = 1;
            $i <= $totalPages;
            $i++
        ): ?>

            <a
                href="index.php?page=market_indices&identifier=<?= urlencode($identifierFilter) ?>&display_name=<?= urlencode($displayNameFilter) ?>&p=<?= $i ?>"
                class="pagination-btn <?= $i === $pageNumber ? 'active' : '' ?>"
            >
                <?= $i ?>
            </a>

        <?php endfor; ?>


        <?php if ($pageNumber < $totalPages): ?>

            <a
                href="index.php?page=market_indices&identifier=<?= urlencode($identifierFilter) ?>&display_name=<?= urlencode($displayNameFilter) ?>&p=<?= $pageNumber + 1 ?>"
                class="pagination-btn"
            >
                Next
            </a>

        <?php endif; ?>

    </div>

<?php endif; ?>


<!-- ============================================================
     ADD / EDIT MODAL
============================================================ -->

<div
    id="market-index-modal"
    class="user-popup-overlay"
    hidden
>

    <div
        class="user-popup"
        role="dialog"
        aria-modal="true"
    >

        <div class="user-popup-header">

            <div>

                <h2 id="market-index-modal-title">
                    Add New Market Index
                </h2>

                <p>
                    Enter market index information for display only.
                </p>

            </div>


            <button
                type="button"
                class="user-popup-close"
                onclick="closeMarketIndexModal()"
            >
                &times;
            </button>

        </div>


        <div class="user-popup-grid">


            <!-- ID -->

            <div class="field">

                <label>
                    ID
                </label>

                <input
                    id="market-index-id"
                    type="text"
                    value="Auto generated"
                    disabled
                >

            </div>


            <!-- IDENTIFIER -->

            <div class="field">

                <label>
                    Identifier
                </label>

                <input
                    id="market-index-code"
                    type="text"
                    placeholder="Enter identifier"
                >

            </div>


            <!-- DISPLAY NAME -->

            <div class="field">

                <label>
                    Display Name
                </label>

                <input
                    id="market-index-display-name"
                    type="text"
                    placeholder="Enter display name"
                >

            </div>


            <!-- CREATED -->

            <div class="field">

                <label>
                    Created
                </label>

                <input
                    id="market-index-created"
                    type="text"
                    value="Not created yet"
                    disabled
                >

            </div>


            <!-- UPDATED -->

            <div class="field">

                <label>
                    Updated
                </label>

                <input
                    id="market-index-updated"
                    type="text"
                    value="Not updated yet"
                    disabled
                >

            </div>


        </div>


        <div class="user-popup-footer">

            <button
                type="button"
                class="btn btn-outline"
                onclick="closeMarketIndexModal()"
            >
                Close
            </button>


            <button
                type="button"
                class="btn btn-orange"
                onclick="marketIndexSaveDisabled()"
            >
                Save Market Index
            </button>

        </div>

    </div>

</div>


<script>

/*
|--------------------------------------------------------------------------
| ADD
|--------------------------------------------------------------------------
*/

function openMarketIndexModal()
{
    document.getElementById(
        'market-index-modal-title'
    ).textContent =
        'Add New Market Index';


    document.getElementById(
        'market-index-id'
    ).value =
        'Auto generated';


    document.getElementById(
        'market-index-code'
    ).value =
        '';


    document.getElementById(
        'market-index-display-name'
    ).value =
        '';


    document.getElementById(
        'market-index-created'
    ).value =
        'Not created yet';


    document.getElementById(
        'market-index-updated'
    ).value =
        'Not updated yet';


    document.getElementById(
        'market-index-modal'
    ).hidden =
        false;


    document.body.style.overflow =
        'hidden';
}


/*
|--------------------------------------------------------------------------
| CLOSE ADD / EDIT
|--------------------------------------------------------------------------
*/

function closeMarketIndexModal()
{
    document.getElementById(
        'market-index-modal'
    ).hidden =
        true;


    document.body.style.overflow =
        '';
}


/*
|--------------------------------------------------------------------------
| SAVE
|--------------------------------------------------------------------------
|
| Does NOT modify PostgreSQL.
|
*/

function marketIndexSaveDisabled()
{
    alert(
        'Save is currently disabled. No changes have been made to the database.'
    );
}


/*
|--------------------------------------------------------------------------
| ESCAPE KEY
|--------------------------------------------------------------------------
*/

document.addEventListener(
    'keydown',
    function(event)
    {

        if (event.key === 'Escape') {

            closeMarketIndexModal();

        }

    }
);


/*
|--------------------------------------------------------------------------
| CLICK OUTSIDE ADD / EDIT
|--------------------------------------------------------------------------
*/

document
    .getElementById(
        'market-index-modal'
    )
    ?.addEventListener(
        'click',
        function(event)
        {

            if (event.target === this) {

                closeMarketIndexModal();

            }

        }
    );

</script>