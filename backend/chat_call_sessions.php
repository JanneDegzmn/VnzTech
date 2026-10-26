<?php

function chatCallParticipantMatches(
    array $call,
    string $prefix,
    array $actor
): bool {
    return $call[$prefix . '_type'] === $actor['actor_type']
        && (int) $call[$prefix . '_id'] === (int) $actor['actor_id'];
}

function chatCallDiagnosticState(array $call): array
{
    return [
        'status' => (string) $call['status'],
        'callerAcknowledged' => $call['caller_connected_at'] !== null,
        'calleeAcknowledged' => $call['callee_connected_at'] !== null
    ];
}

function chatCallSummaryContent(array $call, string $status): string
{
    $label = $call['call_mode'] === 'video' ? 'Video call' : 'Voice call';
    if ($status === 'completed') {
        $duration = (int) $call['duration_seconds'];
        $hours = intdiv($duration, 3600);
        $minutes = intdiv($duration % 3600, 60);
        $seconds = $duration % 60;
        $formattedDuration = $hours > 0
            ? sprintf('%02d:%02d:%02d', $hours, $minutes, $seconds)
            : sprintf('%02d:%02d', $minutes, $seconds);

        return $label . ' · ' . $formattedDuration;
    }

    return match ($status) {
        'missed' => 'Missed ' . strtolower($label),
        'declined' => $label . ' declined',
        'cancelled' => $label . ' cancelled',
        'busy' => $label . ' — participant was busy',
        default => $label . ' failed'
    };
}

function chatPersistCallEvent(
    int $conversationId,
    array $actor,
    array $peer,
    array $event
): array {
    global $pdo;

    if (
        ($actor['actor_type'] ?? null) !== 'seller'
        || ($peer['actor_type'] ?? null) !== 'seller'
    ) {
        throw new ChatHttpException(
            'Calls are only available between eligible Seller accounts.',
            403
        );
    }

    $pdo->beginTransaction();
    try {
        $inviteInserted = false;
        if ($event['event'] === 'call.invite') {
            $insertStmt = $pdo->prepare("
                INSERT IGNORE INTO chat_call_sessions (
                    call_id,
                    conversation_id,
                    caller_type,
                    caller_id,
                    callee_type,
                    callee_id,
                    call_mode,
                    status
                )
                VALUES (?, ?, ?, ?, ?, ?, ?, 'ringing')
            ");
            $insertStmt->execute([
                $event['call_id'],
                $conversationId,
                $actor['actor_type'],
                $actor['actor_id'],
                $peer['actor_type'],
                $peer['actor_id'],
                $event['mode']
            ]);
            $inviteInserted = $insertStmt->rowCount() === 1;
        }

        $callStmt = $pdo->prepare("
            SELECT
                *,
                invited_at > DATE_SUB(
                    CURRENT_TIMESTAMP(6),
                    INTERVAL 30 SECOND
                ) AS invite_is_fresh
            FROM chat_call_sessions
            WHERE call_id = ?
            FOR UPDATE
        ");
        $callStmt->execute([$event['call_id']]);
        $call = $callStmt->fetch(PDO::FETCH_ASSOC);
        if (
            !$call
            || (int) $call['conversation_id'] !== $conversationId
            || $call['caller_type'] !== 'seller'
            || $call['callee_type'] !== 'seller'
            || !(
                chatCallParticipantMatches($call, 'caller', $actor)
                && chatCallParticipantMatches($call, 'callee', $peer)
                || chatCallParticipantMatches($call, 'callee', $actor)
                    && chatCallParticipantMatches($call, 'caller', $peer)
            )
        ) {
            throw new ChatHttpException('Call session was not found.', 404);
        }

        if ($event['event'] === 'call.invite') {
            if (
                !chatCallInviteCanBeRelayed($call, $actor, $event['mode'])
            ) {
                throw new ChatHttpException('Call session is no longer available.', 409);
            }
            if (!$inviteInserted) {
                $pdo->commit();
                return [
                    'duplicate' => true,
                    'relay' => true,
                    'summary_message' => null,
                    'diagnostic_state' => chatCallDiagnosticState($call)
                ];
            }
            $pdo->commit();
            return [
                'duplicate' => false,
                'summary_message' => null,
                'diagnostic_state' => chatCallDiagnosticState($call)
            ];
        }

        if (in_array($call['status'], [
            'completed', 'missed', 'declined', 'cancelled', 'busy', 'failed'
        ], true)) {
            $retryAllowed = match ($event['event']) {
                'call.end' => in_array($call['status'], ['completed', 'failed'], true),
                'call.missed' => in_array($call['status'], ['missed', 'failed'], true),
                'call.decline' => $call['status'] === 'declined'
                    && chatCallParticipantMatches($call, 'callee', $actor),
                'call.cancel' => $call['status'] === 'cancelled'
                    && chatCallParticipantMatches($call, 'caller', $actor),
                'call.busy' => $call['status'] === 'busy'
                    && chatCallParticipantMatches($call, 'callee', $actor),
                'call.failed' => $call['status'] === 'failed',
                default => false
            };
            if (!$retryAllowed) {
                throw new ChatHttpException('This call has already ended.', 409);
            }
            $pdo->commit();
            return [
                'duplicate' => true,
                'relay' => true,
                'summary_message' => null,
                'diagnostic_state' => chatCallDiagnosticState($call)
            ];
        }

        $caller = chatCallParticipantMatches($call, 'caller', $actor);
        $callee = chatCallParticipantMatches($call, 'callee', $actor);
        $status = (string) $call['status'];
        $terminalStatus = null;

        switch ($event['event']) {
            case 'call.accept':
                if (!$callee || !in_array($status, ['ringing', 'accepted'], true)) {
                    throw new ChatHttpException('This call cannot be accepted.', 409);
                }
                if ($status === 'accepted') {
                    $pdo->commit();
                    return [
                        'duplicate' => true,
                        'relay' => true,
                        'summary_message' => null,
                        'diagnostic_state' => chatCallDiagnosticState($call)
                    ];
                }
                if ($status === 'ringing') {
                    $pdo->prepare("
                        UPDATE chat_call_sessions
                        SET status = 'accepted',
                            accepted_at = CURRENT_TIMESTAMP(6)
                        WHERE call_id = ?
                    ")->execute([$event['call_id']]);
                    $call['status'] = 'accepted';
                }
                break;

            case 'call.connected':
                if (!$caller && !$callee || !in_array($status, ['accepted', 'connected'], true)) {
                    throw new ChatHttpException('This call is not ready to connect.', 409);
                }
                $column = $caller ? 'caller_connected_at' : 'callee_connected_at';
                $connectedUpdate = $pdo->prepare("
                    UPDATE chat_call_sessions
                    SET $column = COALESCE($column, CURRENT_TIMESTAMP(6))
                    WHERE call_id = ?
                ");
                $connectedUpdate->execute([$event['call_id']]);
                $connectedStmt = $pdo->prepare("
                    SELECT caller_connected_at, callee_connected_at
                    FROM chat_call_sessions
                    WHERE call_id = ?
                ");
                $connectedStmt->execute([$event['call_id']]);
                $connected = $connectedStmt->fetch(PDO::FETCH_ASSOC);
                $call['caller_connected_at'] = $connected['caller_connected_at'];
                $call['callee_connected_at'] = $connected['callee_connected_at'];
                if ($connected['caller_connected_at'] !== null
                    && $connected['callee_connected_at'] !== null) {
                    $pdo->prepare("
                        UPDATE chat_call_sessions
                        SET status = 'connected',
                            connected_at = GREATEST(
                                caller_connected_at,
                                callee_connected_at
                            )
                        WHERE call_id = ?
                    ")->execute([$event['call_id']]);
                    $call['status'] = 'connected';
                }
                break;

            case 'call.offer':
                if (!$caller || !in_array($status, ['accepted', 'connected'], true)) {
                    throw new ChatHttpException('This call offer is not expected.', 409);
                }
                break;

            case 'call.answer':
                if (!$callee || !in_array($status, ['accepted', 'connected'], true)) {
                    throw new ChatHttpException('This call answer is not expected.', 409);
                }
                break;

            case 'call.ice':
                if (
                    (!$caller && !$callee)
                    || !in_array($status, ['accepted', 'connected'], true)
                ) {
                    throw new ChatHttpException('ICE is not expected for this call.', 409);
                }
                break;

            case 'call.decline':
                if (!$callee || $status !== 'ringing') {
                    throw new ChatHttpException('This call cannot be declined.', 409);
                }
                $terminalStatus = 'declined';
                break;

            case 'call.busy':
                if (!$callee || $status !== 'ringing') {
                    throw new ChatHttpException('This call cannot be marked busy.', 409);
                }
                $terminalStatus = 'busy';
                break;

            case 'call.cancel':
                if (!$caller || $status !== 'ringing') {
                    throw new ChatHttpException('This call cannot be cancelled.', 409);
                }
                $terminalStatus = 'cancelled';
                break;

            case 'call.missed':
                if (
                    (!$caller && !$callee)
                    || !in_array($status, ['ringing', 'accepted'], true)
                ) {
                    throw new ChatHttpException('This call has not timed out.', 409);
                }
                $timeoutStmt = $pdo->prepare("
                    SELECT invited_at <= DATE_SUB(CURRENT_TIMESTAMP(6), INTERVAL 30 SECOND)
                    FROM chat_call_sessions
                    WHERE call_id = ?
                ");
                $timeoutStmt->execute([$event['call_id']]);
                if (!(bool) $timeoutStmt->fetchColumn()) {
                    throw new ChatHttpException('This call has not timed out.', 409);
                }
                $terminalStatus = $status === 'ringing' ? 'missed' : 'failed';
                break;

            case 'call.failed':
                if (!$caller && !$callee) {
                    throw new ChatHttpException('This call cannot be failed.', 403);
                }
                $terminalStatus = $status === 'connected' ? 'completed' : 'failed';
                break;

            case 'call.end':
                if (!$caller && !$callee) {
                    throw new ChatHttpException('This call cannot be ended.', 403);
                }
                $terminalStatus = $status === 'connected' ? 'completed' : 'failed';
                break;

            default:
                throw new ChatHttpException('Unsupported call event.', 422);
        }

        $summaryMessage = null;
        if ($terminalStatus !== null) {
            $durationStmt = $pdo->prepare("
                UPDATE chat_call_sessions
                SET status = ?,
                    ended_at = CURRENT_TIMESTAMP(6),
                    duration_seconds = CASE
                        WHEN ? = 'completed'
                        THEN GREATEST(
                            0,
                            TIMESTAMPDIFF(SECOND, connected_at, CURRENT_TIMESTAMP(6))
                        )
                        ELSE 0
                    END
                WHERE call_id = ?
            ");
            $durationStmt->execute([
                $terminalStatus,
                $terminalStatus,
                $event['call_id']
            ]);
            $callStmt->execute([$event['call_id']]);
            $call = $callStmt->fetch(PDO::FETCH_ASSOC);
            $summaryContent = chatCallSummaryContent($call, $terminalStatus);
            $messageStmt = $pdo->prepare("
                INSERT INTO messages (
                    conversation_id,
                    sender_type,
                    sender_id,
                    message_type,
                    content
                )
                VALUES (?, ?, ?, 'system', ?)
            ");
            $messageStmt->execute([
                $conversationId,
                $call['caller_type'],
                $call['caller_id'],
                $summaryContent
            ]);
            $messageId = (int) $pdo->lastInsertId();
            $createdStmt = $pdo->prepare("
                SELECT created_at
                FROM messages
                WHERE id = ?
                LIMIT 1
            ");
            $createdStmt->execute([$messageId]);
            $summaryMessage = [
                'id' => $messageId,
                'created_at' => (string) $createdStmt->fetchColumn(),
                'message_type' => 'system'
            ];
            $pdo->prepare("
                UPDATE chat_call_sessions
                SET summary_message_id = ?
                WHERE call_id = ?
            ")->execute([$messageId, $event['call_id']]);
        }

        $pdo->commit();
        return [
            'duplicate' => false,
            'relay' => true,
            'summary_message' => $summaryMessage,
            'diagnostic_state' => chatCallDiagnosticState($call)
        ];
    } catch (Throwable $error) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        throw $error;
    }
}

function chatCallInviteCanBeRelayed(
    array $call,
    array $actor,
    string $mode
): bool {
    return chatCallParticipantMatches($call, 'caller', $actor)
        && $call['call_mode'] === $mode
        && $call['status'] === 'ringing'
        && (bool) ($call['invite_is_fresh'] ?? false);
}

function chatCallSignalShouldPublishDuplicate(array $callResult): bool
{
    return ($callResult['relay'] ?? false) === true;
}
