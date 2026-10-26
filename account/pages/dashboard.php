<?php

require_once __DIR__ . '/../../backend/database.php';
require_once __DIR__ . '/../../backend/session.php';
require_once __DIR__ . '/../../backend/csrf.php';
require_once __DIR__ . '/../../backend/chat_service.php';
require_once __DIR__ . '/../../backend/guest_submission_link.php';

if (
    empty($_SESSION['user_logged_in']) ||
    empty($_SESSION['user_id'])
) {
    http_response_code(401);

    echo '
        <div class="portal-error">
            <h2>Session expired</h2>
            <p>Please log in again.</p>
        </div>
    ';

    exit;
}

$userId = (int) $_SESSION['user_id'];

if ($userId <= 0) {
    http_response_code(401);

    echo '
        <div class="portal-error">
            <h2>Invalid session</h2>
            <p>Please log in again.</p>
        </div>
    ';

    exit;
}


/*
|--------------------------------------------------------------------------
| GET CURRENT USER
|--------------------------------------------------------------------------
*/

try {

    $userStmt = $pdo->prepare("
        SELECT
            id,
            full_name,
            mobile,
            email,
            email_verified_at,
            profile_picture,
            language,
            is_active,
            created_at
        FROM users
        WHERE id = ?
        LIMIT 1
    ");

    $userStmt->execute([$userId]);

    $user = $userStmt->fetch();

    if (!$user) {
        http_response_code(404);

        echo '
            <div class="portal-error">
                <h2>User account not found</h2>
                <p>Please contact VNZ TECH.</p>
            </div>
        ';

        exit;
    }

    if ((int) $user['is_active'] !== 1) {
        http_response_code(403);

        echo '
            <div class="portal-error">
                <h2>Account inactive</h2>
                <p>Your account is currently inactive.</p>
            </div>
        ';

        exit;
    }

    $guestSubmissionLinkNotice = null;
    if ($user['email_verified_at'] !== null) {
        try {
            if (linkVerifiedGuestSubmissionsAfterSignIn($pdo, $userId) > 0) {
                $guestSubmissionLinkNotice = 'linked';
            }
        } catch (Throwable $linkError) {
            error_log(
                'Guest submission linking after Seller sign-in failed: ' .
                $linkError->getMessage()
            );
            $guestSubmissionLinkNotice = 'failed';
        }
    }


    /*
    |--------------------------------------------------------------------------
    | TOTAL SUBMISSIONS
    |--------------------------------------------------------------------------
    */

    $submissionCountStmt = $pdo->prepare("
        SELECT COUNT(*)
        FROM submissions
        WHERE user_id = ?
    ");

    $submissionCountStmt->execute([$userId]);

    $submissionCount = (int) $submissionCountStmt->fetchColumn();


    /*
    |--------------------------------------------------------------------------
    | ACTIVE OFFERS
    |--------------------------------------------------------------------------
    */

    $activeOffersStmt = $pdo->prepare("
        SELECT COUNT(*)
        FROM offers o
        INNER JOIN submissions s
            ON s.id = o.submission_id
        WHERE s.user_id = ?
          AND o.status IN ('sent', 'pending')
    ");

    $activeOffersStmt->execute([$userId]);

    $activeOffers = (int) $activeOffersStmt->fetchColumn();


    /*
    |--------------------------------------------------------------------------
    | COMPLETED TRANSACTIONS
    |--------------------------------------------------------------------------
    */

    $completedTransactionsStmt = $pdo->prepare("
        SELECT COUNT(*)
        FROM transactions t
        INNER JOIN submissions s
            ON s.id = t.submission_id
        WHERE s.user_id = ?
          AND t.transaction_status = 'completed'
    ");

    $completedTransactionsStmt->execute([$userId]);

    $completedTransactions = (int) $completedTransactionsStmt->fetchColumn();


    /*
    |--------------------------------------------------------------------------
    | TOTAL PAID
    |--------------------------------------------------------------------------
    */

    $totalPaidStmt = $pdo->prepare("
        SELECT COALESCE(SUM(t.final_amount), 0)
        FROM transactions t
        INNER JOIN submissions s
            ON s.id = t.submission_id
        WHERE s.user_id = ?
          AND t.transaction_status = 'completed'
    ");

    $totalPaidStmt->execute([$userId]);

    $totalPaid = (float) $totalPaidStmt->fetchColumn();


    /*
    |--------------------------------------------------------------------------
    | RECENT SUBMISSIONS
    |--------------------------------------------------------------------------
    */

    $recentSubmissionsStmt = $pdo->prepare("
        SELECT
            s.id,
            s.reference_number,
            s.status,
            s.selling_method,
            s.created_at,

            (
                SELECT COUNT(*)
                FROM submission_items si
                WHERE si.submission_id = s.id
            ) AS item_count,

            (
                SELECT COALESCE(SUM(si.quantity), 0)
                FROM submission_items si
                WHERE si.submission_id = s.id
            ) AS total_quantity,

            (
                SELECT COALESCE(
                    SUM(
                        si.seller_asking_price * si.quantity
                    ),
                    0
                )
                FROM submission_items si
                WHERE si.submission_id = s.id
            ) AS asking_total

        FROM submissions s

        WHERE s.user_id = ?

        ORDER BY s.created_at DESC

        LIMIT 5
    ");

    $recentSubmissionsStmt->execute([$userId]);

    $recentSubmissions = $recentSubmissionsStmt->fetchAll();


    /*
    |--------------------------------------------------------------------------
    | RECENT TRANSACTIONS
    |--------------------------------------------------------------------------
    */

    $recentTransactionsStmt = $pdo->prepare("
        SELECT
            t.id,
            t.transaction_reference,
            t.final_amount,
            t.payment_method,
            t.payment_status,
            t.transaction_status,
            t.completed_at,
            s.reference_number

        FROM transactions t

        INNER JOIN submissions s
            ON s.id = t.submission_id

        WHERE s.user_id = ?

        ORDER BY t.created_at DESC

        LIMIT 5
    ");

    $recentTransactionsStmt->execute([$userId]);

    $recentTransactions = $recentTransactionsStmt->fetchAll();

} catch (Throwable $e) {

    http_response_code(500);

    echo '
        <div class="portal-error">
            <h2>Unable to load dashboard</h2>
            <p>
                Something went wrong while loading
                your dashboard.
            </p>
        </div>
    ';

    exit;
}


/*
|--------------------------------------------------------------------------
| HELPERS
|--------------------------------------------------------------------------
*/

function dashboardEscape($value): string
{
    return htmlspecialchars(
        (string) ($value ?? ''),
        ENT_QUOTES,
        'UTF-8'
    );
}


function dashboardMoney($amount): string
{
    return '₱' . number_format(
        (float) $amount,
        2
    );
}


function dashboardStatusLabel($status): string
{
    $status = strtolower(
        trim((string) $status)
    );

    return ucwords(
        str_replace(
            '_',
            ' ',
            $status
        )
    );
}


function dashboardStatusClass($status): string
{
    $status = strtolower(
        trim((string) $status)
    );

    $allowed = [
        'new_submission',
        'under_review',
        'offer_pending',
        'offer_sent',
        'accepted',
        'declined',
        'cancelled',
        'completed'
    ];

    if (
        in_array(
            $status,
            $allowed,
            true
        )
    ) {
        return $status;
    }

    return 'default';
}


function dashboardDate($date): string
{
    if (empty($date)) {
        return '—';
    }

    $timestamp = strtotime($date);

    if (!$timestamp) {
        return '—';
    }

    return date(
        'M d, Y',
        $timestamp
    );
}


function dashboardTime($date): string
{
    if (empty($date)) {
        return '';
    }

    $timestamp = strtotime($date);

    if (!$timestamp) {
        return '';
    }

    return date(
        'h:i A',
        $timestamp
    );
}


function dashboardProfilePicture($profilePicture): string
{
    if (
        !empty($profilePicture) &&
        is_string($profilePicture)
    ) {
        $profilePicture = trim(
            $profilePicture
        );

        if ($profilePicture !== '') {
            $normalizedPath = str_replace(
                '\\',
                '/',
                $profilePicture
            );

            if (
                str_starts_with(
                    $normalizedPath,
                    'http://'
                ) ||
                str_starts_with(
                    $normalizedPath,
                    'https://'
                ) ||
                str_starts_with(
                    $normalizedPath,
                    '/'
                )
            ) {
                return $normalizedPath;
            }

            return '/VNZ-TECH/' .
                ltrim(
                    $normalizedPath,
                    '/'
                );
        }
    }

    return '';
}


/*
|--------------------------------------------------------------------------
| DISPLAY DATA
|--------------------------------------------------------------------------
*/

$fullName = trim(
    (string) ($user['full_name'] ?? '')
);

if ($fullName === '') {
    $fullName = 'Seller';
}

$firstName = explode(
    ' ',
    $fullName
)[0];

$profilePicture = dashboardProfilePicture(
    $user['profile_picture'] ?? ''
);

$initial = strtoupper(
    substr(
        $fullName,
        0,
        1
    )
);

?>

<section class="dashboard-page">

    <?php if ($guestSubmissionLinkNotice === 'linked'): ?>
        <div class="guest-submission-link-notice guest-submission-link-success" role="status">
            Your eligible guest submission and its chat history are now linked to this account.
        </div>
    <?php elseif ($guestSubmissionLinkNotice === 'failed'): ?>
        <div class="guest-submission-link-notice guest-submission-link-warning" role="alert">
            We could not finish linking your guest submission. Please contact support for help.
        </div>
    <?php endif; ?>

    <!-- =====================================================
         HEADER
    ====================================================== -->

    <header class="dashboard-header">

        <div>

            <span class="eyebrow">
                SELLER PORTAL
            </span>

            <h1>
                Welcome back,
                <?= dashboardEscape($firstName) ?>.
            </h1>

            <p>
                Track your submissions, offers,
                and completed transactions
                from one place.
            </p>

        </div>


        <div class="dashboard-profile">

            <?php if ($profilePicture !== ''): ?>

                <img
                    src="<?= dashboardEscape($profilePicture) ?>"
                    alt="<?= dashboardEscape($fullName) ?>"
                    class="profile-avatar"
                >

            <?php else: ?>

                <div class="profile-avatar profile-avatar-fallback">
                    <?= dashboardEscape($initial) ?>
                </div>

            <?php endif; ?>


            <div class="profile-info">

                <strong>
                    <?= dashboardEscape($fullName) ?>
                </strong>

                <span>
                    <?= dashboardEscape($user['email']) ?>
                </span>

            </div>

        </div>

    </header>


    <!-- =====================================================
         YOUR ACCOUNT
    ====================================================== -->

    <section class="dashboard-section dashboard-account-section">

        <div class="section-heading">

            <div>

                <span class="eyebrow">
                    ACCOUNT
                </span>

                <h2>
                    Your account
                </h2>

            </div>

        </div>


        <div class="account-info-grid">

            <div class="account-info-card">

                <span>
                    Full Name
                </span>

                <strong>
                    <?= dashboardEscape(
                        $user['full_name']
                    ) ?>
                </strong>

            </div>


            <div class="account-info-card">

                <span>
                    Email
                </span>

                <strong>
                    <?= dashboardEscape(
                        $user['email']
                    ) ?>
                </strong>

            </div>


            <div class="account-info-card">

                <span>
                    Mobile
                </span>

                <strong>
                    <?= dashboardEscape(
                        $user['mobile']
                        ?: 'Not provided'
                    ) ?>
                </strong>

            </div>


            <div class="account-info-card">

                <span>
                    Member Since
                </span>

                <strong>
                    <?= dashboardDate(
                        $user['created_at']
                    ) ?>
                </strong>

            </div>

        </div>

    </section>


    <!-- =====================================================
         QUICK ACTION
    ====================================================== -->

    <section class="dashboard-action">

        <div>

            <span class="eyebrow">
                READY TO SELL?
            </span>

            <h2>
                Submit another device.
            </h2>

            <p>
                Tell us about your phone,
                device, or scrap board and
                our team will review it.
            </p>

        </div>


        <a
            href="/VNZ-TECH/sell.php"
            class="sell-button"
        >
            SELL TO VNZ TECH
        </a>

    </section>


    <!-- =====================================================
         STATISTICS
    ====================================================== -->

    <section class="stats-grid">

        <article class="stat-card">

            <div class="stat-card-label">
                TOTAL SUBMISSIONS
            </div>

            <div class="stat-card-value">
                <?= number_format(
                    $submissionCount
                ) ?>
            </div>

            <div class="stat-card-note">
                All submitted devices
            </div>

        </article>


        <article class="stat-card">

            <div class="stat-card-label">
                ACTIVE OFFERS
            </div>

            <div class="stat-card-value">
                <?= number_format(
                    $activeOffers
                ) ?>
            </div>

            <div class="stat-card-note">
                Offers awaiting action
            </div>

        </article>


        <article class="stat-card">

            <div class="stat-card-label">
                COMPLETED
            </div>

            <div class="stat-card-value">
                <?= number_format(
                    $completedTransactions
                ) ?>
            </div>

            <div class="stat-card-note">
                Completed transactions
            </div>

        </article>


        <article class="stat-card">

            <div class="stat-card-label">
                TOTAL PAID
            </div>

            <div class="stat-card-value stat-card-money">
                <?= dashboardMoney(
                    $totalPaid
                ) ?>
            </div>

            <div class="stat-card-note">
                From completed transactions
            </div>

        </article>

    </section>


    <!-- =====================================================
         RECENT SUBMISSIONS
    ====================================================== -->

    <section class="dashboard-section">

        <div class="section-heading">

            <div>

                <span class="eyebrow">
                    ACTIVITY
                </span>

                <h2>
                    Recent submissions
                </h2>

            </div>


            <button
                type="button"
                class="section-link"
                onclick="window.loadSellerPage('submissions')"
            >
                VIEW ALL
            </button>

        </div>


        <?php if (empty($recentSubmissions)): ?>

            <div class="empty-state">

                <div class="empty-state-icon">
                    +
                </div>

                <h3>
                    No submissions yet.
                </h3>

                <p>
                    Your submitted devices will
                    appear here once you make
                    your first submission.
                </p>

                <a
                    href="/VNZ-TECH/sell.php"
                    class="empty-state-button"
                >
                    SUBMIT A DEVICE
                </a>

            </div>

        <?php else: ?>

            <div class="recent-list">

                <?php foreach (
                    $recentSubmissions
                    as $submission
                ): ?>

                    <?php

                    $status = strtolower(
                        trim(
                            (string) (
                                $submission['status']
                                ?? ''
                            )
                        )
                    );

                    ?>

                    <article class="recent-item">

                        <div class="recent-item-main">

                            <div class="recent-reference">

                                <?= dashboardEscape(
                                    $submission[
                                        'reference_number'
                                    ]
                                ) ?>

                            </div>


                            <div class="recent-meta">

                                <span>
                                    <?= dashboardDate(
                                        $submission[
                                            'created_at'
                                        ]
                                    ) ?>
                                </span>

                                <span>
                                    <?= dashboardTime(
                                        $submission[
                                            'created_at'
                                        ]
                                    ) ?>
                                </span>

                                <span>
                                    <?= number_format(
                                        (int) (
                                            $submission[
                                                'item_count'
                                            ] ?? 0
                                        )
                                    ) ?>
                                    items
                                </span>

                            </div>

                        </div>


                        <div class="recent-item-details">

                            <div class="recent-amount">

                                <?= dashboardMoney(
                                    $submission[
                                        'asking_total'
                                    ] ?? 0
                                ) ?>

                            </div>


                            <span
                                class="status-badge status-<?= dashboardEscape(
                                    dashboardStatusClass(
                                        $status
                                    )
                                ) ?>"
                            >
                                <?= dashboardEscape(
                                    dashboardStatusLabel(
                                        $status
                                    )
                                ) ?>
                            </span>

                        </div>

                    </article>

                <?php endforeach; ?>

            </div>

        <?php endif; ?>

    </section>


    <!-- =====================================================
         RECENT TRANSACTIONS
    ====================================================== -->

    <section class="dashboard-section">

        <div class="section-heading">

            <div>

                <span class="eyebrow">
                    HISTORY
                </span>

                <h2>
                    Recent transactions
                </h2>

            </div>


            <a
                href="/VNZ-TECH/account/dashboard.php?page=transactions"
                class="section-link"
                data-seller-page="transactions"
            >
                VIEW ALL
            </a>

        </div>


        <?php if (empty($recentTransactions)): ?>

            <div class="empty-state compact">

                <h3>
                    No transactions yet.
                </h3>

                <p>
                    Completed transactions will
                    appear here.
                </p>

            </div>

        <?php else: ?>

            <div class="recent-list">

                <?php foreach (
                    $recentTransactions
                    as $transaction
                ): ?>

                    <article class="recent-item">

                        <div class="recent-item-main">

                            <div class="recent-reference">

                                <?= dashboardEscape(
                                    $transaction[
                                        'transaction_reference'
                                    ]
                                ) ?>

                            </div>


                            <div class="recent-meta">

                                <span>
                                    Submission:
                                    <?= dashboardEscape(
                                        $transaction[
                                            'reference_number'
                                        ]
                                    ) ?>
                                </span>


                                <?php if (
                                    !empty(
                                        $transaction[
                                            'completed_at'
                                        ]
                                    )
                                ): ?>

                                    <span>
                                        <?= dashboardDate(
                                            $transaction[
                                                'completed_at'
                                            ]
                                        ) ?>
                                    </span>

                                <?php endif; ?>

                            </div>

                        </div>


                        <div class="recent-item-details">

                            <div class="recent-amount">

                                <?= dashboardMoney(
                                    $transaction[
                                        'final_amount'
                                    ]
                                ) ?>

                            </div>


                            <span
                                class="status-badge status-completed"
                            >
                                <?= dashboardEscape(
                                    dashboardStatusLabel(
                                        $transaction[
                                            'transaction_status'
                                        ]
                                    )
                                ) ?>
                            </span>

                        </div>

                    </article>

                <?php endforeach; ?>

            </div>

        <?php endif; ?>

    </section>


</section>