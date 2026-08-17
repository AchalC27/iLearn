<?php

$perPage = 10;
$pageNumber = max(1, (int)($_GET['p'] ?? 1));

$userFilter = trim((string)($_GET['user'] ?? ''));
$messageFilter = trim((string)($_GET['message'] ?? ''));
$typeFilter = trim((string)($_GET['type'] ?? ''));
$statusFilter = $_GET['status'] ?? 'all';

$posts = [];
$totalPosts = 0;
$totalPages = 1;
$dbError = null;


/*
|--------------------------------------------------------------------------
| STATUS
|--------------------------------------------------------------------------
*/

function postStatusLabel($status): string
{
    return (int)$status === 0
        ? 'Active'
        : 'Inactive';
}


/*
|--------------------------------------------------------------------------
| USER NAME
|--------------------------------------------------------------------------
|
| Posts are stored in multipie_main_prod.
| Users are stored in multipie_auth_prod.
|
*/

function getPostUserNames(
    PDO $usersDb,
    array $userIds
): array {

    if (empty($userIds)) {
        return [];
    }

    $userIds = array_values(
        array_unique(
            array_map(
                'intval',
                $userIds
            )
        )
    );

    $placeholders = [];

    $params = [];

    foreach ($userIds as $i => $id) {

        $key = ':uid' . $i;

        $placeholders[] = $key;

        $params[$key] = $id;
    }

    $sql = "
        SELECT
            id,
            display_name,
            username
        FROM public.users
        WHERE id IN (
            " . implode(',', $placeholders) . "
        )
    ";

    $stmt =
        $usersDb->prepare($sql);

    foreach ($params as $key => $value) {

        $stmt->bindValue(
            $key,
            $value,
            PDO::PARAM_INT
        );
    }

    $stmt->execute();

    $users = $stmt->fetchAll(
        PDO::FETCH_ASSOC
    );

    $result = [];

    foreach ($users as $user) {

        $name =
            trim(
                (string)(
                    $user['display_name']
                    ?? ''
                )
            );

        if ($name === '') {

            $name =
                trim(
                    (string)(
                        $user['username']
                        ?? ''
                    )
                );
        }

        $result[(int)$user['id']] =
            $name !== ''
                ? $name
                : 'User #' . $user['id'];
    }

    return $result;
}


/*
|--------------------------------------------------------------------------
| PAGINATION URL
|--------------------------------------------------------------------------
*/

function postPageUrl(
    int $page,
    string $user,
    string $message,
    string $type,
    string $status
): string {

    $params = [
        'page' => 'posts',
        'p' => $page
    ];

    if ($user !== '') {
        $params['user'] = $user;
    }

    if ($message !== '') {
        $params['message'] = $message;
    }

    if ($type !== '') {
        $params['type'] = $type;
    }

    if ($status !== 'all') {
        $params['status'] = $status;
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
    | MESSAGE
    |--------------------------------------------------------------------------
    */

    if ($messageFilter !== '') {

        $where[] =
            'message ILIKE :message';

        $params[':message'] =
            '%' . $messageFilter . '%';
    }


    /*
    |--------------------------------------------------------------------------
    | POST TYPE
    |--------------------------------------------------------------------------
    */

    if ($typeFilter !== '') {

        $where[] =
            'type ILIKE :post_type';

        $params[':post_type'] =
            '%' . $typeFilter . '%';
    }


    /*
    |--------------------------------------------------------------------------
    | STATUS
    |--------------------------------------------------------------------------
    */

    if (
        $statusFilter !== '' &&
        $statusFilter !== 'all'
    ) {

        $where[] =
            'status = :status';

        $params[':status'] =
            (int)$statusFilter;
    }


    /*
    |--------------------------------------------------------------------------
    | USER FILTER
    |--------------------------------------------------------------------------
    |
    | User is in another database.
    |
    | We first find matching users in auth DB,
    | then filter posts by user_id.
    |
    */

    if ($userFilter !== '') {

        $usersDb =
            getUsersDb();

        $userStmt =
            $usersDb->prepare("
                SELECT id
                FROM public.users
                WHERE
                    username ILIKE :user
                    OR display_name ILIKE :user
            ");

        $userStmt->execute([
            ':user' =>
                '%' . $userFilter . '%'
        ]);

        $matchingUserIds =
            $userStmt->fetchAll(
                PDO::FETCH_COLUMN
            );


        if (empty($matchingUserIds)) {

            $where[] =
                '1 = 0';

        } else {

            $placeholders = [];

            foreach (
                $matchingUserIds
                as $i => $userId
            ) {

                $key =
                    ':user_id_' . $i;

                $placeholders[] =
                    $key;

                $params[$key] =
                    (int)$userId;
            }

            $where[] =
                'user_id IN (' .
                implode(
                    ',',
                    $placeholders
                ) .
                ')';
        }
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
    | COUNT
    |--------------------------------------------------------------------------
    */

    $countSql = "
        SELECT COUNT(*)
        FROM public.posts
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

    $totalPosts =
        (int)$countStmt->fetchColumn();


    /*
    |--------------------------------------------------------------------------
    | PAGINATION
    |--------------------------------------------------------------------------
    */

    $totalPages =
        max(
            1,
            (int)ceil(
                $totalPosts /
                $perPage
            )
        );

    if (
        $pageNumber >
        $totalPages
    ) {

        $pageNumber =
            $totalPages;
    }

    $offset =
        ($pageNumber - 1) *
        $perPage;


    /*
    |--------------------------------------------------------------------------
    | POSTS
    |--------------------------------------------------------------------------
    */

    $sql = "
        SELECT
            id,
            visibility,
            message,
            user_id,
            parent_post_id,
            created_at,
            updated_at,
            likes_count,
            replies_count,
            widgets_data,
            meta_info,
            type,
            reposts_count,
            root_post_id,
            status,
            views_count,
            propagation_info
        FROM public.posts
        {$whereSql}
        ORDER BY
            created_at DESC,
            id DESC
        LIMIT :limit
        OFFSET :offset
    ";

    $stmt =
        $pdo->prepare($sql);


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

    $posts =
        $stmt->fetchAll(
            PDO::FETCH_ASSOC
        );


    /*
    |--------------------------------------------------------------------------
    | GET USER NAMES
    |--------------------------------------------------------------------------
    */

    $userNames = [];

    if (!empty($posts)) {

        $userIds = [];

        foreach ($posts as $post) {

            if (
                !empty(
                    $post['user_id']
                )
            ) {

                $userIds[] =
                    (int)$post['user_id'];
            }
        }


        if (!empty($userIds)) {

            $usersDb =
                getUsersDb();

            $userNames =
                getPostUserNames(
                    $usersDb,
                    $userIds
                );
        }
    }


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

            Posts

            <span class="count-pill-navy">

                <?= number_format(
                    $totalPosts
                ) ?>

                Total

            </span>

        </h1>

        <p class="sub">
            Manage MultiPie posts and user-generated content.
        </p>

    </div>

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
     FILTERS
============================================================ -->

<form
    class="filter-bar"
    method="get"
    action="index.php"
>

    <input
        type="hidden"
        name="page"
        value="posts"
    >


    <div class="filter-controls">

        <input
            type="text"
            name="user"
            placeholder="User"
            value="<?= e(
                $userFilter
            ) ?>"
        >


        <input
            type="text"
            name="message"
            placeholder="Message contains"
            value="<?= e(
                $messageFilter
            ) ?>"
        >


        <input
            type="text"
            name="type"
            placeholder="Post Type"
            value="<?= e(
                $typeFilter
            ) ?>"
        >


        <select
            class="select-plain"
            name="status"
        >

            <option
                value="all"
                <?= $statusFilter === 'all'
                    ? 'selected'
                    : '' ?>
            >
                All Status
            </option>

            <option
                value="0"
                <?= $statusFilter === '0'
                    ? 'selected'
                    : '' ?>
            >
                Active
            </option>

            <option
                value="1"
                <?= $statusFilter === '1'
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
            href="index.php?page=posts"
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

        <?= $totalPosts > 0
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
            $totalPosts
        ) ?>

    </b>

    of

    <span>

        <?= number_format(
            $totalPosts
        ) ?>

    </span>

    Posts

</div>


<!-- ============================================================
     TABLE
============================================================ -->

<div class="table-wrap">

    <table>

        <thead>

            <tr>

                <th>
                    User
                </th>

                <th>
                    Message
                </th>

                <th>
                    Post Type
                </th>

                <th>
                    Likes Count
                </th>

                <th>
                    Repost Count
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


        <?php if (empty($posts)): ?>

            <tr class="empty-row">

                <td colspan="7">

                    No Posts found.

                </td>

            </tr>

        <?php endif; ?>


        <?php foreach ($posts as $post): ?>

            <?php

            $userId =
                (int)(
                    $post['user_id']
                    ?? 0
                );

            $userName =
                $userNames[$userId]
                ?? 'User #' . $userId;

            $statusText =
                postStatusLabel(
                    $post['status']
                );

            ?>


            <tr>


                <!-- USER -->

                <td>

                    <?= e(
                        $userName
                    ) ?>

                </td>


                <!-- MESSAGE -->

                <td>

                    <div
                        style="
                            max-width:360px;
                            white-space:nowrap;
                            overflow:hidden;
                            text-overflow:ellipsis;
                        "
                        title="<?= e(
                            $post['message']
                            ?? ''
                        ) ?>"
                    >

                        <?= e(
                            $post['message']
                            ?? '-'
                        ) ?>

                    </div>

                </td>


                <!-- TYPE -->

                <td>

                    <?= e(
                        $post['type']
                        ?? '-'
                    ) ?>

                </td>


                <!-- LIKES -->

                <td>

                    <?= number_format(
                        (int)(
                            $post[
                                'likes_count'
                            ] ?? 0
                        )
                    ) ?>

                </td>


                <!-- REPOSTS -->

                <td>

                    <?= number_format(
                        (int)(
                            $post[
                                'reposts_count'
                            ] ?? 0
                        )
                    ) ?>

                </td>


                <!-- STATUS -->

                <td>

                    <span
                        class="
                            status-badge
                            <?= e(
                                $statusText
                            ) ?>
                        "
                    >

                        <span
                            class="
                                dot-status
                                <?= e(
                                    $statusText
                                ) ?>
                            "
                        ></span>

                        <?= e(
                            $statusText
                        ) ?>

                    </span>

                </td>


                <!-- ACTIONS -->

                <td>

                    <div class="table-actions">

                        <button
                            type="button"
                            class="mini-btn"
                            onclick='openPostEditModal(
                                <?= json_encode(
                                    $post,
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
                            onclick="postDeleteDisabled()"
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
                    postPageUrl(
                        $pageNumber - 1,
                        $userFilter,
                        $messageFilter,
                        $typeFilter,
                        $statusFilter
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
                    postPageUrl(
                        $i,
                        $userFilter,
                        $messageFilter,
                        $typeFilter,
                        $statusFilter
                    )
                ) ?>"
                class="
                    pagination-btn
                    <?= $i === $pageNumber
                        ? 'active'
                        : '' ?>
                "
            >
                <?= $i ?>
            </a>

        <?php endfor; ?>


        <?php if (
            $pageNumber <
            $totalPages
        ): ?>

            <a
                href="<?= e(
                    postPageUrl(
                        $pageNumber + 1,
                        $userFilter,
                        $messageFilter,
                        $typeFilter,
                        $statusFilter
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
     EDIT POST POPUP
============================================================ -->

<div
    id="post-modal"
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

                <h2>
                    Edit Post
                </h2>

                <p>
                    Post information — display only.
                    Saving is disabled.
                </p>

            </div>


            <button
                type="button"
                class="user-popup-close"
                onclick="closePostModal()"
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
                    id="post-id"
                    type="text"
                    disabled
                >

            </div>


            <!-- USER ID -->

            <div class="field">

                <label>
                    User ID
                </label>

                <input
                    id="post-user-id"
                    type="text"
                    disabled
                >

            </div>


            <!-- POST TYPE -->

            <div class="field">

                <label>
                    Post Type
                </label>

                <input
                    id="post-type"
                    type="text"
                >

            </div>


            <!-- VISIBILITY -->

            <div class="field">

                <label>
                    Visibility
                </label>

                <input
                    id="post-visibility"
                    type="number"
                >

            </div>


            <!-- STATUS -->

            <div class="field">

                <label>
                    Status
                </label>

                <select
                    id="post-status"
                >

                    <option value="0">
                        Active
                    </option>

                    <option value="1">
                        Inactive
                    </option>

                </select>

            </div>


            <!-- LIKES -->

            <div class="field">

                <label>
                    Likes Count
                </label>

                <input
                    id="post-likes"
                    type="number"
                >

            </div>


            <!-- REPOSTS -->

            <div class="field">

                <label>
                    Repost Count
                </label>

                <input
                    id="post-reposts"
                    type="number"
                >

            </div>


            <!-- REPLIES -->

            <div class="field">

                <label>
                    Replies Count
                </label>

                <input
                    id="post-replies"
                    type="number"
                >

            </div>


            <!-- VIEWS -->

            <div class="field">

                <label>
                    Views Count
                </label>

                <input
                    id="post-views"
                    type="number"
                >

            </div>


            <!-- CREATED -->

            <div class="field">

                <label>
                    Created
                </label>

                <input
                    id="post-created"
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
                    id="post-updated"
                    type="text"
                    disabled
                >

            </div>


            <!-- MESSAGE -->

            <div
                class="field"
                style="grid-column:1/-1;"
            >

                <label>
                    Message
                </label>

                <textarea
                    id="post-message"
                    rows="5"
                    placeholder="Enter message"
                ></textarea>

            </div>


            <!-- META INFO -->

            <div
                class="field"
                style="grid-column:1/-1;"
            >

                <label>
                    Meta Info
                </label>

                <textarea
                    id="post-meta-info"
                    rows="4"
                    placeholder="Meta information"
                ></textarea>

            </div>

        </div>


        <!-- FOOTER -->

        <div class="user-popup-footer">

            <button
                type="button"
                class="btn btn-outline"
                onclick="closePostModal()"
            >
                Close
            </button>


            <button
                type="button"
                class="btn btn-orange"
                onclick="postSaveDisabled()"
            >
                Save Changes
            </button>

        </div>

    </div>

</div>


<script>

/*
|--------------------------------------------------------------------------
| EDIT POST
|--------------------------------------------------------------------------
*/

function openPostEditModal(post)
{
    document.getElementById(
        'post-id'
    ).value =
        post.id ?? '';


    document.getElementById(
        'post-user-id'
    ).value =
        post.user_id ?? '';


    document.getElementById(
        'post-type'
    ).value =
        post.type ?? '';


    document.getElementById(
        'post-visibility'
    ).value =
        post.visibility ?? '';


    document.getElementById(
        'post-status'
    ).value =
        post.status ?? '0';


    document.getElementById(
        'post-likes'
    ).value =
        post.likes_count ?? '0';


    document.getElementById(
        'post-reposts'
    ).value =
        post.reposts_count ?? '0';


    document.getElementById(
        'post-replies'
    ).value =
        post.replies_count ?? '0';


    document.getElementById(
        'post-views'
    ).value =
        post.views_count ?? '0';


    document.getElementById(
        'post-created'
    ).value =
        post.created_at ?? '';


    document.getElementById(
        'post-updated'
    ).value =
        post.updated_at ?? '';


    document.getElementById(
        'post-message'
    ).value =
        post.message ?? '';


    document.getElementById(
        'post-meta-info'
    ).value =
        post.meta_info ?? '';


    document.getElementById(
        'post-modal'
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

function closePostModal()
{
    document.getElementById(
        'post-modal'
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
| No UPDATE is executed.
|--------------------------------------------------------------------------
*/

function postSaveDisabled()
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
| No DELETE is executed.
|--------------------------------------------------------------------------
*/

function postDeleteDisabled()
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

            closePostModal();

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
        'post-modal'
    )
    ?.addEventListener(
        'click',
        function(event)
        {

            if (
                event.target === this
            ) {

                closePostModal();

            }

        }
    );

</script>