<?php
declare(strict_types=1);
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
require_once __DIR__ . '/../backend/site_status.php';
$action = $argv[1] ?? '--health';
if (!in_array($action, ['--health', '--apply', '--recover-off'], true)) { fwrite(STDERR, "Use --health, --apply or --recover-off.\n"); exit(1); }
try {
    $paths = vnzSiteStatusPaths();
    if ($action === '--recover-off') {
        if (!is_dir($paths['root'])) { throw new RuntimeException('Private storage is missing. Run --apply first.'); }
        $lock = fopen($paths['lock'], 'c');
        if (!$lock || !flock($lock, LOCK_EX)) { throw new RuntimeException('Could not acquire maintenance lock.'); }
        try {
            $old = vnzSiteStatusRead();
            $state = array_replace(vnzSiteStatusDefault(), ['version' => max(time(), $old['version'] + 1)]);
            vnzSiteStatusAtomicWrite($state);
            file_put_contents($paths['root'] . '/recovery.log', gmdate('c') . " Maintenance disabled by local CLI operator.\n", FILE_APPEND | LOCK_EX);
        } finally { flock($lock, LOCK_UN); fclose($lock); }
        echo "Maintenance is off. Database mirror will reconcile on the next admin save.\n"; exit;
    }
    if ($action === '--apply') {
        if (!is_dir($paths['root']) && !mkdir($paths['root'], 0700, true)) { throw new RuntimeException('Could not create private Site Updates storage.'); }
        $root = realpath($paths['root']);
        $webroot = realpath(dirname(__DIR__, 2));
        if (!$root || !$webroot || str_starts_with(strtolower(str_replace('\\', '/', $root)) . '/', strtolower(str_replace('\\', '/', $webroot)) . '/')) { throw new RuntimeException('Private storage cannot be inside htdocs.'); }
    }
    ini_set('session.save_path', sys_get_temp_dir());
require_once __DIR__ . '/../backend/database.php';
    if ($action === '--apply') {
        // A local, private consistent data backup precedes this task's additive DDL.
        $backup = $paths['root'] . '/before-site-updates-' . gmdate('Ymd-His') . '-' . bin2hex(random_bytes(3)) . '.sql';
        $handle = fopen($backup, 'xb');
        if (!$handle) { throw new RuntimeException('Could not open private database backup.'); }
        @chmod($backup, 0600);
        try {
            fwrite($handle, "-- Private local backup; do not publish.\nSET FOREIGN_KEY_CHECKS=0;\n");
            $pdo->exec('SET TRANSACTION ISOLATION LEVEL REPEATABLE READ');
            $pdo->exec('START TRANSACTION WITH CONSISTENT SNAPSHOT');
            $tables = $pdo->query("SHOW FULL TABLES WHERE Table_type = 'BASE TABLE'")->fetchAll(PDO::FETCH_NUM);
            foreach ($tables as $row) {
                $table = '`' . str_replace('`', '``', $row[0]) . '`';
                $ddl = $pdo->query('SHOW CREATE TABLE ' . $table)->fetch(PDO::FETCH_NUM);
                if (fwrite($handle, $ddl[1] . ";\n") === false) { throw new RuntimeException('Backup write failed.'); }
                $rows = $pdo->query('SELECT * FROM ' . $table);
                while ($data = $rows->fetch(PDO::FETCH_ASSOC)) {
                    $columns = implode(',', array_map(static fn ($key) => '`' . str_replace('`', '``', $key) . '`', array_keys($data)));
                    $values = implode(',', array_map(static fn ($value) => $value === null ? 'NULL' : $pdo->quote((string) $value), array_values($data)));
                    if (fwrite($handle, 'INSERT INTO ' . $table . '(' . $columns . ') VALUES (' . $values . ");\n") === false) { throw new RuntimeException('Backup write failed.'); }
                }
            }
            // Preserve triggers separately after table data, including selfie-retention triggers.
            foreach ($pdo->query('SHOW TRIGGERS')->fetchAll(PDO::FETCH_ASSOC) as $trigger) {
                $name = '`' . str_replace('`', '``', $trigger['Trigger']) . '`';
                $definition = $pdo->query('SHOW CREATE TRIGGER ' . $name)->fetch(PDO::FETCH_ASSOC);
                $sql = $definition['SQL Original Statement'] ?? null;
                if (!is_string($sql) || fwrite($handle, "DELIMITER $$\n" . $sql . "$$\nDELIMITER ;\n") === false) { throw new RuntimeException('Trigger backup failed.'); }
            }
            $pdo->commit();
            if (fwrite($handle, "SET FOREIGN_KEY_CHECKS=1;\n") === false || !fflush($handle)) { throw new RuntimeException('Backup flush failed.'); }
        } catch (Throwable $e) { if ($pdo->inTransaction()) { $pdo->rollBack(); } throw $e; }
        finally { fclose($handle); }
        $lock = fopen($paths['lock'], 'c');
        if (!$lock || !flock($lock, LOCK_EX)) { throw new RuntimeException('Could not acquire maintenance lock.'); }
        try {
            if (is_file($paths['ready']) && !empty(vnzSiteStatusRead()['state_error'])) { throw new RuntimeException('Maintenance state needs --recover-off before setup can continue.'); }
            $sql = file_get_contents(__DIR__ . '/../database/migrations/20261120_create_site_updates.sql');
            foreach (explode(';', $sql) as $statement) { if (trim($statement) !== '') { $pdo->exec($statement); } }
            if (!is_file($paths['ready'])) {
                $stored = $pdo->query('SELECT state_json FROM site_maintenance WHERE id = 1')->fetchColumn();
                $state = json_decode((string) $stored, true, 32, JSON_THROW_ON_ERROR);
                vnzSiteStatusAtomicWrite($state);
                if (file_put_contents($paths['ready'], "Site Updates configured.\n", LOCK_EX) === false) { throw new RuntimeException('Could not enable Site Updates integration.'); }
            }
        } finally { flock($lock, LOCK_UN); fclose($lock); }
        echo "Private local database backup created; Site Updates additive migration applied.\n";
    }
    $state = vnzSiteStatusRead();
    $tables = [];
    foreach (['site_announcements', 'site_maintenance'] as $table) { $pdo->query('SELECT 1 FROM ' . $table . ' LIMIT 1'); $tables[$table] = true; }
    $permissions = $pdo->query("SELECT p.name FROM role_permissions rp JOIN roles r ON r.id=rp.role_id JOIN permissions p ON p.id=rp.permission_id WHERE r.name='Super Admin' AND r.is_active=1 AND p.name IN ('manage_announcements','manage_maintenance') ORDER BY p.name")->fetchAll(PDO::FETCH_COLUMN);
    echo json_encode(['tables' => $tables, 'configured' => $state['configured'], 'maintenance_active' => $state['active'], 'state_valid' => empty($state['state_error']), 'super_admin_permissions' => $permissions], JSON_UNESCAPED_SLASHES) . "\n";
    if (!$state['configured'] || !empty($state['state_error']) || count($permissions) !== 2) { exit(1); }
} catch (Throwable $e) { fwrite(STDERR, 'Site Updates setup did not complete (' . get_class($e) . "). No credentials printed. Check local prerequisites/storage.\n"); exit(1); }
