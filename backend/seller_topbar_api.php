<?php
require_once __DIR__ . '/database.php';
require_once __DIR__ . '/session.php';
require_once __DIR__ . '/seller_topbar.php';
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store, private');
header('X-Content-Type-Options: nosniff');
if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'GET') {
    http_response_code(405);
    header('Allow: GET');
    echo json_encode(['success' => false, 'message' => 'Method not allowed.']);
    exit;
}
$actorId = (int) ($_SESSION['user_id'] ?? 0);
if (empty($_SESSION['user_logged_in']) || $actorId <= 0) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Please sign in again.']);
    exit;
}
try {
    $query = $pdo->prepare('SELECT full_name, profile_picture, language, is_active FROM users WHERE id = ? LIMIT 1');
    $query->execute([$actorId]);
    $actor = $query->fetch(PDO::FETCH_ASSOC);
    if (!$actor || (int) $actor['is_active'] !== 1) {
        http_response_code(401);
        echo json_encode(['success' => false, 'message' => 'Please sign in again.']);
        exit;
    }
    echo json_encode([
        'success' => true,
        'profile' => vnzSellerTopbarProfile($actor),
        'language' => ($actor['language'] ?? '') === 'fil' ? 'fil' : 'en',
        'updates' => vnzSellerTopbarUpdates($pdo, $actorId)
    ], JSON_INVALID_UTF8_SUBSTITUTE);
} catch (Throwable $error) {
    http_response_code(503);
    echo json_encode(['success' => false, 'message' => 'Updates are temporarily unavailable. Please try again.']);
}
