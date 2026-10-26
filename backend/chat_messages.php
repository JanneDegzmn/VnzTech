<?php

ob_start();
ini_set('display_errors', '0');

require_once __DIR__ . '/chat_service.php';
require_once __DIR__ . '/chat_scaledrone.php';
require_once __DIR__ . '/chat_attachments.php';

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
                WHEN m.sender_type = 'guest' THEN 'Guest Seller'
                WHEN c.type = 'private' THEN COALESCE(
                    NULLIF(TRIM(CONCAT(a.first_name, ' ', a.last_name)), ''),
                    'Admin'
                )
                ELSE 'Admin'
            END AS sender_display_name,
            m.content,
            m.message_type,
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
    } elseif ($content === '' && in_array($reply['message_type'] ?? '', ['image', 'file', 'voice', 'video'], true)) {
        $content = match ($reply['message_type']) {
            'file' => 'PDF document',
            'voice' => 'Voice message',
            'video' => 'Video',
            default => 'Image'
        };
    }

    return [
        'id' => (int) $reply['id'],
        'sender_type' => $reply['sender_type'],
        'sender_id' => (int) $reply['sender_id'],
        'sender_display_name' => chatParticipantDisplayNameForViewer(
            $actor,
            (string) $reply['sender_type'],
            (string) ($reply['sender_display_name'] ?? '')
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

function chatLoadGlobalReadReceiptReaders(
    PDO $pdo,
    int $conversationId,
    array $messageIds,
    array $actor
): array {
    $messageIds = array_values(array_unique(array_filter(
        array_map('intval', $messageIds),
        static fn (int $messageId): bool => $messageId > 0
    )));
    if ($messageIds === []) {
        return [];
    }

    $placeholders = implode(',', array_fill(0, count($messageIds), '?'));
    $stmt = $pdo->prepare("
        SELECT
            m.id AS message_id,
            reader.actor_type,
            CASE
                WHEN reader.actor_type = 'seller' THEN COALESCE(
                    NULLIF(TRIM(u.full_name), ''),
                    'Seller'
                )
                WHEN reader.actor_type = 'guest' THEN 'Guest Seller'
                ELSE COALESCE(
                    NULLIF(TRIM(CONCAT(a.first_name, ' ', a.last_name)), ''),
                    'Admin'
                )
            END AS display_name
        FROM messages m
        INNER JOIN conversations c
            ON c.id = m.conversation_id
           AND c.type = 'global'
        INNER JOIN conversation_participants reader
            ON reader.conversation_id = m.conversation_id
           AND reader.last_read_message_id >= m.id
           AND NOT (
               reader.actor_type = m.sender_type
               AND reader.actor_id = m.sender_id
           )
        LEFT JOIN users u
            ON reader.actor_type = 'seller'
           AND u.id = reader.actor_id
        LEFT JOIN admins a
            ON reader.actor_type = 'admin'
           AND a.id = reader.actor_id
        WHERE m.conversation_id = ?
          AND m.id IN ($placeholders)
          AND m.sender_type = ?
          AND m.sender_id = ?
        ORDER BY m.id, reader.actor_type, display_name
    ");
    $stmt->execute(array_merge(
        [$conversationId],
        $messageIds,
        [$actor['actor_type'], $actor['actor_id']]
    ));

    $readersByMessageId = [];
    foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
        $messageId = (int) $row['message_id'];
        $readersByMessageId[$messageId][] = chatParticipantDisplayNameForViewer(
            $actor,
            (string) $row['actor_type'],
            (string) $row['display_name']
        );
    }

    return $readersByMessageId;
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
                WHEN mr.actor_type = 'guest' THEN 'Guest Seller'
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
        $displayName = chatParticipantDisplayNameForViewer(
            $actor,
            (string) $reactionRow['actor_type'],
            (string) ($reactionRow['display_name'] ?? '')
        );
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
    if ($actor['actor_type'] === 'guest') {
        return 'Guest Seller';
    }

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

function chatMessageActorProfilePicture(PDO $pdo, array $actor): string
{
    if ($actor['actor_type'] !== 'seller') {
        return '';
    }

    $stmt = $pdo->prepare("
        SELECT profile_picture
        FROM users
        WHERE id = ?
        LIMIT 1
    ");
    $stmt->execute([(int) $actor['actor_id']]);
    $profilePicture = (string) ($stmt->fetchColumn() ?: '');

    return preg_match(
        '/\Auploads\/profile_pictures\/[A-Za-z0-9][A-Za-z0-9._-]*\z/',
        $profilePicture
    ) === 1
        ? $profilePicture
        : '';
}

function chatMessagePayloadProfilePicture(array $message): string
{
    if (($message['sender_type'] ?? '') !== 'seller') {
        return '';
    }

    $profilePicture = (string) ($message['sender_profile_picture'] ?? '');
    return preg_match(
        '/\Auploads\/profile_pictures\/[A-Za-z0-9][A-Za-z0-9._-]*\z/',
        $profilePicture
    ) === 1
        ? $profilePicture
        : '';
}

function chatNormalizeMessagePayload(
    PDO $pdo,
    array $message,
    array $actor,
    int $conversationId,
    ?array $reactionMap = null
): array
{
    $attachmentName = '';
    if (
        empty($message['deleted_at'])
        && in_array($message['message_type'], ['image', 'file', 'voice'], true)
        && !empty($message['attachment_path'])
    ) {
        $attachmentExtension = $message['message_type'] === 'file'
            ? 'pdf'
            : strtolower((string) pathinfo(
                (string) $message['attachment_path'],
                PATHINFO_EXTENSION
            ));
        if (in_array($attachmentExtension, ['jpg', 'png', 'webp', 'pdf', 'ogg', 'webm', 'mp4'], true)) {
            $attachmentName = chatSanitizeAttachmentName(
                (string) ($message['attachment_name'] ?? ''),
                $attachmentExtension
            );
        }
    }

    $response = [
        'id' => (int) $message['id'],
        'conversation_id' => $conversationId,
        'sender_type' => $message['sender_type'],
        'sender_id' => (int) $message['sender_id'],
        'sender_display_name' => chatParticipantDisplayNameForViewer(
            $actor,
            (string) $message['sender_type'],
            (string) ($message['sender_display_name'] ?? '')
        ),
        'sender_profile_picture' => chatMessagePayloadProfilePicture($message),
        'message_type' => $message['message_type'],
        'content' => !empty($message['deleted_at'])
            ? 'This message was deleted'
            : (
                in_array($message['message_type'], ['voice', 'video'], true)
                && trim((string) ($message['content'] ?? '')) === ''
                    ? ($message['message_type'] === 'voice' ? 'Voice message' : 'Video')
                    : (string) ($message['content'] ?? '')
            ),
        'attachment_url' => in_array($message['message_type'], ['image', 'file', 'voice', 'video'], true)
            && empty($message['deleted_at'])
            && !empty($message['attachment_path'])
                ? '/VNZ-TECH/backend/chat_attachment.php?message_id='
                    . (int) $message['id']
                : null,
        'attachment_name' => $attachmentName,
        'attachment_size' => empty($message['deleted_at'])
            ? (int) ($message['attachment_size'] ?? 0)
            : 0,
        'created_at' => $message['created_at'],
        'edited_at' => $message['edited_at'] ?: null,
        'deleted_at' => $message['deleted_at'] ?: null,
        'reply_to_message_id' => $message['reply_to_message_id'] !== null && $message['reply_to_message_id'] !== ''
            ? (int) $message['reply_to_message_id']
            : null,
        'reactions' => $reactionMap[(int) $message['id']]
            ?? chatMessageReactionSummary($pdo, (int) $message['id'], $actor)
    ];

    if (
        $message['sender_type'] === $actor['actor_type']
        && (int) $message['sender_id'] === (int) $actor['actor_id']
    ) {
        $recipientCount = (int) ($message['receipt_recipient_count'] ?? 0);
        $deliveredCount = (int) ($message['delivered_count'] ?? 0);
        $readCount = (int) ($message['read_count'] ?? 0);
        $isGroupConversation = ($message['conversation_type'] ?? '') === 'global';
        $receiptStatus = 'sent';
        if (
            $readCount > 0
            && ($isGroupConversation || $readCount >= $recipientCount)
        ) {
            $receiptStatus = 'read';
        } elseif (
            $deliveredCount > 0
            && ($isGroupConversation || $deliveredCount >= $recipientCount)
        ) {
            $receiptStatus = 'delivered';
        }
        $response['receipt_status'] = $receiptStatus;
        $response['receipt_counts'] = [
            'delivered' => $deliveredCount,
            'read' => $readCount,
            'recipients' => $recipientCount
        ];
    }

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
        $conversationContext = chatRequireConversationMembership(
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
                u.profile_picture AS sender_profile_picture,
                CASE
                    WHEN m.sender_type = 'seller' THEN u.full_name
                    WHEN m.sender_type = 'guest' THEN 'Guest Seller'
                    WHEN c.type = 'private' THEN COALESCE(
                        NULLIF(TRIM(CONCAT(a.first_name, ' ', a.last_name)), ''),
                        'Admin'
                    )
                    ELSE 'Admin'
                END AS sender_display_name,
                m.message_type,
                m.content,
                m.attachment_path,
                m.attachment_name,
                m.attachment_size,
                m.reply_to_message_id,
                m.deleted_at,
                m.edited_at,
                m.created_at,
                c.type AS conversation_type,
                (
                    SELECT COUNT(*)
                    FROM conversation_participants recipient
                    WHERE recipient.conversation_id = m.conversation_id
                      AND NOT (
                          recipient.actor_type = m.sender_type
                          AND recipient.actor_id = m.sender_id
                      )
                ) AS receipt_recipient_count,
                (
                    SELECT COUNT(*)
                    FROM message_deliveries delivery
                    WHERE delivery.message_id = m.id
                ) AS delivered_count,
                (
                    SELECT COUNT(*)
                    FROM conversation_participants reader
                    WHERE reader.conversation_id = m.conversation_id
                      AND reader.last_read_message_id >= m.id
                      AND NOT (
                          reader.actor_type = m.sender_type
                          AND reader.actor_id = m.sender_id
                      )
                ) AS read_count
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
        $ownMessageIds = [];
        if ($conversationContext['conversation_type'] === 'global') {
            foreach ($rows as $row) {
                if (
                    $row['sender_type'] === $actor['actor_type']
                    && (int) $row['sender_id'] === (int) $actor['actor_id']
                ) {
                    $ownMessageIds[] = (int) $row['id'];
                }
            }
        }
        $readersByMessageId = chatLoadGlobalReadReceiptReaders(
            $pdo,
            $conversationId,
            $ownMessageIds,
            $actor
        );
        foreach ($rows as &$row) {
            $normalized = chatNormalizeMessagePayload(
                $pdo,
                $row,
                $actor,
                $conversationId,
                $reactionMap
            );
            if (isset($readersByMessageId[(int) $row['id']])) {
                $normalized['read_receipt_readers'] =
                    $readersByMessageId[(int) $row['id']];
            }
            $row = $normalized;
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

        if (!in_array($messageType, ['text', 'image', 'file', 'voice', 'video'], true)) {
            throw new ChatHttpException(
                'Unsupported message type.',
                422
            );
        }

        $content = $_POST['content'] ?? '';
        if (!is_string($content)) {
            throw new ChatHttpException('Invalid message content.', 422);
        }

        $content = trim($content);
        $attachmentFile = $_FILES['attachment'] ?? null;
        $hasAttachment = is_array($attachmentFile)
            && ($attachmentFile['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_NO_FILE;
        if (
            (in_array($messageType, ['image', 'file', 'voice', 'video'], true)) !== $hasAttachment
        ) {
            throw new ChatHttpException(
                'Select an attachment that matches the selected message type.',
                422
            );
        }

        if ($messageType === 'text' && strtolower($content) === '/bookme') {
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

        if ($content === '' && !$hasAttachment) {
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

        $storedAttachment = $hasAttachment
            ? match ($messageType) {
                'image' => chatStoreImageAttachment($attachmentFile),
                'file' => chatStorePdfAttachment($attachmentFile),
                'voice' => chatStoreVoiceAttachment($attachmentFile),
                'video' => chatStoreVideoAttachment($attachmentFile)
            }
            : null;

        try {
            $pdo->beginTransaction();
            $insertStmt = $pdo->prepare("
                INSERT INTO messages (
                    conversation_id,
                    sender_type,
                    sender_id,
                    message_type,
                    content,
                    attachment_path,
                    attachment_name,
                    attachment_size,
                    reply_to_message_id
                )
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
            ");
            $insertStmt->execute([
                $conversationId,
                $actor['actor_type'],
                $actor['actor_id'],
                $messageType,
                $content,
                $storedAttachment['path'] ?? null,
                $storedAttachment['name'] ?? null,
                $storedAttachment['size'] ?? null,
                $replyToMessageId
            ]);

            $messageId = (int) $pdo->lastInsertId();
            $messageStmt = $pdo->prepare("
                SELECT
                    m.id,
                    m.sender_type,
                    m.sender_id,
                    u.profile_picture AS sender_profile_picture,
                    m.message_type,
                    m.content,
                    m.attachment_path,
                    m.attachment_name,
                    m.attachment_size,
                    m.reply_to_message_id,
                    m.deleted_at,
                    m.edited_at,
                    m.created_at,
                    CASE
                        WHEN m.sender_type = 'seller' THEN u.full_name
                        WHEN m.sender_type = 'guest' THEN 'Guest Seller'
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

            $normalizedMessage = chatNormalizeMessagePayload(
                $pdo,
                $message,
                $actor,
                $conversationId
            );

            $pdo->commit();
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            if ($storedAttachment !== null && is_file($storedAttachment['absolute_path'])) {
                @unlink($storedAttachment['absolute_path']);
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
                'message' => $normalizedMessage
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
            SELECT id, sender_type, sender_id, message_type, deleted_at, edited_at, content
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

        if ($message['message_type'] === 'system') {
            throw new ChatHttpException('Call summaries cannot be edited.', 409);
        }

        if ($message['message_type'] === 'voice') {
            throw new ChatHttpException('Voice messages cannot be edited.', 409);
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
                u.profile_picture AS sender_profile_picture,
                m.message_type,
                m.content,
                m.attachment_path,
                m.attachment_name,
                m.attachment_size,
                m.reply_to_message_id,
                m.deleted_at,
                m.edited_at,
                m.created_at,
                CASE
                    WHEN m.sender_type = 'seller' THEN u.full_name
                    WHEN m.sender_type = 'guest' THEN 'Guest Seller'
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
            SELECT id, sender_type, sender_id, message_type, deleted_at
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

        if ($message['message_type'] === 'system') {
            throw new ChatHttpException('Call summaries cannot be deleted.', 409);
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
            SELECT id, message_type, deleted_at
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

        if ($message['message_type'] === 'system') {
            throw new ChatHttpException('Call summaries cannot be reacted to.', 409);
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
                    : chatMessageActorDisplayName($pdo, $actor),
                'profile_picture' => chatMessageActorProfilePicture($pdo, $actor)
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
        $readCursorAdvanced = $updateStmt->rowCount() > 0;

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
        if ($readCursorAdvanced) {
            chatPublishConversationEvent(
                $conversationId,
                $actor,
                [
                    'event' => 'message.receipts_updated',
                    'last_read_message_id' => (int) $lastReadMessageId
                ]
            );
        }

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

    if ($action === 'delivered') {
        chatRequireMethod('POST');
        chatRequireCsrf();

        $conversationId = chatPositiveInteger(
            $_POST['conversation_id'] ?? null,
            'conversation ID'
        );
        $messageIdsInput = $_POST['message_ids'] ?? null;
        if (is_string($messageIdsInput)) {
            $messageIdsInput = explode(',', $messageIdsInput);
        }
        if (!is_array($messageIdsInput) || count($messageIdsInput) > 30) {
            throw new ChatHttpException('Invalid delivery receipt list.', 422);
        }
        $messageIds = [];
        foreach ($messageIdsInput as $messageIdInput) {
            $messageIds[] = chatPositiveInteger(
                $messageIdInput,
                'message ID'
            );
        }
        $messageIds = array_values(array_unique($messageIds));
        chatRequireConversationMembership($conversationId, $actor);
        if ($messageIds === []) {
            chatJsonResponse(true, 'No delivery receipts to record.', 200, [
                'recorded_count' => 0
            ]);
        }

        global $pdo;
        $pdo->beginTransaction();
        try {
            $placeholders = implode(',', array_fill(0, count($messageIds), '?'));
            $messageStmt = $pdo->prepare("
                SELECT id, sender_type, sender_id
                FROM messages
                WHERE conversation_id = ?
                  AND id IN ($placeholders)
            ");
            $messageStmt->execute(array_merge([$conversationId], $messageIds));
            $messages = $messageStmt->fetchAll(PDO::FETCH_ASSOC);
            if (count($messages) !== count($messageIds)) {
                throw new ChatHttpException(
                    'A message was not found in this conversation.',
                    404
                );
            }

            $incomingIds = [];
            foreach ($messages as $message) {
                if (
                    $message['sender_type'] !== $actor['actor_type']
                    || (int) $message['sender_id'] !== (int) $actor['actor_id']
                ) {
                    $incomingIds[] = (int) $message['id'];
                }
            }

            $insertStmt = $pdo->prepare("
                INSERT INTO message_deliveries (
                    message_id,
                    recipient_type,
                    recipient_id
                )
                VALUES (?, ?, ?)
                ON DUPLICATE KEY UPDATE message_id = VALUES(message_id)
            ");
            $newlyAcknowledged = [];
            foreach ($incomingIds as $incomingId) {
                $insertStmt->execute([
                    $incomingId,
                    $actor['actor_type'],
                    $actor['actor_id']
                ]);
                if ($insertStmt->rowCount() === 1) {
                    $newlyAcknowledged[] = $incomingId;
                }
            }
            $pdo->commit();
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            throw $e;
        }

        if ($newlyAcknowledged !== []) {
            chatPublishConversationEvent(
                $conversationId,
                $actor,
                [
                    'event' => 'message.receipts_updated',
                    'message_ids' => $newlyAcknowledged
                ]
            );
        }

        chatJsonResponse(true, 'Delivery receipts recorded.', 200, [
            'recorded_count' => count($newlyAcknowledged)
        ]);
    }

    throw new ChatHttpException('Invalid action.', 422);
} catch (ChatHttpException $e) {
    chatJsonResponse(false, $e->getMessage(), $e->httpStatus);
} catch (Throwable $e) {
    error_log('Chat messages endpoint failed: ' . $e->getMessage());
    chatJsonResponse(false, 'Unable to process the chat request.', 500);
}
