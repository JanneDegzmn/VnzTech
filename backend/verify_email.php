<?php

require_once __DIR__ . '/database.php';
require_once __DIR__ . '/session.php';

$token = $_GET['token'] ?? '';

if (
    !is_string($token) ||
    !preg_match('/\A[a-f0-9]{64}\z/', $token)
) {
    header('Location: /VNZ-TECH/account/login.php?verification=invalid');
    exit;
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
        header('Location: /VNZ-TECH/account/login.php?verification=invalid');
        exit;
    }

    $updateStmt = $pdo->prepare("
        UPDATE users
        SET email_verified_at = COALESCE(email_verified_at, NOW())
        WHERE id = ?
    ");
    $updateStmt->execute([(int) $verification['user_id']]);

    $deleteStmt = $pdo->prepare("
        DELETE FROM email_verification_tokens
        WHERE user_id = ?
    ");
    $deleteStmt->execute([(int) $verification['user_id']]);

    $pdo->commit();

    if (
        !empty($_SESSION['user_logged_in']) &&
        (int) ($_SESSION['user_id'] ?? 0) === (int) $verification['user_id']
    ) {
        header('Location: /VNZ-TECH/account/dashboard.php?page=settings&verification=success');
        exit;
    }

    header('Location: /VNZ-TECH/account/login.php?verification=success');
    exit;
} catch (Throwable $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }

    error_log(
        'Email verification failed: ' .
        $e->getMessage()
    );

    http_response_code(500);
    echo 'Unable to verify your email address. Please try again later.';
}
