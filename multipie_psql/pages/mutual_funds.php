<?php

/*
|--------------------------------------------------------------------------
| MULTIPIE - MUTUAL FUNDS
|--------------------------------------------------------------------------
|
| Database:
|     multipie_main_prod
|
| Table:
|     public.mutual_funds
|
| Current mode:
|     READ FROM DATABASE
|
| Database mutation:
|     NONE
|
| The following actions are intentionally safe:
|
|     Search          -> Database SELECT
|     View All        -> Database SELECT
|     Export CSV      -> Database SELECT
|     View            -> Display-only popup
|     Upload          -> Local file preview only
|     Rating Sample   -> Downloads CSV template
|     Bulk Update     -> Local CSV preview only
|
| No INSERT
| No UPDATE
| No DELETE
|
|--------------------------------------------------------------------------
*/


/*
|--------------------------------------------------------------------------
| FILTER VALUES
|--------------------------------------------------------------------------
*/

$displayNameStartsWith = trim(
    (string)($_GET['display_name_starts_with'] ?? '')
);

$fundCategory = trim(
    (string)($_GET['fund_category'] ?? '')
);

$amcId = trim(
    (string)($_GET['amc_id'] ?? '')
);


/*
|--------------------------------------------------------------------------
| VARIABLES
|--------------------------------------------------------------------------
*/

$mutualFunds = [];

$totalMutualFunds = 0;

$dbError = null;


/*
|--------------------------------------------------------------------------
| STATUS
|--------------------------------------------------------------------------
|
| Based on the existing MultiPie convention:
|
|     0 = Active
|     anything else = Inactive
|
|--------------------------------------------------------------------------
*/

function mutualFundStatusLabel($status): string
{
    return ((int)$status === 0)
        ? 'Active'
        : 'Inactive';
}


function mutualFundStatusClass($status): string
{
    return ((int)$status === 0)
        ? 'Active'
        : 'Suspended';
}


/*
|--------------------------------------------------------------------------
| SAFE DATE FORMAT
|--------------------------------------------------------------------------
*/

function mutualFundFormatDate($value): string
{
    if (empty($value)) {
        return '-';
    }

    try {

        return (new DateTime(
            (string)$value
        ))->format('d M Y');

    } catch (Throwable $e) {

        return (string)$value;
    }
}


/*
|--------------------------------------------------------------------------
| DATABASE
|--------------------------------------------------------------------------
*/

try {

    /*
    |--------------------------------------------------------------------------
    | Use existing application PostgreSQL connection.
    |--------------------------------------------------------------------------
    */

    $pdo = getAppDb();


    /*
    |--------------------------------------------------------------------------
    | BUILD FILTERS
    |--------------------------------------------------------------------------
    */

    $where = [];

    $params = [];


    /*
    |--------------------------------------------------------------------------
    | DISPLAY NAME STARTS WITH
    |--------------------------------------------------------------------------
    */

    if ($displayNameStartsWith !== '') {

        $where[] = "
            COALESCE(display_name, '') ILIKE :display_name_prefix
        ";

        $params[':display_name_prefix'] =
            $displayNameStartsWith . '%';
    }


    /*
    |--------------------------------------------------------------------------
    | FUND CATEGORY EQUALS
    |--------------------------------------------------------------------------
    */

    if ($fundCategory !== '') {

        $where[] = "
            LOWER(COALESCE(fund_category, ''))
            =
            LOWER(:fund_category)
        ";

        $params[':fund_category'] =
            $fundCategory;
    }


    /*
    |--------------------------------------------------------------------------
    | AMC ID EQUALS
    |--------------------------------------------------------------------------
    */

    if ($amcId !== '') {

        /*
        | Only allow numeric AMC IDs.
        */

        if (ctype_digit($amcId)) {

            $where[] = "
                amc_id = :amc_id
            ";

            $params[':amc_id'] =
                (int)$amcId;

        } else {

            /*
            | Invalid AMC ID should return no records.
            */

            $where[] = "1 = 0";
        }
    }


    /*
    |--------------------------------------------------------------------------
    | WHERE CLAUSE
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
        FROM public.mutual_funds
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
            is_int($value)
                ? PDO::PARAM_INT
                : PDO::PARAM_STR
        );
    }


    $countStmt->execute();


    $totalMutualFunds =
        (int)$countStmt->fetchColumn();


    /*
    |--------------------------------------------------------------------------
    | FETCH DATA
    |--------------------------------------------------------------------------
    */

    $sql = "
        SELECT

            id,
            display_name,
            option,
            plan,
            dividend_sub_type,
            instrument_id,
            fund_category,
            rank_in_category,
            inception_date,
            fact_sheet_url,
            fund_benchmark_id,
            fund_manager_id,
            created_at,
            updated_at,
            risk,
            status,
            amc_id,
            meta_info,
            ratings,
            url_slug,
            seo_desc

        FROM public.mutual_funds

        {$whereSql}

        ORDER BY
            display_name ASC NULLS LAST,
            id ASC
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
            is_int($value)
                ? PDO::PARAM_INT
                : PDO::PARAM_STR
        );
    }


    $stmt->execute();


    $mutualFunds =
        $stmt->fetchAll(
            PDO::FETCH_ASSOC
        );


} catch (Throwable $e) {

    $dbError =
        $e->getMessage();

    $mutualFunds = [];

    $totalMutualFunds = 0;
}


/*
|--------------------------------------------------------------------------
| EXPORT CSV
|--------------------------------------------------------------------------
|
| Export uses the same filtered dataset.
|
| This is intentionally implemented client-side through JavaScript
| below so that the existing page layout is not disturbed.
|
|--------------------------------------------------------------------------
*/

?>



<!-- ================================================================
     PAGE HEADER
================================================================ -->

<section class="view-header">

    <div>

        <h1>

            Mutual Funds

            <span class="count-pill-navy">

                <?= number_format(
                    $totalMutualFunds
                ) ?>

                Total

            </span>

        </h1>


        <p class="sub">

            Manage MultiPie mutual fund records, categories, AMC mappings,
            risk information, ratings, and fund details.

        </p>

    </div>

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
        value="mutual_funds"
    >


    <div class="filter-controls">


        <!-- DISPLAY NAME -->

        <div class="field">

            <label>
                Display name starts with:
            </label>

            <input
                type="text"
                name="display_name_starts_with"
                value="<?= e(
                    $displayNameStartsWith
                ) ?>"
                placeholder="Enter display name"
            >

        </div>



        <!-- FUND CATEGORY -->

        <div class="field">

            <label>
                Fund category equals:
            </label>

            <input
                type="text"
                name="fund_category"
                value="<?= e(
                    $fundCategory
                ) ?>"
                placeholder="Enter fund category"
            >

        </div>



        <!-- AMC -->

        <div class="field">

            <label>
                AMC equals:
            </label>

            <input
                type="number"
                name="amc_id"
                value="<?= e(
                    $amcId
                ) ?>"
                placeholder="AMC ID"
                min="0"
            >

        </div>



        <!-- SEARCH -->

        <button
            type="submit"
            class="btn btn-outline btn-sm"
        >
            Search
        </button>



        <!-- VIEW ALL -->

        <a
            href="index.php?page=mutual_funds"
            class="btn btn-outline btn-sm"
        >
            View All
        </a>



        <!-- EXPORT -->

        <button
            type="button"
            class="btn btn-outline btn-sm"
            onclick="exportMutualFundsCsv()"
        >
            Export to CSV
        </button>

    </div>

</form>



<!-- ================================================================
     UPLOAD / RATING / BULK UPDATE
================================================================ -->

<div
    class="filter-bar"
    style="margin-top:10px;"
>

    <div
        class="filter-controls"
        style="width:100%;"
    >


        <!-- FILE UPLOAD -->

        <div class="field">

            <label>
                Upload
            </label>

            <input
                type="file"
                id="mutualFundUpload"
                accept=".csv"
            >

        </div>


        <button
            type="button"
            class="btn btn-outline btn-sm"
            onclick="previewMutualFundUpload()"
        >
            Upload
        </button>



        <!-- RATING SAMPLE -->

        <button
            type="button"
            class="btn btn-outline btn-sm"
            onclick="downloadRatingSample()"
        >
            Rating Sample
        </button>



        <!-- BULK UPDATE -->

        <div class="field">

            <label>
                Bulk Update CSV
            </label>

            <input
                type="file"
                id="mutualFundBulkUpload"
                accept=".csv"
            >

        </div>


        <button
            type="button"
            class="btn btn-outline btn-sm"
            onclick="previewBulkUpdate()"
        >
            Bulk Update
        </button>

    </div>

</div>



<!-- ================================================================
     SAFE ACTION MESSAGE
================================================================ -->

<div
    id="mutualFundActionMessage"
    class="toast-inline"
    style="display:none;"
></div>



<!-- ================================================================
     COUNT
================================================================ -->

<div class="filter-count">

    Showing

    <b>
        <?= number_format(
            $totalMutualFunds
        ) ?>
    </b>

    Mutual Funds

</div>



<!-- ================================================================
     MUTUAL FUNDS TABLE
================================================================ -->

<div class="table-wrap">

    <table>

        <thead>

            <tr>

                <th>
                    Display Name
                </th>

                <th>
                    Fund Category
                </th>

                <th>
                    AMC-ID
                </th>

                <th>
                    Risk
                </th>

                <th>
                    Status
                </th>

                <th>
                    URL Slug
                </th>

                <th>
                    Fund Rating
                </th>

                <th>
                    Actions
                </th>

            </tr>

        </thead>


        <tbody>


        <?php if (empty($mutualFunds)): ?>

            <tr class="empty-row">

                <td colspan="8">

                    <?php if ($dbError): ?>

                        Unable to load mutual funds.

                    <?php else: ?>

                        No mutual funds found.

                    <?php endif; ?>

                </td>

            </tr>

        <?php endif; ?>



        <?php foreach (
            $mutualFunds
            as $fund
        ): ?>

            <tr>


                <!-- DISPLAY NAME -->

                <td>

                    <strong>

                        <?= e(
                            $fund['display_name']
                            ?? '-'
                        ) ?>

                    </strong>

                </td>



                <!-- FUND CATEGORY -->

                <td>

                    <?= e(
                        $fund['fund_category']
                        ?? '-'
                    ) ?>

                </td>



                <!-- AMC ID -->

                <td>

                    <?= e(
                        $fund['amc_id']
                        ?? '-'
                    ) ?>

                </td>



                <!-- RISK -->

                <td>

                    <?= e(
                        $fund['risk']
                        ?? '-'
                    ) ?>

                </td>



                <!-- STATUS -->

                <td>

                    <span
                        class="status-badge <?= e(
                            mutualFundStatusClass(
                                $fund['status'] ?? 1
                            )
                        ) ?>"
                    >

                        <?= e(
                            mutualFundStatusLabel(
                                $fund['status'] ?? 1
                            )
                        ) ?>

                    </span>

                </td>



                <!-- URL SLUG -->

                <td>

                    <?= e(
                        $fund['url_slug']
                        ?? '-'
                    ) ?>

                </td>



                <!-- RATING -->

                <td>

                    <?= e(
                        $fund['ratings']
                        ?? 'Not Rated'
                    ) ?>

                </td>



                <!-- ACTIONS -->

                <td>

                    <div class="row-actions">


                        <button
                            type="button"
                            class="mini-btn"
                            onclick="openMutualFundView(<?= htmlspecialchars(
                                json_encode(
                                    $fund,
                                    JSON_HEX_TAG |
                                    JSON_HEX_APOS |
                                    JSON_HEX_AMP |
                                    JSON_HEX_QUOT
                                ),
                                ENT_QUOTES,
                                'UTF-8'
                            ) ?>)"
                        >

                            View

                        </button>


                    </div>

                </td>


            </tr>

        <?php endforeach; ?>


        </tbody>

    </table>

</div>



<!-- ================================================================
     MUTUAL FUND VIEW MODAL
================================================================ -->

<div
    id="mutual-fund-modal"
    class="user-popup-overlay"
    hidden
>

    <div
        class="user-popup"
        role="dialog"
        aria-modal="true"
        aria-labelledby="mutual-fund-modal-title"
    >


        <!-- HEADER -->

        <div class="user-popup-header">

            <div>

                <h2 id="mutual-fund-modal-title">
                    Viewing Mutual Fund
                </h2>

                <p>
                    Existing mutual fund information — display only.
                </p>

            </div>


            <button
                type="button"
                class="user-popup-close"
                onclick="closeMutualFundModal()"
                aria-label="Close"
            >

                &times;

            </button>

        </div>



        <!-- BODY -->

        <div class="user-popup-grid">


            <!-- ID -->

            <div class="field">

                <label for="mf-modal-id">
                    ID
                </label>

                <input
                    id="mf-modal-id"
                    type="text"
                    disabled
                >

            </div>



            <!-- DISPLAY NAME -->

            <div class="field">

                <label for="mf-modal-display-name">
                    Display Name
                </label>

                <input
                    id="mf-modal-display-name"
                    type="text"
                    disabled
                >

            </div>



            <!-- FUND CATEGORY -->

            <div class="field">

                <label for="mf-modal-category">
                    Fund Category
                </label>

                <input
                    id="mf-modal-category"
                    type="text"
                    disabled
                >

            </div>



            <!-- AMC -->

            <div class="field">

                <label for="mf-modal-amc">
                    AMC ID
                </label>

                <input
                    id="mf-modal-amc"
                    type="text"
                    disabled
                >

            </div>



            <!-- RISK -->

            <div class="field">

                <label for="mf-modal-risk">
                    Risk
                </label>

                <input
                    id="mf-modal-risk"
                    type="text"
                    disabled
                >

            </div>



            <!-- STATUS -->

            <div class="field">

                <label for="mf-modal-status">
                    Status
                </label>

                <input
                    id="mf-modal-status"
                    type="text"
                    disabled
                >

            </div>



            <!-- URL SLUG -->

            <div class="field">

                <label for="mf-modal-slug">
                    URL Slug
                </label>

                <input
                    id="mf-modal-slug"
                    type="text"
                    disabled
                >

            </div>



            <!-- RATINGS -->

            <div class="field">

                <label for="mf-modal-ratings">
                    Fund Rating
                </label>

                <input
                    id="mf-modal-ratings"
                    type="text"
                    disabled
                >

            </div>



            <!-- OPTION -->

            <div class="field">

                <label for="mf-modal-option">
                    Option
                </label>

                <input
                    id="mf-modal-option"
                    type="text"
                    disabled
                >

            </div>



            <!-- PLAN -->

            <div class="field">

                <label for="mf-modal-plan">
                    Plan
                </label>

                <input
                    id="mf-modal-plan"
                    type="text"
                    disabled
                >

            </div>



            <!-- DIVIDEND SUB TYPE -->

            <div class="field">

                <label for="mf-modal-dividend">
                    Dividend Sub Type
                </label>

                <input
                    id="mf-modal-dividend"
                    type="text"
                    disabled
                >

            </div>



            <!-- INSTRUMENT -->

            <div class="field">

                <label for="mf-modal-instrument">
                    Instrument ID
                </label>

                <input
                    id="mf-modal-instrument"
                    type="text"
                    disabled
                >

            </div>



            <!-- RANK -->

            <div class="field">

                <label for="mf-modal-rank">
                    Rank In Category
                </label>

                <input
                    id="mf-modal-rank"
                    type="text"
                    disabled
                >

            </div>



            <!-- INCEPTION -->

            <div class="field">

                <label for="mf-modal-inception">
                    Inception Date
                </label>

                <input
                    id="mf-modal-inception"
                    type="text"
                    disabled
                >

            </div>



            <!-- FUND BENCHMARK -->

            <div class="field">

                <label for="mf-modal-benchmark">
                    Fund Benchmark ID
                </label>

                <input
                    id="mf-modal-benchmark"
                    type="text"
                    disabled
                >

            </div>



            <!-- FUND MANAGER -->

            <div class="field">

                <label for="mf-modal-manager">
                    Fund Manager ID
                </label>

                <input
                    id="mf-modal-manager"
                    type="text"
                    disabled
                >

            </div>



            <!-- FACT SHEET -->

            <div class="field">

                <label for="mf-modal-fact-sheet">
                    Fact Sheet URL
                </label>

                <input
                    id="mf-modal-fact-sheet"
                    type="text"
                    disabled
                >

            </div>



            <!-- CREATED -->

            <div class="field">

                <label for="mf-modal-created">
                    Created
                </label>

                <input
                    id="mf-modal-created"
                    type="text"
                    disabled
                >

            </div>



            <!-- UPDATED -->

            <div class="field">

                <label for="mf-modal-updated">
                    Updated
                </label>

                <input
                    id="mf-modal-updated"
                    type="text"
                    disabled
                >

            </div>



            <!-- SEO -->

            <div
                class="field"
                style="grid-column:1/-1;"
            >

                <label for="mf-modal-seo">
                    SEO Description
                </label>

                <textarea
                    id="mf-modal-seo"
                    rows="4"
                    disabled
                ></textarea>

            </div>



            <!-- META -->

            <div
                class="field"
                style="grid-column:1/-1;"
            >

                <label for="mf-modal-meta">
                    Meta Information
                </label>

                <textarea
                    id="mf-modal-meta"
                    rows="5"
                    disabled
                ></textarea>

            </div>

        </div>



        <!-- FOOTER -->

        <div class="user-popup-footer">

            <button
                type="button"
                class="btn btn-outline"
                onclick="closeMutualFundModal()"
            >
                Close
            </button>

        </div>


    </div>

</div>



<script>

/*
|--------------------------------------------------------------------------
| Utility
|--------------------------------------------------------------------------
*/

function mutualFundValue(value)
{
    if (
        value === null ||
        value === undefined ||
        value === ''
    ) {
        return '';
    }

    return String(value);
}


/*
|--------------------------------------------------------------------------
| VIEW MUTUAL FUND
|--------------------------------------------------------------------------
*/

function openMutualFundView(fund)
{
    document.getElementById(
        'mf-modal-id'
    ).value =
        mutualFundValue(
            fund.id
        );


    document.getElementById(
        'mf-modal-display-name'
    ).value =
        mutualFundValue(
            fund.display_name
        );


    document.getElementById(
        'mf-modal-category'
    ).value =
        mutualFundValue(
            fund.fund_category
        );


    document.getElementById(
        'mf-modal-amc'
    ).value =
        mutualFundValue(
            fund.amc_id
        );


    document.getElementById(
        'mf-modal-risk'
    ).value =
        mutualFundValue(
            fund.risk
        );


    document.getElementById(
        'mf-modal-status'
    ).value =
        mutualFundStatusText(
            fund.status
        );


    document.getElementById(
        'mf-modal-slug'
    ).value =
        mutualFundValue(
            fund.url_slug
        );


    document.getElementById(
        'mf-modal-ratings'
    ).value =
        mutualFundValue(
            fund.ratings || 'Not Rated'
        );


    document.getElementById(
        'mf-modal-option'
    ).value =
        mutualFundValue(
            fund.option
        );


    document.getElementById(
        'mf-modal-plan'
    ).value =
        mutualFundValue(
            fund.plan
        );


    document.getElementById(
        'mf-modal-dividend'
    ).value =
        mutualFundValue(
            fund.dividend_sub_type
        );


    document.getElementById(
        'mf-modal-instrument'
    ).value =
        mutualFundValue(
            fund.instrument_id
        );


    document.getElementById(
        'mf-modal-rank'
    ).value =
        mutualFundValue(
            fund.rank_in_category
        );


    document.getElementById(
        'mf-modal-inception'
    ).value =
        mutualFundValue(
            fund.inception_date
        );


    document.getElementById(
        'mf-modal-benchmark'
    ).value =
        mutualFundValue(
            fund.fund_benchmark_id
        );


    document.getElementById(
        'mf-modal-manager'
    ).value =
        mutualFundValue(
            fund.fund_manager_id
        );


    document.getElementById(
        'mf-modal-fact-sheet'
    ).value =
        mutualFundValue(
            fund.fact_sheet_url
        );


    document.getElementById(
        'mf-modal-created'
    ).value =
        mutualFundValue(
            fund.created_at
        );


    document.getElementById(
        'mf-modal-updated'
    ).value =
        mutualFundValue(
            fund.updated_at
        );


    document.getElementById(
        'mf-modal-seo'
    ).value =
        mutualFundValue(
            fund.seo_desc
        );


    document.getElementById(
        'mf-modal-meta'
    ).value =
        formatMutualFundMeta(
            fund.meta_info
        );


    document.getElementById(
        'mutual-fund-modal'
    ).hidden =
        false;


    document.body.style.overflow =
        'hidden';
}


/*
|--------------------------------------------------------------------------
| STATUS TEXT
|--------------------------------------------------------------------------
*/

function mutualFundStatusText(status)
{
    return Number(status) === 0
        ? 'Active'
        : 'Inactive';
}


/*
|--------------------------------------------------------------------------
| META JSON
|--------------------------------------------------------------------------
*/

function formatMutualFundMeta(meta)
{
    if (
        meta === null ||
        meta === undefined ||
        meta === ''
    ) {
        return '';
    }

    if (typeof meta === 'string') {

        return meta;
    }

    try {

        return JSON.stringify(
            meta,
            null,
            2
        );

    } catch (error) {

        return String(meta);
    }
}


/*
|--------------------------------------------------------------------------
| CLOSE MODAL
|--------------------------------------------------------------------------
*/

function closeMutualFundModal()
{
    const modal =
        document.getElementById(
            'mutual-fund-modal'
        );

    if (!modal) {
        return;
    }

    modal.hidden = true;

    document.body.style.overflow = '';
}


/*
|--------------------------------------------------------------------------
| CLOSE ON BACKDROP
|--------------------------------------------------------------------------
*/

document
    .getElementById(
        'mutual-fund-modal'
    )
    ?.addEventListener(
        'click',
        function(event) {

            if (
                event.target === this
            ) {

                closeMutualFundModal();

            }

        }
    );


/*
|--------------------------------------------------------------------------
| ESCAPE
|--------------------------------------------------------------------------
*/

document.addEventListener(
    'keydown',
    function(event) {

        if (
            event.key === 'Escape'
        ) {

            closeMutualFundModal();

        }

    }
);


/*
|--------------------------------------------------------------------------
| MESSAGE
|--------------------------------------------------------------------------
*/

function showMutualFundMessage(
    message
)
{
    const box =
        document.getElementById(
            'mutualFundActionMessage'
        );

    if (!box) {
        return;
    }

    box.textContent =
        message;

    box.style.display =
        'flex';

    window.setTimeout(
        function() {

            box.style.display =
                'none';

        },
        4000
    );
}


/*
|--------------------------------------------------------------------------
| EXPORT CSV
|--------------------------------------------------------------------------
|
| Exports the rows currently loaded from the database.
|
|--------------------------------------------------------------------------
*/

function exportMutualFundsCsv()
{
    const rows =
        <?= json_encode(
            $mutualFunds,
            JSON_HEX_TAG |
            JSON_HEX_APOS |
            JSON_HEX_AMP |
            JSON_HEX_QUOT
        ) ?>;


    if (
        !Array.isArray(rows) ||
        rows.length === 0
    ) {

        showMutualFundMessage(
            'There are no mutual funds to export.'
        );

        return;
    }


    const headers = [

        'ID',
        'Display Name',
        'Fund Category',
        'AMC ID',
        'Risk',
        'Status',
        'URL Slug',
        'Fund Rating',
        'Option',
        'Plan',
        'Dividend Sub Type',
        'Instrument ID',
        'Rank In Category',
        'Inception Date',
        'Fact Sheet URL',
        'Fund Benchmark ID',
        'Fund Manager ID',
        'Created At',
        'Updated At'

    ];


    const csvRows = [
        headers
    ];


    rows.forEach(
        function(row) {

            csvRows.push([

                row.id ?? '',
                row.display_name ?? '',
                row.fund_category ?? '',
                row.amc_id ?? '',
                row.risk ?? '',
                Number(row.status) === 0
                    ? 'Active'
                    : 'Inactive',
                row.url_slug ?? '',
                row.ratings ?? 'Not Rated',
                row.option ?? '',
                row.plan ?? '',
                row.dividend_sub_type ?? '',
                row.instrument_id ?? '',
                row.rank_in_category ?? '',
                row.inception_date ?? '',
                row.fact_sheet_url ?? '',
                row.fund_benchmark_id ?? '',
                row.fund_manager_id ?? '',
                row.created_at ?? '',
                row.updated_at ?? ''

            ]);

        }
    );


    const csv =
        csvRows
            .map(
                function(row) {

                    return row
                        .map(
                            function(value) {

                                return '"' +
                                    String(value)
                                        .replace(
                                            /"/g,
                                            '""'
                                        ) +
                                    '"';

                            }
                        )
                        .join(',');

                }
            )
            .join('\n');


    const blob =
        new Blob(
            [csv],
            {
                type:
                    'text/csv;charset=utf-8;'
            }
        );


    const url =
        URL.createObjectURL(
            blob
        );


    const link =
        document.createElement(
            'a'
        );


    link.href =
        url;

    link.download =
        'mutual_funds.csv';


    document.body.appendChild(
        link
    );


    link.click();


    document.body.removeChild(
        link
    );


    URL.revokeObjectURL(
        url
    );
}


/*
|--------------------------------------------------------------------------
| RATING SAMPLE
|--------------------------------------------------------------------------
|
| Downloads a safe CSV template.
|
|--------------------------------------------------------------------------
*/

function downloadRatingSample()
{
    const csv = [
        'id,display_name,ratings',
        '123,Sample Mutual Fund,5 Star'
    ].join('\n');


    const blob =
        new Blob(
            [csv],
            {
                type:
                    'text/csv;charset=utf-8;'
            }
        );


    const url =
        URL.createObjectURL(
            blob
        );


    const link =
        document.createElement(
            'a'
        );


    link.href =
        url;

    link.download =
        'mutual_fund_rating_sample.csv';


    document.body.appendChild(
        link
    );

    link.click();

    document.body.removeChild(
        link
    );

    URL.revokeObjectURL(
        url
    );
}


/*
|--------------------------------------------------------------------------
| READ LOCAL UPLOAD
|--------------------------------------------------------------------------
|
| IMPORTANT:
| This does NOT send anything to PostgreSQL.
|
|--------------------------------------------------------------------------
*/

function previewMutualFundUpload()
{
    const input =
        document.getElementById(
            'mutualFundUpload'
        );


    if (
        !input ||
        !input.files ||
        input.files.length === 0
    ) {

        showMutualFundMessage(
            'Please choose a CSV file first.'
        );

        return;
    }


    const file =
        input.files[0];


    if (
        !file.name
            .toLowerCase()
            .endsWith('.csv')
    ) {

        showMutualFundMessage(
            'Please select a CSV file.'
        );

        return;
    }


    const reader =
        new FileReader();


    reader.onload =
        function(event) {

            const content =
                String(
                    event.target.result
                    || ''
                );


            const lines =
                content
                    .split(/\r?\n/)
                    .filter(
                        line =>
                            line.trim() !== ''
                    );


            showMutualFundMessage(
                'Upload checked successfully. ' +
                lines.length +
                ' CSV row(s) detected. ' +
                'No database changes were made.'
            );

        };


    reader.onerror =
        function() {

            showMutualFundMessage(
                'Unable to read the selected CSV file.'
            );

        };


    reader.readAsText(
        file
    );
}


/*
|--------------------------------------------------------------------------
| BULK UPDATE PREVIEW
|--------------------------------------------------------------------------
|
| IMPORTANT:
| This intentionally does NOT update PostgreSQL.
|
|--------------------------------------------------------------------------
*/

function previewBulkUpdate()
{
    const input =
        document.getElementById(
            'mutualFundBulkUpload'
        );


    if (
        !input ||
        !input.files ||
        input.files.length === 0
    ) {

        showMutualFundMessage(
            'Please choose a Bulk Update CSV file first.'
        );

        return;
    }


    const file =
        input.files[0];


    if (
        !file.name
            .toLowerCase()
            .endsWith('.csv')
    ) {

        showMutualFundMessage(
            'Please select a CSV file.'
        );

        return;
    }


    const reader =
        new FileReader();


    reader.onload =
        function(event) {

            const content =
                String(
                    event.target.result
                    || ''
                );


            const lines =
                content
                    .split(/\r?\n/)
                    .filter(
                        line =>
                            line.trim() !== ''
                    );


            showMutualFundMessage(
                'Bulk Update CSV checked successfully. ' +
                Math.max(
                    0,
                    lines.length - 1
                ) +
                ' data row(s) detected. ' +
                'No database changes were made.'
            );

        };


    reader.onerror =
        function() {

            showMutualFundMessage(
                'Unable to read the selected CSV file.'
            );

        };


    reader.readAsText(
        file
    );
}

</script>