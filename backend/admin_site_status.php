<?php
declare(strict_types=1);
require_once __DIR__ . '/site_status.php';
vnzEnforceSiteMaintenance('json', true);
require_once __DIR__ . '/database.php';
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/csrf.php';
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store, max-age=0');
$reply = static function (array $data, int $code = 200): never { http_response_code($code); echo json_encode($data, JSON_INVALID_UTF8_SUBSTITUTE); exit; };
$admin = getCurrentAdmin();
if (!$admin) { $reply(['success' => false, 'message' => 'An active admin account is required.'], 401); }
$method = $_SERVER['REQUEST_METHOD'] ?? '';
if (!in_array($method, ['GET', 'POST'], true)) { $reply(['success' => false, 'message' => 'Method not allowed.'], 405); }
$action = (string) ($method === 'GET' ? ($_GET['action'] ?? 'announcements') : ($_POST['action'] ?? ''));
$permission = $action === 'maintenance' ? 'manage_maintenance' : 'manage_announcements';
if (!in_array($action, ['maintenance', 'announcements', 'announcement_save'], true)) { $reply(['success' => false, 'message' => 'Unknown action.'], 400); }
if (!hasPermission($permission)) { $reply(['success' => false, 'message' => 'You do not have permission for this action.'], 403); }
if ($method === 'POST' && !verifyCsrfToken($_POST['csrf_token'] ?? null)) { $reply(['success' => false, 'message' => 'Refresh the page and try again.'], 419); }
try {
    if ($method === 'POST') {
        if ($action === 'maintenance') { $state = vnzSiteMaintenanceSave($pdo, (int) $admin['id'], $_POST); $reply(['success' => true, 'message' => $state['active'] ? 'Maintenance is active.' : 'Maintenance is off.', 'maintenance' => $state]); }
        if ($action !== 'announcement_save') { $reply(['success' => false, 'message' => 'Invalid save action.'], 400); }
        vnzAnnouncementSave($pdo, (int) $admin['id'], $_POST);
        $reply(['success' => true, 'message' => 'Announcement saved.']);
    }
    if ($action === 'maintenance') { $reply(['success' => true, 'maintenance' => vnzSiteStatusRead()]); }
    $reply(['success' => true, 'announcements' => vnzAnnouncements($pdo, max(1, (int) ($_GET['page'] ?? 1)), true, (string) ($_GET['status'] ?? ''))]);
} catch (DomainException $e) { $reply(['success' => false, 'message' => $e->getMessage()], 409); }
catch (Throwable $e) { error_log('Site Updates operation failed: ' . get_class($e)); $reply(['success' => false, 'message' => 'Site Updates could not be saved or loaded. Check local setup, then try again.'], 503); }
