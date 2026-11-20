<?php


require_once __DIR__ . '/site_status.php';
vnzEnforceSiteMaintenance('json', true, true, 'messaging');
ob_start();
ini_set('display_errors', '0');
header('Cache-Control: no-store, private');

require_once __DIR__ . '/chat_service.php';
require_once __DIR__ . '/chat_presence_store.php';

try {
    chatRequireMethod('POST');
    chatRequireCsrf();

    $actor = chatGetCurrentActor();
    $cameOnline = chatHeartbeatPresenceSession(
        $actor['actor_type'],
        (int) $actor['actor_id']
    );
    if ($cameOnline) {
        chatBroadcastPresenceChange(
            $actor['actor_type'],
            (int) $actor['actor_id'],
            'online'
        );
    }

    chatJsonResponse(true, 'Presence updated.', 200, [
        'ttl_seconds' => 60
    ]);
} catch (ChatHttpException $e) {
    chatJsonResponse(false, $e->getMessage(), $e->httpStatus);
} catch (Throwable $e) {
    error_log('Chat presence endpoint failed: ' . $e->getMessage());
    chatJsonResponse(false, 'Unable to update chat presence.', 500);
}
