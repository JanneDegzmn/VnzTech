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