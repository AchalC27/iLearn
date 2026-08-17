<?php

/*
|--------------------------------------------------------------------------
| Instrument Categories
|--------------------------------------------------------------------------
| Database:
| multipie_main_prod
|
| Table:
| public.instrument_categories
|
| READ ONLY
| View / Edit / Delete UI is provided, but no database mutation is performed.
|--------------------------------------------------------------------------
*/

$perPage = 10;

$currentPage = max(
    1,
    (int)($_GET['p'] ?? 1)
);

$name = trim(
    (string)($_GET['name'] ?? '')
);

$categories = [];

$totalCategories = 0;

$totalPages = 1;

$dbError = null;


try {

    $pdo = getAppDb();


    /*
    |--------------------------------------------------------------------------
    | FILTER
    |--------------------------------------------------------------------------
    */

    $where = [];

    $params = [];


    if ($name !== '') {

        $where[] = "name ILIKE :name";

        $params[':name'] = '%' . $name . '%';

    }


    $whereSql = '';

    if (!empty($where)) {

        $whereSql =
            'WHERE ' . implode(' AND ', $where);

    }


    /*
    |--------------------------------------------------------------------------
    | COUNT
    |--------------------------------------------------------------------------
    */

    $countSql = "
        SELECT COUNT(*)
        FROM public.instrument_categories
        {$whereSql}
    ";

    $countStmt = $pdo->prepare(
        $countSql
    );


    foreach ($params as $key => $value) {

        $countStmt->bindValue(
            $key,
            $value,
            PDO::PARAM_STR
        );

    }


    $countStmt->execute();


    $totalCategories =
        (int)$countStmt->fetchColumn();


    /*
    |--------------------------------------------------------------------------
    | PAGINATION
    |--------------------------------------------------------------------------
    */

    $totalPages = max(
        1,
        (int)ceil(
            $totalCategories / $perPage
        )
    );


    if ($currentPage > $totalPages) {

        $currentPage =
            $totalPages;

    }


    $offset =
        ($currentPage - 1) * $perPage;


    /*
    |--------------------------------------------------------------------------
    | FETCH DATA
    |--------------------------------------------------------------------------
    */

    $sql = "
        SELECT
            id,
            name,
            level1,
            level2,
            level3,
            composite,
            composition,
            status,
            created_at,
            updated_at
        FROM public.instrument_categories
        {$whereSql}
        ORDER BY name ASC NULLS LAST, id ASC
        LIMIT :limit
        OFFSET :offset
    ";


    $stmt = $pdo->prepare(
        $sql
    );


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


    $categories =
        $stmt->fetchAll(
            PDO::FETCH_ASSOC
        );


} catch (Throwable $e) {

    $dbError =
        $e->getMessage();

}

?>

<style>
    /* Centers the Actions column header and cell contents */
    .table-wrap th.th-center,
    .table-wrap td.td-center {
        text-align: center;
        vertical-align: middle;
    }

    .row-actions-center {
        display: flex;
        align-items: center;
        justify-content: center;
        gap: 6px;
        width: 100%;
    }
</style>

<!-- ============================================================
     PAGE HEADER
============================================================ -->

<section class="view-header">

    <div>

        <h1>

            Instrument Categories

            <span class="count-pill-navy">
                <?= number_format($totalCategories) ?> Total
            </span>

        </h1>

        <p class="sub">
            Manage instrument categories and category hierarchy.
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
        value="instrument_categories"
    >


    <div class="filter-controls">

        <div class="search-box">

            <svg class="icon">
                <use href="#i-search"/>
            </svg>

            <input
                type="text"
                name="name"
                value="<?= e($name) ?>"
                placeholder="Name contains"
            >

        </div>


        <button
            type="submit"
            class="btn btn-outline btn-sm"
        >
            Search
        </button>


        <a
            href="index.php?page=instrument_categories"
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
        <?= $totalCategories > 0
            ? (($currentPage - 1) * $perPage + 1)
            : 0 ?>
    </b>

    to

    <b>
        <?= min(
            $currentPage * $perPage,
            $totalCategories
        ) ?>
    </b>

    of

    <span>
        <?= number_format($totalCategories) ?>
    </span>

    Instrument Categories

</div>


<!-- ============================================================
     TABLE
============================================================ -->

<div class="table-wrap">

    <table>

        <thead>

            <tr>

                <th>
                    Name
                </th>

                <th>
                    Level1
                </th>

                <th>
                    Level2
                </th>

                <th>
                    Level3
                </th>

                <th class="th-center">
                    Actions
                </th>

            </tr>

        </thead>


        <tbody>

        <?php if (empty($categories)): ?>

            <tr class="empty-row">

                <td colspan="5">
                    No instrument categories found.
                </td>

            </tr>

        <?php endif; ?>


        <?php foreach ($categories as $category): ?>

            <tr>

                <!-- NAME -->

                <td>
                    <?= e(
                        $category['name'] ?? '-'
                    ) ?>
                </td>


                <!-- LEVEL 1 -->

                <td>
                    <?= e(
                        $category['level1'] ?? '-'
                    ) ?>
                </td>


                <!-- LEVEL 2 -->

                <td>
                    <?= e(
                        $category['level2'] ?? '-'
                    ) ?>
                </td>


                <!-- LEVEL 3 -->

                <td>
                    <?= e(
                        $category['level3'] ?? '-'
                    ) ?>
                </td>


                <!-- ACTIONS -->

                <td class="td-center">

                    <div class="row-actions row-actions-center">

                        <button
                            type="button"
                            class="btn btn-outline btn-sm"
                            onclick='viewInstrumentCategory(
                                <?= json_encode(
                                    $category,
                                    JSON_HEX_TAG |
                                    JSON_HEX_APOS |
                                    JSON_HEX_AMP |
                                    JSON_HEX_QUOT
                                ) ?>
                            )'
                        >
                            View
                        </button>


                        <button
                            type="button"
                            class="mini-btn"
                            onclick='editInstrumentCategory(
                                <?= json_encode(
                                    $category,
                                    JSON_HEX_TAG |
                                    JSON_HEX_APOS |
                                    JSON_HEX_AMP |
                                    JSON_HEX_QUOT
                                ) ?>
                            )'
                        >
                            Edit
                        </button>


                        <button
                            type="button"
                            class="mini-btn danger"
                            onclick="deleteInstrumentCategoryDisabled()"
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


<!-- ============================================================
     PAGINATION
============================================================ -->

<?php if ($totalPages > 1): ?>

    <div class="pagination">

        <?php if ($currentPage > 1): ?>

            <a
                class="pagination-btn"
                href="index.php?page=instrument_categories&name=<?= urlencode($name) ?>&p=<?= $currentPage - 1 ?>"
            >
                Previous
            </a>

        <?php endif; ?>


        <?php

        /*
        |--------------------------------------------------------------------------
        | Keep pagination compact
        |--------------------------------------------------------------------------
        */

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
                class="pagination-btn <?= $i === $currentPage ? 'active' : '' ?>"
                href="index.php?page=instrument_categories&name=<?= urlencode($name) ?>&p=<?= $i ?>"
            >
                <?= $i ?>
            </a>

        <?php endfor; ?>


        <?php if ($currentPage < $totalPages): ?>

            <a
                class="pagination-btn"
                href="index.php?page=instrument_categories&name=<?= urlencode($name) ?>&p=<?= $currentPage + 1 ?>"
            >
                Next
            </a>

        <?php endif; ?>

    </div>

<?php endif; ?>


<!-- ============================================================
     VIEW / EDIT MODAL
============================================================ -->

<div
    id="instrument-category-modal"
    class="user-popup-overlay"
    hidden
>

    <div
        class="user-popup"
        role="dialog"
        aria-modal="true"
    >

        <!-- HEADER -->

        <div class="user-popup-header">

            <div>

                <h2 id="instrument-category-modal-title">
                    View Instrument Category
                </h2>

                <p>
                    Instrument category information — display only.
                </p>

            </div>


            <button
                type="button"
                class="user-popup-close"
                onclick="closeInstrumentCategoryModal()"
            >
                &times;
            </button>

        </div>


        <!-- FORM -->

        <div class="user-popup-grid">

            <!-- ID -->

            <div class="field">

                <label>
                    ID
                </label>

                <input
                    id="instrument-category-id"
                    type="text"
                    disabled
                >

            </div>


            <!-- NAME -->

            <div class="field">

                <label>
                    Name
                </label>

                <input
                    id="instrument-category-name"
                    type="text"
                >

            </div>


            <!-- LEVEL 1 -->

            <div class="field">

                <label>
                    Level1
                </label>

                <input
                    id="instrument-category-level1"
                    type="text"
                >

            </div>


            <!-- LEVEL 2 -->

            <div class="field">

                <label>
                    Level2
                </label>

                <input
                    id="instrument-category-level2"
                    type="text"
                >

            </div>


            <!-- LEVEL 3 -->

            <div class="field">

                <label>
                    Level3
                </label>

                <input
                    id="instrument-category-level3"
                    type="text"
                >

            </div>


            <!-- COMPOSITE -->

            <div class="field">

                <label>
                    Composite
                </label>

                <select
                    id="instrument-category-composite"
                >

                    <option value="true">
                        Yes
                    </option>

                    <option value="false">
                        No
                    </option>

                </select>

            </div>


            <!-- STATUS -->

            <div class="field">

                <label>
                    Status
                </label>

                <input
                    id="instrument-category-status"
                    type="text"
                >

            </div>


            <!-- CREATED -->

            <div class="field">

                <label>
                    Created
                </label>

                <input
                    id="instrument-category-created"
                    type="text"
                    disabled
                >

            </div>


            <!-- UPDATED -->

            <div class="field">

                <label>
                    Updated
                </label>

                <input
                    id="instrument-category-updated"
                    type="text"
                    disabled
                >

            </div>


            <!-- COMPOSITION -->

            <div
                class="field"
                style="grid-column:1/-1;"
            >

                <label>
                    Composition
                </label>

                <textarea
                    id="instrument-category-composition"
                    rows="5"
                ></textarea>

            </div>

        </div>


        <!-- FOOTER -->

        <div class="user-popup-footer">

            <button
                type="button"
                class="btn btn-outline"
                onclick="closeInstrumentCategoryModal()"
            >
                Close
            </button>


            <button
                id="instrument-category-save"
                type="button"
                class="btn btn-orange"
                onclick="instrumentCategorySaveDisabled()"
            >
                Save Changes
            </button>

        </div>

    </div>

</div>


<script>

/*
|--------------------------------------------------------------------------
| Populate modal
|--------------------------------------------------------------------------
*/

function populateInstrumentCategory(category)
{
    document.getElementById(
        'instrument-category-id'
    ).value =
        category.id ?? '';


    document.getElementById(
        'instrument-category-name'
    ).value =
        category.name ?? '';


    document.getElementById(
        'instrument-category-level1'
    ).value =
        category.level1 ?? '';


    document.getElementById(
        'instrument-category-level2'
    ).value =
        category.level2 ?? '';


    document.getElementById(
        'instrument-category-level3'
    ).value =
        category.level3 ?? '';


    document.getElementById(
        'instrument-category-composite'
    ).value =
        String(
            category.composite
        ) === 'true'
            ? 'true'
            : 'false';


    document.getElementById(
        'instrument-category-status'
    ).value =
        category.status ?? '';


    document.getElementById(
        'instrument-category-created'
    ).value =
        category.created_at ?? '';


    document.getElementById(
        'instrument-category-updated'
    ).value =
        category.updated_at ?? '';


    let composition = '';

    if (category.composition) {

        try {

            if (
                typeof category.composition ===
                'string'
            ) {

                composition =
                    JSON.stringify(
                        JSON.parse(
                            category.composition
                        ),
                        null,
                        2
                    );

            } else {

                composition =
                    JSON.stringify(
                        category.composition,
                        null,
                        2
                    );

            }

        } catch (error) {

            composition =
                String(
                    category.composition
                );

        }

    }


    document.getElementById(
        'instrument-category-composition'
    ).value =
        composition;
}


/*
|--------------------------------------------------------------------------
| VIEW
|--------------------------------------------------------------------------
*/

function viewInstrumentCategory(category)
{
    populateInstrumentCategory(
        category
    );


    document.getElementById(
        'instrument-category-modal-title'
    ).textContent =
        'View Instrument Category';


    /*
    |--------------------------------------------------------------------------
    | View = read only
    |--------------------------------------------------------------------------
    */

    setInstrumentCategoryFieldsDisabled(
        true
    );


    document.getElementById(
        'instrument-category-save'
    ).disabled =
        true;


    document.getElementById(
        'instrument-category-modal'
    ).hidden =
        false;


    document.body.style.overflow =
        'hidden';
}


/*
|--------------------------------------------------------------------------
| EDIT
|--------------------------------------------------------------------------
*/

function editInstrumentCategory(category)
{
    populateInstrumentCategory(
        category
    );


    document.getElementById(
        'instrument-category-modal-title'
    ).textContent =
        'Edit Instrument Category';


    /*
    |--------------------------------------------------------------------------
    | Fields are editable visually.
    |
    | Save remains disabled so corporate DB is safe.
    |--------------------------------------------------------------------------
    */

    setInstrumentCategoryFieldsDisabled(
        false
    );


    document.getElementById(
        'instrument-category-id'
    ).disabled =
        true;


    document.getElementById(
        'instrument-category-created'
    ).disabled =
        true;


    document.getElementById(
        'instrument-category-updated'
    ).disabled =
        true;


    document.getElementById(
        'instrument-category-save'
    ).disabled =
        false;


    document.getElementById(
        'instrument-category-modal'
    ).hidden =
        false;


    document.body.style.overflow =
        'hidden';
}


/*
|--------------------------------------------------------------------------
| Enable / disable fields
|--------------------------------------------------------------------------
*/

function setInstrumentCategoryFieldsDisabled(
    disabled
)
{
    const fields = [

        'instrument-category-name',

        'instrument-category-level1',

        'instrument-category-level2',

        'instrument-category-level3',

        'instrument-category-composite',

        'instrument-category-status',

        'instrument-category-composition'

    ];


    fields.forEach(
        function(id) {

            document.getElementById(
                id
            ).disabled =
                disabled;

        }
    );
}


/*
|--------------------------------------------------------------------------
| CLOSE
|--------------------------------------------------------------------------
*/

function closeInstrumentCategoryModal()
{
    document.getElementById(
        'instrument-category-modal'
    ).hidden =
        true;


    document.body.style.overflow =
        '';
}


/*
|--------------------------------------------------------------------------
| SAVE
|--------------------------------------------------------------------------
| IMPORTANT:
| No INSERT / UPDATE is performed.
|--------------------------------------------------------------------------
*/

function instrumentCategorySaveDisabled()
{
    alert(
        'Save is currently disabled. No changes have been made to the database.'
    );
}


/*
|--------------------------------------------------------------------------
| DELETE
|--------------------------------------------------------------------------
| IMPORTANT:
| No DELETE is performed.
|--------------------------------------------------------------------------
*/

function deleteInstrumentCategoryDisabled()
{
    alert(
        'Delete is currently disabled. No database record has been deleted.'
    );
}


/*
|--------------------------------------------------------------------------
| ESC
|--------------------------------------------------------------------------
*/

document.addEventListener(
    'keydown',
    function(event) {

        if (
            event.key === 'Escape'
        ) {

            closeInstrumentCategoryModal();

        }

    }
);


/*
|--------------------------------------------------------------------------
| CLICK OUTSIDE MODAL
|--------------------------------------------------------------------------
*/

document
    .getElementById(
        'instrument-category-modal'
    )
    ?.addEventListener(
        'click',
        function(event) {

            if (
                event.target === this
            ) {

                closeInstrumentCategoryModal();

            }

        }
    );

</script>