<?php

ob_start();
ini_set('display_errors', '0');

require_once __DIR__ . '/chat_service.php';
require_once __DIR__ . '/chat_scaledrone.php';

function chatMessageReactionOptions(): array
{
    return ['👍', '❤️', '😂', '😮', '😢', '😡'];
}

function chatMessageReplyPayload(PDO $pdo, int $conversationId, int $messageId, array $actor): ?array
{
    $stmt = $pdo->prepare("
        SELECT
            m.id,
            m.sender_type,
            m.sender_id,
            CASE
                WHEN m.sender_type = 'seller' THEN u.full_name
                WHEN c.type = 'private' THEN COALESCE(
                    NULLIF(TRIM(CONCAT(a.first_name, ' ', a.last_name)), ''),
                    'Admin'
                )
                ELSE 'Admin'
            END AS sender_display_name,
            m.content,
            m.deleted_at,
            m.edited_at,
            m.created_at
        FROM messages m
        INNER JOIN conversations c
            ON c.id = m.conversation_id
        LEFT JOIN users u
            ON m.sender_type = 'seller'
           AND u.id = m.sender_id
        LEFT JOIN admins a
            ON m.sender_type = 'admin'
           AND a.id = m.sender_id
        WHERE m.id = ?
          AND m.conversation_id = ?
        LIMIT 1
    ");
    $stmt->execute([$messageId, $conversationId]);
    $reply = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$reply) {
        return null;
    }

    $content = (string) ($reply['content'] ?? '');
    if (!empty($reply['deleted_at'])) {
        $content = 'This message was deleted';
    }

    return [
        'id' => (int) $reply['id'],
        'sender_type' => $reply['sender_type'],
        'sender_id' => (int) $reply['sender_id'],
        'sender_display_name' => trim((string) ($reply['sender_display_name'] ?? '')) ?: (
            $reply['sender_type'] === 'seller' ? 'Seller' : 'Admin'
        ),
        'content' => $content,
        'created_at' => $reply['created_at'],
        'edited_at' => $reply['edited_at'] ?: null,
        'deleted_at' => $reply['deleted_at'] ?: null,
        'preview' => mb_strlen($content, 'UTF-8') > 140
            ? mb_substr($content, 0, 140, 'UTF-8') . '…'
            : $content
    ];
}

function chatLoadMessageReactions(
    PDO $pdo,
    array $messageIds,
    array $actor
): array
{
    $allowed = chatMessageReactionOptions();
    $messageIds = array_values(array_unique(array_filter(
        array_map('intval', $messageIds),
        static function (int $messageId): bool {
            return $messageId > 0;
        }
    )));
    if (!$messageIds) {
        return [];
    }

    $placeholders = implode(',', array_fill(0, count($messageIds), '?'));
    $reactionStmt = $pdo->prepare("
        SELECT
            mr.message_id,
            mr.reaction,
            mr.actor_type,
            mr.actor_id,
            CASE
                WHEN mr.actor_type = 'seller' THEN u.full_name
                ELSE COALESCE(
                    NULLIF(TRIM(CONCAT(a.first_name, ' ', a.last_name)), ''),
                    'Admin'
                )
            END AS display_name
        FROM message_reactions mr
        LEFT JOIN users u
            ON mr.actor_type = 'seller'
           AND u.id = mr.actor_id
        LEFT JOIN admins a
            ON mr.actor_type = 'admin'
           AND a.id = mr.actor_id
        WHERE mr.message_id IN ($placeholders)
        ORDER BY mr.message_id, mr.reaction, mr.created_at, mr.id
    ");
    $reactionStmt->execute($messageIds);

    $messageReactions = [];
    foreach ($reactionStmt->fetchAll(PDO::FETCH_ASSOC) as $reactionRow) {
        $messageId = (int) $reactionRow['message_id'];
        $reaction = (string) $reactionRow['reaction'];
        if (!in_array($reaction, $allowed, true)) {
            continue;
        }

        if (!isset($messageReactions[$messageId][$reaction])) {
            $messageReactions[$messageId][$reaction] = [
                'reaction' => $reaction,
                'count' => 0,
                'active' => false,
                'users' => []
            ];
        }

        $messageReactions[$messageId][$reaction]['count']++;
        if (
            $reactionRow['actor_type'] === $actor['actor_type']
            && (int) $reactionRow['actor_id'] === (int) $actor['actor_id']
        ) {
            $messageReactions[$messageId][$reaction]['active'] = true;
        }
        $displayName = trim((string) ($reactionRow['display_name'] ?? ''));
        if ($displayName === '') {
            $displayName = $reactionRow['actor_type'] === 'seller'
                ? 'Seller'
                : 'Admin';
        }
        $messageReactions[$messageId][$reaction]['users'][] = $displayName;
    }

    foreach ($messageIds as $messageId) {
        foreach ($allowed as $reaction) {
            if (!isset($messageReactions[$messageId][$reaction])) {
                $messageReactions[$messageId][$reaction] = [
                    'reaction' => $reaction,
                    'count' => 0,
                    'active' => false,
                    'users' => []
                ];
            }
        }
        $ordered = [];
        foreach ($allowed as $reaction) {
            $ordered[] = $messageReactions[$messageId][$reaction];
        }
        $messageReactions[$messageId] = $ordered;
    }

    return $messageReactions;
}

function chatMessageReactionSummary(PDO $pdo, int $messageId, array $actor): array
{
    $reactions = chatLoadMessageReactions($pdo, [$messageId], $actor);
    return $reactions[$messageId] ?? [];
}

function chatMessageActorDisplayName(PDO $pdo, array $actor): string
{
    if ($actor['actor_type'] === 'seller') {
        $stmt = $pdo->prepare("
            SELECT full_name
            FROM users
            WHERE id = ?
            LIMIT 1
        ");
    } else {
        $stmt = $pdo->prepare("
            SELECT TRIM(CONCAT(first_name, ' ', last_name))
            FROM admins
            WHERE id = ?
            LIMIT 1
        ");
    }

    $stmt->execute([(int) $actor['actor_id']]);
    $displayName = trim((string) ($stmt->fetchColumn() ?: ''));

    return $displayName !== ''
        ? $displayName
        : ($actor['actor_type'] === 'seller' ? 'Seller' : 'Admin');
}

function chatNormalizeMessagePayload(
    PDO $pdo,
    array $message,
    array $actor,
    int $conversationId,
    ?array $reactionMap = null
): array
{
    $response = [
        'id' => (int) $message['id'],
        'conversation_id' => $conversationId,
        'sender_type' => $message['sender_type'],
        'sender_id' => (int) $message['sender_id'],
        'sender_display_name' => trim((string) ($message['sender_display_name'] ?? '')) ?: (
            $message['sender_type'] === 'seller' ? 'Seller' : 'Admin'
        ),
        'message_type' => $message['message_type'],
        'content' => !empty($message['deleted_at'])
            ? 'This message was deleted'
            : (string) ($message['content'] ?? ''),
        'created_at' => $message['created_at'],
        'edited_at' => $message['edited_at'] ?: null,
        'deleted_at' => $message['deleted_at'] ?: null,
        'reply_to_message_id' => $message['reply_to_message_id'] !== null && $message['reply_to_message_id'] !== ''
            ? (int) $message['reply_to_message_id']
            : null,
        'reactions' => $reactionMap[(int) $message['id']]
            ?? chatMessageReactionSummary($pdo, (int) $message['id'], $actor)
    ];

    if ($response['reply_to_message_id'] !== null) {
        $response['reply'] = chatMessageReplyPayload(
            $pdo,
            $conversationId,
            $response['reply_to_message_id'],
            $actor
        );
    } else {
        $response['reply'] = null;
    }

    $response['is_edited'] = !empty($message['edited_at']);
    return $response;
}

try {
    $action = chatRequestAction();
    $actor = chatGetCurrentActor();

    if ($action === 'history') {
        chatRequireMethod('GET');

        $conversationId = chatPositiveInteger(
            $_GET['conversation_id'] ?? null,
            'conversation ID'
        );
        chatRequireConversationMembership($conversationId, $actor);

        $limit = isset($_GET['limit'])
            ? chatPositiveInteger($_GET['limit'], 'limit')
            : 30;

        if ($limit > 50) {
            throw new ChatHttpException(
                'The message limit cannot exceed 50.',
                422
            );
        }

        global $pdo;

        $cursorId = null;
        $cursorCreatedAt = null;

        if (isset($_GET['before_message_id'])) {
            $cursorId = chatPositiveInteger(
                $_GET['before_message_id'],
                'message cursor'
            );

            $cursorStmt = $pdo->prepare("
                SELECT created_at
                FROM messages
                WHERE id = ?
                  AND conversation_id = ?
                LIMIT 1
            ");
            $cursorStmt->execute([
                $cursorId,
                $conversationId
            ]);
            $cursorCreatedAt = $cursorStmt->fetchColumn();

            if (!$cursorCreatedAt) {
                throw new ChatHttpException(
                    'Invalid message cursor.',
                    422
                );
            }
        }

        $sql = "
            SELECT
                m.id,
                m.sender_type,
                m.sender_id,
                CASE
                    WHEN m.sender_type = 'seller' THEN u.full_name
                    WHEN c.type = 'private' THEN COALESCE(
                        NULLIF(TRIM(CONCAT(a.first_name, ' ', a.last_name)), ''),
                        'Admin'
                    )
                    ELSE 'Admin'
                END AS sender_display_name,
                m.message_type,
                m.content,
                m.reply_to_message_id,
                m.deleted_at,
                m.edited_at,
                m.created_at
            FROM messages m
            INNER JOIN conversations c
                ON c.id = m.conversation_id
            LEFT JOIN users u
                ON m.sender_type = 'seller'
               AND u.id = m.sender_id
            LEFT JOIN admins a
                ON m.sender_type = 'admin'
               AND a.id = m.sender_id
            WHERE m.conversation_id = ?
        ";
        $params = [$conversationId];

        if ($cursorId !== null) {
            $sql .= "
              AND (
                  m.created_at < ?
                  OR (m.created_at = ? AND m.id < ?)
              )
            ";
            $params[] = $cursorCreatedAt;
            $params[] = $cursorCreatedAt;
            $params[] = $cursorId;
        }

        $sql .= "
            ORDER BY m.created_at DESC, m.id DESC
            LIMIT ?
        ";
        $params[] = $limit + 1;

        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $hasMore = count($rows) > $limit;
        if ($hasMore) {
            array_pop($rows);
        }

        $reactionMap = chatLoadMessageReactions(
            $pdo,
            array_column($rows, 'id'),
            $actor
        );
        foreach ($rows as &$row) {
            $row = chatNormalizeMessagePayload(
                $pdo,
                $row,
                $actor,
                $conversationId,
                $reactionMap
            );
        }
        unset($row);

        $messages = array_reverse($rows);
        $nextCursor = $hasMore && $messages
            ? (int) $messages[0]['id']
            : null;

        chatJsonResponse(
            true,
            'Messages loaded.',
            200,
            [
                'conversation_id' => (int) $conversationId,
                'messages' => $messages,
                'has_more' => $hasMore,
                'next_cursor' => $nextCursor
            ]
        );
    }

    if ($action === 'send') {
        chatRequireMethod('POST');
        chatRequireCsrf();

        $conversationId = chatPositiveInteger(
            $_POST['conversation_id'] ?? null,
            'conversation ID'
        );
        chatRequireConversationMembership($conversationId, $actor);

        $messageTypeInput = $_POST['message_type'] ?? 'text';

        if (!is_string($messageTypeInput)) {
            throw new ChatHttpException(
                'Invalid message type.',
                422
            );
        }

        $messageType = strtolower(trim($messageTypeInput));

        if ($messageType !== 'text') {
            throw new ChatHttpException(
                'Only text messages are supported.',
                422
            );
        }

        $content = $_POST['content'] ?? null;
        if (!is_string($content)) {
            throw new ChatHttpException('Invalid message content.', 422);
        }

        $content = trim($content);

        if (strtolower($content) === '/bookme') {
            if ($actor['actor_type'] !== 'admin') {
                throw new ChatHttpException(
                    'Only Admin can use the /bookme command.',
                    403
                );
            }

            $content = implode("\n", [
                'LALAMOVE_BOOKING_V1',
                'https://www.lalamove.com/en-ph/',
                'Block 25 Lot 2 Excess Emmanuel Bergado 2, Brgy. Dasmariñas, Dasmariñas, Cavite, Philippines',
                'MJ’s Kitchen (tapat)'
            ]);
        }

        if ($content === '') {
            throw new ChatHttpException(
                'Message content cannot be empty.',
                422
            );
        }

        if (!mb_check_encoding($content, 'UTF-8')) {
            throw new ChatHttpException(
                'Message content must be valid UTF-8 text.',
                422
            );
        }

        if (mb_strlen($content, 'UTF-8') > 4000) {
            throw new ChatHttpException(
                'Message content cannot exceed 4000 characters.',
                422
            );
        }

        $replyToMessageId = null;
        if (isset($_POST['reply_to_message_id'])) {
            $replyToMessageId = chatPositiveInteger(
                $_POST['reply_to_message_id'],
                'reply message ID'
            );

            global $pdo;
            $replyStmt = $pdo->prepare("
                SELECT id
                FROM messages
                WHERE id = ?
                  AND conversation_id = ?
                LIMIT 1
            ");
            $replyStmt->execute([$replyToMessageId, $conversationId]);
            if (!$replyStmt->fetchColumn()) {
                throw new ChatHttpException(
                    'The selected reply message does not belong to this conversation.',
                    422
                );
            }
        }

        global $pdo;

        $pdo->beginTransaction();

        try {
            $insertStmt = $pdo->prepare("
                INSERT INTO messages (
                    conversation_id,
                    sender_type,
                    sender_id,
                    message_type,
                    content,
                    reply_to_message_id
                )
                VALUES (?, ?, ?, 'text', ?, ?)
            ");
            $insertStmt->execute([
                $conversationId,
                $actor['actor_type'],
                $actor['actor_id'],
                $content,
                $replyToMessageId
            ]);

            $messageId = (int) $pdo->lastInsertId();
            $messageStmt = $pdo->prepare("
                SELECT
                    m.id,
                    m.sender_type,
                    m.sender_id,
                    m.message_type,
                    m.content,
                    m.reply_to_message_id,
                    m.deleted_at,
                    m.edited_at,
                    m.created_at,
                    CASE
                        WHEN m.sender_type = 'seller' THEN u.full_name
                        WHEN c.type = 'private' THEN COALESCE(
                            NULLIF(TRIM(CONCAT(a.first_name, ' ', a.last_name)), ''),
                            'Admin'
                        )
                        ELSE 'Admin'
                    END AS sender_display_name
                FROM messages m
                INNER JOIN conversations c
                    ON c.id = m.conversation_id
                LEFT JOIN users u
                    ON m.sender_type = 'seller'
                   AND u.id = m.sender_id
                LEFT JOIN admins a
                    ON m.sender_type = 'admin'
                   AND a.id = m.sender_id
                WHERE m.id = ?
                  AND m.conversation_id = ?
                LIMIT 1
            ");
            $messageStmt->execute([$messageId, $conversationId]);
            $message = $messageStmt->fetch(PDO::FETCH_ASSOC);

            if (!$message) {
                throw new RuntimeException(
                    'Inserted message could not be loaded.'
                );
            }

            $updateConversation = $pdo->prepare("
                UPDATE conversations
                SET updated_at = NOW()
                WHERE id = ?
            ");
            $updateConversation->execute([$conversationId]);
            $pdo->commit();
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }

            throw $e;
        }

        $realtimeDelivery = chatPublishPersistedMessage(
            $conversationId,
            $actor,
            $message
        );
        $responseMessage = $realtimeDelivery === 'published'
            ? 'Message saved and realtime event published.'
            : 'Message saved; realtime delivery is '
                . str_replace('_', ' ', $realtimeDelivery)
                . '.';

        chatJsonResponse(
            true,
            $responseMessage,
            201,
            [
                'persisted' => true,
                'realtime_delivery' => $realtimeDelivery,
                'message' => chatNormalizeMessagePayload(
                    $pdo,
                    $message,
                    $actor,
                    $conversationId
                )
            ]
        );
    }

    if ($action === 'edit') {
        chatRequireMethod('POST');
        chatRequireCsrf();

        $conversationId = chatPositiveInteger(
            $_POST['conversation_id'] ?? null,
            'conversation ID'
        );
        $messageId = chatPositiveInteger(
            $_POST['message_id'] ?? null,
            'message ID'
        );
        chatRequireConversationMembership($conversationId, $actor);

        $content = $_POST['content'] ?? null;
        if (!is_string($content)) {
            throw new ChatHttpException('Invalid message content.', 422);
        }

        $content = trim($content);
        if ($content === '') {
            throw new ChatHttpException('Message content cannot be empty.', 422);
        }
        if (!mb_check_encoding($content, 'UTF-8')) {
            throw new ChatHttpException('Message content must be valid UTF-8 text.', 422);
        }
        if (mb_strlen($content, 'UTF-8') > 4000) {
            throw new ChatHttpException('Message content cannot exceed 4000 characters.', 422);
        }

        global $pdo;
        $messageStmt = $pdo->prepare("
            SELECT id, sender_type, sender_id, deleted_at, edited_at, content
            FROM messages
            WHERE id = ?
              AND conversation_id = ?
            LIMIT 1
        ");
        $messageStmt->execute([$messageId, $conversationId]);
        $message = $messageStmt->fetch(PDO::FETCH_ASSOC);

        if (!$message) {
            throw new ChatHttpException('Message not found in this conversation.', 404);
        }

        if ($message['deleted_at'] !== null) {
            throw new ChatHttpException('This message was already deleted.', 409);
        }

        if (
            (string) $message['sender_type'] !== $actor['actor_type']
            || (int) $message['sender_id'] !== (int) $actor['actor_id']
        ) {
            throw new ChatHttpException('You can only edit your own messages.', 403);
        }

        $updateStmt = $pdo->prepare("
            UPDATE messages
            SET content = ?, edited_at = NOW()
            WHERE id = ?
              AND conversation_id = ?
              AND sender_type = ?
              AND sender_id = ?
              AND deleted_at IS NULL
        ");
        $updateStmt->execute([
            $content,
            $messageId,
            $conversationId,
            $actor['actor_type'],
            $actor['actor_id']
        ]);

        if ($updateStmt->rowCount() !== 1) {
            throw new ChatHttpException('Unable to update this message.', 409);
        }

        $updatedStmt = $pdo->prepare("
            SELECT
                m.id,
                m.sender_type,
                m.sender_id,
                m.message_type,
                m.content,
                m.reply_to_message_id,
                m.deleted_at,
                m.edited_at,
                m.created_at,
                CASE
                    WHEN m.sender_type = 'seller' THEN u.full_name
                    WHEN c.type = 'private' THEN COALESCE(
                        NULLIF(TRIM(CONCAT(a.first_name, ' ', a.last_name)), ''),
                        'Admin'
                    )
                    ELSE 'Admin'
                END AS sender_display_name
            FROM messages m
            INNER JOIN conversations c
                ON c.id = m.conversation_id
            LEFT JOIN users u
                ON m.sender_type = 'seller'
               AND u.id = m.sender_id
            LEFT JOIN admins a
                ON m.sender_type = 'admin'
               AND a.id = m.sender_id
            WHERE m.id = ?
              AND m.conversation_id = ?
            LIMIT 1
        ");
        $updatedStmt->execute([$messageId, $conversationId]);
        $updatedMessage = $updatedStmt->fetch(PDO::FETCH_ASSOC);

        if (!$updatedMessage) {
            throw new ChatHttpException('Updated message could not be loaded.', 500);
        }

        $realtimeDelivery = chatPublishConversationEvent(
            $conversationId,
            $actor,
            [
                'event' => 'message.edited',
                'message_id' => $messageId,
                'edited_at' => (string) $updatedMessage['edited_at']
            ]
        );

        chatJsonResponse(
            true,
            $realtimeDelivery === 'published'
                ? 'Message updated.'
                : 'Message updated; realtime delivery is ' . str_replace('_', ' ', $realtimeDelivery) . '.',
            200,
            [
                'message' => chatNormalizeMessagePayload(
                    $pdo,
                    $updatedMessage,
                    $actor,
                    $conversationId
                ),
                'realtime_delivery' => $realtimeDelivery
            ]
        );
    }

    if ($action === 'delete') {
        chatRequireMethod('POST');
        chatRequireCsrf();

        $conversationId = chatPositiveInteger(
            $_POST['conversation_id'] ?? null,
            'conversation ID'
        );
        $messageId = chatPositiveInteger(
            $_POST['message_id'] ?? null,
            'message ID'
        );
        chatRequireConversationMembership($conversationId, $actor);

        global $pdo;
        $messageStmt = $pdo->prepare("
            SELECT id, sender_type, sender_id, deleted_at
            FROM messages
            WHERE id = ?
              AND conversation_id = ?
            LIMIT 1
        ");
        $messageStmt->execute([$messageId, $conversationId]);
        $message = $messageStmt->fetch(PDO::FETCH_ASSOC);

        if (!$message) {
            throw new ChatHttpException('Message not found in this conversation.', 404);
        }

        if ($message['deleted_at'] !== null) {
            throw new ChatHttpException('This message was already deleted.', 409);
        }

        if (
            (string) $message['sender_type'] !== $actor['actor_type']
            || (int) $message['sender_id'] !== (int) $actor['actor_id']
        ) {
            throw new ChatHttpException('You can only delete your own messages.', 403);
        }

        $deleteStmt = $pdo->prepare("
            UPDATE messages
            SET deleted_at = NOW()
            WHERE id = ?
              AND conversation_id = ?
              AND sender_type = ?
              AND sender_id = ?
              AND deleted_at IS NULL
        ");
        $deleteStmt->execute([
            $messageId,
            $conversationId,
            $actor['actor_type'],
            $actor['actor_id']
        ]);

        if ($deleteStmt->rowCount() !== 1) {
            throw new ChatHttpException('Unable to delete this message.', 409);
        }

        $realtimeDelivery = chatPublishConversationEvent(
            $conversationId,
            $actor,
            [
                'event' => 'message.deleted',
                'message_id' => $messageId,
                'deleted_at' => date('Y-m-d H:i:s')
            ]
        );

        chatJsonResponse(
            true,
            $realtimeDelivery === 'published'
                ? 'Message deleted.'
                : 'Message deleted; realtime delivery is ' . str_replace('_', ' ', $realtimeDelivery) . '.',
            200,
            [
                'message_id' => $messageId,
                'deleted' => true,
                'realtime_delivery' => $realtimeDelivery
            ]
        );
    }

    if ($action === 'reaction') {
        chatRequireMethod('POST');
        chatRequireCsrf();

        $conversationId = chatPositiveInteger(
            $_POST['conversation_id'] ?? null,
            'conversation ID'
        );
        $messageId = chatPositiveInteger(
            $_POST['message_id'] ?? null,
            'message ID'
        );
        $reaction = trim((string) ($_POST['reaction'] ?? ''));
        chatRequireConversationMembership($conversationId, $actor);

        if (!in_array($reaction, chatMessageReactionOptions(), true)) {
            throw new ChatHttpException('Invalid reaction.', 422);
        }

        global $pdo;
        $messageStmt = $pdo->prepare("
            SELECT id, deleted_at
            FROM messages
            WHERE id = ?
              AND conversation_id = ?
            LIMIT 1
        ");
        $messageStmt->execute([$messageId, $conversationId]);
        $message = $messageStmt->fetch(PDO::FETCH_ASSOC);

        if (!$message) {
            throw new ChatHttpException('Message not found in this conversation.', 404);
        }

        if ($message['deleted_at'] !== null) {
            throw new ChatHttpException('Deleted messages cannot be reacted to.', 409);
        }

        $existingStmt = $pdo->prepare("
            SELECT id
            FROM message_reactions
            WHERE message_id = ?
              AND actor_type = ?
              AND actor_id = ?
              AND reaction = ?
            LIMIT 1
        ");
        $existingStmt->execute([
            $messageId,
            $actor['actor_type'],
            $actor['actor_id'],
            $reaction
        ]);

        if ($existingStmt->fetchColumn()) {
            $removeStmt = $pdo->prepare("
                DELETE FROM message_reactions
                WHERE message_id = ?
                  AND actor_type = ?
                  AND actor_id = ?
                  AND reaction = ?
            ");
            $removeStmt->execute([
                $messageId,
                $actor['actor_type'],
                $actor['actor_id'],
                $reaction
            ]);
            $actionState = 'removed';
        } else {
            $insertStmt = $pdo->prepare("
                INSERT INTO message_reactions (
                    message_id,
                    actor_type,
                    actor_id,
                    reaction
                )
                VALUES (?, ?, ?, ?)
            ");
            $insertStmt->execute([
                $messageId,
                $actor['actor_type'],
                $actor['actor_id'],
                $reaction
            ]);
            $actionState = 'added';
        }

        $realtimeDelivery = chatPublishConversationEvent(
            $conversationId,
            $actor,
            [
                'event' => 'message.reaction_updated',
                'message_id' => $messageId,
                'reaction' => $reaction,
                'action' => $actionState
            ]
        );

        chatJsonResponse(
            true,
            $realtimeDelivery === 'published'
                ? 'Reaction updated.'
                : 'Reaction updated; realtime delivery is ' . str_replace('_', ' ', $realtimeDelivery) . '.',
            200,
            [
                'message_id' => $messageId,
                'reaction' => $reaction,
                'action' => $actionState,
                'reactions' => chatMessageReactionSummary($pdo, $messageId, $actor),
                'realtime_delivery' => $realtimeDelivery
            ]
        );
    }

    if ($action === 'typing') {
        chatRequireMethod('POST');
        chatRequireCsrf();

        $conversationId = chatPositiveInteger(
            $_POST['conversation_id'] ?? null,
            'conversation ID'
        );
        chatRequireConversationMembership($conversationId, $actor);

        $state = $_POST['state'] ?? null;
        if (!is_string($state) || !in_array($state, ['started', 'stopped'], true)) {
            throw new ChatHttpException('Invalid typing state.', 422);
        }

        global $pdo;

        $realtimeDelivery = chatPublishConversationEvent(
            $conversationId,
            $actor,
            [
                'event' => 'typing.' . $state,
                'display_name' => $actor['actor_type'] === 'admin'
                    ? 'Admin'
                    : chatMessageActorDisplayName($pdo, $actor)
            ]
        );

        if ($realtimeDelivery !== 'published') {
            error_log(
                'Chat typing event could not be delivered: '
                . $realtimeDelivery
                . '.'
            );

            throw new ChatHttpException(
                'Typing status could not be delivered to this conversation.',
                503
            );
        }

        chatJsonResponse(
            true,
            'Typing state published.',
            200,
            ['realtime_delivery' => $realtimeDelivery]
        );
    }

    if ($action === 'read') {
        chatRequireMethod('POST');
        chatRequireCsrf();

        $conversationId = chatPositiveInteger(
            $_POST['conversation_id'] ?? null,
            'conversation ID'
        );
        $messageId = chatPositiveInteger(
            $_POST['message_id'] ?? null,
            'message ID'
        );
        chatRequireConversationMembership($conversationId, $actor);

        global $pdo;

        $messageStmt = $pdo->prepare("
            SELECT id
            FROM messages
            WHERE id = ?
              AND conversation_id = ?
            LIMIT 1
        ");
        $messageStmt->execute([
            $messageId,
            $conversationId
        ]);

        if (!$messageStmt->fetchColumn()) {
            throw new ChatHttpException(
                'Message not found in this conversation.',
                404
            );
        }

        $updateStmt = $pdo->prepare("
            UPDATE conversation_participants
            SET last_read_message_id = CASE
                WHEN last_read_message_id IS NULL
                     OR last_read_message_id < ?
                THEN ?
                ELSE last_read_message_id
            END
            WHERE conversation_id = ?
              AND actor_type = ?
              AND actor_id = ?
        ");
        $updateStmt->execute([
            $messageId,
            $messageId,
            $conversationId,
            $actor['actor_type'],
            $actor['actor_id']
        ]);

        $readStmt = $pdo->prepare("
            SELECT last_read_message_id
            FROM conversation_participants
            WHERE conversation_id = ?
              AND actor_type = ?
              AND actor_id = ?
            LIMIT 1
        ");
        $readStmt->execute([
            $conversationId,
            $actor['actor_type'],
            $actor['actor_id']
        ]);
        $lastReadMessageId = $readStmt->fetchColumn();

        chatJsonResponse(
            true,
            'Conversation marked as read.',
            200,
            [
                'conversation_id' => $conversationId,
                'last_read_message_id' =>
                    $lastReadMessageId === false
                        ? null
                        : (int) $lastReadMessageId
            ]
        );
    }

    throw new ChatHttpException('Invalid action.', 422);
} catch (ChatHttpException $e) {
    chatJsonResponse(false, $e->getMessage(), $e->httpStatus);
} catch (Throwable $e) {
    error_log('Chat messages endpoint failed: ' . $e->getMessage());
    chatJsonResponse(false, 'Unable to process the chat request.', 500);
}
