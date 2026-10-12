<?php

require_once __DIR__ . '/chat_service.php';

function chatScaledroneConfig(): ?array
{
    $channelId = getenv('SCALEDRONE_CHANNEL_ID');
    $secretKey = getenv('SCALEDRONE_SECRET');

    if ($channelId === false && $secretKey === false) {
        $localConfigPath = __DIR__ . '/scaledrone_config.local.php';

        if (!is_file($localConfigPath)) {
            return null;
        }

        $localConfig = require $localConfigPath;

        if (!is_array($localConfig)) {
            throw new RuntimeException(
                'Scaledrone local configuration must return an array.'
            );
        }

        $channelId = $localConfig['channel_id'] ?? null;
        $secretKey = $localConfig['secret_key'] ?? null;
    }

    if (
        !is_string($channelId)
        || trim($channelId) === ''
        || !is_string($secretKey)
        || trim($secretKey) === ''
    ) {
        return null;
    }

    $channelId = trim($channelId);
    $secretKey = trim($secretKey);

    if (!preg_match('/\A[A-Za-z0-9_-]{1,128}\z/', $channelId)) {
        throw new RuntimeException('Scaledrone channel ID is invalid.');
    }

    return [
        'channel_id' => $channelId,
        'secret_key' => $secretKey
    ];
}

function chatScaledroneBase64Url(string $value): string
{
    return rtrim(
        strtr(base64_encode($value), '+/', '-_'),
        '='
    );
}

function chatScaledroneSignJwt(array $claims, string $secretKey): string
{
    $header = chatScaledroneBase64Url(json_encode(
        [
            'alg' => 'HS256',
            'typ' => 'JWT'
        ],
        JSON_THROW_ON_ERROR
    ));

    $payload = chatScaledroneBase64Url(json_encode(
        $claims,
        JSON_THROW_ON_ERROR
    ));

    $unsignedToken = $header . '.' . $payload;
    $signature = hash_hmac(
        'sha256',
        $unsignedToken,
        $secretKey,
        true
    );

    return $unsignedToken . '.' . chatScaledroneBase64Url($signature);
}

function chatScaledroneRoomName(
    int $transactionId,
    string $secretKey
): string {
    $roomDigest = hash_hmac(
        'sha256',
        'transaction:' . $transactionId,
        $secretKey
    );

    return 'observable-vnz-tx-' . substr($roomDigest, 0, 32);
}

function chatScaledroneConversationRoomName(
    int $conversationId,
    string $secretKey
): string {
    $roomDigest = hash_hmac(
        'sha256',
        'conversation:' . $conversationId,
        $secretKey
    );

    return 'observable-vnz-chat-' . substr($roomDigest, 0, 32);
}

function chatScaledroneCallRoomName(
    int $conversationId,
    string $secretKey
): string {
    $roomDigest = hash_hmac(
        'sha256',
        'call-conversation:' . $conversationId,
        $secretKey
    );

    return 'observable-vnz-call-' . substr($roomDigest, 0, 32);
}

function chatScaledroneCallInboxRoomName(
    string $actorType,
    int $actorId,
    string $secretKey
): string {
    $roomDigest = hash_hmac(
        'sha256',
        'call-inbox:' . $actorType . ':' . $actorId,
        $secretKey
    );

    return 'observable-vnz-call-inbox-' . substr($roomDigest, 0, 32);
}

function chatGetAuthorizedCallInbox(array $actor): array
{
    if ($actor['actor_type'] === 'guest') {
        throw new ChatHttpException('Calls are unavailable for guest conversations.', 403);
    }

    $config = chatScaledroneConfig();
    if ($config === null) {
        throw new ChatHttpException('Realtime messaging is not configured.', 503);
    }

    return [
        'channel_id' => $config['channel_id'],
        'secret_key' => $config['secret_key'],
        'room' => chatScaledroneCallInboxRoomName(
            $actor['actor_type'],
            (int) $actor['actor_id'],
            $config['secret_key']
        )
    ];
}

function chatGetAuthorizedScaledroneRoom(
    int $conversationId,
    array $actor
): array {
    $conversation = chatRequireConversationMembership(
        $conversationId,
        $actor
    );

    $config = chatScaledroneConfig();

    if ($config === null) {
        throw new ChatHttpException(
            'Realtime messaging is not configured.',
            503
        );
    }

    $room = $conversation['conversation_type'] === 'transaction'
        ? chatScaledroneRoomName(
            (int) $conversation['transaction_id'],
            $config['secret_key']
        )
        : chatScaledroneConversationRoomName(
            $conversationId,
            $config['secret_key']
        );

    return [
        'conversation_id' => $conversationId,
        'conversation_type' => $conversation['conversation_type'],
        'transaction_id' => $conversation['transaction_id'] === null
            ? null
            : (int) $conversation['transaction_id'],
        'guest_submission' => !empty($conversation['guest_submission']),
        'channel_id' => $config['channel_id'],
        'secret_key' => $config['secret_key'],
        'room' => $room
    ];
}

function chatGetAuthorizedScaledroneCallRoom(
    int $conversationId,
    array $actor
): array {
    chatGetOneToOneConversationPeer($conversationId, $actor);
    $config = chatScaledroneConfig();
    if ($config === null) {
        throw new ChatHttpException('Realtime messaging is not configured.', 503);
    }

    return [
        'conversation_id' => $conversationId,
        'channel_id' => $config['channel_id'],
        'secret_key' => $config['secret_key'],
        'room' => chatScaledroneCallRoomName(
            $conversationId,
            $config['secret_key']
        )
    ];
}

function chatCreateScaledroneClientToken(
    array $roomContexts,
    string $clientId,
    ?array $callInboxContext = null
): array {
    if (!preg_match('/\A[A-Za-z0-9:_-]{1,128}\z/', $clientId)) {
        throw new ChatHttpException('Invalid realtime client ID.', 422);
    }

    if ($roomContexts === [] && $callInboxContext === null) {
        throw new ChatHttpException('At least one conversation is required.', 422);
    }

    $firstRoom = $roomContexts === []
        ? $callInboxContext
        : reset($roomContexts);
    $permissions = [];
    $rooms = [];
    $callRooms = [];
    foreach ($roomContexts as $roomContext) {
        if (
            $roomContext['channel_id'] !== $firstRoom['channel_id']
            || $roomContext['secret_key'] !== $firstRoom['secret_key']
        ) {
            throw new ChatHttpException(
                'Realtime channel configuration changed. Reload this page.',
                503
            );
        }

        $permissions['^' . preg_quote($roomContext['room'], '/') . '$'] = [
            'publish' => true,
            'subscribe' => true
        ];
        $rooms[] = [
            'conversation_id' => $roomContext['conversation_id'],
            'room' => $roomContext['room']
        ];
        if (isset($roomContext['call_room'])) {
            $callRoom = $roomContext['call_room'];
            if (
                $callRoom['channel_id'] !== $firstRoom['channel_id']
                || $callRoom['secret_key'] !== $firstRoom['secret_key']
            ) {
                throw new ChatHttpException(
                    'Realtime channel configuration changed. Reload this page.',
                    503
                );
            }
            $permissions['^' . preg_quote($callRoom['room'], '/') . '$'] = [
                'publish' => false,
                'subscribe' => true
            ];
            $callRooms[] = [
                'conversation_id' => $roomContext['conversation_id'],
                'room' => $callRoom['room']
            ];
        }
    }
    if ($callInboxContext !== null) {
        if (
            $callInboxContext['channel_id'] !== $firstRoom['channel_id']
            || $callInboxContext['secret_key'] !== $firstRoom['secret_key']
        ) {
            throw new ChatHttpException(
                'Realtime channel configuration changed. Reload this page.',
                503
            );
        }
        $permissions['^' . preg_quote($callInboxContext['room'], '/') . '$'] = [
            'publish' => false,
            'subscribe' => true
        ];
    }

    $expiresAt = time() + 120;
    $token = chatScaledroneSignJwt(
        [
            'client' => $clientId,
            'channel' => $firstRoom['channel_id'],
            'permissions' => $permissions,
            'exp' => $expiresAt
        ],
        $firstRoom['secret_key']
    );

    $result = [
        'channel_id' => $firstRoom['channel_id'],
        'room' => $rooms[0]['room'] ?? null,
        'rooms' => $rooms,
        'call_rooms' => $callRooms,
        'token' => $token,
        'expires_at' => $expiresAt
    ];
    if ($callInboxContext !== null) {
        $result['call_inbox_room'] = $callInboxContext['room'];
    }

    return $result;
}

function chatPublishConversationEvent(
    int $conversationId,
    array $actor,
    array $event
): string {
    try {
        $roomContext = chatGetAuthorizedScaledroneRoom(
            $conversationId,
            $actor
        );
    } catch (ChatHttpException $e) {
        if ($e->httpStatus === 503) {
            return 'not_configured';
        }

        error_log(
            'Chat event could not be authorized for realtime publish.'
        );

        return 'failed';
    } catch (Throwable $e) {
        error_log('Chat event realtime authorization failed.');

        return 'failed';
    }

    $payload = array_merge([
        'conversation_id' => $conversationId,
        'actor_type' => $actor['actor_type'],
        'actor_id' => (int) $actor['actor_id']
    ], $event);

    return chatScaledronePublishToRoom($roomContext, $payload);
}

function chatPublishCallEvent(
    int $conversationId,
    array $actor,
    array $event
): string {
    try {
        $roomContext = chatGetAuthorizedScaledroneCallRoom(
            $conversationId,
            $actor
        );
        $peer = chatGetOneToOneConversationPeer($conversationId, $actor)['peer'];
    } catch (ChatHttpException $e) {
        if ($e->httpStatus === 503) {
            return 'not_configured';
        }
        return 'failed';
    } catch (Throwable $e) {
        error_log('Call signaling authorization failed.');
        return 'failed';
    }

    $payload = array_merge([
        'conversation_id' => $conversationId,
        'actor_type' => $actor['actor_type'],
        'actor_id' => (int) $actor['actor_id'],
        'actor_display_name' => chatParticipantDisplayNameForViewer(
            $peer,
            (string) $actor['actor_type'],
            (string) ($actor['display_name'] ?? '')
        ),
        'actor_profile_picture' => preg_match(
            '/\Auploads\/profile_pictures\/[A-Za-z0-9][A-Za-z0-9._-]*\z/',
            (string) ($actor['profile_picture'] ?? '')
        ) === 1
            ? (string) $actor['profile_picture']
            : '',
        'to_actor_type' => $peer['actor_type'],
        'to_actor_id' => $peer['actor_id']
    ], $event);

    $delivery = chatScaledronePublishToRoom($roomContext, $payload);
    if ($delivery !== 'published' || ($event['event'] ?? '') !== 'call.invite') {
        return $delivery;
    }

    $inboxContext = [
        'channel_id' => $roomContext['channel_id'],
        'secret_key' => $roomContext['secret_key'],
        'room' => chatScaledroneCallInboxRoomName(
            $peer['actor_type'],
            (int) $peer['actor_id'],
            $roomContext['secret_key']
        )
    ];

    return chatScaledronePublishToRoom($inboxContext, $payload);
}

function chatScaledronePublishToRoom(array $roomContext, array $payload): string
{
    if (!function_exists('curl_init')) {
        error_log('Scaledrone realtime publish failed: PHP cURL is unavailable.');
        return 'failed';
    }

    try {
        $url = 'https://api2.scaledrone.com/'
            . rawurlencode($roomContext['channel_id'])
            . '/'
            . rawurlencode($roomContext['room'])
            . '/publish';

        $curl = curl_init($url);

        if ($curl === false) {
            error_log('Scaledrone realtime publish failed: cURL initialization failed.');
            return 'failed';
        }

        curl_setopt_array($curl, [
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => json_encode(
                $payload,
                JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR
            ),
            CURLOPT_HTTPHEADER => [
                'Authorization: Basic ' . base64_encode(
                    $roomContext['channel_id']
                    . ':'
                    . $roomContext['secret_key']
                ),
                'Content-Type: application/json'
            ],
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_CONNECTTIMEOUT => 3,
            CURLOPT_TIMEOUT => 5,
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_SSL_VERIFYHOST => 2
        ]);

        $responseBody = curl_exec($curl);
        $curlErrorNumber = curl_errno($curl);
        $httpStatus = (int) curl_getinfo($curl, CURLINFO_HTTP_CODE);
        curl_close($curl);

        if ($responseBody === false || $curlErrorNumber !== 0) {
            error_log(
                'Scaledrone realtime publish failed: transport error '
                . $curlErrorNumber
                . '.'
            );

            return 'failed';
        }

        if ($httpStatus < 200 || $httpStatus >= 300) {
            error_log(
                'Scaledrone realtime publish failed with HTTP '
                . $httpStatus
                . '.'
            );

            return 'failed';
        }

        return 'published';
    } catch (Throwable $e) {
        error_log('Scaledrone realtime publish failed during request preparation.');

        return 'failed';
    }
}

function chatPublishPersistedMessage(
    int $conversationId,
    array $actor,
    array $message
): string {
    return chatPublishConversationEvent(
        $conversationId,
        $actor,
        [
            'event' => 'message.created',
            'message_id' => (int) $message['id'],
            'created_at' => (string) $message['created_at'],
            'message_type' => (string) $message['message_type']
        ]
    );
}
