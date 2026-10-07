<?php

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
            <h2>Account unavailable</h2>
            <p>Your account session is no longer available.</p>
        </div>
    ';

    exit;
}

$userId = (int) $_SESSION['user_id'];

if ($userId <= 0) {
    http_response_code(401);

    echo '
        <div class="portal-error">
            <h2>Account unavailable</h2>
            <p>Your account session is no longer available.</p>
        </div>
    ';

    exit;
}

/*
|--------------------------------------------------------------------------
| TRANSACTION IDENTIFIER
|--------------------------------------------------------------------------
*/

$transactionId = (int) ($_GET['id'] ?? 0);
$transactionReference = trim($_GET['reference'] ?? '');

if ($transactionId <= 0 && $transactionReference === '') {
    http_response_code(400);

    echo '
        <div class="portal-error">
            <h2>Transaction unavailable</h2>
            <p>No transaction was specified.</p>
        </div>
    ';

    exit;
}

/*
|--------------------------------------------------------------------------
| LOAD TRANSACTION + ACCEPTED OFFER + SUBMISSION
|--------------------------------------------------------------------------
|
| Seller ownership is always verified through:
|
| transactions -> submissions -> user_id
|
*/

try {

    $sql = "
        SELECT
            t.id,
            t.submission_id,
            t.offer_id,
            t.transaction_reference,
            t.final_amount,
            t.payment_method,
            t.payment_status,
            t.transaction_status,
            t.notes AS transaction_notes,
            t.completed_at,
            t.created_at AS transaction_created_at,
            t.updated_at AS transaction_updated_at,

            o.offer_amount,
            o.feedback AS offer_feedback,
            o.status AS offer_status,
            o.sender_type AS offer_sender_type,
            o.sent_at AS offer_sent_at,
            o.responded_at AS offer_responded_at,
            o.created_at AS offer_created_at,
            o.updated_at AS offer_updated_at,

            s.reference_number AS submission_reference,
            s.seller_name,
            s.seller_mobile,
            s.seller_address,
            s.selling_method,
            s.status AS submission_status,
            s.seller_notes,
            s.admin_feedback AS submission_admin_feedback,
            s.created_at AS submission_created_at,
            s.updated_at AS submission_updated_at

        FROM transactions t

        INNER JOIN submissions s
            ON s.id = t.submission_id

        LEFT JOIN offers o
            ON o.id = t.offer_id

        WHERE s.user_id = ?
    ";

    $params = [$userId];

    if ($transactionId > 0) {
        $sql .= "
            AND t.id = ?
        ";

        $params[] = $transactionId;

    } else {
        $sql .= "
            AND t.transaction_reference = ?
        ";

        $params[] = $transactionReference;
    }

    $sql .= "
        LIMIT 1
    ";

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);

    $transaction = $stmt->fetch();

    if (!$transaction) {
        http_response_code(404);

        echo '
            <div class="portal-error">
                <h2>Transaction not found</h2>
                <p>This transaction does not exist or is not available to your account.</p>
            </div>
        ';

        exit;
    }

    /*
    |--------------------------------------------------------------------------
    | LOAD SUBMISSION ITEMS
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

    $items = $itemStmt->fetchAll();

    /*
    |--------------------------------------------------------------------------
    | LOAD SUBMISSION PHOTOS
    |--------------------------------------------------------------------------
    */

    $photosByItem = [];

    if ($items) {
        $itemIds = array_map(
            static function ($item) {
                return (int) $item['id'];
            },
            $items
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

} catch (Throwable $e) {
    http_response_code(500);

    echo '
        <div class="portal-error">
            <h2>Unable to load transaction</h2>
            <p>Something went wrong while loading this transaction.</p>
        </div>
    ';

    exit;
}

/*
|--------------------------------------------------------------------------
| HELPERS
|--------------------------------------------------------------------------
*/

function transactionDetailEscape($value)
{
    return htmlspecialchars(
        (string) $value,
        ENT_QUOTES,
        'UTF-8'
    );
}

function transactionDetailDate($value)
{
    if (empty($value)) {
        return '—';
    }

    $timestamp = strtotime($value);

    if (!$timestamp) {
        return transactionDetailEscape($value);
    }

    return date('M d, Y · h:i A', $timestamp);
}

function transactionDetailStatusLabel($status)
{
    return ucwords(
        str_replace(
            '_',
            ' ',
            (string) $status
        )
    );
}

function transactionDetailStatusClass($status)
{
    $allowed = [
        'pending',
        'completed',
        'cancelled'
    ];

    if (!in_array($status, $allowed, true)) {
        return 'status-default';
    }

    return 'status-' . $status;
}

function transactionDetailOfferStatusClass($status)
{
    $allowed = [
        'draft',
        'sent',
        'accepted',
        'declined',
        'expired'
    ];

    if (!in_array($status, $allowed, true)) {
        return 'status-default';
    }

    if ($status === 'accepted') {
        return 'status-accepted';
    }

    if ($status === 'declined' || $status === 'expired') {
        return 'status-cancelled';
    }

    return 'status-pending';
}

function transactionDetailPaymentLabel($status)
{
    if (empty($status)) {
        return '—';
    }

    return ucwords(
        str_replace(
            '_',
            ' ',
            (string) $status
        )
    );
}

function transactionDetailItemTypeLabel($type)
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

function transactionDetailDamageLabel($level)
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

<div class="seller-submission-detail-page transaction-detail-page">

    <!-- =====================================================
         HEADER
    ====================================================== -->

    <header class="seller-submission-detail-header">

        <div>

            <div class="eyebrow">
                TRANSACTION
            </div>

            <h1>
                Transaction Details.
            </h1>

            <p>
                View the complete information for this transaction.
            </p>

        </div>

        <div class="seller-submission-reference">
            <?= transactionDetailEscape(
                $transaction['transaction_reference']
            ) ?>
        </div>

    </header>


    <!-- =====================================================
         TRANSACTION OVERVIEW
    ====================================================== -->

    <section class="submission-result-area">

        <div class="result-header">

            <div>

                <span class="section-label">
                    TRANSACTION STATUS
                </span>

                <h2>
                    <?= transactionDetailEscape(
                        $transaction['transaction_reference']
                    ) ?>
                </h2>

            </div>

            <span
                class="result-status <?= transactionDetailStatusClass(
                    $transaction['transaction_status']
                ) ?>"
            >
                <?= transactionDetailEscape(
                    transactionDetailStatusLabel(
                        $transaction['transaction_status']
                    )
                ) ?>
            </span>

        </div>


        <!-- =================================================
             TRANSACTION INFORMATION
        ================================================== -->

        <div class="seller-result-card">

            <span class="section-label">
                TRANSACTION INFORMATION
            </span>

            <div class="seller-result-grid">

                <div>
                    <small>TYPE</small>
                    <strong>
                        <?= $transaction['offer_id'] !== null
                            ? 'Negotiated Offer'
                            : 'Direct Admin Acceptance'
                        ?>
                    </strong>
                </div>

                <div>
                    <small>OFFER</small>
                    <strong>
                        #<?= transactionDetailEscape(
                            $transaction['offer_id']
                        ) ?>
                    </strong>
                </div>

                <div>
                    <small>FINAL AMOUNT</small>
                    <strong>
                        ₱<?= number_format(
                            (float) $transaction['final_amount'],
                            2
                        ) ?>
                    </strong>
                </div>

                <div>
                    <small>PAYMENT STATUS</small>
                    <strong>
                        <?= transactionDetailEscape(
                            transactionDetailPaymentLabel(
                                $transaction['payment_status']
                            )
                        ) ?>
                    </strong>
                </div>

            </div>

        </div>


        <!-- =================================================
             ACCEPTED OFFER
        ================================================== -->

        <div class="offer-result-card transaction-offer-card">

        <?php if ($transaction['offer_id'] !== null): ?>

            <div class="offer-header">

                <div>

                    <span class="section-label">
                        ACCEPTED OFFER
                    </span>

                    <h2>
                        Offer #<?= transactionDetailEscape(
                            $transaction['offer_id']
                        ) ?>
                    </h2>

                </div>

                <span
                    class="result-status <?= transactionDetailOfferStatusClass(
                        $transaction['offer_status']
                    ) ?>"
                >
                    <?= transactionDetailEscape(
                        transactionDetailStatusLabel(
                            $transaction['offer_status']
                        )
                    ) ?>
                </span>

            </div>

            <div class="finished-offer-amount">
                ₱<?= number_format(
                    (float) ($transaction['offer_amount'] ?? $transaction['final_amount']),
                    2
                ) ?>
            </div>

            <div class="seller-result-grid transaction-offer-grid">

                <div>
                    <small>SENDER</small>
                    <strong>
                        <?= transactionDetailEscape(
                            ucfirst(
                                (string) ($transaction['offer_sender_type'] ?? '—')
                            )
                        ) ?>
                    </strong>
                </div>

                <div>
                    <small>SENT</small>
                    <strong>
                        <?= transactionDetailDate(
                            $transaction['offer_sent_at']
                        ) ?>
                    </strong>
                </div>

                <div>
                    <small>RESPONDED</small>
                    <strong>
                        <?= transactionDetailDate(
                            $transaction['offer_responded_at']
                        ) ?>
                    </strong>
                </div>

                <div>
                    <small>OFFER CREATED</small>
                    <strong>
                        <?= transactionDetailDate(
                            $transaction['offer_created_at']
                        ) ?>
                    </strong>
                </div>

            </div>

            <?php if (!empty($transaction['offer_feedback'])): ?>

                <div class="offer-message">
                    <span>OFFER FEEDBACK</span>
                    <p>
                        <?= nl2br(
                            transactionDetailEscape(
                                $transaction['offer_feedback']
                            )
                        ) ?>
                    </p>
                </div>

            <?php endif; ?>
                        <?php else: ?>

                <div class="offer-header">
                    <div>
                        <span class="section-label">
                            ACCEPTED OFFER
                        </span>

                        <h2>
                            Direct Admin Acceptance
                        </h2>
                    </div>
                </div>

                <div class="finished-offer-amount">
                    ₱<?= number_format(
                        (float) $transaction['final_amount'],
                        2
                    ) ?>
                </div>

                <div class="offer-message">
                    <span>DETAILS</span>
                    <p>
                        This transaction was created from the seller's asking price
                        accepted directly by the admin.
                    </p>
                </div>

            <?php endif; ?>

        </div>


        <!-- =================================================
             SUBMISSION INFORMATION
        ================================================== -->

        <div class="seller-result-card">

            <span class="section-label">
                SUBMISSION INFORMATION
            </span>

            <div class="seller-result-grid">

                <div>
                    <small>SELLER</small>
                    <strong>
                        <?= transactionDetailEscape(
                            $transaction['seller_name']
                        ) ?>
                    </strong>
                </div>

                <div>
                    <small>MOBILE</small>
                    <strong>
                        <?= transactionDetailEscape(
                            $transaction['seller_mobile']
                        ) ?>
                    </strong>
                </div>

                <div>
                    <small>SELLING METHOD</small>
                    <strong>
                        <?= transactionDetailEscape(
                            ucwords(
                                str_replace(
                                    '_',
                                    ' ',
                                    (string) $transaction['selling_method']
                                )
                            )
                        ) ?>
                    </strong>
                </div>

                <div>
                    <small>SUBMISSION STATUS</small>
                    <strong>
                        <?= transactionDetailEscape(
                            transactionDetailStatusLabel(
                                $transaction['submission_status']
                            )
                        ) ?>
                    </strong>
                </div>

                <div class="full-width">
                    <small>ADDRESS</small>
                    <strong>
                        <?= transactionDetailEscape(
                            $transaction['seller_address']
                        ) ?>
                    </strong>
                </div>

            </div>

            <?php if (!empty($transaction['seller_notes'])): ?>

                <div class="offer-message">
                    <span>SELLER NOTES</span>
                    <p>
                        <?= nl2br(
                            transactionDetailEscape(
                                $transaction['seller_notes']
                            )
                        ) ?>
                    </p>
                </div>

            <?php endif; ?>

            <?php if (!empty($transaction['submission_admin_feedback'])): ?>

                <div class="offer-message">
                    <span>ADMIN FEEDBACK</span>
                    <p>
                        <?= nl2br(
                            transactionDetailEscape(
                                $transaction['submission_admin_feedback']
                            )
                        ) ?>
                    </p>
                </div>

            <?php endif; ?>

        </div>


        <!-- =================================================
             SUBMITTED ITEMS
        ================================================== -->

        <div class="items-result-card transaction-items-card">

            <span class="section-label">
                SUBMITTED ITEMS
            </span>

            <div class="result-items">

                <?php if (!$items): ?>

                    <div class="no-offer-card">
                        <span>ITEMS</span>
                        <strong>No item details available.</strong>
                    </div>

                <?php else: ?>

                    <?php foreach ($items as $index => $item): ?>

                        <?php
                        $itemId = (int) $item['id'];
                        $itemPhotos = $photosByItem[$itemId] ?? [];
                        ?>

                        <article class="result-item transaction-result-item">

                            <div class="result-item-top">

                                <div>

                                    <div class="result-item-number">
                                        <?= $index + 1 ?>
                                    </div>

                                    <div>
                                        <small>ITEM TYPE</small>
                                        <strong>
                                            <?= transactionDetailEscape(
                                                transactionDetailItemTypeLabel(
                                                    $item['item_type']
                                                )
                                            ) ?>
                                        </strong>
                                    </div>

                                </div>

                                <span class="result-quantity">
                                    QTY <?= (int) $item['quantity'] ?>
                                </span>

                            </div>

                            <div class="result-item-grid">

                                <div>
                                    <small>BRAND</small>
                                    <strong>
                                        <?= $item['brand'] !== null && $item['brand'] !== ''
                                            ? transactionDetailEscape($item['brand'])
                                            : '—'
                                        ?>
                                    </strong>
                                </div>

                                <div>
                                    <small>MODEL</small>
                                    <strong>
                                        <?= $item['model'] !== null && $item['model'] !== ''
                                            ? transactionDetailEscape($item['model'])
                                            : '—'
                                        ?>
                                    </strong>
                                </div>

                                <div>
                                    <small>STORAGE</small>
                                    <strong>
                                        <?= $item['storage'] !== null && $item['storage'] !== ''
                                            ? transactionDetailEscape($item['storage'])
                                            : '—'
                                        ?>
                                    </strong>
                                </div>

                                <div>
                                    <small>ISSUE</small>
                                    <strong>
                                        <?= $item['issue'] !== null && $item['issue'] !== ''
                                            ? transactionDetailEscape($item['issue'])
                                            : '—'
                                        ?>
                                    </strong>
                                </div>

                                <div>
                                    <small>DAMAGE</small>
                                    <strong>
                                        <?= transactionDetailEscape(
                                            transactionDetailDamageLabel(
                                                $item['damage_level']
                                            )
                                        ) ?>
                                    </strong>
                                </div>

                                <div>
                                    <small>ASKING PRICE</small>
                                    <strong>
                                        ₱<?= number_format(
                                            (float) $item['seller_asking_price'],
                                            2
                                        ) ?>
                                    </strong>
                                </div>

                                <?php if (!empty($item['board_code'])): ?>

                                    <div>
                                        <small>BOARD CODE</small>
                                        <strong class="board-code">
                                            <?= transactionDetailEscape(
                                                $item['board_code']
                                            ) ?>
                                        </strong>
                                    </div>

                                <?php endif; ?>

                            </div>

                            <?php if (!empty($item['issue_details'])): ?>

                                <div class="offer-message">
                                    <span>ISSUE DETAILS</span>
                                    <p>
                                        <?= nl2br(
                                            transactionDetailEscape(
                                                $item['issue_details']
                                            )
                                        ) ?>
                                    </p>
                                </div>

                            <?php endif; ?>

                            <?php if (!empty($item['additional_information'])): ?>

                                <div class="offer-message">
                                    <span>ADDITIONAL INFORMATION</span>
                                    <p>
                                        <?= nl2br(
                                            transactionDetailEscape(
                                                $item['additional_information']
                                            )
                                        ) ?>
                                    </p>
                                </div>

                            <?php endif; ?>

                            <?php if ($itemPhotos): ?>

                                <div class="transaction-photo-section">

                                    <span class="section-label">
                                        PHOTOS
                                    </span>

                                    <div class="transaction-photo-grid">

                                        <?php foreach ($itemPhotos as $photo): ?>

                                            <?php
                                            $photoPath = (string) $photo['file_path'];

                                            if ($photoPath !== '' && $photoPath[0] !== '/') {
                                                $photoPath = '/' . $photoPath;
                                            }
                                            ?>

                                            <a
                                                href="<?= transactionDetailEscape($photoPath) ?>"
                                                target="_blank"
                                                rel="noopener"
                                                class="transaction-photo-link"
                                            >
                                                <img
                                                    src="<?= transactionDetailEscape($photoPath) ?>"
                                                    alt="<?= transactionDetailEscape(
                                                        $photo['original_name'] ?: 'Submission photo'
                                                    ) ?>"
                                                    loading="lazy"
                                                >
                                            </a>

                                        <?php endforeach; ?>

                                    </div>

                                </div>

                            <?php endif; ?>

                        </article>

                    <?php endforeach; ?>

                <?php endif; ?>

            </div>

        </div>


        <!-- =================================================
             DATES + PAYMENT
        ================================================== -->

        <div class="seller-result-card">

            <span class="section-label">
                TRANSACTION TIMELINE
            </span>

            <div class="seller-result-grid">

                <div>
                    <small>TRANSACTION CREATED</small>
                    <strong>
                        <?= transactionDetailDate(
                            $transaction['transaction_created_at']
                        ) ?>
                    </strong>
                </div>

                <div>
                    <small>LAST UPDATED</small>
                    <strong>
                        <?= transactionDetailDate(
                            $transaction['transaction_updated_at']
                        ) ?>
                    </strong>
                </div>

                <div>
                    <small>COMPLETED</small>
                    <strong>
                        <?= transactionDetailDate(
                            $transaction['completed_at']
                        ) ?>
                    </strong>
                </div>

                <div>
                    <small>PAYMENT METHOD</small>
                    <strong>
                        <?= $transaction['payment_method'] !== null && $transaction['payment_method'] !== ''
                            ? transactionDetailEscape($transaction['payment_method'])
                            : '—'
                        ?>
                    </strong>
                </div>

            </div>

            <?php if (!empty($transaction['transaction_notes'])): ?>

                <div class="offer-message">
                    <span>TRANSACTION NOTES</span>
                    <p>
                        <?= nl2br(
                            transactionDetailEscape(
                                $transaction['transaction_notes']
                            )
                        ) ?>
                    </p>
                </div>

            <?php endif; ?>

        </div>


        <?php
        $transactionChatId = (int) $transaction['id'];
        ?>
        <button
            type="button"
            class="transaction-chat-shortcut"
            data-open-transaction-chat
            data-transaction-id="<?= $transactionChatId ?>"
            data-transaction-reference="<?= transactionDetailEscape($transaction['transaction_reference']) ?>"
        >
            <span>
                <strong>Open transaction chat</strong>
                <small>Continue this conversation in the floating chat window.</small>
            </span>
            <span aria-hidden="true">→</span>
        </button>

        <!-- =================================================
             BACK
        ================================================== -->

        <button
            type="button"
            class="submission-back-button"
            onclick="history.back();"
        >
            <span>←</span>
            BACK TO TRANSACTIONS
        </button>

    </section>

</div>
