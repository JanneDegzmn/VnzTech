<?php


require_once __DIR__ . '/site_status.php';
vnzEnforceSiteMaintenance('auto', false, true, 'account_settings');
require_once __DIR__ . '/database.php';
require_once __DIR__ . '/session.php';
require_once __DIR__ . '/csrf.php';
require_once __DIR__ . '/remember_login.php';

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

$userId = (int) $_SESSION['user_id'];

if ($userId <= 0) {

    http_response_code(401);

    echo json_encode([
        'success' => false,
        'message' => 'Invalid user session.'
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


$currentPassword =
    (string) ($_POST['current_password'] ?? '');

$newPassword =
    (string) ($_POST['new_password'] ?? '');

$confirmPassword =
    (string) ($_POST['confirm_password'] ?? '');


if (
    $currentPassword === '' ||
    $newPassword === '' ||
    $confirmPassword === ''
) {

    http_response_code(422);

    echo json_encode([
        'success' => false,
        'message' => 'All password fields are required.'
    ]);

    exit;
}


if (strlen($newPassword) < 8) {

    http_response_code(422);

    echo json_encode([
        'success' => false,
        'message' => 'New password must be at least 8 characters.'
    ]);

    exit;
}


if ($newPassword !== $confirmPassword) {

    http_response_code(422);

    echo json_encode([
        'success' => false,
        'message' => 'New password and confirmation do not match.'
    ]);

    exit;
}


try {

    $stmt = $pdo->prepare("
        SELECT
            id,
            password_hash
        FROM users
        WHERE id = ?
        LIMIT 1
    ");

    $stmt->execute([
        $userId
    ]);

    $user = $stmt->fetch();


    if (!$user) {

        http_response_code(404);

        echo json_encode([
            'success' => false,
            'message' => 'User account not found.'
        ]);

        exit;

    }


    if (
        !password_verify(
            $currentPassword,
            $user['password_hash']
        )
    ) {

        http_response_code(422);

        echo json_encode([
            'success' => false,
            'message' => 'Current password is incorrect.'
        ]);

        exit;

    }


    if (
        password_verify(
            $newPassword,
            $user['password_hash']
        )
    ) {

        http_response_code(422);

        echo json_encode([
            'success' => false,
            'message' => 'New password must be different from your current password.'
        ]);

        exit;

    }


    $newHash =
        password_hash(
            $newPassword,
            PASSWORD_DEFAULT
        );


    $updateStmt = $pdo->prepare("
        UPDATE users
        SET
            password_hash = ?,
            updated_at = NOW()
        WHERE id = ?
    ");

    $updateStmt->execute([
        $newHash,
        $userId
    ]);
    vnzRevokeRememberLoginTokens($pdo, 'seller', $userId);


    echo json_encode([
        'success' => true,
        'message' => 'Password updated successfully.'
    ]);

} catch (Throwable $e) {

    http_response_code(500);

    echo json_encode([
        'success' => false,
        'message' => 'Unable to update password. Please try again.'
    ]);

}