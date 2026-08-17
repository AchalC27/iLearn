<?php

$perPage = 10;
$pageNumber = max(1, (int)($_GET['p'] ?? 1));

$titleFilter = trim((string)($_GET['title'] ?? ''));
$productTypeFilter = trim((string)($_GET['product_type'] ?? ''));

$products = [];
$totalProducts = 0;
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
    | Product Type
    |--------------------------------------------------------------------------
    */

    if ($productTypeFilter !== '') {

        $where[] =
            'product_type ILIKE :product_type';

        $params[':product_type'] =
            '%' . $productTypeFilter . '%';
    }


    $whereSql = '';

    if (!empty($where)) {

        $whereSql =
            'WHERE ' .
            implode(' AND ', $where);
    }


    /*
    |--------------------------------------------------------------------------
    | COUNT
    |--------------------------------------------------------------------------
    */

    $countSql = "
        SELECT COUNT(*)
        FROM public.products
        {$whereSql}
    ";

    $countStmt =
        $pdo->prepare($countSql);


    foreach ($params as $key => $value) {

        $countStmt->bindValue(
            $key,
            $value,
            PDO::PARAM_STR
        );
    }


    $countStmt->execute();

    $totalProducts =
        (int)$countStmt->fetchColumn();


    /*
    |--------------------------------------------------------------------------
    | PAGINATION
    |--------------------------------------------------------------------------
    */

    $totalPages = max(
        1,
        (int)ceil(
            $totalProducts / $perPage
        )
    );


    if ($pageNumber > $totalPages) {

        $pageNumber =
            $totalPages;
    }


    $offset =
        ($pageNumber - 1) *
        $perPage;


    /*
    |--------------------------------------------------------------------------
    | FETCH PRODUCTS
    |--------------------------------------------------------------------------
    */

    $sql = "
        SELECT
            id,
            created_by,
            product_id,
            product_type,
            title,
            description,
            status,
            meta_info,
            created_at,
            updated_at,
            summary
        FROM public.products
        {$whereSql}
        ORDER BY
            title ASC NULLS LAST,
            id ASC
        LIMIT :limit
        OFFSET :offset
    ";


    $stmt =
        $pdo->prepare($sql);


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


    $products =
        $stmt->fetchAll(
            PDO::FETCH_ASSOC
        );


} catch (Throwable $e) {

    $dbError =
        $e->getMessage();
}


/*
|--------------------------------------------------------------------------
| Status
|--------------------------------------------------------------------------
*/

function productStatusLabel($status): string
{
    return (int)$status === 0
        ? 'Active'
        : 'Inactive';
}


/*
|--------------------------------------------------------------------------
| Pagination URL
|--------------------------------------------------------------------------
*/

function productPageUrl(
    int $page,
    string $title,
    string $productType
): string {

    $params = [
        'page' => 'products',
        'p' => $page
    ];


    if ($title !== '') {

        $params['title'] =
            $title;
    }


    if ($productType !== '') {

        $params['product_type'] =
            $productType;
    }


    return 'index.php?' .
        http_build_query($params);
}

?>


<!-- ============================================================
     PAGE HEADER
============================================================ -->

<section class="view-header">

    <div>

        <h1>

            Products

            <span class="count-pill-navy">
                <?= number_format(
                    $totalProducts
                ) ?>
                Total
            </span>

        </h1>

        <p class="sub">
            Manage MultiPie products.
        </p>

    </div>


    <button
        type="button"
        class="btn btn-orange"
        onclick="openProductAddModal()"
    >
        + New Product
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
        value="products"
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
            name="product_type"
            placeholder="Product type"
            value="<?= e($productTypeFilter) ?>"
        >


        <button
            type="submit"
            class="btn btn-outline btn-sm"
        >
            Filter
        </button>


        <a
            href="index.php?page=products"
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
        <?= $totalProducts > 0
            ? (
                ($pageNumber - 1) *
                $perPage + 1
            )
            : 0 ?>
    </b>

    to

    <b>
        <?= min(
            $offset + $perPage,
            $totalProducts
        ) ?>
    </b>

    of

    <span>
        <?= number_format(
            $totalProducts
        ) ?>
    </span>

    Products

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
                    Summary
                </th>

                <th>
                    Type
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

        <?php if (empty($products)): ?>

            <tr class="empty-row">

                <td colspan="5">

                    No Products found.

                </td>

            </tr>

        <?php endif; ?>


        <?php foreach ($products as $product): ?>

            <?php

            $statusText =
                productStatusLabel(
                    $product['status']
                );

            ?>


            <tr>


                <!-- TITLE -->

                <td>

                    <?= e(
                        $product['title'] ?? '-'
                    ) ?>

                </td>


                <!-- SUMMARY -->

                <td>

                    <?= e(
                        $product['summary'] ?? '-'
                    ) ?>

                </td>


                <!-- TYPE -->

                <td>

                    <?= e(
                        $product['product_type'] ?? '-'
                    ) ?>

                </td>


                <!-- STATUS -->

                <td>

                    <span
                        class="
                            status-badge
                            <?= e($statusText) ?>
                        "
                    >

                        <span
                            class="
                                dot-status
                                <?= e($statusText) ?>
                            "
                        ></span>

                        <?= e($statusText) ?>

                    </span>

                </td>


                <!-- ACTIONS -->

                <td>

                    <div class="table-actions">

                        <button
                            type="button"
                            class="mini-btn"
                            onclick='openProductEditModal(
                                <?= json_encode(
                                    $product,
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
                            onclick="productDeleteDisabled()"
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

        <?php if ($pageNumber > 1): ?>

            <a
                href="<?= e(
                    productPageUrl(
                        $pageNumber - 1,
                        $titleFilter,
                        $productTypeFilter
                    )
                ) ?>"
                class="pagination-btn"
            >
                Previous
            </a>

        <?php endif; ?>


        <?php

        $startPage =
            max(
                1,
                $pageNumber - 2
            );

        $endPage =
            min(
                $totalPages,
                $pageNumber + 2
            );

        ?>


        <?php for (
            $i = $startPage;
            $i <= $endPage;
            $i++
        ): ?>

            <a
                href="<?= e(
                    productPageUrl(
                        $i,
                        $titleFilter,
                        $productTypeFilter
                    )
                ) ?>"
                class="
                    pagination-btn
                    <?= $i === $pageNumber
                        ? 'active'
                        : '' ?>
                "
            >
                <?= e($i) ?>
            </a>

        <?php endfor; ?>


        <?php if (
            $pageNumber < $totalPages
        ): ?>

            <a
                href="<?= e(
                    productPageUrl(
                        $pageNumber + 1,
                        $titleFilter,
                        $productTypeFilter
                    )
                ) ?>"
                class="pagination-btn"
            >
                Next
            </a>

        <?php endif; ?>

    </div>

<?php endif; ?>


<!-- ============================================================
     ADD / EDIT PRODUCT POPUP
============================================================ -->

<div
    id="product-modal"
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

                <h2 id="product-modal-title">
                    New Product
                </h2>

                <p>
                    Enter product information for
                    display only.
                </p>

            </div>


            <button
                type="button"
                class="user-popup-close"
                onclick="closeProductModal()"
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
                    id="product-id"
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
                    id="product-title"
                    type="text"
                    placeholder="Enter title"
                >

            </div>


            <!-- PRODUCT TYPE -->

            <div class="field">

                <label>
                    Product Type
                </label>

                <input
                    id="product-type"
                    type="text"
                    placeholder="Enter product type"
                >

            </div>


            <!-- PRODUCT ID -->

            <div class="field">

                <label>
                    Product ID
                </label>

                <input
                    id="product-product-id"
                    type="number"
                    placeholder="Enter product ID"
                >

            </div>


            <!-- CREATED BY -->

            <div class="field">

                <label>
                    Created By
                </label>

                <input
                    id="product-created-by"
                    type="number"
                    placeholder="Enter created by"
                >

            </div>


            <!-- STATUS -->

            <div class="field">

                <label>
                    Status
                </label>

                <select
                    id="product-status"
                >

                    <option value="0">
                        Active
                    </option>

                    <option value="1">
                        Inactive
                    </option>

                </select>

            </div>


            <!-- CREATED -->

            <div class="field">

                <label>
                    Created
                </label>

                <input
                    id="product-created"
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
                    id="product-updated"
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
                    id="product-description"
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
                    id="product-summary"
                    rows="4"
                    placeholder="Enter summary"
                ></textarea>

            </div>


            <!-- META INFO -->

            <div
                class="field"
                style="grid-column:1/-1;"
            >

                <label>
                    Meta Info
                </label>

                <textarea
                    id="product-meta-info"
                    rows="4"
                    placeholder='{"key":"value"}'
                ></textarea>

            </div>

        </div>


        <!-- FOOTER -->

        <div class="user-popup-footer">

            <button
                type="button"
                class="btn btn-outline"
                onclick="closeProductModal()"
            >
                Close
            </button>


            <button
                type="button"
                class="btn btn-orange"
                onclick="productSaveDisabled()"
            >
                Save Product
            </button>

        </div>

    </div>

</div>


<script>

/*
|--------------------------------------------------------------------------
| ADD PRODUCT
|--------------------------------------------------------------------------
*/

function openProductAddModal()
{
    document.getElementById(
        'product-modal-title'
    ).textContent =
        'New Product';


    document.getElementById(
        'product-id'
    ).value =
        'Auto generated';


    document.getElementById(
        'product-title'
    ).value =
        '';


    document.getElementById(
        'product-type'
    ).value =
        '';


    document.getElementById(
        'product-product-id'
    ).value =
        '';


    document.getElementById(
        'product-created-by'
    ).value =
        '';


    document.getElementById(
        'product-status'
    ).value =
        '0';


    document.getElementById(
        'product-created'
    ).value =
        'Not created yet';


    document.getElementById(
        'product-updated'
    ).value =
        'Not updated yet';


    document.getElementById(
        'product-description'
    ).value =
        '';


    document.getElementById(
        'product-summary'
    ).value =
        '';


    document.getElementById(
        'product-meta-info'
    ).value =
        '';


    document.getElementById(
        'product-modal'
    ).hidden =
        false;


    document.body.style.overflow =
        'hidden';
}


/*
|--------------------------------------------------------------------------
| EDIT PRODUCT
|--------------------------------------------------------------------------
*/

function openProductEditModal(product)
{
    document.getElementById(
        'product-modal-title'
    ).textContent =
        'Edit Product';


    document.getElementById(
        'product-id'
    ).value =
        product.id ?? '';


    document.getElementById(
        'product-title'
    ).value =
        product.title ?? '';


    document.getElementById(
        'product-type'
    ).value =
        product.product_type ?? '';


    document.getElementById(
        'product-product-id'
    ).value =
        product.product_id ?? '';


    document.getElementById(
        'product-created-by'
    ).value =
        product.created_by ?? '';


    document.getElementById(
        'product-status'
    ).value =
        product.status ?? '0';


    document.getElementById(
        'product-created'
    ).value =
        product.created_at ?? '';


    document.getElementById(
        'product-updated'
    ).value =
        product.updated_at ?? '';


    document.getElementById(
        'product-description'
    ).value =
        product.description ?? '';


    document.getElementById(
        'product-summary'
    ).value =
        product.summary ?? '';


    document.getElementById(
        'product-meta-info'
    ).value =
        product.meta_info ?? '';


    document.getElementById(
        'product-modal'
    ).hidden =
        false;


    document.body.style.overflow =
        'hidden';
}


/*
|--------------------------------------------------------------------------
| CLOSE
|--------------------------------------------------------------------------
*/

function closeProductModal()
{
    document.getElementById(
        'product-modal'
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
| No INSERT / UPDATE is executed.
|--------------------------------------------------------------------------
*/

function productSaveDisabled()
{
    alert(
        'Save is currently disabled. No changes have been made to the database.'
    );
}


/*
|--------------------------------------------------------------------------
| DELETE
|--------------------------------------------------------------------------
|
| IMPORTANT:
| No DELETE is executed.
|--------------------------------------------------------------------------
*/

function productDeleteDisabled()
{
    alert(
        'Delete is currently disabled. No database record has been deleted.'
    );
}


/*
|--------------------------------------------------------------------------
| ESCAPE
|--------------------------------------------------------------------------
*/

document.addEventListener(
    'keydown',
    function(event)
    {

        if (
            event.key === 'Escape'
        ) {

            closeProductModal();

        }

    }
);


/*
|--------------------------------------------------------------------------
| CLICK OUTSIDE
|--------------------------------------------------------------------------
*/

document
    .getElementById(
        'product-modal'
    )
    ?.addEventListener(
        'click',
        function(event)
        {

            if (
                event.target === this
            ) {

                closeProductModal();

            }

        }
    );

</script>