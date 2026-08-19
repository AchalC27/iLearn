<?php

$perPage = 10;
$pageNumber = max(1, (int)($_GET['p'] ?? 1));

$keywordFilter = trim(
    (string)($_GET['keyword'] ?? '')
);

$profaneWords = [];
$totalProfaneWords = 0;
$totalPages = 1;
$dbError = null;


/*
|--------------------------------------------------------------------------
| STATUS HELPER
|--------------------------------------------------------------------------
*/

function offensiveLabel($value): string
{
    return filter_var(
        $value,
        FILTER_VALIDATE_BOOLEAN
    ) ? 'Yes' : 'No';
}


/*
|--------------------------------------------------------------------------
| PAGINATION URL
|--------------------------------------------------------------------------
*/

function profanePageUrl(
    int $page,
    string $keyword = ''
): string {

    $params = [
        'page' => 'profane_words',
        'p' => $page
    ];

    if ($keyword !== '') {
        $params['keyword'] = $keyword;
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

    $pdo = getAppDb();

    $where = [];
    $params = [];


    /*
    |--------------------------------------------------------------------------
    | KEYWORD STARTS WITH
    |--------------------------------------------------------------------------
    */

    if ($keywordFilter !== '') {

        $where[] =
            'keyword ILIKE :keyword';

        $params[':keyword'] =
            $keywordFilter . '%';
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
        FROM public.profane_words
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

    $totalProfaneWords =
        (int)$countStmt->fetchColumn();


    /*
    |--------------------------------------------------------------------------
    | PAGINATION
    |--------------------------------------------------------------------------
    */

    $totalPages = max(
        1,
        (int)ceil(
            $totalProfaneWords /
            $perPage
        )
    );

    if ($pageNumber > $totalPages) {
        $pageNumber = $totalPages;
    }

    $offset =
        ($pageNumber - 1) *
        $perPage;


    /*
    |--------------------------------------------------------------------------
    | FETCH DATA
    |--------------------------------------------------------------------------
    */

    $sql = "
        SELECT
            id,
            keyword,
            created_at,
            updated_at,
            is_offensive
        FROM public.profane_words
        {$whereSql}
        ORDER BY
            keyword ASC NULLS LAST,
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

    $profaneWords =
        $stmt->fetchAll(
            PDO::FETCH_ASSOC
        );


} catch (Throwable $e) {

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

            List of Profane Words

            <span class="count-pill-navy">

                <?= number_format(
                    $totalProfaneWords
                ) ?>

                Total

            </span>

        </h1>

    </div>

</section>


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
     INSTRUCTIONS
============================================================ -->

<!-- <div class="card profane-instructions">

    <h2>
        Instructions to add Keyword
    </h2>

    <p>
        Download sample file for reference
    </p>

    <p>
        Upload CSV file using
        <strong>Upload CSV</strong>
        button
    </p>

    <p>
        When all uploaded keywords appear,
        click on
        <strong>Push CSV to S3</strong>
    </p>

    <p>
        After sometime click on,
        <strong>Start Model Training</strong>
    </p>

</div> -->


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
        value="profane_words"
    >

    <div class="filter-controls">

        <div class="search-box">

            <svg class="icon">
                <use href="#i-search"/>
            </svg>

            <input
                type="text"
                name="keyword"
                value="<?= e(
                    $keywordFilter
                ) ?>"
                placeholder="Keyword starts with..."
            >

        </div>


        <button
            type="submit"
            class="btn btn-outline btn-sm"
        >

            Search

        </button>


        <a
            href="index.php?page=profane_words"
            class="btn btn-outline btn-sm"
        >

            View All

        </a>

    </div>

</form>


<!-- ============================================================
     SPECIAL ACTIONS
============================================================ -->

<div
    class="profane-actions"
>

    <!-- UI ONLY -->
    <button
        type="button"
        class="btn btn-link-action"
        onclick="showProfaneInfo('Push CSV to S3')"
    >

        Push CSV to S3

    </button>


    <!-- UI ONLY -->
    <button
        type="button"
        class="btn btn-link-action"
        onclick="showProfaneInfo('Start Model Training')"
    >

        Start Model Training

    </button>

</div>


<!-- ============================================================
     CSV UPLOAD
============================================================ -->

<div class="card profane-upload-card">

    <div class="profane-upload-row">

        <input
            type="file"
            id="profaneCsv"
            accept=".csv"
        >


        <!-- UI ONLY -->
        <button
            type="button"
            class="btn btn-outline"
            onclick="showProfaneInfo('Upload CSV')"
        >

            Upload CSV

        </button>

    </div>


    <div class="profane-sample">

        <!-- UI ONLY -->
        <button
            type="button"
            class="btn btn-link-action"
            onclick="showProfaneInfo('Wordlist Sample')"
        >

            Wordlist Sample

        </button>

    </div>

</div>


<!-- ============================================================
     TABLE COUNT
============================================================ -->

<div class="filter-count">

    Showing

    <b>

        <?= $totalProfaneWords > 0
            ? ($offset + 1)
            : 0 ?>

        to

        <?= min(
            $offset + $perPage,
            $totalProfaneWords
        ) ?>

    </b>

    of

    <span>
        <?= number_format(
            $totalProfaneWords
        ) ?>
    </span>

    Profane Words

</div>


<!-- ============================================================
     TABLE
============================================================ -->

<div class="table-wrap">

    <table>

        <thead>

            <tr>

                <th>
                    Keyword
                </th>

                <th>
                    Is Offensive
                </th>

                <th>
                    Action
                </th>

            </tr>

        </thead>


        <tbody>

        <?php if (
            empty($profaneWords)
        ): ?>

            <tr class="empty-row">

                <td
                    colspan="3"
                >

                    No profane words found.

                </td>

            </tr>

        <?php endif; ?>


        <?php foreach (
            $profaneWords
            as $word
        ): ?>

            <tr>

                <td>

                    <?= e(
                        $word['keyword']
                        ?? ''
                    ) ?>

                </td>


                <td>

                    <?php
                    $isOffensive =
                        filter_var(
                            $word[
                                'is_offensive'
                            ] ?? false,
                            FILTER_VALIDATE_BOOLEAN
                        );
                    ?>


                    <span
                        class="status-badge
                        <?= $isOffensive
                            ? 'Active'
                            : 'Inactive' ?>"
                    >

                        <span
                            class="dot-status
                            <?= $isOffensive
                                ? 'Active'
                                : 'Inactive' ?>"
                        ></span>

                        <?= $isOffensive
                            ? 'Yes'
                            : 'No' ?>

                    </span>

                </td>


                <td>

                    <div
                        class="action-buttons"
                    >

                        <!-- UI ONLY -->
                        <button
                            type="button"
                            class="mini-btn"
                            onclick="openProfaneEditModal(
                                <?= (int)$word['id'] ?>
                            )"
                        >

                            Edit

                        </button>


                        <!-- UI ONLY -->
                        <button
                            type="button"
                            class="mini-btn danger"
                            onclick="showProfaneInfo('Delete')"
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

<?php if (
    $totalPages > 1
): ?>

    <div class="pagination">

        <?php if (
            $pageNumber > 1
        ): ?>

            <a
                href="<?= e(
                    profanePageUrl(
                        $pageNumber - 1,
                        $keywordFilter
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
                    profanePageUrl(
                        $i,
                        $keywordFilter
                    )
                ) ?>"
                class="pagination-btn
                <?= $i === $pageNumber
                    ? 'active'
                    : '' ?>"
            >

                <?= $i ?>

            </a>

        <?php endfor; ?>


        <?php if (
            $pageNumber < $totalPages
        ): ?>

            <a
                href="<?= e(
                    profanePageUrl(
                        $pageNumber + 1,
                        $keywordFilter
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
     EDIT MODAL
============================================================ -->

<div
    id="profaneEditModal"
    class="modal-overlay"
    style="display:none;"
>

    <div class="modal-box">

        <div class="modal-header">

            <div>

                <h2>
                    Edit Profane Word
                </h2>

                <p>
                    Existing profane word information —
                    display only.
                </p>

            </div>


            <button
                type="button"
                class="modal-close"
                onclick="closeProfaneEditModal()"
            >

                ×

            </button>

        </div>


        <div class="modal-body">

            <div class="form-grid">

                <div class="form-group">

                    <label>
                        ID
                    </label>

                    <input
                        id="profaneEditId"
                        type="text"
                        readonly
                        placeholder="ID"
                    >

                </div>


                <div class="form-group">

                    <label>
                        Keyword
                    </label>

                    <input
                        id="profaneEditKeyword"
                        type="text"
                        placeholder="Enter keyword"
                    >

                </div>


                <div class="form-group">

                    <label>
                        Is Offensive
                    </label>

                    <select
                        id="profaneEditOffensive"
                    >

                        <option value="true">
                            Yes
                        </option>

                        <option value="false">
                            No
                        </option>

                    </select>

                </div>


                <div class="form-group">

                    <label>
                        Created
                    </label>

                    <input
                        id="profaneEditCreated"
                        type="text"
                        readonly
                        value="Display only"
                    >

                </div>


                <div class="form-group">

                    <label>
                        Updated
                    </label>

                    <input
                        id="profaneEditUpdated"
                        type="text"
                        readonly
                        value="Display only"
                    >

                </div>

            </div>

        </div>


        <div class="modal-footer">

            <button
                type="button"
                class="btn btn-outline"
                onclick="closeProfaneEditModal()"
            >

                Close

            </button>


            <!-- Intentionally NON-FUNCTIONAL -->
            <button
                type="button"
                class="btn btn-primary"
                onclick="showProfaneInfo('Save Changes')"
            >

                Save Changes

            </button>

        </div>

    </div>

</div>


<!-- ============================================================
     ADD MODAL
============================================================ -->

<div
    id="profaneAddModal"
    class="modal-overlay"
    style="display:none;"
>

    <div class="modal-box">

        <div class="modal-header">

            <div>

                <h2>
                    Add New Profane Word
                </h2>

                <p>
                    Enter keyword information —
                    display only.
                </p>

            </div>


            <button
                type="button"
                class="modal-close"
                onclick="closeProfaneAddModal()"
            >

                ×

            </button>

        </div>


        <div class="modal-body">

            <div class="form-grid">

                <div class="form-group">

                    <label>
                        ID
                    </label>

                    <input
                        type="text"
                        value="Auto generated"
                        readonly
                    >

                </div>


                <div class="form-group">

                    <label>
                        Keyword
                    </label>

                    <input
                        type="text"
                        placeholder="Enter keyword"
                    >

                </div>


                <div class="form-group">

                    <label>
                        Is Offensive
                    </label>

                    <select>

                        <option value="true">
                            Yes
                        </option>

                        <option value="false">
                            No
                        </option>

                    </select>

                </div>


                <div class="form-group">

                    <label>
                        Created
                    </label>

                    <input
                        type="text"
                        value="Not created yet"
                        readonly
                    >

                </div>


                <div class="form-group">

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

        </div>


        <div class="modal-footer">

            <button
                type="button"
                class="btn btn-outline"
                onclick="closeProfaneAddModal()"
            >

                Close

            </button>


            <!-- Intentionally NON-FUNCTIONAL -->
            <button
                type="button"
                class="btn btn-primary"
                onclick="showProfaneInfo('Save Profane Word')"
            >

                Save Profane Word

            </button>

        </div>

    </div>

</div>


<script>

function openProfaneWordAddModal()
{
    document.getElementById(
        'profaneAddModal'
    ).style.display = 'flex';
}


function closeProfaneAddModal()
{
    document.getElementById(
        'profaneAddModal'
    ).style.display = 'none';
}


function closeProfaneEditModal()
{
    document.getElementById(
        'profaneEditModal'
    ).style.display = 'none';
}


/*
|--------------------------------------------------------------------------
| EDIT
|--------------------------------------------------------------------------
|
| For now this only opens the UI.
| No UPDATE query is executed.
|
*/

function openProfaneEditModal(id)
{
    document.getElementById(
        'profaneEditId'
    ).value = id;

    document.getElementById(
        'profaneEditModal'
    ).style.display = 'flex';
}


/*
|--------------------------------------------------------------------------
| SAFE UI ACTION
|--------------------------------------------------------------------------
*/

function showProfaneInfo(action)
{
    alert(
        action +
        ' is currently disabled because this page is connected to the corporate database.'
    );
}


/*
|--------------------------------------------------------------------------
| CLOSE WHEN CLICKING OUTSIDE
|--------------------------------------------------------------------------
*/

document.addEventListener(
    'click',
    function(event)
    {

        const editModal =
            document.getElementById(
                'profaneEditModal'
            );

        const addModal =
            document.getElementById(
                'profaneAddModal'
            );


        if (
            event.target ===
            editModal
        ) {

            closeProfaneEditModal();

        }


        if (
            event.target ===
            addModal
        ) {

            closeProfaneAddModal();

        }

    }
);

</script>