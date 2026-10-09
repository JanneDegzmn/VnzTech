<?php

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
        } elseif (in_array($conversation['conversation_type'], ['global', 'private'], true)) {
            $transactionChatConversationType = $conversation['conversation_type'];
            $transactionChatConversationDbId = (int) $conversation['conversation_id'];
            $transactionChatDisplayName = (string) $conversation['display_name'];
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

    chatGetTransactionContext(
        (int) $transactionChatWindowId,
        $transactionChatActor
    );
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
$transactionChatConversationType = 'transaction';
$transactionChatConversationDbId = $transactionChatResolvedConversationId;
$transactionChatDisplayName = (string) $transactionChatData['transaction_reference'];
$transactionChatReference = $transactionChatData['transaction_reference'];
$transactionChatStatus = $transactionChatData['transaction_status'];
$transactionChatPaymentStatus = $transactionChatData['payment_status'];
$transactionChatSubmissionReference = $transactionChatData['submission_reference'];
$transactionChatSubmissionStatus = $transactionChatData['submission_status'];
$transactionChatOfferAmount = $transactionChatData['offer_amount'];
$transactionChatFinalAmount = $transactionChatData['final_amount'];
require __DIR__ . '/transaction-chat.php';
