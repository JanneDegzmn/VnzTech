<?php

require_once __DIR__ . '/../../backend/database.php';
require_once __DIR__ . '/../../backend/session.php';
require_once __DIR__ . '/../../backend/auth.php';

requireAdminLogin();

if (!hasPermission('view_submissions')) {
    http_response_code(403);
    ?>

    <link rel="stylesheet" href="../assets/css/transaction_review.css">

    <div class="transaction-review-error">
        <div class="transaction-review-error-icon">!</div>
        <div>
            <h3>Access Denied</h3>
            <p>You do not have permission to view transaction details.</p>
        </div>
    </div>
    <?php
    exit;
}

$transactionId = filter_input(
    INPUT_GET,
    'id',
    FILTER_VALIDATE_INT
);

if (!$transactionId || $transactionId <= 0) {
    http_response_code(400);
    ?>
    <div class="transaction-review-error">
        <div class="transaction-review-error-icon">!</div>
        <div>
            <h3>Invalid Transaction</h3>
            <p>The transaction ID provided is invalid.</p>
        </div>
    </div>
    <?php
    exit;
}


/*
|--------------------------------------------------------------------------
| Get Transaction
|--------------------------------------------------------------------------
*/

$sql = "
    SELECT
        t.id,
        t.transaction_reference,
        t.submission_id,
        t.offer_id,
        t.final_amount,
        t.payment_method,
        t.payment_status,
        t.transaction_status,
        t.notes,
        t.completed_at,
        t.created_at,
        t.updated_at,

        s.reference_number AS submission_reference,
        s.seller_name,
        s.seller_mobile,
        s.seller_address,
        s.selling_method,
        s.status AS submission_status,
        s.seller_notes,
        s.admin_feedback

    FROM transactions t

    LEFT JOIN submissions s
        ON s.id = t.submission_id

    WHERE t.id = ?

    LIMIT 1
";

try {

    $stmt = $pdo->prepare($sql);
    $stmt->execute([$transactionId]);

    $transaction = $stmt->fetch();

} catch (PDOException $e) {

    http_response_code(500);
    ?>
    <div class="transaction-review-error">
        <div class="transaction-review-error-icon">!</div>
        <div>
            <h3>Unable to Load Transaction</h3>
            <p>
                A database error occurred while loading this transaction.
            </p>
        </div>
    </div>
    <?php
    exit;
}


/*
|--------------------------------------------------------------------------
| Transaction Not Found
|--------------------------------------------------------------------------
*/

if (!$transaction) {

    http_response_code(404);
    ?>
    <div class="transaction-review-error">
        <div class="transaction-review-error-icon">!</div>
        <div>
            <h3>Transaction Not Found</h3>
            <p>
                The transaction you are trying to view does not exist.
            </p>
        </div>
    </div>
    <?php
    exit;
}


/*
|--------------------------------------------------------------------------
| Get Submitted Items
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
    (int) $transaction['submission_id']
]);

$submissionItems = $itemStmt->fetchAll();


/*
|--------------------------------------------------------------------------
| Get Item Photos
|--------------------------------------------------------------------------
*/

$photosByItem = [];

if ($submissionItems) {

    $itemIds = array_map(
        static function ($item) {
            return (int) $item['id'];
        },
        $submissionItems
    );

    $placeholders = implode(
        ', ',
        array_fill(0, count($itemIds), '?')
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
        ORDER BY submission_item_id ASC, sort_order ASC, id ASC
    ");

    $photoStmt->execute($itemIds);

    foreach ($photoStmt->fetchAll() as $photo) {

        $itemId = (int) $photo['submission_item_id'];

        if (!isset($photosByItem[$itemId])) {
            $photosByItem[$itemId] = [];
        }

        $photosByItem[$itemId][] = $photo;
    }
}


/*
|--------------------------------------------------------------------------
| Helpers
|--------------------------------------------------------------------------
*/

function reviewEscape($value): string
{
    return htmlspecialchars(
        (string) ($value ?? ''),
        ENT_QUOTES,
        'UTF-8'
    );
}


function reviewPeso($amount): string
{
    return '₱' . number_format(
        (float) ($amount ?? 0),
        2
    );
}


function reviewStatusLabel($status): string
{
    if (!$status) {
        return 'Unknown';
    }

    return ucwords(
        str_replace(
            '_',
            ' ',
            (string) $status
        )
    );
}


function reviewStatusClass($status): string
{
    switch ((string) $status) {

        case 'completed':
        case 'paid':
        case 'successful':
        case 'accepted':
            return 'review-status-success';

        case 'pending':
        case 'processing':
            return 'review-status-warning';

        case 'cancelled':
        case 'failed':
        case 'rejected':
            return 'review-status-danger';

        default:
            return 'review-status-neutral';
    }
}


function reviewDate($date): string
{
    if (!$date) {
        return '—';
    }

    $timestamp = strtotime($date);

    if (!$timestamp) {
        return reviewEscape($date);
    }

    return date('M d, Y', $timestamp)
        . '<span class="review-date-time">'
        . date('h:i A', $timestamp)
        . '</span>';
}


function reviewItemTypeLabel($type): string
{
    $labels = [
        'whole_phone' => 'Whole Phone',
        'scrap_board' => 'Scrap Board'
    ];

    return $labels[$type] ?? ucwords(
        str_replace(
            '_',
            ' ',
            (string) $type
        )
    );
}


function reviewDamageLabel($level): string
{
    $labels = [
        1 => 'Minor',
        2 => 'Moderate',
        3 => 'Major',
        4 => 'Dead / Critical',
        5 => 'For Scrap'
    ];

    $level = (int) $level;

    return $labels[$level] ?? '—';
}

?>

<div class="transaction-review-content">


    <!-- =========================================================
         TRANSACTION HEADER
    ========================================================== -->

    <div class="transaction-review-top">

        <div>

            <div class="transaction-review-kicker">
                TRANSACTION
            </div>

            <h3 class="transaction-review-reference">
                <?= reviewEscape(
                    $transaction['transaction_reference'] ?? '—'
                ) ?>
            </h3>

            <p class="transaction-review-subtext">
                Transaction ID #<?= (int) $transaction['id'] ?>
            </p>

        </div>


        <div class="transaction-review-status-wrap">

            <span
                class="transaction-review-status <?= reviewStatusClass(
                    $transaction['transaction_status'] ?? ''
                ) ?>"
            >
                <?= reviewEscape(
                    reviewStatusLabel(
                        $transaction['transaction_status'] ?? ''
                    )
                ) ?>
            </span>

        </div>

    </div>



    <!-- =========================================================
         TRANSACTION INFORMATION
    ========================================================== -->

    <div class="transaction-review-section">

        <div class="transaction-review-section-title">
            Transaction Information
        </div>

        <div class="transaction-review-grid">

            <div class="transaction-review-item">

                <span class="transaction-review-label">
                    Transaction Reference
                </span>

                <strong>
                    <?= reviewEscape(
                        $transaction['transaction_reference'] ?? '—'
                    ) ?>
                </strong>

            </div>


            <div class="transaction-review-item">

                <span class="transaction-review-label">
                    Transaction ID
                </span>

                <strong>
                    #<?= (int) $transaction['id'] ?>
                </strong>

            </div>


            <div class="transaction-review-item">

                <span class="transaction-review-label">
                    Created Date
                </span>

                <strong>
                    <?= reviewDate(
                        $transaction['created_at'] ?? null
                    ) ?>
                </strong>

            </div>


            <div class="transaction-review-item">

                <span class="transaction-review-label">
                    Updated Date
                </span>

                <strong>
                    <?= reviewDate(
                        $transaction['updated_at'] ?? null
                    ) ?>
                </strong>

            </div>


            <div class="transaction-review-item">

                <span class="transaction-review-label">
                    Completed Date
                </span>

                <strong>
                    <?= reviewDate(
                        $transaction['completed_at'] ?? null
                    ) ?>
                </strong>

            </div>

        </div>

    </div>



    <!-- =========================================================
         SELLER INFORMATION
    ========================================================== -->

    <div class="transaction-review-section">

        <div class="transaction-review-section-title">
            Seller Information
        </div>

        <div class="transaction-review-grid">

            <div class="transaction-review-item">

                <span class="transaction-review-label">
                    Seller Name
                </span>

                <strong>
                    <?= reviewEscape(
                        $transaction['seller_name'] ?? '—'
                    ) ?>
                </strong>

            </div>


            <div class="transaction-review-item">

                <span class="transaction-review-label">
                    Mobile Number
                </span>

                <strong>
                    <?= reviewEscape(
                        $transaction['seller_mobile'] ?? '—'
                    ) ?>
                </strong>

            </div>


            <div class="transaction-review-item transaction-review-item-wide">

                <span class="transaction-review-label">
                    Address
                </span>

                <strong>
                    <?= reviewEscape(
                        $transaction['seller_address'] ?? '—'
                    ) ?>
                </strong>

            </div>

        </div>

    </div>



    <!-- =========================================================
         SUBMISSION INFORMATION
    ========================================================== -->

    <div class="transaction-review-section">

        <div class="transaction-review-section-title">
            Submission Information
        </div>

        <div class="transaction-review-grid">

            <div class="transaction-review-item">

                <span class="transaction-review-label">
                    Submission Reference
                </span>

                <strong>
                    <?= reviewEscape(
                        $transaction['submission_reference'] ?? '—'
                    ) ?>
                </strong>

            </div>


            <div class="transaction-review-item">

                <span class="transaction-review-label">
                    Submission ID
                </span>

                <strong>
                    #<?= (int) (
                        $transaction['submission_id'] ?? 0
                    ) ?>
                </strong>

            </div>


            <div class="transaction-review-item">

                <span class="transaction-review-label">
                    Selling Method
                </span>

                <strong>
                    <?= reviewEscape(
                        $transaction['selling_method'] ?? '—'
                    ) ?>
                </strong>

            </div>


            <div class="transaction-review-item">

                <span class="transaction-review-label">
                    Submission Status
                </span>

                <span
                    class="transaction-review-status <?= reviewStatusClass(
                        $transaction['submission_status'] ?? ''
                    ) ?>"
                >
                    <?= reviewEscape(
                        reviewStatusLabel(
                            $transaction['submission_status'] ?? ''
                        )
                    ) ?>
                </span>

            </div>

        </div>

    </div>



    <!-- =========================================================
         SUBMITTED ITEMS
    ========================================================== -->

    <div class="transaction-review-section">

        <div class="transaction-review-section-title">
            Submitted Items
        </div>

        <?php if (!$submissionItems): ?>

            <div class="transaction-review-notes">
                <span class="transaction-review-empty">
                    No item details available.
                </span>
            </div>

        <?php else: ?>

            <div class="transaction-review-items">

                <?php foreach ($submissionItems as $index => $item): ?>

                    <?php
                    $reviewItemId = (int) $item['id'];
                    $reviewItemPhotos = $photosByItem[$reviewItemId] ?? [];
                    ?>

                    <div class="transaction-review-item-card">

                        <div class="transaction-review-item-card-top">

                            <div>
                                <span class="transaction-review-label">
                                    ITEM <?= $index + 1 ?> · <?= reviewEscape(
                                        reviewItemTypeLabel($item['item_type'])
                                    ) ?>
                                </span>
                            </div>

                            <span class="transaction-review-item-qty">
                                QTY <?= (int) $item['quantity'] ?>
                            </span>

                        </div>


                        <div class="transaction-review-grid">

                            <div class="transaction-review-item">
                                <span class="transaction-review-label">Brand</span>
                                <strong>
                                    <?= $item['brand'] !== null && $item['brand'] !== ''
                                        ? reviewEscape($item['brand'])
                                        : '—'
                                    ?>
                                </strong>
                            </div>

                            <div class="transaction-review-item">
                                <span class="transaction-review-label">Model</span>
                                <strong>
                                    <?= $item['model'] !== null && $item['model'] !== ''
                                        ? reviewEscape($item['model'])
                                        : '—'
                                    ?>
                                </strong>
                            </div>

                            <div class="transaction-review-item">
                                <span class="transaction-review-label">Storage</span>
                                <strong>
                                    <?= $item['storage'] !== null && $item['storage'] !== ''
                                        ? reviewEscape($item['storage'])
                                        : '—'
                                    ?>
                                </strong>
                            </div>

                            <div class="transaction-review-item">
                                <span class="transaction-review-label">Issue</span>
                                <strong>
                                    <?= $item['issue'] !== null && $item['issue'] !== ''
                                        ? reviewEscape($item['issue'])
                                        : '—'
                                    ?>
                                </strong>
                            </div>

                            <div class="transaction-review-item">
                                <span class="transaction-review-label">Damage</span>
                                <strong>
                                    <?= reviewEscape(
                                        reviewDamageLabel($item['damage_level'])
                                    ) ?>
                                </strong>
                            </div>

                            <div class="transaction-review-item">
                                <span class="transaction-review-label">Asking Price</span>
                                <strong>
                                    <?= reviewPeso($item['seller_asking_price']) ?>
                                </strong>
                            </div>

                            <?php if (!empty($item['board_code'])): ?>
                                <div class="transaction-review-item">
                                    <span class="transaction-review-label">Board Code</span>
                                    <strong>
                                        <?= reviewEscape($item['board_code']) ?>
                                    </strong>
                                </div>
                            <?php endif; ?>

                        </div>


                        <?php if (!empty($item['issue_details'])): ?>
                            <div class="transaction-review-notes">
                                <span class="transaction-review-label">Issue Details</span>
                                <?= nl2br(reviewEscape($item['issue_details'])) ?>
                            </div>
                        <?php endif; ?>


                        <?php if (!empty($item['additional_information'])): ?>
                            <div class="transaction-review-notes">
                                <span class="transaction-review-label">Additional Information</span>
                                <?= nl2br(reviewEscape($item['additional_information'])) ?>
                            </div>
                        <?php endif; ?>


                        <?php if ($reviewItemPhotos): ?>

                            <div class="transaction-review-photo-section">

                                <span class="transaction-review-label">
                                    Photos
                                </span>

                                <div class="transaction-review-photo-grid">

                                    <?php foreach ($reviewItemPhotos as $photo): ?>

                                        <?php
                                        $photoPath = (string) $photo['file_path'];

                                        if ($photoPath !== '' && $photoPath[0] !== '/') {
                                            $photoPath = '/' . $photoPath;
                                        }
                                        ?>

                                        <a
                                            href="<?= reviewEscape($photoPath) ?>"
                                            target="_blank"
                                            rel="noopener"
                                            class="transaction-review-photo-link"
                                        >
                                            <img
                                                src="<?= reviewEscape($photoPath) ?>"
                                                alt="<?= reviewEscape(
                                                    $photo['original_name'] ?: 'Submission photo'
                                                ) ?>"
                                                loading="lazy"
                                            >
                                        </a>

                                    <?php endforeach; ?>

                                </div>

                            </div>

                        <?php endif; ?>

                    </div>

                <?php endforeach; ?>

            </div>

        <?php endif; ?>

    </div>



    <!-- =========================================================
         PAYMENT INFORMATION
    ========================================================== -->

    <div class="transaction-review-section">

        <div class="transaction-review-section-title">
            Payment Information
        </div>

        <div class="transaction-review-payment-card">

            <div class="transaction-review-amount">

                <span>
                    Final Amount
                </span>

                <strong>
                    <?= reviewPeso(
                        $transaction['final_amount'] ?? 0
                    ) ?>
                </strong>

            </div>


            <div class="transaction-review-grid transaction-review-payment-grid">

                <div class="transaction-review-item">

                    <span class="transaction-review-label">
                        Payment Method
                    </span>

                    <strong>
                        <?= reviewEscape(
                            ucwords(
                                str_replace(
                                    '_',
                                    ' ',
                                    $transaction['payment_method'] ?? '—'
                                )
                            )
                        ) ?>
                    </strong>

                </div>


                <div class="transaction-review-item">

                    <span class="transaction-review-label">
                        Payment Status
                    </span>

                    <span
                        class="transaction-review-status <?= reviewStatusClass(
                            $transaction['payment_status'] ?? ''
                        ) ?>"
                    >
                        <?= reviewEscape(
                            reviewStatusLabel(
                                $transaction['payment_status'] ?? ''
                            )
                        ) ?>
                    </span>

                </div>

            </div>

        </div>

    </div>



    <!-- =========================================================
         OFFER INFORMATION
    ========================================================== -->

    <div class="transaction-review-section">

        <div class="transaction-review-section-title">
            Offer Information
        </div>

        <div class="transaction-review-grid">

            <div class="transaction-review-item">

                <span class="transaction-review-label">
                    Offer ID
                </span>

                <strong>
                    <?php if (!empty($transaction['offer_id'])): ?>
                        #<?= (int) $transaction['offer_id'] ?>
                    <?php else: ?>
                        —
                    <?php endif; ?>
                </strong>

            </div>


            <div class="transaction-review-item">

                <span class="transaction-review-label">
                    Final Accepted Amount
                </span>

                <strong>
                    <?= reviewPeso(
                        $transaction['final_amount'] ?? 0
                    ) ?>
                </strong>

            </div>

        </div>

    </div>



    <!-- =========================================================
         NOTES
    ========================================================== -->

    <div class="transaction-review-section">

        <div class="transaction-review-section-title">
            Transaction Notes
        </div>

        <div class="transaction-review-notes">

            <?php if (
                !empty(
                    trim(
                        (string) (
                            $transaction['notes'] ?? ''
                        )
                    )
                )
            ): ?>

                <?= nl2br(
                    reviewEscape(
                        $transaction['notes']
                    )
                ) ?>

            <?php else: ?>

                <span class="transaction-review-empty">
                    No transaction notes available.
                </span>

            <?php endif; ?>

        </div>

    </div>



    <!-- =========================================================
         SELLER NOTES
    ========================================================== -->

    <?php if (!empty($transaction['seller_notes'])): ?>

        <div class="transaction-review-section">

            <div class="transaction-review-section-title">
                Seller Notes
            </div>

            <div class="transaction-review-notes">

                <?= nl2br(
                    reviewEscape(
                        $transaction['seller_notes']
                    )
                ) ?>

            </div>

        </div>

    <?php endif; ?>



    <!-- =========================================================
         ADMIN FEEDBACK
    ========================================================== -->

    <?php if (!empty($transaction['admin_feedback'])): ?>

        <div class="transaction-review-section">

            <div class="transaction-review-section-title">
                Admin Feedback
            </div>

            <div class="transaction-review-notes">

                <?= nl2br(
                    reviewEscape(
                        $transaction['admin_feedback']
                    )
                ) ?>

            </div>

        </div>

    <?php endif; ?>



    <!-- =========================================================
         FOOTER
    ========================================================== -->

    <div class="transaction-review-footer">

        <span>
            Transaction #<?= (int) $transaction['id'] ?>
        </span>

        <span>
            VNZ TECH MANAGEMENT
        </span>

    </div>

</div>