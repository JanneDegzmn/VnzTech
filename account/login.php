<?php

session_start();

require_once __DIR__ . '/../backend/session.php';
require_once __DIR__ . '/../backend/csrf.php';

if (
    isset($_SESSION['user_logged_in']) &&
    $_SESSION['user_logged_in'] === true &&
    !empty($_SESSION['user_id'])
) {
    header('Location: /VNZ-TECH/account/dashboard.php');
    exit;
}

$error = $_GET['error'] ?? '';
$success = isset($_GET['success']) && $_GET['success'] === '1';
$passwordReset = isset($_GET['password_reset']) && $_GET['password_reset'] === '1';
$verification = $_GET['verification'] ?? '';
$twoFactorChallenge =
    isset($_GET['two_factor'])
    && !empty($_SESSION['seller_2fa_login_user_id'])
    && time() - (int) ($_SESSION['seller_2fa_login_started_at'] ?? 0) <= 300;

if (isset($_GET['cancel_two_factor'])) {
    unset($_SESSION['seller_2fa_login_user_id'], $_SESSION['seller_2fa_login_started_at']);
    header('Location: /VNZ-TECH/account/login.php');
    exit;
}

if (isset($_GET['two_factor']) && !$twoFactorChallenge) {
    unset($_SESSION['seller_2fa_login_user_id'], $_SESSION['seller_2fa_login_started_at']);
    header('Location: /VNZ-TECH/account/login.php?error=' . urlencode('Your sign-in verification expired. Please log in again.'));
    exit;
}

$csrfToken = generateCsrfToken();

?>

<!DOCTYPE html>
<html lang="en">

<head>

    <?php include __DIR__ . '/../includes/loader-head.php'; ?>

    <meta charset="UTF-8">

    <meta
        name="viewport"
        content="width=device-width, initial-scale=1.0"
    >

    <title>Login | VNZ TECH</title>

    <link
        rel="stylesheet"
        href="/VNZ-TECH/assets/css/seller_login.css?v=20261006-seller-2fa"
    >

</head>

<body>

<?php include __DIR__ . '/../includes/loader-body.php'; ?>

<div class="login-wrapper">

        <div class="page-back">
            <a href="/VNZ-TECH/sell.php">
                ← Back to Home
            </a>
        </div>    

    <div class="brand">

        <h1>VNZ TECH</h1>

        <p>
            WE BUY. WE RECOVER. WE REVALUE.
        </p>

    </div>

    <div class="login-card">

        <h2>
            <?= $twoFactorChallenge ? 'Verify Your Sign-In' : 'Welcome Back' ?>
        </h2>

        <p class="subtitle">
            <?= $twoFactorChallenge
                ? 'Enter the current 6-digit code from your authenticator app.'
                : 'Log in to manage your submissions, offers, transactions, and account.' ?>
        </p>

        <?php if ($error !== ''): ?>

            <div class="message error">

                <?= htmlspecialchars(
                    $error,
                    ENT_QUOTES,
                    'UTF-8'
                ) ?>

            </div>

        <?php endif; ?>

        <?php if ($success): ?>

            <div class="message success">
                Account created successfully.
                You may now log in.
            </div>

        <?php endif; ?>

        <?php if ($passwordReset): ?>

            <div class="message success" role="status">
                Your password has been reset. You may now log in with your new password.
            </div>

        <?php endif; ?>

        <?php if ($verification === 'success'): ?>

            <div class="message success">
                Your email address is verified. You may now log in.
            </div>

        <?php elseif ($verification === 'invalid'): ?>

            <div class="message error">
                This verification link is invalid, expired, or has already been used.
                Log in and request a new link from Settings.
            </div>

        <?php endif; ?>

        <form
            action="<?= $twoFactorChallenge ? '/VNZ-TECH/backend/verify_seller_2fa.php' : '/VNZ-TECH/backend/login.php' ?>"
            method="POST"
            autocomplete="on"
        >

            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8') ?>">

            <?php if ($twoFactorChallenge): ?>
                <div class="field">
                    <label for="two_factor_code">AUTHENTICATOR CODE</label>
                    <input type="text" id="two_factor_code" name="two_factor_code" inputmode="numeric" pattern="[0-9]{6}" maxlength="6" autocomplete="one-time-code" required autofocus>
                </div>
            <?php else: ?>
            <div class="field">

                <label for="login">
                    Email or Mobile Number
                </label>

                <input
                    type="text"
                    id="login"
                    name="login"
                    maxlength="190"
                    autocomplete="username"
                    required
                >

            </div>

            <div class="field">

                <label for="password">
                    Password
                </label>

                <input
                    type="password"
                    id="password"
                    name="password"
                    autocomplete="current-password"
                    required
                >

            </div>

            <div class="extra-row">

                <label class="remember">

                    <input
                        type="checkbox"
                        name="remember"
                        value="1"
                    >

                    <span>
                        Remember me
                    </span>

                </label>

                <a
                    href="/VNZ-TECH/account/forgot_password.php"
                    class="forgot"
                >
                    Forgot Password?
                </a>

            </div>
            <?php endif; ?>

            <button
                type="submit"
                class="login-button"
            >
                <?= $twoFactorChallenge ? 'VERIFY CODE' : 'LOG IN' ?>
            </button>

        </form>

        <?php if ($twoFactorChallenge): ?>
            <div class="register-link">
                <a href="/VNZ-TECH/account/login.php?cancel_two_factor=1">Cancel and return to login</a>
            </div>
        <?php endif; ?>

        <div class="register-link">

            Don't have an account?

            <a href="register.php">
                Create one
            </a>

        </div>

    </div>

</div>

<?php if ($error !== ''): ?>
    <script>
        document.addEventListener('DOMContentLoaded', function () {
            const errorMessage = document.querySelector('.message.error');
            if (errorMessage) {
                errorMessage.hidden = true;
            }

            if (window.VNZDialog) {
                window.VNZDialog.alert({
                    title: 'Login Failed',
                    message: <?= json_encode($error, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>,
                    confirmLabel: 'Try Again',
                    danger: true
                });
            }
        });
    </script>
<?php endif; ?>

</body>
</html>