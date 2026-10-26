<?php
/*
|--------------------------------------------------------------------------
| VNZ TECH - Session Handler
|--------------------------------------------------------------------------
*/

if (session_status() === PHP_SESSION_NONE) {
    $https = isset($_SERVER['HTTPS'])
        && $_SERVER['HTTPS'] !== ''
        && strtolower((string) $_SERVER['HTTPS']) !== 'off';

    session_set_cookie_params([
        'lifetime' => 0,
        'path' => '/',
        'secure' => $https,
        'httponly' => true,
        'samesite' => 'Lax'
    ]);
    ini_set('session.use_strict_mode', '1');
    ini_set('session.use_only_cookies', '1');
    ini_set('session.use_trans_sid', '0');

    session_start();
}

header('Permissions-Policy: unload=(self)');

function clearSellerSessionState(): void
{
    unset(
        $_SESSION['user_logged_in'],
        $_SESSION['user_id'],
        $_SESSION['user_email'],
        $_SESSION['user_name'],
        $_SESSION['seller_2fa_login_user_id'],
        $_SESSION['seller_2fa_login_started_at'],
        $_SESSION['remember_seller_after_2fa']
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
        $_SESSION['admin_2fa_pending_at'],
        $_SESSION['remember_admin_after_2fa']
    );
}
