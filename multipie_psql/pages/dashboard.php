<?php

$userCount = 0;
$mainTables = [];
$usersDbError = null;
$appDbError = null;

try {
    $usersDb = getUsersDb();

    $userCount = (int)$usersDb
        ->query('SELECT COUNT(*) FROM public.users')
        ->fetchColumn();

} catch (Throwable $e) {
    $usersDbError = $e->getMessage();
}

try {
    $appDb = getAppDb();

    $tableStmt = $appDb->query("
        SELECT table_name
        FROM information_schema.tables
        WHERE table_schema = 'public'
          AND table_type = 'BASE TABLE'
        ORDER BY table_name
    ");

    $mainTables = $tableStmt->fetchAll(PDO::FETCH_COLUMN);

} catch (Throwable $e) {
    $appDbError = $e->getMessage();
}

?>

<section class="view-header">

    <div>

        <h1>
            Executive MultiPie Dashboard

            <span class="title-pill">
                PostgreSQL
            </span>
        </h1>

        <p class="sub">
            MultiPie administration connected directly to the PostgreSQL databases.
        </p>

    </div>

</section>


<div class="metrics-grid">

    <div class="metric-card">

        <p class="metric-label">
            Total Users
        </p>

        <div class="metric-value">
            <?= number_format($userCount) ?>
        </div>

        <p class="metric-sub">
            multipie_auth_prod.public.users
        </p>

    </div>


    <div class="metric-card">

        <p class="metric-label">
            Auth Database
        </p>

        <div class="metric-value">
            <?= $usersDbError ? 'Error' : 'Connected' ?>
        </div>

        <p class="metric-sub">
            PostgreSQL users database
        </p>

    </div>


    <div class="metric-card">

        <p class="metric-label">
            Main Database
        </p>

        <div class="metric-value">
            <?= $appDbError ? 'Error' : 'Connected' ?>
        </div>

        <p class="metric-sub">
            multipie_main_prod
        </p>

    </div>


    <div class="metric-card">

        <p class="metric-label">
            Main Tables
        </p>

        <div class="metric-value">
            <?= number_format(count($mainTables)) ?>
        </div>

        <p class="metric-sub">
            Tables discovered directly from PostgreSQL
        </p>

    </div>

</div>


<section class="card">

    <header class="view-header" style="margin-bottom:8px;">

        <div>

            <h2 style="font-size:13px;font-weight:800;color:var(--navy);">
                MultiPie Main Database
            </h2>

            <p class="sub">
                Tables currently available in multipie_main_prod.public
            </p>

        </div>

    </header>


    <?php if ($appDbError): ?>

        <div class="alert-box">

            <div class="row">

                <div>

                    <h4>
                        Main PostgreSQL database is not connected
                    </h4>

                    <p>
                        <?= e($appDbError) ?>
                    </p>

                </div>

            </div>

        </div>

    <?php elseif (!$mainTables): ?>

        <div class="empty-row">
            No public tables were found.
        </div>

    <?php else: ?>

        <div class="table-list-grid">

            <?php foreach ($mainTables as $table): ?>

                <div class="table-name-card">
                    <?= e($table) ?>
                </div>

            <?php endforeach; ?>

        </div>

    <?php endif; ?>

</section>
