<?php

$exchangeFilter = strtolower(trim((string)($_GET['exchange'] ?? 'all')));

$perPage = 10;

$pageNumber = max(1, (int)($_GET['p'] ?? 1));

$exchangeMap = [
    // CHANGE THESE TWO VALUES according to your database.
    'nse' => 0,
    'bse' => 1,
];

$exchangeLabels = array_flip($exchangeMap);

$holidays = [];
$totalHolidays = 0;
$dbError = null;

try {

    $pdo = getAppDb();

    $where = [];
    $params = [];

    if (
        $exchangeFilter !== 'all' &&
        isset($exchangeMap[$exchangeFilter])
    ) {
        $where[] = 'exchange = :exchange';
        $params[':exchange'] = $exchangeMap[$exchangeFilter];
    }

    $whereSql = '';

    if ($where) {
        $whereSql = 'WHERE ' . implode(' AND ', $where);
    }

    /* Total */
    $countSql = "
        SELECT COUNT(*)
        FROM public.exchange_holidays
        {$whereSql}
    ";

    $countStmt = $pdo->prepare($countSql);

    foreach ($params as $key => $value) {
        $countStmt->bindValue(
            $key,
            $value,
            PDO::PARAM_INT
        );
    }

    $countStmt->execute();

    $totalHolidays = (int)$countStmt->fetchColumn();

    $totalPages = max(
        1,
        (int)ceil($totalHolidays / $perPage)
    );

    if ($pageNumber > $totalPages) {
        $pageNumber = $totalPages;
    }

    $offset = ($pageNumber - 1) * $perPage;

    /* Data */
    $sql = "
        SELECT
            id,
            holiday_date,
            purpose,
            exchange,
            created_at,
            updated_at
        FROM public.exchange_holidays
        {$whereSql}
        ORDER BY holiday_date ASC, id ASC
        LIMIT :limit
        OFFSET :offset
    ";

    $stmt = $pdo->prepare($sql);

    foreach ($params as $key => $value) {
        $stmt->bindValue(
            $key,
            $value,
            PDO::PARAM_INT
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

    $holidays = $stmt->fetchAll(PDO::FETCH_ASSOC);

} catch (Throwable $e) {

    $dbError = $e->getMessage();

    $totalPages = 1;
}


/*
|--------------------------------------------------------------------------
| Helpers
|--------------------------------------------------------------------------
*/

function holidayExchangeLabel($exchange, $exchangeLabels): string
{
    $exchange = (int)$exchange;

    return isset($exchangeLabels[$exchange])
        ? strtoupper($exchangeLabels[$exchange])
        : (string)$exchange;
}


function holidayDateLabel($date): string
{
    if (!$date) {
        return '-';
    }

    try {
        return (new DateTime($date))->format('d M Y');
    } catch (Throwable $e) {
        return (string)$date;
    }
}

?>

<style>
    /* Centers the Actions column header and cell contents */
    .table-wrap th.th-center,
    .table-wrap td.td-center {
        text-align: center;
        vertical-align: middle;
    }

    .row-actions-center {
        display: flex;
        align-items: center;
        justify-content: center;
        gap: 6px;
        width: 100%;
    }
</style>

<!-- ============================================================
     HEADER
============================================================ -->

<section class="view-header">

    <div>

        <h1>

            Exchange Holidays

            <span class="count-pill-navy">
                <?= number_format($totalHolidays) ?> Total
            </span>

        </h1>

        <p class="sub">
            Manage exchange holiday dates and purposes.
        </p>

    </div>

    <button
        type="button"
        class="btn btn-orange"
        onclick="openHolidayModal()"
    >
        + Add New Exchange Holiday
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
        value="exchange_holidays"
    >

    <div class="filter-controls">

        <div class="field">

            <label>
                Exchange equals:
            </label>

            <select
                name="exchange"
                class="select-plain"
            >

                <option value="all">
                    All Exchanges
                </option>

                <option
                    value="nse"
                    <?= $exchangeFilter === 'nse'
                        ? 'selected'
                        : '' ?>
                >
                    NSE
                </option>

                <option
                    value="bse"
                    <?= $exchangeFilter === 'bse'
                        ? 'selected'
                        : '' ?>
                >
                    BSE
                </option>

            </select>

        </div>

        <button
            type="submit"
            class="btn btn-outline btn-sm"
        >
            Filter
        </button>

        <a
            href="index.php?page=exchange_holidays"
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
        <?= $totalHolidays > 0
            ? (($pageNumber - 1) * $perPage + 1)
            : 0 ?>
    </b>

    to

    <b>
        <?= min(
            $pageNumber * $perPage,
            $totalHolidays
        ) ?>
    </b>

    of

    <span>
        <?= number_format($totalHolidays) ?>
    </span>

    Exchange Holidays

</div>


<!-- ============================================================
     TABLE
============================================================ -->

<div class="table-wrap">

    <table>

        <thead>

            <tr>

                <th>
                    Holiday Date
                </th>

                <th>
                    Purpose
                </th>

                <th>
                    Exchange
                </th>

                <th class="th-center">
                    Actions
                </th>

            </tr>

        </thead>

        <tbody>

        <?php if (!$holidays): ?>

            <tr class="empty-row">

                <td colspan="4">
                    No exchange holidays found.
                </td>

            </tr>

        <?php endif; ?>


        <?php foreach ($holidays as $holiday): ?>

            <tr>

                <td>

                    <?= e(
                        holidayDateLabel(
                            $holiday['holiday_date']
                        )
                    ) ?>

                </td>


                <td>

                    <?= e(
                        $holiday['purpose'] ?? '-'
                    ) ?>

                </td>


                <td>

                    <span class="role-badge User">

                        <?= e(
                            holidayExchangeLabel(
                                $holiday['exchange'],
                                $exchangeLabels
                            )
                        ) ?>

                    </span>

                </td>


                <td class="td-center">

                    <div class="row-actions row-actions-center">

                        <button
                            type="button"
                            class="mini-btn"
                            onclick='openEditHoliday(<?= json_encode(
                                $holiday,
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
                            onclick="holidayDeleteDisabled()"
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
                href="index.php?page=exchange_holidays&exchange=<?= urlencode($exchangeFilter) ?>&p=<?= $pageNumber - 1 ?>"
                class="pagination-btn"
            >
                Previous
            </a>

        <?php endif; ?>


        <?php for (
            $i = 1;
            $i <= $totalPages;
            $i++
        ): ?>

            <a
                href="index.php?page=exchange_holidays&exchange=<?= urlencode($exchangeFilter) ?>&p=<?= $i ?>"
                class="pagination-btn <?= $i === $pageNumber ? 'active' : '' ?>"
            >
                <?= $i ?>
            </a>

        <?php endfor; ?>


        <?php if ($pageNumber < $totalPages): ?>

            <a
                href="index.php?page=exchange_holidays&exchange=<?= urlencode($exchangeFilter) ?>&p=<?= $pageNumber + 1 ?>"
                class="pagination-btn"
            >
                Next
            </a>

        <?php endif; ?>

    </div>

<?php endif; ?>


<!-- ============================================================
     ADD / EDIT MODAL
============================================================ -->

<div
    id="exchange-holiday-modal"
    class="user-popup-overlay"
    hidden
>

    <div
        class="user-popup"
        role="dialog"
        aria-modal="true"
    >

        <div class="user-popup-header">

            <div>

                <h2 id="holiday-modal-title">
                    Add New Exchange Holiday
                </h2>

                <p>
                    Enter exchange holiday information for display only.
                </p>

            </div>

            <button
                type="button"
                class="user-popup-close"
                onclick="closeHolidayModal()"
            >
                &times;
            </button>

        </div>


        <div class="user-popup-grid">

            <!-- ID -->

            <div class="field">

                <label>
                    ID
                </label>

                <input
                    id="holiday-id"
                    type="text"
                    value="Auto generated"
                    disabled
                >

            </div>


            <!-- HOLIDAY DATE -->

            <div class="field">

                <label>
                    Holiday Date
                </label>

                <input
                    id="holiday-date"
                    type="date"
                >

            </div>


            <!-- PURPOSE -->

            <div
                class="field"
                style="grid-column:1/-1;"
            >

                <label>
                    Purpose
                </label>

                <textarea
                    id="holiday-purpose"
                    rows="4"
                    placeholder="Enter holiday purpose"
                ></textarea>

            </div>


            <!-- EXCHANGE -->

            <div class="field">

                <label>
                    Exchange
                </label>

                <select id="holiday-exchange">

                    <option value="nse">
                        NSE
                    </option>

                    <option value="bse">
                        BSE
                    </option>

                </select>

            </div>


            <!-- CREATED -->

            <div class="field">

                <label>
                    Created
                </label>

                <input
                    id="holiday-created"
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
                    id="holiday-updated"
                    type="text"
                    value="Not updated yet"
                    disabled
                >

            </div>

        </div>


        <div class="user-popup-footer">

            <button
                type="button"
                class="btn btn-outline"
                onclick="closeHolidayModal()"
            >
                Close
            </button>

            <button
                type="button"
                class="btn btn-orange"
                onclick="holidaySaveDisabled()"
            >
                Save Changes
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

function openHolidayModal()
{
    document.getElementById(
        'holiday-modal-title'
    ).textContent =
        'Add New Exchange Holiday';

    document.getElementById(
        'holiday-id'
    ).value =
        'Auto generated';

    document.getElementById(
        'holiday-date'
    ).value =
        '';

    document.getElementById(
        'holiday-purpose'
    ).value =
        '';

    document.getElementById(
        'holiday-exchange'
    ).value =
        'nse';

    document.getElementById(
        'holiday-created'
    ).value =
        'Not created yet';

    document.getElementById(
        'holiday-updated'
    ).value =
        'Not updated yet';

    document.getElementById(
        'exchange-holiday-modal'
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

function openEditHoliday(holiday)
{
    document.getElementById(
        'holiday-modal-title'
    ).textContent =
        'Edit Exchange Holiday';

    document.getElementById(
        'holiday-id'
    ).value =
        holiday.id ?? '';

    document.getElementById(
        'holiday-date'
    ).value =
        holiday.holiday_date ?? '';

    document.getElementById(
        'holiday-purpose'
    ).value =
        holiday.purpose ?? '';

    const exchange =
        Number(holiday.exchange);

    document.getElementById(
        'holiday-exchange'
    ).value =
        exchange === <?= (int)$exchangeMap['bse'] ?>
            ? 'bse'
            : 'nse';

    document.getElementById(
        'holiday-created'
    ).value =
        holiday.created_at ?? '';

    document.getElementById(
        'holiday-updated'
    ).value =
        holiday.updated_at ?? '';

    document.getElementById(
        'exchange-holiday-modal'
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

function closeHolidayModal()
{
    document.getElementById(
        'exchange-holiday-modal'
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
| Intentionally disabled from database operation.
|--------------------------------------------------------------------------
*/

function holidaySaveDisabled()
{
    alert(
        'Save is currently disabled. No changes have been made to the database.'
    );
}


/*
|--------------------------------------------------------------------------
| DELETE
|--------------------------------------------------------------------------
*/

function holidayDeleteDisabled()
{
    alert(
        'Delete is currently disabled. No database record has been deleted.'
    );
}


/*
|--------------------------------------------------------------------------
| ESC
|--------------------------------------------------------------------------
*/

document.addEventListener(
    'keydown',
    function(event) {

        if (event.key === 'Escape') {
            closeHolidayModal();
        }

    }
);


/*
|--------------------------------------------------------------------------
| BACKDROP
|--------------------------------------------------------------------------
*/

document
    .getElementById(
        'exchange-holiday-modal'
    )
    ?.addEventListener(
        'click',
        function(event) {

            if (
                event.target === this
            ) {

                closeHolidayModal();

            }

        }
    );

</script>