<?php
/*
|--------------------------------------------------------------------------
| MultiPie Sidebar
|--------------------------------------------------------------------------
|
| Users count is read directly from:
|   multipie_auth_prod.public.users
|
| No JSON/dummy data is used.
|--------------------------------------------------------------------------
*/

$userCount = 0;

try {
    $usersDb = getUsersDb();

    $userCount = (int)$usersDb
        ->query('SELECT COUNT(*) FROM public.users')
        ->fetchColumn();
} catch (Throwable $e) {
    // Keep the navigation visible even if the DB is temporarily unavailable.
    $userCount = 0;
}
?>

<aside id="ilearn-sidebar" class="cms-sidebar">
    <nav id="sidebar-main-nav">

        <div>
            <p class="sidebar-group-title">Main Menu</p>

            <ul role="list">

                <li>
                    <a
                        class="nav-btn <?= $page === 'dashboard' ? 'active' : '' ?>"
                        href="index.php?page=dashboard"
                    >
                        <span class="nav-btn-left">
                            <svg class="icon">
                                <use href="#i-layout"/>
                            </svg>
                            <span class="sidebar-label">Dashboard</span>
                        </span>
                    </a>
                </li>

                <li>
                    <a
                        class="nav-btn <?= $page === 'users' ? 'active' : '' ?>"
                        href="index.php?page=users"
                    >
                        <span class="nav-btn-left">
                            <svg class="icon">
                                <use href="#i-users"/>
                            </svg>
                            <span class="sidebar-label">Users Directory</span>
                        </span>

                        <span class="sidebar-badge">
                            <?= number_format($userCount) ?>
                        </span>
                    </a>
                </li>

                <li>
                    <a
                        class="nav-btn <?= $page === 'amcs' ? 'active' : '' ?>"
                        href="index.php?page=amcs"
                    >
                        <span class="nav-btn-left">
                            <svg class="icon">
                                <use href="#i-layout"/>
                            </svg>
                            <span class="sidebar-label">
                                AMCs
                            </span>
                        </span>
                    </a>
                </li>

                <li>

                    <a
                        class="nav-btn <?= $page === 'announcements' ? 'active' : '' ?>"
                        href="index.php?page=announcements"
                    >
                        <span class="nav-btn-left">
                            <svg class="icon">
                                <use href="#i-bell"></use>
                            </svg>
                            <span class="sidebar-label">
                                Announcements
                            </span>
                        </span>
                    </a>
                </li>

                <li>
                    <a
                        class="nav-btn <?= $page === 'cams_feedbacks' ? 'active' : '' ?>"
                        href="index.php?page=cams_feedbacks"
                    >
                        <span class="nav-btn-left">
                            <svg class="icon">
                                <use href="#i-file-text"></use>
                            </svg>
                            <span class="sidebar-label">
                                Cams Feedbacks
                            </span>
                        </span>
                    </a>
                </li>

                <li>
                    <a
                        class="nav-btn <?= $page === 'companies' ? 'active' : '' ?>"
                        href="index.php?page=companies"
                    >
                        <span class="nav-btn-left">
                            <svg class="icon">
                                <use href="#i-message"></use>
                            </svg>
                            <span class="sidebar-label">
                                Companies
                            </span>
                        </span>
                    </a>
                </li>

                <li>
                    <a
                        class="nav-btn <?= $page === 'mutual_funds' ? 'active' : '' ?>"
                        href="index.php?page=mutual_funds"
                    >
                        <span class="nav-btn-left">
                            <svg class="icon">
                                <use href="#i-message"></use>
                            </svg>
                            <span class="sidebar-label">
                                Mutual Funds
                            </span>
                        </span>
                    </a>
                </li>


                <!-- <li>
                    <a
                        class="nav-btn <?= $page === 'posts' ? 'active' : '' ?>"
                        href="index.php?page=posts"
                    >
                        <span class="nav-btn-left">
                            <svg class="icon">
                                <use href="#i-file-text"/>
                            </svg>
                            <span class="sidebar-label">Posts &amp; Articles</span>
                        </span>
                    </a>
                </li>

                <li>
                    <a
                        class="nav-btn <?= $page === 'comments' ? 'active' : '' ?>"
                        href="index.php?page=comments"
                    >
                        <span class="nav-btn-left">
                            <svg class="icon">
                                <use href="#i-message"/>
                            </svg>
                            <span class="sidebar-label">Comments</span>
                        </span>
                    </a>
                </li> -->

            </ul>
        </div>

        <!-- <div>
            <p class="sidebar-group-title">Analytics &amp; Inspector</p>

            <ul role="list">

                <li>
                    <a
                        class="nav-btn <?= $page === 'analytics' ? 'active' : '' ?>"
                        href="index.php?page=analytics"
                    >
                        <span class="nav-btn-left">
                            <svg class="icon">
                                <use href="#i-bar-chart"/>
                            </svg>
                            <span class="sidebar-label">Learning Analytics</span>
                        </span>
                    </a>
                </li>

                <li>
                    <a
                        class="nav-btn"
                        href="#"
                        onclick="return false;"
                    >
                        <span class="nav-btn-left">
                            <svg class="icon">
                                <use href="#i-code"/>
                            </svg>
                            <span class="sidebar-label">HTML Code Inspector</span>
                        </span>

                        <span class="sidebar-pill-raw sidebar-label">RAW</span>
                    </a>
                </li>

            </ul>
        </div> -->

    </nav>

    <div class="sidebar-footer-box">
        <div class="row">
            <span class="dot"></span>
            <span>MultiPie Engine v1.0</span>
        </div>

        <p>PostgreSQL / PHP / HTML / CSS</p>
    </div>
</aside>
