<?php

require_once __DIR__ . '/database.php';
require_once __DIR__ . '/session.php';
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/csrf.php';

header('Content-Type: application/json; charset=utf-8');

function adminSettingsResponse(bool $success, string $message, int $status = 200, array $extra = []): void
{
    http_response_code($status);
    echo json_encode(array_merge([
        'success' => $success,
        'message' => $message
    ], $extra));
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    adminSettingsResponse(false, 'Method not allowed.', 405);
}

if (!isAdminLoggedIn()) {
    adminSettingsResponse(false, 'Your admin session has expired.', 401);
}

if (!verifyCsrfToken($_POST['csrf_token'] ?? null)) {
    adminSettingsResponse(false, 'Your session token has expired. Refresh the page and try again.', 419);
}

if (!hasPermission('manage_settings')) {
    adminSettingsResponse(false, 'You do not have permission to manage settings.', 403);
}

$currentAdmin = getCurrentAdmin();

if (!$currentAdmin) {
    adminSettingsResponse(false, 'Your admin account is no longer active.', 401);
}

$adminId = (int) $currentAdmin['id'];
$action = trim((string) ($_POST['action'] ?? ''));

try {
    if ($action === 'profile') {
        $firstName = trim((string) ($_POST['first_name'] ?? ''));
        $lastName = trim((string) ($_POST['last_name'] ?? ''));
        $username = trim((string) ($_POST['username'] ?? ''));
        $email = strtolower(trim((string) ($_POST['email'] ?? '')));

        if (
            $firstName === ''
            || mb_strlen($firstName) > 80
            || $lastName === ''
            || mb_strlen($lastName) > 80
        ) {
            adminSettingsResponse(false, 'Enter a first and last name (maximum 80 characters each).', 422);
        }

        if (!preg_match('/^[A-Za-z0-9._-]{3,60}$/', $username)) {
            adminSettingsResponse(false, 'Username must be 3–60 characters and use only letters, numbers, dots, underscores, or hyphens.', 422);
        }

        if (!filter_var($email, FILTER_VALIDATE_EMAIL) || strlen($email) > 150) {
            adminSettingsResponse(false, 'Enter a valid email address.', 422);
        }

        $duplicateStmt = $pdo->prepare("
            SELECT id
            FROM admins
            WHERE (username = ? OR email = ?)
              AND id <> ?
            LIMIT 1
        ");
        $duplicateStmt->execute([$username, $email, $adminId]);

        if ($duplicateStmt->fetch()) {
            adminSettingsResponse(false, 'That username or email is already used by another admin.', 409);
        }

        $updateStmt = $pdo->prepare("
            UPDATE admins
            SET first_name = ?, last_name = ?, username = ?, email = ?, updated_at = NOW()
            WHERE id = ?
        ");
        $updateStmt->execute([$firstName, $lastName, $username, $email, $adminId]);

        $_SESSION['admin_username'] = $username;
        $_SESSION['admin_email'] = $email;
        $_SESSION['admin_first_name'] = $firstName;
        $_SESSION['admin_last_name'] = $lastName;

        adminSettingsResponse(true, 'Admin profile updated.', 200, [
            'admin_name' => $firstName,
            'admin_full_name' => $firstName . ' ' . $lastName
        ]);
    }

    if ($action === 'password') {
        $currentPassword = (string) ($_POST['current_password'] ?? '');
        $newPassword = (string) ($_POST['new_password'] ?? '');
        $confirmPassword = (string) ($_POST['confirm_password'] ?? '');

        if ($currentPassword === '' || $newPassword === '') {
            adminSettingsResponse(false, 'Enter your current password and a new password.', 422);
        }

        if (strlen($newPassword) < 8) {
            adminSettingsResponse(false, 'New password must be at least 8 characters.', 422);
        }

        if ($newPassword !== $confirmPassword) {
            adminSettingsResponse(false, 'New password and confirmation do not match.', 422);
        }

        $passwordStmt = $pdo->prepare('SELECT password_hash FROM admins WHERE id = ? LIMIT 1');
        $passwordStmt->execute([$adminId]);
        $passwordHash = $passwordStmt->fetchColumn();

        if (!is_string($passwordHash) || !password_verify($currentPassword, $passwordHash)) {
            adminSettingsResponse(false, 'Current password is incorrect.', 403);
        }

        if (password_verify($newPassword, $passwordHash)) {
            adminSettingsResponse(false, 'Choose a password different from your current one.', 422);
        }

        $updateStmt = $pdo->prepare('UPDATE admins SET password_hash = ?, updated_at = NOW() WHERE id = ?');
        $updateStmt->execute([password_hash($newPassword, PASSWORD_DEFAULT), $adminId]);
        session_regenerate_id(true);

        adminSettingsResponse(true, 'Password updated successfully.');
    }

    adminSettingsResponse(false, 'Unknown settings action.', 400);
} catch (Throwable $e) {
    adminSettingsResponse(false, 'Unable to save admin settings right now.', 500);
}