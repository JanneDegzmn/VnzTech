<?php

header(
    'Cache-Control: no-store, no-cache, must-revalidate, max-age=0'
);

header(
    'Pragma: no-cache'
);

header(
    'Expires: 0'
);

require_once __DIR__ . '/../../backend/database.php';
require_once __DIR__ . '/../../backend/session.php';


/*
|--------------------------------------------------------------------------
| AUTHENTICATION
|--------------------------------------------------------------------------
*/

if (
    empty($_SESSION['user_logged_in']) ||
    empty($_SESSION['user_id'])
) {

    http_response_code(401);

    echo '
        <div class="portal-error">

            <h2>
                Session expired
            </h2>

            <p>
                Please log in again.
            </p>

        </div>
    ';

    exit;
}


$userId =
    (int) $_SESSION['user_id'];


if (
    $userId <= 0
) {

    http_response_code(401);

    echo '
        <div class="portal-error">

            <h2>
                Invalid session
            </h2>

            <p>
                Please log in again.
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

function submissionEscape(
    $value
): string {

    return htmlspecialchars(
        (string) (
            $value
            ?? ''
        ),
        ENT_QUOTES,
        'UTF-8'
    );

}


function submissionStatusLabel(
    $status
): string {

    return match (
        (string) $status
    ) {

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


function submissionStatusClass(
    $status
): string {

    return match (
        strtolower(
            (string) $status
        )
    ) {

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


function submissionFormatDate(
    $date
): string {

    if (
        !$date
    ) {

        return '—';

    }


    $timestamp =
        strtotime(
            $date
        );


    if (
        !$timestamp
    ) {

        return submissionEscape(
            $date
        );

    }


    return date(
        'M d, Y · h:i A',
        $timestamp
    );

}


function submissionMoney(
    $amount
): string {

    return '₱' .
        number_format(
            (float) $amount,
            2
        );

}


/*
|--------------------------------------------------------------------------
| CURRENT USER + SUBMISSIONS
|--------------------------------------------------------------------------
*/

try {

    /*
    |--------------------------------------------------------------------------
    | Current User
    |--------------------------------------------------------------------------
    */

    $userStmt =
        $pdo->prepare("
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


    $userStmt->execute([
        $userId
    ]);


    $user =
        $userStmt->fetch();


    if (
        !$user ||
        (int) $user['is_active'] !== 1
    ) {

        http_response_code(401);

        echo '
            <div class="portal-error">

                <h2>
                    Account unavailable
                </h2>

                <p>
                    Your account is currently unavailable.
                </p>

            </div>
        ';

        exit;

    }


    /*
    |--------------------------------------------------------------------------
    | Allowed Filters
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


    $filter =
        strtolower(
            trim(
                $_GET['status']
                ?? 'all'
            )
        );


    if (
        !in_array(
            $filter,
            $allowedFilters,
            true
        )
    ) {

        $filter =
            'all';

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
                SELECT COALESCE(
                    SUM(
                        si.seller_asking_price
                        *
                        GREATEST(
                            COALESCE(
                                si.quantity,
                                1
                            ),
                            1
                        )
                    ),
                    0
                )
                FROM submission_items si
                WHERE si.submission_id = s.id
            ) AS asking_total,


            (
                SELECT GROUP_CONCAT(
                    DISTINCT
                    CONCAT(

                        si.item_type,

                        CASE
                            WHEN
                                si.brand IS NOT NULL
                                AND si.brand <> ''
                            THEN
                                CONCAT(
                                    ' · ',
                                    si.brand
                                )
                            ELSE ''
                        END,

                        CASE
                            WHEN
                                si.model IS NOT NULL
                                AND si.model <> ''
                            THEN
                                CONCAT(
                                    ' ',
                                    si.model
                                )
                            ELSE ''
                        END

                    )
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


    $params = [
        $userId
    ];


    if (
        $filter !== 'all'
    ) {

        $sql .= "
            AND s.status = ?
        ";

        $params[] =
            $filter;

    }


    $sql .= "
        ORDER BY s.id DESC
    ";


    $submissionStmt =
        $pdo->prepare(
            $sql
        );


    $submissionStmt->execute(
        $params
    );


    $submissions =
        $submissionStmt->fetchAll();


    /*
    |--------------------------------------------------------------------------
    | FILTER COUNTS
    |--------------------------------------------------------------------------
    */

    $countStmt =
        $pdo->prepare("
            SELECT
                status,
                COUNT(*) AS total
            FROM submissions
            WHERE user_id = ?
            GROUP BY status
        ");


    $countStmt->execute([
        $userId
    ]);


    $statusCounts =
        [];


    foreach (
        $countStmt->fetchAll()
        as $row
    ) {

        $statusCounts[
            (string) $row['status']
        ] =
            (int) $row['total'];

    }


    $totalSubmissions =
        array_sum(
            $statusCounts
        );


} catch (
    Throwable $e
) {

    http_response_code(500);

    echo '
        <div class="portal-error">

            <h2>
                Unable to load submissions
            </h2>

            <p>
                Something went wrong while loading
                your submissions.
            </p>

        </div>
    ';

    exit;

}


/*
|--------------------------------------------------------------------------
| PROFILE PICTURE
|--------------------------------------------------------------------------
*/

$profilePicture =
    trim(
        (string) (
            $user['profile_picture']
            ?? ''
        )
    );


$profilePictureUrl =
    '';


if (
    $profilePicture !== ''
) {

    $normalizedPath =
        str_replace(
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
| FIRST NAME
|--------------------------------------------------------------------------
*/

$nameParts =
    preg_split(
        '/\s+/',
        trim(
            (string) $user['full_name']
        )
    );


$firstName =
    $nameParts[0]
    ?? $user['full_name'];

?>

<div class="portal-submissions-page">


    <!-- =====================================================
         HEADER
    ====================================================== -->

    <header class="submissions-header">

        <div class="header-copy">

            <div class="eyebrow">
                SELLER PORTAL
            </div>


            <h1>
                My submissions.
            </h1>


            <p>
                Track every phone and board
                you've submitted to VNZ TECH.
            </p>

        </div>


        <a
            href="/VNZ-TECH/sell.php?mode=account"
            class="sell-button"
        >
            + SELL AN ITEM
        </a>

    </header>


    <!-- =====================================================
         PROFILE STRIP
    ====================================================== -->

    <section class="profile-strip">

        <div class="profile-strip-left">

            <div class="profile-avatar">

                <?php if (
                    $profilePictureUrl !== ''
                ): ?>

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


    <!-- =====================================================
         FILTER PANEL
    ====================================================== -->

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
                href="/VNZ-TECH/account/dashboard.php?page=submissions&status=all"
                data-seller-page="submissions"
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
                href="/VNZ-TECH/account/dashboard.php?page=submissions&status=new_submission"
                data-seller-page="submissions"
                class="filter-chip <?= $filter === 'new_submission'
                    ? 'active'
                    : '' ?>"
            >

                New

                <span>
                    <?= number_format(
                        $statusCounts[
                            'new_submission'
                        ] ?? 0
                    ) ?>
                </span>

            </a>


            <a
                href="/VNZ-TECH/account/dashboard.php?page=submissions&status=under_review"
                data-seller-page="submissions"
                class="filter-chip <?= $filter === 'under_review'
                    ? 'active'
                    : '' ?>"
            >

                Under Review

                <span>
                    <?= number_format(
                        $statusCounts[
                            'under_review'
                        ] ?? 0
                    ) ?>
                </span>

            </a>


            <a
                href="/VNZ-TECH/account/dashboard.php?page=submissions&status=offer_pending"
                data-seller-page="submissions"
                class="filter-chip <?= $filter === 'offer_pending'
                    ? 'active'
                    : '' ?>"
            >

                Offer Pending

                <span>
                    <?= number_format(
                        $statusCounts[
                            'offer_pending'
                        ] ?? 0
                    ) ?>
                </span>

            </a>


            <a
                href="/VNZ-TECH/account/dashboard.php?page=submissions&status=offer_sent"
                data-seller-page="submissions"
                class="filter-chip <?= $filter === 'offer_sent'
                    ? 'active'
                    : '' ?>"
            >

                Offer Sent

                <span>
                    <?= number_format(
                        $statusCounts[
                            'offer_sent'
                        ] ?? 0
                    ) ?>
                </span>

            </a>


            <a
                href="/VNZ-TECH/account/dashboard.php?page=submissions&status=accepted"
                data-seller-page="submissions"
                class="filter-chip <?= $filter === 'accepted'
                    ? 'active'
                    : '' ?>"
            >

                Accepted

                <span>
                    <?= number_format(
                        $statusCounts[
                            'accepted'
                        ] ?? 0
                    ) ?>
                </span>

            </a>


            <a
                href="/VNZ-TECH/account/dashboard.php?page=submissions&status=declined"
                data-seller-page="submissions"
                class="filter-chip <?= $filter === 'declined'
                    ? 'active'
                    : '' ?>"
            >

                Declined

                <span>
                    <?= number_format(
                        $statusCounts[
                            'declined'
                        ] ?? 0
                    ) ?>
                </span>

            </a>


            <a
                href="/VNZ-TECH/account/dashboard.php?page=submissions&status=cancelled"
                data-seller-page="submissions"
                class="filter-chip <?= $filter === 'cancelled'
                    ? 'active'
                    : '' ?>"
            >

                Cancelled

                <span>
                    <?= number_format(
                        $statusCounts[
                            'cancelled'
                        ] ?? 0
                    ) ?>
                </span>

            </a>


            <a
                href="/VNZ-TECH/account/dashboard.php?page=submissions&status=completed"
                data-seller-page="submissions"
                class="filter-chip <?= $filter === 'completed'
                    ? 'active'
                    : '' ?>"
            >

                Completed

                <span>
                    <?= number_format(
                        $statusCounts[
                            'completed'
                        ] ?? 0
                    ) ?>
                </span>

            </a>

        </div>

    </section>


    <!-- =====================================================
         SUBMISSIONS
    ====================================================== -->

    <section class="submission-section">


        <?php if (
            !$submissions
        ): ?>


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

                    <?php if (
                        $filter !== 'all'
                    ): ?>

                        You don't have any submissions
                        with this status.

                    <?php else: ?>

                        Your submitted phones and
                        scrap boards will appear here.

                    <?php endif; ?>

                </p>


                <?php if (
                    $filter !== 'all'
                ): ?>

                    <a
                        href="/VNZ-TECH/account/dashboard.php?page=submissions&status=all"
                        data-seller-page="submissions"
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


                    <article class="submission-card">


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
                                            (int)
                                            $submission[
                                                'item_count'
                                            ]
                                        ) ?>

                                        item

                                        <?= (int)
                                            $submission[
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

                                        Qty

                                        <?= number_format(
                                            (int)
                                            $submission[
                                                'total_quantity'
                                            ]
                                        ) ?>

                                    </span>


                                    <span class="meta-dot">
                                        •
                                    </span>


                                    <span>

                                        <?= $submission[
                                            'selling_method'
                                        ] === 'wholesale'
                                            ? 'Wholesale'
                                            : 'Per Piece'
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

                            <div class="feedback-strip">

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


                        <div class="submission-card-footer">


                            <div class="footer-status-text">


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
                                                    (string)
                                                    $submission[
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


                                <!--
                                |--------------------------------------------------------------------------
                                | SELLER PORTAL VIEW
                                |--------------------------------------------------------------------------
                                |
                                | IMPORTANT:
                                | Do NOT send the seller to
                                | seller/tracking.php anymore.
                                |
                                | The dashboard router will load
                                | account/pages/submission.php
                                | inside #contentArea.
                                |--------------------------------------------------------------------------
                                -->


                                <a
                                    href="/VNZ-TECH/account/dashboard.php?page=submission&ref=<?= urlencode(
                                        $submission[
                                            'reference_number'
                                        ]
                                    ) ?>"
                                    data-seller-page="submission"
                                    class="view-button"
                                >

                                    VIEW SUBMISSION

                                    <span>
                                        →
                                    </span>

                                </a>


                            </div>

                        </div>


                    </article>


                <?php endforeach; ?>


            </div>


        <?php endif; ?>


    </section>


</div>