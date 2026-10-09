<?php

require_once __DIR__ . '/database.php';

function chatLogAuthenticationTiming(string $operation, float $startedAt): void
{
    error_log(sprintf(
        'Chat authentication timing: operation=%s duration_ms=%.2f',
        $operation,
        (microtime(true) - $startedAt) * 1000
    ));
}

function chatPresenceSessionHash(): string
{
    $sessionId = session_id();
    if ($sessionId === '') {
        throw new RuntimeException('Authenticated session is unavailable for presence.');
    }

    return hash('sha256', $sessionId);
}

function chatRegisterPresenceSession(string $actorType, int $actorId): void
{
    global $pdo;

    $stmt = $pdo->prepare("
        INSERT INTO chat_presence_sessions (
            session_token_hash,
            actor_type,
            actor_id,
            heartbeat_at,
            ended_at
        )
        VALUES (?, ?, ?, NOW(), NULL)
        ON DUPLICATE KEY UPDATE
            actor_type = VALUES(actor_type),
            actor_id = VALUES(actor_id),
            heartbeat_at = NOW(),
            ended_at = NULL
    ");
    $stmt->execute([
        chatPresenceSessionHash(),
        $actorType,
        $actorId
    ]);
}

function chatHeartbeatPresenceSession(string $actorType, int $actorId): bool
{
    global $pdo;

    $sessionHash = chatPresenceSessionHash();
    $statusStmt = $pdo->prepare("
        SELECT (
            ended_at IS NULL
            AND heartbeat_at >= DATE_SUB(NOW(), INTERVAL 60 SECOND)
        ) AS was_online
        FROM chat_presence_sessions
        WHERE session_token_hash = ?
          AND actor_type = ?
          AND actor_id = ?
        LIMIT 1
    ");
    $statusStmt->execute([$sessionHash, $actorType, $actorId]);
    $wasOnline = (bool) $statusStmt->fetchColumn();

    $stmt = $pdo->prepare("
        INSERT INTO chat_presence_sessions (
            session_token_hash,
            actor_type,
            actor_id,
            heartbeat_at,
            ended_at
        )
        VALUES (?, ?, ?, NOW(), NULL)
        ON DUPLICATE KEY UPDATE
            actor_type = VALUES(actor_type),
            actor_id = VALUES(actor_id),
            heartbeat_at = NOW(),
            ended_at = NULL
    ");
    $stmt->execute([
        $sessionHash,
        $actorType,
        $actorId
    ]);

    return !$wasOnline;
}

function chatEndPresenceSession(string $actorType, int $actorId): bool
{
    global $pdo;

    $stmt = $pdo->prepare("
        UPDATE chat_presence_sessions
        SET heartbeat_at = NOW(),
            ended_at = NOW()
        WHERE session_token_hash = ?
          AND actor_type = ?
          AND actor_id = ?
          AND ended_at IS NULL
    ");
    $stmt->execute([
        chatPresenceSessionHash(),
        $actorType,
        $actorId
    ]);

    return $stmt->rowCount() > 0;
}

function chatActorHasActivePresence(string $actorType, int $actorId): bool
{
    global $pdo;

    $stmt = $pdo->prepare("
        SELECT 1
        FROM chat_presence_sessions
        WHERE actor_type = ?
          AND actor_id = ?
          AND ended_at IS NULL
          AND heartbeat_at >= DATE_SUB(NOW(), INTERVAL 60 SECOND)
        LIMIT 1
    ");
    $stmt->execute([$actorType, $actorId]);

    return (bool) $stmt->fetchColumn();
}

function chatBroadcastPresenceChange(
    string $actorType,
    int $actorId,
    string $state
): void {
    try {
        require_once __DIR__ . '/chat_scaledrone.php';

        global $pdo;
        $stmt = $pdo->prepare("
            SELECT conversation_id
            FROM conversation_participants
            WHERE actor_type = ?
              AND actor_id = ?
        ");
        $stmt->execute([$actorType, $actorId]);

        $actor = [
            'actor_type' => $actorType,
            'actor_id' => $actorId
        ];
        foreach ($stmt->fetchAll(PDO::FETCH_COLUMN) as $conversationId) {
            chatPublishConversationEvent(
                (int) $conversationId,
                $actor,
                ['event' => 'presence.updated', 'presence' => $state]
            );
        }
    } catch (Throwable $e) {
        error_log('Chat presence notification failed: ' . $e->getMessage());
    }
}
