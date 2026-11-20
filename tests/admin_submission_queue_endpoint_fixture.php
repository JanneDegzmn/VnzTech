<?php
require_once __DIR__ . "/../backend/site_status.php";
// Isolated endpoint authorization checks: no application database/session bootstrap.
$scenario = $argv[1] ?? 'ok';
$_SERVER['REQUEST_METHOD'] = $scenario === 'post' ? 'POST' : 'GET';
$_GET = ['after' => $scenario === 'invalid' ? '-1' : '2', 'opening_total' => $scenario === 'invalidtotal' ? '-1' : '2'];
if ($scenario === 'missingtotal') unset($_GET['opening_total']);
if ($scenario === 'late') $_GET = ['after' => '4', 'opening_total' => '3'];
function isAdminLoggedIn(): bool { global $scenario; return $scenario !== 'guest'; }
function hasPermission(string $permission): bool { global $scenario; return $permission === 'view_submissions' && !in_array($scenario, ['denied', 'inactive'], true); }
$pdo = new PDO('sqlite::memory:');
$pdo->exec('CREATE TABLE submissions(id INTEGER PRIMARY KEY)');
$pdo->exec('INSERT INTO submissions VALUES(1),(2),(3),(4)');
register_shutdown_function(static function (): void {
    echo "\nSTATUS:" . (http_response_code() ?: 200);
});
$source = file_get_contents(__DIR__ . '/../backend/admin_submission_queue_updates.php');
$source = preg_replace('/^require_once[^\n]*\n/m', '', $source);
eval('?>' . $source);
