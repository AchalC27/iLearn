<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$currentPage = $_GET['page'] ?? 'dashboard';
?>

<header class="main-db-header">
    <div class="main-db-header-inner">
        <div class="main-db-brand">ICICI Securities Universe</div>

        <nav class="database-switcher" aria-label="Application switcher">
            <a
                class="database-button ilearn-button"
                href="../index.php?app=ilearn_mysql&amp;page=<?= urlencode($currentPage) ?>"
            >
                <span class="database-name">iLearn</span>
                <span class="database-type">MySQL</span>
            </a>

            <a
                class="database-button multipie-button active"
                href="../index.php?app=multipie_psql&amp;page=<?= urlencode($currentPage) ?>"
            >
                <span class="database-name">MultiPie</span>
                <span class="database-type">PostgreSQL</span>
            </a>
        </nav>
    </div>
</header>
