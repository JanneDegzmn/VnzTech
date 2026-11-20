<?php
/** Private submission selfie; authority comes from current database permissions. */
require_once __DIR__ . '/database.php';
require_once __DIR__ . '/session.php';
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/identity_verification.php';

header('Cache-Control: no-store, private, max-age=0');
header('Pragma: no-cache');
header('X-Content-Type-Options: nosniff');
header('Referrer-Policy: no-referrer');
header("Content-Security-Policy: default-src 'none'; frame-ancestors 'self'");

$deny = static function (int $status = 403): never {
    http_response_code($status);
    header('Content-Type: text/plain; charset=utf-8');
    exit('Private photo is unavailable.');
};

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    header('Allow: GET');
    $deny(405);
}
if (!isAdminLoggedIn()) {
    $deny();
}
requireAdminLogin();
try {
    if (!hasPermission('view_submissions')) {
        $deny();
    }
    $active = $pdo->prepare('SELECT a.id FROM admins a INNER JOIN roles r ON r.id=a.role_id AND r.is_active=1 WHERE a.id=? AND a.is_active=1 LIMIT 1');
    $active->execute([(int) ($_SESSION['admin_id'] ?? 0)]);
    if (!$active->fetchColumn()) {
        $deny();
    }
    $value = $_GET['submission_id'] ?? '';
    if (!is_string($value) || !ctype_digit($value) || (int) $value < 1 || strlen($value) > 18) {
        $deny(404);
    }
    $bytes = vnzSubmissionFacePhoto($pdo, (int) $value);
    header('Content-Type: image/jpeg');
    header('Content-Disposition: inline; filename="submission-selfie.jpg"');
    header('Content-Length: ' . strlen($bytes));
    echo $bytes;
} catch (Throwable $error) {
    // Do not disclose record existence, private paths or decryption details.
    $deny(404);
}
