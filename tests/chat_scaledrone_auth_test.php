<?php

require_once __DIR__ . '/../backend/chat_scaledrone.php';

function assertChatAuthorization(bool $condition, string $message): void
{
    if (!$condition) {
        throw new RuntimeException($message);
    }
}

function decodeChatTokenPayload(string $token): array
{
    $parts = explode('.', $token);
    if (count($parts) !== 3) {
        throw new RuntimeException('Authorization token is malformed.');
    }

    $payload = base64_decode(
        strtr($parts[1], '-_', '+/') . str_repeat('=', (4 - strlen($parts[1]) % 4) % 4),
        true
    );
    if ($payload === false) {
        throw new RuntimeException('Authorization token payload is malformed.');
    }

    $decoded = json_decode($payload, true);
    if (!is_array($decoded)) {
        throw new RuntimeException('Authorization token payload is invalid.');
    }

    return $decoded;
}

function chatRoomContext(
    int $conversationId,
    string $type,
    string $room,
    ?array $callRoom = null
): array {
    $context = [
        'conversation_id' => $conversationId,
        'conversation_type' => $type,
        'transaction_id' => $type === 'transaction' ? $conversationId + 100 : null,
        'guest_submission' => false,
        'channel_id' => 'test-channel',
        'secret_key' => 'test-secret',
        'room' => $room
    ];
    if ($callRoom !== null) {
        $context['call_room'] = $callRoom;
    }

    return $context;
}

$sellerSellerIneligibility = new ChatHttpException(
    'Calls are only available between eligible Seller accounts.',
    403
);
assertChatAuthorization(
    chatIsOptionalCallRoomIneligibility($sellerSellerIneligibility),
    'Seller-Seller call ineligibility should not block message-room authorization.'
);
assertChatAuthorization(
    !chatIsOptionalCallRoomIneligibility(
        new ChatHttpException('Unauthorized.', 403)
    ),
    'Unauthorized membership errors must not be treated as optional call ineligibility.'
);
assertChatAuthorization(
    !chatIsOptionalCallRoomIneligibility(
        new ChatHttpException('Realtime messaging is not configured.', 503)
    ),
    'Realtime configuration errors must not be treated as optional call ineligibility.'
);

$adminCallInboxRejected = false;
try {
    chatGetAuthorizedCallInbox([
        'actor_type' => 'admin',
        'actor_id' => 1
    ]);
} catch (ChatHttpException $exception) {
    $adminCallInboxRejected = $exception->httpStatus === 403;
}
assertChatAuthorization(
    $adminCallInboxRejected,
    'Admin accounts must not receive call-inbox authorization.'
);

$sellerSeller = chatCreateScaledroneClientToken(
    [chatRoomContext(1, 'private', 'message-private-1')],
    'seller:1'
);
assertChatAuthorization(
    count($sellerSeller['rooms']) === 1
        && $sellerSeller['rooms'][0]['room'] === 'message-private-1',
    'Seller-Seller private messaging room should remain authorized.'
);
assertChatAuthorization(
    $sellerSeller['call_rooms'] === [],
    'Seller-Seller private chat must not receive a call-room grant.'
);
$sellerSellerPermissions = decodeChatTokenPayload($sellerSeller['token'])['permissions'];
$sellerMessagePermission = '^' . preg_quote('message-private-1', '/') . '$';
$sellerCallPermission = '^' . preg_quote('call-private-1', '/') . '$';
assertChatAuthorization(
    isset($sellerSellerPermissions[$sellerMessagePermission])
        && !isset($sellerSellerPermissions[$sellerCallPermission]),
    'Seller-Seller token should grant only the message room.'
);

$sellerCallRoom = chatCreateScaledroneClientToken([
    chatRoomContext(
        2,
        'private',
        'message-private-2',
        [
            'conversation_id' => 2,
            'channel_id' => 'test-channel',
            'secret_key' => 'test-secret',
            'room' => 'call-private-2'
        ]
    )
], 'seller:2');
assertChatAuthorization(
    count($sellerCallRoom['rooms']) === 1
        && count($sellerCallRoom['call_rooms']) === 1
        && $sellerCallRoom['call_rooms'][0]['room'] === 'call-private-2',
    'Eligible Seller-Seller private chat should retain its call-room grant.'
);
$sellerCallPermissions = decodeChatTokenPayload($sellerCallRoom['token'])['permissions'];
$sellerCallPermission = '^' . preg_quote('call-private-2', '/') . '$';
assertChatAuthorization(
    ($sellerCallPermissions[$sellerCallPermission] ?? null)
        === ['publish' => false, 'subscribe' => true],
    'Call-room permission should retain its existing subscribe-only policy.'
);

$transaction = chatCreateScaledroneClientToken([
    chatRoomContext(3, 'transaction', 'message-transaction-3')
], 'seller:2');
assertChatAuthorization(
    count($transaction['rooms']) === 1
        && $transaction['rooms'][0]['room'] === 'message-transaction-3'
        && $transaction['call_rooms'] === [],
    'Transaction chat message-room authorization should remain intact.'
);

$global = chatCreateScaledroneClientToken([
    chatRoomContext(4, 'global', 'message-global-4')
], 'seller:2');
assertChatAuthorization(
    count($global['rooms']) === 1
        && $global['rooms'][0]['room'] === 'message-global-4'
        && $global['call_rooms'] === [],
    'Global chat should retain messaging authorization without a call-room grant.'
);

$multipleRooms = chatCreateScaledroneClientToken([
    chatRoomContext(5, 'private', 'message-private-5'),
    chatRoomContext(
        6,
        'private',
        'message-private-6',
        [
            'conversation_id' => 6,
            'channel_id' => 'test-channel',
            'secret_key' => 'test-secret',
            'room' => 'call-private-6'
        ]
    )
], 'seller:3');
assertChatAuthorization(
    count($multipleRooms['rooms']) === 2
        && count($multipleRooms['call_rooms']) === 1
        && $multipleRooms['call_rooms'][0]['conversation_id'] === 6,
    'An ineligible room must not prevent unrelated message rooms or eligible calls from being granted.'
);

$signalingSource = file_get_contents(
    __DIR__ . '/../backend/chat_call_signal.php'
);
assertChatAuthorization(
    is_string($signalingSource)
        && strpos($signalingSource, 'chatGetOneToOneConversationPeer($conversationId, $actor)') !== false,
    'Call signaling must continue to enforce Seller-only participant eligibility.'
);
$callSessionSource = file_get_contents(
    __DIR__ . '/../backend/chat_call_sessions.php'
);
assertChatAuthorization(
    is_string($callSessionSource)
        && strpos(
            $callSessionSource,
            '($actor[\'actor_type\'] ?? null) !== \'seller\''
        ) !== false
        && strpos($callSessionSource, '$call[\'caller_type\'] !== \'seller\'') !== false
        && strpos($callSessionSource, '$call[\'callee_type\'] !== \'seller\'') !== false,
    'Call-session transitions must reject non-Seller actors and legacy non-Seller sessions.'
);

echo "Scaledrone chat authorization regression tests passed.\n";
