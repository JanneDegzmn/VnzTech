<?php

require_once __DIR__ . '/../backend/session.php';
require_once __DIR__ . '/../backend/database.php';
require_once __DIR__ . '/../backend/admin_activity.php';
require_once __DIR__ . '/../backend/chat_presence_store.php';
require_once __DIR__ . '/../backend/remember_login.php';

$chatAuthenticationStartedAt = microtime(true);

if (isset($_SESSION['admin_id'])) {
    $adminId = (int) $_SESSION['admin_id'];
    try {
        chatEndPresenceSession('admin', $adminId);
    } catch (Throwable $e) {
        error_log('Admin logout presence cleanup failed: ' . $e->getMessage());
    }
    logAdminActivity($adminId, 'auth.logout', 'Admin signed out.');
}
vnzRevokeRememberLoginCookie($pdo);
chatLogAuthenticationTiming('admin_logout', $chatAuthenticationStartedAt);


// Clear all session data

$_SESSION = [];


// Destroy session

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


// Return to login

header('Location: login.php');

exit;