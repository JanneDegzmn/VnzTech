<?php
declare(strict_types=1);

require_once __DIR__ . '/../backend/database.php';
require_once __DIR__ . '/../backend/session.php';
require_once __DIR__ . '/../backend/csrf.php';

$token = (string) ($_GET['token'] ?? $_POST['token'] ?? '');
$validTokenFormat = preg_match('/\A[a-f0-9]{64}\z/', $token) === 1;
$message = '';
$isError = false;
$csrfToken = generateCsrfToken();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verifyCsrfToken($_POST['csrf_token'] ?? null)) {
        http_response_code(419);
        $message = 'Your session expired. Refresh the page and try again.';
        $isError = true;
    } elseif (!$validTokenFormat) {
        http_response_code(400);
        $message = 'This reset link is invalid or expired. Request a new link to continue.';
        $isError = true;
    } else {
        $newPassword = (string) ($_POST['new_password'] ?? '');
        $confirmPassword = (string) ($_POST['confirm_password'] ?? '');

        if (strlen($newPassword) < 8) {
            http_response_code(422);
            $message = 'Your new password must be at least 8 characters.';
            $isError = true;
        } elseif ($newPassword !== $confirmPassword) {
            http_response_code(422);
            $message = 'The new password and confirmation do not match.';
            $isError = true;
        } else {
            try {
                $pdo->beginTransaction();
                $tokenStmt = $pdo->prepare("
                    SELECT id, user_id
                    FROM seller_password_reset_attempts
                    WHERE token_hash = ?
                      AND expires_at > NOW()
                      AND used_at IS NULL
                      AND user_id IS NOT NULL
                    LIMIT 1
                    FOR UPDATE
                ");
                $tokenStmt->execute([hash('sha256', $token)]);
                $reset = $tokenStmt->fetch();

                if (!$reset) {
                    $pdo->rollBack();
                    http_response_code(400);
                    $message = 'This reset link is invalid, expired, or already used. Request a new link to continue.';
                    $isError = true;
                } else {
                    $updateStmt = $pdo->prepare("
                        UPDATE users
                        SET password_hash = ?, updated_at = NOW()
                        WHERE id = ? AND is_active = 1
                    ");
                    $updateStmt->execute([
                        password_hash($newPassword, PASSWORD_DEFAULT),
                        (int) $reset['user_id']
                    ]);

                    if ($updateStmt->rowCount() !== 1) {
                        $pdo->rollBack();
                        http_response_code(400);
                        $message = 'This reset link is invalid or expired. Request a new link to continue.';
                        $isError = true;
                    } else {
                        $usedStmt = $pdo->prepare("
                            UPDATE seller_password_reset_attempts
                            SET used_at = NOW(), token_hash = NULL
                            WHERE user_id = ?
                              AND token_hash IS NOT NULL
                              AND used_at IS NULL
                        ");
                        $usedStmt->execute([(int) $reset['user_id']]);
                        $pdo->commit();
                        header('Location: /VNZ-TECH/account/login.php?password_reset=1');
                        exit;
                    }
                }
            } catch (Throwable $e) {
                if ($pdo->inTransaction()) {
                    $pdo->rollBack();
                }
                error_log('Seller password reset failed: ' . $e->getMessage());
                http_response_code(500);
                $message = 'Unable to reset your password right now. Please try again later.';
                $isError = true;
            }
        }
    }
}

$tokenIsUsable = false;
if ($validTokenFormat && http_response_code() !== 500) {
    try {
        $checkStmt = $pdo->prepare("
            SELECT 1
            FROM seller_password_reset_attempts
            WHERE token_hash = ?
              AND expires_at > NOW()
              AND used_at IS NULL
              AND user_id IS NOT NULL
            LIMIT 1
        ");
        $checkStmt->execute([hash('sha256', $token)]);
        $tokenIsUsable = (bool) $checkStmt->fetchColumn();
    } catch (Throwable $e) {
        error_log('Seller password reset link lookup failed: ' . $e->getMessage());
        http_response_code(500);
        $message = 'Unable to validate this reset link right now. Please try again later.';
        $isError = true;
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <?php include __DIR__ . '/../includes/loader-head.php'; ?>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Reset Password | VNZ TECH</title>
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
        <h2>Create a New Password</h2>
        <p class="subtitle">Choose a new password with at least 8 characters.</p>
        <?php if ($message !== ''): ?>
            <div class="message <?= $isError ? 'error' : 'success' ?>" role="<?= $isError ? 'alert' : 'status' ?>">
                <?= htmlspecialchars($message, ENT_QUOTES, 'UTF-8') ?>
            </div>
        <?php endif; ?>
        <?php if ($tokenIsUsable): ?>
            <form method="POST" action="/VNZ-TECH/account/reset_password.php">
                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8') ?>">
                <input type="hidden" name="token" value="<?= htmlspecialchars($token, ENT_QUOTES, 'UTF-8') ?>">
                <div class="field">
                    <label for="new_password">New Password</label>
                    <input type="password" id="new_password" name="new_password" minlength="8" autocomplete="new-password" required>
                </div>
                <div class="field">
                    <label for="confirm_password">Confirm New Password</label>
                    <input type="password" id="confirm_password" name="confirm_password" minlength="8" autocomplete="new-password" required>
                </div>
                <button type="submit" class="login-button">RESET PASSWORD</button>
            </form>
        <?php elseif ($message === ''): ?>
            <div class="message error" role="alert">
                This reset link is invalid, expired, or already used. Request a new link to continue.
            </div>
        <?php endif; ?>
        <div class="register-link">
            <a href="/VNZ-TECH/account/forgot_password.php">Request another reset link</a>
        </div>
    </div>
</div>
</body>
</html>
