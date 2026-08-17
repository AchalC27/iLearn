<?php

$perPage = 10;
$pageNumber = max(1, (int)($_GET['p'] ?? 1));

$titleFilter = trim((string)($_GET['title'] ?? ''));
$identifierFilter = trim((string)($_GET['identifier'] ?? ''));

$multiBoxes = [];
$totalMultiBoxes = 0;
$totalPages = 1;
$dbError = null;

try {

    $pdo = getAppDb();

    $where = [];
    $params = [];

    /*
    |--------------------------------------------------------------------------
    | Title contains
    |--------------------------------------------------------------------------
    */

    if ($titleFilter !== '') {

        $where[] = 'title ILIKE :title';

        $params[':title'] =
            '%' . $titleFilter . '%';
    }


    /*
    |--------------------------------------------------------------------------
    | Identifier contains
    |--------------------------------------------------------------------------
    */

    if ($identifierFilter !== '') {

        $where[] = 'identifier ILIKE :identifier';

        $params[':identifier'] =
            '%' . $identifierFilter . '%';
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
        FROM public.multi_boxes
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

    $totalMultiBoxes =
        (int)$countStmt->fetchColumn();


    /*
    |--------------------------------------------------------------------------
    | Pagination
    |--------------------------------------------------------------------------
    */

    $totalPages = max(
        1,
        (int)ceil(
            $totalMultiBoxes / $perPage
        )
    );

    if ($pageNumber > $totalPages) {
        $pageNumber = $totalPages;
    }

    $offset =
        ($pageNumber - 1) * $perPage;


    /*
    |--------------------------------------------------------------------------
    | Fetch Multi Boxes
    |--------------------------------------------------------------------------
    */

    $sql = "
        SELECT
            id,
            title,
            identifier,
            relevance,
            product_type,
            created_at,
            updated_at,
            status,
            description,
            offered_by,
            created_by_id,
            ext_id,
            meta_info,
            summary
        FROM public.multi_boxes
        {$whereSql}
        ORDER BY title ASC NULLS LAST, id ASC
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

    $multiBoxes =
        $stmt->fetchAll(PDO::FETCH_ASSOC);

} catch (Throwable $e) {

    $dbError = $e->getMessage();
}


/*
|--------------------------------------------------------------------------
| Status helper
|--------------------------------------------------------------------------
|
| Existing project convention:
| 0 = Active
| 1 = Inactive
|
*/

function multiBoxStatusLabel($status): string
{
    return (int)$status === 0
        ? 'Active'
        : 'Inactive';
}

?>

<style>

    /*
    |--------------------------------------------------------------------------
    | Actions alignment
    |--------------------------------------------------------------------------
    */

    .multi-box-actions {
        display: flex;
        align-items: center;
        justify-content: center;
        gap: 6px;
        width: 100%;
    }

    .multi-box-actions-cell {
        text-align: center;
        vertical-align: middle;
    }

</style>


<!-- ============================================================
     PAGE HEADER
============================================================ -->

<section class="view-header">

    <div>

        <h1>

            Multi Boxes

            <span class="count-pill-navy">
                <?= number_format($totalMultiBoxes) ?> Total
            </span>

        </h1>

        <p class="sub">
            Manage MultiPie multi boxes.
        </p>

    </div>


    <button
        type="button"
        class="btn btn-orange"
        onclick="openMultiBoxModal()"
    >
        + Add New Multi Box
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
        value="multi_boxes"
    >


    <div class="filter-controls">

        <input
            type="text"
            name="title"
            placeholder="Title contains"
            value="<?= e($titleFilter) ?>"
        >


        <input
            type="text"
            name="identifier"
            placeholder="Identifier contains"
            value="<?= e($identifierFilter) ?>"
        >


        <button
            type="submit"
            class="btn btn-outline btn-sm"
        >
            Filter
        </button>


        <a
            href="index.php?page=multi_boxes"
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
        <?= $totalMultiBoxes > 0
            ? (($pageNumber - 1) * $perPage + 1)
            : 0 ?>
    </b>

    to

    <b>
        <?= min(
            $pageNumber * $perPage,
            $totalMultiBoxes
        ) ?>
    </b>

    of

    <span>
        <?= number_format($totalMultiBoxes) ?>
    </span>

    Multi Boxes

</div>


<!-- ============================================================
     TABLE
============================================================ -->

<div class="table-wrap">

    <table>

        <thead>

            <tr>

                <th>
                    Title
                </th>

                <th>
                    Identifier
                </th>

                <th>
                    Relevance
                </th>

                <th>
                    Product Type
                </th>

                <th class="multi-box-actions-cell">
                    Actions
                </th>

            </tr>

        </thead>


        <tbody>

        <?php if (empty($multiBoxes)): ?>

            <tr class="empty-row">

                <td colspan="5">
                    No Multi Boxes found.
                </td>

            </tr>

        <?php endif; ?>


        <?php foreach ($multiBoxes as $box): ?>

            <?php

            $statusText =
                multiBoxStatusLabel(
                    $box['status']
                );

            ?>

            <tr>

                <!-- TITLE -->

                <td>

                    <?= e(
                        $box['title'] ?? '-'
                    ) ?>

                </td>


                <!-- IDENTIFIER -->

                <td>

                    <?= e(
                        $box['identifier'] ?? '-'
                    ) ?>

                </td>


                <!-- RELEVANCE -->

                <td>

                    <?= e(
                        $box['relevance'] ?? '-'
                    ) ?>

                </td>


                <!-- PRODUCT TYPE -->

                <td>

                    <?= e(
                        $box['product_type'] ?? '-'
                    ) ?>

                </td>


                <!-- ACTIONS -->

                <td class="multi-box-actions-cell">

                    <div class="multi-box-actions">

                        <button
                            type="button"
                            class="mini-btn"
                            onclick='openViewMultiBox(
                                <?= json_encode(
                                    $box,
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
                            onclick='openEditMultiBox(
                                <?= json_encode(
                                    $box,
                                    JSON_HEX_TAG |
                                    JSON_HEX_APOS |
                                    JSON_HEX_AMP |
                                    JSON_HEX_QUOT
                                ) ?>
                            )'
                        >
                            Edit
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

        <?php if ($pageNumber > 1): ?>

            <a
                href="index.php?page=multi_boxes&title=<?= urlencode($titleFilter) ?>&identifier=<?= urlencode($identifierFilter) ?>&p=<?= $pageNumber - 1 ?>"
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
                href="index.php?page=multi_boxes&title=<?= urlencode($titleFilter) ?>&identifier=<?= urlencode($identifierFilter) ?>&p=<?= $i ?>"
                class="pagination-btn <?= $i === $pageNumber ? 'active' : '' ?>"
            >
                <?= $i ?>
            </a>

        <?php endfor; ?>


        <?php if ($pageNumber < $totalPages): ?>

            <a
                href="index.php?page=multi_boxes&title=<?= urlencode($titleFilter) ?>&identifier=<?= urlencode($identifierFilter) ?>&p=<?= $pageNumber + 1 ?>"
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
    id="multi-box-modal"
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

                <h2 id="multi-box-modal-title">
                    Add New Multi Box
                </h2>

                <p>
                    Enter Multi Box information for display only.
                </p>

            </div>


            <button
                type="button"
                class="user-popup-close"
                onclick="closeMultiBoxModal()"
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
                    id="multi-box-id"
                    type="text"
                    value="Auto generated"
                    disabled
                >

            </div>


            <!-- TITLE -->

            <div class="field">

                <label>
                    Title
                </label>

                <input
                    id="multi-box-title"
                    type="text"
                    placeholder="Enter title"
                >

            </div>


            <!-- IDENTIFIER -->

            <div class="field">

                <label>
                    Identifier
                </label>

                <input
                    id="multi-box-identifier"
                    type="text"
                    placeholder="Enter identifier"
                >

            </div>


            <!-- RELEVANCE -->

            <div class="field">

                <label>
                    Relevance
                </label>

                <input
                    id="multi-box-relevance"
                    type="number"
                    placeholder="Enter relevance"
                >

            </div>


            <!-- PRODUCT TYPE -->

            <div class="field">

                <label>
                    Product Type
                </label>

                <input
                    id="multi-box-product-type"
                    type="text"
                    placeholder="Enter product type"
                >

            </div>


            <!-- STATUS -->

            <div class="field">

                <label>
                    Status
                </label>

                <select id="multi-box-status">

                    <option value="0">
                        Active
                    </option>

                    <option value="1">
                        Inactive
                    </option>

                </select>

            </div>


            <!-- OFFERED BY -->

            <div class="field">

                <label>
                    Offered By
                </label>

                <input
                    id="multi-box-offered-by"
                    type="number"
                    placeholder="Enter offered by"
                >

            </div>


            <!-- CREATED BY -->

            <div class="field">

                <label>
                    Created By ID
                </label>

                <input
                    id="multi-box-created-by"
                    type="number"
                    placeholder="Enter created by ID"
                >

            </div>


            <!-- EXTERNAL ID -->

            <div class="field">

                <label>
                    External ID
                </label>

                <input
                    id="multi-box-ext-id"
                    type="text"
                    placeholder="Enter external ID"
                >

            </div>


            <!-- CREATED -->

            <div class="field">

                <label>
                    Created
                </label>

                <input
                    id="multi-box-created"
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
                    id="multi-box-updated"
                    type="text"
                    value="Not updated yet"
                    disabled
                >

            </div>


            <!-- DESCRIPTION -->

            <div
                class="field"
                style="grid-column:1/-1;"
            >

                <label>
                    Description
                </label>

                <textarea
                    id="multi-box-description"
                    rows="4"
                    placeholder="Enter description"
                ></textarea>

            </div>


            <!-- SUMMARY -->

            <div
                class="field"
                style="grid-column:1/-1;"
            >

                <label>
                    Summary
                </label>

                <textarea
                    id="multi-box-summary"
                    rows="4"
                    placeholder="Enter summary"
                ></textarea>

            </div>

        </div>


        <!-- FOOTER -->

        <div class="user-popup-footer">

            <button
                type="button"
                class="btn btn-outline"
                onclick="closeMultiBoxModal()"
            >
                Close
            </button>


            <button
                type="button"
                class="btn btn-orange"
                onclick="multiBoxSaveDisabled()"
            >
                Save Multi Box
            </button>

        </div>

    </div>

</div>


<!-- ============================================================
     VIEW MODAL
============================================================ -->

<div
    id="multi-box-view-modal"
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

                <h2>
                    View Multi Box
                </h2>

                <p>
                    Multi Box information — display only.
                </p>

            </div>


            <button
                type="button"
                class="user-popup-close"
                onclick="closeViewMultiBox()"
            >
                &times;
            </button>

        </div>


        <!-- VIEW FIELDS -->

        <div class="user-popup-grid">


            <div class="field">

                <label>
                    ID
                </label>

                <input
                    id="view-multi-box-id"
                    type="text"
                    disabled
                >

            </div>


            <div class="field">

                <label>
                    Title
                </label>

                <input
                    id="view-multi-box-title"
                    type="text"
                    disabled
                >

            </div>


            <div class="field">

                <label>
                    Identifier
                </label>

                <input
                    id="view-multi-box-identifier"
                    type="text"
                    disabled
                >

            </div>


            <div class="field">

                <label>
                    Relevance
                </label>

                <input
                    id="view-multi-box-relevance"
                    type="text"
                    disabled
                >

            </div>


            <div class="field">

                <label>
                    Product Type
                </label>

                <input
                    id="view-multi-box-product-type"
                    type="text"
                    disabled
                >

            </div>


            <div class="field">

                <label>
                    Status
                </label>

                <input
                    id="view-multi-box-status"
                    type="text"
                    disabled
                >

            </div>


            <div class="field">

                <label>
                    Offered By
                </label>

                <input
                    id="view-multi-box-offered-by"
                    type="text"
                    disabled
                >

            </div>


            <div class="field">

                <label>
                    Created By ID
                </label>

                <input
                    id="view-multi-box-created-by"
                    type="text"
                    disabled
                >

            </div>


            <div class="field">

                <label>
                    External ID
                </label>

                <input
                    id="view-multi-box-ext-id"
                    type="text"
                    disabled
                >

            </div>


            <div class="field">

                <label>
                    Created
                </label>

                <input
                    id="view-multi-box-created"
                    type="text"
                    disabled
                >

            </div>


            <div class="field">

                <label>
                    Updated
                </label>

                <input
                    id="view-multi-box-updated"
                    type="text"
                    disabled
                >

            </div>


            <div
                class="field"
                style="grid-column:1/-1;"
            >

                <label>
                    Description
                </label>

                <textarea
                    id="view-multi-box-description"
                    rows="4"
                    disabled
                ></textarea>

            </div>


            <div
                class="field"
                style="grid-column:1/-1;"
            >

                <label>
                    Summary
                </label>

                <textarea
                    id="view-multi-box-summary"
                    rows="4"
                    disabled
                ></textarea>

            </div>

        </div>


        <!-- FOOTER -->

        <div class="user-popup-footer">

            <button
                type="button"
                class="btn btn-outline"
                onclick="closeViewMultiBox()"
            >
                Close
            </button>

        </div>

    </div>

</div>


<script>

/*
|--------------------------------------------------------------------------
| ADD NEW MULTI BOX
|--------------------------------------------------------------------------
*/

function openMultiBoxModal()
{
    document.getElementById(
        'multi-box-modal-title'
    ).textContent =
        'Add New Multi Box';


    document.getElementById(
        'multi-box-id'
    ).value =
        'Auto generated';


    document.getElementById(
        'multi-box-title'
    ).value =
        '';


    document.getElementById(
        'multi-box-identifier'
    ).value =
        '';


    document.getElementById(
        'multi-box-relevance'
    ).value =
        '';


    document.getElementById(
        'multi-box-product-type'
    ).value =
        '';


    document.getElementById(
        'multi-box-status'
    ).value =
        '0';


    document.getElementById(
        'multi-box-offered-by'
    ).value =
        '';


    document.getElementById(
        'multi-box-created-by'
    ).value =
        '';


    document.getElementById(
        'multi-box-ext-id'
    ).value =
        '';


    document.getElementById(
        'multi-box-created'
    ).value =
        'Not created yet';


    document.getElementById(
        'multi-box-updated'
    ).value =
        'Not updated yet';


    document.getElementById(
        'multi-box-description'
    ).value =
        '';


    document.getElementById(
        'multi-box-summary'
    ).value =
        '';


    document.getElementById(
        'multi-box-modal'
    ).hidden =
        false;


    document.body.style.overflow =
        'hidden';
}


/*
|--------------------------------------------------------------------------
| EDIT MULTI BOX
|--------------------------------------------------------------------------
*/

function openEditMultiBox(box)
{
    document.getElementById(
        'multi-box-modal-title'
    ).textContent =
        'Edit Multi Box';


    document.getElementById(
        'multi-box-id'
    ).value =
        box.id ?? '';


    document.getElementById(
        'multi-box-title'
    ).value =
        box.title ?? '';


    document.getElementById(
        'multi-box-identifier'
    ).value =
        box.identifier ?? '';


    document.getElementById(
        'multi-box-relevance'
    ).value =
        box.relevance ?? '';


    document.getElementById(
        'multi-box-product-type'
    ).value =
        box.product_type ?? '';


    document.getElementById(
        'multi-box-status'
    ).value =
        box.status ?? '0';


    document.getElementById(
        'multi-box-offered-by'
    ).value =
        box.offered_by ?? '';


    document.getElementById(
        'multi-box-created-by'
    ).value =
        box.created_by_id ?? '';


    document.getElementById(
        'multi-box-ext-id'
    ).value =
        box.ext_id ?? '';


    document.getElementById(
        'multi-box-created'
    ).value =
        box.created_at ?? '';


    document.getElementById(
        'multi-box-updated'
    ).value =
        box.updated_at ?? '';


    document.getElementById(
        'multi-box-description'
    ).value =
        box.description ?? '';


    document.getElementById(
        'multi-box-summary'
    ).value =
        box.summary ?? '';


    document.getElementById(
        'multi-box-modal'
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

function closeMultiBoxModal()
{
    document.getElementById(
        'multi-box-modal'
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
| IMPORTANT:
| This does NOT update PostgreSQL.
|
*/

function multiBoxSaveDisabled()
{
    alert(
        'Save is currently disabled. No changes have been made to the database.'
    );
}


/*
|--------------------------------------------------------------------------
| VIEW
|--------------------------------------------------------------------------
*/

function openViewMultiBox(box)
{
    document.getElementById(
        'view-multi-box-id'
    ).value =
        box.id ?? '';


    document.getElementById(
        'view-multi-box-title'
    ).value =
        box.title ?? '';


    document.getElementById(
        'view-multi-box-identifier'
    ).value =
        box.identifier ?? '';


    document.getElementById(
        'view-multi-box-relevance'
    ).value =
        box.relevance ?? '';


    document.getElementById(
        'view-multi-box-product-type'
    ).value =
        box.product_type ?? '';


    document.getElementById(
        'view-multi-box-status'
    ).value =
        multiBoxStatusLabel(
            box.status
        );


    document.getElementById(
        'view-multi-box-offered-by'
    ).value =
        box.offered_by ?? '';


    document.getElementById(
        'view-multi-box-created-by'
    ).value =
        box.created_by_id ?? '';


    document.getElementById(
        'view-multi-box-ext-id'
    ).value =
        box.ext_id ?? '';


    document.getElementById(
        'view-multi-box-created'
    ).value =
        box.created_at ?? '';


    document.getElementById(
        'view-multi-box-updated'
    ).value =
        box.updated_at ?? '';


    document.getElementById(
        'view-multi-box-description'
    ).value =
        box.description ?? '';


    document.getElementById(
        'view-multi-box-summary'
    ).value =
        box.summary ?? '';


    document.getElementById(
        'multi-box-view-modal'
    ).hidden =
        false;


    document.body.style.overflow =
        'hidden';
}


/*
|--------------------------------------------------------------------------
| STATUS
|--------------------------------------------------------------------------
*/

function multiBoxStatusLabel(status)
{
    return Number(status) === 0
        ? 'Active'
        : 'Inactive';
}


/*
|--------------------------------------------------------------------------
| CLOSE VIEW
|--------------------------------------------------------------------------
*/

function closeViewMultiBox()
{
    document.getElementById(
        'multi-box-view-modal'
    ).hidden =
        true;


    document.body.style.overflow =
        '';
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

            closeMultiBoxModal();

            closeViewMultiBox();

        }

    }
);


/*
|--------------------------------------------------------------------------
| CLICK OUTSIDE ADD / EDIT MODAL
|--------------------------------------------------------------------------
*/

document
    .getElementById(
        'multi-box-modal'
    )
    ?.addEventListener(
        'click',
        function(event)
        {

            if (event.target === this) {

                closeMultiBoxModal();

            }

        }
    );


/*
|--------------------------------------------------------------------------
| CLICK OUTSIDE VIEW MODAL
|--------------------------------------------------------------------------
*/

document
    .getElementById(
        'multi-box-view-modal'
    )
    ?.addEventListener(
        'click',
        function(event)
        {

            if (event.target === this) {

                closeViewMultiBox();

            }

        }
    );

</script>