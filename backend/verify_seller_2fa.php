<?php
declare(strict_types=1);

require_once __DIR__ . '/database.php';
require_once __DIR__ . '/session.php';
require_once __DIR__ . '/csrf.php';
require_once __DIR__ . '/admin_totp.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    exit('Method not allowed.');
}

$pendingUserId = (int) ($_SESSION['seller_2fa_login_user_id'] ?? 0);
$challengeStartedAt = (int) ($_SESSION['seller_2fa_login_started_at'] ?? 0);
if (
    $pendingUserId <= 0
    || $challengeStartedAt <= 0
    || time() - $challengeStartedAt > 300
) {
    unset($_SESSION['seller_2fa_login_user_id'], $_SESSION['seller_2fa_login_started_at']);
    header('Location: /VNZ-TECH/account/login.php?error=' . urlencode('Your sign-in verification expired. Please log in again.'));
    exit;
}

if (!verifyCsrfToken($_POST['csrf_token'] ?? null)) {
    header('Location: /VNZ-TECH/account/login.php?two_factor=1&error=' . urlencode('Your session expired. Refresh and try again.'));
    exit;
}

$failureWindowStartedAt = (int) ($_SESSION['seller_2fa_failure_window_started_at'] ?? 0);
if ($failureWindowStartedAt === 0 || time() - $failureWindowStartedAt >= 900) {
    $_SESSION['seller_2fa_failure_window_started_at'] = time();
    $_SESSION['seller_2fa_failure_count'] = 0;
}
if ((int) ($_SESSION['seller_2fa_failure_count'] ?? 0) >= 5) {
    unset(
        $_SESSION['seller_2fa_login_user_id'],
        $_SESSION['seller_2fa_login_started_at'],
        $_SESSION['seller_2fa_failure_count'],
        $_SESSION['seller_2fa_failure_window_started_at']
    );
    header('Location: /VNZ-TECH/account/login.php?error=' . urlencode('Too many invalid authenticator codes. Please sign in again.'));
    exit;
}

try {
    $ipHash = hash('sha256', (string) ($_SERVER['REMOTE_ADDR'] ?? ''));
    $pdo->beginTransaction();
    $userStmt = $pdo->prepare("
        SELECT id, full_name, email, is_active, totp_secret, totp_enabled_at,
               totp_last_used_counter
        FROM users
        WHERE id = ?
        LIMIT 1
        FOR UPDATE
    ");
    $userStmt->execute([$pendingUserId]);
    $user = $userStmt->fetch();

    $cleanupStmt = $pdo->prepare("
        DELETE FROM seller_totp_login_attempts
        WHERE attempted_at < DATE_SUB(NOW(), INTERVAL 1 DAY)
    ");
    $cleanupStmt->execute();

    $rateLimitStmt = $pdo->prepare("
        SELECT COUNT(*)
        FROM seller_totp_login_attempts
        WHERE user_id = ?
          AND ip_hash = ?
          AND attempted_at >= DATE_SUB(NOW(), INTERVAL 15 MINUTE)
    ");
    $rateLimitStmt->execute([$pendingUserId, $ipHash]);
    if ((int) $rateLimitStmt->fetchColumn() >= 5) {
        $pdo->rollBack();
        unset(
            $_SESSION['seller_2fa_login_user_id'],
            $_SESSION['seller_2fa_login_started_at'],
            $_SESSION['seller_2fa_failure_count'],
            $_SESSION['seller_2fa_failure_window_started_at']
        );
        header('Location: /VNZ-TECH/account/login.php?error=' . urlencode('Too many invalid authenticator codes. Please wait 15 minutes before signing in again.'));
        exit;
    }

    $code = trim((string) ($_POST['two_factor_code'] ?? ''));
    $counter = $user && !empty($user['totp_enabled_at']) && !empty($user['totp_secret'])
        ? matchAdminTotpCode((string) $user['totp_secret'], $code)
        : null;

    if (
        !$user
        || (int) $user['is_active'] !== 1
        || $counter === null
        || (
            $user['totp_last_used_counter'] !== null
            && $counter <= (int) $user['totp_last_used_counter']
        )
    ) {
        $pdo->rollBack();
        if ($user && (int) $user['is_active'] === 1 && !empty($user['totp_enabled_at'])) {
            $recordAttempt = $pdo->prepare('INSERT INTO seller_totp_login_attempts (user_id, ip_hash, attempted_at) VALUES (?, ?, NOW())');
            $recordAttempt->execute([$pendingUserId, $ipHash]);
        }
        $_SESSION['seller_2fa_failure_count'] =
            (int) ($_SESSION['seller_2fa_failure_count'] ?? 0) + 1;
        header('Location: /VNZ-TECH/account/login.php?two_factor=1&error=' . urlencode('The authenticator code is invalid, expired, or already used.'));
        exit;
    }

    $updateStmt = $pdo->prepare('UPDATE users SET totp_last_used_counter = ? WHERE id = ?');
    $updateStmt->execute([$counter, $pendingUserId]);
    $pdo->commit();

    session_regenerate_id(true);
    clearAdminSessionState();
    unset(
        $_SESSION['seller_2fa_login_user_id'],
        $_SESSION['seller_2fa_login_started_at'],
        $_SESSION['seller_2fa_failure_count'],
        $_SESSION['seller_2fa_failure_window_started_at'],
        $_SESSION['seller_2fa_reminder']
    );
    $_SESSION['user_logged_in'] = true;
    $_SESSION['user_id'] = (int) $user['id'];
    $_SESSION['user_email'] = $user['email'];
    $_SESSION['user_name'] = $user['full_name'];
    header('Location: /VNZ-TECH/account/dashboard.php');
    exit;
} catch (Throwable $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    error_log('Seller two-factor login verification failed: ' . $e->getMessage());
    header('Location: /VNZ-TECH/account/login.php?two_factor=1&error=' . urlencode('Unable to verify the authenticator code. Please try again.'));
    exit;
}
