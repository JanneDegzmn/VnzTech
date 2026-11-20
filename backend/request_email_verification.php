<?php


require_once __DIR__ . '/site_status.php';
vnzEnforceSiteMaintenance('auto', false);
require_once __DIR__ . '/database.php';
require_once __DIR__ . '/session.php';
require_once __DIR__ . '/csrf.php';
require_once __DIR__ . '/email_verification.php';
require_once __DIR__ . '/guest_submission_onboarding.php';

header('Content-Type: application/json; charset=UTF-8');

if (
    empty($_SESSION['user_logged_in']) ||
    empty($_SESSION['user_id'])
) {
    http_response_code(401);

    echo json_encode([
        'success' => false,
        'message' => 'Your session has expired.'
    ]);

    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);

    echo json_encode([
        'success' => false,
        'message' => 'Invalid request method.'
    ]);

    exit;
}

if (!verifyCsrfToken($_POST['csrf_token'] ?? null)) {
    http_response_code(419);

    echo json_encode([
        'success' => false,
        'message' => 'Your session has expired. Refresh the page and try again.'
    ]);

    exit;
}

$userId = (int) $_SESSION['user_id'];

if ($userId <= 0) {
    http_response_code(401);

    echo json_encode([
        'success' => false,
        'message' => 'Invalid user session.'
    ]);

    exit;
}

try {
    $userStmt = $pdo->prepare("
        SELECT email, email_verified_at
        FROM users
        WHERE id = ?
        LIMIT 1
    ");
    $userStmt->execute([$userId]);
    $user = $userStmt->fetch();

    if (!$user) {
        http_response_code(404);

        echo json_encode([
            'success' => false,
            'message' => 'User account not found.'
        ]);

        exit;
    }

    if (!empty($user['email_verified_at'])) {
        echo json_encode([
            'success' => true,
            'email_verified' => true,
            'message' => 'Your email address is already verified.'
        ]);

        exit;
    }

    sendEmailVerification(
        $pdo,
        $userId,
        (string) $user['email']
    );
    vnzRecordGuestSubmissionVerificationSent($_SESSION, $userId);

    echo json_encode([
        'success' => true,
        'message' => 'Verification email sent. Check your inbox and spam folder.'
    ]);
} catch (RuntimeException $e) {
    $isRateLimit =
        str_contains($e->getMessage(), 'wait one minute');

    http_response_code($isRateLimit ? 429 : 503);
    error_log(
        'Seller email verification request failed for user ' .
        $userId .
        ': ' .
        $e->getMessage()
    );

    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
} catch (Throwable $e) {
    http_response_code(500);
    error_log(
        'Seller email verification request failed for user ' .
        $userId .
        ': ' .
        $e->getMessage()
    );

    echo json_encode([
        'success' => false,
        'message' => 'Unable to send verification email. Please try again later.'
    ]);
}
