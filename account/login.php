<?php

session_start();

require_once __DIR__ . '/../backend/session.php';

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
$verification = $_GET['verification'] ?? '';

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
        href="/VNZ-TECH/assets/css/seller_login.css"
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
            Welcome Back
        </h2>

        <p class="subtitle">
            Log in to manage your submissions, offers,
            transactions, and account.
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
            action="/VNZ-TECH/backend/login.php"
            method="POST"
            autocomplete="on"
        >

            <div class="field">

                <label for="email">
                    Email Address
                </label>

                <input
                    type="email"
                    id="email"
                    name="email"
                    maxlength="190"
                    autocomplete="email"
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
                    href="#"
                    class="forgot"
                    onclick="return false;"
                >
                    Forgot Password?
                </a>

            </div>

            <button
                type="submit"
                class="login-button"
            >
                LOG IN
            </button>

        </form>

        <div class="register-link">

            Don't have an account?

            <a href="register.php">
                Create one
            </a>

        </div>

    </div>

</div>

</body>
</html>