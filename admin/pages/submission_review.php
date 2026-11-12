<?php

require_once __DIR__ . '/../../backend/database.php';
require_once __DIR__ . '/../../backend/session.php';
require_once __DIR__ . '/../../backend/auth.php';
require_once __DIR__ . '/../../backend/csrf.php';

requireAdminLogin();

if (!hasPermission('view_submissions')) {
    http_response_code(403);
    exit('You do not have permission to view submissions.');
}

$submissionId = isset($_GET['id'])
    ? (int) $_GET['id']
    : 0;

if ($submissionId <= 0) {
    http_response_code(400);
    exit('Invalid submission ID.');
}


/*
|--------------------------------------------------------------------------
| Helper Functions
|--------------------------------------------------------------------------
*/

function e($value)
{
    return htmlspecialchars(
        (string) $value,
        ENT_QUOTES,
        'UTF-8'
    );
}


function peso($amount)
{
    if ($amount === null || $amount === '') {
        return '—';
    }

    return '₱' . number_format(
        (float) $amount,
        2
    );
}


function statusLabel($status)
{
    $labels = [
        'new_submission' => 'New Submission',
        'under_review'   => 'Under Review',
        'offer_pending'  => 'Offer Pending',
        'offer_sent'     => 'Offer Sent',
        'accepted'       => 'Accepted',
        'declined'       => 'Declined',
        'cancelled'      => 'Cancelled',
        'completed'      => 'Completed'
    ];

    return $labels[$status]
        ?? ucwords(
            str_replace(
                '_',
                ' ',
                $status
            )
        );
}


function statusClass($status)
{
    $classes = [
        'new_submission' => 'status-new',
        'under_review'   => 'status-review',
        'offer_pending'  => 'status-pending',
        'offer_sent'     => 'status-offer',
        'accepted'       => 'status-accepted',
        'declined'       => 'status-declined',
        'cancelled'      => 'status-cancelled',
        'completed'      => 'status-completed'
    ];

    return $classes[$status]
        ?? 'status-default';
}


function damageLabel($level)
{
    $labels = [
        1 => '1 — Minor',
        2 => '2 — Moderate',
        3 => '3 — Major',
        4 => '4 — Dead / Critical',
        5 => '5 — For Scrap'
    ];

    return $labels[(int) $level]
        ?? '—';
}


function senderLabel($senderType)
{
    return $senderType === 'seller'
        ? 'SELLER'
        : 'VNZ TECH';
}


function senderClass($senderType)
{
    return $senderType === 'seller'
        ? 'offer-sender-seller'
        : 'offer-sender-admin';
}


/*
|--------------------------------------------------------------------------
| Get Submission
|--------------------------------------------------------------------------
*/

$stmt = $pdo->prepare("
    SELECT
        s.id,
        s.reference_number,
        s.seller_name,
        s.seller_mobile,
        s.seller_address,
        s.selling_method,
        s.status,
        s.assigned_admin_id,
        s.seller_notes,
        s.admin_feedback,
        s.created_at,
        s.updated_at,

        a.first_name AS assigned_first_name,
        a.last_name AS assigned_last_name

    FROM submissions s

    LEFT JOIN admins a
        ON a.id = s.assigned_admin_id

    WHERE s.id = ?

    LIMIT 1
");

$stmt->execute([
    $submissionId
]);

$submission = $stmt->fetch();

if (!$submission) {
    http_response_code(404);
    exit('Submission not found.');
}


/*
|--------------------------------------------------------------------------
| Get Items
|--------------------------------------------------------------------------
*/

$itemStmt = $pdo->prepare("
    SELECT
        id,
        submission_id,
        item_type,
        brand,
        model,
        storage,
        issue,
        issue_details,
        damage_level,
        board_code,
        quantity,
        seller_asking_price,
        additional_information,
        created_at,
        updated_at

    FROM submission_items

    WHERE submission_id = ?

    ORDER BY id ASC
");

$itemStmt->execute([
    $submissionId
]);

$items = $itemStmt->fetchAll();


/*
|--------------------------------------------------------------------------
| Get Photos
|--------------------------------------------------------------------------
*/

$photosByItem = [];

if (!empty($items)) {

    $itemIds = array_column(
        $items,
        'id'
    );

    $placeholders = implode(
        ',',
        array_fill(
            0,
            count($itemIds),
            '?'
        )
    );

    $photoStmt = $pdo->prepare("
        SELECT
            id,
            submission_item_id,
            file_name,
            file_path,
            original_name,
            mime_type,
            file_size,
            photo_type,
            sort_order,
            created_at

        FROM submission_photos

        WHERE submission_item_id IN ($placeholders)

        ORDER BY
            submission_item_id ASC,
            sort_order ASC,
            id ASC
    ");

    $photoStmt->execute(
        $itemIds
    );

    foreach (
        $photoStmt->fetchAll()
        as $photo
    ) {

        $photosByItem[
            $photo['submission_item_id']
        ][] = $photo;

    }
}


/*
|--------------------------------------------------------------------------
| Get Offer History
|--------------------------------------------------------------------------
*/

$offerStmt = $pdo->prepare("
    SELECT
        o.id,
        o.submission_id,
        o.created_by_admin_id,
        o.sender_type,
        o.offer_amount,
        o.feedback,
        o.status,
        o.sent_at,
        o.responded_at,
        o.created_at,
        o.updated_at,

        a.first_name,
        a.last_name,
        a.username

    FROM offers o

    LEFT JOIN admins a
        ON a.id = o.created_by_admin_id

    WHERE o.submission_id = ?

    ORDER BY o.id DESC
");

$offerStmt->execute([
    $submissionId
]);

$offers = $offerStmt->fetchAll();

$latestOffer =
    $offers[0]
    ?? null;


/*
|--------------------------------------------------------------------------
| Permissions
|--------------------------------------------------------------------------
*/

$canReview =
    hasPermission(
        'review_submissions'
    );

$canSendOffer =
    hasPermission(
        'manage_offers'
    );


$csrfToken =
    generateCsrfToken();


/*
|--------------------------------------------------------------------------
| Negotiation State
|--------------------------------------------------------------------------
*/

$latestSenderType =
    $latestOffer['sender_type']
    ?? null;

$latestIsSeller =
    $latestSenderType === 'seller';

$latestIsAdmin =
    $latestSenderType === 'admin';

$submissionInNegotiation =
    $submission['status'] === 'offer_sent';

$isNegotiationFinished = in_array(
    $submission['status'],
    [
        'accepted',
        'declined',
        'cancelled',
        'completed'
    ],
    true
);

$showOfferForm =
    !$isNegotiationFinished
    &&
    (
        !$latestOffer
        ||
        $latestIsSeller
    );

$canDirectRespond =
    $canSendOffer
    && in_array(
        $submission['status'],
        [
            'new_submission',
            'under_review'
        ],
        true
    );


/*
|--------------------------------------------------------------------------
| Totals
|--------------------------------------------------------------------------
*/

$totalQuantity = 0;
$totalAskingPrice = 0;

foreach ($items as $item) {

    $totalQuantity +=
        (int) $item['quantity'];

    if (
        $item['seller_asking_price']
        !== null
        &&
        $item['seller_asking_price']
        !== ''
    ) {

        $totalAskingPrice +=
            (float)
            $item['seller_asking_price'];

    }
}

?>

<link
    rel="stylesheet"
    href="/VNZ-TECH/assets/css/admin_submission_review.css?v=<?= filemtime(__DIR__ . '/../../assets/css/admin_submission_review.css') ?>"
>


<div class="submission-review-page">


    <!-- =========================================================
         PAGE HEADER
    ========================================================== -->

    <div class="review-page-header">

        <div class="review-header-left">

            <button
                type="button"
                class="back-button"
                onclick="document.getElementById('adminSubmissionModal')?.classList.contains('is-open') ? window.closeAdminSubmissionModal() : window.loadAdminPage('submissions')"
            >

                <span>←</span>

                Back to Submissions

            </button>


            <div class="reference-block">

                <div class="reference-label">
                    SUBMISSION REFERENCE
                </div>

                <h1>
                    <?= e(
                        $submission['reference_number']
                    ) ?>
                </h1>

                <div class="submission-date">

                    Submitted
                    <?= e(
                        date(
                            'M d, Y · h:i A',
                            strtotime(
                                $submission['created_at']
                            )
                        )
                    ) ?>

                </div>

            </div>

        </div>


        <div class="review-header-right">

            <span class="
                status-badge
                <?= e(
                    statusClass(
                        $submission['status']
                    )
                ) ?>
            ">

                <?= e(
                    statusLabel(
                        $submission['status']
                    )
                ) ?>

            </span>

        </div>

    </div>


    <!-- =========================================================
         QUICK SUMMARY
    ========================================================== -->

    <div class="summary-grid">


        <div class="summary-card">

            <div class="summary-icon" aria-hidden="true">
                <svg viewBox="0 0 24 24" fill="none">
                    <path d="m4 7 8-4 8 4v10l-8 4-8-4V7Z" stroke="currentColor" stroke-width="1.7" stroke-linejoin="round"/>
                    <path d="m4 7 8 4 8-4M12 11v10" stroke="currentColor" stroke-width="1.7" stroke-linejoin="round"/>
                </svg>
            </div>

            <div>

                <span>
                    Items
                </span>

                <strong>
                    <?= count($items) ?>
                </strong>

            </div>

        </div>


        <div class="summary-card">

            <div class="summary-icon" aria-hidden="true">
                <svg viewBox="0 0 24 24" fill="none">
                    <path d="M8 5h12M8 12h12M8 19h12" stroke="currentColor" stroke-width="1.7" stroke-linecap="round"/>
                    <path d="M3.5 5h.01M3.5 12h.01M3.5 19h.01" stroke="currentColor" stroke-width="3" stroke-linecap="round"/>
                </svg>
            </div>

            <div>

                <span>
                    Total quantity
                </span>

                <strong>
                    <?= number_format(
                        $totalQuantity
                    ) ?>
                </strong>

            </div>

        </div>


        <div class="summary-card">

            <div class="summary-icon" aria-hidden="true">
                ₱
            </div>

            <div>

                <span>
                    Seller asking
                </span>

                <strong>
                    <?= peso(
                        $totalAskingPrice
                    ) ?>
                </strong>

            </div>

        </div>


        <div class="summary-card">

            <div class="summary-icon" aria-hidden="true">
                <svg viewBox="0 0 24 24" fill="none">
                    <path d="M20 13 13 20l-9-9V4h7l9 9Z" stroke="currentColor" stroke-width="1.7" stroke-linejoin="round"/>
                    <circle cx="8" cy="8" r="1.2" fill="currentColor"/>
                </svg>
            </div>

            <div>

                <span>
                    Selling method
                </span>

                <strong>

                    <?= e(
                        $submission['selling_method']
                            === 'wholesale'
                            ? 'Wholesale'
                            : 'Per Piece'
                    ) ?>

                </strong>

            </div>

        </div>

    </div>


    <!-- =========================================================
         SELLER INFORMATION
    ========================================================== -->

    <section class="review-card">

        <div class="section-header">

            <div>

                <span class="section-kicker">
                    SELLER
                </span>

                <h2>
                    Seller Information
                </h2>

            </div>

        </div>


        <div class="seller-info-grid">


            <div class="info-field">

                <span>
                    FULL NAME
                </span>

                <strong>
                    <?= e(
                        $submission['seller_name']
                    ) ?>
                </strong>

            </div>


            <div class="info-field">

                <span>
                    MOBILE
                </span>

                <strong>
                    <?= e(
                        $submission['seller_mobile']
                    ) ?>
                </strong>

            </div>


            <div class="
                info-field
                info-field-wide
            ">

                <span>
                    ADDRESS / LOCATION
                </span>

                <strong>

                    <?= nl2br(
                        e(
                            $submission[
                                'seller_address'
                            ]
                        )
                    ) ?>

                </strong>

            </div>


            <div class="info-field">

                <span>
                    SELLING METHOD
                </span>

                <strong>

                    <?= e(
                        $submission['selling_method']
                            === 'wholesale'
                            ? 'Wholesale'
                            : 'Per Piece'
                    ) ?>

                </strong>

            </div>

        </div>

    </section>


    <!-- =========================================================
         SELLER NOTES
    ========================================================== -->

    <?php if (
        !empty(
            $submission['seller_notes']
        )
    ): ?>

        <section class="review-card">

            <div class="section-header">

                <div>

                    <span class="section-kicker">
                        SELLER MESSAGE
                    </span>

                    <h2>
                        Seller Notes
                    </h2>

                </div>

            </div>


            <div class="notes-box">

                <?= nl2br(
                    e(
                        $submission[
                            'seller_notes'
                        ]
                    )
                ) ?>

            </div>

        </section>

    <?php endif; ?>


    <!-- =========================================================
         ITEMS
    ========================================================== -->

    <section class="review-card">

        <div class="section-header">

            <div>

                <span class="section-kicker">
                    SUBMISSION CONTENT
                </span>

                <h2>
                    Submitted Items
                </h2>

            </div>


            <span class="item-count">

                <?= count($items) ?>

                item<?= count($items) !== 1
                    ? 's'
                    : '' ?>

            </span>

        </div>


        <?php if (empty($items)): ?>

            <div class="empty-review-state">

                No items were found
                for this submission.

            </div>

        <?php else: ?>

            <div class="items-list">


                <?php foreach (
                    $items
                    as $index => $item
                ): ?>

                    <article class="item-card">


                        <div class="item-card-header">

                            <div class="item-title">

                                <span class="item-number">

                                    <?= str_pad(
                                        $index + 1,
                                        2,
                                        '0',
                                        STR_PAD_LEFT
                                    ) ?>

                                </span>


                                <div>

                                    <span
                                        class="
                                            item-type-label
                                        "
                                    >

                                        <?= e(
                                            $item[
                                                'item_type'
                                            ]
                                        ) ?>

                                    </span>


                                    <h3>

                                        <?php if (
                                            $item[
                                                'item_type'
                                            ]
                                            ===
                                            'Whole Phone'
                                        ): ?>

                                            <?= e(
                                                trim(
                                                    (
                                                        $item[
                                                            'brand'
                                                        ]
                                                        ??
                                                        ''
                                                    )
                                                    . ' '
                                                    .
                                                    (
                                                        $item[
                                                            'model'
                                                        ]
                                                        ??
                                                        ''
                                                    )
                                                )
                                            )
                                            ?: 'Unknown Phone' ?>

                                        <?php else: ?>

                                            Scrap Board

                                        <?php endif; ?>

                                    </h3>

                                </div>

                            </div>


                            <div
                                class="quantity-badge"
                            >

                                QTY
                                <?= number_format(
                                    (int)
                                    $item[
                                        'quantity'
                                    ]
                                ) ?>

                            </div>

                        </div>


                        <div
                            class="
                                item-details-grid
                            "
                        >


                            <?php if (
                                $item['item_type']
                                ===
                                'Whole Phone'
                            ): ?>


                                <div
                                    class="detail-field"
                                >

                                    <span>
                                        BRAND
                                    </span>

                                    <strong>

                                        <?= e(
                                            $item[
                                                'brand'
                                            ]
                                        )
                                        ?: 'Unknown' ?>

                                    </strong>

                                </div>


                                <div
                                    class="detail-field"
                                >

                                    <span>
                                        MODEL
                                    </span>

                                    <strong>

                                        <?= e(
                                            $item[
                                                'model'
                                            ]
                                        )
                                        ?: 'Unknown' ?>

                                    </strong>

                                </div>


                                <div
                                    class="detail-field"
                                >

                                    <span>
                                        STORAGE
                                    </span>

                                    <strong>

                                        <?= e(
                                            $item[
                                                'storage'
                                            ]
                                        )
                                        ?: 'Unknown' ?>

                                    </strong>

                                </div>


                                <div
                                    class="detail-field"
                                >

                                    <span>
                                        ISSUE
                                    </span>

                                    <strong>

                                        <?= e(
                                            $item[
                                                'issue'
                                            ]
                                        )
                                        ?: '—' ?>

                                    </strong>

                                </div>


                                <div
                                    class="detail-field"
                                >

                                    <span>
                                        DAMAGE / CONDITION
                                    </span>

                                    <strong>

                                        <?= e(
                                            damageLabel(
                                                $item[
                                                    'damage_level'
                                                ]
                                            )
                                        ) ?>

                                    </strong>

                                </div>


                            <?php else: ?>


                                <div
                                    class="detail-field"
                                >

                                    <span>
                                        BOARD CODE
                                    </span>

                                    <strong
                                        class="board-code"
                                    >

                                        <?= e(
                                            $item[
                                                'board_code'
                                            ]
                                        )
                                        ?: '—' ?>

                                    </strong>

                                </div>


                                <div
                                    class="detail-field"
                                >

                                    <span>
                                        BRAND
                                    </span>

                                    <strong>

                                        <?= e(
                                            $item[
                                                'brand'
                                            ]
                                        )
                                        ?: 'Unknown' ?>

                                    </strong>

                                </div>


                                <div
                                    class="detail-field"
                                >

                                    <span>
                                        MODEL / BOARD
                                    </span>

                                    <strong>

                                        <?= e(
                                            $item[
                                                'model'
                                            ]
                                        )
                                        ?: 'Unknown' ?>

                                    </strong>

                                </div>


                                <div
                                    class="detail-field"
                                >

                                    <span>
                                        STORAGE
                                    </span>

                                    <strong>

                                        <?= e(
                                            $item[
                                                'storage'
                                            ]
                                        )
                                        ?: 'N/A' ?>

                                    </strong>

                                </div>


                                <div
                                    class="detail-field"
                                >

                                    <span>
                                        CONDITION
                                    </span>

                                    <strong>

                                        <?= e(
                                            damageLabel(
                                                $item[
                                                    'damage_level'
                                                ]
                                            )
                                        ) ?>

                                    </strong>

                                </div>


                            <?php endif; ?>


                            <div
                                class="detail-field"
                            >

                                <span>
                                    QUANTITY
                                </span>

                                <strong>

                                    <?= number_format(
                                        (int)
                                        $item[
                                            'quantity'
                                        ]
                                    ) ?>

                                </strong>

                            </div>


                            <div
                                class="
                                    detail-field
                                    asking-field
                                "
                            >

                                <span>
                                    SELLER ASKING PRICE
                                </span>

                                <strong>

                                    <?= peso(
                                        $item[
                                            'seller_asking_price'
                                        ]
                                    ) ?>

                                </strong>

                            </div>


                        </div>


                        <?php if (
                            !empty(
                                $item[
                                    'issue_details'
                                ]
                            )
                        ): ?>

                            <div
                                class="item-description"
                            >

                                <span>
                                    ISSUE DETAILS
                                </span>

                                <p>

                                    <?= nl2br(
                                        e(
                                            $item[
                                                'issue_details'
                                            ]
                                        )
                                    ) ?>

                                </p>

                            </div>

                        <?php endif; ?>


                        <?php if (
                            !empty(
                                $item[
                                    'additional_information'
                                ]
                            )
                        ): ?>

                            <div
                                class="item-description"
                            >

                                <span>
                                    ADDITIONAL INFORMATION
                                </span>

                                <p>

                                    <?= nl2br(
                                        e(
                                            $item[
                                                'additional_information'
                                            ]
                                        )
                                    ) ?>

                                </p>

                            </div>

                        <?php endif; ?>


                        <?php
                        $itemPhotos =
                            $photosByItem[
                                $item['id']
                            ]
                            ?? [];
                        ?>


                        <div class="photos-section">

                            <div class="photos-header">

                                <span>
                                    SUBMITTED PHOTOS
                                </span>

                                <small>

                                    <?= count(
                                        $itemPhotos
                                    ) ?>

                                    photo<?= count(
                                        $itemPhotos
                                    ) !== 1
                                        ? 's'
                                        : '' ?>

                                </small>

                            </div>


                            <?php if (
                                empty($itemPhotos)
                            ): ?>

                                <div class="no-photos">

                                    No photos uploaded
                                    for this item.

                                </div>

                            <?php else: ?>

                                <div class="photo-grid">


                                    <?php foreach (
                                        $itemPhotos
                                        as $photo
                                    ): ?>

                                        <a
                                            href="<?= e(
                                                $photo[
                                                    'file_path'
                                                ]
                                            ) ?>"
                                            class="photo-card"
                                            data-lightbox-src="<?= e(
                                                $photo[
                                                    'file_path'
                                                ]
                                            ) ?>"
                                            data-lightbox-label="<?= e(
                                                $photo[
                                                    'photo_type'
                                                ]
                                                ?:
                                                'Photo'
                                            ) ?>"
                                        >

                                            <img
                                                src="<?= e(
                                                    $photo[
                                                        'file_path'
                                                    ]
                                                ) ?>"
                                                alt="<?= e(
                                                    $photo[
                                                        'original_name'
                                                    ]
                                                    ?:
                                                    'Submission photo'
                                                ) ?>"
                                                loading="lazy"
                                            >


                                            <div
                                                class="
                                                    photo-overlay
                                                "
                                            >

                                                <span>

                                                    <?= e(
                                                        $photo[
                                                            'photo_type'
                                                        ]
                                                        ?:
                                                        'Photo'
                                                    ) ?>

                                                </span>

                                                <b>
                                                    ⤢
                                                </b>

                                            </div>

                                        </a>

                                    <?php endforeach; ?>


                                </div>

                            <?php endif; ?>


                        </div>


                    </article>

                <?php endforeach; ?>


            </div>

        <?php endif; ?>


    </section>


    <!-- =========================================================
         ADMIN FEEDBACK
    ========================================================== -->

    <?php if (
        !empty(
            $submission[
                'admin_feedback'
            ]
        )
    ): ?>

        <section class="review-card">

            <div class="section-header">

                <div>

                    <span class="section-kicker">
                        ADMIN REVIEW
                    </span>

                    <h2>
                        Current Feedback
                    </h2>

                </div>

            </div>


            <div
                class="
                    notes-box
                    admin-feedback-box
                "
            >

                <?= nl2br(
                    e(
                        $submission[
                            'admin_feedback'
                        ]
                    )
                ) ?>

            </div>

        </section>

    <?php endif; ?>

    <?php if ($canDirectRespond): ?>

    <!-- =========================================================
         DIRECT SUBMISSION ACTIONS
    ========================================================== -->

    <section class="review-card">

        <div class="section-header">

            <div>
                <span class="section-kicker">
                    SELLER ASKING PRICE
                </span>

                <h2>
                    Review Submission
                </h2>
            </div>

        </div>

        <div class="negotiation-amount">
            <?= peso($totalAskingPrice) ?>
        </div>

        <div class="seller-counter-warning">

            <strong>
                Accept the seller's asking price or decline the submission.
            </strong>

            <span>
                Accepting matches the seller's total asking price. A counter offer lets you set VNZ TECH's price.
            </span>

        </div>

        <div class="negotiation-action-buttons">

            <button
                type="button"
                class="negotiation-decline-button"
                data-submission-response="decline"
                data-submission-id="<?= (int) $submission['id'] ?>"
            >
                Decline
            </button>

            <button
                type="button"
                class="negotiation-accept-button"
                data-submission-response="accept"
                data-submission-id="<?= (int) $submission['id'] ?>"
            >
                Accept
                <?= peso($totalAskingPrice) ?>
            </button>

            <?php if ($canSendOffer && $showOfferForm): ?>
                <button
                    type="button"
                    class="negotiation-counter-button"
                    id="openAdminCounter"
                    aria-controls="adminOfferPanel"
                    aria-expanded="false"
                >
                    Counter offer
                </button>
            <?php endif; ?>

        </div>

    </section>

<?php endif; ?>

    <!-- =========================================================
         CURRENT NEGOTIATION
    ========================================================== -->

        <?php if (
            $latestOffer
            &&
            $submissionInNegotiation
        ): ?>

            <section
                class="
                    review-card
                    current-negotiation-card
                    <?= $latestIsSeller
                        ? 'negotiation-seller-active'
                        : 'negotiation-admin-active'
                    ?>
                "
            >


            <div class="negotiation-banner">

                <div>

                    <span class="section-kicker">
                        CURRENT NEGOTIATION
                    </span>


                    <h2>

                        <?= $latestIsSeller
                            ? 'Seller Counter-Offer'
                            : 'Latest VNZ TECH Offer'
                        ?>

                    </h2>

                </div>


                <span
                    class="
                        negotiation-live-badge
                        <?= e(
                            senderClass(
                                $latestSenderType
                            )
                        ) ?>
                    "
                >

                    <?= e(
                        senderLabel(
                            $latestSenderType
                        )
                    ) ?>

                </span>

            </div>


            <div class="negotiation-amount">

                <?= peso(
                    $latestOffer[
                        'offer_amount'
                    ]
                ) ?>

            </div>


            <?php if (
                !empty(
                    $latestOffer[
                        'feedback'
                    ]
                )
            ): ?>

                <div class="negotiation-message">

                    <span>

                        <?= $latestIsSeller
                            ? 'MESSAGE FROM SELLER'
                            : 'MESSAGE TO SELLER'
                        ?>

                    </span>


                    <p>

                        <?= nl2br(
                            e(
                                $latestOffer[
                                    'feedback'
                                ]
                            )
                        ) ?>

                    </p>

                </div>

            <?php endif; ?>


            <div class="negotiation-meta">

                <span>

                    <?= e(
                        senderLabel(
                            $latestSenderType
                        )
                    ) ?>

                </span>


                <span>

                    <?= e(
                        date(
                            'M d, Y · h:i A',
                            strtotime(
                                $latestOffer[
                                    'created_at'
                                ]
                            )
                        )
                    ) ?>

                </span>


                <span>

                    Status:
                    <?= e(
                        statusLabel(
                            $latestOffer[
                                'status'
                            ]
                        )
                    ) ?>

                </span>

            </div>


            <!-- =====================================================
                 SELLER COUNTER ACTIONS
            ====================================================== -->

            <?php if (
                $latestIsSeller
                &&
                $canSendOffer
                &&
                $submissionInNegotiation
            ): ?>

                <div
                    class="
                        seller-counter-warning
                    "
                >

                    <strong>
                        Seller has responded.
                    </strong>

                    <span>
                        Review the counter-offer and
                        choose how VNZ TECH wants to proceed.
                    </span>

                </div>


                <div
                    class="
                        negotiation-action-buttons
                    "
                >

                    <button
                        type="button"
                        class="
                            negotiation-decline-button
                        "
                        data-admin-response="decline"
                        data-offer-id="<?= (int) $latestOffer['id'] ?>"
                        data-offer-amount="<?= htmlspecialchars((string) $latestOffer['offer_amount'], ENT_QUOTES, 'UTF-8') ?>"
                    >
                        DECLINE
                    </button>


                    <button
                        type="button"
                        class="
                            negotiation-accept-button
                        "
                        data-admin-response="accept"
                        data-offer-id="<?= (int) $latestOffer['id'] ?>"
                        data-offer-amount="<?= htmlspecialchars((string) $latestOffer['offer_amount'], ENT_QUOTES, 'UTF-8') ?>"
                    >
                        ACCEPT
                        <?= peso(
                            $latestOffer[
                                'offer_amount'
                            ]
                        ) ?>
                        →
                    </button>


                    <button
                        type="button"
                        class="
                            negotiation-counter-button
                        "
                        id="openAdminCounter"
                        aria-controls="adminOfferPanel"
                        aria-expanded="false"
                    >
                        COUNTER OFFER
                    </button>

                </div>

            <?php elseif (
                $latestIsAdmin
                &&
                $submissionInNegotiation
            ): ?>

                <div
                    class="
                        negotiation-waiting-state
                    "
                >

                    <span
                        class="waiting-dot"
                    ></span>

                    <div>

                        <strong>
                            Waiting for seller response
                        </strong>

                        <p>
                            VNZ TECH has already sent
                            the latest offer. The seller can
                            accept, decline, or counter it.
                        </p>

                    </div>

                </div>

            <?php endif; ?>


        </section>

    <?php endif; ?>


    <!-- =========================================================
         OFFER HISTORY
    ========================================================== -->

    <?php if (
        !empty($offers)
    ): ?>

        <section
            class="review-card"
        >

            <div class="section-header">

                <div>

                    <span class="section-kicker">
                        NEGOTIATION HISTORY
                    </span>

                    <h2>
                        Offer History
                    </h2>

                </div>


                <span class="item-count">

                    <?= count($offers) ?>

                    exchange<?= count(
                        $offers
                    ) !== 1
                        ? 's'
                        : '' ?>

                </span>

            </div>


            <div
                class="offer-history"
            >


                <?php foreach (
                    $offers
                    as $offer
                ): ?>

                    <?php

                    $offerSender =
                        $offer[
                            'sender_type'
                        ]
                        ?? 'admin';

                    $isSellerOffer =
                        $offerSender === 'seller';

                    ?>


                    <div
                        class="
                            offer-history-row
                            <?= $isSellerOffer
                                ? 'history-seller'
                                : 'history-admin'
                            ?>
                        "
                    >


                        <div
                            class="
                                offer-history-top
                            "
                        >

                            <div
                                class="
                                    offer-history-amount
                                "
                            >

                                <?= peso(
                                    $offer[
                                        'offer_amount'
                                    ]
                                ) ?>

                            </div>


                            <span
                                class="
                                    offer-history-sender
                                    <?= e(
                                        senderClass(
                                            $offerSender
                                        )
                                    ) ?>
                                "
                            >

                                <?= e(
                                    senderLabel(
                                        $offerSender
                                    )
                                ) ?>

                            </span>


                            <span
                                class="
                                    offer-history-status
                                "
                            >

                                <?= e(
                                    statusLabel(
                                        $offer[
                                            'status'
                                        ]
                                    )
                                ) ?>

                            </span>

                        </div>


                        <div
                            class="
                                offer-history-meta
                            "
                        >

                            <span>

                                <?php if (
                                    $isSellerOffer
                                ): ?>

                                    Seller

                                <?php else: ?>

                                    <?= e(
                                        trim(
                                            (
                                                $offer[
                                                    'first_name'
                                                ]
                                                ??
                                                ''
                                            )
                                            . ' '
                                            .
                                            (
                                                $offer[
                                                    'last_name'
                                                ]
                                                ??
                                                ''
                                            )
                                        )
                                    )
                                    ?: e(
                                        $offer[
                                            'username'
                                        ]
                                        ?? 'Admin'
                                    ) ?>

                                <?php endif; ?>

                            </span>


                            <span>

                                <?= e(
                                    date(
                                        'M d, Y · h:i A',
                                        strtotime(
                                            $offer[
                                                'created_at'
                                            ]
                                        )
                                    )
                                ) ?>

                            </span>

                        </div>


                        <?php if (
                            !empty(
                                $offer[
                                    'feedback'
                                ]
                            )
                        ): ?>

                            <p>

                                <?= nl2br(
                                    e(
                                        $offer[
                                            'feedback'
                                        ]
                                    )
                                ) ?>

                            </p>

                        <?php endif; ?>


                    </div>

                <?php endforeach; ?>


            </div>

        </section>

    <?php endif; ?>


    <!-- =========================================================
         SEND / COUNTER OFFER
    ========================================================== -->

    <?php if ($canSendOffer): ?>

        <?php if ($showOfferForm): ?>

            <section
                class="
                    review-card
                    offer-panel
                    admin-offer-panel
                "
                id="adminOfferPanel"
                hidden
            >

                <div class="section-header">

                    <div>

                        <span class="section-kicker">

                            <?= $latestIsSeller
                                ? 'VNZ TECH RESPONSE'
                                : 'VNZ TECH OFFER'
                            ?>

                        </span>


                        <h2>

                            <?= $latestIsSeller
                                ? 'Counter the Seller'
                                : 'Send VNZ TECH Offer'
                            ?>

                        </h2>

                    </div>


                    <?php if (
                        $latestIsSeller
                    ): ?>

                        <span
                            class="
                                offer-state
                                seller-response-state
                            "
                        >
                            Seller Counter
                        </span>

                    <?php endif; ?>

                </div>


                <form
                    id="sendOfferForm"
                    method="POST"
                    action="/VNZ-TECH/backend/send_offer.php"
                >

                    <input
                        type="hidden"
                        name="submission_id"
                        value="<?= (int) $submission['id'] ?>"
                    >


                    <input
                        type="hidden"
                        name="csrf_token"
                        value="<?= htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8') ?>"
                    >


                    <div
                        class="
                            offer-form-grid
                        "
                    >


                        <div class="form-field">

                            <label
                                for="offer_amount"
                            >

                                <?= $latestIsSeller ? 'Counter offer amount' : 'Offer amount' ?>

                            </label>


                            <div
                                class="
                                    money-input
                                "
                            >

                                <span>
                                    ₱
                                </span>


                                <input
                                    type="number"
                                    id="offer_amount"
                                    name="offer_amount"
                                    min="0"
                                    step="0.01"
                                    value=""
                                    placeholder="0.00"
                                    required
                                >

                            </div>

                        </div>


                        <div
                            class="
                                form-field
                                form-field-wide
                            "
                        >

                            <label
                                for="offer_feedback"
                            >

                                Message to seller

                            </label>


                            <textarea
                                id="offer_feedback"
                                name="feedback"
                                rows="5"
                                placeholder="<?= $latestIsSeller
                                    ? "Explain VNZ TECH's counter-offer or response..."
                                    : "Add the reason, notes, or instructions related to this offer..."
                                ?>"
                            ></textarea>

                        </div>

                    </div>


                    <div
                        class="
                            offer-warning
                        "
                    >

                        <span class="offer-warning-mark" aria-hidden="true">
                            i
                        </span>


                        <p>

                            Review the photos, item condition, quantity, and asking price before sending.
                            Base the offer on VNZ TECH's verified buying valuation.

                        </p>

                    </div>


                    <div
                        class="
                            offer-actions
                        "
                    >

                        <button
                            type="button"
                            class="secondary-button"
                            data-close-admin-offer
                        >
                            Cancel
                        </button>


                        <button
                            type="submit"
                            class="primary-button"
                        >

                            <?= $latestIsSeller
                                ? 'Send Counter Offer'
                                : 'Send Offer'
                            ?>

                        </button>

                    </div>


                </form>

            </section>

        <?php endif; ?>


    <?php elseif ($canReview): ?>

        <section
            class="
                review-card
                restricted-offer-panel
            "
        >

            <div class="restricted-icon" aria-hidden="true">
                <svg viewBox="0 0 24 24" fill="none">
                    <path d="M7 10V7a5 5 0 0 1 10 0v3m-10 0h10a2 2 0 0 1 2 2v7a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2v-7a2 2 0 0 1 2-2Zm5 4v3" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round"/>
                </svg>
            </div>


            <div>

                <strong>
                    Offer sending is restricted
                </strong>


                <p>

                    Your current role can review
                    submissions but does not have
                    permission to send VNZ TECH offers.

                </p>

            </div>

        </section>

    <?php endif; ?>


    <!-- =========================================================
         DISCLAIMER
    ========================================================== -->

    <div class="review-disclaimer">

        <strong>
            VNZ TECH REVIEW NOTICE
        </strong>


        <span>

            Seller-submitted information and condition
            ratings are subject to inspection and verification.
            Final buying decisions and offers may change
            after actual inspection.

        </span>

    </div>


</div>


<!-- =========================================================
     PHOTO LIGHTBOX
========================================================== -->

<div class="submission-photo-lightbox" id="submissionPhotoLightbox">

    <div class="submission-photo-lightbox-backdrop"></div>

    <div class="submission-photo-lightbox-content">

        <button
            type="button"
            class="submission-photo-lightbox-close"
            id="submissionPhotoLightboxClose"
        >
            ×
        </button>

        <img
            src=""
            alt=""
            id="submissionPhotoLightboxImg"
        >

        <div
            class="submission-photo-lightbox-caption"
            id="submissionPhotoLightboxCaption"
        ></div>

    </div>

</div>

<div class="transaction-chat-approval-modal" data-approval-modal aria-hidden="true">
    <div class="transaction-chat-approval-backdrop" data-approval-close></div>
    <section
        class="transaction-chat-approval-panel"
        role="dialog"
        aria-modal="true"
        aria-labelledby="transactionApprovalTitle"
    >
        <span class="transaction-chat-approval-kicker">VNZ TECH · APPROVED</span>
        <h2 id="transactionApprovalTitle">Request Approved</h2>
        <p>The seller's request has been approved successfully.</p>
        <dl class="transaction-chat-approval-details">
            <div>
                <dt>Submission</dt>
                <dd data-approval-submission></dd>
            </div>
            <div>
                <dt>Transaction</dt>
                <dd data-approval-transaction></dd>
            </div>
            <div>
                <dt>Approved amount</dt>
                <dd data-approval-amount></dd>
            </div>
        </dl>
        <div class="transaction-chat-approval-actions">
            <button type="button" class="transaction-chat-approval-primary" data-approval-chat>
                Chat with Seller
            </button>
            <button type="button" class="transaction-chat-approval-close" data-approval-close>
                Close
            </button>
        </div>
    </section>
</div>


<script>

(function () {

    const reviewPage =
        document.querySelector(
            '.submission-review-page'
        );

    if (!reviewPage) {
        return;
    }

    function refreshReviewAfterAction() {
        if (
            reviewPage.closest('#adminSubmissionModalBody')
            && typeof window.refreshAdminSubmissionModal === 'function'
        ) {
            window.refreshAdminSubmissionModal(
                <?= (int) $submissionId ?>
            );
            return;
        }

        if (typeof window.loadAdminPage === 'function') {
            window.loadAdminPage(
                'submission_review',
                {
                    id: <?= (int) $submissionId ?>
                }
            );
        }
    }

    const approvalModal =
        reviewPage.parentElement?.querySelector('[data-approval-modal]');
    const approvalChatButton =
        approvalModal?.querySelector('[data-approval-chat]');
    let approvedTransactionId = 0;
    let submissionResponsePending = false;
    let adminCounterResponsePending = false;
    let sendOfferPending = false;

    function closeApprovalModal(refresh = true) {
        if (!approvalModal) {
            return;
        }

        approvalModal.classList.remove('is-open');
        approvalModal.setAttribute('aria-hidden', 'true');

        if (refresh) {
            refreshReviewAfterAction();
        }
    }

    function showApprovalModal(data) {
        if (!approvalModal) {
            throw new Error('The approval confirmation could not be displayed.');
        }

        approvedTransactionId = Number(data.transaction_id) || 0;
        if (!approvedTransactionId) {
            throw new Error('The approved transaction could not be identified.');
        }

        approvalModal.querySelector('[data-approval-submission]').textContent =
            <?= json_encode((string) $submission['reference_number']) ?>;
        approvalModal.querySelector('[data-approval-transaction]').textContent =
            String(data.transaction_reference || 'Transaction created');

        const amount = Number(data.offer_amount ?? data.final_amount);
        approvalModal.querySelector('[data-approval-amount]').textContent =
            Number.isFinite(amount)
                ? new Intl.NumberFormat('en-PH', {
                    style: 'currency',
                    currency: 'PHP'
                }).format(amount)
                : '—';

        approvalModal.classList.add('is-open');
        approvalModal.setAttribute('aria-hidden', 'false');
        approvalChatButton?.focus({preventScroll: true});
    }

    approvalModal?.querySelectorAll('[data-approval-close]').forEach(button => {
        button.addEventListener('click', () => closeApprovalModal());
    });

    approvalChatButton?.addEventListener('click', () => {
        if (!approvedTransactionId) {
            return;
        }

        closeApprovalModal(false);
        if (
            reviewPage.closest('#adminSubmissionModalBody')
            && typeof window.closeAdminSubmissionModal === 'function'
        ) {
            window.closeAdminSubmissionModal();
        }

        window.openTransactionChat?.(approvedTransactionId);
    });

    approvalModal?.addEventListener('keydown', event => {
        if (event.key === 'Escape' && approvalModal.classList.contains('is-open')) {
            closeApprovalModal();
        }
    });

    /*
|--------------------------------------------------------------------------
| Direct Submission Accept / Decline
|--------------------------------------------------------------------------
*/

reviewPage
    .querySelectorAll('[data-submission-response]')
    .forEach(function (button) {

        button.addEventListener(
            'click',
            async function () {
                if (submissionResponsePending) {
                    return;
                }
                submissionResponsePending = true;

                const responseType =
                    this.dataset.submissionResponse;

                const submissionId =
                    this.dataset.submissionId;
                const askingPrice = new Intl.NumberFormat('en-PH', {
                    style: 'currency',
                    currency: 'PHP'
                }).format(<?= json_encode((float) $totalAskingPrice) ?>);
                const confirmed = await window.VNZDialog.confirm({
                    title: responseType === 'accept'
                        ? 'Accept Submission?'
                        : 'Decline Submission?',
                    message: responseType === 'accept'
                        ? `Are you sure you want to accept this submission at the seller's asking price? Submission <?= json_encode((string) $submission['reference_number'], JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?> has a total asking price of ${askingPrice}.`
                        : 'Are you sure you want to decline submission <?= json_encode((string) $submission['reference_number'], JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>?',
                    confirmLabel: responseType === 'accept'
                        ? 'Accept Submission'
                        : 'Decline Submission',
                    danger: responseType !== 'accept'
                });

                if (!confirmed) {
                    submissionResponsePending = false;
                    return;
                }

                const buttons =
                    reviewPage.querySelectorAll(
                        '[data-submission-response]'
                    );

                buttons.forEach(function (item) {
                    item.disabled = true;
                });

                const body =
                    new FormData();

                body.append(
                    'submission_id',
                    submissionId
                );

                body.append(
                    'response',
                    responseType
                );

                body.append(
                    'csrf_token',
                    <?= json_encode($csrfToken) ?>
                );

                let responseSaved = false;
                try {

                    const response =
                        await fetch(
                            '/VNZ-TECH/backend/admin_respond_submission.php',
                            {
                                method: 'POST',
                                body
                            }
                        );

                    const data =
                        await response.json();

                    if (
                        !response.ok
                        ||
                        !data.success
                    ) {
                        throw new Error(
                            data.message
                            ||
                            'Unable to update submission.'
                        );
                    }

                    responseSaved = true;
                    if (responseType === 'accept') {
                        showApprovalModal({
                            transaction_id: data.transaction_id,
                            transaction_reference: data.transaction_reference,
                            final_amount: data.final_amount
                        });
                    } else {
                        await window.VNZDialog.alert({
                            title: 'Submission Declined',
                            message: data.message,
                            confirmLabel: 'Close'
                        });
                        refreshReviewAfterAction();
                    }

                } catch (error) {

                    await window.VNZDialog.alert({
                        title: responseSaved
                            ? 'Submission Updated'
                            : 'Unable to Update Submission',
                        message: responseSaved
                            ? `The submission was updated, but the result could not be displayed: ${error.message}`
                            : error.message,
                        confirmLabel: 'Close'
                    });

                    if (!responseSaved) {
                        buttons.forEach(function (item) {
                            item.disabled = false;
                        });
                        submissionResponsePending = false;
                    }

                }

            }
        );

    });

    /*
    |--------------------------------------------------------------------------
    | Admin Accept / Decline Seller Counter
    |--------------------------------------------------------------------------
    */

    reviewPage
        .querySelectorAll(
            '[data-admin-response]'
        )
        .forEach(
            function (button) {

                button.addEventListener(
                    'click',
                    async function () {
                        if (adminCounterResponsePending) {
                            return;
                        }
                        adminCounterResponsePending = true;

                        const responseType =
                            this.dataset.adminResponse;

                        const offerId =
                            this.dataset.offerId;
                        const offerAmount = Number(this.dataset.offerAmount);
                        const formattedAmount = Number.isFinite(offerAmount)
                            ? new Intl.NumberFormat('en-PH', {
                                style: 'currency',
                                currency: 'PHP'
                            }).format(offerAmount)
                            : 'the seller counter-offer';
                        const confirmed = await window.VNZDialog.confirm({
                            title: responseType === 'accept'
                                ? 'Accept Counter-Offer?'
                                : 'Decline Counter-Offer?',
                            message: responseType === 'accept'
                                ? `Accept the seller's counter-offer of ${formattedAmount} for submission <?= json_encode((string) $submission['reference_number'], JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>?`
                                : `Decline the seller's counter-offer of ${formattedAmount} for submission <?= json_encode((string) $submission['reference_number'], JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>?`,
                            confirmLabel: responseType === 'accept'
                                ? 'Accept Counter-Offer'
                                : 'Decline Counter-Offer',
                            danger: responseType !== 'accept'
                        });


                        if (!confirmed) {
                            adminCounterResponsePending = false;
                            return;
                        }


                        const buttons =
                            reviewPage.querySelectorAll(
                                '[data-admin-response], #openAdminCounter'
                            );


                        buttons.forEach(
                            function (item) {

                                item.disabled =
                                    true;

                            }
                        );


                        const body =
                            new FormData();


                        body.append(
                            'offer_id',
                            offerId
                        );


                        body.append(
                            'response',
                            responseType
                        );


                        body.append(
                            'csrf_token',
                            <?= json_encode($csrfToken) ?>
                        );

                        let responseSaved = false;
                        try {

                            const response =
                                await fetch(
                                    '/VNZ-TECH/backend/admin_respond_offer.php',
                                    {
                                        method: 'POST',
                                        body
                                    }
                                );


                            const data =
                                await response.json();


                            if (
                                !response.ok
                                ||
                                !data.success
                            ) {

                                throw new Error(
                                    data.message
                                    ||
                                    'Unable to update seller counter-offer.'
                                );

                            }

                            responseSaved = true;

                            if (responseType === 'accept') {
                                showApprovalModal({
                                    transaction_id: data.transaction_id,
                                    transaction_reference: data.transaction_reference,
                                    offer_amount: data.offer_amount
                                });
                            } else {
                                await window.VNZDialog.alert({
                                    title: 'Counter-Offer Declined',
                                    message: data.message,
                                    confirmLabel: 'Close'
                                });
                                refreshReviewAfterAction();
                            }

                        } catch (error) {

                            await window.VNZDialog.alert({
                                title: responseSaved
                                    ? 'Counter-Offer Updated'
                                    : 'Unable to Update Counter-Offer',
                                message: responseSaved
                                    ? `The counter-offer was updated, but the result could not be displayed: ${error.message}`
                                    : error.message,
                                confirmLabel: 'Close'
                            });

                            if (!responseSaved) {
                                buttons.forEach(
                                    function (item) {
                                        item.disabled = false;
                                    }
                                );
                                adminCounterResponsePending = false;
                            }

                        }

                    }
                );

            }
        );


    /*
    |--------------------------------------------------------------------------
    | Open Admin Counter
    |--------------------------------------------------------------------------
    */

    const openAdminCounter =
        reviewPage.querySelector(
            '#openAdminCounter'
        );


    const adminOfferPanel =
        reviewPage.querySelector(
            '#adminOfferPanel'
        );


    if (
        openAdminCounter
        &&
        adminOfferPanel
    ) {

        openAdminCounter.addEventListener(
            'click',
            function () {
                adminOfferPanel.hidden = false;
                adminOfferPanel.classList.remove('offer-panel-revealed');
                void adminOfferPanel.offsetWidth;
                adminOfferPanel.classList.add('offer-panel-revealed');
                openAdminCounter.setAttribute('aria-expanded', 'true');

                adminOfferPanel.scrollIntoView({
                    behavior: 'smooth',
                    block: 'center'
                });


                const amountInput =
                    adminOfferPanel.querySelector(
                        '#offer_amount'
                    );


                if (amountInput) {

                    setTimeout(
                        function () {

                            amountInput.focus();

                        },
                        300
                    );

                }

            }
        );

        const closeAdminOffer =
            adminOfferPanel.querySelector('[data-close-admin-offer]');

        if (closeAdminOffer) {
            closeAdminOffer.addEventListener('click', function () {
                adminOfferPanel.hidden = true;
                adminOfferPanel.classList.remove('offer-panel-revealed');
                openAdminCounter.setAttribute('aria-expanded', 'false');
                openAdminCounter.focus();
            });
        }

    }


    /*
    |--------------------------------------------------------------------------
    | Send Offer / Counter Offer
    |--------------------------------------------------------------------------
    */

    const sendOfferForm =
        reviewPage.querySelector(
            '#sendOfferForm'
        );


    if (sendOfferForm) {
        const sendingCounterOffer = <?= $latestIsSeller ? 'true' : 'false' ?>;

        sendOfferForm.addEventListener(
            'submit',
            async function (event) {

                event.preventDefault();
                if (sendOfferPending) {
                    return;
                }


                const submitButton =
                    sendOfferForm.querySelector(
                        'button[type="submit"]'
                    );


                const amountInput =
                    sendOfferForm.querySelector(
                        '#offer_amount'
                    );


                const amount =
                    amountInput
                        ? amountInput.value.trim()
                        : '';


                if (
                    amount === ''
                    ||
                    !Number.isFinite(
                        Number(amount)
                    )
                    ||
                    Number(amount) <= 0
                ) {

                    await window.VNZDialog.alert({
                        title: 'Invalid Offer Amount',
                        message: 'Please enter a valid offer amount.',
                        confirmLabel: 'Close'
                    });


                    if (amountInput) {
                        amountInput.focus();
                    }

                    return;
                }


                sendOfferPending = true;
                const formattedAmount = new Intl.NumberFormat('en-PH', {
                    style: 'currency',
                    currency: 'PHP'
                }).format(Number(amount));
                const confirmed = await window.VNZDialog.confirm({
                    title: sendingCounterOffer ? 'Send Counter-Offer?' : 'Send Offer?',
                    message: `Send VNZ TECH ${sendingCounterOffer ? 'counter-offer' : 'offer'} of ${formattedAmount} for submission <?= json_encode((string) $submission['reference_number'], JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>?`,
                    confirmLabel: sendingCounterOffer ? 'Send Counter-Offer' : 'Send Offer'
                });


                if (!confirmed) {
                    sendOfferPending = false;
                    return;
                }


                if (submitButton) {

                    submitButton.disabled =
                        true;

                    submitButton.innerHTML =
                        'Sending...';

                }


  try {

    const response =
        await fetch(
            sendOfferForm.action,
            {
                method: 'POST',
                body:
                    new FormData(
                        sendOfferForm
                    )
            }
        );


    const data =
        await response.json();


                    if (
                        !response.ok
                        ||
                        !data.success
                    ) {

                        throw new Error(
                            data.message
                            ||
                            'Unable to send offer.'
                        );

                    }

                    refreshReviewAfterAction();

                } catch (error) {

                    await window.VNZDialog.alert({
                        title: 'Unable to Send Offer',
                        message: error.message,
                        confirmLabel: 'Close'
                    });

                    sendOfferPending = false;

                    if (submitButton) {

                        submitButton.disabled =
                            false;

                        submitButton.innerHTML =
                            '<span>↗</span> ' +
                            (
                                <?= $latestIsSeller
                                    ? 'true'
                                    : 'false'
                                ?>
                                    ? 'Send Counter Offer'
                                    : 'Send Offer'
                            );

                    }

                }

            }
        );

    }


    /*
    |--------------------------------------------------------------------------
    | Photo Lightbox
    |--------------------------------------------------------------------------
    */

    const photoLightbox =
        document.getElementById(
            'submissionPhotoLightbox'
        );

    const photoLightboxImg =
        document.getElementById(
            'submissionPhotoLightboxImg'
        );

    const photoLightboxCaption =
        document.getElementById(
            'submissionPhotoLightboxCaption'
        );

    const photoLightboxClose =
        document.getElementById(
            'submissionPhotoLightboxClose'
        );


    function openPhotoLightbox(src, label) {

        if (!photoLightbox) {
            return;
        }

        photoLightboxImg.src = src;
        photoLightboxImg.alt = label || 'Submission photo';
        photoLightboxCaption.textContent = label || '';

        photoLightbox.classList.add('is-open');

        document.body.style.overflow = 'hidden';

    }


    function closePhotoLightbox() {

        if (!photoLightbox) {
            return;
        }

        photoLightbox.classList.remove('is-open');

        document.body.style.overflow = '';

        photoLightboxImg.src = '';

    }


    reviewPage.addEventListener(
        'click',
        function (event) {

            const photoCard =
                event.target.closest(
                    '.photo-card'
                );

            if (!photoCard) {
                return;
            }

            event.preventDefault();

            openPhotoLightbox(
                photoCard.dataset.lightboxSrc,
                photoCard.dataset.lightboxLabel
            );

        }
    );


    if (photoLightboxClose) {

        photoLightboxClose.addEventListener(
            'click',
            closePhotoLightbox
        );

    }


    if (photoLightbox) {

        const backdrop =
            photoLightbox.querySelector(
                '.submission-photo-lightbox-backdrop'
            );

        if (backdrop) {

            backdrop.addEventListener(
                'click',
                closePhotoLightbox
            );

        }

    }


    document.addEventListener(
        'keydown',
        function (event) {

            if (
                event.key === 'Escape'
                &&
                photoLightbox
                &&
                photoLightbox.classList.contains(
                    'is-open'
                )
            ) {

                closePhotoLightbox();

            }

        }
    );

})();

</script>