<?php

/*
|--------------------------------------------------------------------------
| MULTIPIE AUTH - OAUTH APPLICATIONS
|--------------------------------------------------------------------------
|
| Database:
|   multipie_auth_prod
|
| Table:
|   public.oauth_applications
|
| Actions currently implemented:
|   - Add Application -> UI only
|   - Edit            -> Opens working edit modal
|   - Destroy         -> Button only
|
|--------------------------------------------------------------------------
*/


/*
|--------------------------------------------------------------------------
| PAGINATION
|--------------------------------------------------------------------------
*/

$perPage = 25;

$currentPage = max(
    1,
    (int)($_GET['p'] ?? 1)
);


/*
|--------------------------------------------------------------------------
| DETAIL VIEW
|--------------------------------------------------------------------------
*/

$applicationId = max(
    0,
    (int)($_GET['view'] ?? 0)
);


/*
|--------------------------------------------------------------------------
| DATABASE VARIABLES
|--------------------------------------------------------------------------
*/

$dbError = null;

$totalApplications = 0;

$totalPages = 1;

$applications = [];

$selectedApplication = null;


/*
|--------------------------------------------------------------------------
| DATABASE
|--------------------------------------------------------------------------
*/

try {

    /*
    |--------------------------------------------------------------------------
    | IMPORTANT:
    | Use the same Auth DB connection as auth_users.php
    |--------------------------------------------------------------------------
    */

    $pdo = getAuthDb();


    /*
    |--------------------------------------------------------------------------
    | APPLICATION DETAIL
    |--------------------------------------------------------------------------
    */

    if ($applicationId > 0) {

        $detailStmt = $pdo->prepare("
            SELECT
                id,
                name,
                uid,
                secret,
                redirect_uri,
                scopes,
                confidential,
                created_at,
                updated_at
            FROM public.oauth_applications
            WHERE id = :id
            LIMIT 1
        ");

        $detailStmt->bindValue(
            ':id',
            $applicationId,
            PDO::PARAM_INT
        );

        $detailStmt->execute();

        $selectedApplication =
            $detailStmt->fetch(PDO::FETCH_ASSOC);
    }


    /*
    |--------------------------------------------------------------------------
    | TOTAL APPLICATION COUNT
    |--------------------------------------------------------------------------
    */

    $countStmt = $pdo->query("
        SELECT COUNT(*)
        FROM public.oauth_applications
    ");

    $totalApplications =
        (int)$countStmt->fetchColumn();


    /*
    |--------------------------------------------------------------------------
    | PAGINATION
    |--------------------------------------------------------------------------
    */

    $totalPages = max(
        1,
        (int)ceil(
            $totalApplications / $perPage
        )
    );

    $currentPage = min(
        $currentPage,
        $totalPages
    );

    $offset =
        ($currentPage - 1) * $perPage;


    /*
    |--------------------------------------------------------------------------
    | FETCH APPLICATIONS
    |--------------------------------------------------------------------------
    */

    $stmt = $pdo->prepare("
        SELECT
            id,
            name,
            uid,
            secret,
            redirect_uri,
            scopes,
            confidential,
            created_at,
            updated_at
        FROM public.oauth_applications
        ORDER BY id DESC
        LIMIT :limit
        OFFSET :offset
    ");

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

    $applications =
        $stmt->fetchAll(PDO::FETCH_ASSOC);


} catch (Throwable $e) {

    $dbError =
        $e->getMessage();

    $totalApplications = 0;

    $totalPages = 1;

    $applications = [];

}


/*
|--------------------------------------------------------------------------
| HELPERS
|--------------------------------------------------------------------------
*/

function authApplicationEscape($value): string
{
    return htmlspecialchars(
        (string)$value,
        ENT_QUOTES,
        'UTF-8'
    );
}


/*
|--------------------------------------------------------------------------
| DATE FORMAT
|--------------------------------------------------------------------------
*/

function authApplicationFormatDate(
    $value
): string {

    if (
        empty($value)
    ) {
        return '-';
    }

    try {

        return (
            new DateTimeImmutable(
                (string)$value
            )
        )->format('d M Y');

    } catch (Throwable $e) {

        return (string)$value;

    }
}


/*
|--------------------------------------------------------------------------
| CALLBACK URLS
|--------------------------------------------------------------------------
*/

function authApplicationGetCallbackUrls(
    $value
): array {

    if (
        $value === null ||
        trim((string)$value) === ''
    ) {
        return [];
    }

    $urls = preg_split(
        '/\r\n|\r|\n/',
        (string)$value
    );

    return array_values(
        array_filter(
            $urls,
            static function ($url) {

                return trim(
                    (string)$url
                ) !== '';

            }
        )
    );
}


/*
|--------------------------------------------------------------------------
| APPLICATION DETAIL URL
|--------------------------------------------------------------------------
*/

function authApplicationDetailUrl(
    int $id
): string {

    return 'index.php?' .
        http_build_query([

            'sidebar' => 'auth',

            'page' =>
                'auth/auth_application',

            'view' => $id

        ]);
}


/*
|--------------------------------------------------------------------------
| APPLICATION LIST URL
|--------------------------------------------------------------------------
*/

function authApplicationListUrl(): string
{
    return 'index.php?' .
        http_build_query([

            'sidebar' => 'auth',

            'page' =>
                'auth/auth_application'

        ]);
}


/*
|--------------------------------------------------------------------------
| PAGINATION URL
|--------------------------------------------------------------------------
*/

function authApplicationPaginationUrl(
    int $page
): string {

    return 'index.php?' .
        http_build_query([

            'sidebar' => 'auth',

            'page' =>
                'auth/auth_application',

            'p' => $page

        ]);
}

?>


<?php if ($applicationId > 0): ?>


<!-- ================================================================
     APPLICATION DETAIL PAGE
     ================================================================ -->

<section class="view-header">

    <div>

        <h1>

            Application:

            <?= authApplicationEscape(
                $selectedApplication['name']
                    ?? 'Application'
            ) ?>

        </h1>

        <p class="sub">

            OAuth application details.

        </p>

    </div>


    <a
        href="<?= authApplicationListUrl() ?>"
        class="btn btn-outline"
    >

        ← Back to Applications

    </a>

</section>


<?php if ($dbError): ?>

    <div class="alert-box">

        <div class="row">

            <div>

                <h4>
                    PostgreSQL connection failed
                </h4>

                <p>
                    <?= authApplicationEscape(
                        $dbError
                    ) ?>
                </p>

            </div>

        </div>

    </div>


<?php elseif (!$selectedApplication): ?>

    <div class="alert-box">

        <div class="row">

            <div>

                <h4>
                    Application not found
                </h4>

                <p>
                    The requested application
                    could not be found.
                </p>

            </div>

        </div>

    </div>


<?php else: ?>


<!-- ================================================================
     APPLICATION DETAIL
     ================================================================ -->

<div class="table-wrap">

    <table>

        <tbody>

            <!-- UID -->

            <tr>

                <th>
                    UID:
                </th>

                <td>

                    <code>
                        <?= authApplicationEscape(
                            $selectedApplication['uid']
                                ?? '-'
                        ) ?>
                    </code>

                </td>

            </tr>


            <!-- SECRET -->

            <tr>

                <th>
                    Secret:
                </th>

                <td>

                    <code>
                        <?= authApplicationEscape(
                            $selectedApplication['secret']
                                ?? '-'
                        ) ?>
                    </code>

                </td>

            </tr>


            <!-- SCOPES -->

            <tr>

                <th>
                    Scopes:
                </th>

                <td>

                    <?php

                    $scopes = trim(
                        (string)(
                            $selectedApplication[
                                'scopes'
                            ] ?? ''
                        )
                    );

                    ?>

                    <?php if ($scopes !== ''): ?>

                        <code>

                            <?= authApplicationEscape(
                                $scopes
                            ) ?>

                        </code>

                    <?php else: ?>

                        -

                    <?php endif; ?>

                </td>

            </tr>


            <!-- CONFIDENTIAL -->

            <tr>

                <th>
                    Confidential:
                </th>

                <td>

                    <?php if (
                        !empty(
                            $selectedApplication[
                                'confidential'
                            ]
                        )
                    ): ?>

                        <span class="status-badge Active">

                            <span
                                class="dot-status Active"
                            ></span>

                            Yes

                        </span>

                    <?php else: ?>

                        <span class="status-badge Inactive">

                            <span
                                class="dot-status Inactive"
                            ></span>

                            No

                        </span>

                    <?php endif; ?>

                </td>

            </tr>


            <!-- CALLBACK URL -->

            <tr>

                <th>
                    Callback urls:
                </th>

                <td>

                    <?php

                    $callbackUrls =
                        authApplicationGetCallbackUrls(
                            $selectedApplication[
                                'redirect_uri'
                            ] ?? ''
                        );

                    ?>

                    <?php if ($callbackUrls): ?>

                        <?php foreach (
                            $callbackUrls
                            as $callbackUrl
                        ): ?>

                            <div
                                style="
                                    margin-bottom:8px;
                                "
                            >

                                <code>

                                    <?= authApplicationEscape(
                                        $callbackUrl
                                    ) ?>

                                </code>

                            </div>

                        <?php endforeach; ?>

                    <?php else: ?>

                        -

                    <?php endif; ?>

                </td>

            </tr>


            <!-- CREATED -->

            <tr>

                <th>
                    Created At:
                </th>

                <td>

                    <?= authApplicationEscape(
                        authApplicationFormatDate(
                            $selectedApplication[
                                'created_at'
                            ] ?? null
                        )
                    ) ?>

                </td>

            </tr>


            <!-- UPDATED -->

            <tr>

                <th>
                    Updated At:
                </th>

                <td>

                    <?= authApplicationEscape(
                        authApplicationFormatDate(
                            $selectedApplication[
                                'updated_at'
                            ] ?? null
                        )
                    ) ?>

                </td>

            </tr>


            <!-- ACTIONS -->

            <tr>

                <th>
                    Actions:
                </th>

                <td>

                    <div class="row-actions">


                        <!-- EDIT -->

                        <button
                            type="button"
                            class="mini-btn"
                            onclick='openApplicationModal(<?= json_encode(
                                $selectedApplication,
                                JSON_HEX_TAG |
                                JSON_HEX_APOS |
                                JSON_HEX_AMP |
                                JSON_HEX_QUOT
                            ) ?>)'
                        >

                            Edit

                        </button>


                        <!-- DESTROY -->

                        <button
                            type="button"
                            class="mini-btn danger"
                            onclick="applicationDestroyMessage()"
                        >

                            Destroy

                        </button>

                    </div>

                </td>

            </tr>


            <!-- AUTHORIZE -->

            <tr>

                <th>
                    Authorization:
                </th>

                <td>

                    <button
                        type="button"
                        class="btn btn-outline btn-sm"
                        onclick="applicationAuthorizeMessage()"
                    >

                        Authorize

                    </button>

                </td>

            </tr>

        </tbody>

    </table>

</div>


<?php endif; ?>


<?php else: ?>


<!-- ================================================================
     APPLICATION LIST HEADER
     ================================================================ -->

<section class="view-header">

    <div>

        <h1>

            OAuth Applications

            <span class="count-pill-navy">

                <?= number_format(
                    $totalApplications
                ) ?>

                Total

            </span>

        </h1>

        <p class="sub">

            Manage MultiPie OAuth applications.

        </p>

    </div>


    <button
        class="btn btn-orange"
        type="button"
        onclick="openAddApplicationModal()"
    >

        + Add Application

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
                    <?= authApplicationEscape(
                        $dbError
                    ) ?>
                </p>

            </div>

        </div>

    </div>

<?php endif; ?>


<!-- ================================================================
     COUNT
     ================================================================ -->

<div class="filter-count">

    Showing

    <b>

        <?= $totalApplications
            ? (
                (
                    $currentPage - 1
                ) * $perPage + 1
            )
            : 0
        ?>

        -

        <?= min(
            $currentPage * $perPage,
            $totalApplications
        ) ?>

    </b>

    of

    <span>

        <?= number_format(
            $totalApplications
        ) ?>

    </span>

    Applications

</div>


<!-- ================================================================
     APPLICATION TABLE
     ================================================================ -->

<div class="table-wrap">

    <table>

        <thead>

            <tr>

                <th>
                    Name
                </th>

                <th>
                    Callback URL
                </th>

                <th>
                    Confidential?
                </th>

                <th class="right">
                    Actions
                </th>

            </tr>

        </thead>


        <tbody>


        <?php if (!$applications): ?>

            <tr class="empty-row">

                <td colspan="4">

                    <?= $dbError
                        ? 'Unable to load applications from PostgreSQL.'
                        : 'No applications found.'
                    ?>

                </td>

            </tr>

        <?php endif; ?>


        <?php foreach (
            $applications
            as $application
        ): ?>


            <?php

            $callbackUrls =
                authApplicationGetCallbackUrls(
                    $application[
                        'redirect_uri'
                    ] ?? ''
                );

            ?>


            <tr>


                <!-- NAME -->

                <td>

                    <a
                        href="<?= authApplicationDetailUrl(
                            (int)$application['id']
                        ) ?>"
                        class="table-link"
                    >

                        <?= authApplicationEscape(
                            $application['name']
                                ?? '-'
                        ) ?>

                    </a>

                </td>


                <!-- CALLBACK URL -->

                <td>

                    <?php if ($callbackUrls): ?>

                        <?php foreach (
                            $callbackUrls
                            as $callbackUrl
                        ): ?>

                            <div
                                title="<?= authApplicationEscape(
                                    $callbackUrl
                                ) ?>"
                                style="
                                    max-width:650px;
                                    overflow:hidden;
                                    text-overflow:ellipsis;
                                    white-space:nowrap;
                                "
                            >

                                <?= authApplicationEscape(
                                    $callbackUrl
                                ) ?>

                            </div>

                        <?php endforeach; ?>

                    <?php else: ?>

                        -

                    <?php endif; ?>

                </td>


                <!-- CONFIDENTIAL -->

                <td>

                    <?php if (
                        !empty(
                            $application[
                                'confidential'
                            ]
                        )
                    ): ?>

                        <span class="status-badge Active">

                            <span
                                class="dot-status Active"
                            ></span>

                            Yes

                        </span>

                    <?php else: ?>

                        <span class="status-badge Inactive">

                            <span
                                class="dot-status Inactive"
                            ></span>

                            No

                        </span>

                    <?php endif; ?>

                </td>


                <!-- ACTIONS -->

                <td class="right">

                    <div class="row-actions">


                        <!-- EDIT -->

                        <button
                            type="button"
                            class="mini-btn"
                            onclick='openApplicationModal(<?= json_encode(
                                $application,
                                JSON_HEX_TAG |
                                JSON_HEX_APOS |
                                JSON_HEX_AMP |
                                JSON_HEX_QUOT
                            ) ?>)'
                        >

                            Edit

                        </button>


                        <!-- DESTROY -->

                        <button
                            type="button"
                            class="mini-btn danger"
                            onclick="applicationDestroyMessage()"
                        >

                            Destroy

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

<?php if (
    $totalPages > 1
): ?>

    <div class="pagination">


        <?php if (
            $currentPage > 1
        ): ?>

            <a
                class="btn btn-outline btn-sm"
                href="<?= authApplicationPaginationUrl(
                    $currentPage - 1
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


        <?php if (
            $currentPage < $totalPages
        ): ?>

            <a
                class="btn btn-outline btn-sm"
                href="<?= authApplicationPaginationUrl(
                    $currentPage + 1
                ) ?>"
            >

                Next

            </a>

        <?php endif; ?>


    </div>

<?php endif; ?>


<!-- ================================================================
     ADD APPLICATION MODAL
     SAME UI STRUCTURE AS AUTH_USERS.PHP
     ================================================================ -->

<div
    id="add-application-modal"
    class="user-popup-overlay"
    hidden
>

    <div
        class="user-popup"
        role="dialog"
        aria-modal="true"
        aria-labelledby="add-application-modal-title"
    >


        <!-- HEADER -->

        <div class="user-popup-header">

            <div>

                <h2 id="add-application-modal-title">

                    New Application

                </h2>

                <p>

                    Register a new OAuth application.

                </p>

            </div>


            <button
                type="button"
                class="user-popup-close"
                onclick="closeAddApplicationModal()"
                aria-label="Close"
            >

                &times;

            </button>

        </div>


        <!-- FORM -->

        <div class="user-popup-grid">


            <!-- NAME -->

            <div class="field">

                <label for="application-name">

                    Name

                </label>

                <input
                    id="application-name"
                    type="text"
                    placeholder="Application name"
                >

            </div>


            <!-- REDIRECT URI -->

            <div
                class="field"
                style="grid-column:1 / -1;"
            >

                <label for="application-redirect-uri">

                    Redirect URI

                </label>

                <textarea
                    id="application-redirect-uri"
                    rows="4"
                    placeholder="Use one line per URI"
                ></textarea>

                <small>

                    Use one line per URI.

                </small>

            </div>


            <!-- CONFIDENTIAL -->

            <div class="field">

                <label for="application-confidential">

                    Confidential

                </label>

                <select
                    id="application-confidential"
                >

                    <option value="1">
                        Yes
                    </option>

                    <option value="0">
                        No
                    </option>

                </select>

            </div>


            <!-- SCOPES -->

            <div class="field">

                <label for="application-scopes">

                    Scopes

                </label>

                <input
                    id="application-scopes"
                    type="text"
                    placeholder="multipie_internal"
                >

                <small>

                    Separate scopes with spaces.
                    Leave blank to use the default scopes.

                </small>

            </div>


        </div>


        <!-- FOOTER -->

        <div class="user-popup-footer">

            <button
                type="button"
                class="btn btn-outline"
                onclick="closeAddApplicationModal()"
            >

                Cancel

            </button>


            <button
                type="button"
                class="btn btn-navy"
                onclick="applicationSubmitMessage()"
            >

                Submit

            </button>

        </div>

    </div>

</div>


<!-- ================================================================
     EDIT APPLICATION MODAL
     SAME UI AS AUTH_USERS.PHP
     ================================================================ -->

<div
    id="application-modal"
    class="user-popup-overlay"
    hidden
>

    <div
        class="user-popup"
        role="dialog"
        aria-modal="true"
        aria-labelledby="application-modal-title"
    >


        <!-- HEADER -->

        <div class="user-popup-header">

            <div>

                <h2 id="application-modal-title">

                    Edit Application

                </h2>

                <p>

                    Existing application information
                    — display only.

                </p>

            </div>


            <button
                type="button"
                class="user-popup-close"
                onclick="closeApplicationModal()"
                aria-label="Close"
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
                    id="application-modal-id"
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
                    id="application-modal-name"
                    type="text"
                >

            </div>


            <!-- UID -->

            <div class="field">

                <label>
                    UID
                </label>

                <input
                    id="application-modal-uid"
                    type="text"

                >

            </div>


            <!-- CONFIDENTIAL -->

            <div class="field">

                <label>
                    Confidential
                </label>

                <input
                    id="application-modal-confidential"
                    type="text"
                >

            </div>


            <!-- SCOPES -->

            <div class="field">

                <label>
                    Scopes
                </label>

                <input
                    id="application-modal-scopes"
                    type="text"
                >

            </div>


            <!-- CREATED -->

            <div class="field">

                <label>
                    Created
                </label>

                <input
                    id="application-modal-created"
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
                    id="application-modal-updated"
                    type="text"
                    disabled
                >

            </div>


            <!-- SECRET -->

            <div class="field">

                <label>
                    Secret
                </label>

                <input
                    id="application-modal-secret"
                    type="text"
                >

            </div>


            <!-- REDIRECT URI -->

            <div
                class="field"
                style="grid-column:1 / -1;"
            >

                <label>
                    Redirect URI
                </label>

                <textarea
                    id="application-modal-redirect-uri"
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
                onclick="closeApplicationModal()"
            >

                Close

            </button>


            <button
                type="button"
                class="btn btn-navy"
                disabled
                title="Save is intentionally disabled for now."
            >

                Save Changes

            </button>

        </div>

    </div>

</div>


<?php endif; ?>


<!-- ================================================================
     JAVASCRIPT
     IMPORTANT:
     Keep this OUTSIDE the list/detail conditional so Edit works
     from BOTH the application list and application detail page.
     ================================================================ -->

<script>

/*
|--------------------------------------------------------------------------
| GENERIC MODAL TOGGLE
|--------------------------------------------------------------------------
*/

function authApplicationToggleModal(
    modal,
    show
) {

    if (!modal) {
        return;
    }

    modal.hidden = !show;

    document.body.style.overflow =
        show
            ? 'hidden'
            : '';

}


/*
|--------------------------------------------------------------------------
| ADD APPLICATION
|--------------------------------------------------------------------------
*/

function openAddApplicationModal()
{

    const modal =
        document.getElementById(
            'add-application-modal'
        );

    if (!modal) {
        return;
    }


    const name =
        document.getElementById(
            'application-name'
        );

    const redirect =
        document.getElementById(
            'application-redirect-uri'
        );

    const confidential =
        document.getElementById(
            'application-confidential'
        );

    const scopes =
        document.getElementById(
            'application-scopes'
        );


    if (name) {
        name.value = '';
    }

    if (redirect) {
        redirect.value = '';
    }

    if (confidential) {
        confidential.value = '1';
    }

    if (scopes) {
        scopes.value = '';
    }


    authApplicationToggleModal(
        modal,
        true
    );

}


/*
|--------------------------------------------------------------------------
| CLOSE ADD APPLICATION
|--------------------------------------------------------------------------
*/

function closeAddApplicationModal()
{

    const modal =
        document.getElementById(
            'add-application-modal'
        );

    authApplicationToggleModal(
        modal,
        false
    );

}


/*
|--------------------------------------------------------------------------
| EDIT APPLICATION
|
| Same pattern as auth_users.php:
|
| onclick='openApplicationModal(<?= json_encode($application) ?>)'
|
|--------------------------------------------------------------------------
*/

function openApplicationModal(
    application
) {

    const modal =
        document.getElementById(
            'application-modal'
        );

    if (!modal) {

        console.error(
            'Application edit modal not found.'
        );

        return;

    }


    /*
    |--------------------------------------------------------------------------
    | ID
    |--------------------------------------------------------------------------
    */

    const id =
        document.getElementById(
            'application-modal-id'
        );

    if (id) {

        id.value =
            application.id ??
            '';

    }


    /*
    |--------------------------------------------------------------------------
    | NAME
    |--------------------------------------------------------------------------
    */

    const name =
        document.getElementById(
            'application-modal-name'
        );

    if (name) {

        name.value =
            application.name ??
            '';

    }


    /*
    |--------------------------------------------------------------------------
    | UID
    |--------------------------------------------------------------------------
    */

    const uid =
        document.getElementById(
            'application-modal-uid'
        );

    if (uid) {

        uid.value =
            application.uid ??
            '';

    }


    /*
    |--------------------------------------------------------------------------
    | SECRET
    |--------------------------------------------------------------------------
    */

    const secret =
        document.getElementById(
            'application-modal-secret'
        );

    if (secret) {

        secret.value =
            application.secret ??
            '';

    }


    /*
    |--------------------------------------------------------------------------
    | SCOPES
    |--------------------------------------------------------------------------
    */

    const scopes =
        document.getElementById(
            'application-modal-scopes'
        );

    if (scopes) {

        scopes.value =
            application.scopes ??
            '';

    }


    /*
    |--------------------------------------------------------------------------
    | CONFIDENTIAL
    |--------------------------------------------------------------------------
    */

    const confidential =
        document.getElementById(
            'application-modal-confidential'
        );

    if (confidential) {

        confidential.value =
            (
                application.confidential == 1 ||
                application.confidential === true
            )
                ? 'Yes'
                : 'No';

    }


    /*
    |--------------------------------------------------------------------------
    | CREATED
    |--------------------------------------------------------------------------
    */

    const created =
        document.getElementById(
            'application-modal-created'
        );

    if (created) {

        created.value =
            application.created_at ??
            '';

    }


    /*
    |--------------------------------------------------------------------------
    | UPDATED
    |--------------------------------------------------------------------------
    */

    const updated =
        document.getElementById(
            'application-modal-updated'
        );

    if (updated) {

        updated.value =
            application.updated_at ??
            '';

    }


    /*
    |--------------------------------------------------------------------------
    | REDIRECT URI
    |--------------------------------------------------------------------------
    */

    const redirect =
        document.getElementById(
            'application-modal-redirect-uri'
        );

    if (redirect) {

        redirect.value =
            application.redirect_uri ??
            '';

    }


    /*
    |--------------------------------------------------------------------------
    | OPEN MODAL
    |--------------------------------------------------------------------------
    */

    authApplicationToggleModal(
        modal,
        true
    );

}


/*
|--------------------------------------------------------------------------
| CLOSE EDIT APPLICATION
|--------------------------------------------------------------------------
*/

function closeApplicationModal()
{

    const modal =
        document.getElementById(
            'application-modal'
        );

    authApplicationToggleModal(
        modal,
        false
    );

}


/*
|--------------------------------------------------------------------------
| DESTROY
|--------------------------------------------------------------------------
*/

function applicationDestroyMessage()
{

    alert(
        'Destroy action is currently display-only.'
    );

}


/*
|--------------------------------------------------------------------------
| AUTHORIZE
|--------------------------------------------------------------------------
*/

function applicationAuthorizeMessage()
{

    alert(
        'Authorize action is currently display-only.'
    );

}


/*
|--------------------------------------------------------------------------
| SUBMIT
|--------------------------------------------------------------------------
*/

function applicationSubmitMessage()
{

    alert(
        'Application creation is currently display-only. No data was saved.'
    );

}


/*
|--------------------------------------------------------------------------
| BACKDROP CLICK
|--------------------------------------------------------------------------
*/

const addApplicationModal =
    document.getElementById(
        'add-application-modal'
    );

if (addApplicationModal) {

    addApplicationModal.addEventListener(
        'click',
        function(event) {

            if (
                event.target ===
                addApplicationModal
            ) {

                closeAddApplicationModal();

            }

        }
    );

}


const applicationModal =
    document.getElementById(
        'application-modal'
    );

if (applicationModal) {

    applicationModal.addEventListener(
        'click',
        function(event) {

            if (
                event.target ===
                applicationModal
            ) {

                closeApplicationModal();

            }

        }
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

        if (
            event.key !== 'Escape'
        ) {

            return;

        }

        closeAddApplicationModal();

        closeApplicationModal();

    }
);

</script>