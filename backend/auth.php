<?php
/*
|--------------------------------------------------------------------------
| VNZ TECH - Authentication Helper
|--------------------------------------------------------------------------
*/

require_once __DIR__ . '/database.php';
require_once __DIR__ . '/session.php';


/*
|--------------------------------------------------------------------------
| Check if Admin is Logged In
|--------------------------------------------------------------------------
*/

function isAdminLoggedIn(): bool
{
    return isset($_SESSION['admin_id']);
}


/*
|--------------------------------------------------------------------------
| Require Admin Login
|--------------------------------------------------------------------------
*/

function requireAdminLogin(): void
{
    if (!isAdminLoggedIn()) {
        header('Location: /VNZ-TECH/admin/login.php');
        exit;
    }
}


/*
|--------------------------------------------------------------------------
| Get Current Admin
|--------------------------------------------------------------------------
*/

function getCurrentAdmin(): ?array
{
    global $pdo;

    if (!isAdminLoggedIn()) {
        return null;
    }

    $stmt = $pdo->prepare("
        SELECT
            a.id,
            a.role_id,
            a.first_name,
            a.last_name,
            a.username,
            a.email,
            a.is_active,
            a.totp_enabled_at,
            a.totp_login_required,
            a.totp_reminder_dismissed,
            r.name AS role_name
        FROM admins a
        INNER JOIN roles r
            ON r.id = a.role_id
        WHERE a.id = ?
        LIMIT 1
    ");

    $stmt->execute([$_SESSION['admin_id']]);

    $admin = $stmt->fetch();

    if (!$admin || !$admin['is_active']) {
        unset($_SESSION['admin_id']);
        return null;
    }

    return $admin;
}


/*
|--------------------------------------------------------------------------
| Check Permission
|--------------------------------------------------------------------------
*/

function hasPermission(string $permissionName): bool
{
    global $pdo;

    if (!isAdminLoggedIn()) {
        return false;
    }

    $stmt = $pdo->prepare("
        SELECT COUNT(*)
        FROM admins a
        INNER JOIN role_permissions rp
            ON rp.role_id = a.role_id
        INNER JOIN permissions p
            ON p.id = rp.permission_id
        WHERE a.id = ?
          AND a.is_active = 1
          AND p.name = ?
    ");

    $stmt->execute([
        $_SESSION['admin_id'],
        $permissionName
    ]);

    return (int) $stmt->fetchColumn() > 0;
}


/*
|--------------------------------------------------------------------------
| Require Permission
|--------------------------------------------------------------------------
*/

function requirePermission(string $permissionName): void
{
    requireAdminLogin();

    if (!hasPermission($permissionName)) {
        http_response_code(403);

        die('
            <h1>403 - Access Denied</h1>
            <p>You do not have permission to access this page.</p>
        ');
    }
}