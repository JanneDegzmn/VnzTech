<?php


require_once __DIR__ . '/site_status.php';
vnzEnforceSiteMaintenance('auto', false, true, 'submissions');
require_once __DIR__ . '/database.php';
require_once __DIR__ . '/session.php';

header('Content-Type: application/json; charset=utf-8');


if (
    $_SERVER['REQUEST_METHOD'] !== 'POST'
) {

    http_response_code(405);

    echo json_encode([
        'success' => false,
        'message' => 'Method not allowed.'
    ]);

    exit;
}


/*
|--------------------------------------------------------------------------
| SELLER SESSION
|--------------------------------------------------------------------------
*/

if (
    empty($_SESSION['user_logged_in']) ||
    empty($_SESSION['user_id'])
) {

    http_response_code(401);

    echo json_encode([
        'success' => false,
        'message' => 'Not logged in.'
    ]);

    exit;
}


$userId =
    (int) $_SESSION['user_id'];


if (
    $userId <= 0
) {

    http_response_code(401);

    echo json_encode([
        'success' => false,
        'message' => 'Invalid session.'
    ]);

    exit;
}


/*
|--------------------------------------------------------------------------
| REQUEST
|--------------------------------------------------------------------------
*/

$submissionId =
    isset($_POST['submission_id'])
        ? (int) $_POST['submission_id']
        : 0;


if (
    $submissionId <= 0
) {

    http_response_code(422);

    echo json_encode([
        'success' => false,
        'message' => 'Invalid submission.'
    ]);

    exit;
}


/*
|--------------------------------------------------------------------------
| UPDATE — OWNERSHIP CHECK BAKED INTO THE WHERE CLAUSE
|--------------------------------------------------------------------------
|
| A seller can only mark their OWN submissions as seen. Even if
| submission_id belongs to someone else, this simply updates 0 rows
| — no error, no leak of whether that submission exists.
|
*/

try {

    $stmt =
        $pdo->prepare("
            UPDATE submissions
            SET result_seen_by_seller = 1
            WHERE id = ?
              AND user_id = ?
            LIMIT 1
        ");

    $stmt->execute([
        $submissionId,
        $userId
    ]);

    echo json_encode([
        'success' => true,
        'message' => 'Marked as seen.'
    ]);

    exit;

} catch (Throwable $e) {

    http_response_code(500);

    echo json_encode([
        'success' => false,
        'message' => 'Unable to update.'
    ]);

    exit;

}