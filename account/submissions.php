<?php


require_once __DIR__ . '/../backend/site_status.php';
vnzEnforceSiteMaintenance('auto', false, true, 'submissions');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Cache-Control: post-check=0, pre-check=0', false);
header('Pragma: no-cache');
header('Expires: 0');

require_once __DIR__ . '/../backend/database.php';
require_once __DIR__ . '/../backend/session.php';

$isAjaxRequest =
    isset($_SERVER['HTTP_X_REQUESTED_WITH']) &&
    strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest';

if (
    !isset($_SESSION['user_logged_in']) ||
    $_SESSION['user_logged_in'] !== true
) {
    if ($isAjaxRequest) {
        http_response_code(401);
        exit('SESSION_EXPIRED');
    }

    header('Location: /VNZ-TECH/account/login.php');
    exit;
}

$userId = (int) ($_SESSION['user_id'] ?? 0);

if ($userId <= 0) {
    session_unset();
    session_destroy();

    if ($isAjaxRequest) {
        http_response_code(401);
        exit('SESSION_EXPIRED');
    }

    header(
        'Location: /VNZ-TECH/account/login.php?error=' .
        urlencode('Please log in again.')
    );

    exit;
}

/*
|--------------------------------------------------------------------------
| Helpers
|--------------------------------------------------------------------------
*/

function submissionEscape(?string $value): string
{
    return htmlspecialchars(
        (string) $value,
        ENT_QUOTES,
        'UTF-8'
    );
}

function submissionStatusLabel(?string $status): string
{
    return match ((string) $status) {

        'new_submission'
            => 'New Submission',

        'under_review'
            => 'Under Review',

        'offer_pending'
            => 'Offer Pending',

        'offer_sent'
            => 'Offer Sent',

        'accepted'
            => 'Accepted',

        'declined'
            => 'Declined',

        'cancelled'
            => 'Cancelled',

        'completed'
            => 'Completed',

        default
            => ucwords(
                str_replace(
                    '_',
                    ' ',
                    (string) $status
                )
            )
    };
}

function submissionStatusClass(?string $status): string
{
    return match (strtolower((string) $status)) {

        'new_submission'
            => 'status-new',

        'under_review'
            => 'status-review',

        'offer_pending'
            => 'status-pending',

        'offer_sent'
            => 'status-offer',

        'accepted'
            => 'status-accepted',

        'declined'
            => 'status-declined',

        'cancelled'
            => 'status-cancelled',

        'completed'
            => 'status-completed',

        default
            => 'status-default'
    };
}

function submissionFormatDate(?string $date): string
{
    if (!$date) {
        return '—';
    }

    $timestamp = strtotime($date);

    if (!$timestamp) {
        return submissionEscape($date);
    }

    return date(
        'M d, Y · h:i A',
        $timestamp
    );
}

function submissionMoney($amount): string
{
    return '₱' . number_format(
        (float) $amount,
        2
    );
}

/*
|--------------------------------------------------------------------------
| Current User
|--------------------------------------------------------------------------
*/

try {

    $userStmt = $pdo->prepare("
        SELECT
            id,
            full_name,
            email,
            mobile,
            profile_picture,
            language,
            is_active
        FROM users
        WHERE id = ?
        LIMIT 1
    ");

    $userStmt->execute([$userId]);

    $user = $userStmt->fetch();

    if (
        !$user ||
        (int) $user['is_active'] !== 1
    ) {

        session_unset();
        session_destroy();

        if ($isAjaxRequest) {
            http_response_code(401);
            exit('ACCOUNT_UNAVAILABLE');
        }

        header(
            'Location: /VNZ-TECH/account/login.php?error=' .
            urlencode('Your account is unavailable.')
        );

        exit;
    }

    /*
    |--------------------------------------------------------------------------
    | Filter
    |--------------------------------------------------------------------------
    */

    $allowedFilters = [
        'all',
        'new_submission',
        'under_review',
        'offer_pending',
        'offer_sent',
        'accepted',
        'declined',
        'cancelled',
        'completed'
    ];

    $filter = strtolower(
        trim($_GET['status'] ?? 'all')
    );

    if (
        !in_array(
            $filter,
            $allowedFilters,
            true
        )
    ) {
        $filter = 'all';
    }

    /*
    |--------------------------------------------------------------------------
    | Submission Query
    |--------------------------------------------------------------------------
    */

    $sql = "
        SELECT
            s.id,
            s.reference_number,
            s.status,
            s.selling_method,
            s.bulk_format,
            s.seller_notes,
            s.admin_feedback,
            s.created_at,
            s.updated_at,

            (
                SELECT COUNT(*)
                FROM submission_items si
                WHERE si.submission_id = s.id
            ) AS item_count,

            (
                SELECT COALESCE(
                    SUM(si.quantity),
                    0
                )
                FROM submission_items si
                WHERE si.submission_id = s.id
            ) AS total_quantity,

            (
                SELECT COALESCE(MAX(si.quantity_is_estimated), 0)
                FROM submission_items si
                WHERE si.submission_id = s.id
            ) AS has_estimated_quantity,

            (
                SELECT COALESCE(
                    SUM(
                        CASE
                            WHEN s.bulk_format = 1
                            THEN si.seller_asking_price
                            ELSE si.seller_asking_price * GREATEST(COALESCE(si.quantity, 1), 1)
                        END
                    ),
                    0
                )
                FROM submission_items si
                WHERE si.submission_id = s.id
            ) AS asking_total,

            (
                SELECT GROUP_CONCAT(
                    DISTINCT
                    CASE
                        WHEN s.bulk_format = 1 AND si.item_type IN ('whole_phone', 'Whole Phone')
                        THEN 'Phone bulk'
                        WHEN s.bulk_format = 1 AND si.item_type IN ('scrap_board', 'Scrap Board')
                        THEN 'Scrap board bulk'
                        ELSE CONCAT(
                            si.item_type,
                            CASE
                                WHEN si.brand IS NOT NULL AND si.brand <> ''
                                THEN CONCAT(' · ', si.brand)
                                ELSE ''
                            END,
                            CASE
                                WHEN si.model IS NOT NULL AND si.model <> ''
                                THEN CONCAT(' ', si.model)
                                ELSE ''
                            END
                        )
                    END
                    ORDER BY si.id ASC
                    SEPARATOR ', '
                )
                FROM submission_items si
                WHERE si.submission_id = s.id
            ) AS item_summary,

            (
                SELECT
                    o.offer_amount
                FROM offers o
                WHERE o.submission_id = s.id
                ORDER BY o.id DESC
                LIMIT 1
            ) AS latest_offer_amount,

            (
                SELECT
                    o.status
                FROM offers o
                WHERE o.submission_id = s.id
                ORDER BY o.id DESC
                LIMIT 1
            ) AS latest_offer_status,

            (
                SELECT
                    o.sender_type
                FROM offers o
                WHERE o.submission_id = s.id
                ORDER BY o.id DESC
                LIMIT 1
            ) AS latest_offer_sender

        FROM submissions s
        WHERE s.user_id = ?
    ";

    $params = [$userId];

    if ($filter !== 'all') {

        $sql .= "
            AND s.status = ?
        ";

        $params[] = $filter;
    }

    $sql .= "
        ORDER BY s.id DESC
    ";

    $submissionStmt = $pdo->prepare($sql);

    $submissionStmt->execute($params);

    $submissions = $submissionStmt->fetchAll();

    /*
    |--------------------------------------------------------------------------
    | Filter Counts
    |--------------------------------------------------------------------------
    */

    $countStmt = $pdo->prepare("
        SELECT
            status,
            COUNT(*) AS total
        FROM submissions
        WHERE user_id = ?
        GROUP BY status
    ");

    $countStmt->execute([$userId]);

    $statusCounts = [];

    foreach (
        $countStmt->fetchAll()
        as $row
    ) {

        $statusCounts[
            (string) $row['status']
        ] = (int) $row['total'];
    }

    $totalSubmissions = array_sum(
        $statusCounts
    );

} catch (Throwable $e) {

    if ($isAjaxRequest) {
        http_response_code(500);
        exit('LOAD_ERROR');
    }

    die(
        'Unable to load your submissions right now.'
    );
}

/*
|--------------------------------------------------------------------------
| Profile Picture
|--------------------------------------------------------------------------
*/

$profilePicture =
    (string) (
        $user['profile_picture']
        ?? ''
    );

$profilePictureUrl = '';

if ($profilePicture !== '') {

    $normalizedPath = str_replace(
        '\\',
        '/',
        $profilePicture
    );

    if (
        str_starts_with(
            $normalizedPath,
            '/'
        )
    ) {

        $profilePictureUrl =
            $normalizedPath;

    } else {

        $profilePictureUrl =
            '/VNZ-TECH/' .
            ltrim(
                $normalizedPath,
                '/'
            );
    }
}

/*
|--------------------------------------------------------------------------
| First Name
|--------------------------------------------------------------------------
*/

$nameParts = preg_split(
    '/\s+/',
    trim(
        (string) $user['full_name']
    )
);

$firstName =
    $nameParts[0]
    ?? $user['full_name'];

/*
|--------------------------------------------------------------------------
| AJAX RESPONSE
|--------------------------------------------------------------------------
|
| IMPORTANT:
| When called by seller_portal.js, only this content is returned.
| The sidebar, html, head, and body are NOT returned.
|
|--------------------------------------------------------------------------
*/

if ($isAjaxRequest):

?>

<div class="portal-submissions-page">

    <header class="submissions-header">

        <div class="header-copy">

            <div class="eyebrow">
                SELLER PORTAL
            </div>

            <h1>
                My submissions.
            </h1>

            <p>
                Track every phone and board you've
                submitted to VNZ TECH.
            </p>

        </div>

        <a
            href="/VNZ-TECH/sell.php?mode=account"
            class="sell-button"
        >
            + SELL AN ITEM
        </a>

    </header>

    <section class="profile-strip">

        <div class="profile-strip-left">

            <div class="profile-avatar">

                <?php if ($profilePictureUrl !== ''): ?>

                    <img
                        src="<?= submissionEscape(
                            $profilePictureUrl
                        ) ?>"
                        alt="Profile Picture"
                    >

                <?php else: ?>

                    <?= submissionEscape(
                        strtoupper(
                            substr(
                                $firstName,
                                0,
                                1
                            )
                        )
                    ) ?>

                <?php endif; ?>

            </div>

            <div>

                <strong>
                    <?= submissionEscape(
                        $user['full_name']
                    ) ?>
                </strong>

                <span>
                    <?= submissionEscape(
                        $user['email']
                    ) ?>
                </span>

            </div>

        </div>

    </section>

    <section class="filter-panel">

        <div class="filter-heading">

            <div>

                <span>
                    SUBMISSIONS
                </span>

                <strong>
                    <?= number_format(
                        $totalSubmissions
                    ) ?>
                </strong>

            </div>

            <p>
                Select a status to filter your submissions.
            </p>

        </div>

        <div class="filter-list">

            <a
                href="/VNZ-TECH/account/submissions.php?status=all"
                class="filter-chip <?= $filter === 'all'
                    ? 'active'
                    : '' ?>"
                data-submission-filter="all"
            >
                All

                <span>
                    <?= number_format(
                        $totalSubmissions
                    ) ?>
                </span>

            </a>

            <a
                href="/VNZ-TECH/account/submissions.php?status=new_submission"
                class="filter-chip <?= $filter === 'new_submission'
                    ? 'active'
                    : '' ?>"
                data-submission-filter="new_submission"
            >
                New

                <span>
                    <?= number_format(
                        $statusCounts['new_submission']
                        ?? 0
                    ) ?>
                </span>

            </a>

            <a
                href="/VNZ-TECH/account/submissions.php?status=under_review"
                class="filter-chip <?= $filter === 'under_review'
                    ? 'active'
                    : '' ?>"
                data-submission-filter="under_review"
            >
                Under Review

                <span>
                    <?= number_format(
                        $statusCounts['under_review']
                        ?? 0
                    ) ?>
                </span>

            </a>

            <a
                href="/VNZ-TECH/account/submissions.php?status=offer_pending"
                class="filter-chip <?= $filter === 'offer_pending'
                    ? 'active'
                    : '' ?>"
                data-submission-filter="offer_pending"
            >
                Offer Pending

                <span>
                    <?= number_format(
                        $statusCounts['offer_pending']
                        ?? 0
                    ) ?>
                </span>

            </a>

            <a
                href="/VNZ-TECH/account/submissions.php?status=offer_sent"
                class="filter-chip <?= $filter === 'offer_sent'
                    ? 'active'
                    : '' ?>"
                data-submission-filter="offer_sent"
            >
                Offer Sent

                <span>
                    <?= number_format(
                        $statusCounts['offer_sent']
                        ?? 0
                    ) ?>
                </span>

            </a>

            <a
                href="/VNZ-TECH/account/submissions.php?status=accepted"
                class="filter-chip <?= $filter === 'accepted'
                    ? 'active'
                    : '' ?>"
                data-submission-filter="accepted"
            >
                Accepted

                <span>
                    <?= number_format(
                        $statusCounts['accepted']
                        ?? 0
                    ) ?>
                </span>

            </a>

            <a
                href="/VNZ-TECH/account/submissions.php?status=declined"
                class="filter-chip <?= $filter === 'declined'
                    ? 'active'
                    : '' ?>"
                data-submission-filter="declined"
            >
                Declined

                <span>
                    <?= number_format(
                        $statusCounts['declined']
                        ?? 0
                    ) ?>
                </span>

            </a>

            <a
                href="/VNZ-TECH/account/submissions.php?status=completed"
                class="filter-chip <?= $filter === 'completed'
                    ? 'active'
                    : '' ?>"
                data-submission-filter="completed"
            >
                Completed

                <span>
                    <?= number_format(
                        $statusCounts['completed']
                        ?? 0
                    ) ?>
                </span>

            </a>

        </div>

    </section>

    <section class="submission-section">

        <?php if (!$submissions): ?>

            <div class="empty-state">

                <div class="empty-icon">
                    ▣
                </div>

                <span class="empty-label">
                    NO SUBMISSIONS FOUND
                </span>

                <h2>
                    Nothing here yet.
                </h2>

                <p>

                    <?php if ($filter !== 'all'): ?>

                        You don't have any submissions
                        with this status.

                    <?php else: ?>

                        Your submitted phones and
                        scrap boards will appear here.

                    <?php endif; ?>

                </p>

                <?php if ($filter !== 'all'): ?>

                    <a
                        href="/VNZ-TECH/account/submissions.php?status=all"
                        class="empty-button secondary"
                        data-submission-filter="all"
                    >
                        VIEW ALL SUBMISSIONS
                    </a>

                <?php else: ?>

                    <a
                        href="/VNZ-TECH/sell.php?mode=account"
                        class="empty-button"
                    >
                        SELL YOUR FIRST ITEM
                    </a>

                <?php endif; ?>

            </div>

        <?php else: ?>

            <div class="submission-list">

                <?php foreach (
                    $submissions
                    as $submission
                ): ?>

                    <article
                        class="submission-card"
                    >

                        <div class="submission-card-top">

                            <div class="submission-identity">

                                <div class="reference-block">

                                    <span>
                                        REFERENCE
                                    </span>

                                    <strong>
                                        <?= submissionEscape(
                                            $submission[
                                                'reference_number'
                                            ]
                                        ) ?>
                                    </strong>

                                </div>

                                <div class="submission-date">

                                    <span>
                                        SUBMITTED
                                    </span>

                                    <strong>
                                        <?= submissionFormatDate(
                                            $submission[
                                                'created_at'
                                            ]
                                        ) ?>
                                    </strong>

                                </div>

                            </div>

                            <span
                                class="
                                    status-badge
                                    <?= submissionStatusClass(
                                        $submission['status']
                                    ) ?>
                                "
                            >
                                <?= submissionEscape(
                                    submissionStatusLabel(
                                        $submission['status']
                                    )
                                ) ?>
                            </span>

                        </div>

                        <div class="submission-card-body">

                            <div class="submission-item-summary">

                                <span class="summary-label">
                                    ITEMS
                                </span>

                                <h2>
                                    <?= submissionEscape(
                                        $submission[
                                            'item_summary'
                                        ]
                                        ?: 'Item Submission'
                                    ) ?>
                                </h2>

                                <div class="summary-meta">

                                    <span>

                                        <?= number_format(
                                            (int) $submission[
                                                'item_count'
                                            ]
                                        ) ?>

                                        item

                                        <?= (int) $submission[
                                            'item_count'
                                        ] === 1
                                            ? ''
                                            : 's'
                                        ?>

                                    </span>

                                    <span class="meta-dot">
                                        •
                                    </span>

                                    <span>

                                        <?= !empty($submission['bulk_format']) && !empty($submission['has_estimated_quantity']) ? 'Estimated Qty' : 'Qty' ?>

                                        <?= number_format(
                                            (int) $submission[
                                                'total_quantity'
                                            ]
                                        ) ?>

                                    </span>

                                    <span class="meta-dot">
                                        •
                                    </span>

                                    <span>

                                        <?= !empty($submission['bulk_format'])
                                            ? 'Bulk'
                                            : ($submission['selling_method'] === 'wholesale' ? 'Wholesale' : 'Per Piece')
                                        ?>

                                    </span>

                                </div>

                            </div>

                            <div class="submission-financial">

                                <div>

                                    <span>
                                        ASKING PRICE
                                    </span>

                                    <strong>
                                        <?= submissionMoney(
                                            $submission[
                                                'asking_total'
                                            ]
                                        ) ?>
                                    </strong>

                                </div>

                                <div>

                                    <span>
                                        CURRENT OFFER
                                    </span>

                                    <?php if (
                                        $submission[
                                            'latest_offer_amount'
                                        ] !== null
                                    ): ?>

                                        <strong
                                            class="offer-amount"
                                        >
                                            <?= submissionMoney(
                                                $submission[
                                                    'latest_offer_amount'
                                                ]
                                            ) ?>
                                        </strong>

                                    <?php else: ?>

                                        <strong
                                            class="offer-none"
                                        >
                                            —
                                        </strong>

                                    <?php endif; ?>

                                </div>

                            </div>

                        </div>

                        <?php if (
                            !empty(
                                $submission[
                                    'admin_feedback'
                                ]
                            )
                        ): ?>

                            <div
                                class="feedback-strip"
                            >

                                <span>
                                    VNZ TECH MESSAGE
                                </span>

                                <p>
                                    <?= submissionEscape(
                                        $submission[
                                            'admin_feedback'
                                        ]
                                    ) ?>
                                </p>

                            </div>

                        <?php endif; ?>

                        <div
                            class="submission-card-footer"
                        >

                            <div
                                class="footer-status-text"
                            >

                                <?php if (
                                    $submission[
                                        'latest_offer_status'
                                    ]
                                ): ?>

                                    <span>
                                        LATEST OFFER
                                    </span>

                                    <strong>
                                        <?= submissionEscape(
                                            ucwords(
                                                str_replace(
                                                    '_',
                                                    ' ',
                                                    (string) $submission[
                                                        'latest_offer_status'
                                                    ]
                                                )
                                            )
                                        ) ?>
                                    </strong>

                                <?php else: ?>

                                    <span>
                                        STATUS
                                    </span>

                                    <strong>
                                        <?= submissionEscape(
                                            submissionStatusLabel(
                                                $submission[
                                                    'status'
                                                ]
                                            )
                                        ) ?>
                                    </strong>

                                <?php endif; ?>

                            </div>

                            <div class="footer-actions">

                                <a
                                    href="/VNZ-TECH/seller/tracking.php?ref=<?= urlencode(
                                        $submission[
                                            'reference_number'
                                        ]
                                    ) ?>&mobile=<?= urlencode(
                                        $user['mobile']
                                    ) ?>"
                                    class="view-button"
                                >
                                    VIEW SUBMISSION
                                    <span>→</span>
                                </a>

                            </div>

                        </div>

                    </article>

                <?php endforeach; ?>

            </div>

        <?php endif; ?>

    </section>

</div>

<?php

exit;

endif;

?>

<!DOCTYPE html>

<html
    lang="<?= submissionEscape(
        $user['language'] === 'fil'
            ? 'fil'
            : 'en'
    ) ?>"
>

<head>

    <meta charset="UTF-8">

    <meta
        name="viewport"
        content="width=device-width, initial-scale=1.0"
    >

    <meta
        name="theme-color"
        content="#07090c"
    >

    <title>
        My Submissions | VNZ TECH
    </title>

    <link
        rel="stylesheet"
        href="/VNZ-TECH/assets/css/seller_submissions.css"
    >
    <link
        rel="stylesheet"
        href="/VNZ-TECH/assets/css/vnz_dialog.css?v=<?= filemtime(__DIR__ . '/../assets/css/vnz_dialog.css') ?>"
    >
    <script
        src="/VNZ-TECH/assets/js/vnz-dialog.js"
        defer
    ></script>

<?php $siteStatusArea = 'public'; require __DIR__ . '/../includes/site-status-head.php'; ?>
</head>

<body>

<div class="submissions-layout">

    <aside class="submissions-sidebar">

        <div class="sidebar-brand">

            <div class="brand-mark">
                V
            </div>

            <div>

                <div class="brand-name">
                    VNZ TECH
                </div>

                <div class="brand-subtitle">
                    SELLER PORTAL
                </div>

            </div>

        </div>

        <nav class="sidebar-nav">

            <a
                href="/VNZ-TECH/account/dashboard.php"
                class="nav-item"
            >
                <span class="nav-icon">
                    ⌂
                </span>

                <span>
                    Dashboard
                </span>
            </a>

            <a
                href="/VNZ-TECH/account/submissions.php"
                class="nav-item active"
            >
                <span class="nav-icon">
                    ▣
                </span>

                <span>
                    My Submissions
                </span>
            </a>

            <a
                href="#"
                class="nav-item"
                onclick="return false;"
            >
                <span class="nav-icon">
                    ◈
                </span>

                <span>
                    Offers
                </span>
            </a>

            <a
                href="#"
                class="nav-item"
                onclick="return false;"
            >
                <span class="nav-icon">
                    ▤
                </span>

                <span>
                    Transactions
                </span>
            </a>

            <a
                href="#"
                class="nav-item"
                onclick="return false;"
            >
                <span class="nav-icon">
                    ◎
                </span>

                <span>
                    Profile
                </span>
            </a>

        </nav>

        <div class="sidebar-bottom">

            <a
                href="/VNZ-TECH/account/logout.php"
                class="logout-link"
                data-vnz-confirm="Are you sure you want to log out?"
                data-vnz-confirm-title="Log Out?"
                data-vnz-confirm-label="Log Out"
                data-vnz-confirm-danger="true"
            >

                <span class="nav-icon">
                    ↪
                </span>

                <span>
                    Log Out
                </span>

            </a>

        </div>

    </aside>

    <main class="submissions-main">

        <header class="submissions-header">

            <div class="header-copy">

                <div class="eyebrow">
                    SELLER PORTAL
                </div>

                <h1>
                    My submissions.
                </h1>

                <p>
                    Track every phone and board you've
                    submitted to VNZ TECH.
                </p>

            </div>

            <a
                href="/VNZ-TECH/sell.php?mode=account"
                class="sell-button"
            >
                + SELL AN ITEM
            </a>

        </header>

        <section class="profile-strip">

            <div class="profile-strip-left">

                <div class="profile-avatar">

                    <?php if ($profilePictureUrl !== ''): ?>

                        <img
                            src="<?= submissionEscape(
                                $profilePictureUrl
                            ) ?>"
                            alt="Profile Picture"
                        >

                    <?php else: ?>

                        <?= submissionEscape(
                            strtoupper(
                                substr(
                                    $firstName,
                                    0,
                                    1
                                )
                            )
                        ) ?>

                    <?php endif; ?>

                </div>

                <div>

                    <strong>
                        <?= submissionEscape(
                            $user['full_name']
                        ) ?>
                    </strong>

                    <span>
                        <?= submissionEscape(
                            $user['email']
                        ) ?>
                    </span>

                </div>

            </div>

            <a
                href="/VNZ-TECH/account/dashboard.php"
                class="dashboard-link"
            >
                ← Dashboard
            </a>

        </section>

        <section class="filter-panel">

            <div class="filter-heading">

                <div>

                    <span>
                        SUBMISSIONS
                    </span>

                    <strong>
                        <?= number_format(
                            $totalSubmissions
                        ) ?>
                    </strong>

                </div>

                <p>
                    Select a status to filter your submissions.
                </p>

            </div>

            <div class="filter-list">

                <a
                    href="?status=all"
                    class="filter-chip <?= $filter === 'all'
                        ? 'active'
                        : '' ?>"
                >
                    All

                    <span>
                        <?= number_format(
                            $totalSubmissions
                        ) ?>
                    </span>

                </a>

                <a
                    href="?status=new_submission"
                    class="filter-chip <?= $filter === 'new_submission'
                        ? 'active'
                        : '' ?>"
                >
                    New

                    <span>
                        <?= number_format(
                            $statusCounts['new_submission']
                            ?? 0
                        ) ?>
                    </span>

                </a>

                <a
                    href="?status=under_review"
                    class="filter-chip <?= $filter === 'under_review'
                        ? 'active'
                        : '' ?>"
                >
                    Under Review

                    <span>
                        <?= number_format(
                            $statusCounts['under_review']
                            ?? 0
                        ) ?>
                    </span>

                </a>

                <a
                    href="?status=offer_pending"
                    class="filter-chip <?= $filter === 'offer_pending'
                        ? 'active'
                        : '' ?>"
                >
                    Offer Pending

                    <span>
                        <?= number_format(
                            $statusCounts['offer_pending']
                            ?? 0
                        ) ?>
                    </span>

                </a>

                <a
                    href="?status=offer_sent"
                    class="filter-chip <?= $filter === 'offer_sent'
                        ? 'active'
                        : '' ?>"
                >
                    Offer Sent

                    <span>
                        <?= number_format(
                            $statusCounts['offer_sent']
                            ?? 0
                        ) ?>
                    </span>

                </a>

                <a
                    href="?status=accepted"
                    class="filter-chip <?= $filter === 'accepted'
                        ? 'active'
                        : '' ?>"
                >
                    Accepted

                    <span>
                        <?= number_format(
                            $statusCounts['accepted']
                            ?? 0
                        ) ?>
                    </span>

                </a>

                <a
                    href="?status=declined"
                    class="filter-chip <?= $filter === 'declined'
                        ? 'active'
                        : '' ?>"
                >
                    Declined

                    <span>
                        <?= number_format(
                            $statusCounts['declined']
                            ?? 0
                        ) ?>
                    </span>

                </a>

                <a
                    href="?status=completed"
                    class="filter-chip <?= $filter === 'completed'
                        ? 'active'
                        : '' ?>"
                >
                    Completed

                    <span>
                        <?= number_format(
                            $statusCounts['completed']
                            ?? 0
                        ) ?>
                    </span>

                </a>

            </div>

        </section>

        <section class="submission-section">

            <?php if (!$submissions): ?>

                <div class="empty-state">

                    <div class="empty-icon">
                        ▣
                    </div>

                    <span class="empty-label">
                        NO SUBMISSIONS FOUND
                    </span>

                    <h2>
                        Nothing here yet.
                    </h2>

                    <p>

                        <?php if ($filter !== 'all'): ?>

                            You don't have any submissions
                            with this status.

                        <?php else: ?>

                            Your submitted phones and
                            scrap boards will appear here.

                        <?php endif; ?>

                    </p>

                    <?php if ($filter !== 'all'): ?>

                        <a
                            href="?status=all"
                            class="empty-button secondary"
                        >
                            VIEW ALL SUBMISSIONS
                        </a>

                    <?php else: ?>

                        <a
                            href="/VNZ-TECH/sell.php?mode=account"
                            class="empty-button"
                        >
                            SELL YOUR FIRST ITEM
                        </a>

                    <?php endif; ?>

                </div>

            <?php else: ?>

                <div class="submission-list">

                    <?php foreach (
                        $submissions
                        as $submission
                    ): ?>

                        <article
                            class="submission-card"
                        >

                            <div class="submission-card-top">

                                <div class="submission-identity">

                                    <div class="reference-block">

                                        <span>
                                            REFERENCE
                                        </span>

                                        <strong>
                                            <?= submissionEscape(
                                                $submission[
                                                    'reference_number'
                                                ]
                                            ) ?>
                                        </strong>

                                    </div>

                                    <div class="submission-date">

                                        <span>
                                            SUBMITTED
                                        </span>

                                        <strong>
                                            <?= submissionFormatDate(
                                                $submission[
                                                    'created_at'
                                                ]
                                            ) ?>
                                        </strong>

                                    </div>

                                </div>

                                <span
                                    class="
                                        status-badge
                                        <?= submissionStatusClass(
                                            $submission['status']
                                        ) ?>
                                    "
                                >
                                    <?= submissionEscape(
                                        submissionStatusLabel(
                                            $submission['status']
                                        )
                                    ) ?>
                                </span>

                            </div>

                            <div class="submission-card-body">

                                <div
                                    class="submission-item-summary"
                                >

                                    <span class="summary-label">
                                        ITEMS
                                    </span>

                                    <h2>
                                        <?= submissionEscape(
                                            $submission[
                                                'item_summary'
                                            ]
                                            ?: 'Item Submission'
                                        ) ?>
                                    </h2>

                                    <div class="summary-meta">

                                        <span>

                                            <?= number_format(
                                                (int) $submission[
                                                    'item_count'
                                                ]
                                            ) ?>

                                            item

                                            <?= (int) $submission[
                                                'item_count'
                                            ] === 1
                                                ? ''
                                                : 's'
                                            ?>

                                        </span>

                                        <span class="meta-dot">
                                            •
                                        </span>

                                        <span>

                                            <?= !empty($submission['bulk_format']) && !empty($submission['has_estimated_quantity']) ? 'Estimated Qty' : 'Qty' ?>

                                            <?= number_format(
                                                (int) $submission[
                                                    'total_quantity'
                                                ]
                                            ) ?>

                                        </span>

                                        <span class="meta-dot">
                                            •
                                        </span>

                                        <span>

                                            <?= !empty($submission['bulk_format'])
                                                ? 'Bulk'
                                                : ($submission['selling_method'] === 'wholesale' ? 'Wholesale' : 'Per Piece')
                                            ?>

                                        </span>

                                    </div>

                                </div>

                                <div
                                    class="submission-financial"
                                >

                                    <div>

                                        <span>
                                            ASKING PRICE
                                        </span>

                                        <strong>
                                            <?= submissionMoney(
                                                $submission[
                                                    'asking_total'
                                                ]
                                            ) ?>
                                        </strong>

                                    </div>

                                    <div>

                                        <span>
                                            CURRENT OFFER
                                        </span>

                                        <?php if (
                                            $submission[
                                                'latest_offer_amount'
                                            ] !== null
                                        ): ?>

                                            <strong
                                                class="offer-amount"
                                            >
                                                <?= submissionMoney(
                                                    $submission[
                                                        'latest_offer_amount'
                                                    ]
                                                ) ?>
                                            </strong>

                                        <?php else: ?>

                                            <strong
                                                class="offer-none"
                                            >
                                                —
                                            </strong>

                                        <?php endif; ?>

                                    </div>

                                </div>

                            </div>

                            <?php if (
                                !empty(
                                    $submission[
                                        'admin_feedback'
                                    ]
                                )
                            ): ?>

                                <div
                                    class="feedback-strip"
                                >

                                    <span>
                                        VNZ TECH MESSAGE
                                    </span>

                                    <p>
                                        <?= submissionEscape(
                                            $submission[
                                                'admin_feedback'
                                            ]
                                        ) ?>
                                    </p>

                                </div>

                            <?php endif; ?>

                            <div
                                class="submission-card-footer"
                            >

                                <div
                                    class="footer-status-text"
                                >

                                    <?php if (
                                        $submission[
                                            'latest_offer_status'
                                        ]
                                    ): ?>

                                        <span>
                                            LATEST OFFER
                                        </span>

                                        <strong>
                                            <?= submissionEscape(
                                                ucwords(
                                                    str_replace(
                                                        '_',
                                                        ' ',
                                                        (string) $submission[
                                                            'latest_offer_status'
                                                        ]
                                                    )
                                                )
                                            ) ?>
                                        </strong>

                                    <?php else: ?>

                                        <span>
                                            STATUS
                                        </span>

                                        <strong>
                                            <?= submissionEscape(
                                                submissionStatusLabel(
                                                    $submission[
                                                        'status'
                                                    ]
                                                )
                                            ) ?>
                                        </strong>

                                    <?php endif; ?>

                                </div>

                                <div class="footer-actions">

                                    <a
                                        href="/VNZ-TECH/seller/tracking.php?ref=<?= urlencode(
                                            $submission[
                                                'reference_number'
                                            ]
                                        ) ?>&mobile=<?= urlencode(
                                            $user['mobile']
                                        ) ?>"
                                        class="view-button"
                                    >
                                        VIEW SUBMISSION
                                        <span>→</span>
                                    </a>

                                </div>

                            </div>

                        </article>

                    <?php endforeach; ?>

                </div>

            <?php endif; ?>

        </section>

    </main>

</div>

</body>
</html>