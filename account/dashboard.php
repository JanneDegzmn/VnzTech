<?php

require_once __DIR__ . '/../backend/database.php';
require_once __DIR__ . '/../backend/session.php';
require_once __DIR__ . '/../backend/csrf.php';
require_once __DIR__ . '/../backend/guest_submission_onboarding.php';


/*
|--------------------------------------------------------------------------
| AUTHENTICATION
|--------------------------------------------------------------------------
*/

if (
    empty($_SESSION['user_logged_in']) ||
    empty($_SESSION['user_id'])
) {
    header(
        'Location: /VNZ-TECH/account/login.php'
    );

    exit;
}


$userId = (int) $_SESSION['user_id'];


if ($userId <= 0) {

    session_unset();
    session_destroy();

    header(
        'Location: /VNZ-TECH/account/login.php'
    );

    exit;
}


/*
|--------------------------------------------------------------------------
| CURRENT SELLER ACCOUNT
|--------------------------------------------------------------------------
*/

try {

    $sellerAccountStmt = $pdo->prepare("
        SELECT
            id,
            full_name,
            mobile,
            email,
            email_verified_at,
            address,
            profile_picture,
            language,
            is_active,
            totp_enabled_at,
            totp_login_required
        FROM users
        WHERE id = ?
        LIMIT 1
    ");

    $sellerAccountStmt->execute([
        $userId
    ]);

    $sellerAccount =
        $sellerAccountStmt->fetch();


    if (!$sellerAccount) {

        session_unset();
        session_destroy();

        header(
            'Location: /VNZ-TECH/account/login.php'
        );

        exit;
    }


    if (
        (int) $sellerAccount['is_active'] !== 1
    ) {

        session_unset();
        session_destroy();

        header(
            'Location: /VNZ-TECH/account/login.php'
        );

        exit;
    }

} catch (Throwable $e) {

    http_response_code(500);

    exit(
        'Unable to load seller account.'
    );

}


/*
|--------------------------------------------------------------------------
| PAGE
|--------------------------------------------------------------------------
*/

$page =
    strtolower(
        trim(
            $_GET['page'] ?? 'dashboard'
        )
    );


/*
|--------------------------------------------------------------------------
| ALLOWED PAGES
|--------------------------------------------------------------------------
*/

$allowedPages = [
    'dashboard',
    'submissions',
    'submission',
    'offers',
    'trade_history',
    'profile',
    'transactions',
    'transaction',
    'settings'
];


if (
    !in_array(
        $page,
        $allowedPages,
        true
    )
) {

    $page =
        'dashboard';

}


/*
|--------------------------------------------------------------------------
| PAGE TITLES
|--------------------------------------------------------------------------
*/

$pageTitles = [
    'dashboard' => 'Dashboard',
    'submissions' => 'My Submissions',
    'submission' => 'Submission Details',
    'offers' => 'Offers',
    'trade_history' => 'Trade History',
    'profile' => 'Profile',
    'transactions' => 'Transactions',
    'transaction' => 'Transaction Details',
    'settings' => 'Settings'
];


/*
|--------------------------------------------------------------------------
| Initial Alert (from full-page redirects, e.g. submit_submission.php)
|--------------------------------------------------------------------------
|
| Backend endpoints that can't respond with JSON (because they do a
| full page redirect, like submit_submission.php after a multipart
| form POST) communicate errors back to the user via ?error=... on
| the redirect URL. Previously this value was silently ignored by
| the frontend - it sat in the URL but nothing rendered it, so the
| user had no idea why their action didn't go through.
|
| This is read once here and handed to JS below, which shows it in
| a reusable alert modal (#sellerAlertModal) instead of leaving it
| invisible in the query string.
|
*/

$initialAlertMessage =
    isset($_GET['error'])
        ? trim((string) $_GET['error'])
        : '';
$sellerLanguage = ($sellerAccount['language'] ?? 'en') === 'fil'
    ? 'fil'
    : 'en';
$guestSubmissionOnboarding = vnzGuestSubmissionOnboardingForSeller($_SESSION, $sellerAccount);

?>
<!DOCTYPE html>
<html lang="<?= htmlspecialchars($sellerLanguage, ENT_QUOTES, 'UTF-8') ?>" data-seller-language="<?= htmlspecialchars($sellerLanguage, ENT_QUOTES, 'UTF-8') ?>">

<head>

    <?php if ($guestSubmissionOnboarding !== null): ?>
        <link rel="stylesheet" href="/VNZ-TECH/assets/css/guest_submission_claim.css?v=<?= filemtime(__DIR__ . '/../assets/css/guest_submission_claim.css') ?>">
        <script src="/VNZ-TECH/assets/js/guest-submission-claim.js?v=<?= filemtime(__DIR__ . '/../assets/js/guest-submission-claim.js') ?>" defer></script>
    <?php endif; ?>

    <?php include __DIR__ . '/../includes/loader-head.php'; ?>

    <meta charset="UTF-8">

    <meta
        name="viewport"
        content="width=device-width, initial-scale=1.0"
    >

    <title>
        <?= htmlspecialchars(
            $pageTitles[$page]
                ?? 'Dashboard',
            ENT_QUOTES,
            'UTF-8'
        ) ?>
        | VNZ TECH
    </title>


    <link
        rel="stylesheet"
        href="/VNZ-TECH/assets/css/seller_dashboard.css?v=<?= filemtime(__DIR__ . '/../assets/css/seller_dashboard.css') ?>"
    >
    <link
        rel="stylesheet"
        href="/VNZ-TECH/assets/css/transaction_chat.css?v=<?= filemtime(__DIR__ . '/../assets/css/transaction_chat.css') ?>"
    >
    <link
        rel="stylesheet"
        href="/VNZ-TECH/assets/css/seller_theme.css?v=<?= filemtime(__DIR__ . '/../assets/css/seller_theme.css') ?>"
    >
    <link
        rel="stylesheet"
        href="/VNZ-TECH/assets/css/seller_alert.css?v=<?= filemtime(__DIR__ . '/../assets/css/seller_alert.css') ?>"
    >
    <script
        src="/VNZ-TECH/assets/js/transaction-chat.js?v=<?= filemtime(__DIR__ . '/../assets/js/transaction-chat.js') ?>"
        defer
    ></script>
    <script
        src="/VNZ-TECH/assets/js/floating-messages.js?v=<?= filemtime(__DIR__ . '/../assets/js/floating-messages.js') ?>"
        defer
    ></script>
    <script
        src="/VNZ-TECH/assets/js/seller-language-copy.js?v=<?= filemtime(__DIR__ . '/../assets/js/seller-language-copy.js') ?>"
        defer
    ></script>
    <script
        src="/VNZ-TECH/assets/js/seller-language.js?v=<?= filemtime(__DIR__ . '/../assets/js/seller-language.js') ?>"
        defer
    ></script>
    <script
        src="/VNZ-TECH/assets/js/seller-theme.js?v=<?= filemtime(__DIR__ . '/../assets/js/seller-theme.js') ?>"
        defer
    ></script>
    <script>
        try {
            const savedSellerTheme = localStorage.getItem('vnz-seller-theme');
            if (savedSellerTheme === 'light') {
                document.documentElement.dataset.sellerTheme = 'light';
            }
        } catch (error) {
            console.warn('Unable to read the saved Seller portal theme.', error);
        }
    </script>

</head>


<body>

<?php include __DIR__ . '/../includes/loader-body.php'; ?>

<?php if (
    ($_GET['security_reminder'] ?? '') === '1'
    && !empty($_SESSION['seller_2fa_reminder'])
): ?>
    <div class="seller-2fa-reminder-backdrop" data-seller-2fa-reminder role="presentation">
        <section class="seller-2fa-reminder-modal" role="dialog" aria-modal="true" aria-labelledby="seller2faReminderTitle">
            <span class="seller-2fa-reminder-icon" aria-hidden="true">!</span>
            <?php if (!empty($sellerAccount['totp_enabled_at'])): ?>
                <h2 id="seller2faReminderTitle">Use a 6-digit code at login?</h2>
                <p>Your authenticator is set up. Choose whether to require its 6-digit code every time you sign in. You can change this later in Settings.</p>
            <?php else: ?>
                <h2 id="seller2faReminderTitle">Secure your seller account</h2>
                <p>Set up an authenticator for password recovery. You can choose separately whether you want a 6-digit code at every login.</p>
            <?php endif; ?>
            <label class="seller-2fa-reminder-choice">
                <input type="checkbox" data-seller-2fa-dont-show>
                <span>Don't show again</span>
            </label>
            <div class="seller-2fa-reminder-actions">
                <?php if (!empty($sellerAccount['totp_enabled_at'])): ?>
                    <button type="button" class="seller-2fa-reminder-later" data-seller-2fa-login-choice="0">Continue without code</button>
                    <button type="button" data-seller-2fa-login-choice="1">Require code every login</button>
                <?php else: ?>
                    <button type="button" class="seller-2fa-reminder-later" data-seller-2fa-remind-later>Remind me later</button>
                    <a href="/VNZ-TECH/account/dashboard.php?page=settings" data-seller-2fa-setup>Set up 2FA</a>
                <?php endif; ?>
            </div>
        </section>
    </div>
    <script>
    (function () {
        const reminder = document.querySelector('[data-seller-2fa-reminder]');
        const dismissButton = document.querySelector('[data-seller-2fa-remind-later]');
        if (!reminder) return;

        async function saveReminder(action, loginRequired = null, button = null, redirectUrl = null) {
            if (button) button.disabled = true;
            try {
                const data = new FormData();
                data.set('action', action);
                data.set('csrf_token', <?= json_encode(generateCsrfToken(), JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>);
                if (document.querySelector('[data-seller-2fa-dont-show]').checked) {
                    data.set('dont_show_again', '1');
                }
                if (loginRequired !== null) {
                    data.set('login_required', String(loginRequired));
                }
                const response = await fetch('/VNZ-TECH/backend/seller_2fa.php', {
                    method: 'POST',
                    body: data,
                    headers: {
                        'Accept': 'application/json',
                        'X-Requested-With': 'XMLHttpRequest'
                    },
                    credentials: 'same-origin'
                });
                const result = await response.json();
                if (!response.ok || !result.success) {
                    throw new Error(result.message || 'Unable to save your reminder preference.');
                }
                if (redirectUrl) {
                    window.location.href = redirectUrl;
                } else {
                    reminder.remove();
                }
            } catch (error) {
                if (button) button.disabled = false;
                await window.VNZDialog.alert({
                    title: 'Unable to Save Reminder Preference',
                    message: error.message || 'Unable to save your reminder preference.',
                    confirmLabel: 'Close'
                });
            }
        }

        if (dismissButton) {
            dismissButton.addEventListener('click', function () {
                saveReminder('dismiss_reminder', null, dismissButton);
            });
        }

        const setupLink = reminder.querySelector('[data-seller-2fa-setup]');
        if (setupLink) {
            setupLink.addEventListener('click', function (event) {
                event.preventDefault();
                saveReminder(
                    'dismiss_reminder',
                    null,
                    setupLink,
                    setupLink.href
                );
            });
        }

        reminder.querySelectorAll('[data-seller-2fa-login-choice]').forEach(button => {
            button.addEventListener('click', function () {
                saveReminder(
                    'set_login_requirement',
                    button.dataset.seller2faLoginChoice,
                    button
                );
            });
        });
    })();
    </script>
<?php endif; ?>

<div class="dashboard-layout">


    <!-- =====================================================
         SIDEBAR
    ====================================================== -->

    <aside class="dashboard-sidebar" id="sellerDashboardSidebar">

        <div class="sidebar-brand">

            <div class="brand-logo">
                VNZ
            </div>


            <div class="brand-text">

                <strong>
                    VNZ TECH
                </strong>

                <span>
                    SELLER PORTAL
                </span>

            </div>

        </div>


        <nav class="sidebar-nav">

            <button
                type="button"
                class="nav-item <?= $page === 'dashboard' ? 'active' : '' ?>"
                data-page="dashboard"
            >

                <span class="nav-icon">
                    ⌂
                </span>

                <span>
                    Dashboard
                </span>

            </button>


            <button
                type="button"
                class="nav-item <?= in_array($page, ['submissions', 'submission'], true) ? 'active' : '' ?>"
                data-page="submissions"
            >

                <span class="nav-icon">
                    ▣
                </span>

                <span>
                    My Submissions
                </span>

            </button>


            <button
                type="button"
                class="nav-item <?= $page === 'offers' ? 'active' : '' ?>"
                data-page="offers"
            >

                <span class="nav-icon">
                    ◈
                </span>

                <span>
                    Offers
                </span>

            </button>


            <button
                type="button"
                class="nav-item <?= $page === 'transactions' ? 'active' : '' ?>"
                data-page="transactions"
            >
                <span class="nav-icon">▤</span>
                <span>Transactions</span>
            </button>

            <button
                type="button"
                class="nav-item <?= $page === 'trade_history' ? 'active' : '' ?>"
                data-page="trade_history"
            >
                <span class="nav-icon">⇄</span>
                <span>Trade History</span>
            </button>


            <div class="sidebar-divider"></div>


            <button
                type="button"
                class="nav-item <?= $page === 'profile' ? 'active' : '' ?>"
                data-page="profile"
            >

                <span class="nav-icon">
                    ◎
                </span>

                <span>
                    Profile
                </span>

            </button>


            <button
                type="button"
                class="nav-item <?= $page === 'settings' ? 'active' : '' ?>"
                data-page="settings"
            >

                <span class="nav-icon">
                    ⚙
                </span>

                <span>
                    Settings
                </span>

            </button>

        </nav>


        <div class="sidebar-bottom">

            <a
                href="/VNZ-TECH/account/logout.php"
                class="logout-button"
                data-vnz-confirm="<?= $sellerLanguage === 'fil' ? 'Mag-logout ka na ba sa account mo?' : 'Are you sure you want to log out of your account?' ?>"
                data-vnz-confirm-title="<?= $sellerLanguage === 'fil' ? 'Mag-logout?' : 'Log out?' ?>"
                data-vnz-confirm-label="<?= $sellerLanguage === 'fil' ? 'Mag-logout' : 'Log out' ?>"
                data-vnz-confirm-cancel-label="<?= $sellerLanguage === 'fil' ? 'Wag muna' : 'Stay signed in' ?>"
                data-vnz-confirm-danger="true"
            >

                <span class="nav-icon">
                    ↪
                </span>

                <span>
                    Logout
                </span>

            </a>

        </div>

    </aside>

    <button
        type="button"
        class="seller-mobile-menu-button"
        id="sellerMobileMenuButton"
        aria-label="Open navigation menu"
        aria-controls="sellerDashboardSidebar"
        aria-expanded="false"
    >
        <span aria-hidden="true">☰</span>
    </button>

    <div
        class="seller-sidebar-overlay"
        id="sellerSidebarOverlay"
        aria-hidden="true"
    ></div>


    <!-- =====================================================
         MAIN CONTENT
    ====================================================== -->

    <main class="dashboard-main">

        <div id="contentArea">

            <div class="content-loading">
                Loading...
            </div>

        </div>

        <?php if ($guestSubmissionOnboarding !== null): ?>
            <?php require __DIR__ . '/../includes/guest-submission-claim-prompt.php'; ?>
        <?php endif; ?>

    </main>

</div>


<!-- =====================================================
     SELL ITEM MODAL
====================================================== -->

<div
    id="sellerSellModal"
    class="seller-sell-modal"
    aria-hidden="true"
>

    <div class="seller-sell-modal-panel">


        <div class="seller-sell-modal-header">

            <div class="seller-sell-modal-title">

                <span class="seller-sell-modal-dot"></span>

                SELL TO VNZ TECH

            </div>


            <button
                type="button"
                id="sellerSellModalClose"
                class="seller-sell-modal-close"
                aria-label="Close"
            >
                ×
            </button>

        </div>


        <div class="seller-sell-modal-body">

            <div class="seller-form-wrap">


                <div class="seller-form-intro">

                    <h2>
                        Sell an Item
                    </h2>

                    <p>
                        Submit your phone or scrap item details
                        and our team will review your submission.
                    </p>

                </div>


                <div
                    id="sellerFormMessage"
                    class="seller-form-message"
                ></div>


                <form
                    id="sellerSubmissionForm"
                    action="/VNZ-TECH/backend/submit_submission.php"
                    method="POST"
                    enctype="multipart/form-data"
                >

                    <!--
                        Marks this as an AUTHENTICATED ACCOUNT-PORTAL
                        submission. The backend only attaches a
                        submission to the logged-in seller's account
                        when this value is "account" AND a valid
                        session exists — see backend/submit_submission.php.
                        Without this field, this submission would be
                        (correctly, but unintentionally) treated as
                        a guest submission instead.
                    -->
                    <input
                        type="hidden"
                        name="submission_source"
                        value="account"
                    >


                    <div class="seller-form-section">

                        <div class="seller-form-section-title">
                            Seller Information
                        </div>


                        <div class="seller-account-info">


                            <div class="seller-account-info-card">

                                <span>
                                    Full Name
                                </span>

                                <strong>

                                    <?= htmlspecialchars(
                                        $sellerAccount['full_name'] ?? '',
                                        ENT_QUOTES,
                                        'UTF-8'
                                    ) ?>

                                </strong>

                            </div>


                            <div class="seller-account-info-card">

                                <span>
                                    Email
                                </span>

                                <strong>

                                    <?= htmlspecialchars(
                                        $sellerAccount['email'] ?? '',
                                        ENT_QUOTES,
                                        'UTF-8'
                                    ) ?>

                                </strong>

                                <small>

                                    <?php if (!empty($sellerAccount['email_verified_at'])): ?>
                                        Verified email. Submission receipts can be sent to this address.
                                    <?php else: ?>
                                        This email is not verified. Submission receipts will not be sent until it is verified.
                                    <?php endif; ?>

                                </small>

                            </div>


                            <div class="seller-account-info-card">

                                <span>
                                    Mobile
                                </span>

                                <strong>

                                    <?= !empty(
                                        $sellerAccount['mobile']
                                    )
                                        ? htmlspecialchars(
                                            $sellerAccount['mobile'],
                                            ENT_QUOTES,
                                            'UTF-8'
                                        )
                                        : 'Not provided'
                                    ?>

                                </strong>

                            </div>


                            <div class="seller-account-info-card">

                                <span>
                                    Address
                                </span>

                                <strong>

                                    <?php if (
                                        !empty(
                                            $sellerAccount['address']
                                        )
                                    ): ?>

                                        <?= htmlspecialchars(
                                            $sellerAccount['address'],
                                            ENT_QUOTES,
                                            'UTF-8'
                                        ) ?>

                                    <?php else: ?>

                                        Please complete your address
                                        in Profile.

                                    <?php endif; ?>

                                </strong>

                            </div>


                        </div>


                        <div class="seller-account-sync-note">

                            Seller information is automatically
                            synced with your VNZ TECH account.

                            To change your name, mobile number,
                            or address, update your
                            <a
                                href="?page=profile"
                                data-seller-page="profile"
                            >
                                Profile
                            </a>.

                        </div>


                        <input
                            type="hidden"
                            name="seller_name"
                            value="<?= htmlspecialchars(
                                $sellerAccount['full_name'] ?? '',
                                ENT_QUOTES,
                                'UTF-8'
                            ) ?>"
                        >


                        <input
                            type="hidden"
                            name="seller_mobile"
                            value="<?= htmlspecialchars(
                                $sellerAccount['mobile'] ?? '',
                                ENT_QUOTES,
                                'UTF-8'
                            ) ?>"
                        >


                        <input
                            type="hidden"
                            name="seller_address"
                            value="<?= htmlspecialchars(
                                $sellerAccount['address'] ?? '',
                                ENT_QUOTES,
                                'UTF-8'
                            ) ?>"
                        >


                        <div
                            class="seller-form-group full"
                            style="margin-top: 15px;"
                        >

                            <label>
                                Selling Method
                            </label>


                            <div class="seller-form-methods">


                                <div
                                    class="seller-form-method-option"
                                >

                                    <input
                                        type="radio"
                                        id="method_per_piece"
                                        name="selling_method"
                                        value="per_piece"
                                        checked
                                    >


                                    <label
                                        for="method_per_piece"
                                        class="seller-form-method-label"
                                    >
                                        Sell Per Piece
                                    </label>

                                </div>


                                <div
                                    class="seller-form-method-option"
                                >

                                    <input
                                        type="radio"
                                        id="method_wholesale"
                                        name="selling_method"
                                        value="wholesale"
                                    >


                                    <label
                                        for="method_wholesale"
                                        class="seller-form-method-label"
                                    >
                                        Wholesale / Bulk
                                    </label>

                                </div>


                            </div>

                        </div>


                    </div>


                    <div class="seller-form-section">

                        <div class="seller-form-section-title">

                            Item Details

                        </div>


                        <div
                            id="sellerItemsContainer"
                        ></div>


                        <button
                            type="button"
                            class="seller-add-item"
                            id="sellerAddItemBtn"
                        >
                            + Add Another Item
                        </button>

                    </div>


                    <div class="seller-form-section">

                        <div class="seller-form-section-title">

                            Additional Information

                        </div>


                        <div class="seller-form-group">

                            <label for="seller_notes">

                                Seller Notes

                            </label>


                            <textarea
                                id="seller_notes"
                                name="seller_notes"
                                placeholder="Anything else you want our team to know..."
                            ></textarea>

                        </div>

                    </div>


                    <div class="seller-form-disclaimer">

                        By submitting this form, you confirm that
                        the information and photos you provide are
                        accurate to the best of your knowledge.

                        VNZ TECH will review your submission and
                        may contact you regarding an offer.

                    </div>


                    <div class="seller-form-submit-row">

                        <button
                            type="submit"
                            class="seller-form-submit"
                            id="sellerSubmitBtn"
                        >

                            SUBMIT ITEM FOR REVIEW

                        </button>

                    </div>


                </form>

            </div>

        </div>

    </div>

</div>


<!-- =====================================================
     GLOBAL ALERT MODAL
====================================================== -->

<div
    id="sellerAlertModal"
    class="seller-alert-modal"
    data-tone="notice"
    aria-hidden="true"
>

    <div class="seller-alert-modal-panel" role="dialog" aria-modal="true" aria-labelledby="sellerAlertModalTitle" aria-describedby="sellerAlertModalMessage">

        <div class="seller-alert-modal-icon" aria-hidden="true">
            <svg class="seller-alert-icon-notice" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><path d="M12 6v7m0 4v.01"/></svg>
            <svg class="seller-alert-icon-success" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="m6 12 4 4 8-8"/></svg>
        </div>

        <h3 id="sellerAlertModalTitle">
            Heads up
        </h3>

        <p id="sellerAlertModalMessage"></p>

        <div class="seller-alert-modal-actions">

            <button
                type="button"
                id="sellerAlertModalPrimary"
                class="seller-alert-modal-primary"
                style="display: none;"
            ></button>

            <button
                type="button"
                id="sellerAlertModalDismiss"
                class="seller-alert-modal-dismiss"
            >
                Close
            </button>

        </div>

    </div>

</div>

<div
    id="sellerSubmissionDetailsModal"
    class="transaction-details-modal"
    aria-hidden="true"
>
    <div
        class="transaction-details-modal-backdrop"
        data-seller-submission-modal-close
    ></div>

    <div
        class="transaction-details-modal-panel"
        role="dialog"
        aria-modal="true"
        aria-labelledby="sellerSubmissionDetailsModalTitle"
    >
        <div class="transaction-details-modal-header">
            <div>
                <div class="transaction-details-modal-eyebrow">
                    SELLER PORTAL
                </div>
                <h2 id="sellerSubmissionDetailsModalTitle">
                    Submission &amp; negotiation details
                </h2>
            </div>

            <button
                type="button"
                class="transaction-details-modal-close"
                data-seller-submission-modal-close
                aria-label="Close submission details"
            >
                ×
            </button>
        </div>

        <div
            id="sellerSubmissionDetailsModalBody"
            class="transaction-details-modal-body"
        >
            <div class="transaction-details-modal-loading">
                Loading submission details...
            </div>
        </div>
    </div>
</div>


<!-- =====================================================
     PHOTO LIGHTBOX
====================================================== -->

<div
    id="transactionPhotoLightbox"
    class="transaction-photo-lightbox"
    aria-hidden="true"
>

    <div
        class="transaction-photo-lightbox-backdrop"
        data-close-photo-lightbox
    ></div>

    <button
        type="button"
        class="transaction-photo-lightbox-close"
        data-close-photo-lightbox
        aria-label="Close"
    >
        ×
    </button>

    <img
        src=""
        alt=""
        class="transaction-photo-lightbox-img"
    >

</div>

<script>

(function () {


    /* =====================================================
       PAGE SYSTEM
    ====================================================== */

    const contentArea =
        document.getElementById(
            'contentArea'
        );

    const sellerSidebar =
        document.getElementById('sellerDashboardSidebar');
    const sellerMobileMenuButton =
        document.getElementById('sellerMobileMenuButton');
    const sellerSidebarOverlay =
        document.getElementById('sellerSidebarOverlay');

    function closeSellerMobileMenu() {
        sellerSidebar.classList.remove('mobile-open');
        sellerSidebarOverlay.classList.remove('active');
        sellerSidebarOverlay.setAttribute('aria-hidden', 'true');
        sellerMobileMenuButton.setAttribute('aria-expanded', 'false');
        sellerMobileMenuButton.setAttribute('aria-label', 'Open navigation menu');
        document.body.classList.remove('seller-mobile-menu-open');
    }

    sellerMobileMenuButton.addEventListener('click', function () {
        const isOpen = sellerSidebar.classList.toggle('mobile-open');
        sellerSidebarOverlay.classList.toggle('active', isOpen);
        sellerSidebarOverlay.setAttribute('aria-hidden', String(!isOpen));
        sellerMobileMenuButton.setAttribute('aria-expanded', String(isOpen));
        sellerMobileMenuButton.setAttribute(
            'aria-label',
            isOpen ? 'Close navigation menu' : 'Open navigation menu'
        );
        document.body.classList.toggle('seller-mobile-menu-open', isOpen);
    });

    sellerSidebarOverlay.addEventListener('click', closeSellerMobileMenu);
    document.addEventListener('keydown', function (event) {
        if (event.key === 'Escape') {
            closeSellerMobileMenu();
        }
    });
    window.addEventListener('resize', function () {
        if (window.innerWidth > 760) {
            closeSellerMobileMenu();
        }
    });

    const navItems =
        document.querySelectorAll(
            '.sidebar-nav .nav-item[data-page]'
        );


    const pageTitles = {

        dashboard:
            'Dashboard',

        submissions:
            'My Submissions',

        submission:
            'Submission Details',

        offers:
            'Offers',

        transactions:
            'Transactions',

        trade_history:
            'Trade History',

        profile:
            'Profile',

        transaction:
            'Transaction Details',

        settings:
            'Settings'

    };

    let activePageRequestId = 0;
    let contentAnimationTimer = null;


    function setActiveNavigation(
        page
    ) {

        navItems.forEach(
            item => {

                const itemPage =
                    item.dataset.page;


                item.classList.toggle(
                    'active',

                    itemPage === page
                        ||
                    (
                        page === 'submission'
                        &&
                        itemPage === 'submissions'
                    )
                );

            }
        );

    }


    function updatePageTitle(
        page
    ) {

        const title =
            pageTitles[page]
            ||
            'Dashboard';


        document.title =
            title +
            ' | VNZ TECH';

    }


    function executeScripts(
        container
    ) {

        const scripts =
            container.querySelectorAll(
                'script'
            );


        scripts.forEach(
            oldScript => {

                const newScript =
                    document.createElement(
                        'script'
                    );


                Array.from(
                    oldScript.attributes
                ).forEach(
                    attribute => {

                        newScript.setAttribute(
                            attribute.name,
                            attribute.value
                        );

                    }
                );


                newScript.textContent =
                    oldScript.textContent;


                oldScript.parentNode.replaceChild(
                    newScript,
                    oldScript
                );

            }
        );

    }


    const sellerSubmissionModal =
        document.getElementById(
            'sellerSubmissionDetailsModal'
        );


    const sellerSubmissionModalBody =
        document.getElementById(
            'sellerSubmissionDetailsModalBody'
        );


    let sellerSubmissionModalRequest =
        0;


    function closeSellerSubmissionModal() {

        if (
            !sellerSubmissionModal ||
            !sellerSubmissionModalBody
        ) {
            return;
        }


        sellerSubmissionModalRequest++;

        sellerSubmissionModal.classList.remove(
            'is-open'
        );

        sellerSubmissionModal.setAttribute(
            'aria-hidden',
            'true'
        );

        document.body.classList.remove(
            'transaction-details-modal-open'
        );

        sellerSubmissionModalBody.innerHTML = `
            <div class="transaction-details-modal-loading">
                Loading submission details...
            </div>
        `;

    }


    async function openSellerSubmissionModal(
        reference
    ) {

        if (
            !sellerSubmissionModal ||
            !sellerSubmissionModalBody ||
            !reference
        ) {
            return;
        }


        const requestId =
            ++sellerSubmissionModalRequest;


        sellerSubmissionModal.classList.add(
            'is-open'
        );

        sellerSubmissionModal.setAttribute(
            'aria-hidden',
            'false'
        );

        document.body.classList.add(
            'transaction-details-modal-open'
        );

        sellerSubmissionModalBody.innerHTML = `
            <div class="transaction-details-modal-loading">
                Loading submission details...
            </div>
        `;


        try {

            const params =
                new URLSearchParams({
                    ref: reference,
                    modal: '1'
                });


            const response =
                await fetch(
                    'pages/submission.php?' +
                    params.toString(),
                    {
                        method: 'GET',
                        credentials: 'same-origin',
                        cache: 'no-store',
                        headers: {
                            'X-Requested-With':
                                'XMLHttpRequest'
                        }
                    }
                );


            if (
                !response.ok
            ) {
                throw new Error(
                    'HTTP ' + response.status
                );
            }


            const html =
                await response.text();


            if (
                requestId !==
                sellerSubmissionModalRequest
            ) {
                return;
            }


            if (
                html.trim() === ''
            ) {
                throw new Error(
                    'Empty submission response.'
                );
            }


            sellerSubmissionModalBody.innerHTML =
                html;

            executeScripts(
                sellerSubmissionModalBody
            );

        } catch (
            error
        ) {

            console.error(
                'VNZ TECH Seller Portal: unable to load submission details.',
                error
            );


            if (
                requestId !==
                sellerSubmissionModalRequest
            ) {
                return;
            }


            const errorPanel =
                document.createElement(
                    'div'
                );

            errorPanel.className =
                'transaction-details-modal-error';


            const errorTitle =
                document.createElement(
                    'strong'
                );

            errorTitle.textContent =
                'Unable to load submission details';


            const errorMessage =
                document.createElement(
                    'p'
                );

            errorMessage.textContent =
                'Please close this window and try again.';


            errorPanel.append(
                errorTitle,
                errorMessage
            );

            sellerSubmissionModalBody.replaceChildren(
                errorPanel
            );

        }

    }


    window.closeSellerSubmissionModal =
        closeSellerSubmissionModal;


    if (
        sellerSubmissionModal
    ) {

        sellerSubmissionModal
            .querySelectorAll(
                '[data-seller-submission-modal-close]'
            )
            .forEach(
                button => {

                    button.addEventListener(
                        'click',
                        closeSellerSubmissionModal
                    );

                }
            );


        document.addEventListener(
            'keydown',
            event => {

                if (
                    event.key === 'Escape' &&
                    sellerSubmissionModal.classList.contains(
                        'is-open'
                    )
                ) {
                    closeSellerSubmissionModal();
                }

            }
        );

    }


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


    async function loadPage(
        page,
        pushState = true,
        params = {},
        paginationTop = null
    ) {

        if (
            !pageTitles[page]
        ) {

            page =
                'dashboard';

        }

        const requestId =
            ++activePageRequestId;
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


        setActiveNavigation(
            page
        );


        updatePageTitle(
            page
        );


        if (paginationTop === null) {
            contentArea.innerHTML = `
                <div class="content-loading">
                    Loading...
                </div>
            `;
        }


        try {

            const queryParams =
                new URLSearchParams();


            Object.keys(
                params
            ).forEach(
                key => {

                    if (
                        params[key] !== null &&
                        params[key] !== undefined &&
                        params[key] !== ''
                    ) {

                        queryParams.set(
                            key,
                            params[key]
                        );

                    }

                }
            );


            let url =
                'pages/' +
                page +
                '.php';


            const queryString =
                queryParams.toString();


            if (
                queryString !== ''
            ) {

                url +=
                    '?' +
                    queryString;

            }


            const response =
                await fetch(
                    url,
                    {
                        method:
                            'GET',

                        headers: {
                            'X-Requested-With':
                                'XMLHttpRequest'
                        },

                        credentials:
                            'same-origin',

                        cache:
                            'no-store'
                    }
                );


            if (
                !response.ok
            ) {

                throw new Error(
                    'HTTP error: ' +
                    response.status
                );

            }


            const html =
                await response.text();

            if (
                requestId !==
                activePageRequestId
            ) {

                return;

            }


            contentArea.innerHTML =
                html;

            finishPageTransition();


            executeScripts(
                contentArea
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


            if (
                pushState
            ) {

                const browserParams =
                    new URLSearchParams();


                browserParams.set(
                    'page',
                    page
                );


                Object.keys(
                    params
                ).forEach(
                    key => {

                        if (
                            params[key] !== null &&
                            params[key] !== undefined &&
                            params[key] !== ''
                        ) {

                            browserParams.set(
                                key,
                                params[key]
                            );

                        }

                    }
                );


                const newUrl =
                    window.location.pathname +
                    '?' +
                    browserParams.toString();


                window.history.pushState(
                    {
                        page:
                            page,

                        params:
                            params
                    },
                    '',
                    newUrl
                );

            }

        } catch (
            error
        ) {

            if (
                requestId !==
                activePageRequestId
            ) {

                return;

            }


            console.error(
                error
            );


            contentArea.innerHTML = `
                <div class="portal-error">

                    <h2>
                        Unable to load this page
                    </h2>

                    <p>
                        Something went wrong while loading
                        this section.
                    </p>

                    <button
                        type="button"
                        onclick="window.loadSellerPage('dashboard')"
                    >
                        Back to Dashboard
                    </button>

                </div>
            `;

            finishPageTransition();

        }

    }


    navItems.forEach(
        item => {

            item.addEventListener(
                'click',
                function () {

                    const page =
                        this.dataset.page;

                    closeSellerMobileMenu();

                    loadPage(
                        page
                    );

                }
            );

        }
    );


    contentArea.addEventListener(
        'click',
        function (
            event
        ) {

            const submissionLink =
                event.target.closest(
                    'a[data-seller-submission-modal]'
                );


            if (
                submissionLink
            ) {

                event.preventDefault();

                const url =
                    new URL(
                        submissionLink.href,
                        window.location.origin
                    );

                openSellerSubmissionModal(
                    url.searchParams.get('ref')
                );

                return;

            }


            const link =
                event.target.closest(
                    'a[data-seller-page]'
                );


            if (!link) {
                return;
            }


            event.preventDefault();


            const targetPage =
                link.dataset.sellerPage ||
                'dashboard';


            const params = {};


            const url =
                new URL(
                    link.href,
                    window.location.origin
                );


            url.searchParams.forEach(
                (
                    value,
                    key
                ) => {

                    if (
                        key !== 'page'
                    ) {

                        params[key] =
                            value;

                    }

                }
            );


            loadPage(
                targetPage,
                true,
                params,
                link.closest('.vnz-pagination')
                    ? link.closest('.vnz-pagination').getBoundingClientRect().top
                    : null
            );

        }
    );

    window.loadSellerPage =
        loadPage;


    window.addEventListener(
        'popstate',
        function () {

            const urlParams =
                new URLSearchParams(
                    window.location.search
                );


            const currentPage =
                urlParams.get(
                    'page'
                ) ||
                'dashboard';


            const params = {};


            urlParams.forEach(
                (
                    value,
                    key
                ) => {

                    if (
                        key !== 'page'
                    ) {

                        params[key] =
                            value;

                    }

                }
            );


            loadPage(
                currentPage,
                false,
                params
            );

        }
    );


    /* =====================================================
       SELL ITEM MODAL
    ====================================================== */

    const sellerSellModal =
        document.getElementById(
            'sellerSellModal'
        );


    const sellerSellModalClose =
        document.getElementById(
            'sellerSellModalClose'
        );


    const sellerItemsContainer =
        document.getElementById(
            'sellerItemsContainer'
        );


    const sellerAddItemBtn =
        document.getElementById(
            'sellerAddItemBtn'
        );


    const sellerSubmissionForm =
        document.getElementById(
            'sellerSubmissionForm'
        );


    const sellerFormMessage =
        document.getElementById(
            'sellerFormMessage'
        );


    const sellerSubmitBtn =
        document.getElementById(
            'sellerSubmitBtn'
        );


    let sellerItemIndex =
        0;


    const issueList = [

        'No Power',
        'No Display',
        'Bootloop',
        'LCD Damaged',
        'Touch Problem',
        'Battery Problem',
        'Charging Problem',
        'Camera Problem',
        'Speaker Problem',
        'Microphone Problem',
        'Water Damage',
        'Board Problem',
        'Multiple Issues',
        'Other'

    ];


    const damageLevels = [

        {
            value:
                '1',

            label:
                '1 — Minor'
        },

        {
            value:
                '2',

            label:
                '2 — Moderate'
        },

        {
            value:
                '3',

            label:
                '3 — Major'
        },

        {
            value:
                '4',

            label:
                '4 — Dead / Critical'
        },

        {
            value:
                '5',

            label:
                '5 — For Scrap'
        }

    ];


    function openSellerSellModal() {

        if (
            !sellerSellModal
        ) {
            return;
        }


        sellerSellModal.classList.add(
            'is-open'
        );


        sellerSellModal.setAttribute(
            'aria-hidden',
            'false'
        );


        document.body.classList.add(
            'seller-modal-open'
        );


        if (
            sellerItemsContainer &&
            sellerItemsContainer.children.length === 0
        ) {

            addSellerItem();

        }

    }


    function closeSellerSellModal() {

        if (
            !sellerSellModal
        ) {
            return;
        }


        sellerSellModal.classList.remove(
            'is-open'
        );


        sellerSellModal.setAttribute(
            'aria-hidden',
            'true'
        );


        document.body.classList.remove(
            'seller-modal-open'
        );

    }


    window.openSellerSellModal =
        openSellerSellModal;


    window.closeSellerSellModal =
        closeSellerSellModal;


    if (
        sellerSellModalClose
    ) {

        sellerSellModalClose.addEventListener(
            'click',
            closeSellerSellModal
        );

    }


    if (
        sellerSellModal
    ) {

        sellerSellModal.addEventListener(
            'click',
            function (
                event
            ) {

                if (
                    event.target ===
                    sellerSellModal
                ) {

                    closeSellerSellModal();

                }

            }
        );

    }


    document.addEventListener(
        'keydown',
        function (
            event
        ) {

            if (
                event.key === 'Escape' &&
                sellerSellModal &&
                sellerSellModal.classList.contains(
                    'is-open'
                )
            ) {

                closeSellerSellModal();

            }

        }
    );


    /*
     * Seller Portal sell links
     */

    document.addEventListener(
        'click',
        function (
            event
        ) {

            const trigger =
                event.target.closest(
                    'a[href*="/sell.php"], [data-open-sell-modal]'
                );


            if (!trigger) {
                return;
            }


            if (
                trigger.closest(
                    '#sellerSellModal'
                )
            ) {
                return;
            }


            event.preventDefault();


            openSellerSellModal();

        }
    );


    /* =====================================================
       ADD SELLER ITEM
    ====================================================== */

    function addSellerItem() {

        if (
            !sellerItemsContainer
        ) {
            return;
        }


        const index =
            sellerItemIndex++;


        const itemCard =
            document.createElement(
                'div'
            );


        itemCard.className =
            'seller-item-card';


        itemCard.dataset.index =
            index;


        itemCard.innerHTML = `

            <div class="seller-item-header">

                <div class="seller-item-title">
                    Item ${index + 1}
                </div>

                <button
                    type="button"
                    class="seller-item-remove"
                    data-remove-item
                >
                    Remove
                </button>

            </div>


            <div class="seller-item-type-row">

                <button
                    type="button"
                    class="seller-type-btn active"
                    data-item-type="phone"
                >
                    WHOLE PHONE
                </button>


                <button
                    type="button"
                    class="seller-type-btn"
                    data-item-type="scrap"
                >
                    SCRAP BOARD
                </button>

            </div>


            <input
                type="hidden"
                name="items[${index}][item_type]"
                value="Whole Phone"
                data-item-type-input
            >


            <div
                class="seller-phone-fields"
                data-phone-fields
            >

                <div class="seller-form-grid">


                    <div class="seller-form-group">

                        <label>
                            Brand
                        </label>

                        <input
                            type="text"
                            name="items[${index}][brand]"
                            maxlength="100"
                            placeholder="Samsung / Apple / Xiaomi..."
                        >

                    </div>


                    <div class="seller-form-group">

                        <label>
                            Model
                        </label>

                        <input
                            type="text"
                            name="items[${index}][model]"
                            maxlength="150"
                            placeholder="A52 / iPhone 11 / Redmi Note..."
                        >

                    </div>


                    <div class="seller-form-group">

                        <label>
                            Storage
                        </label>

                        <select
                            name="items[${index}][storage]"
                        >

                            <option value="">
                                Unknown / N/A
                            </option>

                            <option value="16GB">
                                16GB
                            </option>

                            <option value="32GB">
                                32GB
                            </option>

                            <option value="64GB">
                                64GB
                            </option>

                            <option value="128GB">
                                128GB
                            </option>

                            <option value="256GB">
                                256GB
                            </option>

                            <option value="512GB">
                                512GB
                            </option>

                            <option value="1TB">
                                1TB
                            </option>

                        </select>

                    </div>


                    <div class="seller-form-group">

                        <label>
                            Quantity
                        </label>

                        <input
                            type="number"
                            name="items[${index}][quantity]"
                            min="1"
                            max="100000"
                            value="1"
                            required
                        >

                    </div>


                    <div class="seller-form-group full">

                        <label>
                            Main Issue
                        </label>

                        <select
                            name="items[${index}][issue]"
                            data-issue-select
                            required
                        >

                            <option value="">
                                Select issue
                            </option>

                        </select>

                    </div>


                    <div class="seller-form-group full">

                        <label>
                            Issue Details
                        </label>

                        <textarea
                            name="items[${index}][issue_details]"
                            maxlength="1000"
                            placeholder="Describe the issue..."
                        ></textarea>

                    </div>


                    <div class="seller-form-group">

                        <label>
                            Damage / Condition
                        </label>

                        <select
                            name="items[${index}][damage_level]"
                            data-damage-select
                            required
                        >

                            <option value="">
                                Select condition
                            </option>

                        </select>

                    </div>


                </div>

            </div>


            <div
                class="seller-scrap-fields"
                data-scrap-fields
                style="display: none;"
            >

                <div class="seller-form-grid">


                    <div class="seller-form-group">

                        <label>
                            Board Code / Marking
                        </label>

                        <input
                            type="text"
                            name="items[${index}][board_code]"
                            maxlength="150"
                            placeholder="Board code / marking"
                            data-board-code
                        >

                    </div>


                    <div class="seller-form-group">

                        <label>
                            Brand
                        </label>

                        <input
                            type="text"
                            name="items[${index}][brand]"
                            maxlength="100"
                            placeholder="Apple / Samsung..."
                        >

                    </div>


                    <div class="seller-form-group">

                        <label>
                            Board / Model
                        </label>

                        <input
                            type="text"
                            name="items[${index}][model]"
                            maxlength="150"
                            placeholder="Board model if known"
                        >

                    </div>


                    <div class="seller-form-group">

                        <label>
                            Storage
                        </label>

                        <select
                            name="items[${index}][storage]"
                        >

                            <option value="">
                                N/A / Unknown
                            </option>

                            <option value="16GB">
                                16GB
                            </option>

                            <option value="32GB">
                                32GB
                            </option>

                            <option value="64GB">
                                64GB
                            </option>

                            <option value="128GB">
                                128GB
                            </option>

                            <option value="256GB">
                                256GB
                            </option>

                            <option value="512GB">
                                512GB
                            </option>

                            <option value="1TB">
                                1TB
                            </option>

                        </select>

                    </div>


                    <div class="seller-form-group">

                        <label>
                            Condition
                        </label>

                        <select
                            name="items[${index}][damage_level]"
                            data-board-damage
                            required
                        >

                            <option value="">
                                Select condition
                            </option>

                        </select>

                    </div>


                    <div class="seller-form-group">

                        <label>
                            Quantity
                        </label>

                        <input
                            type="number"
                            name="items[${index}][quantity]"
                            min="1"
                            max="100000"
                            value="1"
                            data-scrap-quantity
                            required
                        >

                    </div>


                </div>

            </div>


            <div
                class="
                    seller-form-group
                    seller-asking-price-group
                "
            >

                <label>
                    Seller Asking Price
                </label>


                <div class="seller-price-input">

                    <span>
                        ₱
                    </span>


                    <input
                        type="number"
                        name="items[${index}][seller_asking_price]"
                        min="0"
                        max="999999999"
                        step="0.01"
                        placeholder="0.00"
                        required
                    >

                </div>


                <small>

                    Your requested price.
                    Final VNZ TECH offer is subject
                    to inspection and verification.

                </small>

            </div>


            <div
                class="
                    seller-form-group
                    seller-additional-group
                "
            >

                <label>
                    Additional Information
                </label>


                <textarea
                    name="items[${index}][additional_information]"
                    maxlength="1500"
                    placeholder="Missing parts, repair history, accessories, etc."
                ></textarea>

            </div>


            <div class="seller-photo-block">


                <div class="seller-photo-heading">

                    <strong>
                        Photos
                    </strong>


                    <small>
                        JPG, PNG, WEBP · Max 10 photos · 5MB each
                    </small>

                </div>


                <input
                    type="file"
                    name="photos[${index}][]"
                    accept="image/jpeg,image/png,image/webp"
                    multiple
                    required
                    data-photo-input
                >


                <div
                    class="seller-photo-preview"
                    data-photo-preview
                ></div>


            </div>

        `;


        sellerItemsContainer.appendChild(
            itemCard
        );


        setupSellerItem(
            itemCard
        );

    }


    /* =====================================================
       SETUP ITEM
    ====================================================== */

    function setupSellerItem(
        itemCard
    ) {

        const phoneButton =
            itemCard.querySelector(
                '[data-item-type="phone"]'
            );


        const scrapButton =
            itemCard.querySelector(
                '[data-item-type="scrap"]'
            );


        const itemTypeInput =
            itemCard.querySelector(
                '[data-item-type-input]'
            );


        const phoneFields =
            itemCard.querySelector(
                '[data-phone-fields]'
            );


        const scrapFields =
            itemCard.querySelector(
                '[data-scrap-fields]'
            );


        const phoneInputs =
            phoneFields.querySelectorAll(
                'input, select, textarea'
            );


        const scrapInputs =
            scrapFields.querySelectorAll(
                'input, select, textarea'
            );


        const issueSelect =
            itemCard.querySelector(
                '[data-issue-select]'
            );


        const damageSelect =
            itemCard.querySelector(
                '[data-damage-select]'
            );


        const boardDamage =
            itemCard.querySelector(
                '[data-board-damage]'
            );


        const boardCode =
            itemCard.querySelector(
                '[data-board-code]'
            );


        issueList.forEach(
            issue => {

                const option =
                    document.createElement(
                        'option'
                    );


                option.value =
                    issue;


                option.textContent =
                    issue;


                issueSelect.appendChild(
                    option
                );

            }
        );


        damageLevels.forEach(
            level => {

                const option =
                    document.createElement(
                        'option'
                    );


                option.value =
                    level.value;


                option.textContent =
                    level.label;


                damageSelect.appendChild(
                    option
                );


                const boardOption =
                    document.createElement(
                        'option'
                    );


                boardOption.value =
                    level.value;


                boardOption.textContent =
                    level.label;


                boardDamage.appendChild(
                    boardOption
                );

            }
        );


        function setType(
            type
        ) {

            const isPhone =
                type === 'phone';


            itemTypeInput.value =
                isPhone
                    ? 'Whole Phone'
                    : 'Scrap Board';


            phoneButton.classList.toggle(
                'active',
                isPhone
            );


            scrapButton.classList.toggle(
                'active',
                !isPhone
            );


            phoneFields.style.display =
                isPhone
                    ? ''
                    : 'none';


            scrapFields.style.display =
                isPhone
                    ? 'none'
                    : '';


            phoneInputs.forEach(
                input => {

                    input.disabled =
                        !isPhone;

                }
            );


            scrapInputs.forEach(
                input => {

                    input.disabled =
                        isPhone;

                }
            );


            issueSelect.required =
                isPhone;


            damageSelect.required =
                isPhone;


            boardDamage.required =
                !isPhone;


            boardCode.required =
                !isPhone;

        }


        phoneButton.addEventListener(
            'click',
            function () {

                setType(
                    'phone'
                );

            }
        );


        scrapButton.addEventListener(
            'click',
            function () {

                setType(
                    'scrap'
                );

            }
        );


        const removeButton =
            itemCard.querySelector(
                '[data-remove-item]'
            );


        removeButton.addEventListener(
            'click',
            function () {

                const cards =
                    sellerItemsContainer.querySelectorAll(
                        '.seller-item-card'
                    );


                if (
                    cards.length <= 1
                ) {

                    return;

                }


                itemCard.remove();

            }
        );


        const photoInput =
            itemCard.querySelector(
                '[data-photo-input]'
            );


        const photoPreview =
            itemCard.querySelector(
                '[data-photo-preview]'
            );


        if (
            photoInput &&
            photoPreview
        ) {

            photoInput.addEventListener(
                'change',
                function () {

                    photoPreview.innerHTML =
                        '';


                    Array.from(
                        this.files
                    )
                    .slice(
                        0,
                        10
                    )
                    .forEach(
                        file => {

                            if (
                                !file.type.startsWith(
                                    'image/'
                                )
                            ) {

                                return;

                            }


                            const reader =
                                new FileReader();


                            reader.onload =
                                function (
                                    event
                                ) {

                                    const wrapper =
                                        document.createElement(
                                            'div'
                                        );


                                    wrapper.className =
                                        'seller-photo-preview-item';


                                    const image =
                                        document.createElement(
                                            'img'
                                        );


                                    image.src =
                                        event.target.result;


                                    image.alt =
                                        'Selected photo';


                                    wrapper.appendChild(
                                        image
                                    );


                                    photoPreview.appendChild(
                                        wrapper
                                    );

                                };


                            reader.readAsDataURL(
                                file
                            );

                        }
                    );

                }
            );

        }


        setType(
            'phone'
        );

    }


    /* =====================================================
       ADD ITEM BUTTON
    ====================================================== */

    if (
        sellerAddItemBtn
    ) {

        sellerAddItemBtn.addEventListener(
            'click',
            function () {

                addSellerItem();

            }
        );

    }


    /* =====================================================
       SELL BUTTONS
    ====================================================== */

    document.addEventListener(
        'click',
        function (
            event
        ) {

            const sellButton =
                event.target.closest(
                    '.sell-button'
                );


            if (!sellButton) {
                return;
            }


            event.preventDefault();


            openSellerSellModal();

        }
    );


    /* =====================================================
       FORM SUBMIT
    ====================================================== */

    if (
        sellerSubmissionForm
    ) {

        sellerSubmissionForm.addEventListener(
            'submit',
            function () {

                if (
                    sellerSubmitBtn
                ) {

                    sellerSubmitBtn.disabled =
                        true;


                    sellerSubmitBtn.textContent =
                        'SUBMITTING...';

                }

            }
        );

    }


    /* =====================================================
       GLOBAL ALERT MODAL
    ====================================================== */

    const sellerAlertModal =
        document.getElementById(
            'sellerAlertModal'
        );


    const sellerAlertModalMessage =
        document.getElementById(
            'sellerAlertModalMessage'
        );

    const sellerAlertModalTitle =
        document.getElementById(
            'sellerAlertModalTitle'
        );


    const sellerAlertModalPrimary =
        document.getElementById(
            'sellerAlertModalPrimary'
        );


    const sellerAlertModalDismiss =
        document.getElementById(
            'sellerAlertModalDismiss'
        );


    let currentSellerAlertOnClose =
        null;


    let sellerAlertQueue =
        [];
    const sellerResultAlertIds =
        new Set();
    let checkingSellerResults =
        false;


    function showSellerAlertModal(
        message,
        options
    ) {

        options =
            options || {};


        if (
            !sellerAlertModal ||
            !sellerAlertModalMessage
        ) {
            return;
        }


        currentSellerAlertOnClose =
            typeof options.onClose ===
                'function'
                ? options.onClose
                : null;


        sellerAlertModalMessage.textContent =
            message;
        sellerAlertModal.dataset.tone = options.tone === 'success' ? 'success' : 'notice';

        if (sellerAlertModalTitle) {
            sellerAlertModalTitle.textContent =
                options.title || 'Heads up';
        }


        if (
            options.actionLabel &&
            (options.actionPage || typeof options.onAction === 'function')
        ) {

            sellerAlertModalPrimary.style.display =
                '';


            sellerAlertModalPrimary.textContent =
                options.actionLabel;


            sellerAlertModalPrimary.onclick =
                function () {

                    closeSellerAlertModal();

                    if (typeof options.onAction === 'function') {
                        options.onAction();
                        return;
                    }

                    loadPage(options.actionPage, true, options.actionParams || {});

                };

        } else {

            sellerAlertModalPrimary.style.display =
                'none';


            sellerAlertModalPrimary.onclick =
                null;

        }


        sellerAlertModal.classList.add(
            'is-open'
        );


        sellerAlertModal.setAttribute(
            'aria-hidden',
            'false'
        );

    }


    /*
    |--------------------------------------------------------------------------
    | ALERT QUEUE
    |--------------------------------------------------------------------------
    |
    | Only one #sellerAlertModal exists in the DOM. When several
    | alerts need to be shown (e.g. multiple unseen admin decisions,
    | or an existing ?error= redirect plus new results), queue them
    | and show one at a time as each gets dismissed.
    |
    */

    function enqueueSellerAlert(
        message,
        options
    ) {

        sellerAlertQueue.push({
            message: message,
            options: options || {}
        });


        if (
            !sellerAlertModal.classList.contains(
                'is-open'
            )
        ) {

            showNextQueuedSellerAlert();

        }

    }

    window.showSellerTransactionApproval = function (result, onClose = null) {
        const rawAmount = result.offer_amount ?? result.final_amount;
        const amount = Number(rawAmount);
        const amountText = rawAmount !== null && rawAmount !== undefined && Number.isFinite(amount)
            ? '\nApproved amount: ' + amount.toLocaleString('en-PH', {
                style: 'currency',
                currency: 'PHP'
            })
            : '';

        enqueueSellerAlert(
            'Your request has been approved successfully.\nSubmission: ' +
                (result.submission_reference || '—') +
                '\nTransaction: ' +
                (result.transaction_reference || 'Created') +
                amountText,
            {
                title: 'Request Approved',
                tone: 'success',
                actionLabel: 'Chat with Admin',
                onAction: function () {
                    window.closeSellerSubmissionModal?.();
                    window.openTransactionChat?.(Number(result.transaction_id));
                },
                onClose
            }
        );
    };


    function showNextQueuedSellerAlert() {

        if (
            sellerAlertQueue.length === 0
        ) {
            return;
        }


        const next =
            sellerAlertQueue.shift();


        showSellerAlertModal(
            next.message,
            next.options
        );

    }


    function closeSellerAlertModal() {

        if (
            !sellerAlertModal
        ) {
            return;
        }


        sellerAlertModal.classList.remove(
            'is-open'
        );


        sellerAlertModal.setAttribute(
            'aria-hidden',
            'true'
        );


        const onClose =
            currentSellerAlertOnClose;


        currentSellerAlertOnClose =
            null;


        if (
            typeof onClose === 'function'
        ) {

            onClose();

        }


        showNextQueuedSellerAlert();

    }


    if (
        sellerAlertModalDismiss
    ) {

        sellerAlertModalDismiss.addEventListener(
            'click',
            closeSellerAlertModal
        );

    }


    if (
        sellerAlertModal
    ) {

        sellerAlertModal.addEventListener(
            'click',
            function (
                event
            ) {

                if (
                    event.target ===
                    sellerAlertModal
                ) {

                    closeSellerAlertModal();

                }

            }
        );

    }


    document.addEventListener(
        'keydown',
        function (
            event
        ) {

            if (
                event.key === 'Escape' &&
                sellerAlertModal &&
                sellerAlertModal.classList.contains(
                    'is-open'
                )
            ) {

                closeSellerAlertModal();

            }

        }
    );


    /*
    |--------------------------------------------------------------------------
    | UNSEEN ADMIN-DECIDED RESULTS (Accept / Decline popup)
    |--------------------------------------------------------------------------
    |
    | Checks backend/get_unseen_results.php on every dashboard load.
    | Only ever returns submissions where Admin accepted/declined the
    | seller's own counter-offer (see admin_respond_offer.php) — the
    | seller has no other way of knowing this happened yet.
    |
    | Each result is enqueued as its own alert so multiple unseen
    | results show one after another instead of overwriting the
    | modal. Each is marked seen (backend/mark_result_seen.php) the
    | moment its alert is dismissed, however the person dismisses it
    | (X button, backdrop click, Escape, or the action button).
    |
    */

    async function checkUnseenResults() {
        if (checkingSellerResults) {
            return;
        }

        checkingSellerResults = true;
        try {

            const response =
                await fetch(
                    '/VNZ-TECH/backend/get_unseen_results.php'
                );


            const data =
                await response.json();


            if (
                !data.success ||
                !Array.isArray(
                    data.results
                ) ||
                data.results.length === 0
            ) {
                return;
            }


            data.results.forEach(
                function (result) {
                    const resultId = Number(result.id);
                    if (
                        !Number.isSafeInteger(resultId)
                        || resultId <= 0
                        || sellerResultAlertIds.has(resultId)
                    ) {
                        return;
                    }
                    sellerResultAlertIds.add(resultId);

                    const isAccepted =
                        result.status ===
                        'accepted';


                    const amountText =
                        (result.offer_amount || result.final_amount)
                            ? '₱' +
                                Number(
                                    result.offer_amount || result.final_amount
                                ).toLocaleString(
                                    'en-PH',
                                    {
                                        minimumFractionDigits: 2,
                                        maximumFractionDigits: 2
                                    }
                                )
                            : '';


                    if (isAccepted && Number(result.transaction_id) > 0) {
                        window.showSellerTransactionApproval(
                            {
                                ...result,
                                submission_reference: result.reference_number
                            },
                            function () {
                                markResultSeen(resultId);
                            }
                        );
                        return;
                    }

                    const message =
                        isAccepted
                            ? (
                                'Your request has been approved successfully.\nSubmission: ' +
                                result.reference_number +
                                '\nTransaction: ' +
                                (result.transaction_reference || 'Created') +
                                (
                                    amountText
                                        ? '\nApproved amount: ' + amountText
                                        : ''
                                )
                            )

                            : (
                                'Na-decline ng VNZ TECH ang submission mo (' +
                                result.reference_number +
                                ').' +
                                (
                                    result.admin_feedback
                                        ? ' ' + result.admin_feedback
                                        : ''
                                )
                            );


                    enqueueSellerAlert(
                        message,
                        {
                            title: isAccepted
                                ? 'Request Approved'
                                : 'Heads up',
                            tone: isAccepted ? 'success' : 'notice',

                            actionLabel: isAccepted && Number(result.transaction_id) > 0
                                ? 'Chat with Admin'
                                : 'View Submission',

                            actionPage: isAccepted && Number(result.transaction_id) > 0
                                ? 'transaction'
                                : 'submission',

                            actionParams: isAccepted && Number(result.transaction_id) > 0
                                ? {
                                    id: result.transaction_id,
                                    open_chat: 1
                                }
                                : {
                                    ref: result.reference_number
                                },

                            onClose:
                                function () {

                                    markResultSeen(
                                        resultId
                                    );

                                }
                        }
                    );

                }
            );

        } catch (error) {

            /*
             * Logged (not alerted) — this is a background
             * convenience check, not a blocking part of the page,
             * but we still want visibility in the console for
             * debugging.
             */

            console.warn(
                'checkUnseenResults failed:',
                error
            );

        } finally {
            checkingSellerResults = false;
        }

    }


    async function markResultSeen(
        submissionId
    ) {

        try {

            const body =
                new FormData();


            body.append(
                'submission_id',
                submissionId
            );


            await fetch(
                '/VNZ-TECH/backend/mark_result_seen.php',
                {
                    method: 'POST',
                    body
                }
            );

        } catch (error) {

            console.warn(
                'markResultSeen failed:',
                error
            );

        }

    }


    window.showSellerAlertModal =
        showSellerAlertModal;


    /* =====================================================
       INITIAL ITEM
    ====================================================== */

    addSellerItem();


    /* =====================================================
       INITIAL PAGE
    ====================================================== */

    const initialParams =
        new URLSearchParams(
            window.location.search
        );


    const initialPage =
        initialParams.get(
            'page'
        ) ||
        'dashboard';


    const initialExtraParams = {};


    initialParams.forEach(
        (
            value,
            key
        ) => {

            if (
                key !== 'page'
            ) {

                initialExtraParams[key] =
                    value;

            }

        }
    );


    /*
    |--------------------------------------------------------------------------
    | Surface the ?error= redirect message (see PHP $initialAlertMessage)
    |--------------------------------------------------------------------------
    |
    | If the user got here via a full-page redirect that failed
    | (e.g. submit_submission.php rejecting an incomplete profile),
    | show it as an actionable modal instead of leaving it invisible
    | in the URL. Profile-related messages get a "Go to Profile"
    | shortcut; everything else just gets a dismissible notice.
    |
    */

    const initialAlertMessage =
        <?= json_encode($initialAlertMessage) ?>;


    if (
        initialAlertMessage
    ) {

        delete initialExtraParams.error;


        const isProfileRelated =
            /profile/i.test(
                initialAlertMessage
            );


        enqueueSellerAlert(
            initialAlertMessage,
            isProfileRelated
                ? {
                    actionLabel:
                        'Go to Profile',

                    actionPage:
                        'profile'
                }
                : {}
        );


        const cleanParams =
            new URLSearchParams(
                window.location.search
            );


        cleanParams.delete(
            'error'
        );


        const cleanQuery =
            cleanParams.toString();


        const cleanUrl =
            window.location.pathname +
            (
                cleanQuery
                    ? '?' + cleanQuery
                    : ''
            );


        window.history.replaceState(
            {},
            '',
            cleanUrl
        );

    }


    /*
    |--------------------------------------------------------------------------
    | Check for unseen Admin Accept/Decline results
    |--------------------------------------------------------------------------
    |
    | Runs after the ?error= alert above so both queue correctly —
    | if an error alert is already open, this just adds to the
    | queue and waits its turn.
    |
    */

    checkUnseenResults();


    /*
    |--------------------------------------------------------------------------
    | REAL-TIME POLLING
    |--------------------------------------------------------------------------
    |
    | Re-checks in the background every 20 seconds so a seller who
    | is already sitting on the dashboard sees the popup as soon as
    | Admin accepts/declines their counter-offer, without needing to
    | reload the page.
    |
    */

    const UNSEEN_RESULTS_POLL_INTERVAL_MS =
        4000;


    setInterval(
        checkUnseenResults,
        UNSEEN_RESULTS_POLL_INTERVAL_MS
    );


    window.addEventListener('vnz:guest-claim-updated', function () {
        const params = new URLSearchParams(window.location.search);
        const page = params.get('page') || 'dashboard';
        params.delete('page');
        params.delete('verification');
        loadPage(page, false, Object.fromEntries(params));
    });

    loadPage(
        pageTitles[initialPage]
            ? initialPage
            : 'dashboard',

        false,

        initialExtraParams
    );
     
        /* =====================================================
       PHOTO LIGHTBOX
    ====================================================== */

    const photoLightbox =
        document.getElementById(
            'transactionPhotoLightbox'
        );

    const photoLightboxImg =
        photoLightbox
            ? photoLightbox.querySelector(
                '.transaction-photo-lightbox-img'
            )
            : null;


    function openPhotoLightbox(src, alt) {

        if (!photoLightbox || !photoLightboxImg) {
            return;
        }

        photoLightboxImg.src = src;
        photoLightboxImg.alt = alt || '';

        photoLightbox.classList.add('is-open');
        photoLightbox.setAttribute('aria-hidden', 'false');

        document.body.classList.add(
            'transaction-photo-lightbox-open'
        );

    }


    function closePhotoLightbox() {

        if (!photoLightbox) {
            return;
        }

        photoLightbox.classList.remove('is-open');
        photoLightbox.setAttribute('aria-hidden', 'true');

        document.body.classList.remove(
            'transaction-photo-lightbox-open'
        );

        if (photoLightboxImg) {
            photoLightboxImg.src = '';
        }

    }


    document.addEventListener('click', function (event) {

        const photoLink =
            event.target.closest(
                '.transaction-photo-link'
            );

        if (photoLink) {

            event.preventDefault();

            const img =
                photoLink.querySelector('img');

            openPhotoLightbox(
                photoLink.getAttribute('href'),
                img ? img.alt : ''
            );

            return;
        }

        if (event.target.closest('[data-close-photo-lightbox]')) {
            closePhotoLightbox();
        }

    });


    document.addEventListener('keydown', function (event) {

        if (
            event.key === 'Escape'
            &&
            photoLightbox
            &&
            photoLightbox.classList.contains('is-open')
        ) {

            closePhotoLightbox();

        }

    });

})();

</script>

<?php require __DIR__ . '/../includes/floating-messages.php'; ?>

</body>
</html>
