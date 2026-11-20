<?php
declare(strict_types=1);
require_once __DIR__ . '/site_status.php';
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store, max-age=0');
if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'GET') { http_response_code(405); header('Allow: GET'); echo json_encode(['success' => false, 'message' => 'Method not allowed.']); exit; }
$state = vnzSiteStatusRead();
$result = ['success' => true, 'maintenance' => vnzSiteStatusPublic($state), 'announcements' => ['items' => [], 'page' => 1, 'pages' => 1, 'total' => 0]];
// The private snapshot keeps maintenance/recovery available without database access.
if (!$state['active'] && !empty($state['configured'])) {
    try {
        require_once __DIR__ . '/database.php';
        if (session_status() === PHP_SESSION_ACTIVE) { session_write_close(); }
        $result['announcements'] = vnzAnnouncements($pdo, max(1, (int) ($_GET['page'] ?? 1)));
    } catch (Throwable $e) { http_response_code(503); $result['success'] = false; $result['message'] = 'Announcements are temporarily unavailable.'; }
}
echo json_encode($result, JSON_UNESCAPED_SLASHES | JSON_INVALID_UTF8_SUBSTITUTE);
