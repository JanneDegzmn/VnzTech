<?php

require_once __DIR__ . '/database.php';
require_once __DIR__ . '/session.php';
require_once __DIR__ . '/csrf.php';

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

$submissionUpdates =
    isset($_POST['submission_updates']) ? 1 : 0;

$offerUpdates =
    isset($_POST['offer_updates']) ? 1 : 0;

$transactionUpdates =
    isset($_POST['transaction_updates']) ? 1 : 0;

try {
    $stmt = $pdo->prepare("
        INSERT INTO seller_notification_preferences (
            user_id,
            submission_updates,
            offer_updates,
            transaction_updates
        )
        VALUES (?, ?, ?, ?)
        ON DUPLICATE KEY UPDATE
            submission_updates = VALUES(submission_updates),
            offer_updates = VALUES(offer_updates),
            transaction_updates = VALUES(transaction_updates),
            updated_at = CURRENT_TIMESTAMP
    ");

    $stmt->execute([
        $userId,
        $submissionUpdates,
        $offerUpdates,
        $transactionUpdates
    ]);

    echo json_encode([
        'success' => true,
        'message' => 'Notification preferences saved.'
    ]);
} catch (Throwable $e) {
    error_log(
        'Unable to save seller notification preferences: ' .
        $e->getMessage()
    );

    http_response_code(500);

    echo json_encode([
        'success' => false,
        'message' => 'Unable to save notification preferences. Please try again.'
    ]);
}
