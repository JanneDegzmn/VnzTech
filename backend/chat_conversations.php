<?php

ob_start();
ini_set('display_errors', '0');

require_once __DIR__ . '/chat_service.php';

try {
    $action = chatRequestAction();
    $actor = chatGetCurrentActor();

    if ($action === 'open') {
        chatRequireMethod('POST');
        chatRequireCsrf();

        $conversationType = $_POST['conversation_type'] ?? 'transaction';
        if (!is_string($conversationType)) {
            throw new ChatHttpException('Invalid conversation type.', 422);
        }
        $conversationType = strtolower(trim($conversationType));

        if ($conversationType === 'transaction') {
            $transactionId = chatPositiveInteger(
                $_POST['transaction_id'] ?? null,
                'transaction ID'
            );
            $conversation = getOrCreateTransactionConversation($transactionId);
        } elseif ($conversationType === 'global') {
            $conversation = chatGetOrCreateGlobalConversation($actor);
        } elseif ($conversationType === 'private') {
            $targetType = $_POST['target_type'] ?? null;
            if (!is_string($targetType)) {
                throw new ChatHttpException('Invalid participant type.', 422);
            }
            $targetId = chatPositiveInteger(
                $_POST['target_id'] ?? null,
                'participant ID'
            );
            $conversation = chatGetOrCreatePrivateConversation(
                $actor,
                strtolower(trim($targetType)),
                $targetId
            );
        } else {
            throw new ChatHttpException('Invalid conversation type.', 422);
        }

        chatJsonResponse(
            true,
            'Conversation opened.',
            200,
            $conversation
        );
    }

    if ($action === 'contacts') {
        chatRequireMethod('GET');
        $category = $_GET['category'] ?? null;
        if (!is_string($category)) {
            throw new ChatHttpException('Invalid contact category.', 422);
        }

        $contacts = chatListPrivateContacts(
            $actor,
            strtolower(trim($category))
        );
        chatJsonResponse(
            true,
            'Contacts loaded.',
            200,
            ['contacts' => $contacts]
        );
    }

    if ($action === 'list') {
        chatRequireMethod('GET');

        global $pdo;

        $sql = "
            SELECT
                c.id AS conversation_id,
                c.type AS conversation_type,
                c.transaction_id,
                t.transaction_reference,
                c.updated_at
            FROM conversations c
            INNER JOIN conversation_participants current_participant
                ON current_participant.conversation_id = c.id
               AND current_participant.actor_type = ?
               AND current_participant.actor_id = ?
            LEFT JOIN transactions t
                ON t.id = c.transaction_id
            LEFT JOIN submissions s
                ON s.id = t.submission_id
            LEFT JOIN offers o
                ON o.id = t.offer_id
            WHERE c.type IN ('transaction', 'private', 'global')
              AND (
                  c.type <> 'transaction'
                  OR (
                      ? = 'seller'
                      AND s.user_id = ?
                  )
                  OR (
                      ? = 'admin'
                      AND (
                          t.processed_by_admin_id = ?
                          OR o.created_by_admin_id = ?
                          OR s.assigned_admin_id = ?
                      )
                  )
              )
        ";
        $sql .= ' ORDER BY c.updated_at DESC, c.id DESC';

        $stmt = $pdo->prepare($sql);
        $stmt->execute([
            $actor['actor_type'],
            $actor['actor_id'],
            $actor['actor_type'],
            $actor['actor_id'],
            $actor['actor_type'],
            $actor['actor_id'],
            $actor['actor_id'],
            $actor['actor_id']
        ]);
        $conversations = $stmt->fetchAll();
        $authorizedConversations = [];

        foreach ($conversations as $conversation) {
            try {
                $context = chatRequireConversationMembership(
                    (int) $conversation['conversation_id'],
                    $actor
                );
            } catch (ChatHttpException $e) {
                if (in_array($e->httpStatus, [403, 404, 409], true)) {
                    continue;
                }

                throw $e;
            }

            $conversationId = (int) $conversation['conversation_id'];
            $unreadCount = chatCountUnreadMessages(
                $conversationId,
                $actor
            );

            $conversationData = [
                'conversation_id' => $conversationId,
                'conversation_type' => $conversation['conversation_type'],
                'transaction_id' => $conversation['transaction_id'] === null
                    ? null
                    : (int) $conversation['transaction_id'],
                'unread_count' => $unreadCount,
                'updated_at' => $conversation['updated_at']
            ];

            if ($conversation['conversation_type'] === 'transaction') {
                $conversationData['transaction_reference'] =
                    $context['transaction_reference'];
                $conversationData['transaction_status'] =
                    $context['transaction_status'];
                $conversationData['display_name'] =
                    $actor['actor_type'] === 'admin'
                        ? 'SELLER - '
                            . (trim((string) ($context['seller_full_name'] ?? '')) ?: 'Seller')
                        : 'ADMIN';
            } else {
                $conversationData['display_name'] =
                    $context['display_name'];
            }
            $conversationData['presence'] = chatGetConversationPresence(
                $conversationId,
                $actor
            );

            $authorizedConversations[] = $conversationData;
        }

        chatJsonResponse(
            true,
            'Authorized conversations loaded.',
            200,
            ['conversations' => $authorizedConversations]
        );
    }

    throw new ChatHttpException('Invalid action.', 422);
} catch (ChatHttpException $e) {
    chatJsonResponse(false, $e->getMessage(), $e->httpStatus);
} catch (Throwable $e) {
    error_log('Chat conversations endpoint failed: ' . $e->getMessage());
    chatJsonResponse(false, 'Unable to process the chat request.', 500);
}

function chatCountUnreadMessages(int $conversationId, array $actor): int
{
    global $pdo;

    $stmt = $pdo->prepare("
        SELECT COUNT(*)
        FROM messages m
        LEFT JOIN conversation_participants p
            ON p.conversation_id = m.conversation_id
           AND p.actor_type = ?
           AND p.actor_id = ?
        WHERE m.conversation_id = ?
          AND NOT (
              m.sender_type = ?
              AND m.sender_id = ?
          )
          AND (
              p.last_read_message_id IS NULL
              OR m.id > p.last_read_message_id
          )
    ");
    $stmt->execute([
        $actor['actor_type'],
        $actor['actor_id'],
        $conversationId,
        $actor['actor_type'],
        $actor['actor_id']
    ]);

    return (int) $stmt->fetchColumn();
}
