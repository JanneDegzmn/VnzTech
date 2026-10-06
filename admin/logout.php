<?php

require_once __DIR__ . '/../backend/session.php';
require_once __DIR__ . '/../backend/database.php';
require_once __DIR__ . '/../backend/admin_activity.php';

if (isset($_SESSION['admin_id'])) {
    logAdminActivity((int) $_SESSION['admin_id'], 'auth.logout', 'Admin signed out.');
}


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