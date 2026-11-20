<?php
// Isolated actual-page/endpoint fixture. No live DB, account or session bootstrap.
require_once __DIR__.'/../backend/site_status.php';
require_once __DIR__.'/../backend/pagination.php';
$kind = $argv[1] ?? 'page';
$scenario = $argv[2] ?? 'settings';
$_SESSION = ['admin_id' => 7];
$_GET = [];
$fixtureAdmin = ['id'=>7,'is_active'=>1,'first_name'=>'Fixture','last_name'=>'Admin','username'=>'fixture','email'=>'fixture@example.invalid','role_name'=>'Reviewer','totp_enabled_at'=>null];
function requireAdminLogin(): void {}
function isAdminLoggedIn(): bool { global $scenario; return $scenario !== 'guest'; }
function getCurrentAdmin(): ?array { global $scenario, $fixtureAdmin; return $scenario === 'inactive' ? null : $fixtureAdmin; }
function hasPermission(string $permission): bool { global $scenario; return in_array($scenario, ['privileged', 'activity'], true); }
function generateCsrfToken(): string { return 'fixture'; }
function verifyCsrfToken($token): bool { return $token === 'fixture'; }
function logAdminActivity(...$args): void {}
$pdo = new PDO('sqlite::memory:');
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
$pdo->sqliteCreateFunction('NOW', fn() => '2026-11-20 12:00:00');
$pdo->exec('CREATE TABLE admins(id INTEGER PRIMARY KEY, first_name TEXT,last_name TEXT,username TEXT,email TEXT,password_hash TEXT,updated_at TEXT)');
$pdo->exec('CREATE TABLE admin_activity_logs(id INTEGER PRIMARY KEY,admin_id INTEGER,action TEXT,details TEXT,ip_address TEXT,created_at TEXT)');
$stmt=$pdo->prepare('INSERT INTO admins VALUES(?,?,?,?,?,?,NULL)');
$stmt->execute([7,'Fixture','Admin','fixture','fixture@example.invalid',password_hash('FixturePassword123!',PASSWORD_DEFAULT)]);
$stmt->execute([99,'Other','Admin','other','other@example.invalid',password_hash('OtherPassword123!',PASSWORD_DEFAULT)]);
$pdo->exec("INSERT INTO admin_activity_logs VALUES(1,7,'auth.login','Fixture admin signed in.','127.0.0.1','2026-11-20 12:00:00')");
register_shutdown_function(static function (): void {
    global $pdo, $kind;
    if ($kind === 'endpoint') echo "\nRESULT:".json_encode(['status'=>http_response_code()?:200,'accounts'=>$pdo->query('SELECT id,first_name,username FROM admins ORDER BY id')->fetchAll(PDO::FETCH_ASSOC)]);
});
if ($kind === 'endpoint') {
    $_SERVER['REQUEST_METHOD'] = $scenario === 'get' ? 'GET' : 'POST';
    $_POST = ['csrf_token'=>$scenario === 'csrf' ? 'bad':'fixture','action'=>'profile','admin_id'=>99,'role_id'=>1,'first_name'=>'Changed','last_name'=>'Admin','username'=>'changed','email'=>'changed@example.invalid'];
    if ($scenario === 'unknown') $_POST['action']='grant_role';
    if ($scenario === 'password') $_POST = ['csrf_token'=>'fixture','action'=>'password','current_password'=>'wrong','new_password'=>'NewPassword123!','confirm_password'=>'NewPassword123!'];
    $path=__DIR__.'/../backend/update_admin_settings.php';
} else {
    if (!in_array($scenario,['settings','profile','security','moderation','activity'],true)) exit('Unknown fixture page');
    $path=__DIR__.'/../admin/pages/'.$scenario.'.php';
}
$source=file_get_contents($path);
$source=preg_replace('/^require_once[^\n]*\n/m','',$source);
$source=str_replace('__DIR__',var_export(dirname($path),true),$source);
eval('?>'.$source);
