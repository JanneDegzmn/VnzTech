<?php

require_once __DIR__ . '/database.php';
require_once __DIR__ . '/session.php';
require_once __DIR__ . '/csrf.php';
require_once __DIR__ . '/guest_submission_link.php';

header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Referrer-Policy: no-referrer');

$scriptPath = (string) ($_SERVER['SCRIPT_NAME'] ?? '/backend/verify_email.php');
$appBasePath = rtrim(dirname(dirname($scriptPath)), '/\\');
$appBasePath = $appBasePath === '.' ? '' : $appBasePath;
$loginUrl = $appBasePath . '/account/login.php';
$verificationUrl = $appBasePath . '/backend/verify_email.php';
$token = $_SERVER['REQUEST_METHOD'] === 'POST'
    ? ($_POST['token'] ?? '')
    : ($_GET['token'] ?? '');

function redirectVerificationResult(string $loginUrl, string $result): never
{
    header('Location: ' . $loginUrl . '?verification=' . rawurlencode($result));
    exit;
}

if (
    !is_string($token) ||
    !preg_match('/\A[a-f0-9]{64}\z/', $token)
) {
    redirectVerificationResult($loginUrl, 'invalid');
}

if (!in_array($_SERVER['REQUEST_METHOD'] ?? 'GET', ['GET', 'POST'], true)) {
    http_response_code(405);
    header('Allow: GET, POST');
    exit('Method not allowed.');
}

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    try {
        $tokenStmt = $pdo->prepare("
            SELECT 1
            FROM email_verification_tokens
            WHERE token_hash = ?
              AND expires_at > NOW()
            LIMIT 1
        ");
        $tokenStmt->execute([hash('sha256', $token)]);

        if (!$tokenStmt->fetchColumn()) {
            redirectVerificationResult($loginUrl, 'invalid');
        }
    } catch (Throwable $e) {
        error_log('Email verification link check failed: ' . $e->getMessage());
        http_response_code(500);
        exit('Unable to check this verification link. Please try again later.');
    }

    $csrfToken = generateCsrfToken();
    ?>
    <!DOCTYPE html>
    <html lang="en">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <meta name="referrer" content="no-referrer">
        <title>Verify Email | VNZ TECH</title>
        <link rel="stylesheet" href="<?= htmlspecialchars($appBasePath . '/assets/css/seller_register.css', ENT_QUOTES, 'UTF-8') ?>">
    </head>
    <body>
        <main class="register-wrapper">
            <div class="brand">
                <h1>VNZ TECH</h1>
                <p>WE BUY. WE RECOVER. WE REVALUE.</p>
            </div>
            <section class="register-card">
                <h2>Verify your email</h2>
                <p class="subtitle">Confirm your email address to activate your VNZ TECH Seller account.</p>
                <form action="<?= htmlspecialchars($verificationUrl, ENT_QUOTES, 'UTF-8') ?>" method="POST">
                    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8') ?>">
                    <input type="hidden" name="token" value="<?= htmlspecialchars($token, ENT_QUOTES, 'UTF-8') ?>">
                    <button type="submit" class="submit-button">VERIFY EMAIL ADDRESS</button>
                </form>
            </section>
        </main>
    </body>
    </html>
    <?php
    exit;
}

if (!verifyCsrfToken($_POST['csrf_token'] ?? null)) {
    http_response_code(419);
    exit('This verification page expired. Reopen the latest verification email and try again.');
}

try {
    $pdo->beginTransaction();

    $tokenStmt = $pdo->prepare("
        SELECT user_id
        FROM email_verification_tokens
        WHERE token_hash = ?
          AND expires_at > NOW()
        LIMIT 1
        FOR UPDATE
    ");
    $tokenStmt->execute([hash('sha256', $token)]);
    $verification = $tokenStmt->fetch();

    if (!$verification) {
        $pdo->rollBack();
        redirectVerificationResult($loginUrl, 'invalid');
    }

    $userId = (int) $verification['user_id'];
    $updateStmt = $pdo->prepare("
        UPDATE users
        SET email_verified_at = COALESCE(email_verified_at, NOW())
        WHERE id = ?
    ");
    $updateStmt->execute([$userId]);

    linkVerifiedGuestSubmissions($pdo, $userId);

    $deleteStmt = $pdo->prepare("
        DELETE FROM email_verification_tokens
        WHERE user_id = ?
    ");
    $deleteStmt->execute([$userId]);

    $pdo->commit();

    if (
        !empty($_SESSION['user_logged_in']) &&
        (int) ($_SESSION['user_id'] ?? 0) === $userId
    ) {
        header('Location: ' . $appBasePath . '/account/dashboard.php?page=settings&verification=success');
        exit;
    }

    redirectVerificationResult($loginUrl, 'success');
} catch (Throwable $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }

    error_log('Email verification failed: ' . $e->getMessage());
    http_response_code(500);
    echo 'Unable to verify your email address. Please try again later.';
}
