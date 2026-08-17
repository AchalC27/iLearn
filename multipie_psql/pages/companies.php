<?php

/*
|--------------------------------------------------------------------------
| MULTIPIE - COMPANIES
|--------------------------------------------------------------------------
|
| Database:
|   multipie_main_prod
|
| Table:
|   public.companies
|
| Current mode:
|   READ FROM DATABASE
|
| IMPORTANT:
|   Edit / Add / Update Bio buttons are intentionally NON-FUNCTIONAL.
|   They only open editable UI popups.
|
|   No INSERT
|   No UPDATE
|   No DELETE
|
|--------------------------------------------------------------------------
*/


/*
|--------------------------------------------------------------------------
| Filters
|--------------------------------------------------------------------------
*/

$nameStartsWith = trim(
    (string)($_GET['name_starts_with'] ?? '')
);

$bseTicker = trim(
    (string)($_GET['bse_ticker'] ?? '')
);

$nseTicker = trim(
    (string)($_GET['nse_ticker'] ?? '')
);


/*
|--------------------------------------------------------------------------
| Database variables
|--------------------------------------------------------------------------
*/

$companies = [];

$totalCompanies = 0;

$dbError = null;


/*
|--------------------------------------------------------------------------
| Helper - status
|--------------------------------------------------------------------------
|
| As established earlier:
|
| 0 = Active
| anything else = Inactive
|
|--------------------------------------------------------------------------
*/

function companyStatusLabel($status): string
{
    return ((int)$status === 0)
        ? 'Active'
        : 'Inactive';
}


function companyStatusClass($status): string
{
    return ((int)$status === 0)
        ? 'Active'
        : 'Suspended';
}


/*
|--------------------------------------------------------------------------
| Helper - date
|--------------------------------------------------------------------------
*/

function companyFormatDate($value): string
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
| Database
|--------------------------------------------------------------------------
*/

try {

    /*
    |--------------------------------------------------------------------------
    | IMPORTANT:
    | Use the existing application DB connection.
    |--------------------------------------------------------------------------
    */

    $pdo = getAppDb();


    /*
    |--------------------------------------------------------------------------
    | Build filters
    |--------------------------------------------------------------------------
    */

    $where = [];

    $params = [];


    /*
    |--------------------------------------------------------------------------
    | Name starts with
    |--------------------------------------------------------------------------
    */

    if ($nameStartsWith !== '') {

        $where[] =
            "COALESCE(name, '') ILIKE :name_prefix";

        $params[':name_prefix'] =
            $nameStartsWith . '%';
    }


    /*
    |--------------------------------------------------------------------------
    | BSE ticker equals
    |--------------------------------------------------------------------------
    */

    if ($bseTicker !== '') {

        $where[] =
            "COALESCE(bse_ticker, '') ILIKE :bse_ticker";

        $params[':bse_ticker'] =
            $bseTicker;
    }


    /*
    |--------------------------------------------------------------------------
    | NSE ticker equals
    |--------------------------------------------------------------------------
    */

    if ($nseTicker !== '') {

        $where[] =
            "COALESCE(nse_ticker, '') ILIKE :nse_ticker";

        $params[':nse_ticker'] =
            $nseTicker;
    }


    /*
    |--------------------------------------------------------------------------
    | WHERE
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
    | Count
    |--------------------------------------------------------------------------
    */

    $countSql = "
        SELECT COUNT(*)
        FROM public.companies
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


    $totalCompanies =
        (int)$countStmt->fetchColumn();


    /*
    |--------------------------------------------------------------------------
    | Fetch companies
    |--------------------------------------------------------------------------
    |
    | There is no direct ISIN column in companies.
    |
    | Therefore:
    |
    | meta_info->>'isin'
    |
    | is used.
    |
    |--------------------------------------------------------------------------
    */

    $sql = "
        SELECT

            id,
            name,
            relevance,
            status,
            nse_ticker,
            bse_ticker,
            meta_info,
            created_at,
            updated_at,
            size_classification,
            sector_id,
            icon_url,
            instrument_id,
            short_name,
            series,
            url_slug,
            seo_desc,

            COALESCE(
                meta_info->>'isin',
                meta_info->>'ISIN',
                ''
            ) AS isin

        FROM public.companies

        {$whereSql}

        ORDER BY
            name ASC NULLS LAST
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


    $stmt->execute();


    $companies =
        $stmt->fetchAll(
            PDO::FETCH_ASSOC
        );


} catch (Throwable $e) {

    $dbError =
        $e->getMessage();

    $companies = [];

    $totalCompanies = 0;
}

?>



<!-- ================================================================
     PAGE HEADER
================================================================ -->

<section class="view-header">

    <div>

        <h1>

            Companies

            <span class="count-pill-navy">

                <?= number_format(
                    $totalCompanies
                ) ?>

                Total

            </span>

        </h1>


        <p class="sub">

            Manage MultiPie company records and company information.

        </p>

    </div>


    <!-- NEW COMPANY -->

    <button
        type="button"
        class="btn btn-orange"
        onclick="openAddCompanyModal()"
    >

        +

        New Company

    </button>

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
        value="companies"
    >


    <div
        class="filter-controls"
    >


        <!-- NAME -->

        <div class="field">

            <label>
                Name starts with:
            </label>

            <input
                type="text"
                name="name_starts_with"
                value="<?= e(
                    $nameStartsWith
                ) ?>"
            >

        </div>



        <!-- BSE -->

        <div class="field">

            <label>
                BSE ticker equals:
            </label>

            <input
                type="text"
                name="bse_ticker"
                value="<?= e(
                    $bseTicker
                ) ?>"
            >

        </div>



        <!-- NSE -->

        <div class="field">

            <label>
                NSE ticker equals:
            </label>

            <input
                type="text"
                name="nse_ticker"
                value="<?= e(
                    $nseTicker
                ) ?>"
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
            href="index.php?page=companies"
            class="btn btn-outline btn-sm"
        >

            View All

        </a>



        <!-- EXPORT -->

        <button
            type="button"
            class="btn btn-outline btn-sm"
            onclick="showCompanyDisabledMessage()"
        >

            Export to CSV

        </button>

    </div>

</form>



<!-- ================================================================
     COUNT
================================================================ -->

<div class="filter-count">

    Showing

    <b>
        <?= number_format(
            $totalCompanies
        ) ?>
    </b>

    Companies

</div>



<!-- ================================================================
     COMPANY TABLE
================================================================ -->

<div class="table-wrap">

    <table>

        <thead>

            <tr>

                <th>
                    Name
                </th>

                <th>
                    BSE Ticker
                </th>

                <th>
                    NSE Ticker
                </th>

                <th>
                    ISIN
                </th>

                <th>
                    URL Slug
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


        <?php if (empty($companies)): ?>

            <tr class="empty-row">

                <td colspan="7">

                    <?php if ($dbError): ?>

                        Unable to load companies.

                    <?php else: ?>

                        No companies found.

                    <?php endif; ?>

                </td>

            </tr>

        <?php endif; ?>



        <?php foreach (
            $companies
            as $company
        ): ?>


            <tr>


                <!-- NAME -->

                <td>

                    <strong
                        
                    >

                        <?= e(
                            $company['name']
                            ?? '-'
                        ) ?>

                    </strong>

                </td>



                <!-- BSE -->

                <td>

                    <?= e(
                        $company['bse_ticker']
                        ?? '-'
                    ) ?>

                </td>



                <!-- NSE -->

                <td>

                    <?= e(
                        $company['nse_ticker']
                        ?? '-'
                    ) ?>

                </td>



                <!-- ISIN -->

                <td>

                    <?= e(
                        $company['isin']
                        ?? '-'
                    ) ?>

                </td>



                <!-- URL -->

                <td>

                    <?= e(
                        $company['url_slug']
                        ?? '-'
                    ) ?>

                </td>



                <!-- STATUS -->

                <td>

                    <span
                        class="status-badge <?= e( companyStatusClass( $company['status'] ?? 1 ) ) ?>"
                    >
<?= e(
                            companyStatusLabel(
                                $company['status']
                                ?? 1
                            )
                        ) ?>

                    </span>

                </td>



                <!-- ACTIONS -->

                <td>

                    <div
                        class="row-actions"
                    >


                        <!-- VIEW -->

                        <button
                            type="button"
                            class="mini-btn"
                            onclick="openCompanyView(<?= htmlspecialchars(
                                json_encode(
                                    $company,
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



                        <!-- EDIT -->

                        <button
                            type="button"
                            class="mini-btn"
                            onclick="openCompanyEdit(<?= htmlspecialchars(
                                json_encode(
                                    $company,
                                    JSON_HEX_TAG |
                                    JSON_HEX_APOS |
                                    JSON_HEX_AMP |
                                    JSON_HEX_QUOT
                                ),
                                ENT_QUOTES,
                                'UTF-8'
                            ) ?>)"
                        >

                            Edit

                        </button>



                        <!-- UPDATE BIO -->

                        <button
                            type="button"
                            class="mini-btn warn"
                            onclick="openCompanyBio(<?= htmlspecialchars(
                                json_encode(
                                    $company,
                                    JSON_HEX_TAG |
                                    JSON_HEX_APOS |
                                    JSON_HEX_AMP |
                                    JSON_HEX_QUOT
                                ),
                                ENT_QUOTES,
                                'UTF-8'
                            ) ?>)"
                        >

                            Update Bio

                        </button>


                    </div>

                </td>


            </tr>


        <?php endforeach; ?>


        </tbody>

    </table>

</div>



<!-- =================================================================
     VIEW / EDIT COMPANY MODAL
================================================================= -->

<div
    id="companyModal"
    class="modal-overlay"
>

    <div
        class="modal-box wide"
    >


        <!-- HEADER -->

        <div
            class="modal-head"
        >

            <div>

                <h3
                    id="companyModalTitle"
                >
                    Viewing Company
                </h3>

                <p
                    id="companyModalSubtitle"
                >
                    Company information — display only.
                </p>

            </div>


            <button
                type="button"
                class="modal-close"
                onclick="closeCompanyModal()"
            >

                ×

            </button>

        </div>



        <!-- BODY -->

        <div
            
        >

            <div>

<div class="field-row">

<div class="field">

                    <label>
                        ID
                    </label>

                    <input
                        type="text"
                        id="company_id"
                        readonly
                    >

                </div>

<div class="field">

                    <label>
                        Name
                    </label>

                    <input
                        type="text"
                        id="company_name"
                        placeholder="Enter company name"
                    >

                </div>

</div>

<div class="field-row">

<div class="field">

                    <label>
                        BSE Ticker
                    </label>

                    <input
                        type="text"
                        id="company_bse"
                        placeholder="Enter BSE ticker"
                    >

                </div>

<div class="field">

                    <label>
                        NSE Ticker
                    </label>

                    <input
                        type="text"
                        id="company_nse"
                        placeholder="Enter NSE ticker"
                    >

                </div>

</div>

<div class="field-row">

<div class="field">

                    <label>
                        ISIN
                    </label>

                    <input
                        type="text"
                        id="company_isin"
                        placeholder="Enter ISIN"
                    >

                </div>

<div class="field">

                    <label>
                        Short Name
                    </label>

                    <input
                        type="text"
                        id="company_short_name"
                        placeholder="Enter short name"
                    >

                </div>

</div>

<div class="field-row">

<div class="field">

                    <label>
                        URL Slug
                    </label>

                    <input
                        type="text"
                        id="company_slug"
                        placeholder="Enter URL slug"
                    >

                </div>

<div class="field">

                    <label>
                        Series
                    </label>

                    <input
                        type="text"
                        id="company_series"
                        placeholder="Enter series"
                    >

                </div>

</div>

<div class="field-row">

<div class="field">

                    <label>
                        Relevance
                    </label>

                    <input
                        type="number"
                        id="company_relevance"
                        placeholder="Enter relevance"
                    >

                </div>

<div class="field">

                    <label>
                        Status
                    </label>

                    <select
                        id="company_status"
                    >

                        <option value="0">
                            Active
                        </option>

                        <option value="1">
                            Inactive
                        </option>

                    </select>

                </div>

</div>

<div class="field-row">

<div class="field">

                    <label>
                        Instrument ID
                    </label>

                    <input
                        type="text"
                        id="company_instrument_id"
                    >

                </div>

<div class="field">

                    <label>
                        Sector ID
                    </label>

                    <input
                        type="text"
                        id="company_sector_id"
                    >

                </div>

</div>

<div class="field-row">

<div class="field">

                    <label>
                        Created
                    </label>

                    <input
                        type="text"
                        id="company_created"
                        readonly
                    >

                </div>

<div class="field">

                    <label>
                        Updated
                    </label>

                    <input
                        type="text"
                        id="company_updated"
                        readonly
                    >

                </div>

</div>

<div
                    class="field"
                >

                    <label>
                        SEO Description
                    </label>

                    <textarea
                        id="company_seo"
                        placeholder="Enter SEO description"
                    ></textarea>

                </div>

</div>

        </div>



        <!-- FOOTER -->

        <div
            class="modal-footer"
        >

            <button
                type="button"
                class="btn btn-outline"
                onclick="closeCompanyModal()"
            >

                Close

            </button>


            <!-- DISABLED -->

            <button
                type="button"
                class="btn btn-orange"
                disabled
            >

                Save Changes

            </button>

        </div>


    </div>

</div>



<!-- =================================================================
     ADD COMPANY MODAL
================================================================= -->

<div
    id="addCompanyModal"
    class="modal-overlay"
>

    <div
        class="modal-box wide"
    >


        <!-- HEADER -->

        <div
            class="modal-head"
        >

            <div>

                <h3>
                    Add New Company
                </h3>

                <p>
                    Enter company information for display only.
                </p>

            </div>


            <button
                type="button"
                class="modal-close"
                onclick="closeAddCompanyModal()"
            >

                ×

            </button>

        </div>



        <!-- BODY -->

        <div
            
        >

            <div>

<div class="field-row">

<div class="field">

                    <label>
                        ID
                    </label>

                    <input
                        type="text"
                        value="Auto generated"
                        readonly
                    >

                </div>

<div class="field">

                    <label>
                        Name
                    </label>

                    <input
                        type="text"
                        placeholder="Enter company name"
                    >

                </div>

</div>

<div class="field-row">

<div class="field">

                    <label>
                        BSE Ticker
                    </label>

                    <input
                        type="text"
                        placeholder="Enter BSE ticker"
                    >

                </div>

<div class="field">

                    <label>
                        NSE Ticker
                    </label>

                    <input
                        type="text"
                        placeholder="Enter NSE ticker"
                    >

                </div>

</div>

<div class="field-row">

<div class="field">

                    <label>
                        ISIN
                    </label>

                    <input
                        type="text"
                        placeholder="Enter ISIN"
                    >

                </div>

<div class="field">

                    <label>
                        Short Name
                    </label>

                    <input
                        type="text"
                        placeholder="Enter short name"
                    >

                </div>

</div>

<div class="field-row">

<div class="field">

                    <label>
                        URL Slug
                    </label>

                    <input
                        type="text"
                        placeholder="Enter URL slug"
                    >

                </div>

<div class="field">

                    <label>
                        Series
                    </label>

                    <input
                        type="text"
                        placeholder="Enter series"
                    >

                </div>

</div>

<div class="field-row">

<div class="field">

                    <label>
                        Relevance
                    </label>

                    <input
                        type="number"
                        placeholder="Enter relevance"
                    >

                </div>

<div class="field">

                    <label>
                        Status
                    </label>

                    <select>

                        <option value="0">
                            Active
                        </option>

                        <option value="1">
                            Inactive
                        </option>

                    </select>

                </div>

</div>

<div class="field-row">

<div class="field">

                    <label>
                        Instrument ID
                    </label>

                    <input
                        type="text"
                        placeholder="Enter instrument ID"
                    >

                </div>

<div class="field">

                    <label>
                        Sector ID
                    </label>

                    <input
                        type="text"
                        placeholder="Enter sector ID"
                    >

                </div>

</div>

<div class="field-row">

<div class="field">

                    <label>
                        Created
                    </label>

                    <input
                        type="text"
                        value="Not created yet"
                        readonly
                    >

                </div>

<div class="field">

                    <label>
                        Updated
                    </label>

                    <input
                        type="text"
                        value="Not updated yet"
                        readonly
                    >

                </div>

</div>

<div
                    class="field"
                >

                    <label>
                        SEO Description
                    </label>

                    <textarea
                        placeholder="Enter SEO description"
                    ></textarea>

                </div>

</div>

        </div>



        <!-- FOOTER -->

        <div
            class="modal-footer"
        >

            <button
                type="button"
                class="btn btn-outline"
                onclick="closeAddCompanyModal()"
            >

                Close

            </button>


            <!-- DISABLED -->

            <button
                type="button"
                class="btn btn-orange"
                disabled
            >

                Save Company

            </button>

        </div>


    </div>

</div>



<!-- =================================================================
     UPDATE COMPANY BIO MODAL
================================================================= -->

<div
    id="companyBioModal"
    class="modal-overlay"
>

    <div
        class="modal-box wide"
    >


        <!-- HEADER -->

        <div
            class="modal-head"
        >

            <div>

                <h3>
                    Update Company Bio
                </h3>

                <p>
                    Update company information — display only.
                </p>

            </div>


            <button
                type="button"
                class="modal-close"
                onclick="closeCompanyBio()"
            >

                ×

            </button>

        </div>



        <!-- BODY -->

        <div
            
        >

            <div>

<div class="field-row">

<div class="field">

                    <label>
                        ID
                    </label>

                    <input
                        type="text"
                        id="bio_company_id"
                        readonly
                    >

                </div>

<div class="field">

                    <label>
                        Name
                    </label>

                    <input
                        type="text"
                        id="bio_company_name"
                    >

                </div>

</div>

<div class="field-row">

<div class="field">

                    <label>
                        Short Name
                    </label>

                    <input
                        type="text"
                        id="bio_company_short_name"
                    >

                </div>

<div class="field">

                    <label>
                        URL Slug
                    </label>

                    <input
                        type="text"
                        id="bio_company_slug"
                    >

                </div>

</div>

<div
                    class="field"
                >

                    <label>
                        Company Bio / SEO Description
                    </label>

                    <textarea
                        id="bio_company_seo"
                        placeholder="Enter company bio"
                    ></textarea>

                </div>

<div
                    class="field"
                >

                    <label>
                        Meta Information
                    </label>

                    <textarea
                        id="bio_company_meta"
                        placeholder="Company metadata"
                    ></textarea>

                </div>

</div>

        </div>



        <!-- FOOTER -->

        <div
            class="modal-footer"
        >

            <button
                type="button"
                class="btn btn-outline"
                onclick="closeCompanyBio()"
            >

                Close

            </button>


            <!-- DISABLED -->

            <button
                type="button"
                class="btn btn-orange"
                disabled
            >

                Save Changes

            </button>

        </div>


    </div>

</div>



<!-- =================================================================
     CSS
================================================================= -->





<!-- =================================================================
     JAVASCRIPT
================================================================= -->

<script>

/*
|--------------------------------------------------------------------------
| Utility
|--------------------------------------------------------------------------
*/

function companyValue(value)
{
    if (
        value === null ||
        value === undefined ||
        value === ''
    ) {

        return '';

    }

    return value;
}


/*
|--------------------------------------------------------------------------
| VIEW COMPANY
|--------------------------------------------------------------------------
*/

function openCompanyView(company)
{

    /*
    |--------------------------------------------------------------------------
    | Open the same popup as Edit.
    | Fields are populated from database.
    |--------------------------------------------------------------------------
    */

    populateCompanyForm(
        company
    );


    /*
    |--------------------------------------------------------------------------
    | Make it display-only.
    |--------------------------------------------------------------------------
    */

    setCompanyFieldsEditable(
        false
    );


    document.getElementById(
        'companyModalTitle'
    ).textContent =
        'Viewing Company';


    document.getElementById(
        'companyModalSubtitle'
    ).textContent =
        'Existing company information — display only.';


    document.querySelector(
        '#companyModal .btn[disabled]'
    ).style.display =
        'none';


    document.getElementById(
        'companyModal'
    ).classList.add(
        'open'
    );


    document.body.style.overflow =
        'hidden';
}



/*
|--------------------------------------------------------------------------
| EDIT COMPANY
|--------------------------------------------------------------------------
*/

function openCompanyEdit(company)
{

    populateCompanyForm(
        company
    );


    /*
    |--------------------------------------------------------------------------
    | Fields can be edited visually.
    |
    | IMPORTANT:
    | Save button remains disabled.
    |--------------------------------------------------------------------------
    */

    setCompanyFieldsEditable(
        true
    );


    document.getElementById(
        'companyModalTitle'
    ).textContent =
        'Edit Company';


    document.getElementById(
        'companyModalSubtitle'
    ).textContent =
        'Existing company information — display only.';


    const saveButton =
        document.querySelector(
            '#companyModal .btn[disabled]'
        );


    saveButton.style.display =
        'inline-flex';

    saveButton.disabled =
        true;


    document.getElementById(
        'companyModal'
    ).classList.add(
        'open'
    );


    document.body.style.overflow =
        'hidden';
}



/*
|--------------------------------------------------------------------------
| POPULATE EDIT / VIEW FORM
|--------------------------------------------------------------------------
*/

function populateCompanyForm(company)
{

    document.getElementById(
        'company_id'
    ).value =
        companyValue(
            company.id
        );


    document.getElementById(
        'company_name'
    ).value =
        companyValue(
            company.name
        );


    document.getElementById(
        'company_bse'
    ).value =
        companyValue(
            company.bse_ticker
        );


    document.getElementById(
        'company_nse'
    ).value =
        companyValue(
            company.nse_ticker
        );


    document.getElementById(
        'company_isin'
    ).value =
        companyValue(
            company.isin
        );


    document.getElementById(
        'company_short_name'
    ).value =
        companyValue(
            company.short_name
        );


    document.getElementById(
        'company_slug'
    ).value =
        companyValue(
            company.url_slug
        );


    document.getElementById(
        'company_series'
    ).value =
        companyValue(
            company.series
        );


    document.getElementById(
        'company_relevance'
    ).value =
        companyValue(
            company.relevance
        );


    document.getElementById(
        'company_status'
    ).value =
        companyValue(
            company.status
        );


    document.getElementById(
        'company_instrument_id'
    ).value =
        companyValue(
            company.instrument_id
        );


    document.getElementById(
        'company_sector_id'
    ).value =
        companyValue(
            company.sector_id
        );


    document.getElementById(
        'company_created'
    ).value =
        formatCompanyDate(
            company.created_at
        );


    document.getElementById(
        'company_updated'
    ).value =
        formatCompanyDate(
            company.updated_at
        );


    document.getElementById(
        'company_seo'
    ).value =
        companyValue(
            company.seo_desc
        );
}



/*
|--------------------------------------------------------------------------
| SET EDITABLE / READ ONLY
|--------------------------------------------------------------------------
*/

function setCompanyFieldsEditable(
    editable
)
{

    const ids = [

        'company_name',

        'company_bse',

        'company_nse',

        'company_isin',

        'company_short_name',

        'company_slug',

        'company_series',

        'company_relevance',

        'company_status',

        'company_instrument_id',

        'company_sector_id',

        'company_seo'

    ];


    ids.forEach(
        function(id)
        {

            const field =
                document.getElementById(
                    id
                );


            if (!field) {
                return;
            }


            field.readOnly =
                !editable;


            if (
                field.tagName ===
                'SELECT'
            ) {

                field.disabled =
                    !editable;
            }

        }
    );


    /*
    |--------------------------------------------------------------------------
    | ID / Created / Updated always read-only.
    |--------------------------------------------------------------------------
    */

    document.getElementById(
        'company_id'
    ).readOnly =
        true;


    document.getElementById(
        'company_created'
    ).readOnly =
        true;


    document.getElementById(
        'company_updated'
    ).readOnly =
        true;
}



/*
|--------------------------------------------------------------------------
| CLOSE COMPANY MODAL
|--------------------------------------------------------------------------
*/

function closeCompanyModal()
{

    document.getElementById(
        'companyModal'
    ).classList.remove(
        'open'
    );


    document.body.style.overflow =
        '';
}



/*
|--------------------------------------------------------------------------
| ADD COMPANY
|--------------------------------------------------------------------------
*/

function openAddCompanyModal()
{

    const modal =
        document.getElementById(
            'addCompanyModal'
        );


    /*
    |--------------------------------------------------------------------------
    | Clear editable fields
    |--------------------------------------------------------------------------
    */

    const inputs =
        modal.querySelectorAll(
            'input:not([readonly]), textarea'
        );


    inputs.forEach(
        function(input)
        {

            input.value =
                '';

        }
    );


    /*
    |--------------------------------------------------------------------------
    | Default status
    |--------------------------------------------------------------------------
    */

    const status =
        modal.querySelector(
            'select'
        );


    if (status) {

        status.value =
            '0';
    }


    modal.classList.add(
        'open'
    );


    document.body.style.overflow =
        'hidden';
}



/*
|--------------------------------------------------------------------------
| CLOSE ADD COMPANY
|--------------------------------------------------------------------------
*/

function closeAddCompanyModal()
{

    document.getElementById(
        'addCompanyModal'
    ).classList.remove(
        'open'
    );


    document.body.style.overflow =
        '';
}



/*
|--------------------------------------------------------------------------
| UPDATE COMPANY BIO
|--------------------------------------------------------------------------
*/

function openCompanyBio(company)
{

    document.getElementById(
        'bio_company_id'
    ).value =
        companyValue(
            company.id
        );


    document.getElementById(
        'bio_company_name'
    ).value =
        companyValue(
            company.name
        );


    document.getElementById(
        'bio_company_short_name'
    ).value =
        companyValue(
            company.short_name
        );


    document.getElementById(
        'bio_company_slug'
    ).value =
        companyValue(
            company.url_slug
        );


    document.getElementById(
        'bio_company_seo'
    ).value =
        companyValue(
            company.seo_desc
        );


    /*
    |--------------------------------------------------------------------------
    | meta_info is JSON.
    |--------------------------------------------------------------------------
    */

    let metaInfo = '';


    if (
        company.meta_info
    ) {

        if (
            typeof company.meta_info ===
            'string'
        ) {

            metaInfo =
                company.meta_info;

        } else {

            try {

                metaInfo =
                    JSON.stringify(
                        company.meta_info,
                        null,
                        2
                    );

            } catch (
                error
            ) {

                metaInfo =
                    String(
                        company.meta_info
                    );
            }
        }
    }


    document.getElementById(
        'bio_company_meta'
    ).value =
        metaInfo;


    /*
    |--------------------------------------------------------------------------
    | Open
    |--------------------------------------------------------------------------
    */

    document.getElementById(
        'companyBioModal'
    ).classList.add(
        'open'
    );


    document.body.style.overflow =
        'hidden';
}



/*
|--------------------------------------------------------------------------
| CLOSE BIO
|--------------------------------------------------------------------------
*/

function closeCompanyBio()
{

    document.getElementById(
        'companyBioModal'
    ).classList.remove(
        'open'
    );


    document.body.style.overflow =
        '';
}



/*
|--------------------------------------------------------------------------
| DATE FORMAT
|--------------------------------------------------------------------------
*/

function formatCompanyDate(
    value
)
{

    if (!value) {

        return '';

    }


    const date =
        new Date(
            value
        );


    if (
        Number.isNaN(
            date.getTime()
        )
    ) {

        return value;

    }


    return date.toLocaleDateString(
        'en-GB',
        {
            day: '2-digit',
            month: 'short',
            year: 'numeric'
        }
    );
}



/*
|--------------------------------------------------------------------------
| Disabled functionality message
|--------------------------------------------------------------------------
*/

function showCompanyDisabledMessage()
{

    alert(
        'CSV export is currently disabled for corporate database safety.'
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
            event.key !==
            'Escape'
        ) {

            return;
        }


        closeCompanyModal();

        closeAddCompanyModal();

        closeCompanyBio();

    }
);



/*
|--------------------------------------------------------------------------
| BACKDROP CLICK
|--------------------------------------------------------------------------
*/

document.getElementById(
    'companyModal'
).addEventListener(
    'click',
    function(event)
    {

        if (
            event.target ===
            this
        ) {

            closeCompanyModal();

        }

    }
);


document.getElementById(
    'addCompanyModal'
).addEventListener(
    'click',
    function(event)
    {

        if (
            event.target ===
            this
        ) {

            closeAddCompanyModal();

        }

    }
);


document.getElementById(
    'companyBioModal'
).addEventListener(
    'click',
    function(event)
    {

        if (
            event.target ===
            this
        ) {

            closeCompanyBio();

        }

    }
);

</script>