<?php


require_once __DIR__ . '/site_status.php';
vnzEnforceSiteMaintenance('auto', false, true, 'account_settings');
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
        'message' => 'Your session has expired. Please log in again.',
    ]);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    header('Allow: POST');
    echo json_encode([
        'success' => false,
        'message' => 'Invalid request method.',
    ]);
    exit;
}

if (!verifyCsrfToken($_POST['csrf_token'] ?? null)) {
    http_response_code(419);
    echo json_encode([
        'success' => false,
        'message' => 'Your session expired. Refresh the page and try again.',
    ]);
    exit;
}

$language = $_POST['language'] ?? '';
if (!is_string($language) || !in_array($language, ['en', 'fil'], true)) {
    http_response_code(422);
    echo json_encode([
        'success' => false,
        'message' => 'Choose English or Filipino.',
    ]);
    exit;
}

try {
    $stmt = $pdo->prepare('UPDATE users SET language = ?, updated_at = NOW() WHERE id = ? AND is_active = 1');
    $stmt->execute([$language, (int) $_SESSION['user_id']]);

    if ($stmt->rowCount() === 0) {
        $checkStmt = $pdo->prepare('SELECT id FROM users WHERE id = ? AND is_active = 1 LIMIT 1');
        $checkStmt->execute([(int) $_SESSION['user_id']]);
        if (!$checkStmt->fetchColumn()) {
            http_response_code(404);
            echo json_encode([
                'success' => false,
                'message' => 'Seller account not found.',
            ]);
            exit;
        }
    }

    $_SESSION['user_language'] = $language;
    echo json_encode([
        'success' => true,
        'language' => $language,
        'message' => 'Language preference saved.',
    ]);
} catch (Throwable $e) {
    error_log('Unable to update seller language preference: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Unable to save your language preference. Please try again.',
    ]);
}
