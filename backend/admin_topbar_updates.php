<?php

require_once __DIR__ . '/site_status.php';
vnzEnforceSiteMaintenance('json', true);
require_once __DIR__ . '/database.php';
require_once __DIR__ . '/session.php';
require_once __DIR__ . '/auth.php';
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store, private');
header('X-Content-Type-Options: nosniff');
if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'GET') {
    header('Allow: GET'); http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed.']); exit;
}
try {
    if (!getCurrentAdmin()) {
        http_response_code(401); echo json_encode(['success' => false, 'message' => 'Please sign in again.']); exit;
    }
    $canViewSubmissions = hasPermission('view_submissions');
    $canManageTrades = hasPermission('manage_trades');
    if (session_status() === PHP_SESSION_ACTIVE) session_write_close();
    $items = [];
    if ($canViewSubmissions) {
        foreach ([
            ['submissions', 'New submissions', "SELECT COUNT(*) FROM submissions WHERE seen_by_admin = 0"],
            ['submissions', 'Seller counter-offers', "SELECT COUNT(*) FROM offers WHERE sender_type = 'seller' AND seen_by_admin = 0"],
            ['transactions', 'Transaction updates', "SELECT COUNT(*) FROM transactions WHERE seen_by_admin = 0"]
        ] as [$page, $label, $sql]) {
            $items[] = ['page' => $page, 'label' => $label, 'count' => (int) $pdo->query($sql)->fetchColumn()];
        }
    }
    if ($canManageTrades) {
        $items[] = ['page' => 'trade_requests', 'label' => 'Pending trade requests', 'count' => (int) $pdo->query("SELECT COUNT(*) FROM trade_requests WHERE status = 'PENDING'")->fetchColumn()];
    }
    echo json_encode(['success' => true, 'items' => $items], JSON_INVALID_UTF8_SUBSTITUTE);
} catch (Throwable $error) {
    http_response_code(503); echo json_encode(['success' => false, 'message' => 'Updates are temporarily unavailable.']);
}
