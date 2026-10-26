<?php

require_once __DIR__ . '/../backend/database.php';
require_once __DIR__ . '/../backend/session.php';
require_once __DIR__ . '/../backend/chat_presence_store.php';
require_once __DIR__ . '/../backend/remember_login.php';

$chatAuthenticationStartedAt = microtime(true);

if (!empty($_SESSION['user_logged_in']) && !empty($_SESSION['user_id'])) {
    try {
        $userId = (int) $_SESSION['user_id'];
        chatEndPresenceSession('seller', $userId);
    } catch (Throwable $e) {
        error_log('Seller logout presence cleanup failed: ' . $e->getMessage());
    }
}
vnzRevokeRememberLoginCookie($pdo);
chatLogAuthenticationTiming('seller_logout', $chatAuthenticationStartedAt);

$_SESSION = [];

if (ini_get('session.use_cookies')) {

    $params = session_get_cookie_params();

    setcookie(
        session_name(),
        '',
        time() - 42000,
        $params['path'],
        $params['domain'] ?? '',
        $params['secure'],
        $params['httponly']
    );
}

session_destroy();

header(
    'Cache-Control: no-store, no-cache, must-revalidate, max-age=0'
);
header(
    'Cache-Control: post-check=0, pre-check=0',
    false
);
header('Pragma: no-cache');
header('Expires: 0');

header(
    'Location: /VNZ-TECH/account/login.php?logout=1'
);

exit;