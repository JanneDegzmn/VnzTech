<?php
declare(strict_types=1);
// Uses connection-local TEMPORARY tables which shadow, never change, application tables.
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
require_once __DIR__ . '/../backend/site_status.php';
ini_set('session.save_path', sys_get_temp_dir());
require_once __DIR__ . '/../backend/database.php';
$root = sys_get_temp_dir() . '/vnz-status-mysql-' . bin2hex(random_bytes(8));
mkdir($root, 0700); putenv('VNZ_SITE_STATUS_ROOT=' . $root); putenv('VNZ_SITE_STATUS_READY_FLAG=' . $root . '/ready');
$checks = 0;
$check = static function (bool $value, string $message) use (&$checks): void { if (!$value) { throw new RuntimeException($message); } $checks++; };
try {
    $pdo->exec('CREATE TEMPORARY TABLE site_maintenance (id INT PRIMARY KEY, version INT, state_json TEXT, updated_by INT, updated_at DATETIME) ENGINE=InnoDB');
    $pdo->exec('CREATE TEMPORARY TABLE admin_activity_logs (admin_id INT, action VARCHAR(100), details TEXT, created_at DATETIME) ENGINE=InnoDB');
    $pdo->exec('CREATE TEMPORARY TABLE site_announcements (id INT AUTO_INCREMENT PRIMARY KEY,title VARCHAR(150),message TEXT,status VARCHAR(16),expires_at DATETIME,published_at DATETIME,created_by INT,updated_by INT,version INT DEFAULT 1,created_at DATETIME DEFAULT CURRENT_TIMESTAMP,updated_at DATETIME) ENGINE=InnoDB');
    $pdo->exec("INSERT INTO site_maintenance (id,version,state_json) VALUES(1,0,'{}')");
    vnzSiteStatusAtomicWrite(vnzSiteStatusDefault()); file_put_contents($root . '/ready', 'fixture');
    $state = vnzSiteMaintenanceSave($pdo, 7, ['active'=>'1','reason'=>'Isolated fixture maintenance','hours'=>'1','minutes'=>'30','version'=>'0']);
    $check($state['active'] && $state['version'] === 1 && $state['duration_minutes'] === 90, 'MySQL enable and duration');
    $check(vnzSiteStatusRead()['active'], 'Isolated atomic snapshot published');
    $check((int) $pdo->query('SELECT version FROM site_maintenance WHERE id=1')->fetchColumn() === 1, 'Database mirror version');
    try { vnzSiteMaintenanceSave($pdo, 7, ['active'=>'0','hours'=>'0','minutes'=>'0','version'=>'0']); throw new RuntimeException('Stale accepted'); } catch (DomainException $e) { $check(true, 'Stale writer rejected'); }
    $check(vnzSiteStatusRead()['active'], 'Stale does not disable');
    $state = vnzSiteMaintenanceSave($pdo, 7, ['active'=>'0','hours'=>'0','minutes'=>'0','version'=>'1']);
    $check(!$state['active'] && !vnzSiteStatusRead()['active'], 'Disable database and snapshot');
    $check((int) $pdo->query('SELECT COUNT(*) FROM admin_activity_logs')->fetchColumn() === 2, 'Maintenance audit atomic');
    $pdo->exec('DELETE FROM site_maintenance');
    try { vnzSiteMaintenanceSave($pdo, 7, ['active'=>'1','reason'=>'Missing row','hours'=>'1','minutes'=>'0','version'=>'2']); throw new DomainException('Missing singleton accepted'); } catch (RuntimeException $e) { $check(!$e instanceof DomainException, 'Missing singleton rejected'); }
    $check(!vnzSiteStatusRead()['active'], 'Missing singleton cannot publish');
    $check(!$pdo->inTransaction(), 'Failed maintenance transaction rolled back');
    vnzAnnouncementSave($pdo, 7, ['title'=>'Fixture draft','message'=>'Private fixture','status'=>'draft']);
    $check(vnzAnnouncements($pdo)['total'] === 0, 'MySQL draft private');
    vnzAnnouncementSave($pdo, 7, ['id'=>'1','version'=>'1','title'=>'Fixture publish','message'=>'Safe text','status'=>'published']);
    $check(vnzAnnouncements($pdo)['total'] === 1, 'MySQL draft publish visible');
    $check($pdo->query('SELECT published_at FROM site_announcements WHERE id=1')->fetchColumn() !== null, 'MySQL timestamp uses old status');
    vnzAnnouncementSave($pdo, 7, ['id'=>'1','version'=>'2','title'=>'Fixture archive','message'=>'Archived','status'=>'archived']);
    $check(vnzAnnouncements($pdo)['total'] === 0, 'MySQL archive invisible');
    try { vnzAnnouncementSave($pdo, 7, ['id'=>'1','version'=>'2','title'=>'Stale','message'=>'Stale','status'=>'published']); throw new RuntimeException('Stale accepted'); } catch (DomainException $e) { $check(true, 'MySQL stale announcement rejected'); }
    $check(!$pdo->inTransaction(), 'Announcement rollback');
    echo "PASS $checks MySQL service assertions using connection-local temporary tables and an isolated temporary snapshot; no persistent application rows/state changed.\n";
} finally {
    if ($pdo->inTransaction()) { $pdo->rollBack(); }
    foreach (['site_maintenance','site_announcements','admin_activity_logs'] as $table) { $pdo->exec('DROP TEMPORARY TABLE IF EXISTS ' . $table); }
    foreach (glob($root . '/*') ?: [] as $file) { unlink($file); } rmdir($root);
    putenv('VNZ_SITE_STATUS_ROOT'); putenv('VNZ_SITE_STATUS_READY_FLAG');
}
