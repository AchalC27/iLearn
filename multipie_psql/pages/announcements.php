<?php

/*
|--------------------------------------------------------------------------
| Announcements
|--------------------------------------------------------------------------
| PostgreSQL application database:
|
| Database:
|     multipie_main_prod
|
| Table:
|     public.announcements
|
| Columns:
|     id
|     title
|     content
|     status
|     created_at
|     updated_at
|
| IMPORTANT:
| This page is READ-ONLY.
| Edit / Add / Delete buttons are UI only.
| They do NOT modify the corporate database.
|--------------------------------------------------------------------------
*/


/*
|--------------------------------------------------------------------------
| Variables
|--------------------------------------------------------------------------
*/

$dbError = null;

$announcements = [];

$totalAnnouncements = 0;

$q = trim((string)($_GET['q'] ?? ''));

$statusFilter = (string)($_GET['status'] ?? 'all');


/*
|--------------------------------------------------------------------------
| Database
|--------------------------------------------------------------------------
*/

try {

    /*
    |--------------------------------------------------------------------------
    | Application PostgreSQL database
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
    | Search
    |--------------------------------------------------------------------------
    |
    | Search by:
    | - title
    | - content
    |
    */

    if ($q !== '') {

        $where[] = "
            (
                COALESCE(title, '') ILIKE :search
                OR COALESCE(content, '') ILIKE :search
            )
        ";

        $params[':search'] = '%' . $q . '%';
    }


    /*
    |--------------------------------------------------------------------------
    | Status filter
    |--------------------------------------------------------------------------
    |
    | 0 = Active
    | 1 = Inactive
    |
    */

    if ($statusFilter !== 'all') {

        $where[] = "status = :status";

        $params[':status'] = (int)$statusFilter;
    }


    /*
    |--------------------------------------------------------------------------
    | WHERE
    |--------------------------------------------------------------------------
    */

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
        FROM public.announcements
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


    $totalAnnouncements =
        (int)$countStmt->fetchColumn();


    /*
    |--------------------------------------------------------------------------
    | Pagination
    |--------------------------------------------------------------------------
    */

    $perPage = 10;

    $currentPage =
        max(
            1,
            (int)($_GET['p'] ?? 1)
        );


    $totalPages =
        max(
            1,
            (int)ceil(
                $totalAnnouncements / $perPage
            )
        );


    if ($currentPage > $totalPages) {

        $currentPage = $totalPages;
    }


    $offset =
        ($currentPage - 1) * $perPage;


    /*
    |--------------------------------------------------------------------------
    | Fetch announcements
    |--------------------------------------------------------------------------
    */

    $sql = "
        SELECT
            id,
            title,
            content,
            status,
            created_at,
            updated_at
        FROM public.announcements
        {$whereSql}
        ORDER BY id DESC
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


    $announcements =
        $stmt->fetchAll(PDO::FETCH_ASSOC);


} catch (Throwable $e) {

    $dbError =
        $e->getMessage();

    $announcements = [];

    $totalAnnouncements = 0;

    $totalPages = 1;

    $currentPage = 1;
}


/*
|--------------------------------------------------------------------------
| Helper functions
|--------------------------------------------------------------------------
*/


/**
 * Convert announcement status into readable text.
 *
 * 0 = Active
 * anything else = Inactive
 */
function announcementStatusLabel($status): string
{
    return ((int)$status === 0)
        ? 'Active'
        : 'Inactive';
}


/**
 * Format PostgreSQL timestamp.
 */
function announcementFormatDate($value): string
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


/**
 * Preserve filters while changing pagination.
 */
function announcementQuery(array $overrides = []): string
{
    $query = [

        'page' =>
            'announcements',

        'q' =>
            (string)($_GET['q'] ?? ''),

        'status' =>
            (string)($_GET['status'] ?? 'all'),

        'p' =>
            (int)($_GET['p'] ?? 1),
    ];


    foreach ($overrides as $key => $value) {

        $query[$key] = $value;
    }


    return http_build_query(
        array_filter(
            $query,
            static function ($value) {

                return $value !== '';
            }
        )
    );
}

?>


<!-- ================================================================
     PAGE HEADER
================================================================ -->

<section class="view-header">

    <div>

        <h1>

            Announcements

            <span class="count-pill-navy">

                <?= number_format($totalAnnouncements) ?>

                Total

            </span>

        </h1>


        <p class="sub">

            Manage MultiPie announcements and platform notifications.

        </p>

    </div>


    <!-- ============================================================
         ADD ANNOUNCEMENT
         UI ONLY - NO DATABASE INSERT
    ============================================================= -->

    <button
        class="btn btn-orange"
        type="button"
        onclick="openAnnouncementModal('add')"
    >

        <span style="font-size:18px; margin-right:6px;">
            +
        </span>

        Add Announcement

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
        value="announcements"
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
                placeholder="Search announcements..."
            >

        </div>



        <!-- Status -->

        <select
            class="select-plain"
            name="status"
        >

            <option
                value="all"
                <?= $statusFilter === 'all' ? 'selected' : '' ?>
            >
                All Status
            </option>


            <option
                value="0"
                <?= $statusFilter === '0' ? 'selected' : '' ?>
            >
                Active
            </option>


            <option
                value="1"
                <?= $statusFilter === '1' ? 'selected' : '' ?>
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
            href="index.php?page=announcements"
        >

            Reset

        </a>


    </div>

</form>



<!-- ================================================================
     COUNT
================================================================ -->

<div class="filter-count">

    Showing

    <b>
        <?=
        $totalAnnouncements > 0
            ? (($currentPage - 1) * $perPage + 1)
            : 0
        ?>
        -
        <?=
        min(
            $currentPage * $perPage,
            $totalAnnouncements
        )
        ?>
    </b>

    of

    <span>
        <?= number_format($totalAnnouncements) ?>
    </span>

    Announcements

</div>



<!-- ================================================================
     ANNOUNCEMENTS TABLE
================================================================ -->

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

                <th class="right">
                    Actions
                </th>

            </tr>

        </thead>


        <tbody>


        <?php if (!$announcements): ?>

            <tr class="empty-row">

                <td colspan="3">

                    <?= $dbError
                        ? 'Unable to load announcements from PostgreSQL.'
                        : 'No announcements found matching the selected filters.'
                    ?>

                </td>

            </tr>

        <?php endif; ?>



        <?php foreach ($announcements as $announcement): ?>


            <?php

            $statusLabel =
                announcementStatusLabel(
                    $announcement['status']
                );

            ?>


            <tr>


                <!-- TITLE -->

                <td>

                    <strong>

                        <?= e(
                            $announcement['title'] ?? '-'
                        ) ?>

                    </strong>

                </td>



                <!-- STATUS -->

                <td>

                    <span
                        class="status-badge <?= e($statusLabel) ?>"
                    >

                        <span
                            class="dot-status <?= e($statusLabel) ?>"
                        ></span>

                        <?= e($statusLabel) ?>

                    </span>

                </td>



                <!-- ACTIONS -->

                <td class="right">

                    <div class="row-actions">


                        <!-- EDIT -->

                        <button
                            type="button"
                            class="mini-btn"
                            onclick='openAnnouncementModal(
                                "edit",
                                <?= json_encode(
                                    $announcement,
                                    JSON_HEX_TAG |
                                    JSON_HEX_APOS |
                                    JSON_HEX_AMP |
                                    JSON_HEX_QUOT
                                ) ?>
                            )'
                        >

                            Edit

                        </button>



                        <!-- DELETE -->

                        <button
                            type="button"
                            class="mini-btn danger"
                            disabled
                            title="Delete is intentionally disabled for corporate data."
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

<?php if (($totalPages ?? 1) > 1): ?>

    <div class="pagination">


        <?php if ($currentPage > 1): ?>

            <a
                class="btn btn-outline btn-sm"
                href="index.php?<?= e(
                    announcementQuery([
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
                    announcementQuery([
                        'p' => $currentPage + 1
                    ])
                ) ?>"
            >

                Next

            </a>

        <?php endif; ?>


    </div>

<?php endif; ?>



<!-- ================================================================
     ANNOUNCEMENT MODAL
================================================================ -->

<div
    id="announcement-modal"
    class="user-popup-overlay"
    hidden
>

    <div
        class="user-popup announcement-popup"
        role="dialog"
        aria-modal="true"
        aria-labelledby="announcement-modal-title"
    >


        <!-- ========================================================
             MODAL HEADER
        ========================================================= -->

        <div class="user-popup-header">

            <div>

                <h2 id="announcement-modal-title">

                    Add New Announcement

                </h2>


                <p id="announcement-modal-subtitle">

                    Enter announcement information for display only.

                </p>

            </div>


            <button
                type="button"
                class="user-popup-close"
                onclick="closeAnnouncementModal()"
                aria-label="Close"
            >

                &times;

            </button>

        </div>



        <!-- ========================================================
             MODAL BODY
        ========================================================= -->

        <div class="user-popup-grid">


            <!-- ID -->

            <div class="field">

                <label for="announcement-modal-id">
                    ID
                </label>


                <input
                    id="announcement-modal-id"
                    type="text"
                    value="Auto generated"
                    disabled
                >

            </div>



            <!-- TITLE -->

            <div class="field">

                <label for="announcement-modal-title-input">
                    Title
                </label>


                <input
                    id="announcement-modal-title-input"
                    type="text"
                    placeholder="Enter announcement title"
                >

            </div>



            <!-- CONTENT -->

            <div class="field">

                <label for="announcement-modal-content">
                    Content
                </label>


                <textarea
                    id="announcement-modal-content"
                    rows="6"
                    placeholder="Enter announcement content"
                ></textarea>

            </div>



            <!-- STATUS -->

            <div class="field">

                <label for="announcement-modal-status">
                    Status
                </label>


                <select
                    id="announcement-modal-status"
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

                <label for="announcement-modal-created">
                    Created
                </label>


                <input
                    id="announcement-modal-created"
                    type="text"
                    value="Not created yet"
                    disabled
                >

            </div>



            <!-- UPDATED -->

            <div class="field">

                <label for="announcement-modal-updated">
                    Updated
                </label>


                <input
                    id="announcement-modal-updated"
                    type="text"
                    value="Not updated yet"
                    disabled
                >

            </div>


        </div>



        <!-- ========================================================
             MODAL FOOTER
        ========================================================= -->

        <div class="user-popup-footer">


            <button
                type="button"
                class="btn btn-outline"
                onclick="closeAnnouncementModal()"
            >

                Close

            </button>



            <!--

                IMPORTANT:

                This button is intentionally NOT connected
                to PostgreSQL.

                It is clickable, but it does nothing.

            -->

            <button
                type="button"
                class="btn btn-navy"
                onclick="displayOnlyAnnouncementSave()"
            >

                <span id="announcement-save-text">
                    Save Announcement
                </span>

            </button>


        </div>


    </div>

</div>



<!-- ================================================================
     ANNOUNCEMENT MODAL JAVASCRIPT
================================================================ -->

<script>

function openAnnouncementModal(mode, announcement = null)
{
    const modal =
        document.getElementById(
            'announcement-modal'
        );


    const modalTitle =
        document.getElementById(
            'announcement-modal-title'
        );


    const modalSubtitle =
        document.getElementById(
            'announcement-modal-subtitle'
        );


    const idInput =
        document.getElementById(
            'announcement-modal-id'
        );


    const titleInput =
        document.getElementById(
            'announcement-modal-title-input'
        );


    const contentInput =
        document.getElementById(
            'announcement-modal-content'
        );


    const statusInput =
        document.getElementById(
            'announcement-modal-status'
        );


    const createdInput =
        document.getElementById(
            'announcement-modal-created'
        );


    const updatedInput =
        document.getElementById(
            'announcement-modal-updated'
        );


    const saveText =
        document.getElementById(
            'announcement-save-text'
        );


    /*
    |--------------------------------------------------------------------------
    | ADD
    |--------------------------------------------------------------------------
    */

    if (mode === 'add') {

        modalTitle.textContent =
            'Add New Announcement';


        modalSubtitle.textContent =
            'Enter announcement information for display only.';


        idInput.value =
            'Auto generated';


        titleInput.value =
            '';


        contentInput.value =
            '';


        statusInput.value =
            '0';


        createdInput.value =
            'Not created yet';


        updatedInput.value =
            'Not updated yet';


        saveText.textContent =
            'Save Announcement';

    }


    /*
    |--------------------------------------------------------------------------
    | EDIT
    |--------------------------------------------------------------------------
    */

    if (mode === 'edit') {

        if (!announcement) {

            return;
        }


        modalTitle.textContent =
            'Edit Announcement';


        modalSubtitle.textContent =
            'Existing announcement information — display only.';


        idInput.value =
            announcement.id ?? '';


        titleInput.value =
            announcement.title ?? '';


        contentInput.value =
            announcement.content ?? '';


        statusInput.value =
            String(
                announcement.status ?? 0
            );


        createdInput.value =
            formatAnnouncementDate(
                announcement.created_at
            );


        updatedInput.value =
            formatAnnouncementDate(
                announcement.updated_at
            );


        saveText.textContent =
            'Save Changes';

    }


    /*
    |--------------------------------------------------------------------------
    | OPEN
    |--------------------------------------------------------------------------
    */

    modal.hidden =
        false;


    document.body.style.overflow =
        'hidden';
}



function closeAnnouncementModal()
{
    const modal =
        document.getElementById(
            'announcement-modal'
        );


    if (!modal) {

        return;
    }


    modal.hidden =
        true;


    document.body.style.overflow =
        '';
}



/*
|--------------------------------------------------------------------------
| Date formatting
|--------------------------------------------------------------------------
*/

function formatAnnouncementDate(value)
{
    if (!value) {

        return '-';
    }


    const date =
        new Date(
            value
        );


    if (Number.isNaN(date.getTime())) {

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
| Save button
|--------------------------------------------------------------------------
|
| INTENTIONALLY DOES NOTHING.
|
| The button is clickable so the UI behaves normally,
| but no INSERT / UPDATE query is executed.
|
*/

function displayOnlyAnnouncementSave()
{
    /*
     * Intentionally empty.
     *
     * DO NOT add fetch(), AJAX, POST or SQL logic here.
     *
     * Corporate database must remain untouched.
     */

    return;
}



/*
|--------------------------------------------------------------------------
| Close when clicking outside modal
|--------------------------------------------------------------------------
*/

document
    .getElementById('announcement-modal')
    ?.addEventListener(
        'click',
        function(event) {

            if (event.target === this) {

                closeAnnouncementModal();
            }

        }
    );



/*
|--------------------------------------------------------------------------
| Escape key
|--------------------------------------------------------------------------
*/

document.addEventListener(
    'keydown',
    function(event) {

        if (
            event.key === 'Escape'
        ) {

            const modal =
                document.getElementById(
                    'announcement-modal'
                );


            if (
                modal &&
                !modal.hidden
            ) {

                closeAnnouncementModal();
            }

        }

    }
);

</script>



<!-- ================================================================
     ANNOUNCEMENT MODAL CSS
     Uses the SAME USER POPUP classes.
================================================================ -->

<style>

/*
|--------------------------------------------------------------------------
| Announcement popup
|--------------------------------------------------------------------------
|
| Keep the exact same base structure as User popup.
|--------------------------------------------------------------------------
*/

.announcement-popup {

    width: min(
        1080px,
        calc(100vw - 48px)
    );

    max-width: 1080px;

}


/*
|--------------------------------------------------------------------------
| Make announcement textarea behave like popup fields
|--------------------------------------------------------------------------
*/

.announcement-popup textarea {

    width: 100%;

    box-sizing: border-box;

    min-height: 150px;

    resize: vertical;

    border: 1px solid #cbd5e1;

    border-radius: 7px;

    padding: 12px 14px;

    font-family: inherit;

    font-size: 16px;

    color: #334155;

    background: #ffffff;

    outline: none;

}


.announcement-popup textarea:focus {

    border-color: #0b477f;

    box-shadow:
        0 0 0 2px
        rgba(
            11,
            71,
            127,
            0.08
        );

}


/*
|--------------------------------------------------------------------------
| Editable announcement fields
|--------------------------------------------------------------------------
|
| Unlike the User edit modal, Title / Content / Status are intentionally
| enabled because you asked to be able to type/select values.
|--------------------------------------------------------------------------
*/

.announcement-popup
input:not([disabled]),

.announcement-popup
select,

.announcement-popup
textarea {

    cursor: text;

}


/*
|--------------------------------------------------------------------------
| Disabled fields
|--------------------------------------------------------------------------
*/

.announcement-popup
input[disabled] {

    cursor: default;

}


/*
|--------------------------------------------------------------------------
| Responsive
|--------------------------------------------------------------------------
*/

@media (max-width: 800px) {

    .announcement-popup {

        width: calc(
            100vw - 24px
        );

    }

}

</style>