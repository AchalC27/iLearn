<?php
/**
 * Shared top-level application switcher.
 *
 * This file is included by each application's own index.php.
 * The buttons always route through the root iLearn/index.php,
 * which then redirects to the selected application's index.php.
 */

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$currentApp = $_SESSION['selected_app'] ?? 'ilearn_mysql';
$currentPage = $_GET['page'] ?? 'dashboard';

$isIlearn = $currentApp === 'ilearn_mysql';
$isMultiPie = $currentApp === 'multipie_psql';
?>

<header class="main-db-header">
    <div class="main-db-header-inner">

        <div class="main-db-brand">
            ICICI Securities Universe
        </div>

        <nav class="database-switcher" aria-label="Application switcher">

            <a
                class="database-button ilearn-button <?= $isIlearn ? 'active' : '' ?>"
                href="../index.php?app=ilearn_mysql&amp;page=<?= urlencode($currentPage) ?>"
            >
                <span class="database-name">iLearn</span>
                <!-- <span class="database-type">MySQL</span> -->
            </a>

            <a
                class="database-button multipie-button <?= $isMultiPie ? 'active' : '' ?>"
                href="../index.php?app=multipie_psql&amp;page=<?= urlencode($currentPage) ?>"
            >
                <span class="database-name">Community</span>
                <!-- <span class="database-type">PostgreSQL</span> -->
            </a>

        </nav>

    </div>
</header>
