<?php

ob_start();
ini_set('display_errors', '0');

require_once __DIR__ . '/chat_service.php';

try {
    $action = chatRequestAction();
    $actor = chatGetCurrentActor();

    if ($action === 'revoke_guest_access') {
        chatRequireMethod('POST');
        chatRequireCsrf();
        if (
            $actor['actor_type'] !== 'admin'
            || !hasPermission('manage_offers')
        ) {
            throw new ChatHttpException('Unauthorized.', 403);
        }

        $conversationId = chatPositiveInteger(
            $_POST['conversation_id'] ?? null,
            'conversation ID'
        );
        $conversation = chatRequireConversationMembership(
            $conversationId,
            $actor
        );
        if (
            $conversation['conversation_type'] !== 'private'
            || $conversation['submission_id'] === null
        ) {
            throw new ChatHttpException('This is not a guest submission conversation.', 422);
        }

        global $pdo;
        $revokeStmt = $pdo->prepare("
            UPDATE guest_chat_identities
            SET revoked_at = COALESCE(revoked_at, NOW())
            WHERE submission_id = ?
        ");
        $revokeStmt->execute([(int) $conversation['submission_id']]);
        if ($revokeStmt->rowCount() < 1) {
            throw new ChatHttpException('Guest chat access is already unavailable.', 409);
        }

        chatJsonResponse(true, 'Guest chat access was revoked.', 200);
    }

    if ($action === 'open') {
        chatRequireMethod('POST');
        chatRequireCsrf();

        $conversationType = $_POST['conversation_type'] ?? 'transaction';
        if (!is_string($conversationType)) {
            throw new ChatHttpException('Invalid conversation type.', 422);
        }
        $conversationType = strtolower(trim($conversationType));
        if (
            $actor['actor_type'] === 'guest'
            && $conversationType !== 'transaction'
        ) {
            throw new ChatHttpException('Unauthorized.', 403);
        }

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
        if ($actor['actor_type'] === 'guest') {
            throw new ChatHttpException('Unauthorized.', 403);
        }
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

        $submissionIdExpression = chatGuestSubmissionSchemaAvailable()
            ? 'c.submission_id'
            : 'NULL';
        $sql = "
            SELECT
                c.id AS conversation_id,
                c.type AS conversation_type,
                c.transaction_id,
                $submissionIdExpression AS submission_id,
                t.transaction_reference,
                s.reference_number AS submission_reference,
                s.seller_name AS submission_seller_name,
                (
                    SELECT CASE
                        WHEN m.deleted_at IS NOT NULL THEN 'Message deleted'
                        ELSE LEFT(m.content, 120)
                    END
                    FROM messages m
                    WHERE m.conversation_id = c.id
                    ORDER BY m.created_at DESC, m.id DESC
                    LIMIT 1
                ) AS last_message,
                c.updated_at
            FROM conversations c
            INNER JOIN conversation_participants current_participant
                ON current_participant.conversation_id = c.id
               AND current_participant.actor_type = ?
               AND current_participant.actor_id = ?
            LEFT JOIN transactions t
                ON t.id = c.transaction_id
                OR (
                    c.type = 'private'
                    AND $submissionIdExpression IS NOT NULL
                    AND t.submission_id = $submissionIdExpression
                )
            LEFT JOIN submissions s
                ON s.id = COALESCE(t.submission_id, $submissionIdExpression)
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
                'conversation_class' => $context['conversation_class'],
                'transaction_id' => isset($context['transaction_id'])
                    ? (int) $context['transaction_id']
                    : ($conversation['transaction_id'] === null
                        ? null
                        : (int) $conversation['transaction_id']),
                'guest_submission' => !empty($context['guest_submission']),
                'claimed_submission' => !empty($context['claimed_submission']),
                'unread_count' => $unreadCount,
                'updated_at' => $conversation['updated_at']
            ];
            if (isset($context['submission_reference'])) {
                $conversationData['submission_reference'] =
                    $context['submission_reference'];
                if (!empty($context['guest_submission'])) {
                    $conversationData['guest_seller_name'] =
                        $context['submission_seller_name'] !== ''
                            ? $context['submission_seller_name']
                            : 'Guest Seller';
                }
                $conversationData['last_message'] =
                    (string) ($conversation['last_message'] ?? '');
            }
            $conversationData['call_eligible'] = false;
            if (in_array($conversation['conversation_type'], ['transaction', 'private'], true)) {
                try {
                    chatGetOneToOneConversationPeer($conversationId, $actor);
                    $conversationData['call_eligible'] = true;
                } catch (ChatHttpException $e) {
                    if (!in_array($e->httpStatus, [403, 404, 409], true)) {
                        throw $e;
                    }
                }
            }

            if (
                in_array(
                    $context['conversation_class'],
                    ['transaction', 'claimed_submission'],
                    true
                )
            ) {
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
