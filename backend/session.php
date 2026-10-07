<?php
/*
|--------------------------------------------------------------------------
| VNZ TECH - Session Handler
|--------------------------------------------------------------------------
*/

if (session_status() === PHP_SESSION_NONE) {

    session_set_cookie_params([
        'lifetime' => 0,
        'path' => '/',
        'secure' => false, // localhost/XAMPP
        'httponly' => true,
        'samesite' => 'Lax'
    ]);

    session_start();
}

function clearSellerSessionState(): void
{
    unset(
        $_SESSION['user_logged_in'],
        $_SESSION['user_id'],
        $_SESSION['user_email'],
        $_SESSION['user_name'],
        $_SESSION['seller_2fa_login_user_id'],
        $_SESSION['seller_2fa_login_started_at']
    );
}

function clearAdminSessionState(): void
{
    unset(
        $_SESSION['admin_id'],
        $_SESSION['admin_role_id'],
        $_SESSION['admin_role'],
        $_SESSION['admin_username'],
        $_SESSION['admin_first_name'],
        $_SESSION['admin_last_name'],
        $_SESSION['admin_email'],
        $_SESSION['admin_2fa_pending_id'],
        $_SESSION['admin_2fa_pending_at']
    );
}
