<?php
declare(strict_types=1);

require_once __DIR__ . '/database.php';
require_once __DIR__ . '/session.php';

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

function redirectToDashboard(): void
{
    header('Location: /VNZ-TECH/account/dashboard.php');
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
    $email = strtolower(trim((string) ($_POST['email'] ?? '')));
    $password = (string) ($_POST['password'] ?? '');

    if ($email === '' || $password === '') {
        sendJsonResponse([
            'success' => false,
            'message' => 'Please enter your email and password.'
        ], 400);
    }

    try {
        $stmt = $pdo->prepare("
            SELECT
                id,
                full_name,
                email,
                password_hash,
                is_active
            FROM users
            WHERE email = ?
            LIMIT 1
        ");

        $stmt->execute([$email]);
        $user = $stmt->fetch();

        if (!$user) {
            sendJsonResponse([
                'success' => false,
                'message' => 'Invalid email or password.'
            ], 401);
        }

        if ((int) $user['is_active'] !== 1) {
            sendJsonResponse([
                'success' => false,
                'message' => 'Your account is currently inactive.'
            ], 403);
        }

        if (!password_verify($password, (string) $user['password_hash'])) {
            sendJsonResponse([
                'success' => false,
                'message' => 'Invalid email or password.'
            ], 401);
        }

        session_regenerate_id(true);
        $_SESSION['user_logged_in'] = true;
        $_SESSION['user_id'] = (int) $user['id'];
        $_SESSION['user_email'] = $user['email'];
        $_SESSION['user_name'] = $user['full_name'];

        if (isAjaxRequest()) {
            sendJsonResponse([
                'success' => true,
                'message' => 'Login successful.',
                'redirect' => '/VNZ-TECH/account/dashboard.php'
            ]);
        }

        redirectToDashboard();
    } catch (Throwable $e) {
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
        href="/VNZ-TECH/assets/css/seller_login.css"
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


            <div class="form-group">

                <label for="email">
                    EMAIL ADDRESS
                </label>

                <input
                    type="email"
                    id="email"
                    name="email"
                    placeholder="Enter your email"
                    autocomplete="email"
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
                href="#"
                class="forgot-password"
                onclick="return false;"
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


        const email = document
            .getElementById('email')
            .value
            .trim();

        const password = document
            .getElementById('password')
            .value;


        if (email === '' || password === '') {

            showMessage(
                'Please enter your email and password.',
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

</body>
</html>

