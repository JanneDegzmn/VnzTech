<?php

require_once __DIR__ . '/../backend/database.php';
require_once __DIR__ . '/../backend/session.php';
require_once __DIR__ . '/../backend/auth.php';
require_once __DIR__ . '/../backend/csrf.php';

requireAdminLogin();

$currentAdmin = getCurrentAdmin();

if (!$currentAdmin) {
    header('Location: login.php');
    exit;
}

$csrfToken = generateCsrfToken();

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
        href="/VNZ-TECH/assets/css/admin_dashboard.css?v=<?= filemtime(__DIR__ . '/../assets/css/admin_dashboard.css') ?>"
    >
    <link
        rel="stylesheet"
        href="/VNZ-TECH/assets/css/admin_theme.css?v=<?= filemtime(__DIR__ . '/../assets/css/admin_theme.css') ?>"
    >
    <script>
        (function () {
            try {
                const theme = localStorage.getItem('vnz-admin-theme');
                if (theme === 'light') {
                    document.documentElement.setAttribute('data-admin-theme', 'light');
                }
            } catch (error) {
                console.error('Unable to load the admin theme preference.', error);
            }
        })();
    </script>
    <script
        src="/VNZ-TECH/assets/js/admin-theme.js?v=<?= filemtime(__DIR__ . '/../assets/js/admin-theme.js') ?>"
        defer
    ></script>
    <link
        rel="stylesheet"
        href="/VNZ-TECH/assets/css/transaction_chat.css?v=<?= filemtime(__DIR__ . '/../assets/css/transaction_chat.css') ?>"
    >
    <script
        src="/VNZ-TECH/assets/js/transaction-chat.js?v=<?= filemtime(__DIR__ . '/../assets/js/transaction-chat.js') ?>"
        defer
    ></script>
    <script
        src="/VNZ-TECH/assets/js/floating-messages.js?v=<?= filemtime(__DIR__ . '/../assets/js/floating-messages.js') ?>"
        defer
    ></script>

    <?php include __DIR__ . '/../includes/loader-head.php'; ?>

</head>


<body>

<?php include __DIR__ . '/../includes/loader-body.php'; ?>

<?php if (
    ($_GET['security_reminder'] ?? '') === '1'
    && !empty($_SESSION['admin_2fa_reminder'])
    && !empty($currentAdmin['totp_enabled_at'])
    && (int) ($currentAdmin['totp_login_required'] ?? 0) !== 1
): ?>
    <div class="admin-2fa-reminder-backdrop" data-admin-2fa-reminder role="presentation">
        <section class="admin-2fa-reminder-modal" role="dialog" aria-modal="true" aria-labelledby="admin2faReminderTitle">
            <span class="admin-2fa-reminder-icon" aria-hidden="true">!</span>
            <h2 id="admin2faReminderTitle">Use a 6-digit code at login?</h2>
            <p>Your authenticator is set up. Choose whether to require its 6-digit code every time you sign in. You can change this later in Settings.</p>
            <label class="admin-2fa-reminder-choice">
                <input type="checkbox" data-admin-2fa-dont-show>
                <span>Don't show again</span>
            </label>
            <div class="admin-2fa-reminder-actions">
                <button type="button" data-admin-2fa-login-choice="0">Continue without code</button>
                <button type="button" data-admin-2fa-login-choice="1">Require code every login</button>
            </div>
        </section>
    </div>
    <script>
    (function () {
        const reminder = document.querySelector('[data-admin-2fa-reminder]');
        if (!reminder) return;

        reminder.querySelectorAll('[data-admin-2fa-login-choice]').forEach(button => {
            button.addEventListener('click', async function () {
                button.disabled = true;
                try {
                    const data = new FormData();
                    data.set('csrf_token', <?= json_encode(generateCsrfToken(), JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>);
                    data.set('action', 'set_2fa_login_requirement');
                    data.set('login_required', button.dataset.admin2faLoginChoice);
                    if (reminder.querySelector('[data-admin-2fa-dont-show]').checked) {
                        data.set('dont_show_again', '1');
                    }
                    const response = await fetch('/VNZ-TECH/backend/update_admin_settings.php', {
                        method: 'POST',
                        body: new URLSearchParams(data).toString(),
                        headers: {
                            'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8',
                            'Accept': 'application/json'
                        },
                        credentials: 'same-origin'
                    });
                    const result = await response.json();
                    if (!response.ok || !result.success) {
                        throw new Error(result.message || 'Unable to save the sign-in preference.');
                    }
                    reminder.remove();
                } catch (error) {
                    button.disabled = false;
                    await window.VNZDialog.alert({
                        title: 'Unable to Save Sign-In Preference',
                        message: error.message || 'Unable to save the sign-in preference.',
                        confirmLabel: 'Close'
                    });
                }
            });
        });
    })();
    </script>

<?php endif; ?>

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

                <span
                    class="admin-notification-badge"
                    id="submissionsBadge"
                    hidden
                >0</span>

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

                <span
                    class="admin-notification-badge"
                    id="transactionsBadge"
                    hidden
                >0</span>

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


            <?php if (hasPermission('manage_trades')): ?>

                <button
                    type="button"
                    class="nav-item"
                    data-page="trade_items"
                >

                    <span class="nav-icon">◉</span>

                    <span class="nav-text">
                        Trade Items
                    </span>

                </button>

                <button
                    type="button"
                    class="nav-item"
                    data-page="trade_requests"
                >

                    <span class="nav-icon">⇄</span>

                    <span class="nav-text">
                        Trade Requests
                    </span>

                    <span
                        class="admin-notification-badge"
                        id="tradeRequestsBadge"
                        hidden
                    >0</span>

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
                aria-label="Log out of the admin account"
                data-loader
            >
                <span aria-hidden="true">↪</span>
                <span>Logout</span>
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


    <div
        class="admin-submission-modal"
        id="adminSubmissionModal"
        aria-hidden="true"
    >
        <div
            class="admin-submission-modal-backdrop"
            data-close-admin-submission-modal
        ></div>
        <section
            class="admin-submission-modal-panel"
            role="dialog"
            aria-modal="true"
            aria-labelledby="adminSubmissionModalTitle"
        >
            <header class="admin-submission-modal-header">
                <h2 id="adminSubmissionModalTitle">Submission Review</h2>
                <button
                    type="button"
                    class="admin-submission-modal-close"
                    data-close-admin-submission-modal
                    aria-label="Close submission review"
                >
                    ×
                </button>
            </header>
            <div class="admin-submission-modal-body" id="adminSubmissionModalBody">
                <div class="admin-submission-modal-loading">Loading submission...</div>
            </div>
        </section>
    </div>



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

        const submissionModal =
            document.getElementById('adminSubmissionModal');

        const submissionModalBody =
            document.getElementById('adminSubmissionModalBody');

        let submissionModalId = null;
        let submissionModalOrigin = 'submissions';
        let submissionModalNeedsRefresh = false;
        let activePageRequestId = 0;
        let contentAnimationTimer = null;

                /*
        |--------------------------------------------------------------------------
        | ADMIN NOTIFICATIONS
        |--------------------------------------------------------------------------
        */

        const submissionsBadge =
            document.getElementById(
                'submissionsBadge'
            );

        const transactionsBadge =
            document.getElementById(
                'transactionsBadge'
            );

        const tradeRequestsBadge =
            document.getElementById(
                'tradeRequestsBadge'
            );

        const adminCsrfToken =
            <?= json_encode(
                $csrfToken,
                JSON_UNESCAPED_UNICODE |
                JSON_UNESCAPED_SLASHES
            ) ?>;

        const ADMIN_NOTIFICATION_POLL_INTERVAL_MS =
            4000;

        let notificationCheckInProgress =
            false;


        function updateNotificationBadge(
            badgeElement,
            count
        ) {

            if (!badgeElement) {
                return;
            }


            const safeCount =
                Math.max(
                    0,
                    Number(count) || 0
                );


            if (safeCount <= 0) {

                badgeElement.hidden =
                    true;

                badgeElement.textContent =
                    '0';

                return;
            }


            badgeElement.hidden =
                false;


            badgeElement.textContent =
                safeCount > 99
                    ? '99+'
                    : String(safeCount);

        }


        async function refreshAdminNotificationCounts() {

            if (
                notificationCheckInProgress
            ) {
                return;
            }


            notificationCheckInProgress =
                true;


            try {

                const response =
                    await fetch(
                        '/VNZ-TECH/backend/get_unseen_admin_counts.php',
                        {
                            method: 'GET',

                            credentials:
                                'same-origin',

                            cache:
                                'no-store'
                        }
                    );


                if (!response.ok) {

                    throw new Error(
                        'Unable to load admin notification counts.'
                    );

                }


                const data =
                    await response.json();


                if (!data.success) {

                    throw new Error(
                        data.message
                        ||
                        'Unable to load admin notification counts.'
                    );

                }


                updateNotificationBadge(
                    submissionsBadge,
                    data.submissions_count
                );


                updateNotificationBadge(
                    transactionsBadge,
                    data.transactions_count
                );

                updateNotificationBadge(
                    tradeRequestsBadge,
                    data.trade_requests_count
                );

            } catch (error) {

                console.warn(
                    'Admin notification check failed:',
                    error
                );

            } finally {

                notificationCheckInProgress =
                    false;

            }

        }


        async function markAdminNotificationsSeen(
            type
        ) {

            const badgeElement =
                type === 'submissions'
                    ? submissionsBadge
                    : transactionsBadge;


            updateNotificationBadge(
                badgeElement,
                0
            );


            try {

                const body =
                    new URLSearchParams();


                body.set(
                    'type',
                    type
                );


                body.set(
                    'csrf_token',
                    adminCsrfToken
                );


                const response =
                    await fetch(
                        '/VNZ-TECH/backend/mark_admin_notifications_seen.php',
                        {
                            method: 'POST',

                            headers: {
                                'Content-Type':
                                    'application/x-www-form-urlencoded; charset=UTF-8'
                            },

                            credentials:
                                'same-origin',

                            body:
                                body.toString(),

                            cache:
                                'no-store'
                        }
                    );


                if (!response.ok) {

                    throw new Error(
                        'Unable to mark admin notifications as seen.'
                    );

                }


                const data =
                    await response.json();


                if (!data.success) {

                    throw new Error(
                        data.message
                        ||
                        'Unable to mark admin notifications as seen.'
                    );

                }

            } catch (error) {

                console.warn(
                    'Admin notification mark-seen failed:',
                    error
                );

            }

        }


        setInterval(
            refreshAdminNotificationCounts,
            ADMIN_NOTIFICATION_POLL_INTERVAL_MS
        );


        refreshAdminNotificationCounts();        
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

            trade_items: 'Trade Items',

            trade_requests: 'Trade Requests',

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

        function finishPageTransition() {

            contentArea.classList.remove(
                'is-loading',
                'is-entering'
            );

            contentArea.setAttribute(
                'aria-busy',
                'false'
            );

            void contentArea.offsetWidth;

            contentArea.classList.add(
                'is-entering'
            );

            contentAnimationTimer =
                window.setTimeout(
                    function () {

                        contentArea.classList.remove(
                            'is-entering'
                        );

                        contentAnimationTimer =
                            null;

                    },
                    320
                );

        }


        function loadPage(
            page,
            pushState = true,
            params = {},
            paginationTop = null
        ) {

            const focusedElement = contentArea.contains(document.activeElement)
                ? document.activeElement
                : null;
            const focusedElementId = focusedElement?.id || '';
            const selectionStart = focusedElement
                && typeof focusedElement.selectionStart === 'number'
                ? focusedElement.selectionStart
                : null;
            const selectionEnd = focusedElement
                && typeof focusedElement.selectionEnd === 'number'
                ? focusedElement.selectionEnd
                : null;
            const requestId =
                ++activePageRequestId;


            if (contentAnimationTimer) {

                window.clearTimeout(
                    contentAnimationTimer
                );

                contentAnimationTimer =
                    null;

            }


            contentArea.classList.remove(
                'is-entering'
            );

            contentArea.classList.add(
                'is-loading'
            );

            contentArea.setAttribute(
                'aria-busy',
                'true'
            );


            if (paginationTop === null) {
                contentArea.innerHTML = `
                    <div class="content-loading">
                        <div class="loading-spinner"></div>
                        <span>Loading...</span>
                    </div>
                `;
            }


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

                    if (
                        requestId !==
                        activePageRequestId
                    ) {

                        return;

                    }


                    contentArea.innerHTML =
                        html;

                    finishPageTransition();


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

                    if (focusedElementId) {
                        const replacement = document.getElementById(focusedElementId);
                        replacement?.focus({preventScroll: true});
                        if (
                            replacement
                            && selectionStart !== null
                            && selectionEnd !== null
                            && typeof replacement.setSelectionRange === 'function'
                        ) {
                            replacement.setSelectionRange(selectionStart, selectionEnd);
                        }
                    }

                    if (paginationTop !== null) {
                        const replacementPagination = contentArea.querySelector('.vnz-pagination');
                        if (replacementPagination) {
                            const offset = replacementPagination.getBoundingClientRect().top - paginationTop;
                            window.scrollTo({
                                top: window.scrollY + offset,
                                left: window.scrollX,
                                behavior: 'auto'
                            });
                        }
                    }

                }
            )

            .catch(
                function (error) {

                    if (
                        requestId !==
                        activePageRequestId
                    ) {

                        return;

                    }


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

                    finishPageTransition();

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


        async function loadSubmissionModalContent(submissionId) {
            if (!submissionModalBody || !submissionId) {
                return;
            }

            submissionModalBody.innerHTML =
                '<div class="admin-submission-modal-loading">Loading submission...</div>';

            try {
                const response = await fetch(
                    'pages/submission_review.php?id=' +
                    encodeURIComponent(submissionId) +
                    '&modal=1',
                    {
                        method: 'GET',
                        credentials: 'same-origin',
                        headers: {
                            'X-Requested-With': 'XMLHttpRequest'
                        }
                    }
                );

                if (!response.ok) {
                    throw new Error('Unable to load submission details.');
                }

                submissionModalBody.innerHTML = await response.text();

                submissionModalBody.querySelectorAll('script').forEach((oldScript) => {
                    const newScript = document.createElement('script');
                    Array.from(oldScript.attributes).forEach((attribute) => {
                        newScript.setAttribute(attribute.name, attribute.value);
                    });
                    newScript.textContent = oldScript.textContent;
                    oldScript.replaceWith(newScript);
                });
            } catch (error) {
                submissionModalBody.innerHTML =
                    '<div class="admin-submission-modal-error">' +
                    '<strong>Unable to load submission</strong>' +
                    '<p>Please close this window and try again.</p>' +
                    '</div>';
            }
        }


        function closeSubmissionModal() {
            if (!submissionModal) {
                return;
            }

            submissionModal.classList.remove('is-open');
            submissionModal.setAttribute('aria-hidden', 'true');
            document.body.classList.remove('admin-submission-modal-open');

            if (submissionModalNeedsRefresh) {
                submissionModalNeedsRefresh = false;
                loadPage(submissionModalOrigin, false, {});
            }
        }


        window.openAdminSubmissionModal = function (submissionId, origin) {
            if (!submissionModal || !submissionModalBody || !submissionId) {
                return;
            }

            submissionModalId = submissionId;
            submissionModalOrigin = origin === 'offers' ? 'offers' : 'submissions';
            submissionModal.classList.add('is-open');
            submissionModal.setAttribute('aria-hidden', 'false');
            document.body.classList.add('admin-submission-modal-open');
            loadSubmissionModalContent(submissionId);
        };


        window.refreshAdminSubmissionModal = function (submissionId) {
            submissionModalNeedsRefresh = true;
            return loadSubmissionModalContent(submissionId || submissionModalId);
        };


        window.closeAdminSubmissionModal = closeSubmissionModal;


        document.querySelectorAll('[data-close-admin-submission-modal]').forEach((element) => {
            element.addEventListener('click', closeSubmissionModal);
        });


        document.addEventListener('keydown', (event) => {
            if (event.key === 'Escape' && submissionModal?.classList.contains('is-open')) {
                closeSubmissionModal();
            }
        });


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

        contentArea.addEventListener('click', function (event) {
            const link = event.target.closest('[data-admin-pagination]');
            if (!link) {
                return;
            }

            event.preventDefault();
            const params = {};
            new URL(link.href, window.location.origin).searchParams.forEach(
                (value, key) => {
                    params[key] = value;
                }
            );
            const pagination = link.closest('.vnz-pagination');
            loadPage(
                link.dataset.adminPage,
                true,
                params,
                pagination ? pagination.getBoundingClientRect().top : null
            );
        });


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


                        if (
                            page === 'submissions' ||
                            page === 'transactions'
                        ) {

                            markAdminNotificationsSeen(
                                page
                            );

                        }


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


        if (
            initialPage === 'submissions' ||
            initialPage === 'transactions'
        ) {

            markAdminNotificationsSeen(
                initialPage
            );

        }


        loadPage(
            initialPage,
            false,
            initialParams
        );

    }
);

</script>

<?php if (hasPermission('view_submissions')): ?>
    <?php require __DIR__ . '/../includes/floating-messages.php'; ?>
<?php endif; ?>

</body>

</html>