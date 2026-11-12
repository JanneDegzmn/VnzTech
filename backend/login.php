<?php
declare(strict_types=1);

require_once __DIR__ . '/database.php';
require_once __DIR__ . '/session.php';
require_once __DIR__ . '/csrf.php';
require_once __DIR__ . '/chat_presence_store.php';

$chatAuthenticationStartedAt = microtime(true);

function isAjaxRequest(): bool
{
    $requestedWith = $_SERVER['HTTP_X_REQUESTED_WITH'] ?? '';
    $accept = $_SERVER['HTTP_ACCEPT'] ?? '';

    return (
        strtolower((string) $requestedWith) === 'xmlhttprequest'
        || str_contains(strtolower($accept), 'application/json')
    );
}

function sendJsonResponse(array $payload, int $statusCode = 200): void
{
    http_response_code($statusCode);
    header('Content-Type: application/json; charset=UTF-8');
    echo json_encode($payload, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES);
    exit;
}

function sendLoginFailure(string $message, int $statusCode): void
{
    if (isAjaxRequest()) {
        sendJsonResponse([
            'success' => false,
            'message' => $message,
        ], $statusCode);
    }

    header('Location: /VNZ-TECH/account/login.php?error=' . urlencode($message));
    exit;
}

function redirectToDashboard(): void
{
    $reminder = !empty($_SESSION['seller_2fa_reminder'])
        ? '?security_reminder=1'
        : '';
    header('Location: /VNZ-TECH/account/dashboard.php' . $reminder);
    exit;
}

if (isset($_SESSION['user_logged_in']) && $_SESSION['user_logged_in'] === true) {
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        if (isAjaxRequest()) {
            sendJsonResponse([
                'success' => true,
                'message' => 'Already logged in.',
                'redirect' => '/VNZ-TECH/account/dashboard.php'
            ]);
        }

        redirectToDashboard();
    }

    redirectToDashboard();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verifyCsrfToken($_POST['csrf_token'] ?? null)) {
        sendLoginFailure(
            'Your session token expired. Refresh the page and try again.',
            419
        );
    }

    $login = strtolower(trim((string) ($_POST['login'] ?? $_POST['email'] ?? '')));
    $password = (string) ($_POST['password'] ?? '');

    if ($login === '' || $password === '') {
        sendLoginFailure(
            'Please enter your email or registered mobile number and password.',
            400
        );
    }

    try {
        $selectedFields = "
            SELECT
                id,
                full_name,
                email,
                password_hash,
                is_active,
                totp_secret,
                totp_enabled_at,
                totp_last_used_counter,
                totp_reminder_dismissed,
                totp_login_required
            FROM users
        ";
        if (filter_var($login, FILTER_VALIDATE_EMAIL)) {
            $stmt = $pdo->prepare($selectedFields . ' WHERE email = ? LIMIT 1');
            $stmt->execute([$login]);
            $user = $stmt->fetch();
        } else {
            $mobile = preg_replace('/\D+/', '', $login);
            if (strlen($mobile) < 8 || strlen($mobile) > 15) {
                sendLoginFailure(
                    'Enter a valid email address or registered mobile number.',
                    422
                );
            }
            $stmt = $pdo->prepare($selectedFields . "
                WHERE REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(mobile, ' ', ''), '-', ''), '(', ''), ')', ''), '+', '') = ?
                LIMIT 1
            ");
            $stmt->execute([$mobile]);
            $matches = $stmt->fetchAll();
            $user = count($matches) === 1 ? $matches[0] : null;
        }

        if (!$user) {
            sendLoginFailure('Invalid email or password.', 401);
        }

        if ((int) $user['is_active'] !== 1) {
            sendLoginFailure('Your account is currently inactive.', 403);
        }

        if (!password_verify($password, (string) $user['password_hash'])) {
            sendLoginFailure('Invalid email or password.', 401);
        }

        if (
            !empty($user['totp_enabled_at'])
            && !empty($user['totp_secret'])
            && (int) $user['totp_login_required'] === 1
        ) {
            $_SESSION['seller_2fa_login_user_id'] = (int) $user['id'];
            $_SESSION['seller_2fa_login_started_at'] = time();
            $_SESSION['remember_seller_after_2fa'] =
                ($_POST['remember'] ?? '') === '1';
            $challengeRedirect = '/VNZ-TECH/account/login.php?two_factor=1';

            if (isAjaxRequest()) {
                sendJsonResponse([
                    'success' => false,
                    'requires_two_factor' => true,
                    'message' => 'Enter the 6-digit code from your authenticator app.',
                    'redirect' => $challengeRedirect,
                ], 200);
            }

            header('Location: ' . $challengeRedirect);
            exit;
        }

        session_regenerate_id(true);
        clearAdminSessionState();
        $_SESSION['user_logged_in'] = true;
        $_SESSION['user_id'] = (int) $user['id'];
        $_SESSION['user_email'] = $user['email'];
        $_SESSION['user_name'] = $user['full_name'];
        if (($_POST['remember'] ?? '') === '1') {
            vnzIssueRememberLoginToken($pdo, 'seller', (int) $user['id']);
        } else {
            vnzRevokeRememberLoginCookie($pdo);
        }
        chatRegisterPresenceSession('seller', (int) $user['id']);
        chatLogAuthenticationTiming('seller_login', $chatAuthenticationStartedAt);
        if (
            (int) $user['totp_reminder_dismissed'] !== 1
            && (
                empty($user['totp_enabled_at'])
                || (int) $user['totp_login_required'] !== 1
            )
        ) {
            $_SESSION['seller_2fa_reminder'] = true;
        }

        if (isAjaxRequest()) {
            sendJsonResponse([
                'success' => true,
                'message' => 'Login successful.',
                'redirect' => '/VNZ-TECH/account/dashboard.php' .
                    (!empty($_SESSION['seller_2fa_reminder']) ? '?security_reminder=1' : '')
            ]);
        }

        redirectToDashboard();
    } catch (Throwable $e) {
        if (isset($user['id'])) {
            try {
                chatEndPresenceSession('seller', (int) $user['id']);
            } catch (Throwable $presenceError) {
                error_log('Seller login presence rollback failed: ' . $presenceError->getMessage());
            }
        }
        unset(
            $_SESSION['user_logged_in'],
            $_SESSION['user_id'],
            $_SESSION['user_email'],
            $_SESSION['user_name'],
            $_SESSION['seller_2fa_reminder']
        );
        error_log('Seller login presence registration failed: ' . $e->getMessage());
        if (isAjaxRequest()) {
            sendJsonResponse([
                'success' => false,
                'message' => 'Something went wrong while logging in.'
            ], 500);
        }

        header('Location: /VNZ-TECH/account/login.php?error=' . urlencode('Something went wrong while logging in.'));
        exit;
    }
}

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>Login | VNZ TECH</title>

    <link
        rel="stylesheet"
        href="/VNZ-TECH/assets/css/seller_login.css?v=20261006-seller-2fa"
    >
    <link
        rel="stylesheet"
        href="/VNZ-TECH/assets/css/password_visibility.css?v=<?= filemtime(__DIR__ . '/../assets/css/password_visibility.css') ?>"
    >
</head>

<body>

<div class="login-page">

    <div class="login-card">

        <div class="login-header">

            <a
                href="/VNZ-TECH/index.php"
                class="brand-logo"
            >
                VNZ <span>TECH</span>
            </a>

            <h1>WELCOME BACK</h1>

            <p>
                Login to your VNZ TECH seller account.
            </p>

        </div>


        <form
            id="loginForm"
            method="POST"
            action="/VNZ-TECH/backend/login.php"
            novalidate
        >

            <div
                id="loginMessage"
                class="login-message"
                style="display:none;"
            ></div>

            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(generateCsrfToken(), ENT_QUOTES, 'UTF-8') ?>">

            <div class="form-group">

                <label for="login">
                    EMAIL OR MOBILE NUMBER
                </label>

                <input
                    type="text"
                    id="login"
                    name="login"
                    placeholder="Enter your email or registered mobile"
                    autocomplete="username"
                    required
                >

            </div>


            <div class="form-group">

                <label for="password">
                    PASSWORD
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


            <button
                type="submit"
                id="loginButton"
                class="login-button"
            >
                LOGIN
            </button>

        </form>


        <div class="login-footer">

            <a
                href="/VNZ-TECH/account/forgot_password.php"
                class="forgot-password"
            >
                Forgot Password?
            </a>

            <div class="register-text">

                Don't have an account?

                <a
                    href="/VNZ-TECH/account/register.php"
                >
                    CREATE ACCOUNT
                </a>

            </div>

            <a
                href="/VNZ-TECH/index.php"
                class="back-home"
            >
                ← Back to Home
            </a>

        </div>

    </div>

</div>


<script>

document.addEventListener('DOMContentLoaded', function () {

    const form = document.getElementById('loginForm');
    const messageBox = document.getElementById('loginMessage');
    const loginButton = document.getElementById('loginButton');

    if (!form) {
        return;
    }


    form.addEventListener('submit', async function (event) {

        event.preventDefault();

        let redirecting = false;

        VNZLoader.show();

        messageBox.style.display = 'none';
        messageBox.textContent = '';
        messageBox.className = 'login-message';


        const login = document
            .getElementById('login')
            .value
            .trim();

        const password = document
            .getElementById('password')
            .value;


        if (login === '' || password === '') {

            showMessage(
                'Please enter your email or registered mobile number and password.',
                'error'
            );

            return;
        }


        loginButton.disabled = true;
        loginButton.textContent = 'LOGGING IN...';


        try {

            const formData = new FormData(form);


            const response = await fetch(
                '/VNZ-TECH/backend/login.php',
                {
                    method: 'POST',
                    body: formData,
                    headers: {
                        'X-Requested-With': 'XMLHttpRequest'
                    },
                    credentials: 'same-origin'
                }
            );


            const text = await response.text();


            let data;

            try {

                data = JSON.parse(text);

            } catch (jsonError) {

                console.error(
                    'Invalid JSON response:',
                    text
                );

                showMessage(
                    'The server returned an invalid response.',
                    'error'
                );

                return;
            }


            if (data.success === true) {

                redirecting = true;

                showMessage(
                    data.message || 'Login successful.',
                    'success'
                );


                const redirectUrl =
                    data.redirect ||
                    '/VNZ-TECH/account/dashboard.php';


                setTimeout(function () {

                    window.location.href = redirectUrl;

                }, 300);


                return;
            }

            if (data.requires_two_factor === true && data.redirect) {
                window.location.href = data.redirect;
                return;
            }


            showMessage(
                data.message ||
                'Invalid email or password.',
                'error'
            );


        } catch (error) {

            console.error(
                'Login error:',
                error
            );

            showMessage(
                'Unable to connect to the server. Please try again.',
                'error'
            );

        } finally {

            if (!redirecting) {
                VNZLoader.hide();
            }

            loginButton.disabled = false;
            loginButton.textContent = 'LOGIN';

        }

    });


    function showMessage(message, type) {

        messageBox.textContent = message;

        messageBox.className =
            'login-message ' + type;

        messageBox.style.display = 'block';

    }

});

</script>

<script src="/VNZ-TECH/assets/js/password-visibility.js?v=<?= filemtime(__DIR__ . '/../assets/js/password-visibility.js') ?>" defer></script>
</body>
</html>
