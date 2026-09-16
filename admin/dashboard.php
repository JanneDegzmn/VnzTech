<?php

require_once __DIR__ . '/../backend/database.php';
require_once __DIR__ . '/../backend/session.php';
require_once __DIR__ . '/../backend/auth.php';

requireAdminLogin();

$currentAdmin = getCurrentAdmin();

if (!$currentAdmin) {
    header('Location: login.php');
    exit;
}

?>

<!DOCTYPE html>
<html lang="en">

<head>

    <meta charset="UTF-8">

    <meta
        name="viewport"
        content="width=device-width, initial-scale=1.0"
    >

    <title>VNZ TECH - Admin Dashboard</title>

    <link
        rel="stylesheet"
        href="/VNZ-TECH/assets/css/admin_dashboard.css"
    >

</head>


<body>

<div class="admin-layout">


    <!-- =====================================================
         SIDEBAR
    ====================================================== -->

    <aside class="sidebar" id="sidebar">

        <div class="sidebar-top">

            <div class="brand">

                <div class="brand-name">
                    VNZ TECH
                </div>

                <div class="brand-subtitle">
                    ADMIN PANEL
                </div>

            </div>


            <button
                type="button"
                class="sidebar-close"
                id="sidebarClose"
            >
                ×
            </button>

        </div>


        <nav class="sidebar-nav">

            <div class="nav-section-title">
                MAIN
            </div>


            <button
                type="button"
                class="nav-item active"
                data-page="dashboard"
            >

                <span class="nav-icon">▣</span>

                <span class="nav-text">
                    Dashboard
                </span>

            </button>


            <button
                type="button"
                class="nav-item"
                data-page="submissions"
            >

                <span class="nav-icon">▤</span>

                <span class="nav-text">
                    Submissions
                </span>

            </button>


            <button
                type="button"
                class="nav-item"
                data-page="offers"
            >

                <span class="nav-icon">₱</span>

                <span class="nav-text">
                    Offers
                </span>

            </button>


            <button
                type="button"
                class="nav-item"
                data-page="transactions"
            >

                <span class="nav-icon">↔</span>

                <span class="nav-text">
                    Transactions
                </span>

            </button>


            <div class="nav-section-title">
                MANAGEMENT
            </div>


            <?php if (hasPermission('manage_rates')): ?>

                <button
                    type="button"
                    class="nav-item"
                    data-page="buying_rates"
                >

                    <span class="nav-icon">◈</span>

                    <span class="nav-text">
                        Buying Rates
                    </span>

                </button>

            <?php endif; ?>


            <?php if (hasPermission('view_reports')): ?>

                <button
                    type="button"
                    class="nav-item"
                    data-page="reports"
                >

                    <span class="nav-icon">▥</span>

                    <span class="nav-text">
                        Reports
                    </span>

                </button>

            <?php endif; ?>


            <?php if (hasPermission('manage_settings')): ?>

                <button
                    type="button"
                    class="nav-item"
                    data-page="settings"
                >

                    <span class="nav-icon">⚙</span>

                    <span class="nav-text">
                        Settings
                    </span>

                </button>

            <?php endif; ?>

        </nav>


        <!-- =================================================
             USER AREA
        ================================================== -->

        <div class="sidebar-user">

            <div class="user-avatar">

                <?= strtoupper(
                    substr(
                        $currentAdmin['first_name'] ?? 'A',
                        0,
                        1
                    )
                ) ?>

            </div>


            <div class="user-info">

                <div class="user-name">

                    <?= htmlspecialchars(
                        $currentAdmin['first_name']
                        . ' '
                        . $currentAdmin['last_name'],
                        ENT_QUOTES,
                        'UTF-8'
                    ) ?>

                </div>


                <div class="user-role">

                    <?= htmlspecialchars(
                        $currentAdmin['role_name'],
                        ENT_QUOTES,
                        'UTF-8'
                    ) ?>

                </div>

            </div>


            <a
                href="logout.php"
                class="logout-button"
                title="Logout"
            >
                ↪
            </a>

        </div>

    </aside>


    <!-- =====================================================
         MOBILE OVERLAY
    ====================================================== -->

    <div
        class="sidebar-overlay"
        id="sidebarOverlay"
    ></div>


    <!-- =====================================================
         MAIN AREA
    ====================================================== -->

    <main class="main-area">


        <!-- =================================================
             TOP BAR
        ================================================== -->

        <header class="topbar">

            <div class="topbar-left">

                <button
                    type="button"
                    class="menu-button"
                    id="menuButton"
                >
                    ☰
                </button>


                <div>

                    <div
                        class="page-title"
                        id="pageTitle"
                    >
                        Dashboard
                    </div>

                    <div class="page-subtitle">
                        VNZ TECH Management System
                    </div>

                </div>

            </div>


            <div class="topbar-right">

                <div class="topbar-user">

                    <?= htmlspecialchars(
                        $currentAdmin['first_name'],
                        ENT_QUOTES,
                        'UTF-8'
                    ) ?>

                </div>

            </div>

        </header>


        <!-- =================================================
             DYNAMIC CONTENT
        ================================================== -->

        <section
            class="content-area"
            id="contentArea"
        >

            <div class="content-loading">

                <div class="loading-spinner"></div>

                <span>
                    Loading...
                </span>

            </div>

        </section>


    </main>

</div>


<script>

document.addEventListener(
    'DOMContentLoaded',
    function () {

        const sidebar =
            document.getElementById('sidebar');

        const menuButton =
            document.getElementById('menuButton');

        const sidebarClose =
            document.getElementById('sidebarClose');

        const sidebarOverlay =
            document.getElementById('sidebarOverlay');

        const contentArea =
            document.getElementById('contentArea');

        const pageTitle =
            document.getElementById('pageTitle');

        const navItems =
            document.querySelectorAll('.nav-item');


        /*
        |--------------------------------------------------------------------------
        | Page Titles
        |--------------------------------------------------------------------------
        */

        const pageTitles = {

            dashboard: 'Dashboard',

            submissions: 'Submissions',

            submission_review: 'Review Submission',

            offers: 'Offers',

            transactions: 'Transactions',

            buying_rates: 'Buying Rates',

            reports: 'Reports',

            settings: 'Settings'

        };


        /*
        |--------------------------------------------------------------------------
        | Build Page URL
        |--------------------------------------------------------------------------
        */

        function buildPageUrl(page, params = {}) {

            const query =
                new URLSearchParams();

            query.set(
                'page',
                page
            );


            Object.keys(params).forEach(
                function (key) {

                    const value =
                        params[key];

                    if (
                        value !== undefined &&
                        value !== null &&
                        value !== ''
                    ) {

                        query.set(
                            key,
                            value
                        );

                    }

                }
            );


            return '?' + query.toString();

        }


        /*
        |--------------------------------------------------------------------------
        | Set Active Navigation
        |--------------------------------------------------------------------------
        */

        function setActiveNavigation(page) {

            let activePage = page;


            /*
            |--------------------------------------------------------------------------
            | Review page belongs to Submissions
            |--------------------------------------------------------------------------
            */

            if (
                page === 'submission_review'
            ) {

                activePage =
                    'submissions';

            }


            navItems.forEach(
                function (item) {

                    item.classList.remove(
                        'active'
                    );


                    if (
                        item.dataset.page ===
                        activePage
                    ) {

                        item.classList.add(
                            'active'
                        );

                    }

                }
            );

        }


        /*
        |--------------------------------------------------------------------------
        | Page Loader
        |--------------------------------------------------------------------------
        */

        function loadPage(
            page,
            pushState = true,
            params = {}
        ) {

            contentArea.innerHTML = `

                <div class="content-loading">

                    <div class="loading-spinner"></div>

                    <span>
                        Loading...
                    </span>

                </div>

            `;


            setActiveNavigation(
                page
            );


            pageTitle.textContent =
                pageTitles[page]
                || 'Dashboard';


            /*
            |--------------------------------------------------------------------------
            | Build Fetch URL
            |--------------------------------------------------------------------------
            */

            const query =
                new URLSearchParams(
                    params
                );


            const queryString =
                query.toString();


            const fetchUrl =
                'pages/' +
                page +
                '.php' +
                (
                    queryString
                        ? '?' + queryString
                        : ''
                );


            /*
            |--------------------------------------------------------------------------
            | Fetch Page
            |--------------------------------------------------------------------------
            */

            fetch(
                fetchUrl,
                {
                    method: 'GET',

                    headers: {
                        'X-Requested-With':
                            'XMLHttpRequest'
                    }
                }
            )

            .then(
                function (response) {

                    if (!response.ok) {

                        throw new Error(
                            'Unable to load page.'
                        );

                    }


                    return response.text();

                }
            )

            .then(
                function (html) {

                    contentArea.innerHTML =
                        html;


                    /*
                    |--------------------------------------------------------------------------
                    | Execute scripts returned by loaded page
                    |--------------------------------------------------------------------------
                    */

                    const scripts =
                        contentArea.querySelectorAll(
                            'script'
                        );


                    scripts.forEach(
                        function (oldScript) {

                            const newScript =
                                document.createElement(
                                    'script'
                                );


                            Array.from(
                                oldScript.attributes
                            )
                            .forEach(
                                function (attribute) {

                                    newScript.setAttribute(
                                        attribute.name,
                                        attribute.value
                                    );

                                }
                            );


                            newScript.textContent =
                                oldScript.textContent;


                            oldScript.replaceWith(
                                newScript
                            );

                        }
                    );

                }
            )

            .catch(
                function (error) {

                    contentArea.innerHTML = `

                        <div class="page-error">

                            <div class="page-error-icon">
                                !
                            </div>

                            <h2>
                                Unable to load this page
                            </h2>

                            <p>
                                ${error.message}
                            </p>

                            <button
                                type="button"
                                onclick="location.reload()"
                            >
                                Reload Dashboard
                            </button>

                        </div>

                    `;

                }
            );


            /*
            |--------------------------------------------------------------------------
            | Browser History
            |--------------------------------------------------------------------------
            */

            if (pushState) {

                history.pushState(
                    {
                        page: page,
                        params: params
                    },
                    '',
                    buildPageUrl(
                        page,
                        params
                    )
                );

            }


            /*
            |--------------------------------------------------------------------------
            | Close mobile sidebar
            |--------------------------------------------------------------------------
            */

            sidebar.classList.remove(
                'mobile-open'
            );

            sidebarOverlay.classList.remove(
                'active'
            );

        }


        /*
        |--------------------------------------------------------------------------
        | GLOBAL ADMIN PAGE LOADER
        |--------------------------------------------------------------------------
        |
        | This is the important fix.
        |
        | Submissions page can now call:
        |
        | window.loadAdminPage(
        |     'submission_review',
        |     { id: 123 }
        | );
        |
        |--------------------------------------------------------------------------
        */

        window.loadAdminPage =
            function (
                page,
                params = {}
            ) {

                loadPage(
                    page,
                    true,
                    params
                );

            };


        /*
        |--------------------------------------------------------------------------
        | Navigation Click
        |--------------------------------------------------------------------------
        */

        navItems.forEach(
            function (item) {

                item.addEventListener(
                    'click',
                    function () {

                        const page =
                            this.dataset.page;


                        loadPage(
                            page,
                            true,
                            {}
                        );

                    }
                );

            }
        );


        /*
        |--------------------------------------------------------------------------
        | Browser Back / Forward
        |--------------------------------------------------------------------------
        */

        window.addEventListener(
            'popstate',
            function (event) {

                const state =
                    event.state;


                const page =
                    state?.page ||
                    'dashboard';


                const params =
                    state?.params ||
                    {};


                loadPage(
                    page,
                    false,
                    params
                );

            }
        );


        /*
        |--------------------------------------------------------------------------
        | Mobile Menu
        |--------------------------------------------------------------------------
        */

        menuButton.addEventListener(
            'click',
            function () {

                sidebar.classList.add(
                    'mobile-open'
                );


                sidebarOverlay.classList.add(
                    'active'
                );

            }
        );


        sidebarClose.addEventListener(
            'click',
            function () {

                sidebar.classList.remove(
                    'mobile-open'
                );


                sidebarOverlay.classList.remove(
                    'active'
                );

            }
        );


        sidebarOverlay.addEventListener(
            'click',
            function () {

                sidebar.classList.remove(
                    'mobile-open'
                );


                sidebarOverlay.classList.remove(
                    'active'
                );

            }
        );


        /*
        |--------------------------------------------------------------------------
        | Initial Page
        |--------------------------------------------------------------------------
        */

        const urlParams =
            new URLSearchParams(
                window.location.search
            );


        const initialPage =
            urlParams.get('page')
            || 'dashboard';


        /*
        |--------------------------------------------------------------------------
        | Preserve URL Parameters
        |--------------------------------------------------------------------------
        */

        const initialParams = {};


        urlParams.forEach(
            function (value, key) {

                if (key !== 'page') {

                    initialParams[key] =
                        value;

                }

            }
        );


        loadPage(
            initialPage,
            false,
            initialParams
        );

    }
);

</script>


</body>

</html>