<?php
declare(strict_types=1);

require_once __DIR__ . '/../backend/database.php';
require_once __DIR__ . '/../backend/session.php';
require_once __DIR__ . '/../backend/csrf.php';
require_once __DIR__ . '/../backend/email_verification.php';
require_once __DIR__ . '/../backend/admin_totp.php';

$message = '';
$isError = false;
$csrfToken = generateCsrfToken();
$isTotpRecovery = ($_POST['recovery_method'] ?? '') === 'totp';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verifyCsrfToken($_POST['csrf_token'] ?? null)) {
        http_response_code(419);
        $message = 'Your session expired. Refresh the page and try again.';
        $isError = true;
    } else {
        if ($isTotpRecovery) {
            $mobile = preg_replace('/\D+/', '', (string) ($_POST['mobile'] ?? ''));
            $code = trim((string) ($_POST['two_factor_code'] ?? ''));
            $newPassword = (string) ($_POST['new_password'] ?? '');
            $confirmPassword = (string) ($_POST['confirm_password'] ?? '');

            if (strlen($mobile) < 8 || strlen($mobile) > 15) {
                http_response_code(422);
                $message = 'Enter the mobile number registered to your account.';
                $isError = true;
            } elseif (!preg_match('/^\d{6}$/', $code)) {
                http_response_code(422);
                $message = 'Enter the 6-digit code from your authenticator app.';
                $isError = true;
            } elseif (strlen($newPassword) < 8) {
                http_response_code(422);
                $message = 'Your new password must be at least 8 characters.';
                $isError = true;
            } elseif ($newPassword !== $confirmPassword) {
                http_response_code(422);
                $message = 'The new password and confirmation do not match.';
                $isError = true;
            } else {
                $genericFailure = 'We could not verify that mobile number and authenticator code. Check your details and try again.';
                try {
                    $ipHash = hash('sha256', (string) ($_SERVER['REMOTE_ADDR'] ?? ''));
                    $lookupHash = hash('sha256', 'mobile:' . $mobile);

                    $cleanupStmt = $pdo->prepare("
                        DELETE FROM seller_password_reset_attempts
                        WHERE attempted_at < DATE_SUB(NOW(), INTERVAL 1 DAY)
                    ");
                    $cleanupStmt->execute();

                    $rateLimitStmt = $pdo->prepare("
                        SELECT COUNT(*)
                        FROM seller_password_reset_attempts
                        WHERE email_hash = ?
                          AND ip_hash = ?
                          AND attempted_at >= DATE_SUB(NOW(), INTERVAL 15 MINUTE)
                    ");
                    $rateLimitStmt->execute([$lookupHash, $ipHash]);
                    $rateLimited = (int) $rateLimitStmt->fetchColumn() >= 5;

                    $attemptStmt = $pdo->prepare("
                        INSERT INTO seller_password_reset_attempts (
                            user_id, email_hash, ip_hash, attempted_at
                        )
                        VALUES (NULL, ?, ?, NOW())
                    ");
                    $attemptStmt->execute([$lookupHash, $ipHash]);

                    if (!$rateLimited) {
                        $userStmt = $pdo->prepare("
                            SELECT id
                            FROM users
                            WHERE REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(mobile, ' ', ''), '-', ''), '(', ''), ')', ''), '+', '') = ?
                              AND is_active = 1
                              AND totp_enabled_at IS NOT NULL
                            LIMIT 2
                        ");
                        $userStmt->execute([$mobile]);
                        $matches = $userStmt->fetchAll();

                        if (count($matches) === 1) {
                            $pdo->beginTransaction();
                            $authStmt = $pdo->prepare("
                                SELECT id, totp_secret, totp_last_used_counter
                                FROM users
                                WHERE id = ? AND is_active = 1 AND totp_enabled_at IS NOT NULL
                                LIMIT 1
                                FOR UPDATE
                            ");
                            $authStmt->execute([(int) $matches[0]['id']]);
                            $account = $authStmt->fetch();
                            $counter = $account && !empty($account['totp_secret'])
                                ? matchAdminTotpCode((string) $account['totp_secret'], $code)
                                : null;

                            if (
                                $account
                                && $counter !== null
                                && (
                                    $account['totp_last_used_counter'] === null
                                    || $counter > (int) $account['totp_last_used_counter']
                                )
                            ) {
                                $updateStmt = $pdo->prepare("
                                    UPDATE users
                                    SET password_hash = ?, totp_last_used_counter = ?
                                    WHERE id = ? AND is_active = 1
                                ");
                                $updateStmt->execute([
                                    password_hash($newPassword, PASSWORD_DEFAULT),
                                    $counter,
                                    (int) $account['id']
                                ]);
                                $invalidateStmt = $pdo->prepare("
                                    UPDATE seller_password_reset_attempts
                                    SET used_at = NOW(), token_hash = NULL, expires_at = NULL
                                    WHERE user_id = ? AND token_hash IS NOT NULL AND used_at IS NULL
                                ");
                                $invalidateStmt->execute([(int) $account['id']]);
                                $pdo->commit();
                                $message = 'Your password has been reset. You can now sign in using your registered mobile number and new password.';
                            } else {
                                $pdo->rollBack();
                                $message = $genericFailure;
                                $isError = true;
                            }
                        } else {
                            $message = $genericFailure;
                            $isError = true;
                        }
                    } else {
                        $message = $genericFailure;
                        $isError = true;
                    }
                } catch (Throwable $e) {
                    if ($pdo->inTransaction()) {
                        $pdo->rollBack();
                    }
                    error_log('Seller authenticator password recovery failed: ' . $e->getMessage());
                    http_response_code(500);
                    $message = 'Unable to verify your recovery details right now. Please try again later.';
                    $isError = true;
                }
            }
        } else {
        $email = strtolower(trim((string) ($_POST['email'] ?? '')));
        if (!filter_var($email, FILTER_VALIDATE_EMAIL) || strlen($email) > 190) {
            http_response_code(422);
            $message = 'Enter a valid email address.';
            $isError = true;
        } else {
            $genericMessage =
                'If an active seller account uses this verified email, a password reset link will be sent. Check your inbox and spam folder.';

            try {
                $ipAddress = (string) ($_SERVER['REMOTE_ADDR'] ?? '');
                $emailHash = hash('sha256', $email);
                $ipHash = hash('sha256', $ipAddress);

                $cleanupStmt = $pdo->prepare("
                    DELETE FROM seller_password_reset_attempts
                    WHERE attempted_at < DATE_SUB(NOW(), INTERVAL 1 DAY)
                ");
                $cleanupStmt->execute();

                $rateLimitStmt = $pdo->prepare("
                    SELECT COUNT(*)
                    FROM seller_password_reset_attempts
                    WHERE email_hash = ?
                      AND ip_hash = ?
                      AND attempted_at >= DATE_SUB(NOW(), INTERVAL 15 MINUTE)
                ");
                $rateLimitStmt->execute([$emailHash, $ipHash]);
                $rateLimited = (int) $rateLimitStmt->fetchColumn() >= 5;

                $userStmt = $pdo->prepare("
                    SELECT id, email
                    FROM users
                    WHERE email = ?
                      AND is_active = 1
                      AND email_verified_at IS NOT NULL
                    LIMIT 1
                ");
                $userStmt->execute([$email]);
                $user = $userStmt->fetch();

                $token = null;
                $tokenHash = null;
                if (!$rateLimited && $user) {
                    $token = bin2hex(random_bytes(32));
                    $tokenHash = hash('sha256', $token);
                }

                $recordStmt = $pdo->prepare("
                    INSERT INTO seller_password_reset_attempts (
                        user_id,
                        email_hash,
                        ip_hash,
                        token_hash,
                        expires_at,
                        attempted_at
                    )
                    VALUES (
                        ?, ?, ?, ?,
                        IF(? IS NULL, NULL, DATE_ADD(NOW(), INTERVAL 30 MINUTE)),
                        NOW()
                    )
                ");
                $recordStmt->execute([
                    $user ? (int) $user['id'] : null,
                    $emailHash,
                    $ipHash,
                    $tokenHash,
                    $tokenHash
                ]);

                if ($token !== null && $tokenHash !== null) {
                    try {
                        $config = getVerificationMailConfig();
                        $resetUrl =
                            rtrim((string) $config['base_url'], '/') .
                            '/account/reset_password.php?token=' .
                            rawurlencode($token);

                        $mailer = new PHPMailer\PHPMailer\PHPMailer(true);
                        $mailer->isSMTP();
                        $mailer->Host = (string) $config['smtp_host'];
                        $mailer->SMTPAuth = true;
                        $mailer->Username = (string) $config['smtp_username'];
                        $mailer->Password = preg_replace(
                            '/\s+/',
                            '',
                            (string) $config['smtp_password']
                        );
                        $mailer->SMTPSecure =
                            PHPMailer\PHPMailer\PHPMailer::ENCRYPTION_STARTTLS;
                        $mailer->Port = (int) $config['smtp_port'];
                        $mailer->CharSet =
                            PHPMailer\PHPMailer\PHPMailer::CHARSET_UTF8;
                        $mailer->setFrom(
                            (string) $config['from_email'],
                            (string) $config['from_name']
                        );
                        $mailer->addAddress((string) $user['email']);
                        $mailer->isHTML(true);
                        $mailer->Subject = 'Reset your VNZ TECH seller password';
                        $safeResetUrl = htmlspecialchars(
                            $resetUrl,
                            ENT_QUOTES,
                            'UTF-8'
                        );
                        $mailer->Body =
                            '<p>We received a request to reset your VNZ TECH seller account password.</p>' .
                            '<p><a href="' . $safeResetUrl . '">Create a new password</a></p>' .
                            '<p>This one-time link expires in 30 minutes. If you did not request this, you can ignore this email.</p>';
                        $mailer->AltBody =
                            "Create a new password using this one-time link:\n\n" .
                            $resetUrl .
                            "\n\nThis link expires in 30 minutes. If you did not request this, you can ignore this email.";
                        $mailer->send();
                    } catch (Throwable $e) {
                        $invalidateStmt = $pdo->prepare("
                            UPDATE seller_password_reset_attempts
                            SET token_hash = NULL, expires_at = NULL
                            WHERE token_hash = ?
                        ");
                        $invalidateStmt->execute([$tokenHash]);
                        error_log('Seller password reset email could not be sent: ' . $e->getMessage());
                    }
                }

                $message = $genericMessage;
            } catch (Throwable $e) {
                error_log('Seller password reset request failed: ' . $e->getMessage());
                http_response_code(500);
                $message = 'Unable to process the password reset request right now. Please try again later.';
                $isError = true;
            }
        }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <?php include __DIR__ . '/../includes/loader-head.php'; ?>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Forgot Password | VNZ TECH</title>
    <link rel="stylesheet" href="/VNZ-TECH/assets/css/seller_login.css?v=20261006-seller-2fa">
</head>
<body>
<?php include __DIR__ . '/../includes/loader-body.php'; ?>
<div class="login-wrapper">
    <div class="page-back">
        <a href="/VNZ-TECH/account/login.php">← Back to Login</a>
    </div>
    <div class="brand">
        <h1>VNZ TECH</h1>
        <p>WE BUY. WE RECOVER. WE REVALUE.</p>
    </div>
    <div class="login-card">
        <h2>Forgot Password?</h2>
        <p class="subtitle">
            Enter the verified email address on your seller account. We will email you a secure, one-time link to create a new password.
        </p>
        <?php if ($message !== ''): ?>
            <div class="message <?= $isError ? 'error' : 'success' ?>" role="<?= $isError ? 'alert' : 'status' ?>">
                <?= htmlspecialchars($message, ENT_QUOTES, 'UTF-8') ?>
            </div>
        <?php endif; ?>
        <form method="POST" action="/VNZ-TECH/account/forgot_password.php">
            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8') ?>">
            <input type="hidden" name="recovery_method" value="email">
            <div class="field">
                <label for="email">Email Address</label>
                <input type="email" id="email" name="email" maxlength="190" autocomplete="email" required>
            </div>
            <button type="submit" class="login-button">SEND RESET LINK</button>
        </form>
        <details class="seller-totp-recovery" <?= $isTotpRecovery ? 'open' : '' ?>>
            <summary>Forgot your email? Use your mobile number and 2FA</summary>
            <p class="subtitle">
                Enter the mobile number registered to your seller account and a current authenticator code. This works only if you previously enabled 2FA.
            </p>
            <form method="POST" action="/VNZ-TECH/account/forgot_password.php">
                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8') ?>">
                <input type="hidden" name="recovery_method" value="totp">
                <div class="field">
                    <label for="recovery_mobile">REGISTERED MOBILE NUMBER</label>
                    <input type="tel" id="recovery_mobile" name="mobile" inputmode="tel" autocomplete="tel" required>
                </div>
                <div class="field">
                    <label for="recovery_code">AUTHENTICATOR CODE</label>
                    <input type="text" id="recovery_code" name="two_factor_code" inputmode="numeric" pattern="[0-9]{6}" maxlength="6" autocomplete="one-time-code" required>
                </div>
                <div class="field">
                    <label for="recovery_new_password">NEW PASSWORD</label>
                    <input type="password" id="recovery_new_password" name="new_password" minlength="8" autocomplete="new-password" required>
                </div>
                <div class="field">
                    <label for="recovery_confirm_password">CONFIRM NEW PASSWORD</label>
                    <input type="password" id="recovery_confirm_password" name="confirm_password" minlength="8" autocomplete="new-password" required>
                </div>
                <button type="submit" class="login-button">VERIFY CODE &amp; RESET PASSWORD</button>
            </form>
        </details>
    </div>
</div>
</body>
</html>
