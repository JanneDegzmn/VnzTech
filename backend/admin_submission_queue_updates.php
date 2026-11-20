<?php

require_once __DIR__ . '/site_status.php';
vnzEnforceSiteMaintenance('json', true);
require_once __DIR__ . '/database.php';
require_once __DIR__ . '/session.php';
require_once __DIR__ . '/auth.php';
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');
if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'GET') {
    header('Allow: GET');
    http_response_code(405);
    echo json_encode(['success' => false]);
    exit;
}
if (!isAdminLoggedIn()) {
    http_response_code(401);
    echo json_encode(['success' => false]);
    exit;
}
if (!hasPermission('view_submissions')) {
    http_response_code(403);
    echo json_encode(['success' => false]);
    exit;
}
$after = filter_var($_GET['after'] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 0]]);
$openingTotal = filter_var($_GET['opening_total'] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 0]]);
if ($after === false || $after === null || $openingTotal === false || $openingTotal === null) {
    http_response_code(400);
    echo json_encode(['success' => false]);
    exit;
}
session_write_close();
try {
    // Both aggregates share one statement snapshot. Total growth catches a lower
    // auto-ID insert that commits after a higher ID was already in the baseline.
    $stmt = $pdo->prepare('SELECT COALESCE(SUM(CASE WHEN id > ? THEN 1 ELSE 0 END), 0) AS newer_count, COUNT(*) AS current_total FROM submissions');
    $stmt->execute([$after]);
    $counts = $stmt->fetch(PDO::FETCH_ASSOC);
    $newerCount = (int) $counts['newer_count'];
    $currentTotal = (int) $counts['current_total'];
    echo json_encode(['success' => true, 'newer_count' => $newerCount, 'current_total' => $currentTotal,
        'new_count' => max($newerCount, max(0, $currentTotal - $openingTotal))]);
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['success' => false]);
}
