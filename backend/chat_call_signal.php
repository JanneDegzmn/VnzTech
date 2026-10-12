<?php

$callSignalRequestStartedAt = hrtime(true);
$callSignalTiming = [
    'event' => null,
    'correlationId' => null
];
register_shutdown_function(static function () use (
    &$callSignalTiming,
    $callSignalRequestStartedAt
): void {
    $callSignalTiming['totalMs'] = round(
        (hrtime(true) - $callSignalRequestStartedAt) / 1_000_000,
        2
    );
    $callSignalTiming['httpStatus'] = http_response_code();
    error_log(
        '[call-signal-timing] '
        . json_encode($callSignalTiming, JSON_UNESCAPED_SLASHES)
    );
});

ob_start();
ini_set('display_errors', '0');
header('Cache-Control: private, no-store');

require_once __DIR__ . '/chat_scaledrone.php';
require_once __DIR__ . '/chat_call_sessions.php';
$callSignalTiming['bootstrapMs'] = round(
    (hrtime(true) - $callSignalRequestStartedAt) / 1_000_000,
    2
);

try {
    $stageStartedAt = hrtime(true);
    chatRequireMethod('POST');
    chatRequireCsrf();
    $actor = chatGetCurrentActor();
    if (session_status() === PHP_SESSION_ACTIVE) {
        session_write_close();
    }
    $callSignalTiming['authenticationMs'] = round(
        (hrtime(true) - $stageStartedAt) / 1_000_000,
        2
    );

    $stageStartedAt = hrtime(true);
    $conversationId = chatPositiveInteger(
        $_POST['conversation_id'] ?? null,
        'conversation ID'
    );
    $peer = chatGetOneToOneConversationPeer($conversationId, $actor)['peer'];
    $callSignalTiming['peerAuthorizationMs'] = round(
        (hrtime(true) - $stageStartedAt) / 1_000_000,
        2
    );
    $callId = $_POST['call_id'] ?? null;
    if (
        !is_string($callId)
        || preg_match(
            '/\A[0-9a-f]{8}-[0-9a-f]{4}-[1-5][0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}\z/i',
            $callId
        ) !== 1
    ) {
        throw new ChatHttpException('Invalid call session.', 422);
    }

    $callEvent = $_POST['call_event'] ?? null;
    if (!is_string($callEvent)) {
        throw new ChatHttpException('Invalid call event.', 422);
    }
    $event = [
        'event' => strtolower(trim($callEvent)),
        'call_id' => strtolower($callId)
    ];
    $callSignalTiming['event'] = $event['event'];
    $callSignalTiming['correlationId'] = $event['call_id'];

    $stageStartedAt = hrtime(true);
    if ($event['event'] === 'call.invite') {
        $mode = $_POST['mode'] ?? null;
        if (!is_string($mode) || !in_array($mode, ['audio', 'video'], true)) {
            throw new ChatHttpException('Invalid call mode.', 422);
        }
        $event['mode'] = $mode;
    } elseif (in_array($event['event'], [
        'call.accept',
        'call.decline',
        'call.cancel',
        'call.busy',
        'call.end',
        'call.missed',
        'call.connected',
        'call.failed'
    ], true)) {
        // These call-state notifications carry no client-supplied identity.
    } elseif (in_array($event['event'], ['call.offer', 'call.answer'], true)) {
        $descriptionJson = $_POST['description'] ?? null;
        if (!is_string($descriptionJson) || strlen($descriptionJson) > 30000) {
            throw new ChatHttpException('Invalid call description.', 422);
        }
        try {
            $description = json_decode($descriptionJson, true, 8, JSON_THROW_ON_ERROR);
        } catch (JsonException $e) {
            throw new ChatHttpException('Invalid call description.', 422);
        }
        if (
            !is_array($description)
            || ($description['type'] ?? null) !== substr($event['event'], 5)
            || !is_string($description['sdp'] ?? null)
            || strlen($description['sdp']) > 24000
        ) {
            throw new ChatHttpException('Invalid call description.', 422);
        }
        $event['description'] = [
            'type' => $description['type'],
            'sdp' => $description['sdp']
        ];
    } elseif ($event['event'] === 'call.ice') {
        $candidateJson = $_POST['candidate'] ?? null;
        if (!is_string($candidateJson) || strlen($candidateJson) > 4096) {
            throw new ChatHttpException('Invalid ICE candidate.', 422);
        }
        try {
            $candidate = json_decode($candidateJson, true, 8, JSON_THROW_ON_ERROR);
        } catch (JsonException $e) {
            throw new ChatHttpException('Invalid ICE candidate.', 422);
        }
        if (
            !is_array($candidate)
            || !is_string($candidate['candidate'] ?? null)
            || strlen($candidate['candidate']) > 2048
            || (
                isset($candidate['sdpMid'])
                && !is_string($candidate['sdpMid'])
            )
            || (
                isset($candidate['sdpMLineIndex'])
                && !is_int($candidate['sdpMLineIndex'])
                && (
                    !is_string($candidate['sdpMLineIndex'])
                    || !ctype_digit($candidate['sdpMLineIndex'])
                )
            )
        ) {
            throw new ChatHttpException('Invalid ICE candidate.', 422);
        }
        $event['candidate'] = [
            'candidate' => $candidate['candidate'],
            'sdpMid' => $candidate['sdpMid'] ?? null,
            'sdpMLineIndex' => isset($candidate['sdpMLineIndex'])
                ? (int) $candidate['sdpMLineIndex']
                : null,
            'usernameFragment' => is_string($candidate['usernameFragment'] ?? null)
                ? $candidate['usernameFragment']
                : null
        ];
    } else {
        throw new ChatHttpException('Unsupported call event.', 422);
    }
    $callSignalTiming['validationMs'] = round(
        (hrtime(true) - $stageStartedAt) / 1_000_000,
        2
    );

    $event['to_actor_type'] = $peer['actor_type'];
    $event['to_actor_id'] = $peer['actor_id'];
    $stageStartedAt = hrtime(true);
    $callResult = chatPersistCallEvent(
        $conversationId,
        $actor,
        $peer,
        $event
    );
    $callSignalTiming['persistMs'] = round(
        (hrtime(true) - $stageStartedAt) / 1_000_000,
        2
    );
    if ($callResult['duplicate']) {
        if (chatCallSignalShouldPublishDuplicate($callResult)) {
            $stageStartedAt = hrtime(true);
            $delivery = chatPublishCallEvent($conversationId, $actor, $event);
            $callSignalTiming['publishMs'] = round(
                (hrtime(true) - $stageStartedAt) / 1_000_000,
                2
            );
            if ($delivery !== 'published') {
                chatJsonResponse(
                    false,
                    'Call signaling is temporarily unavailable.',
                    503
                );
            }
        }
        chatJsonResponse(true, 'Call event was already processed.', 200, [
            'call_id' => $event['call_id'],
            'duplicate' => true
        ]);
    }

    $stageStartedAt = hrtime(true);
    $delivery = chatPublishCallEvent($conversationId, $actor, $event);
    $callSignalTiming['publishMs'] = round(
        (hrtime(true) - $stageStartedAt) / 1_000_000,
        2
    );
    $summaryDelivery = null;
    if ($callResult['summary_message'] !== null) {
        $summaryDelivery = chatPublishConversationEvent(
            $conversationId,
            $actor,
            [
                'event' => 'message.created',
                'message_id' => $callResult['summary_message']['id'],
                'created_at' => $callResult['summary_message']['created_at'],
                'message_type' => 'system'
            ]
        );
    }
    if ($delivery !== 'published') {
        chatJsonResponse(
            false,
            $delivery === 'not_configured'
                ? 'Realtime calling is not configured.'
                : 'Call signaling is temporarily unavailable.',
            503
        );
    }

    chatJsonResponse(true, 'Call event delivered.', 200, [
        'call_id' => strtolower($callId),
        'summary_created' => $callResult['summary_message'] !== null,
        'summary_delivery' => $summaryDelivery
    ]);
} catch (ChatHttpException $e) {
    chatJsonResponse(false, $e->getMessage(), $e->httpStatus);
} catch (Throwable $e) {
    error_log('Call signaling endpoint failed.');
    chatJsonResponse(false, 'Unable to deliver the call event.', 500);
}
