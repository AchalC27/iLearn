<?php
/*
|--------------------------------------------------------------------------
| MultiPie Auth Server - Settings
|--------------------------------------------------------------------------
|
| Database:
|   multipie_auth_prod
|
| Tables:
|   public.settings
|   public.users
|
| Features:
|   - Key starts-with filter
|   - User Email exact-match filter
|   - Pagination
|   - Edit setting
|   - JSON value editing
|
|--------------------------------------------------------------------------
*/

if (!function_exists('getAuthDb')) {
    require_once __DIR__ . '/../../includes/bootstrap.php';
}

/*
|--------------------------------------------------------------------------
| CSRF TOKEN
|--------------------------------------------------------------------------
*/

if (empty($_SESSION['auth_settings_csrf'])) {
    $_SESSION['auth_settings_csrf'] = bin2hex(random_bytes(32));
}

$csrfToken = $_SESSION['auth_settings_csrf'];

/*
|--------------------------------------------------------------------------
| POST ACTIONS
|--------------------------------------------------------------------------
*/

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {

    header('Content-Type: application/json; charset=utf-8');

    try {

        /*
         * CSRF
         */
        $postedToken = (string)($_POST['csrf_token'] ?? '');

        if (
            empty($_SESSION['auth_settings_csrf']) ||
            !hash_equals(
                $_SESSION['auth_settings_csrf'],
                $postedToken
            )
        ) {
            throw new RuntimeException(
                'Invalid security token. Please refresh the page.'
            );
        }

        $action = trim(
            (string)($_POST['action'] ?? '')
        );

        $settingId = (int)(
            $_POST['id'] ?? 0
        );

        if ($settingId <= 0) {
            throw new InvalidArgumentException(
                'Invalid setting ID.'
            );
        }

        $pdo = getAuthDb();

        /*
        |--------------------------------------------------------------------------
        | EDIT SETTING
        |--------------------------------------------------------------------------
        */

        if ($action === 'edit') {

            $key = trim(
                (string)($_POST['key'] ?? '')
            );

            $valueRaw = trim(
                (string)($_POST['value'] ?? '')
            );

            if ($key === '') {
                throw new InvalidArgumentException(
                    'Key cannot be empty.'
                );
            }

            /*
             * Validate JSON before sending it to PostgreSQL.
             */
            if ($valueRaw === '') {
                throw new InvalidArgumentException(
                    'Value cannot be empty.'
                );
            }

            json_decode($valueRaw);

            if (json_last_error() !== JSON_ERROR_NONE) {

                throw new InvalidArgumentException(
                    'Value must contain valid JSON: ' .
                    json_last_error_msg()
                );
            }

            /*
             * Get current user_id.
             */
            $currentStmt = $pdo->prepare("
                SELECT
                    id,
                    user_id
                FROM public.settings
                WHERE id = :id
                LIMIT 1
            ");

            $currentStmt->execute([
                ':id' => $settingId
            ]);

            $currentSetting =
                $currentStmt->fetch(
                    PDO::FETCH_ASSOC
                );

            if (!$currentSetting) {
                throw new RuntimeException(
                    'Setting not found.'
                );
            }

            $userId =
                $currentSetting['user_id'];

            /*
             * Unique constraint:
             *
             * UNIQUE (key, user_id)
             */
            $duplicateStmt = $pdo->prepare("
                SELECT id
                FROM public.settings
                WHERE key = :key
                  AND user_id IS NOT DISTINCT FROM :user_id
                  AND id <> :id
                LIMIT 1
            ");

            if ($userId === null) {

                $duplicateStmt->bindValue(
                    ':key',
                    $key,
                    PDO::PARAM_STR
                );

                $duplicateStmt->bindValue(
                    ':user_id',
                    null,
                    PDO::PARAM_NULL
                );

                $duplicateStmt->bindValue(
                    ':id',
                    $settingId,
                    PDO::PARAM_INT
                );

            } else {

                $duplicateStmt->bindValue(
                    ':key',
                    $key,
                    PDO::PARAM_STR
                );

                $duplicateStmt->bindValue(
                    ':user_id',
                    (int)$userId,
                    PDO::PARAM_INT
                );

                $duplicateStmt->bindValue(
                    ':id',
                    $settingId,
                    PDO::PARAM_INT
                );
            }

            $duplicateStmt->execute();

            if ($duplicateStmt->fetch()) {
                throw new RuntimeException(
                    'A setting with this Key already exists for this user.'
                );
            }

            /*
             * Update.
             */
            $updateStmt = $pdo->prepare("
                UPDATE public.settings
                SET
                    key = :key,
                    value = CAST(:value AS json),
                    updated_at = NOW()
                WHERE id = :id
            ");

            $updateStmt->execute([
                ':key' => $key,
                ':value' => $valueRaw,
                ':id' => $settingId
            ]);

            echo json_encode([
                'success' => true,
                'message' => 'Setting updated successfully.'
            ]);

            exit;
        }

        throw new RuntimeException(
            'Unknown action.'
        );

    } catch (Throwable $e) {

        http_response_code(400);

        echo json_encode([
            'success' => false,
            'message' => $e->getMessage()
        ]);

        exit;
    }
}

/*
|--------------------------------------------------------------------------
| FILTERS
|--------------------------------------------------------------------------
*/

$keyFilter = trim(
    (string)($_GET['key'] ?? '')
);

$userEmailFilter = trim(
    (string)($_GET['user_email'] ?? '')
);

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
| WHERE CONDITIONS
|--------------------------------------------------------------------------
*/

$where = [];
$params = [];

/*
 * Key starts with.
 */
if ($keyFilter !== '') {

    $where[] =
        "s.key ILIKE :key_filter";

    $params[':key_filter'] =
        addcslashes(
            $keyFilter,
            '%_\\'
        ) . '%';
}

/*
 * User Email exact match.
 */
if ($userEmailFilter !== '') {

    $where[] =
        "u.email = :user_email";

    $params[':user_email'] =
        $userEmailFilter;
}

$whereSql = $where
    ? 'WHERE ' . implode(
        ' AND ',
        $where
    )
    : '';

/*
|--------------------------------------------------------------------------
| DATABASE
|--------------------------------------------------------------------------
*/

$dbError = null;

$totalSettings = 0;
$totalPages = 1;
$settings = [];

try {

    $pdo = getAuthDb();

    /*
    |--------------------------------------------------------------------------
    | COUNT
    |--------------------------------------------------------------------------
    */

    $countStmt = $pdo->prepare("
        SELECT COUNT(*)
        FROM public.settings s
        LEFT JOIN public.users u
            ON u.id = s.user_id
        {$whereSql}
    ");

    foreach ($params as $key => $value) {

        $countStmt->bindValue(
            $key,
            $value,
            PDO::PARAM_STR
        );
    }

    $countStmt->execute();

    $totalSettings =
        (int)$countStmt->fetchColumn();

    /*
    |--------------------------------------------------------------------------
    | TOTAL PAGES
    |--------------------------------------------------------------------------
    */

    $totalPages = max(
        1,
        (int)ceil(
            $totalSettings / $perPage
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
    | FETCH SETTINGS
    |--------------------------------------------------------------------------
    */

    $stmt = $pdo->prepare("
        SELECT
            s.id,
            s.setting_type,
            s.user_id,
            s.key,
            s.value,
            s.created_at,
            s.updated_at,
            u.email AS user_email
        FROM public.settings s
        LEFT JOIN public.users u
            ON u.id = s.user_id
        {$whereSql}
        ORDER BY s.id DESC
        LIMIT :limit
        OFFSET :offset
    ");

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

    $settings =
        $stmt->fetchAll(
            PDO::FETCH_ASSOC
        );

} catch (Throwable $e) {

    $dbError = $e->getMessage();

    $totalSettings = 0;
    $totalPages = 1;
    $settings = [];
}

/*
|--------------------------------------------------------------------------
| HELPERS
|--------------------------------------------------------------------------
*/

/*
 * Format JSON value for table display.
 */
function authSettingDisplayValue($value): string
{
    if ($value === null) {
        return '-';
    }

    if (is_array($value)) {

        return json_encode(
            $value,
            JSON_UNESCAPED_UNICODE |
            JSON_UNESCAPED_SLASHES
        );
    }

    $value = (string)$value;

    /*
     * PostgreSQL JSON is normally returned as
     * a JSON string through PDO.
     */
    $decoded = json_decode($value, true);

    if (json_last_error() === JSON_ERROR_NONE) {

        return json_encode(
            $decoded,
            JSON_UNESCAPED_UNICODE |
            JSON_UNESCAPED_SLASHES
        );
    }

    return $value;
}

/*
|--------------------------------------------------------------------------
| PAGINATION QUERY
|--------------------------------------------------------------------------
*/

function authSettingsQuery(
    array $overrides = []
): string {

    $query = array_merge(
        [
            'sidebar' =>
                'auth',

            'page' =>
                'auth_settings',

            'key' =>
                (string)(
                    $_GET['key'] ?? ''
                ),

            'user_email' =>
                (string)(
                    $_GET['user_email'] ?? ''
                )
        ],
        $overrides
    );

    return http_build_query(
        array_filter(
            $query,
            static function ($value) {

                return
                    $value !== '' &&
                    $value !== null;
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

            Auth Settings

            <span class="count-pill-navy">

                <?= number_format(
                    $totalSettings
                ) ?>

                Total

            </span>

        </h1>

        <p class="sub">

            Manage authentication server settings.

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

                    <?= htmlspecialchars(
                        $dbError,
                        ENT_QUOTES,
                        'UTF-8'
                    ) ?>

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
        name="sidebar"
        value="auth"
    >

    <input
        type="hidden"
        name="page"
        value="auth_settings"
    >

    <div class="filter-controls">

        <!-- Key -->
        <div class="search-box">

            <svg class="icon">
                <use href="#i-search"/>
            </svg>

            <input
                name="key"
                type="text"
                value="<?= htmlspecialchars(
                    $keyFilter,
                    ENT_QUOTES,
                    'UTF-8'
                ) ?>"
                placeholder="Key starts with:"
                autocomplete="off"
            >

        </div>

        <!-- User Email -->
        <div class="search-box">

            <svg class="icon">
                <use href="#i-search"/>
            </svg>

            <input
                name="user_email"
                type="email"
                value="<?= htmlspecialchars(
                    $userEmailFilter,
                    ENT_QUOTES,
                    'UTF-8'
                ) ?>"
                placeholder="User Email equals:"
                autocomplete="off"
            >

        </div>

        <!-- Filter -->
        <button
            type="submit"
            class="btn btn-outline btn-sm"
        >
            Filter
        </button>

        <!-- Reset -->
        <a
            class="btn btn-outline btn-sm"
            href="index.php?sidebar=auth&page=auth_settings"
        >
            Reset
        </a>

    </div>

</form>

<!-- ================================================================
     RESULT COUNT
     ================================================================ -->

<div class="filter-count">

    Showing

    <b>

        <?= $totalSettings
            ? (
                (($currentPage - 1) * $perPage) + 1
            )
            : 0
        ?>

        -

        <?= min(
            $currentPage * $perPage,
            $totalSettings
        ) ?>

    </b>

    of

    <span>
        <?= number_format(
            $totalSettings
        ) ?>
    </span>

    Settings

</div>

<!-- ================================================================
     SETTINGS TABLE
     ================================================================ -->

<div class="table-wrap">

    <table>

        <thead>

            <tr>

                <th>
                    Key
                </th>

                <th>
                    Value
                </th>

                <th>
                    User Email
                </th>

                <th class="right">
                    Actions
                </th>

            </tr>

        </thead>

        <tbody>

        <?php if (!$settings): ?>

            <tr class="empty-row">

                <td colspan="4">

                    <?= $dbError
                        ? 'Unable to load settings from PostgreSQL.'
                        : 'No settings found matching the selected filters.'
                    ?>

                </td>

            </tr>

        <?php endif; ?>

        <?php foreach ($settings as $setting): ?>

            <?php

            $displayValue =
                authSettingDisplayValue(
                    $setting['value']
                );

            ?>

            <tr>

                <!-- Key -->
                <td>

                    <div class="user-cell">

                        <div class="name">

                            <?= htmlspecialchars(
                                $setting['key'],
                                ENT_QUOTES,
                                'UTF-8'
                            ) ?>

                        </div>

                    </div>

                </td>

                <!-- Value -->
                <td>

                    <div
                        class="setting-value-preview"
                        title="<?= htmlspecialchars(
                            $displayValue,
                            ENT_QUOTES,
                            'UTF-8'
                        ) ?>"
                    >

                        <?= htmlspecialchars(
                            $displayValue,
                            ENT_QUOTES,
                            'UTF-8'
                        ) ?>

                    </div>

                </td>

                <!-- User Email -->
                <td>

                    <?= htmlspecialchars(
                        $setting['user_email']
                            ?: '-',
                        ENT_QUOTES,
                        'UTF-8'
                    ) ?>

                </td>

                <!-- Actions -->
                <td class="right">

                    <div class="row-actions">

                        <button
                            type="button"
                            class="mini-btn"
                            onclick='openAuthSettingModal(<?= json_encode(
                                $setting,
                                JSON_HEX_TAG |
                                JSON_HEX_APOS |
                                JSON_HEX_AMP |
                                JSON_HEX_QUOT
                            ) ?>)'
                        >
                            Edit
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

        <?php if ($currentPage > 1): ?>

            <a
                class="btn btn-outline btn-sm"
                href="index.php?<?= htmlspecialchars(
                    authSettingsQuery([
                        'p' =>
                            $currentPage - 1
                    ]),
                    ENT_QUOTES,
                    'UTF-8'
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
                href="index.php?<?= htmlspecialchars(
                    authSettingsQuery([
                        'p' =>
                            $currentPage + 1
                    ]),
                    ENT_QUOTES,
                    'UTF-8'
                ) ?>"
            >
                Next
            </a>

        <?php endif; ?>

    </div>

<?php endif; ?>

<!-- ================================================================
     EDIT SETTING MODAL
     ================================================================ -->

<div
    id="auth-setting-modal"
    class="user-popup-overlay"
    hidden
>

    <div
        class="user-popup"
        role="dialog"
        aria-modal="true"
        aria-labelledby="auth-setting-modal-title"
    >

        <div class="user-popup-header">

            <div>

                <h2 id="auth-setting-modal-title">
                    Edit Setting
                </h2>

                <p>
                    Update the authentication setting.
                </p>

            </div>

            <button
                type="button"
                class="user-popup-close"
                onclick="closeAuthSettingModal()"
                aria-label="Close"
            >
                &times;
            </button>

        </div>

        <form
            id="auth-setting-edit-form"
        >

            <input
                type="hidden"
                name="action"
                value="edit"
            >

            <input
                type="hidden"
                name="csrf_token"
                value="<?= htmlspecialchars(
                    $csrfToken,
                    ENT_QUOTES,
                    'UTF-8'
                ) ?>"
            >

            <input
                id="auth-setting-modal-id"
                type="hidden"
                name="id"
            >

            <div class="user-popup-grid">

                <!-- Key -->
                <div class="field">

                    <label>
                        Key
                    </label>

                    <input
                        id="auth-setting-modal-key"
                        name="key"
                        type="text"
                        required
                    >

                </div>

                <!-- User -->
                <div class="field">

                    <label>
                        User Email
                    </label>

                    <input
                        id="auth-setting-modal-email"
                        type="text"
                        disabled
                    >

                </div>

            </div>

            <!-- JSON Value -->
            <div
                class="field"
                style="margin-top:16px;"
            >

                <label>
                    Value (JSON)
                </label>

                <textarea
                    id="auth-setting-modal-value"
                    name="value"
                    rows="10"
                    spellcheck="false"
                    required
                ></textarea>

                <small
                    style="
                        display:block;
                        margin-top:6px;
                        color:var(--muted,#6b7280);
                    "
                >
                    Enter valid JSON. Example:
                    {"enabled":true}
                </small>

            </div>

            <div class="user-popup-footer">

                <button
                    type="button"
                    class="btn btn-outline"
                    onclick="closeAuthSettingModal()"
                >
                    Close
                </button>

                <button
                    type="submit"
                    class="btn btn-navy"
                    id="auth-setting-save-btn"
                >
                    Save Changes
                </button>

            </div>

        </form>

    </div>

</div>

<!-- ================================================================
     TOAST
     ================================================================ -->

<div
    id="auth-setting-toast-stack"
></div>

<script>

/*
|--------------------------------------------------------------------------
| Auth Settings JavaScript
|--------------------------------------------------------------------------
*/

const authSettingModal =
    document.getElementById(
        'auth-setting-modal'
    );

const authSettingEditForm =
    document.getElementById(
        'auth-setting-edit-form'
    );

const authSettingSaveBtn =
    document.getElementById(
        'auth-setting-save-btn'
    );

const authSettingCsrf =
    <?= json_encode($csrfToken) ?>;


/*
|--------------------------------------------------------------------------
| Toast
|--------------------------------------------------------------------------
*/

function showAuthSettingToast(
    message,
    type = 'success'
) {

    const stack =
        document.getElementById(
            'auth-setting-toast-stack'
        );

    if (!stack) {

        alert(message);

        return;
    }

    const toast =
        document.createElement(
            'div'
        );

    toast.className =
        'toast' +
        (
            type === 'error'
                ? ' error'
                : ''
        );

    toast.textContent =
        message;

    stack.appendChild(toast);

    setTimeout(
        () => toast.remove(),
        3500
    );
}


/*
|--------------------------------------------------------------------------
| Open Modal
|--------------------------------------------------------------------------
*/

function openAuthSettingModal(
    setting
) {

    document.getElementById(
        'auth-setting-modal-id'
    ).value =
        setting.id ?? '';

    document.getElementById(
        'auth-setting-modal-key'
    ).value =
        setting.key ?? '';

    document.getElementById(
        'auth-setting-modal-email'
    ).value =
        setting.user_email ?? '-';

    /*
     * Pretty-print JSON where possible.
     */
    let value =
        setting.value ?? '';

    try {

        const parsed =
            typeof value === 'string'
                ? JSON.parse(value)
                : value;

        value =
            JSON.stringify(
                parsed,
                null,
                2
            );

    } catch (error) {

        /*
         * Keep original value if it cannot
         * be parsed on the client.
         */
    }

    document.getElementById(
        'auth-setting-modal-value'
    ).value =
        value;

    authSettingModal.hidden =
        false;

    document.body.style.overflow =
        'hidden';

    setTimeout(
        () => {

            document.getElementById(
                'auth-setting-modal-key'
            )?.focus();

        },
        50
    );
}


/*
|--------------------------------------------------------------------------
| Close Modal
|--------------------------------------------------------------------------
*/

function closeAuthSettingModal()
{

    authSettingModal.hidden =
        true;

    document.body.style.overflow =
        '';

}


/*
|--------------------------------------------------------------------------
| Submit Edit
|--------------------------------------------------------------------------
*/

authSettingEditForm?.addEventListener(
    'submit',
    async function(event) {

        event.preventDefault();

        if (!authSettingSaveBtn) {
            return;
        }

        authSettingSaveBtn.disabled =
            true;

        authSettingSaveBtn.textContent =
            'Saving...';

        try {

            /*
             * Validate JSON before request.
             */
            const value =
                document.getElementById(
                    'auth-setting-modal-value'
                ).value.trim();

            if (!value) {

                throw new Error(
                    'Value cannot be empty.'
                );
            }

            try {

                JSON.parse(value);

            } catch (error) {

                throw new Error(
                    'Value must contain valid JSON.'
                );
            }

            const formData =
                new FormData(
                    authSettingEditForm
                );

            const response =
                await fetch(
                    'pages/auth/auth_settings.php',
                    {
                        method: 'POST',
                        body: formData,
                        credentials: 'same-origin'
                    }
                );

            const data =
                await response.json();

            if (!data.success) {

                throw new Error(
                    data.message ||
                    'Unable to update setting.'
                );
            }

            closeAuthSettingModal();

            showAuthSettingToast(
                data.message,
                'success'
            );

            setTimeout(
                () => {

                    window.location.reload();

                },
                500
            );

        } catch (error) {

            showAuthSettingToast(
                error.message ||
                'Unable to update setting.',
                'error'
            );

        } finally {

            authSettingSaveBtn.disabled =
                false;

            authSettingSaveBtn.textContent =
                'Save Changes';
        }

    }
);


/*
|--------------------------------------------------------------------------
| Overlay click
|--------------------------------------------------------------------------
*/

authSettingModal?.addEventListener(
    'click',
    function(event) {

        if (
            event.target ===
            authSettingModal
        ) {

            closeAuthSettingModal();

        }

    }
);


/*
|--------------------------------------------------------------------------
| ESC
|--------------------------------------------------------------------------
*/

document.addEventListener(
    'keydown',
    function(event) {

        if (
            event.key === 'Escape' &&
            authSettingModal &&
            !authSettingModal.hidden
        ) {

            closeAuthSettingModal();

        }

    }
);

</script>

