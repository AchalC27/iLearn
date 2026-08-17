<?php

/*
|--------------------------------------------------------------------------
| Holdings Statements
|--------------------------------------------------------------------------
| Database:
| multipie_main_prod
|
| Table:
| public.holdings_statements
|
| IMPORTANT:
| Edit and Delete buttons are UI-only.
| They DO NOT modify corporate data.
|--------------------------------------------------------------------------
*/

$pdo = getAppDb();


/*
|--------------------------------------------------------------------------
| Filters
|--------------------------------------------------------------------------
*/

$connectedUid = trim(
    (string)($_GET['connected_uid'] ?? '')
);

$status = (string)($_GET['status'] ?? '');

$page = max(
    1,
    (int)($_GET['p'] ?? 1)
);

$perPage = 10;


/*
|--------------------------------------------------------------------------
| WHERE conditions
|--------------------------------------------------------------------------
*/

$where = [];
$params = [];


/*
|--------------------------------------------------------------------------
| Connected account UID
|--------------------------------------------------------------------------
|
| The holdings_statements table contains:
|
| connected_account_id
|
| Since the connected-account table/schema has not been provided yet,
| we safely filter against connected_account_id for now.
|
|--------------------------------------------------------------------------
*/

if ($connectedUid !== '') {

    $where[] = "
        CAST(hs.connected_account_id AS TEXT) = :connected_uid
    ";

    $params[':connected_uid'] = $connectedUid;
}


/*
|--------------------------------------------------------------------------
| Status
|--------------------------------------------------------------------------
|
| Based on the current project convention:
|
| 0 = Active
| 1 = Inactive
|
|--------------------------------------------------------------------------
*/

if ($status !== '' && $status !== 'any') {

    $where[] = "
        hs.status = :status
    ";

    $params[':status'] = (int)$status;
}


/*
|--------------------------------------------------------------------------
| Build WHERE
|--------------------------------------------------------------------------
*/

$whereSql = '';

if (!empty($where)) {

    $whereSql =
        ' WHERE ' .
        implode(' AND ', $where);

}


/*
|--------------------------------------------------------------------------
| Total records
|--------------------------------------------------------------------------
*/

$countSql = "
    SELECT COUNT(*)
    FROM public.holdings_statements hs
    $whereSql
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

$totalStatements =
    (int)$countStmt->fetchColumn();


/*
|--------------------------------------------------------------------------
| Pagination
|--------------------------------------------------------------------------
*/

$totalPages = max(
    1,
    (int)ceil(
        $totalStatements / $perPage
    )
);

if ($page > $totalPages) {
    $page = $totalPages;
}

$offset =
    ($page - 1) * $perPage;


/*
|--------------------------------------------------------------------------
| Fetch holdings statements
|--------------------------------------------------------------------------
*/

$sql = "
    SELECT
        hs.id,
        hs.password,
        hs.statement_for,
        hs.statement_type,
        hs.status,
        hs.created_at,
        hs.updated_at,
        hs.connected_account_id,
        hs.meta_info
    FROM public.holdings_statements hs
    $whereSql
    ORDER BY
        hs.created_at DESC,
        hs.id DESC
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

$holdingsStatements =
    $stmt->fetchAll(PDO::FETCH_ASSOC);


/*
|--------------------------------------------------------------------------
| Helper functions
|--------------------------------------------------------------------------
*/

/*
 * Extract email from meta_info if available.
 *
 * We don't assume a particular connected-account table
 * until its actual schema is provided.
 */
function holdings_user_email($metaInfo): string
{
    if (empty($metaInfo)) {
        return '-';
    }

    if (is_string($metaInfo)) {

        $decoded = json_decode(
            $metaInfo,
            true
        );

        if (json_last_error() === JSON_ERROR_NONE) {
            $metaInfo = $decoded;
        }
    }

    if (!is_array($metaInfo)) {
        return '-';
    }

    $possibleKeys = [
        'email',
        'user_email',
        'userEmail',
        'email_address',
        'user' => 'email'
    ];

    foreach ($possibleKeys as $key => $value) {

        /*
         * Normal key
         */
        if (is_int($key)) {

            if (
                isset($metaInfo[$value]) &&
                is_string($metaInfo[$value]) &&
                trim($metaInfo[$value]) !== ''
            ) {

                return trim(
                    $metaInfo[$value]
                );

            }

            continue;
        }

        /*
         * Nested user.email
         */
        if (
            isset($metaInfo[$key]) &&
            is_array($metaInfo[$key]) &&
            isset($metaInfo[$key][$value])
        ) {

            return trim(
                (string)$metaInfo[$key][$value]
            );

        }
    }

    return '-';
}


/*
|--------------------------------------------------------------------------
| Statement label
|--------------------------------------------------------------------------
|
| We display the actual database value rather than inventing
| an incorrect enum mapping.
|
|--------------------------------------------------------------------------
*/

function holdings_statement_label($value): string
{
    if ($value === null || $value === '') {
        return '-';
    }

    return (string)$value;
}


/*
|--------------------------------------------------------------------------
| Statement For / Requested label
|--------------------------------------------------------------------------
*/

function holdings_statement_for_label($value): string
{
    if ($value === null || $value === '') {
        return '-';
    }

    return (string)$value;
}


/*
|--------------------------------------------------------------------------
| Status
|--------------------------------------------------------------------------
*/

function holdings_status_label($status): string
{
    if ((int)$status === 0) {
        return 'Active';
    }

    if ((int)$status === 1) {
        return 'Inactive';
    }

    return (string)$status;
}


function holdings_status_class($status): string
{
    if ((int)$status === 0) {
        return 'Active';
    }

    if ((int)$status === 1) {
        return 'Inactive';
    }

    return '';
}


/*
|--------------------------------------------------------------------------
| Pagination URL
|--------------------------------------------------------------------------
*/

function holdings_page_url(
    int $pageNumber,
    string $connectedUid,
    string $status
): string {

    $params = [
        'page' => 'holdings_statements',
        'p' => $pageNumber
    ];

    if ($connectedUid !== '') {
        $params['connected_uid'] =
            $connectedUid;
    }

    if ($status !== '') {
        $params['status'] =
            $status;
    }

    return 'index.php?' .
        http_build_query($params);
}

?>

<section class="view-header">

    <div>

        <h1>
            Holdings Statements

            <span class="count-pill-navy">
                <?= e($totalStatements) ?> Total
            </span>
        </h1>

        <p class="sub">
            Manage connected account holdings statements.
        </p>

    </div>

</section>


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
        value="holdings_statements"
    >

    <div class="filter-controls">

        <div class="filter-field">

            <label>
                Connected account Uid on broker equals
            </label>

            <input
                type="text"
                name="connected_uid"
                value="<?= e($connectedUid) ?>"
            >

        </div>


        <div class="filter-field">

            <label>
                Status equals
            </label>

            <select name="status">

                <option
                    value=""
                    <?= $status === ''
                        ? 'selected'
                        : '' ?>
                >
                    -Any-
                </option>

                <option
                    value="0"
                    <?= $status === '0'
                        ? 'selected'
                        : '' ?>
                >
                    Active
                </option>

                <option
                    value="1"
                    <?= $status === '1'
                        ? 'selected'
                        : '' ?>
                >
                    Inactive
                </option>

            </select>

        </div>


        <button
            type="submit"
            class="btn btn-outline btn-sm"
        >
            Search
        </button>


        <a
            href="index.php?page=holdings_statements"
            class="btn btn-outline btn-sm"
        >
            View All
        </a>

    </div>

</form>


<!-- ================================================================
     RESULT COUNT
================================================================ -->

<div class="filter-count">

    Showing

    <b>
        <?= $totalStatements > 0
            ? ($offset + 1)
            : 0 ?>
    </b>

    to

    <b>
        <?= min(
            $offset + $perPage,
            $totalStatements
        ) ?>
    </b>

    of

    <span>
        <?= e($totalStatements) ?>
    </span>

    Holdings Statements

</div>


<!-- ================================================================
     TABLE
================================================================ -->

<div class="table-wrap">

    <table>

        <thead>

            <tr>

                <th>
                    User Email
                </th>

                <th>
                    Statement
                </th>

                <th>
                    For Requested
                </th>

                <th>
                    On
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

        <?php if (!$holdingsStatements): ?>

            <tr class="empty-row">

                <td colspan="6">
                    No holdings statements found.
                </td>

            </tr>

        <?php endif; ?>


        <?php foreach ($holdingsStatements as $statement): ?>

            <?php

            $id =
                $statement['id'] ?? '';

            $email =
                holdings_user_email(
                    $statement['meta_info'] ?? null
                );

            $statementType =
                holdings_statement_label(
                    $statement['statement_type'] ?? null
                );

            $statementFor =
                holdings_statement_for_label(
                    $statement['statement_for'] ?? null
                );

            $statusText =
                holdings_status_label(
                    $statement['status'] ?? null
                );

            $statusClass =
                holdings_status_class(
                    $statement['status'] ?? null
                );

            ?>

            <tr>

                <!-- User Email -->

                <td>
                    <?= e($email) ?>
                </td>


                <!-- Statement -->

                <td>
                    <?= e($statementType) ?>
                </td>


                <!-- For Requested -->

                <td>
                    <?= e($statementFor) ?>
                </td>


                <!-- On -->

                <td
                    style="
                        color:var(--slate-500);
                        font-size:11px;
                    "
                >

                    <?= e(
                        $statement['created_at'] ?? ''
                    ) ?>

                </td>


                <!-- Status -->

                <td>

                    <span
                        class="status-badge
                        <?= e($statusClass) ?>"
                    >

                        <span
                            class="
                            dot-status
                            <?= e($statusClass) ?>
                            "
                        ></span>

                        <?= e($statusText) ?>

                    </span>

                </td>


                <!-- Actions -->

                <td>

                    <div class="table-actions">

                        <button
                            type="button"
                            class="mini-btn"
                            onclick="openHoldingEditModal(
                                <?= htmlspecialchars(
                                    json_encode(
                                        $statement,
                                        JSON_HEX_TAG |
                                        JSON_HEX_APOS |
                                        JSON_HEX_QUOT |
                                        JSON_HEX_AMP
                                    ),
                                    ENT_QUOTES,
                                    'UTF-8'
                                ) ?>
                            )"
                        >
                            Edit
                        </button>


                        <button
                            type="button"
                            class="mini-btn danger"
                            onclick="return false;"
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


<!-- ================================================================
     PAGINATION
================================================================ -->

<?php if ($totalPages > 1): ?>

    <div class="pagination">

        <?php if ($page > 1): ?>

            <a
                class="pagination-btn"
                href="<?= e(
                    holdings_page_url(
                        $page - 1,
                        $connectedUid,
                        $status
                    )
                ) ?>"
            >
                Previous
            </a>

        <?php endif; ?>


        <?php

        $startPage =
            max(1, $page - 2);

        $endPage =
            min(
                $totalPages,
                $page + 2
            );

        ?>


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
                    holdings_page_url(
                        $i,
                        $connectedUid,
                        $status
                    )
                ) ?>"
            >
                <?= e($i) ?>
            </a>

        <?php endfor; ?>


        <?php if ($page < $totalPages): ?>

            <a
                class="pagination-btn"
                href="<?= e(
                    holdings_page_url(
                        $page + 1,
                        $connectedUid,
                        $status
                    )
                ) ?>"
            >
                Next
            </a>

        <?php endif; ?>

    </div>

<?php endif; ?>


<!-- ================================================================
     EDIT MODAL
================================================================ -->

<div
    id="holdingEditModal"
    class="modal-overlay"
    style="display:none;"
>

    <div
        class="modal-container"
        role="dialog"
        aria-modal="true"
    >

        <div class="modal-header">

            <div>

                <h2>
                    Edit Holdings Statement
                </h2>

                <p>
                    Existing statement information — display only.
                </p>

            </div>


            <button
                type="button"
                class="modal-close"
                onclick="closeHoldingEditModal()"
                aria-label="Close"
            >
                &times;
            </button>

        </div>


        <div class="modal-body">

            <div class="form-grid">

                <div class="form-group">

                    <label>
                        ID
                    </label>

                    <input
                        id="holding_edit_id"
                        type="text"
                        readonly
                        disabled
                    >

                </div>


                <div class="form-group">

                    <label>
                        Connected Account ID
                    </label>

                    <input
                        id="holding_edit_connected_account"
                        type="text"
                        readonly
                    >

                </div>


                <div class="form-group">

                    <label>
                        Statement
                    </label>

                    <input
                        id="holding_edit_statement"
                        type="text"
                    >

                </div>


                <div class="form-group">

                    <label>
                        For Requested
                    </label>

                    <input
                        id="holding_edit_for"
                        type="text"
                    >

                </div>


                <div class="form-group">

                    <label>
                        Status
                    </label>

                    <select
                        id="holding_edit_status"
                    >

                        <option value="0">
                            Active
                        </option>

                        <option value="1">
                            Inactive
                        </option>

                    </select>

                </div>


                <div class="form-group">

                    <label>
                        Created
                    </label>

                    <input
                        id="holding_edit_created"
                        type="text"
                        readonly
                    >

                </div>


                <div class="form-group">

                    <label>
                        Updated
                    </label>

                    <input
                        id="holding_edit_updated"
                        type="text"
                        readonly
                    >

                </div>

            </div>

        </div>


        <div class="modal-footer">

            <button
                type="button"
                class="btn btn-outline"
                onclick="closeHoldingEditModal()"
            >
                Close
            </button>


            <!--
                Intentionally NOT connected to database.
                Corporate data must not be modified.
            -->

            <button
                type="button"
                class="btn btn-orange"
                onclick="return false;"
            >
                Save Changes
            </button>

        </div>

    </div>

</div>


<script>

function openHoldingEditModal(data)
{
    document.getElementById(
        'holding_edit_id'
    ).value =
        data.id ?? '';


    document.getElementById(
        'holding_edit_connected_account'
    ).value =
        data.connected_account_id ?? '';


    document.getElementById(
        'holding_edit_statement'
    ).value =
        data.statement_type ?? '';


    document.getElementById(
        'holding_edit_for'
    ).value =
        data.statement_for ?? '';


    document.getElementById(
        'holding_edit_status'
    ).value =
        data.status ?? '0';


    document.getElementById(
        'holding_edit_created'
    ).value =
        data.created_at ?? '';


    document.getElementById(
        'holding_edit_updated'
    ).value =
        data.updated_at ?? '';


    document.getElementById(
        'holdingEditModal'
    ).style.display =
        'flex';


    document.body.style.overflow =
        'hidden';
}


function closeHoldingEditModal()
{
    document.getElementById(
        'holdingEditModal'
    ).style.display =
        'none';


    document.body.style.overflow =
        '';
}


document.addEventListener(
    'click',
    function(event)
    {

        const modal =
            document.getElementById(
                'holdingEditModal'
            );


        if (
            event.target === modal
        ) {

            closeHoldingEditModal();

        }

    }
);


document.addEventListener(
    'keydown',
    function(event)
    {

        if (
            event.key === 'Escape'
        ) {

            closeHoldingEditModal();

        }

    }
);

</script>