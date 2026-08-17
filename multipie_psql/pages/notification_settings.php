<?php

$perPage = 20;
$pageNumber = max(1, (int)($_GET['p'] ?? 1));

$identifierFilter =
    trim((string)($_GET['identifier'] ?? ''));

$notificationSettings = [];
$totalNotificationSettings = 0;
$totalPages = 1;
$dbError = null;


try {

    $pdo = getAppDb();

    $where = [];
    $params = [];


    /*
    |--------------------------------------------------------------------------
    | Identifier contains
    |--------------------------------------------------------------------------
    */

    if ($identifierFilter !== '') {

        $where[] =
            'identifier ILIKE :identifier';

        $params[':identifier'] =
            '%' . $identifierFilter . '%';
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
        FROM public.notification_settings
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

    $totalNotificationSettings =
        (int)$countStmt->fetchColumn();


    /*
    |--------------------------------------------------------------------------
    | PAGINATION
    |--------------------------------------------------------------------------
    */

    $totalPages = max(
        1,
        (int)ceil(
            $totalNotificationSettings /
            $perPage
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
    | FETCH
    |--------------------------------------------------------------------------
    */

    $sql = "
        SELECT
            id,
            identifier,
            description,
            user_id,
            send_flag,
            user_modifiable,
            created_at,
            updated_at,
            status
        FROM public.notification_settings
        {$whereSql}
        ORDER BY
            identifier ASC NULLS LAST,
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


    $notificationSettings =
        $stmt->fetchAll(
            PDO::FETCH_ASSOC
        );


} catch (Throwable $e) {

    $dbError =
        $e->getMessage();
}


/*
|--------------------------------------------------------------------------
| JSON display helper
|--------------------------------------------------------------------------
*/

function notificationJsonDisplay($value): string
{
    if (
        $value === null ||
        $value === ''
    ) {
        return '-';
    }


    if (is_array($value)) {

        return json_encode(
            $value,
            JSON_UNESCAPED_SLASHES |
            JSON_UNESCAPED_UNICODE
        );
    }


    $decoded =
        json_decode(
            (string)$value,
            true
        );


    if (
        json_last_error() ===
        JSON_ERROR_NONE
    ) {

        return json_encode(
            $decoded,
            JSON_UNESCAPED_SLASHES |
            JSON_UNESCAPED_UNICODE
        );
    }


    return (string)$value;
}


/*
|--------------------------------------------------------------------------
| Pagination URL
|--------------------------------------------------------------------------
*/

function notificationPageUrl(
    int $page,
    string $identifier
): string {

    $params = [
        'page' => 'notification_settings',
        'p' => $page
    ];


    if ($identifier !== '') {

        $params['identifier'] =
            $identifier;
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

            Notification Settings

            <span class="count-pill-navy">
                <?= number_format(
                    $totalNotificationSettings
                ) ?>
                Total
            </span>

        </h1>

        <p class="sub">
            Manage MultiPie notification settings.
        </p>

    </div>


    <button
        type="button"
        class="btn btn-orange"
        onclick="openNotificationAddModal()"
    >
        + Add New Notification
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
        value="notification_settings"
    >


    <div class="filter-controls">

        <input
            type="text"
            name="identifier"
            placeholder="Identifier contains"
            value="<?= e(
                $identifierFilter
            ) ?>"
        >


        <button
            type="submit"
            class="btn btn-outline btn-sm"
        >
            Filter
        </button>


        <a
            href="index.php?page=notification_settings"
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
        <?= $totalNotificationSettings > 0
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
            $totalNotificationSettings
        ) ?>
    </b>

    of

    <span>
        <?= number_format(
            $totalNotificationSettings
        ) ?>
    </span>

    Notification Settings

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
                    Name
                </th>

                <th>
                    Description
                </th>

                <th>
                    Send Flag
                </th>

                <th>
                    User Modifiable
                </th>

                <th>
                    Actions
                </th>

            </tr>

        </thead>


        <tbody>

        <?php if (
            empty($notificationSettings)
        ): ?>

            <tr class="empty-row">

                <td colspan="6">

                    No Notification Settings
                    found.

                </td>

            </tr>

        <?php endif; ?>


        <?php foreach (
            $notificationSettings
            as $notification
        ): ?>

            <tr>


                <!-- IDENTIFIER -->

                <td>

                    <?= e(
                        $notification[
                            'identifier'
                        ] ?? '-'
                    ) ?>

                </td>


                <!-- NAME -->

                <td>

                    <?= e(
                        $notification[
                            'identifier'
                        ] ?? '-'
                    ) ?>

                </td>


                <!-- DESCRIPTION -->

                <td>

                    <?= e(
                        $notification[
                            'description'
                        ] ?? '-'
                    ) ?>

                </td>


                <!-- SEND FLAG -->

                <td>

                    <?= e(
                        notificationJsonDisplay(
                            $notification[
                                'send_flag'
                            ] ?? null
                        )
                    ) ?>

                </td>


                <!-- USER MODIFIABLE -->

                <td>

                    <?= e(
                        notificationJsonDisplay(
                            $notification[
                                'user_modifiable'
                            ] ?? null
                        )
                    ) ?>

                </td>


                <!-- ACTIONS -->

                <td>

                    <div class="table-actions">

                        <button
                            type="button"
                            class="mini-btn"
                            onclick='openNotificationEditModal(
                                <?= json_encode(
                                    $notification,
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
                            onclick="notificationDeleteDisabled()"
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
                    notificationPageUrl(
                        $pageNumber - 1,
                        $identifierFilter
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
                    notificationPageUrl(
                        $i,
                        $identifierFilter
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
                    notificationPageUrl(
                        $pageNumber + 1,
                        $identifierFilter
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
     ADD / EDIT POPUP
============================================================ -->

<div
    id="notification-modal"
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

                <h2 id="notification-modal-title">
                    Add New Notification
                </h2>

                <p>
                    Enter notification information
                    for display only.
                </p>

            </div>


            <button
                type="button"
                class="user-popup-close"
                onclick="closeNotificationModal()"
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
                    id="notification-id"
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
                    id="notification-identifier"
                    type="text"
                    placeholder="Enter identifier"
                >

            </div>


            <!-- NAME -->

            <div class="field">

                <label>
                    Name
                </label>

                <input
                    id="notification-name"
                    type="text"
                    placeholder="Enter name"
                >

            </div>


            <!-- USER ID -->

            <div class="field">

                <label>
                    User ID
                </label>

                <input
                    id="notification-user-id"
                    type="number"
                    placeholder="Enter user ID"
                >

            </div>


            <!-- STATUS -->

            <div class="field">

                <label>
                    Status
                </label>

                <select
                    id="notification-status"
                >

                    <option value="0">
                        Active
                    </option>

                    <option value="1">
                        Inactive
                    </option>

                </select>

            </div>


            <!-- SEND FLAG -->

            <div class="field">

                <label>
                    Send Flag
                </label>

                <textarea
                    id="notification-send-flag"
                    rows="3"
                    placeholder='{"email": true}'
                ></textarea>

            </div>


            <!-- USER MODIFIABLE -->

            <div class="field">

                <label>
                    User Modifiable
                </label>

                <textarea
                    id="notification-user-modifiable"
                    rows="3"
                    placeholder='{"email": true}'
                ></textarea>

            </div>


            <!-- CREATED -->

            <div class="field">

                <label>
                    Created
                </label>

                <input
                    id="notification-created"
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
                    id="notification-updated"
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
                    id="notification-description"
                    rows="4"
                    placeholder="Enter description"
                ></textarea>

            </div>

        </div>


        <!-- FOOTER -->

        <div class="user-popup-footer">

            <button
                type="button"
                class="btn btn-outline"
                onclick="closeNotificationModal()"
            >
                Close
            </button>


            <button
                type="button"
                class="btn btn-orange"
                onclick="notificationSaveDisabled()"
            >
                Save Notification
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

function openNotificationAddModal()
{
    document.getElementById(
        'notification-modal-title'
    ).textContent =
        'Add New Notification';


    document.getElementById(
        'notification-id'
    ).value =
        'Auto generated';


    document.getElementById(
        'notification-identifier'
    ).value =
        '';


    document.getElementById(
        'notification-name'
    ).value =
        '';


    document.getElementById(
        'notification-user-id'
    ).value =
        '';


    document.getElementById(
        'notification-status'
    ).value =
        '0';


    document.getElementById(
        'notification-send-flag'
    ).value =
        '';


    document.getElementById(
        'notification-user-modifiable'
    ).value =
        '';


    document.getElementById(
        'notification-created'
    ).value =
        'Not created yet';


    document.getElementById(
        'notification-updated'
    ).value =
        'Not updated yet';


    document.getElementById(
        'notification-description'
    ).value =
        '';


    document.getElementById(
        'notification-modal'
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

function openNotificationEditModal(
    notification
)
{
    document.getElementById(
        'notification-modal-title'
    ).textContent =
        'Edit Notification';


    document.getElementById(
        'notification-id'
    ).value =
        notification.id ?? '';


    document.getElementById(
        'notification-identifier'
    ).value =
        notification.identifier ?? '';


    document.getElementById(
        'notification-name'
    ).value =
        notification.identifier ?? '';


    document.getElementById(
        'notification-user-id'
    ).value =
        notification.user_id ?? '';


    document.getElementById(
        'notification-status'
    ).value =
        notification.status ?? '0';


    document.getElementById(
        'notification-send-flag'
    ).value =
        notification.send_flag ?? '';


    document.getElementById(
        'notification-user-modifiable'
    ).value =
        notification.user_modifiable ?? '';


    document.getElementById(
        'notification-created'
    ).value =
        notification.created_at ?? '';


    document.getElementById(
        'notification-updated'
    ).value =
        notification.updated_at ?? '';


    document.getElementById(
        'notification-description'
    ).value =
        notification.description ?? '';


    document.getElementById(
        'notification-modal'
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

function closeNotificationModal()
{
    document.getElementById(
        'notification-modal'
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

function notificationSaveDisabled()
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

function notificationDeleteDisabled()
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

            closeNotificationModal();

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
        'notification-modal'
    )
    ?.addEventListener(
        'click',
        function(event)
        {

            if (
                event.target === this
            ) {

                closeNotificationModal();

            }

        }
    );

</script>