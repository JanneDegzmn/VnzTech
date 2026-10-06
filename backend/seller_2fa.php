<?php
declare(strict_types=1);

require_once __DIR__ . '/database.php';
require_once __DIR__ . '/session.php';
require_once __DIR__ . '/csrf.php';
require_once __DIR__ . '/admin_totp.php';

header('Content-Type: application/json; charset=UTF-8');

function sellerTwoFactorResponse(bool $success, string $message, int $status = 200, array $extra = []): void
{
    http_response_code($status);
    echo json_encode(array_merge([
        'success' => $success,
        'message' => $message,
    ], $extra), JSON_THROW_ON_ERROR);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    sellerTwoFactorResponse(false, 'Method not allowed.', 405);
}

if (empty($_SESSION['user_logged_in']) || empty($_SESSION['user_id'])) {
    sellerTwoFactorResponse(false, 'Your session has expired. Please log in again.', 401);
}

if (!verifyCsrfToken($_POST['csrf_token'] ?? null)) {
    sellerTwoFactorResponse(false, 'Your session token has expired. Refresh and try again.', 419);
}

$userId = (int) $_SESSION['user_id'];
$action = trim((string) ($_POST['action'] ?? ''));

try {
    if ($action === 'dismiss_reminder') {
        if (isset($_POST['dont_show_again']) && $_POST['dont_show_again'] === '1') {
            $stmt = $pdo->prepare('UPDATE users SET totp_reminder_dismissed = 1 WHERE id = ?');
            $stmt->execute([$userId]);
        }

        unset($_SESSION['seller_2fa_reminder']);
        sellerTwoFactorResponse(true, 'Reminder preference saved.');
    }

    if ($action === 'set_login_requirement') {
        $loginRequired = ($_POST['login_required'] ?? '') === '1' ? 1 : 0;
        $statusStmt = $pdo->prepare("
            SELECT totp_enabled_at, totp_secret
            FROM users
            WHERE id = ? AND is_active = 1
            LIMIT 1
        ");
        $statusStmt->execute([$userId]);
        $account = $statusStmt->fetch();

        if (!$account) {
            sellerTwoFactorResponse(false, 'Your seller account is no longer active.', 401);
        }
        if ($loginRequired === 1 && (empty($account['totp_enabled_at']) || empty($account['totp_secret']))) {
            sellerTwoFactorResponse(false, 'Set up and verify an authenticator before requiring a code at login.', 409);
        }

        $updateStmt = $pdo->prepare("
            UPDATE users
            SET totp_login_required = ?,
                totp_reminder_dismissed = CASE
                    WHEN ? = 1 THEN 1
                    ELSE totp_reminder_dismissed
                END
            WHERE id = ?
        ");
        $dontShowAgain = ($_POST['dont_show_again'] ?? '') === '1' ? 1 : 0;
        $updateStmt->execute([$loginRequired, $dontShowAgain, $userId]);
        unset($_SESSION['seller_2fa_reminder']);
        sellerTwoFactorResponse(
            true,
            $loginRequired === 1
                ? 'Authenticator codes will be required at every login.'
                : 'Password-only login is selected. Your authenticator remains available for account recovery.'
        );
    }

    if ($action === 'setup') {
        $passwordStmt = $pdo->prepare('SELECT password_hash, email FROM users WHERE id = ? AND is_active = 1 LIMIT 1');
        $passwordStmt->execute([$userId]);
        $account = $passwordStmt->fetch();

        if (!$account || !password_verify((string) ($_POST['current_password'] ?? ''), (string) $account['password_hash'])) {
            sellerTwoFactorResponse(false, 'Your current password is incorrect.', 403);
        }

        $statusStmt = $pdo->prepare('SELECT totp_enabled_at FROM users WHERE id = ? LIMIT 1');
        $statusStmt->execute([$userId]);
        if ($statusStmt->fetchColumn()) {
            sellerTwoFactorResponse(false, 'Two-factor authentication is already enabled.', 409);
        }

        $secret = generateAdminTotpSecret();
        $_SESSION['seller_2fa_setup_secret'] = $secret;
        $_SESSION['seller_2fa_setup_started_at'] = time();

        sellerTwoFactorResponse(true, 'Authenticator setup started.', 200, [
            'action' => 'setup_started',
            'secret' => $secret,
            'otpauth_uri' => buildAdminTotpUri($secret, (string) $account['email']),
        ]);
    }

    if ($action === 'confirm_setup') {
        $secret = (string) ($_SESSION['seller_2fa_setup_secret'] ?? '');
        $startedAt = (int) ($_SESSION['seller_2fa_setup_started_at'] ?? 0);
        if ($secret === '' || $startedAt === 0 || time() - $startedAt > 600) {
            unset($_SESSION['seller_2fa_setup_secret'], $_SESSION['seller_2fa_setup_started_at']);
            sellerTwoFactorResponse(false, 'Authenticator setup expired. Start setup again.', 419);
        }

        $counter = matchAdminTotpCode($secret, trim((string) ($_POST['two_factor_code'] ?? '')));
        if ($counter === null) {
            sellerTwoFactorResponse(false, 'That code is invalid or expired. Check your authenticator and try again.', 422);
        }

        $pdo->beginTransaction();
        $updateStmt = $pdo->prepare("
            UPDATE users
            SET totp_secret = ?, totp_enabled_at = NOW(), totp_last_used_counter = ?,
                totp_login_required = 0
            WHERE id = ? AND is_active = 1 AND totp_enabled_at IS NULL
        ");
        $updateStmt->execute([$secret, $counter, $userId]);
        if ($updateStmt->rowCount() !== 1) {
            $pdo->rollBack();
            sellerTwoFactorResponse(false, 'Two-factor authentication is already enabled. Refresh Settings.', 409);
        }
        $pdo->commit();
        unset($_SESSION['seller_2fa_setup_secret'], $_SESSION['seller_2fa_setup_started_at']);
        session_regenerate_id(true);
        sellerTwoFactorResponse(true, 'Two-factor authentication is now enabled.');
    }

    if ($action === 'disable') {
        $pdo->beginTransaction();
        $authStmt = $pdo->prepare("
            SELECT password_hash, totp_secret, totp_enabled_at, totp_last_used_counter
            FROM users
            WHERE id = ? AND is_active = 1
            LIMIT 1
            FOR UPDATE
        ");
        $authStmt->execute([$userId]);
        $account = $authStmt->fetch();

        if (!$account || !password_verify((string) ($_POST['current_password'] ?? ''), (string) $account['password_hash'])) {
            $pdo->rollBack();
            sellerTwoFactorResponse(false, 'Your current password is incorrect.', 403);
        }

        if (empty($account['totp_enabled_at']) || empty($account['totp_secret'])) {
            $pdo->rollBack();
            sellerTwoFactorResponse(false, 'Two-factor authentication is not enabled.', 409);
        }

        $counter = matchAdminTotpCode((string) $account['totp_secret'], trim((string) ($_POST['two_factor_code'] ?? '')));
        if (
            $counter === null
            || ($account['totp_last_used_counter'] !== null && $counter <= (int) $account['totp_last_used_counter'])
        ) {
            $pdo->rollBack();
            sellerTwoFactorResponse(false, 'The authenticator code is invalid, expired, or already used.', 422);
        }

        $disableStmt = $pdo->prepare("
            UPDATE users
            SET totp_secret = NULL, totp_enabled_at = NULL,
                totp_last_used_counter = NULL, totp_login_required = 0
            WHERE id = ?
        ");
        $disableStmt->execute([$userId]);
        $pdo->commit();
        session_regenerate_id(true);
        sellerTwoFactorResponse(true, 'Two-factor authentication has been disabled.');
    }

    sellerTwoFactorResponse(false, 'Unknown two-factor action.', 400);
} catch (Throwable $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    error_log('Seller two-factor action failed for user ' . $userId . ': ' . $e->getMessage());
    sellerTwoFactorResponse(false, 'Unable to update two-factor settings right now. Please try again.', 500);
}
