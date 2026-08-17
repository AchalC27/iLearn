<?php

$perPage = 10;
$pageNumber = max(1, (int)($_GET['p'] ?? 1));

$statusFilter = strtolower(trim((string)($_GET['status'] ?? 'all')));

$links = [];
$totalLinks = 0;
$totalPages = 1;
$dbError = null;

/*
|--------------------------------------------------------------------------
| Status mapping
|--------------------------------------------------------------------------
| Existing project convention:
| 0 = Active
| 1 = Inactive
*/

$statusMap = [
    0 => 'Active',
    1 => 'Inactive',
];

try {

    $pdo = getAppDb();

    $where = [];
    $params = [];

    /*
    |--------------------------------------------------------------------------
    | Status Filter
    |--------------------------------------------------------------------------
    */

    if ($statusFilter === 'active') {

        $where[] = 'status = :status';
        $params[':status'] = 0;

    } elseif ($statusFilter === 'inactive') {

        $where[] = 'status = :status';
        $params[':status'] = 1;

    }

    $whereSql = '';

    if (!empty($where)) {
        $whereSql = 'WHERE ' . implode(' AND ', $where);
    }

    /*
    |--------------------------------------------------------------------------
    | Count
    |--------------------------------------------------------------------------
    */

    $countSql = "
        SELECT COUNT(*)
        FROM public.external_links
        {$whereSql}
    ";

    $countStmt = $pdo->prepare($countSql);

    foreach ($params as $key => $value) {

        $countStmt->bindValue(
            $key,
            $value,
            PDO::PARAM_INT
        );

    }

    $countStmt->execute();

    $totalLinks = (int)$countStmt->fetchColumn();

    $totalPages = max(
        1,
        (int)ceil($totalLinks / $perPage)
    );

    if ($pageNumber > $totalPages) {
        $pageNumber = $totalPages;
    }

    $offset = ($pageNumber - 1) * $perPage;

    /*
    |--------------------------------------------------------------------------
    | Fetch External Links
    |--------------------------------------------------------------------------
    */

    $sql = "
        SELECT
            id,
            title,
            description,
            display_sequence,
            redirect_url,
            status,
            meta_info,
            created_at,
            updated_at
        FROM public.external_links
        {$whereSql}
        ORDER BY display_sequence ASC NULLS LAST, id ASC
        LIMIT :limit
        OFFSET :offset
    ";

    $stmt = $pdo->prepare($sql);

    foreach ($params as $key => $value) {

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

    $links = $stmt->fetchAll(PDO::FETCH_ASSOC);

} catch (Throwable $e) {

    $dbError = $e->getMessage();

}


/*
|--------------------------------------------------------------------------
| Helper
|--------------------------------------------------------------------------
*/

function externalLinkStatusLabel($status): string
{
    $status = (int)$status;

    return $status === 0
        ? 'Active'
        : 'Inactive';
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

            External Links

            <span class="count-pill-navy">
                <?= number_format($totalLinks) ?> Total
            </span>

        </h1>

        <p class="sub">
            Manage external links and their display sequence.
        </p>

    </div>


    <button
        type="button"
        class="btn btn-orange"
        onclick="openExternalLinkModal()"
    >
        + Add New External Link
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
        value="external_links"
    >

    <div class="filter-controls">

        <select
            class="select-plain"
            name="status"
        >

            <option value="all">
                All Status
            </option>

            <option
                value="active"
                <?= $statusFilter === 'active'
                    ? 'selected'
                    : '' ?>
            >
                Active
            </option>

            <option
                value="inactive"
                <?= $statusFilter === 'inactive'
                    ? 'selected'
                    : '' ?>
            >
                Inactive
            </option>

        </select>


        <button
            type="submit"
            class="btn btn-outline btn-sm"
        >
            Filter
        </button>


        <a
            href="index.php?page=external_links"
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
        <?= $totalLinks > 0
            ? (($pageNumber - 1) * $perPage + 1)
            : 0 ?>
    </b>

    to

    <b>
        <?= min(
            $pageNumber * $perPage,
            $totalLinks
        ) ?>
    </b>

    of

    <span>
        <?= number_format($totalLinks) ?>
    </span>

    External Links

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
                    Status
                </th>

                <th>
                    Sequence
                </th>

                <th class="th-center">
                    Actions
                </th>

            </tr>

        </thead>


        <tbody>

        <?php if (empty($links)): ?>

            <tr class="empty-row">

                <td colspan="4">
                    No external links found.
                </td>

            </tr>

        <?php endif; ?>


        <?php foreach ($links as $link): ?>

            <?php
                $statusText =
                    externalLinkStatusLabel(
                        $link['status']
                    );
            ?>

            <tr>

                <!-- TITLE -->

                <td>

                    <?= e(
                        $link['title'] ?? '-'
                    ) ?>

                </td>


                <!-- STATUS -->

                <td>

                    <span
                        class="status-badge <?= e($statusText) ?>"
                    >

                        <span
                            class="dot-status <?= e($statusText) ?>"
                        ></span>

                        <?= e($statusText) ?>

                    </span>

                </td>


                <!-- SEQUENCE -->

                <td>

                    <?= e(
                        $link['display_sequence'] ?? '-'
                    ) ?>

                </td>


                <!-- ACTIONS -->

                 <td class="td-center">

                    <div class="row-actions row-actions-center">

                        <button
                            type="button"
                            class="mini-btn"
                            onclick='openEditExternalLink(<?= json_encode(
                                $link,
                                JSON_HEX_TAG |
                                JSON_HEX_APOS |
                                JSON_HEX_AMP |
                                JSON_HEX_QUOT
                            ) ?>)'
                        >
                            Edit
                        </button>

                        <button
                            type="button"
                            class="mini-btn danger"
                            onclick="externalLinkDeleteDisabled()"
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
                href="index.php?page=external_links&status=<?= urlencode($statusFilter) ?>&p=<?= $pageNumber - 1 ?>"
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
                href="index.php?page=external_links&status=<?= urlencode($statusFilter) ?>&p=<?= $i ?>"
                class="pagination-btn <?= $i === $pageNumber ? 'active' : '' ?>"
            >
                <?= $i ?>
            </a>

        <?php endfor; ?>


        <?php if ($pageNumber < $totalPages): ?>

            <a
                href="index.php?page=external_links&status=<?= urlencode($statusFilter) ?>&p=<?= $pageNumber + 1 ?>"
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
    id="external-link-modal"
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

                <h2 id="external-link-modal-title">
                    Add New External Link
                </h2>

                <p>
                    Enter external link information for display only.
                </p>

            </div>


            <button
                type="button"
                class="user-popup-close"
                onclick="closeExternalLinkModal()"
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
                    id="external-link-id"
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
                    id="external-link-title"
                    type="text"
                    placeholder="Enter title"
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
                    id="external-link-description"
                    rows="4"
                    placeholder="Enter description"
                ></textarea>

            </div>


            <!-- DISPLAY SEQUENCE -->

            <div class="field">

                <label>
                    Sequence
                </label>

                <input
                    id="external-link-sequence"
                    type="number"
                    placeholder="Enter display sequence"
                >

            </div>


            <!-- STATUS -->

            <div class="field">

                <label>
                    Status
                </label>

                <select id="external-link-status">

                    <option value="0">
                        Active
                    </option>

                    <option value="1">
                        Inactive
                    </option>

                </select>

            </div>


            <!-- REDIRECT URL -->

            <div
                class="field"
                style="grid-column:1/-1;"
            >

                <label>
                    Redirect URL
                </label>

                <input
                    id="external-link-url"
                    type="text"
                    placeholder="Enter redirect URL"
                >

            </div>


            <!-- CREATED -->

            <div class="field">

                <label>
                    Created
                </label>

                <input
                    id="external-link-created"
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
                    id="external-link-updated"
                    type="text"
                    value="Not updated yet"
                    disabled
                >

            </div>

        </div>


        <!-- FOOTER -->

        <div class="user-popup-footer">

            <button
                type="button"
                class="btn btn-outline"
                onclick="closeExternalLinkModal()"
            >
                Close
            </button>


            <button
                type="button"
                class="btn btn-orange"
                onclick="externalLinkSaveDisabled()"
            >
                Save External Link
            </button>

        </div>

    </div>

</div>


<script>

/*
|--------------------------------------------------------------------------
| ADD NEW EXTERNAL LINK
|--------------------------------------------------------------------------
*/

function openExternalLinkModal()
{
    document.getElementById(
        'external-link-modal-title'
    ).textContent =
        'Add New External Link';


    document.getElementById(
        'external-link-id'
    ).value =
        'Auto generated';


    document.getElementById(
        'external-link-title'
    ).value =
        '';


    document.getElementById(
        'external-link-description'
    ).value =
        '';


    document.getElementById(
        'external-link-sequence'
    ).value =
        '';


    document.getElementById(
        'external-link-status'
    ).value =
        '0';


    document.getElementById(
        'external-link-url'
    ).value =
        '';


    document.getElementById(
        'external-link-created'
    ).value =
        'Not created yet';


    document.getElementById(
        'external-link-updated'
    ).value =
        'Not updated yet';


    document.getElementById(
        'external-link-modal'
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

function openEditExternalLink(link)
{
    document.getElementById(
        'external-link-modal-title'
    ).textContent =
        'Edit External Link';


    document.getElementById(
        'external-link-id'
    ).value =
        link.id ?? '';


    document.getElementById(
        'external-link-title'
    ).value =
        link.title ?? '';


    document.getElementById(
        'external-link-description'
    ).value =
        link.description ?? '';


    document.getElementById(
        'external-link-sequence'
    ).value =
        link.display_sequence ?? '';


    document.getElementById(
        'external-link-status'
    ).value =
        link.status ?? '0';


    document.getElementById(
        'external-link-url'
    ).value =
        link.redirect_url ?? '';


    document.getElementById(
        'external-link-created'
    ).value =
        link.created_at ?? '';


    document.getElementById(
        'external-link-updated'
    ).value =
        link.updated_at ?? '';


    document.getElementById(
        'external-link-modal'
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

function closeExternalLinkModal()
{
    document.getElementById(
        'external-link-modal'
    ).hidden =
        true;


    document.body.style.overflow =
        '';
}


/*
|--------------------------------------------------------------------------
| SAVE
|--------------------------------------------------------------------------
| Intentionally does NOT touch PostgreSQL.
|--------------------------------------------------------------------------
*/

function externalLinkSaveDisabled()
{
    alert(
        'Save is currently disabled. No changes have been made to the database.'
    );
}


/*
|--------------------------------------------------------------------------
| DELETE
|--------------------------------------------------------------------------
| Intentionally does NOT touch PostgreSQL.
|--------------------------------------------------------------------------
*/

function externalLinkDeleteDisabled()
{
    alert(
        'Delete is currently disabled. No database record has been deleted.'
    );
}


/*
|--------------------------------------------------------------------------
| ESCAPE KEY
|--------------------------------------------------------------------------
*/

document.addEventListener(
    'keydown',
    function(event) {

        if (event.key === 'Escape') {
            closeExternalLinkModal();
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
        'external-link-modal'
    )
    ?.addEventListener(
        'click',
        function(event) {

            if (event.target === this) {
                closeExternalLinkModal();
            }

        }
    );

</script>