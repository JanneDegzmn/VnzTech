<?php


require_once __DIR__ . '/../backend/site_status.php';
vnzEnforceSiteMaintenance('auto', true, true, 'messaging');
require_once __DIR__ . '/../backend/chat_scaledrone.php';

$transactionChatWindowId = filter_input(
    INPUT_GET,
    'transaction_id',
    FILTER_VALIDATE_INT
);
$transactionChatConversationId = filter_input(
    INPUT_GET,
    'conversation_id',
    FILTER_VALIDATE_INT
);
$transactionChatResolvedConversationId = 0;
$transactionChatGuestSubmission = false;
$transactionChatClaimedSubmission = false;
$transactionChatConversationClass = 'transaction';
$transactionChatTradeRequestData = null;
$transactionChatTradeRequirements = [];

try {
    $transactionChatActor = chatGetCurrentActor();

    if (
        $transactionChatConversationId !== false
        && $transactionChatConversationId !== null
        && $transactionChatConversationId > 0
    ) {
        $conversation = chatRequireConversationMembership(
            (int) $transactionChatConversationId,
            $transactionChatActor
        );
        if ($conversation['conversation_type'] === 'transaction') {
            if ($conversation['transaction_id'] === null) {
                throw new ChatHttpException('Unauthorized.', 403);
            }
            $transactionChatWindowId = (int) $conversation['transaction_id'];
            $transactionChatResolvedConversationId = (int) $conversation['conversation_id'];
        } elseif (
            $conversation['conversation_type'] === 'private'
            && !empty($conversation['claimed_submission'])
        ) {
            if (
                $conversation['transaction_id'] === null
                || (int) $conversation['transaction_id'] <= 0
            ) {
                throw new ChatHttpException('Unauthorized.', 403);
            }
            $transactionChatWindowId = (int) $conversation['transaction_id'];
            $transactionChatResolvedConversationId = (int) $conversation['conversation_id'];
            $transactionChatClaimedSubmission = true;
            $transactionChatShowTransactionContext = true;
        } elseif (in_array($conversation['conversation_type'], ['global', 'private'], true)) {
            $transactionChatConversationType = $conversation['conversation_type'];
            $transactionChatConversationClass =
                $conversation['conversation_class'];
            $transactionChatConversationDbId = (int) $conversation['conversation_id'];
            $transactionChatDisplayName = (string) $conversation['display_name'];
            $transactionChatGuestSubmission =
                !empty($conversation['guest_submission']);
            $transactionChatClaimedSubmission =
                !empty($conversation['claimed_submission']);
            $transactionChatId = 0;
            $transactionChatReference = '';
            $transactionChatStatus = null;
            $transactionChatPaymentStatus = null;
            $transactionChatSubmissionReference = null;
            $transactionChatSubmissionStatus = null;
            $transactionChatOfferAmount = null;
            $transactionChatFinalAmount = null;
            $transactionChatSubmissionItems = [];
            $transactionChatPhotosByItem = [];

            if ($conversation['conversation_class'] === 'trade_request') {
                $tradeRequestStmt = $pdo->prepare("
                    SELECT
                        tr.request_reference,
                        tr.status AS request_status,
                        tr.created_at AS requested_at,
                        ti.name AS item_name,
                        ti.`condition` AS item_condition,
                        ti.description AS item_description,
                        ti.image_path AS item_image_path
                    FROM trade_requests tr
                    INNER JOIN trade_items ti
                        ON ti.id = tr.trade_item_id
                    WHERE tr.id = ?
                    LIMIT 1
                ");
                $tradeRequestStmt->execute([
                    (int) $conversation['trade_request_id']
                ]);
                $transactionChatTradeRequestData =
                    $tradeRequestStmt->fetch(PDO::FETCH_ASSOC);
                if (!$transactionChatTradeRequestData) {
                    throw new ChatHttpException('Trade request not found.', 404);
                }

                $tradeRequirementsStmt = $pdo->prepare("
                    SELECT requirement_type, quantity, storage, description
                    FROM trade_requirements
                    WHERE trade_item_id = (
                        SELECT trade_item_id
                        FROM trade_requests
                        WHERE id = ?
                    )
                    ORDER BY sort_order ASC, id ASC
                ");
                $tradeRequirementsStmt->execute([
                    (int) $conversation['trade_request_id']
                ]);
                $transactionChatTradeRequirements =
                    $tradeRequirementsStmt->fetchAll(PDO::FETCH_ASSOC);
            } elseif ($conversation['submission_id'] !== null) {
                $guestContextStmt = $pdo->prepare("
                    SELECT
                        s.id AS submission_id,
                        s.reference_number AS submission_reference,
                        s.status AS submission_status,
                        t.id AS transaction_id,
                        t.transaction_reference,
                        t.transaction_status,
                        t.payment_status,
                        t.final_amount,
                        o.offer_amount
                    FROM submissions s
                    LEFT JOIN transactions t
                        ON t.id = (
                            SELECT t2.id
                            FROM transactions t2
                            WHERE t2.submission_id = s.id
                            ORDER BY t2.id DESC
                            LIMIT 1
                        )
                    LEFT JOIN offers o
                        ON o.id = COALESCE(
                            t.offer_id,
                            (
                                SELECT o2.id
                                FROM offers o2
                                WHERE o2.submission_id = s.id
                                ORDER BY o2.id DESC
                                LIMIT 1
                            )
                        )
                    WHERE s.id = ?
                    LIMIT 1
                ");
                $guestContextStmt->execute([(int) $conversation['submission_id']]);
                $guestContext = $guestContextStmt->fetch(PDO::FETCH_ASSOC);
                if (!$guestContext) {
                    throw new ChatHttpException('Submission not found.', 404);
                }

                $transactionChatShowTransactionContext = true;
                $transactionChatId = (int) ($guestContext['transaction_id'] ?? 0);
                $transactionChatReference =
                    (string) ($guestContext['transaction_reference'] ?? '—');
                $transactionChatStatus = $guestContext['transaction_status'];
                $transactionChatPaymentStatus = $guestContext['payment_status'];
                $transactionChatSubmissionReference =
                    (string) $guestContext['submission_reference'];
                $transactionChatSubmissionStatus = $guestContext['submission_status'];
                $transactionChatOfferAmount = $guestContext['offer_amount'];
                $transactionChatFinalAmount = $guestContext['final_amount'];

                $guestItemsStmt = $pdo->prepare("
                    SELECT
                        id,
                        item_type,
                        brand,
                        model,
                        storage,
                        issue,
                        issue_details,
                        board_code,
                        quantity
                    FROM submission_items
                    WHERE submission_id = ?
                    ORDER BY id ASC
                ");
                $guestItemsStmt->execute([(int) $guestContext['submission_id']]);
                $transactionChatSubmissionItems = $guestItemsStmt->fetchAll(PDO::FETCH_ASSOC);
                if ($transactionChatSubmissionItems) {
                    $guestItemIds = array_map(
                        static fn(array $item): int => (int) $item['id'],
                        $transactionChatSubmissionItems
                    );
                    $guestPhotoPlaceholders = implode(
                        ',',
                        array_fill(0, count($guestItemIds), '?')
                    );
                    $guestPhotosStmt = $pdo->prepare("
                        SELECT
                            submission_item_id,
                            file_path,
                            original_name,
                            photo_type,
                            sort_order,
                            id
                        FROM submission_photos
                        WHERE submission_item_id IN ($guestPhotoPlaceholders)
                        ORDER BY submission_item_id ASC, sort_order ASC, id ASC
                    ");
                    $guestPhotosStmt->execute($guestItemIds);
                    foreach ($guestPhotosStmt->fetchAll(PDO::FETCH_ASSOC) as $guestPhoto) {
                        $transactionChatPhotosByItem[(int) $guestPhoto['submission_item_id']][] =
                            $guestPhoto;
                    }
                }
            }

            require __DIR__ . '/transaction-chat.php';
            return;
        } else {
            throw new ChatHttpException(
                'This conversation is not available.',
                422
            );
        }
    }

    if ($transactionChatWindowId === false || $transactionChatWindowId === null || $transactionChatWindowId <= 0) {
        http_response_code(422);
        ?>
        <section class="transaction-chat-unavailable" role="alert">
            <strong>Conversation unavailable</strong>
            <p>A valid conversation was not specified.</p>
        </section>
        <?php
        return;
    }

    $transactionChatContext = chatGetTransactionContext(
        (int) $transactionChatWindowId,
        $transactionChatActor
    );
    $transactionChatConversationClass =
        $transactionChatContext['conversation_class'] ?? 'transaction';
    if (!empty($transactionChatContext['guest_chat_conversation_id'])) {
        $transactionChatResolvedConversationId =
            (int) $transactionChatContext['guest_chat_conversation_id'];
        $transactionChatConversationType = 'private';
        $transactionChatDisplayName = $transactionChatContext['conversation_class']
            === 'claimed_submission'
                ? ($transactionChatActor['actor_type'] === 'admin'
                    ? 'SELLER - '
                        . (trim((string) ($transactionChatContext['seller_full_name'] ?? ''))
                            ?: 'Seller')
                    : 'ADMIN')
                : ($transactionChatActor['actor_type'] === 'admin'
                    ? 'Guest Seller · ' . $transactionChatContext['submission_reference']
                    : 'Admin');
        $transactionChatGuestSubmission =
            !empty($transactionChatContext['guest_submission']);
        $transactionChatClaimedSubmission =
            !empty($transactionChatContext['claimed_submission']);
        $transactionChatShowTransactionContext = true;
    }
} catch (ChatHttpException $e) {
    http_response_code($e->httpStatus);
    ?>
    <section class="transaction-chat-unavailable" role="alert">
        <strong>Conversation unavailable</strong>
        <p><?= htmlspecialchars($e->getMessage(), ENT_QUOTES, 'UTF-8') ?></p>
    </section>
    <?php
    return;
} catch (Throwable $e) {
    error_log('Chat window could not be authorized: ' . $e->getMessage());
    http_response_code(500);
    ?>
    <section class="transaction-chat-unavailable" role="alert">
        <strong>Conversation unavailable</strong>
        <p>Unable to load this conversation right now.</p>
    </section>
    <?php
    return;
}

$transactionChatDataStmt = $pdo->prepare("
    SELECT
        t.id,
        t.transaction_reference,
        t.transaction_status,
        t.payment_status,
        t.final_amount,
        o.offer_amount,
        s.reference_number AS submission_reference,
        s.status AS submission_status
    FROM transactions t
    INNER JOIN submissions s
        ON s.id = t.submission_id
    LEFT JOIN offers o
        ON o.id = t.offer_id
    WHERE t.id = ?
    LIMIT 1
");
$transactionChatDataStmt->execute([(int) $transactionChatWindowId]);
$transactionChatData = $transactionChatDataStmt->fetch();

if (!$transactionChatData) {
    http_response_code(404);
    ?>
    <section class="transaction-chat-unavailable" role="alert">
        <strong>Conversation unavailable</strong>
        <p>The transaction could not be found.</p>
    </section>
    <?php
    return;
}

$transactionChatItemStmt = $pdo->prepare("
    SELECT
        id,
        item_type,
        brand,
        model,
        storage,
        issue,
        issue_details,
        board_code,
        quantity
    FROM submission_items
    WHERE submission_id = (
        SELECT submission_id
        FROM transactions
        WHERE id = ?
    )
    ORDER BY id ASC
");
$transactionChatItemStmt->execute([(int) $transactionChatWindowId]);
$transactionChatSubmissionItems = $transactionChatItemStmt->fetchAll();
$transactionChatPhotosByItem = [];

if ($transactionChatSubmissionItems) {
    $transactionChatItemIds = array_map(
        static fn(array $item): int => (int) $item['id'],
        $transactionChatSubmissionItems
    );
    $transactionChatPhotoPlaceholders = implode(
        ',',
        array_fill(0, count($transactionChatItemIds), '?')
    );
    $transactionChatPhotoStmt = $pdo->prepare("
        SELECT
            submission_item_id,
            file_path,
            original_name,
            photo_type,
            sort_order,
            id
        FROM submission_photos
        WHERE submission_item_id IN ($transactionChatPhotoPlaceholders)
        ORDER BY submission_item_id ASC, sort_order ASC, id ASC
    ");
    $transactionChatPhotoStmt->execute($transactionChatItemIds);

    foreach ($transactionChatPhotoStmt->fetchAll() as $transactionChatPhoto) {
        $transactionChatPhotosByItem[(int) $transactionChatPhoto['submission_item_id']][] =
            $transactionChatPhoto;
    }
}

$transactionChatId = (int) $transactionChatData['id'];
$transactionChatConversationType = $transactionChatResolvedConversationId > 0
    ? 'private'
    : 'transaction';
$transactionChatConversationDbId = $transactionChatResolvedConversationId;
if (!$transactionChatGuestSubmission && !$transactionChatClaimedSubmission) {
    $transactionChatDisplayName = (string) $transactionChatData['transaction_reference'];
}
$transactionChatReference = $transactionChatData['transaction_reference'];
$transactionChatStatus = $transactionChatData['transaction_status'];
$transactionChatPaymentStatus = $transactionChatData['payment_status'];
$transactionChatSubmissionReference = $transactionChatData['submission_reference'];
$transactionChatSubmissionStatus = $transactionChatData['submission_status'];
$transactionChatOfferAmount = $transactionChatData['offer_amount'];
$transactionChatFinalAmount = $transactionChatData['final_amount'];
require __DIR__ . '/transaction-chat.php';
