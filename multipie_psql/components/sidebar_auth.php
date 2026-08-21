<?php
$currentPage = $_GET['page'] ?? 'auth/auth_dashboard';
?>

<aside id="ilearn-sidebar" class="cms-sidebar auth-sidebar">

    <div class="sidebar-section-switch">
        <a
            class="sidebar-auth-back-btn"
            href="index.php?sidebar=main&page=dashboard"
        >
            <span class="sidebar-auth-admin-icon">
                <svg class="icon"><use href="#i-arrow-left"/></svg>
            </span>
            <span>Back to Main Admin</span>
        </a>
    </div>

    <nav id="sidebar-main-nav" class="auth-sidebar-nav">
        <div>
            <p class="sidebar-group-title">Auth Server Admin</p>

            <ul role="list">

                <li>
                    <a
                        class="nav-btn <?= ($currentPage === 'auth/auth_dashboard') ? 'active' : '' ?>"
                        href="index.php?sidebar=auth&page=auth/auth_dashboard"
                    >
                        <span class="nav-btn-left">
                            <svg class="icon">
                                <use href="#i-users"/>
                            </svg>
                            <span class="sidebar-label">Auth Dashboard</span>
                        </span>
                    </a>
                </li>
            
                <li>
                    <a
                        class="nav-btn <?= ($currentPage === 'auth/auth_users') ? 'active' : '' ?>"
                        href="index.php?sidebar=auth&page=auth/auth_users"
                    >
                        <span class="nav-btn-left">
                            <svg class="icon">
                                <use href="#i-users"/>
                            </svg>
                            <span class="sidebar-label">Auth Users</span>
                        </span>
                    </a>
                </li>

                <li>
                    <a
                        class="nav-btn <?= ($currentPage === 'auth/auth_settings') ? 'active' : '' ?>"
                        href="index.php?sidebar=auth&page=auth/auth_settings"
                    >
                        <span class="nav-btn-left">
                            <svg class="icon">
                                <use href="#i-icon"/>
                            </svg>
                            <span class="sidebar-label">Auth Settings</span>
                        </span>
                    </a>
                </li>

                

                <li>
                    <a
                        class="nav-btn <?= ($currentPage === 'auth/auth_application') ? 'active' : '' ?>"
                        href="index.php?sidebar=auth&page=auth/auth_application"
                    >
                        <span class="nav-btn-left">
                            <svg class="icon">
                                <use href="#i-users"/>
                            </svg>
                            <span class="sidebar-label">Auth Applications</span>
                        </span>
                    </a>
                </li>

                <li>
                    <a
                        class="nav-btn <?= ($currentPage === 'auth/auth_referral_codes') ? 'active' : '' ?>"
                        href="index.php?sidebar=auth&page=auth/auth_referral_codes"
                    >
                        <span class="nav-btn-left">
                            <svg class="icon">
                                <use href="#i-users"/>
                            </svg>
                            <span class="sidebar-label">Auth Referral Codes</span>
                        </span>
                    </a>
                </li>
               
            </ul>
        </div>
    </nav>

    <div class="sidebar-footer-box">
        <div class="row">
            <span class="dot"></span>
            <span>Auth Server Admin</span>
        </div>
        <p>PostgreSQL / PHP / Auth Server</p>
    </div>

</aside>