<?php

ob_start();
ini_set('display_errors', '0');
header('Cache-Control: no-store, private');

require_once __DIR__ . '/chat_scaledrone.php';

try {
    chatRequireMethod('POST');
    chatRequireCsrf();

    $actor = chatGetCurrentActor();
    $conversationId = chatPositiveInteger(
        $_POST['conversation_id'] ?? null,
        'conversation ID'
    );
    $clientId = $_POST['client_id'] ?? null;

    if (!is_string($clientId)) {
        throw new ChatHttpException('Invalid realtime client ID.', 422);
    }

    $roomContext = chatGetAuthorizedScaledroneRoom(
        $conversationId,
        $actor
    );
    $authorization = chatCreateScaledroneClientToken(
        $roomContext,
        trim($clientId)
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
