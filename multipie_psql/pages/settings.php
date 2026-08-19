<?php

/*
|--------------------------------------------------------------------------
| Settings
|--------------------------------------------------------------------------
|
| Application settings come from:
|   multipie_main_prod.public.settings
|
| User email is resolved separately from:
|   multipie_auth_prod.public.users
|
| There is NO cross-database SQL JOIN.
|
| Edit/Delete/Save are intentionally non-functional because this page
| is currently being used against corporate data.
|--------------------------------------------------------------------------
*/

$settings = [];
$totalSettings = 0;
$totalPages = 1;
$currentPage = max(1, (int)($_GET['p'] ?? 1));
$perPage = 10;
$dbError = null;

$keyFilter = trim((string)($_GET['key'] ?? ''));
$userEmailFilter = trim((string)($_GET['user_email'] ?? ''));

$userIdsForFilter = [];

/*
|--------------------------------------------------------------------------
| Load matching user IDs for User Email filter
|--------------------------------------------------------------------------
*/

try {

    if ($userEmailFilter !== '') {

        $usersDb = getUsersDb();

        $userStmt = $usersDb->prepare("
            SELECT id
            FROM public.users
            WHERE email = :email
        ");

        $userStmt->execute([
            ':email' => $userEmailFilter
        ]);

        $userIdsForFilter = array_map(
            'intval',
            $userStmt->fetchAll(PDO::FETCH_COLUMN)
        );
    }

} catch (Throwable $e) {

    $dbError = $e->getMessage();

}


/*
|--------------------------------------------------------------------------
| Load settings from application database
|--------------------------------------------------------------------------
*/

if ($dbError === null) {

    try {

        $appDb = getAppDb();

        $where = [];
        $params = [];

        /*
         * Key starts with
         */
        if ($keyFilter !== '') {

            $where[] = 's.key ILIKE :key';

            $params[':key'] =
                $keyFilter . '%';
        }


        /*
         * User Email equals
         *
         * We already resolved email -> user IDs using auth DB.
         * Now filter settings in main DB using those IDs.
         */
        if ($userEmailFilter !== '') {

            if (!$userIdsForFilter) {

                /*
                 * No matching user means no settings can match.
                 */
                $where[] = '1 = 0';

            } else {

                $placeholders = [];

                foreach (
                    $userIdsForFilter
                    as $index => $userId
                ) {

                    $placeholder =
                        ':user_id_' . $index;

                    $placeholders[] =
                        $placeholder;

                    $params[$placeholder] =
                        $userId;
                }

                $where[] =
                    's.user_id IN (' .
                    implode(
                        ', ',
                        $placeholders
                    ) .
                    ')';
            }
        }


        $whereSql = '';

        if ($where) {

            $whereSql =
                'WHERE ' .
                implode(
                    ' AND ',
                    $where
                );
        }


        /*
         * Total records
         */
        $countSql = "
            SELECT COUNT(*)
            FROM public.settings s
            {$whereSql}
        ";

        $countStmt =
            $appDb->prepare(
                $countSql
            );

        foreach (
            $params as $name => $value
        ) {

            $countStmt->bindValue(
                $name,
                $value,
                is_int($value)
                    ? PDO::PARAM_INT
                    : PDO::PARAM_STR
            );
        }

        $countStmt->execute();

        $totalSettings =
            (int)$countStmt->fetchColumn();


        $totalPages = max(
            1,
            (int)ceil(
                $totalSettings /
                $perPage
            )
        );

        if (
            $currentPage >
            $totalPages
        ) {

            $currentPage =
                $totalPages;
        }


        $offset =
            ($currentPage - 1) *
            $perPage;


        /*
         * Settings data
         */
        $sql = "
            SELECT
                s.id,
                s.setting_type,
                s.user_id,
                s.key,
                s.value,
                s.created_at,
                s.updated_at,
                s.status
            FROM public.settings s
            {$whereSql}
            ORDER BY s.id DESC
            LIMIT :limit
            OFFSET :offset
        ";

        $stmt =
            $appDb->prepare($sql);


        foreach (
            $params as $name => $value
        ) {

            $stmt->bindValue(
                $name,
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

        $settings =
            $stmt->fetchAll(
                PDO::FETCH_ASSOC
            );


        /*
         * Resolve emails separately from auth DB.
         */
        $userIds = [];

        foreach (
            $settings as $setting
        ) {

            if (
                $setting['user_id'] !== null &&
                $setting['user_id'] !== ''
            ) {

                $userIds[] =
                    (int)$setting['user_id'];
            }
        }

        $userIds =
            array_values(
                array_unique($userIds)
            );


        $userEmails = [];

        if ($userIds) {

            $usersDb =
                getUsersDb();

            $emailPlaceholders = [];
            $emailParams = [];

            foreach (
                $userIds as $index => $userId
            ) {

                $placeholder =
                    ':uid_' . $index;

                $emailPlaceholders[] =
                    $placeholder;

                $emailParams[$placeholder] =
                    $userId;
            }


            $emailSql = "
                SELECT
                    id,
                    email
                FROM public.users
                WHERE id IN (
                    " .
                    implode(
                        ', ',
                        $emailPlaceholders
                    ) .
                    "
                )
            ";

            $emailStmt =
                $usersDb->prepare(
                    $emailSql
                );

            foreach (
                $emailParams as $name => $value
            ) {

                $emailStmt->bindValue(
                    $name,
                    $value,
                    PDO::PARAM_INT
                );
            }

            $emailStmt->execute();

            foreach (
                $emailStmt->fetchAll(
                    PDO::FETCH_ASSOC
                ) as $user
            ) {

                $userEmails[
                    (int)$user['id']
                ] =
                    $user['email'] ?? '';
            }
        }


        /*
         * Attach resolved email to each setting.
         */
        foreach (
            $settings as &$setting
        ) {

            $uid =
                $setting['user_id'] !== null
                    ? (int)$setting['user_id']
                    : null;

            $setting['user_email'] =
                $uid !== null
                    ? ($userEmails[$uid] ?? '')
                    : '';
        }

        unset($setting);


    } catch (Throwable $e) {

        $dbError =
            $e->getMessage();

        $settings = [];
        $totalSettings = 0;
        $totalPages = 1;
    }
}


/*
|--------------------------------------------------------------------------
| Helpers
|--------------------------------------------------------------------------
*/

function settingsFormatDate($value): string
{
    if (!$value) {
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


function settingsValue($value): string
{
    if (
        $value === null ||
        $value === ''
    ) {

        return '-';
    }

    /*
     * PostgreSQL JSON values normally arrive
     * as strings through PDO.
     */
    if (is_string($value)) {

        $decoded =
            json_decode(
                $value,
                true
            );

        if (
            json_last_error() ===
            JSON_ERROR_NONE
        ) {

            return json_encode(
                $decoded,
                JSON_PRETTY_PRINT |
                JSON_UNESCAPED_SLASHES |
                JSON_UNESCAPED_UNICODE
            );
        }

        return $value;
    }

    return json_encode(
        $value,
        JSON_PRETTY_PRINT |
        JSON_UNESCAPED_SLASHES |
        JSON_UNESCAPED_UNICODE
    );
}


function settingsQuery(array $overrides = []): string
{
    $query = [
        'page' =>
            'settings',

        'key' =>
            (string)(
                $_GET['key'] ?? ''
            ),

        'user_email' =>
            (string)(
                $_GET['user_email'] ?? ''
            ),

        'p' =>
            (string)(
                $_GET['p'] ?? ''
            ),
    ];


    foreach (
        $overrides as $key => $value
    ) {

        $query[$key] =
            $value;
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


$from =
    $totalSettings > 0
        ? (($currentPage - 1) *
            $perPage + 1)
        : 0;

$to =
    min(
        $currentPage *
        $perPage,
        $totalSettings
    );

?>

<section class="view-header">

    <div>

        <h1>

            List of Settings

            <span class="count-pill-navy">
                <?= number_format($totalSettings) ?>
                Total
            </span>

        </h1>

        <p class="sub">
            Manage MultiPie application settings and user-specific configuration.
        </p>

    </div>

</section>


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
        value="settings"
    >

    <div class="filter-controls">

        <div class="search-box">

            <svg class="icon">
                <use href="#i-search"/>
            </svg>

            <input
                name="key"
                type="text"
                value="<?= e($keyFilter) ?>"
                placeholder="Key starts with"
            >

        </div>


        <input
            class="settings-email-filter"
            name="user_email"
            type="text"
            value="<?= e($userEmailFilter) ?>"
            placeholder="User Email equals"
        >


        <button
            type="submit"
            class="btn btn-outline btn-sm"
        >
            Search
        </button>


        <a
            href="index.php?page=settings"
            class="btn btn-outline btn-sm"
        >
            View All
        </a>

    </div>

</form>


<!-- ============================================================
     COUNT
============================================================ -->

<div class="filter-count">

    Showing

    <b>
        <?= $from ?>
        to
        <?= $to ?>
    </b>

    of

    <span>
        <?= number_format($totalSettings) ?>
    </span>

    Settings

</div>


<!-- ============================================================
     TABLE
============================================================ -->

<div class="table-wrap">

    <table>

        <thead>

            <tr>

                <th>
                    Key
                </th>

                <th>
                    User ID
                </th>

                <th>
                    Value
                </th>

                <th>
                    User Email
                </th>

                <th>
                    Actions
                </th>

            </tr>

        </thead>


        <tbody>

        <?php if (!$settings): ?>

            <tr class="empty-row">

                <td colspan="5">

                    <?= $dbError
                        ? 'Unable to load settings from PostgreSQL.'
                        : 'No settings found matching the selected filters.'
                    ?>

                </td>

            </tr>

        <?php endif; ?>


        <?php foreach (
            $settings as $setting
        ): ?>

            <?php

            $valueText =
                settingsValue(
                    $setting['value'] ??
                    null
                );

            $valuePreview =
                preg_replace(
                    '/\s+/',
                    ' ',
                    $valueText
                );

            if (
                mb_strlen(
                    $valuePreview
                ) > 100
            ) {

                $valuePreview =
                    mb_substr(
                        $valuePreview,
                        0,
                        100
                    ) . '...';
            }

            ?>

            <tr>

                <td>
                    <?= e(
                        $setting['key'] ??
                        '-'
                    ) ?>
                </td>


                <td>
                    <?= e(
                        $setting['user_id'] ??
                        '-'
                    ) ?>
                </td>


                <td>

                    <span
                        class="settings-value-preview"
                        title="<?= e($valueText) ?>"
                    >
                        <?= e(
                            $valuePreview
                        ) ?>
                    </span>

                </td>


                <td>

                    <?= e(
                        $setting['user_email'] ??
                        '-'
                    ) ?>

                </td>


                <td>

                    <div class="row-actions">

                        <button
                            type="button"
                            class="mini-btn"
                            onclick='openSettingsModal(<?= json_encode(
                                $setting,
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
                            onclick="settingsDeleteDisabled()"
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
            $currentPage > 1
        ): ?>

            <a
                class="pagination-btn"
                href="index.php?<?= e(
                    settingsQuery([
                        'p' =>
                            $currentPage - 1
                    ])
                ) ?>"
            >
                Previous
            </a>

        <?php endif; ?>


        <?php

        $startPage =
            max(
                1,
                $currentPage - 2
            );

        $endPage =
            min(
                $totalPages,
                $currentPage + 2
            );

        for (
            $i = $startPage;
            $i <= $endPage;
            $i++
        ):

        ?>

            <a
                class="pagination-btn <?= $i === $currentPage
                    ? 'active'
                    : '' ?>"
                href="index.php?<?= e(
                    settingsQuery([
                        'p' => $i
                    ])
                ) ?>"
            >
                <?= $i ?>
            </a>

        <?php endfor; ?>


        <?php if (
            $currentPage <
            $totalPages
        ): ?>

            <a
                class="pagination-btn"
                href="index.php?<?= e(
                    settingsQuery([
                        'p' =>
                            $currentPage + 1
                    ])
                ) ?>"
            >
                Next
            </a>

        <?php endif; ?>

    </div>

<?php endif; ?>


<!-- ============================================================
     EDIT SETTING MODAL
============================================================ -->

<div
    id="settings-modal"
    class="user-popup-overlay"
    hidden
>

    <div
        class="user-popup settings-popup"
        role="dialog"
        aria-modal="true"
        aria-labelledby="settings-modal-title"
    >

        <div class="user-popup-header">

            <div>

                <h2 id="settings-modal-title">
                    Edit Setting
                </h2>

                <p>
                    Existing setting information — display only.
                </p>

            </div>


            <button
                type="button"
                class="user-popup-close"
                onclick="closeSettingsModal()"
                aria-label="Close"
            >
                &times;
            </button>

        </div>


        <div class="user-popup-grid">

            <div class="field">

                <label>
                    ID
                </label>

                <input
                    id="settings-modal-id"
                    type="text"
                    disabled
                >

            </div>


            <div class="field">

                <label>
                    Key
                </label>

                <input
                    id="settings-modal-key"
                    type="text"
                >

            </div>


            <div class="field">

                <label>
                    User ID
                </label>

                <input
                    id="settings-modal-user-id"
                    type="text"
                >

            </div>


            <div class="field">

                <label>
                    User Email
                </label>

                <input
                    id="settings-modal-email"
                    type="text"
                    disabled
                >

            </div>


            <div class="field">

                <label>
                    Setting Type
                </label>

                <input
                    id="settings-modal-type"
                    type="text"
                >

            </div>


            <div class="field">

                <label>
                    Status
                </label>

                <select id="settings-modal-status">

                    <option value="0">
                        Inactive
                    </option>

                    <option value="1">
                        Active
                    </option>

                </select>

            </div>


            <div
                class="field"
                style="grid-column: 1 / -1;"
            >

                <label>
                    Value
                </label>

                <textarea
                    id="settings-modal-value"
                    rows="9"
                    placeholder="JSON value"
                ></textarea>

            </div>


            <div class="field">

                <label>
                    Created
                </label>

                <input
                    id="settings-modal-created"
                    type="text"
                    disabled
                >

            </div>


            <div class="field">

                <label>
                    Updated
                </label>

                <input
                    id="settings-modal-updated"
                    type="text"
                    disabled
                >

            </div>

        </div>


        <div class="user-popup-footer">

            <button
                type="button"
                class="btn btn-outline"
                onclick="closeSettingsModal()"
            >
                Close
            </button>


            <button
                type="button"
                class="btn btn-orange"
                onclick="settingsSaveDisabled()"
            >
                Save Changes
            </button>

        </div>

    </div>

</div>


<style>

/*
|--------------------------------------------------------------------------
| Settings-specific styling
|--------------------------------------------------------------------------
|
| Layout/modal/button styling follows the same classes used throughout
| the existing MultiPie pages.
|--------------------------------------------------------------------------
*/

.settings-value-preview {

    display: block;

    max-width: 360px;

    white-space: nowrap;

    overflow: hidden;

    text-overflow: ellipsis;

}


.settings-popup textarea {

    width: 100%;

    min-height: 190px;

    resize: vertical;

    font-family:
        Consolas,
        "Courier New",
        monospace;

    line-height: 1.5;

}


.settings-email-filter {

    height: 38px;

    min-width: 220px;

    padding:
        0 12px;

    border:
        1px solid
        #cbd5e1;

    border-radius:
        6px;

    background:
        #fff;

    color:
        #334155;

    font-size:
        13px;

}


.settings-email-filter:focus {

    outline: none;

    border-color:
        #0b477f;

    box-shadow:
        0 0 0 2px
        rgba(
            11,
            71,
            127,
            0.08
        );

}

</style>


<script>

/*
|--------------------------------------------------------------------------
| EDIT SETTING MODAL
|--------------------------------------------------------------------------
*/

function openSettingsModal(setting)
{

    document.getElementById(
        'settings-modal-id'
    ).value =
        setting.id ?? '';


    document.getElementById(
        'settings-modal-key'
    ).value =
        setting.key ?? '';


    document.getElementById(
        'settings-modal-user-id'
    ).value =
        setting.user_id ?? '';


    document.getElementById(
        'settings-modal-email'
    ).value =
        setting.user_email ?? '';


    document.getElementById(
        'settings-modal-type'
    ).value =
        setting.setting_type ?? '';


    document.getElementById(
        'settings-modal-status'
    ).value =
        setting.status ?? '';


    let value =
        setting.value ?? '';


    /*
     * Pretty-print JSON in the popup.
     */
    try {

        if (
            typeof value ===
            'string'
        ) {

            const parsed =
                JSON.parse(value);

            value =
                JSON.stringify(
                    parsed,
                    null,
                    2
                );

        } else {

            value =
                JSON.stringify(
                    value,
                    null,
                    2
                );
        }

    } catch (error) {

        /*
         * Keep the original value if
         * it is not valid JSON.
         */

    }


    document.getElementById(
        'settings-modal-value'
    ).value =
        value;


    document.getElementById(
        'settings-modal-created'
    ).value =
        setting.created_at ?? '-';


    document.getElementById(
        'settings-modal-updated'
    ).value =
        setting.updated_at ?? '-';


    document.getElementById(
        'settings-modal'
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

function closeSettingsModal()
{

    document.getElementById(
        'settings-modal'
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
| Intentionally does not touch PostgreSQL.
|--------------------------------------------------------------------------
*/

function settingsSaveDisabled()
{

    alert(
        'Save Changes is currently disabled. No changes have been made to the database.'
    );
}


/*
|--------------------------------------------------------------------------
| DELETE
|--------------------------------------------------------------------------
|
| Intentionally does not touch PostgreSQL.
|--------------------------------------------------------------------------
*/

function settingsDeleteDisabled()
{

    alert(
        'Delete is currently disabled. No database record has been deleted.'
    );
}


/*
|--------------------------------------------------------------------------
| CLOSE ON BACKDROP
|--------------------------------------------------------------------------
*/

document.getElementById(
    'settings-modal'
)?.addEventListener(
    'click',
    function(event) {

        if (
            event.target === this
        ) {

            closeSettingsModal();

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
            event.key ===
            'Escape'
        ) {

            closeSettingsModal();

        }

    }
);

</script>
