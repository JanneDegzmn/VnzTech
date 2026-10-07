<?php

ob_start();
ini_set('display_errors', '0');

require_once __DIR__ . '/chat_service.php';
require_once __DIR__ . '/chat_scaledrone.php';

try {
    $action = chatRequestAction();
    $actor = chatGetCurrentActor();

    if ($action === 'history') {
        chatRequireMethod('GET');

        $conversationId = chatPositiveInteger(
            $_GET['conversation_id'] ?? null,
            'conversation ID'
        );
        $conversation = chatRequireConversationMembership(
            $conversationId,
            $actor
        );

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
        $rows = $stmt->fetchAll();

        $hasMore = count($rows) > $limit;
        if ($hasMore) {
            array_pop($rows);
        }

        $messages = array_reverse($rows);
        $nextCursor = $hasMore && $messages
            ? (int) $messages[0]['id']
            : null;

        chatJsonResponse(
            true,
            'Messages loaded.',
            200,
            [
                'conversation_id' =>
                    (int) $conversation['conversation_id'],
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

        global $pdo;

        $pdo->beginTransaction();

        try {
            $insertStmt = $pdo->prepare("
                INSERT INTO messages (
                    conversation_id,
                    sender_type,
                    sender_id,
                    message_type,
                    content
                )
                VALUES (?, ?, ?, 'text', ?)
            ");
            $insertStmt->execute([
                $conversationId,
                $actor['actor_type'],
                $actor['actor_id'],
                $content
            ]);

            $messageId = (int) $pdo->lastInsertId();
            $messageStmt = $pdo->prepare("
                SELECT
                    id,
                    sender_type,
                    sender_id,
                    message_type,
                    content,
                    created_at
                FROM messages
                WHERE id = ?
                  AND conversation_id = ?
                LIMIT 1
            ");
            $messageStmt->execute([
                $messageId,
                $conversationId
            ]);
            $message = $messageStmt->fetch();

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
                'message' => $message
            ]
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
