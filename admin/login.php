<?php

/*
|--------------------------------------------------------------------------
| Prevent browser from caching the admin login page
|--------------------------------------------------------------------------
*/

header(
    'Cache-Control: no-store, no-cache, must-revalidate, max-age=0'
);

header(
    'Cache-Control: post-check=0, pre-check=0',
    false
);

header(
    'Pragma: no-cache'
);

header(
    'Expires: 0'
);


/*
|--------------------------------------------------------------------------
| Dependencies
|--------------------------------------------------------------------------
*/

require_once __DIR__ . '/../backend/database.php';
require_once __DIR__ . '/../backend/session.php';
require_once __DIR__ . '/../backend/auth.php';
require_once __DIR__ . '/../backend/csrf.php';
require_once __DIR__ . '/../backend/admin_totp.php';
require_once __DIR__ . '/../backend/admin_activity.php';
require_once __DIR__ . '/../backend/chat_presence_store.php';

$chatAuthenticationStartedAt = microtime(true);


/*
|--------------------------------------------------------------------------
| Already Logged In
|--------------------------------------------------------------------------
|
| If an admin is already authenticated, do not allow the login
| page to remain visible when the browser Back button is used.
|
|--------------------------------------------------------------------------
*/

if (isAdminLoggedIn()) {

    header(
        'Location: /VNZ-TECH/admin/dashboard.php'
    );

    exit;
}


$error = '';
$csrfToken = generateCsrfToken();
$pendingAdminId = (int) ($_SESSION['admin_2fa_pending_id'] ?? 0);
$twoFactorChallenge = $pendingAdminId > 0;
$forgotPasswordMode = isset($_GET['forgot_password'])
    || (
        $_SERVER['REQUEST_METHOD'] === 'POST'
        && ($_POST['action'] ?? '') === 'forgot_password'
    );
$forgotPasswordLogin = trim((string) ($_POST['login'] ?? ''));
$successMessage = isset($_GET['password_reset']);

if (
    $twoFactorChallenge
    && (
        empty($_SESSION['admin_2fa_pending_at'])
        || time() - (int) $_SESSION['admin_2fa_pending_at'] > 300
    )
) {
    unset(
        $_SESSION['admin_2fa_pending_id'],
        $_SESSION['admin_2fa_pending_at'],
        $_SESSION['admin_2fa_attempts']
    );
    $pendingAdminId = 0;
    $twoFactorChallenge = false;
    $error = 'Your verification session expired. Please sign in again.';
}

if (isset($_GET['cancel_2fa'])) {
    unset(
        $_SESSION['admin_2fa_pending_id'],
        $_SESSION['admin_2fa_pending_at'],
        $_SESSION['admin_2fa_attempts']
    );
    header('Location: /VNZ-TECH/admin/login.php');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verifyCsrfToken($_POST['csrf_token'] ?? null)) {
        $error = 'Your session token expired. Refresh the page and try again.';
    } elseif (($_POST['action'] ?? '') === 'forgot_password') {
        $forgotPasswordMode = true;
        $login = trim((string) ($_POST['login'] ?? ''));
        $code = trim((string) ($_POST['two_factor_code'] ?? ''));
        $newPassword = (string) ($_POST['new_password'] ?? '');
        $confirmPassword = (string) ($_POST['confirm_password'] ?? '');
        $ipAddress = mb_substr((string) ($_SERVER['REMOTE_ADDR'] ?? ''), 0, 45);
        $loginHash = hash('sha256', strtolower($login));

        $pdo->prepare("
            DELETE FROM admin_password_reset_attempts
            WHERE attempted_at < DATE_SUB(NOW(), INTERVAL 1 DAY)
        ")->execute();

        $attemptCountStmt = $pdo->prepare("
            SELECT COUNT(*)
            FROM admin_password_reset_attempts
            WHERE login_hash = ?
              AND ip_address = ?
              AND attempted_at >= DATE_SUB(NOW(), INTERVAL 15 MINUTE)
        ");
        $attemptCountStmt->execute([$loginHash, $ipAddress]);
        $attemptCount = (int) $attemptCountStmt->fetchColumn();

        if ($attemptCount >= 5) {
            $error = 'Too many reset attempts. Please wait 15 minutes before trying again.';
        } elseif (
            $login === ''
            || strlen($newPassword) < 8
            || $newPassword !== $confirmPassword
        ) {
            $error = $newPassword !== $confirmPassword
                ? 'The new password and confirmation do not match.'
                : 'Enter your username or email, a 6-digit authenticator code, and a password of at least 8 characters.';
        } else {
            $adminStmt = $pdo->prepare("
                SELECT id, password_hash, totp_secret, totp_enabled_at, totp_last_used_counter
                FROM admins
                WHERE (username = ? OR email = ?)
                  AND is_active = 1
                LIMIT 1
            ");
            $adminStmt->execute([$login, $login]);
            $admin = $adminStmt->fetch();
            $counter = $admin && !empty($admin['totp_enabled_at']) && !empty($admin['totp_secret'])
                && preg_match('/^\d{6}$/', $code)
                ? matchAdminTotpCode((string) $admin['totp_secret'], $code)
                : null;

            if (
                !$admin
                || $counter === null
                || (
                    $admin['totp_last_used_counter'] !== null
                    && $counter <= (int) $admin['totp_last_used_counter']
                )
            ) {
                $recordAttempt = $pdo->prepare("
                    INSERT INTO admin_password_reset_attempts (login_hash, ip_address, attempted_at)
                    VALUES (?, ?, NOW())
                ");
                $recordAttempt->execute([$loginHash, $ipAddress]);
                logAdminActivity(
                    $admin ? (int) $admin['id'] : null,
                    'security.password_reset_failed',
                    'Admin password reset verification failed.'
                );
                $error = 'We could not verify those details. Check your account and 6-digit authenticator code, then try again.';
            } else {
                $pdo->beginTransaction();
                $updateAdmin = $pdo->prepare("
                    UPDATE admins
                    SET password_hash = ?,
                        totp_last_used_counter = ?,
                        updated_at = NOW()
                    WHERE id = ?
                      AND (
                          totp_last_used_counter IS NULL
                          OR totp_last_used_counter < ?
                      )
                ");
                $updateAdmin->execute([
                    password_hash($newPassword, PASSWORD_DEFAULT),
                    $counter,
                    (int) $admin['id'],
                    $counter,
                ]);

                if ($updateAdmin->rowCount() !== 1) {
                    $pdo->rollBack();
                    $recordAttempt = $pdo->prepare("
                        INSERT INTO admin_password_reset_attempts (login_hash, ip_address, attempted_at)
                        VALUES (?, ?, NOW())
                    ");
                    $recordAttempt->execute([$loginHash, $ipAddress]);
                    $error = 'We could not verify those details. Check your account and 6-digit authenticator code, then try again.';
                } else {
                    $clearAttempts = $pdo->prepare("
                        DELETE FROM admin_password_reset_attempts
                        WHERE login_hash = ? AND ip_address = ?
                    ");
                    $clearAttempts->execute([$loginHash, $ipAddress]);
                    logAdminActivity(
                        (int) $admin['id'],
                        'security.password_reset',
                        'Admin password reset using authenticator verification.'
                    );
                    $pdo->commit();
                    $error = '';
                    $forgotPasswordMode = false;
                    $_SESSION['admin_password_reset_success'] = 'Password reset successfully. Sign in with your new password.';
                    header('Location: /VNZ-TECH/admin/login.php?password_reset=1');
                    exit;
                }
            }
        }
    } elseif ($twoFactorChallenge) {
        $attempts = (int) ($_SESSION['admin_2fa_attempts'] ?? 0);

        if ($attempts >= 5) {
            unset(
                $_SESSION['admin_2fa_pending_id'],
                $_SESSION['admin_2fa_pending_at'],
                $_SESSION['admin_2fa_attempts']
            );
            $twoFactorChallenge = false;
            $error = 'Too many verification attempts. Please sign in again.';
        } else {
            $stmt = $pdo->prepare("
                SELECT a.*, r.name AS role_name
                FROM admins a
                INNER JOIN roles r ON r.id = a.role_id
                WHERE a.id = ? AND a.is_active = 1
                LIMIT 1
            ");
            $stmt->execute([$pendingAdminId]);
            $admin = $stmt->fetch();
            $code = trim((string) ($_POST['two_factor_code'] ?? ''));
            $counter = $admin && !empty($admin['totp_secret'])
                ? matchAdminTotpCode((string) $admin['totp_secret'], $code)
                : null;
            $recoveryCodeId = null;

            if ($admin && $counter === null) {
                $recoveryHash = hash('sha256', normalizeAdminTotpRecoveryCode($code));
                $recoveryStmt = $pdo->prepare("
                    SELECT id
                    FROM admin_totp_recovery_codes
                    WHERE admin_id = ? AND code_hash = ? AND used_at IS NULL
                    LIMIT 1
                ");
                $recoveryStmt->execute([$pendingAdminId, $recoveryHash]);
                $recoveryCodeId = $recoveryStmt->fetchColumn();
            }

            if (
                !$admin
                || empty($admin['totp_enabled_at'])
                || ($counter === null && !$recoveryCodeId)
                || (
                    $counter !== null
                    &&
                    $admin['totp_last_used_counter'] !== null
                    && $counter <= (int) $admin['totp_last_used_counter']
                )
            ) {
                $_SESSION['admin_2fa_attempts'] = $attempts + 1;
                logAdminActivity(
                    $admin ? (int) $admin['id'] : null,
                    'auth.2fa_failed',
                    'Authenticator verification failed.'
                );
                $error = 'The verification code is invalid or expired.';
            } else {
                $pdo->beginTransaction();

                if ($counter !== null) {
                    $updateCounter = $pdo->prepare("
                        UPDATE admins
                        SET totp_last_used_counter = ?
                        WHERE id = ?
                          AND (
                              totp_last_used_counter IS NULL
                              OR totp_last_used_counter < ?
                          )
                    ");
                    $updateCounter->execute([$counter, $pendingAdminId, $counter]);
                    $codeAccepted = $updateCounter->rowCount() === 1;
                    $loginMethod = 'Authenticator verification';
                } else {
                    $consumeRecoveryCode = $pdo->prepare("
                        UPDATE admin_totp_recovery_codes
                        SET used_at = NOW()
                        WHERE id = ? AND admin_id = ? AND used_at IS NULL
                    ");
                    $consumeRecoveryCode->execute([$recoveryCodeId, $pendingAdminId]);
                    $codeAccepted = $consumeRecoveryCode->rowCount() === 1;
                    $loginMethod = 'Recovery code';
                }

                if (!$codeAccepted) {
                    $pdo->rollBack();
                    $_SESSION['admin_2fa_attempts'] = $attempts + 1;
                    $error = 'That verification code has already been used. Please wait for a new code.';
                } else {
                    logAdminActivity(
                        (int) $admin['id'],
                        'auth.login',
                        'Admin signed in with ' . $loginMethod . '.'
                    );
                    $pdo->commit();
                    session_regenerate_id(true);
                    clearSellerSessionState();
                    chatRegisterPresenceSession('admin', (int) $admin['id']);
                    unset(
                        $_SESSION['admin_2fa_pending_id'],
                        $_SESSION['admin_2fa_pending_at'],
                        $_SESSION['admin_2fa_attempts']
                    );
                    $_SESSION['admin_id'] = (int) $admin['id'];
                    $_SESSION['admin_role_id'] = (int) $admin['role_id'];
                    $_SESSION['admin_role'] = $admin['role_name'];
                    $_SESSION['admin_username'] = $admin['username'];
                    $_SESSION['admin_first_name'] = $admin['first_name'];
                    $_SESSION['admin_last_name'] = $admin['last_name'];
                    $_SESSION['admin_email'] = $admin['email'];
                    chatLogAuthenticationTiming('admin_2fa_login', $chatAuthenticationStartedAt);
                    header('Location: /VNZ-TECH/admin/dashboard.php');
                    exit;
                }
            }

            if ((int) ($_SESSION['admin_2fa_attempts'] ?? 0) >= 5) {
                unset(
                    $_SESSION['admin_2fa_pending_id'],
                    $_SESSION['admin_2fa_pending_at'],
                    $_SESSION['admin_2fa_attempts']
                );
                $twoFactorChallenge = false;
                $error = 'Too many verification attempts. Please sign in again.';
            }
        }
    } else {
        $login = trim((string) ($_POST['login'] ?? ''));
        $password = (string) ($_POST['password'] ?? '');

        if ($login === '' || $password === '') {
            $error = 'Please enter your username/email and password.';
        } else {
            $stmt = $pdo->prepare("
                SELECT
                    a.id, a.role_id, a.first_name, a.last_name, a.username,
                    a.email, a.password_hash, a.is_active, a.totp_secret,
                    a.totp_enabled_at, a.totp_login_required,
                    a.totp_reminder_dismissed, r.name AS role_name
                FROM admins a
                INNER JOIN roles r ON r.id = a.role_id
                WHERE a.username = ? OR a.email = ?
                LIMIT 1
            ");
            $stmt->execute([$login, $login]);
            $admin = $stmt->fetch();

            if (!$admin || !password_verify($password, $admin['password_hash'])) {
                logAdminActivity(
                    $admin ? (int) $admin['id'] : null,
                    'auth.login_failed',
                    'Invalid admin credentials.'
                );
                $error = 'Invalid username/email or password.';
            } elseif ((int) $admin['is_active'] !== 1) {
                logAdminActivity((int) $admin['id'], 'auth.login_failed', 'Inactive admin attempted to sign in.');
                $error = 'This admin account is currently inactive.';
            } elseif (
                !empty($admin['totp_enabled_at'])
                && !empty($admin['totp_secret'])
                && (int) $admin['totp_login_required'] === 1
            ) {
                session_regenerate_id(true);
                $_SESSION['admin_2fa_pending_id'] = (int) $admin['id'];
                $_SESSION['admin_2fa_pending_at'] = time();
                $_SESSION['admin_2fa_attempts'] = 0;
                $pendingAdminId = (int) $admin['id'];
                $twoFactorChallenge = true;
                logAdminActivity((int) $admin['id'], 'auth.2fa_challenge_started', 'Password accepted; authenticator code required.');
            } else {
                session_regenerate_id(true);
                clearSellerSessionState();
                chatRegisterPresenceSession('admin', (int) $admin['id']);
                $_SESSION['admin_id'] = (int) $admin['id'];
                $_SESSION['admin_role_id'] = (int) $admin['role_id'];
                $_SESSION['admin_role'] = $admin['role_name'];
                $_SESSION['admin_username'] = $admin['username'];
                $_SESSION['admin_first_name'] = $admin['first_name'];
                $_SESSION['admin_last_name'] = $admin['last_name'];
                $_SESSION['admin_email'] = $admin['email'];
                logAdminActivity((int) $admin['id'], 'auth.login', 'Admin signed in.');
                chatLogAuthenticationTiming('admin_login', $chatAuthenticationStartedAt);
                if (
                    !empty($admin['totp_enabled_at'])
                    && !empty($admin['totp_secret'])
                    && (int) $admin['totp_login_required'] !== 1
                    && (int) $admin['totp_reminder_dismissed'] !== 1
                ) {
                    $_SESSION['admin_2fa_reminder'] = true;
                    header('Location: /VNZ-TECH/admin/dashboard.php?security_reminder=1');
                } else {
                    header('Location: /VNZ-TECH/admin/dashboard.php');
                }
                exit;
            }
        }
    }
}

if ($successMessage) {
    $error = (string) ($_SESSION['admin_password_reset_success'] ?? 'Password reset successfully. Sign in with your new password.');
    unset($_SESSION['admin_password_reset_success']);
}

?>

<!DOCTYPE html>

<html lang="en">

<head>

    <meta charset="UTF-8">

    <meta
        name="viewport"
        content="width=device-width, initial-scale=1.0"
    >

    <meta
        http-equiv="Cache-Control"
        content="no-store, no-cache, must-revalidate"
    >

    <meta
        http-equiv="Pragma"
        content="no-cache"
    >

    <meta
        http-equiv="Expires"
        content="0"
    >

    <?php include __DIR__ . '/../includes/loader-head.php'; ?>

    <title>
        VNZ TECH - Admin Login
    </title>


    <link
        rel="stylesheet"
        href="/VNZ-TECH/assets/css/admin_login.css?v=<?= filemtime(__DIR__ . '/../assets/css/admin_login.css') ?>"
    >

</head>


<body>

<?php include __DIR__ . '/../includes/loader-body.php'; ?>

<div class="login-page">


    <div class="login-card">


        <!-- =====================================================
             BRAND
        ====================================================== -->

        <div class="brand">


            <div class="brand-name">
                VNZ TECH
            </div>


            <div class="brand-line"></div>


            <div class="brand-subtitle">
                ADMIN PANEL
            </div>


        </div>


        <!-- =====================================================
             LOGIN HEADING
        ====================================================== -->

        <div class="login-heading">
            <?= $forgotPasswordMode
                ? 'Reset Your Password'
                : ($twoFactorChallenge ? 'Verify Your Identity' : 'Welcome Back') ?>
        </div>


        <div class="login-description">
            <?= $forgotPasswordMode
                ? 'Enter your account details and a 6-digit authenticator code to create a new password. This option is available for admins with 2FA enabled.'
                : ($twoFactorChallenge
                    ? 'Enter the current 6-digit code from your authenticator app.'
                    : 'Sign in to access the VNZ TECH management system.') ?>
        </div>


        <!-- =====================================================
             ERROR
        ====================================================== -->

        <?php if ($error !== ''): ?>

            <div class="login-error<?= $successMessage ? ' login-message-success' : '' ?>"<?= $successMessage ? ' role="status"' : ' role="alert"' ?>>

                <?= htmlspecialchars(
                    $error,
                    ENT_QUOTES,
                    'UTF-8'
                ) ?>

            </div>

        <?php endif; ?>


        <!-- =====================================================
             LOGIN FORM
        ====================================================== -->

        <form
            method="POST"
            autocomplete="on"
            data-loader
        >
            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8') ?>">

            <?php if ($forgotPasswordMode): ?>
                <input type="hidden" name="action" value="forgot_password">

                <div class="form-group">
                    <label for="login">Username or Email</label>
                    <input
                        type="text"
                        id="login"
                        name="login"
                        placeholder="Enter your username or email"
                        value="<?= htmlspecialchars($forgotPasswordLogin, ENT_QUOTES, 'UTF-8') ?>"
                        autocomplete="username"
                        required
                        autofocus
                    >
                </div>

                <div class="form-group">
                    <label for="reset_two_factor_code">6-digit authenticator code</label>
                    <input
                        type="text"
                        id="reset_two_factor_code"
                        name="two_factor_code"
                        inputmode="numeric"
                        pattern="[0-9]{6}"
                        maxlength="6"
                        autocomplete="one-time-code"
                        placeholder="000000"
                        required
                    >
                </div>

                <div class="form-group">
                    <label for="new_password">New password</label>
                    <input
                        type="password"
                        id="new_password"
                        name="new_password"
                        minlength="8"
                        autocomplete="new-password"
                        placeholder="At least 8 characters"
                        required
                    >
                </div>

                <div class="form-group">
                    <label for="confirm_password">Confirm new password</label>
                    <input
                        type="password"
                        id="confirm_password"
                        name="confirm_password"
                        minlength="8"
                        autocomplete="new-password"
                        placeholder="Re-enter your new password"
                        required
                    >
                </div>
            <?php elseif ($twoFactorChallenge): ?>
                <div class="form-group">
                    <label for="two_factor_code">Authenticator code or recovery code</label>
                    <input
                        type="text"
                        id="two_factor_code"
                        name="two_factor_code"
                        pattern="([0-9]{6}|[A-Fa-f0-9]{4}(-[A-Fa-f0-9]{4}){3})"
                        maxlength="19"
                        autocomplete="one-time-code"
                        placeholder="000000 or XXXX-XXXX"
                        required
                        autofocus
                    >
                </div>
            <?php else: ?>

            <!-- =================================================
                 USERNAME / EMAIL
            ================================================== -->

            <div class="form-group">


                <label for="login">
                    Username or Email
                </label>


                <input
                    type="text"
                    id="login"
                    name="login"
                    placeholder="Enter your username or email"
                    value="<?= htmlspecialchars(
                        $_POST['login'] ?? '',
                        ENT_QUOTES,
                        'UTF-8'
                    ) ?>"
                    autocomplete="username"
                    required
                    autofocus
                >


            </div>


            <!-- =================================================
                 PASSWORD
            ================================================== -->

            <div class="form-group">


                <label for="password">
                    Password
                </label>


                <input
                    type="password"
                    id="password"
                    name="password"
                    placeholder="Enter your password"
                    autocomplete="current-password"
                    required
                >


            </div>
            <div class="login-forgot-password">
                <a href="/VNZ-TECH/admin/login.php?forgot_password=1">Forgot password?</a>
            </div>
            <?php endif; ?>


            <!-- =================================================
                 SIGN IN
            ================================================== -->

            <button
                type="submit"
                class="login-button"
            >
                <?= $forgotPasswordMode
                    ? 'RESET PASSWORD'
                    : ($twoFactorChallenge ? 'VERIFY CODE' : 'SIGN IN') ?>
            </button>


        </form>

        <?php if ($forgotPasswordMode): ?>
            <a class="login-two-factor-cancel" href="/VNZ-TECH/admin/login.php">Back to sign in</a>
        <?php elseif ($twoFactorChallenge): ?>
            <a class="login-two-factor-cancel" href="/VNZ-TECH/admin/login.php?cancel_2fa=1">Back to sign in</a>
        <?php endif; ?>

        <!-- =====================================================
             FOOTER
        ====================================================== -->

        <div class="login-footer">


            <span>
                VNZ TECH
            </span>


            <span class="footer-dot">
                •
            </span>


            <span>
                Management System
            </span>


        </div>


    </div>


</div>


</body>

</html>