<?php

require_once __DIR__ . '/database.php';
require_once __DIR__ . '/session.php';
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/csrf.php';
require_once __DIR__ . '/admin_totp.php';
require_once __DIR__ . '/admin_activity.php';

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

$action = trim((string) ($_POST['action'] ?? ''));

if (
    $action !== 'set_2fa_login_requirement'
    && !hasPermission('manage_settings')
) {
    adminSettingsResponse(false, 'You do not have permission to manage settings.', 403);
}

$currentAdmin = getCurrentAdmin();

if (!$currentAdmin) {
    adminSettingsResponse(false, 'Your admin account is no longer active.', 401);
}

$adminId = (int) $currentAdmin['id'];

try {
    if ($action === 'set_2fa_login_requirement') {
        $loginRequired = ($_POST['login_required'] ?? '') === '1' ? 1 : 0;

        if ($loginRequired === 1) {
            $totpStmt = $pdo->prepare("
                SELECT totp_secret, totp_enabled_at
                FROM admins
                WHERE id = ? AND is_active = 1
                LIMIT 1
            ");
            $totpStmt->execute([$adminId]);
            $totp = $totpStmt->fetch();

            if (!$totp || empty($totp['totp_enabled_at']) || empty($totp['totp_secret'])) {
                adminSettingsResponse(false, 'Set up and verify an authenticator before requiring a code at login.', 409);
            }
        }

        $dontShowAgain = ($_POST['dont_show_again'] ?? '') === '1' ? 1 : 0;
        $updatePreference = $pdo->prepare("
            UPDATE admins
            SET totp_login_required = ?,
                totp_reminder_dismissed = CASE
                WHEN ? = 1 THEN 1
                    ELSE totp_reminder_dismissed
                END,
                updated_at = NOW()
            WHERE id = ?
        ");
        $updatePreference->execute([$loginRequired, $dontShowAgain, $adminId]);
        logAdminActivity(
            $adminId,
            'security.2fa_login_requirement_updated',
            $loginRequired === 1
                ? 'Authenticator code required at every admin sign-in.'
                : 'Password-only admin sign-in selected.'
        );
        unset($_SESSION['admin_2fa_reminder']);
        adminSettingsResponse(
            true,
            $loginRequired === 1
                ? 'Authenticator codes will be required at every sign-in.'
                : 'Password-only sign-in selected. Your authenticator remains enabled for account recovery.'
        );
    }

    if ($action === 'setup_2fa') {
        if (!empty($currentAdmin['totp_enabled_at'])) {
            adminSettingsResponse(false, 'Two-factor authentication is already enabled.', 409);
        }

        $currentPassword = (string) ($_POST['current_password'] ?? '');
        $passwordStmt = $pdo->prepare('SELECT password_hash FROM admins WHERE id = ? LIMIT 1');
        $passwordStmt->execute([$adminId]);
        $passwordHash = $passwordStmt->fetchColumn();

        if (!is_string($passwordHash) || !password_verify($currentPassword, $passwordHash)) {
            adminSettingsResponse(false, 'Current password is incorrect.', 403);
        }

        $secret = generateAdminTotpSecret();
        $_SESSION['admin_2fa_setup_secret'] = $secret;
        $_SESSION['admin_2fa_setup_started_at'] = time();
        logAdminActivity($adminId, 'security.2fa_setup_started', 'Authenticator setup initiated.');
        adminSettingsResponse(true, 'Authenticator setup started.', 200, [
            'action' => 'setup_started',
            'secret' => $secret,
            'otpauth_uri' => buildAdminTotpUri($secret, (string) $currentAdmin['username']),
        ]);
    }

    if ($action === 'confirm_2fa') {
        $secret = (string) ($_SESSION['admin_2fa_setup_secret'] ?? '');
        $startedAt = (int) ($_SESSION['admin_2fa_setup_started_at'] ?? 0);

        if ($secret === '' || $startedAt === 0 || time() - $startedAt > 600) {
            unset($_SESSION['admin_2fa_setup_secret'], $_SESSION['admin_2fa_setup_started_at']);
            adminSettingsResponse(false, 'Authenticator setup expired. Start setup again.', 419);
        }

        $counter = matchAdminTotpCode(
            $secret,
            trim((string) ($_POST['two_factor_code'] ?? ''))
        );

        if ($counter === null) {
            adminSettingsResponse(false, 'That code is invalid or expired. Check your authenticator app and try again.', 422);
        }

        $recoveryCodes = generateAdminTotpRecoveryCodes();
        $updateStmt = $pdo->prepare("
            UPDATE admins
            SET totp_secret = ?, totp_enabled_at = NOW(), totp_last_used_counter = ?,
                totp_login_required = 0, updated_at = NOW()
            WHERE id = ?
        ");
        $pdo->beginTransaction();
        $updateStmt->execute([$secret, $counter, $adminId]);
        $deleteCodes = $pdo->prepare('DELETE FROM admin_totp_recovery_codes WHERE admin_id = ?');
        $deleteCodes->execute([$adminId]);
        $insertCode = $pdo->prepare("
            INSERT INTO admin_totp_recovery_codes (admin_id, code_hash, created_at)
            VALUES (?, ?, NOW())
        ");
        foreach ($recoveryCodes as $recoveryCode) {
            $insertCode->execute([
                $adminId,
                hash('sha256', normalizeAdminTotpRecoveryCode($recoveryCode)),
            ]);
        }
        logAdminActivity($adminId, 'security.2fa_enabled', 'Authenticator verification enabled.');
        $pdo->commit();
        unset($_SESSION['admin_2fa_setup_secret'], $_SESSION['admin_2fa_setup_started_at']);
        session_regenerate_id(true);
        adminSettingsResponse(true, 'Two-factor authentication is now enabled.', 200, [
            'recovery_codes' => $recoveryCodes,
        ]);
    }

    if ($action === 'disable_2fa') {
        $currentPassword = (string) ($_POST['current_password'] ?? '');
        $code = trim((string) ($_POST['two_factor_code'] ?? ''));
        $authStmt = $pdo->prepare("
            SELECT password_hash, totp_secret, totp_last_used_counter, totp_enabled_at
            FROM admins
            WHERE id = ?
            LIMIT 1
        ");
        $authStmt->execute([$adminId]);
        $authData = $authStmt->fetch();

        if (
            !$authData
            || !password_verify($currentPassword, (string) $authData['password_hash'])
        ) {
            adminSettingsResponse(false, 'Current password is incorrect.', 403);
        }

        if (empty($authData['totp_enabled_at']) || empty($authData['totp_secret'])) {
            adminSettingsResponse(false, 'Two-factor authentication is not enabled.', 409);
        }

        $counter = matchAdminTotpCode((string) $authData['totp_secret'], $code);
        $recoveryCodeId = null;

        if ($counter === null) {
            $recoveryStmt = $pdo->prepare("
                SELECT id
                FROM admin_totp_recovery_codes
                WHERE admin_id = ? AND code_hash = ? AND used_at IS NULL
                LIMIT 1
            ");
            $recoveryStmt->execute([
                $adminId,
                hash('sha256', normalizeAdminTotpRecoveryCode($code)),
            ]);
            $recoveryCodeId = $recoveryStmt->fetchColumn();
        }

        if (
            ($counter === null && !$recoveryCodeId)
            || (
                $counter !== null
                &&
                $authData['totp_last_used_counter'] !== null
                && $counter <= (int) $authData['totp_last_used_counter']
            )
        ) {
            adminSettingsResponse(false, 'The authenticator code is invalid, expired, or already used.', 422);
        }

        $updateStmt = $pdo->prepare("
            UPDATE admins
            SET totp_secret = NULL, totp_enabled_at = NULL,
                totp_last_used_counter = NULL, totp_login_required = 0,
                updated_at = NOW()
            WHERE id = ?
        ");
        $pdo->beginTransaction();

        if ($recoveryCodeId) {
            $consumeCode = $pdo->prepare("
                UPDATE admin_totp_recovery_codes
                SET used_at = NOW()
                WHERE id = ? AND admin_id = ? AND used_at IS NULL
            ");
            $consumeCode->execute([$recoveryCodeId, $adminId]);

            if ($consumeCode->rowCount() !== 1) {
                $pdo->rollBack();
                adminSettingsResponse(false, 'That recovery code has already been used.', 422);
            }
        }

        $updateStmt->execute([$adminId]);
        $deleteCodes = $pdo->prepare('DELETE FROM admin_totp_recovery_codes WHERE admin_id = ?');
        $deleteCodes->execute([$adminId]);
        $resetPreference = $pdo->prepare('UPDATE admins SET totp_login_required = 0 WHERE id = ?');
        $resetPreference->execute([$adminId]);
        logAdminActivity($adminId, 'security.2fa_disabled', 'Authenticator verification disabled after password and code confirmation.');
        $pdo->commit();
        unset($_SESSION['admin_2fa_setup_secret'], $_SESSION['admin_2fa_setup_started_at']);
        session_regenerate_id(true);
        adminSettingsResponse(true, 'Two-factor authentication has been disabled.');
    }

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
        logAdminActivity($adminId, 'settings.profile_updated', 'Admin profile details updated.');

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
        logAdminActivity($adminId, 'security.password_changed', 'Admin password changed.');

        adminSettingsResponse(true, 'Password updated successfully.');
    }

    adminSettingsResponse(false, 'Unknown settings action.', 400);
} catch (Throwable $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    adminSettingsResponse(false, 'Unable to save admin settings right now.', 500);
}