<?php

/*
|--------------------------------------------------------------------------
| Reports
|--------------------------------------------------------------------------
| Database:
|   multipie_main_prod
|   public.reports
|
| Reporter information:
|   multipie_auth_prod
|   public.users
|
| This page is READ-ONLY for now.
| No UPDATE / DELETE operation is performed.
|--------------------------------------------------------------------------
*/


$perPage = 10;

$currentPage = max(
    1,
    (int)($_GET['p'] ?? 1)
);

$idFilter = trim(
    (string)($_GET['id'] ?? '')
);

$reportableTypeFilter = trim(
    (string)($_GET['reportable_type'] ?? '')
);

$statusFilter = trim(
    (string)($_GET['status'] ?? 'pending')
);


$reports = [];

$totalReports = 0;

$totalPages = 1;

$reporterUsers = [];

$dbError = null;


/*
|--------------------------------------------------------------------------
| STATUS LABEL
|--------------------------------------------------------------------------
|
| IMPORTANT:
| The reports table stores status as INTEGER.
|
| If your corporate application's enum values are different,
| change this mapping only.
|
*/

$reportStatusMap = [
    0 => 'pending',
    1 => 'reviewed',
    2 => 'dismissed'
];


function reportStatusLabel($status): string
{
    global $reportStatusMap;

    $status = (int)$status;

    return $reportStatusMap[$status]
        ?? (string)$status;
}


/*
|--------------------------------------------------------------------------
| STATUS CLASS
|--------------------------------------------------------------------------
*/

function reportStatusClass($status): string
{
    $label = strtolower(
        reportStatusLabel($status)
    );

    if ($label === 'pending') {
        return 'Pending';
    }

    if (
        $label === 'reviewed' ||
        $label === 'resolved'
    ) {
        return 'Active';
    }

    if (
        $label === 'dismissed' ||
        $label === 'rejected'
    ) {
        return 'Inactive';
    }

    return '';
}


/*
|--------------------------------------------------------------------------
| PAGINATION URL
|--------------------------------------------------------------------------
*/

function reportsPageUrl(
    int $page,
    string $id,
    string $type,
    string $status
): string {

    $params = [
        'page' => 'reports',
        'p' => $page
    ];

    if ($id !== '') {
        $params['id'] = $id;
    }

    if ($type !== '') {
        $params['reportable_type'] = $type;
    }

    if ($status !== '') {
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

    /*
    |--------------------------------------------------------------------------
    | WHERE CONDITIONS
    |--------------------------------------------------------------------------
    */

    $where = [];

    $params = [];


    /*
    |--------------------------------------------------------------------------
    | ID EQUALS
    |--------------------------------------------------------------------------
    */

    if ($idFilter !== '') {

        $where[] =
            'r.id = :report_id';

        $params[':report_id'] =
            (int)$idFilter;
    }


    /*
    |--------------------------------------------------------------------------
    | REPORTABLE TYPE
    |--------------------------------------------------------------------------
    */

    if (
        $reportableTypeFilter !== ''
    ) {

        $where[] =
            'r.reportable_type = :reportable_type';

        $params[':reportable_type'] =
            $reportableTypeFilter;
    }


    /*
    |--------------------------------------------------------------------------
    | STATUS
    |--------------------------------------------------------------------------
    */

    if ($statusFilter !== '') {

        /*
         * Convert the UI status to the
         * integer stored in PostgreSQL.
         */

        $statusValue = null;

        foreach (
            $reportStatusMap
            as $number => $label
        ) {

            if (
                strtolower($label) ===
                strtolower($statusFilter)
            ) {

                $statusValue =
                    (int)$number;

                break;
            }
        }


        /*
         * If numeric status is supplied,
         * allow it directly.
         */

        if (
            $statusValue === null &&
            is_numeric($statusFilter)
        ) {

            $statusValue =
                (int)$statusFilter;
        }


        if ($statusValue !== null) {

            $where[] =
                'r.status = :status';

            $params[':status'] =
                $statusValue;
        }
    }


    /*
    |--------------------------------------------------------------------------
    | WHERE SQL
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
        FROM public.reports r
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

        if ($key === ':report_id') {

            $countStmt->bindValue(
                $key,
                $value,
                PDO::PARAM_INT
            );

        } elseif (
            $key === ':status'
        ) {

            $countStmt->bindValue(
                $key,
                $value,
                PDO::PARAM_INT
            );

        } else {

            $countStmt->bindValue(
                $key,
                $value,
                PDO::PARAM_STR
            );
        }
    }


    $countStmt->execute();

    $totalReports =
        (int)$countStmt->fetchColumn();


    /*
    |--------------------------------------------------------------------------
    | PAGINATION
    |--------------------------------------------------------------------------
    */

    $totalPages = max(
        1,
        (int)ceil(
            $totalReports /
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
    |--------------------------------------------------------------------------
    | FETCH REPORTS
    |--------------------------------------------------------------------------
    |
    | meta_info is used for the Comment field.
    |
    */

    $sql = "
        SELECT
            r.id,
            r.reporter_id,
            r.reportable_type,
            r.reportable_id,
            r.reason,
            r.status,
            r.meta_info,
            r.created_at,
            r.updated_at,

            r.meta_info->>'comment'
                AS comment

        FROM public.reports r

        {$whereSql}

        ORDER BY
            r.id DESC

        LIMIT :limit
        OFFSET :offset
    ";


    $stmt =
        $pdo->prepare(
            $sql
        );


    foreach (
        $params
        as $key => $value
    ) {

        if ($key === ':report_id') {

            $stmt->bindValue(
                $key,
                $value,
                PDO::PARAM_INT
            );

        } elseif (
            $key === ':status'
        ) {

            $stmt->bindValue(
                $key,
                $value,
                PDO::PARAM_INT
            );

        } else {

            $stmt->bindValue(
                $key,
                $value,
                PDO::PARAM_STR
            );
        }
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


    $reports =
        $stmt->fetchAll(
            PDO::FETCH_ASSOC
        );


    /*
    |--------------------------------------------------------------------------
    | LOAD REPORTERS FROM AUTH DATABASE
    |--------------------------------------------------------------------------
    */

    $reporterIds = [];


    foreach (
        $reports
        as $report
    ) {

        if (
            !empty(
                $report['reporter_id']
            )
        ) {

            $reporterIds[] =
                (int)$report[
                    'reporter_id'
                ];
        }
    }


    $reporterIds =
        array_values(
            array_unique(
                $reporterIds
            )
        );


    /*
    |--------------------------------------------------------------------------
    | AUTH DATABASE
    |--------------------------------------------------------------------------
    */

    if (!empty($reporterIds)) {

        $AppPdo =
            getAppDb();


        $placeholders = [];

        $userParams = [];


        foreach (
            $reporterIds
            as $index => $reporterId
        ) {

            $placeholder =
                ':uid_' . $index;

            $placeholders[] =
                $placeholder;

            $userParams[
                $placeholder
            ] = $reporterId;
        }


        $usersSql = "
            SELECT
                id,
                username,
                display_name,
                email,
                mobile
            FROM public.users
            WHERE id IN (
                " .
                implode(
                    ',',
                    $placeholders
                ) .
            ")
        ";


        $usersStmt =
            $AppPdo->prepare(
                $usersSql
            );


        foreach (
            $userParams
            as $key => $value
        ) {

            $usersStmt->bindValue(
                $key,
                $value,
                PDO::PARAM_INT
            );
        }


        $usersStmt->execute();


        $usersRows =
            $usersStmt->fetchAll(
                PDO::FETCH_ASSOC
            );


        foreach (
            $usersRows
            as $user
        ) {

            $reporterUsers[
                (int)$user['id']
            ] = $user;
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

            Reports

            <span class="count-pill-navy">

                <?= number_format(
                    $totalReports
                ) ?>

                Total

            </span>

        </h1>


        <p class="sub">

            Manage reports submitted against
            MultiPie users and content.

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
        value="reports"
    >


    <div class="filter-controls">


        <!-- ID -->

        <input
            type="number"
            name="id"
            value="<?= e(
                $idFilter
            ) ?>"
            placeholder="ID equals"
        >


        <!-- REPORTABLE TYPE -->

        <select
            name="reportable_type"
            class="select-plain"
        >

            <option value="">
                -Any Type-
            </option>

            <option
                value="User"
                <?= strcasecmp(
                    $reportableTypeFilter,
                    'User'
                ) === 0
                    ? 'selected'
                    : '' ?>
            >
                User
            </option>

            <option
                value="Post"
                <?= strcasecmp(
                    $reportableTypeFilter,
                    'Post'
                ) === 0
                    ? 'selected'
                    : '' ?>
            >
                Post
            </option>

            <option
                value="Comment"
                <?= strcasecmp(
                    $reportableTypeFilter,
                    'Comment'
                ) === 0
                    ? 'selected'
                    : '' ?>
            >
                Comment
            </option>

        </select>


        <!-- STATUS -->

        <select
            name="status"
            class="select-plain"
        >

            <option value="">
                -Any Status-
            </option>

            <?php foreach (
                $reportStatusMap
                as $statusNumber =>
                    $statusName
            ): ?>

                <option
                    value="<?= e(
                        $statusName
                    ) ?>"
                    <?= strtolower(
                        $statusFilter
                    ) === strtolower(
                        $statusName
                    )
                        ? 'selected'
                        : '' ?>
                >

                    <?= e(
                        ucfirst(
                            $statusName
                        )
                    ) ?>

                </option>

            <?php endforeach; ?>

        </select>


        <button
            type="submit"
            class="btn btn-outline btn-sm"
        >

            Search

        </button>


        <a
            href="index.php?page=reports"
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

        <?= $totalReports > 0
            ? ($offset + 1)
            : 0 ?>

        to

        <?= min(
            $offset + $perPage,
            $totalReports
        ) ?>

    </b>

    of

    <span>

        <?= number_format(
            $totalReports
        ) ?>

    </span>

    Reports

</div>


<!-- ============================================================
     REPORTS TABLE
============================================================ -->

<div class="table-wrap">

    <table>

        <thead>

            <tr>

                <th>
                    Type Reported
                </th>

                <th>
                    ID
                </th>

                <th>
                    Status
                </th>

                <th>
                    Reason
                </th>

                <th>
                    Comment
                </th>

                <th>
                    Reporter
                </th>

                <th>
                    View
                </th>

            </tr>

        </thead>


        <tbody>


        <?php if (
            empty($reports)
        ): ?>

            <tr class="empty-row">

                <td
                    colspan="7"
                >

                    No reports found.

                </td>

            </tr>

        <?php endif; ?>


        <?php foreach (
            $reports
            as $report
        ): ?>


            <?php

            $reporterId =
                (int)(
                    $report[
                        'reporter_id'
                    ] ?? 0
                );


            $reporter =
                $reporterUsers[
                    $reporterId
                ] ?? null;


            $reporterName =
                $reporter['username']
                ?? (
                    $reporter[
                        'display_name'
                    ] ?? (
                        $reporterId
                            ? 'User #' .
                                $reporterId
                            : '-'
                    )
                );


            $comment =
                $report[
                    'comment'
                ] ?? '';


            $statusLabel =
                reportStatusLabel(
                    $report[
                        'status'
                    ] ?? ''
                );


            $statusClass =
                reportStatusClass(
                    $report[
                        'status'
                    ] ?? ''
                );

            ?>


            <tr>


                <!-- TYPE -->

                <td>

                    <span
                        class="role-badge"
                    >

                        <?= e(
                            $report[
                                'reportable_type'
                            ] ?? ''
                        ) ?>

                    </span>

                </td>


                <!-- ID -->

                <td>

                    <?= e(
                        $report[
                            'reportable_id'
                        ] ?? ''
                    ) ?>

                </td>


                <!-- STATUS -->

                <td>

                    <span
                        class="status-badge
                        <?= e(
                            $statusClass
                        ) ?>"
                    >

                        <span
                            class="dot-status
                            <?= e(
                                $statusClass
                            ) ?>"
                        ></span>

                        <?= e(
                            ucfirst(
                                $statusLabel
                            )
                        ) ?>

                    </span>

                </td>


                <!-- REASON -->

                <td>

                    <?= e(
                        $report[
                            'reason'
                        ] ?? ''
                    ) ?>

                </td>


                <!-- COMMENT -->

                <td>

                    <?php if (
                        $comment !== ''
                    ): ?>

                        <?= e(
                            $comment
                        ) ?>

                    <?php else: ?>

                        -

                    <?php endif; ?>

                </td>


                <!-- REPORTER -->

                <td>

                    <?php if (
                        $reporter
                    ): ?>

                        <div>

                            <strong>
                                <?= e(
                                    $reporterName
                                ) ?>
                            </strong>

                            <?php if (
                                !empty(
                                    $reporter[
                                        'email'
                                    ]
                                )
                            ): ?>

                                <small
                                    style="
                                    display:block;
                                    color:var(--slate-500);
                                    margin-top:3px;
                                    "
                                >

                                    <?= e(
                                        $reporter[
                                            'email'
                                        ]
                                    ) ?>

                                </small>

                            <?php endif; ?>

                        </div>

                    <?php else: ?>

                        <?= $reporterId
                            ? 'User #' .
                                $reporterId
                            : '-' ?>

                    <?php endif; ?>

                </td>


                <!-- VIEW -->

                <td>

                    <button
                        type="button"
                        class="btn btn-edit"
                        onclick='openReportViewModal(
                            <?= json_encode(
                                $report
                            ) ?>,
                            <?= json_encode(
                                $reporter
                            ) ?>
                        )'
                    >

                        View

                    </button>

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
                href="<?= e(
                    reportsPageUrl(
                        $currentPage - 1,
                        $idFilter,
                        $reportableTypeFilter,
                        $statusFilter
                    )
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

        ?>


        <?php for (
            $i = $startPage;
            $i <= $endPage;
            $i++
        ): ?>

            <a
                class="pagination-btn
                <?= $i === $currentPage
                    ? 'active'
                    : '' ?>"
                href="<?= e(
                    reportsPageUrl(
                        $i,
                        $idFilter,
                        $reportableTypeFilter,
                        $statusFilter
                    )
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
                href="<?= e(
                    reportsPageUrl(
                        $currentPage + 1,
                        $idFilter,
                        $reportableTypeFilter,
                        $statusFilter
                    )
                ) ?>"
            >

                Next

            </a>

        <?php endif; ?>


    </div>

<?php endif; ?>


<!-- ============================================================
     VIEW REPORT / USER MODAL
============================================================ -->

<div
    id="reportViewModal"
    class="modal-overlay"
    style="display:none;"
>

    <div
        class="modal-box"
        style="max-width:1100px;"
    >


        <!-- HEADER -->

        <div class="modal-header">

            <div>

                <h2>
                    Viewing Report
                </h2>

                <p>
                    Report information and reporter details.
                </p>

            </div>


            <button
                type="button"
                class="modal-close"
                onclick="closeReportViewModal()"
            >

                ×

            </button>

        </div>


        <!-- BODY -->

        <div class="modal-body">


            <div
                class="form-grid"
            >


                <!-- REPORT ID -->

                <div class="form-group">

                    <label>
                        Report ID
                    </label>

                    <input
                        id="viewReportId"
                        type="text"
                        readonly
                    >

                </div>


                <!-- REPORTABLE TYPE -->

                <div class="form-group">

                    <label>
                        Type Reported
                    </label>

                    <input
                        id="viewReportType"
                        type="text"
                        readonly
                    >

                </div>


                <!-- REPORTABLE ID -->

                <div class="form-group">

                    <label>
                        Reported ID
                    </label>

                    <input
                        id="viewReportableId"
                        type="text"
                        readonly
                    >

                </div>


                <!-- STATUS -->

                <div class="form-group">

                    <label>
                        Status
                    </label>

                    <input
                        id="viewReportStatus"
                        type="text"
                        readonly
                    >

                </div>


                <!-- REASON -->

                <div class="form-group">

                    <label>
                        Reason
                    </label>

                    <input
                        id="viewReportReason"
                        type="text"
                        readonly
                    >

                </div>


                <!-- REPORTER -->

                <div class="form-group">

                    <label>
                        Reporter
                    </label>

                    <input
                        id="viewReporter"
                        type="text"
                        readonly
                    >

                </div>


                <!-- EMAIL -->

                <div class="form-group">

                    <label>
                        Reporter Email
                    </label>

                    <input
                        id="viewReporterEmail"
                        type="text"
                        readonly
                    >

                </div>


                <!-- MOBILE -->

                <div class="form-group">

                    <label>
                        Reporter Mobile
                    </label>

                    <input
                        id="viewReporterMobile"
                        type="text"
                        readonly
                    >

                </div>


                <!-- CREATED -->

                <div class="form-group">

                    <label>
                        Created
                    </label>

                    <input
                        id="viewReportCreated"
                        type="text"
                        readonly
                    >

                </div>


                <!-- UPDATED -->

                <div class="form-group">

                    <label>
                        Updated
                    </label>

                    <input
                        id="viewReportUpdated"
                        type="text"
                        readonly
                    >

                </div>


                <!-- COMMENT -->

                <div
                    class="form-group"
                    style="grid-column:1/-1;"
                >

                    <label>
                        Comment
                    </label>

                    <textarea
                        id="viewReportComment"
                        rows="4"
                        readonly
                    ></textarea>

                </div>


            </div>


            <!-- =================================================
                 USER SECTION
            ================================================== -->

            <div
                id="reportUserSection"
                style="display:none;margin-top:30px;"
            >

                <h2>
                    Viewing User
                </h2>


                <p
                    class="sub"
                    style="margin-bottom:20px;"
                >

                    User information associated with
                    this report.

                </p>


                <div
                    class="form-grid"
                >


                    <div class="form-group">

                        <label>
                            User
                        </label>

                        <input
                            id="viewUserUsername"
                            type="text"
                            readonly
                        >

                    </div>


                    <div class="form-group">

                        <label>
                            Display Name
                        </label>

                        <input
                            id="viewUserDisplayName"
                            type="text"
                            readonly
                        >

                    </div>


                    <div class="form-group">

                        <label>
                            Email
                        </label>

                        <input
                            id="viewUserEmail"
                            type="text"
                            readonly
                        >

                    </div>


                    <div class="form-group">

                        <label>
                            Mobile
                        </label>

                        <input
                            id="viewUserMobile"
                            type="text"
                            readonly
                        >

                    </div>


                    <div class="form-group">

                        <label>
                            User ID
                        </label>

                        <input
                            id="viewUserId"
                            type="text"
                            readonly
                        >

                    </div>


                </div>

            </div>


        </div>


        <!-- FOOTER -->

        <div class="modal-footer">

            <button
                type="button"
                class="btn btn-outline"
                onclick="closeReportViewModal()"
            >

                Close

            </button>

        </div>


    </div>

</div>


<script>

/*
|--------------------------------------------------------------------------
| OPEN VIEW MODAL
|--------------------------------------------------------------------------
*/

function openReportViewModal(
    report,
    reporter
) {

    const modal =
        document.getElementById(
            'reportViewModal'
        );


    document.getElementById(
        'viewReportId'
    ).value =
        report.id ?? '';


    document.getElementById(
        'viewReportType'
    ).value =
        report.reportable_type ?? '';


    document.getElementById(
        'viewReportableId'
    ).value =
        report.reportable_id ?? '';


    document.getElementById(
        'viewReportStatus'
    ).value =
        report.status !== undefined
            ? reportStatusText(
                report.status
            )
            : '';


    document.getElementById(
        'viewReportReason'
    ).value =
        report.reason ?? '';


    document.getElementById(
        'viewReportCreated'
    ).value =
        report.created_at ?? '';


    document.getElementById(
        'viewReportUpdated'
    ).value =
        report.updated_at ?? '';


    document.getElementById(
        'viewReportComment'
    ).value =
        report.comment ?? '';


    /*
    |--------------------------------------------------------------------------
    | REPORTER
    |--------------------------------------------------------------------------
    */

    if (reporter) {

        document.getElementById(
            'viewReporter'
        ).value =
            reporter.username ||
            reporter.display_name ||
            '';


        document.getElementById(
            'viewReporterEmail'
        ).value =
            reporter.email || '';


        document.getElementById(
            'viewReporterMobile'
        ).value =
            reporter.mobile || '';

    } else {

        document.getElementById(
            'viewReporter'
        ).value =
            report.reporter_id
                ? 'User #' +
                    report.reporter_id
                : '';


        document.getElementById(
            'viewReporterEmail'
        ).value = '';


        document.getElementById(
            'viewReporterMobile'
        ).value = '';
    }


    /*
    |--------------------------------------------------------------------------
    | USER INFORMATION
    |--------------------------------------------------------------------------
    */

    const userSection =
        document.getElementById(
            'reportUserSection'
        );


    if (
        reporter &&
        String(
            report.reportable_type
        ).toLowerCase() ===
        'user'
    ) {

        userSection.style.display =
            'block';


        document.getElementById(
            'viewUserUsername'
        ).value =
            reporter.username || '';


        document.getElementById(
            'viewUserDisplayName'
        ).value =
            reporter.display_name || '';


        document.getElementById(
            'viewUserEmail'
        ).value =
            reporter.email || '';


        document.getElementById(
            'viewUserMobile'
        ).value =
            reporter.mobile || '';


        document.getElementById(
            'viewUserId'
        ).value =
            report.reportable_id || '';

    } else {

        userSection.style.display =
            'none';
    }


    modal.style.display =
        'flex';
}


/*
|--------------------------------------------------------------------------
| STATUS TEXT
|--------------------------------------------------------------------------
*/

function reportStatusText(
    status
) {

    const statuses = {

        0: 'Pending',

        1: 'Reviewed',

        2: 'Dismissed'

    };


    return statuses[status] ??
        String(status);
}


/*
|--------------------------------------------------------------------------
| CLOSE MODAL
|--------------------------------------------------------------------------
*/

function closeReportViewModal()
{
    document.getElementById(
        'reportViewModal'
    ).style.display =
        'none';
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

        const modal =
            document.getElementById(
                'reportViewModal'
            );


        if (
            event.target === modal
        ) {

            closeReportViewModal();

        }

    }
);

</script>