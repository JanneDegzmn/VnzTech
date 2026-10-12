<?php

ob_start();
ini_set('display_errors', '0');
header('Cache-Control: no-store, private');

require_once __DIR__ . '/chat_scaledrone.php';

try {
    chatRequireMethod('POST');
    chatRequireCsrf();

    $actor = chatGetCurrentActor();
    $conversationIds = [];
    $requestedIds = $_POST['conversation_ids'] ?? $_POST['conversation_id'] ?? [];
    if (is_string($requestedIds)) {
        $requestedIds = trim($requestedIds) === ''
            ? []
            : explode(',', $requestedIds);
    }
    if (!is_array($requestedIds) || count($requestedIds) > 10) {
        throw new ChatHttpException('Invalid conversation list.', 422);
    }
    foreach ($requestedIds as $requestedId) {
        $conversationIds[] = chatPositiveInteger(
            $requestedId,
            'conversation ID'
        );
    }
    $conversationIds = array_values(array_unique($conversationIds));
    $callInboxRequested = ($_POST['call_inbox'] ?? '') === '1';
    if ($conversationIds === [] && !$callInboxRequested) {
        throw new ChatHttpException('At least one conversation is required.', 422);
    }
    $clientId = $_POST['client_id'] ?? null;

    if (!is_string($clientId)) {
        throw new ChatHttpException('Invalid realtime client ID.', 422);
    }

    $roomContexts = [];
    foreach ($conversationIds as $conversationId) {
        $roomContext = chatGetAuthorizedScaledroneRoom(
            $conversationId,
            $actor
        );
        if (
            in_array(
                $roomContext['conversation_type'] ?? '',
                ['transaction', 'private'],
                true
            )
            && empty($roomContext['guest_submission'])
        ) {
            try {
                $roomContext['call_room'] = chatGetAuthorizedScaledroneCallRoom(
                    $conversationId,
                    $actor
                );
            } catch (ChatHttpException $e) {
                if (!chatIsOptionalCallRoomIneligibility($e)) {
                    throw $e;
                }
            }
        }
        $roomContexts[] = $roomContext;
    }
    $callInboxContext = $callInboxRequested
        ? chatGetAuthorizedCallInbox($actor)
        : null;
    $authorization = chatCreateScaledroneClientToken(
        $roomContexts,
        trim($clientId),
        $callInboxContext
    );

    chatJsonResponse(
        true,
        'Realtime room authorized.',
        200,
        $authorization
    );
} catch (ChatHttpException $e) {
    chatJsonResponse(false, $e->getMessage(), $e->httpStatus);
} catch (Throwable $e) {
    error_log('Scaledrone authorization endpoint failed.');
    chatJsonResponse(
        false,
        'Unable to authorize realtime access.',
        500
    );
}
