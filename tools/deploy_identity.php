<?php
/** Local CLI only: additive identity schema and non-record health output. */
declare(strict_types=1);
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
session_save_path(sys_get_temp_dir());
require_once __DIR__ . '/../backend/database.php';
$mode = $argv[1] ?? 'inspect';
$tables = ['identity_attempts', 'identity_bindings', 'identity_audit'];
try {
    if ($mode === 'migrate') {
        $backupRoot = 'C:/xampp/private/VNZ-TECH/identity/backups';
        if (!is_dir($backupRoot)) { throw new RuntimeException('Private backup directory missing.'); }
        $backup = ['recorded_at' => date(DATE_ATOM), 'schema' => [], 'permission_backup' => []];
        foreach ($tables as $table) {
            $stmt = $pdo->query('SHOW TABLES LIKE ' . $pdo->quote($table));
            if ($stmt->fetchColumn()) { throw new RuntimeException('Identity schema already exists; inspect instead of reapplying.'); }
        }
        foreach (['permissions', 'role_permissions'] as $table) {
            $backup['schema'][$table] = $pdo->query('SHOW CREATE TABLE `' . $table . '`')->fetch(PDO::FETCH_NUM)[1];
            $backup['permission_backup'][$table] = $pdo->query('SELECT * FROM `' . $table . '`')->fetchAll(PDO::FETCH_ASSOC);
        }
        $backupPath = $backupRoot . '/pre-identity-' . date('Ymd-His') . '.json';
        if (file_put_contents($backupPath, json_encode($backup, JSON_THROW_ON_ERROR)) === false) { throw new RuntimeException('Private backup failed.'); }
        $sql = file_get_contents(__DIR__ . '/../database/migrations/20261119_create_identity_verification.sql');
        if ($sql === false) { throw new RuntimeException('Migration missing.'); }
        $pdo->exec($sql);
    } elseif ($mode === 'migrate-dual') {
        require_once __DIR__ . '/../backend/identity_verification.php';
        $backupRoot = vnzIdentityRoot() . '/backups';
        if (!is_dir($backupRoot) || !is_writable($backupRoot)) { throw new RuntimeException('Private backup directory missing.'); }
        if ($pdo->query("SHOW TABLES LIKE 'identity_auto_decisions'")->fetchColumn()) {
            // Idempotent inspection only; do not overwrite a prior migration/backup.
            $pdo->query('SELECT attempt_id,policy_version,evidence_hash,consent_version,crop_hash,crop_policy,local_engine,provider_engine,local_pass,provider_pass,local_distance,local_threshold,provider_confidence,provider_threshold,processing_nonce_hash,lease_expires_at,outcome_code,completed_at FROM identity_auto_decisions LIMIT 1');
        } else {
            $backup = ['recorded_at' => gmdate(DATE_ATOM), 'schema' => [], 'rows' => []];
            foreach ($tables as $table) {
                $backup['schema'][$table] = $pdo->query('SHOW CREATE TABLE `' . $table . '`')->fetch(PDO::FETCH_NUM)[1];
                $backup['rows'][$table] = $pdo->query('SELECT * FROM `' . $table . '` LIMIT 10001')->fetchAll(PDO::FETCH_ASSOC);
                if (count($backup['rows'][$table]) > 10000) { throw new RuntimeException('Backup size requires a dedicated operator export.'); }
            }
            $plain = json_encode($backup, JSON_THROW_ON_ERROR);
            if (strlen($plain) > 16 * 1024 * 1024) { throw new RuntimeException('Backup size requires a dedicated operator export.'); }
            $backupPath = $backupRoot . '/pre-dual-' . gmdate('Ymd-His') . '-' . bin2hex(random_bytes(6)) . '.enc';
            if (file_put_contents($backupPath, vnzIdentityEncrypt($plain, vnzIdentityKey()), LOCK_EX) === false) { throw new RuntimeException('Private backup failed.'); }
            unset($backup, $plain);
            $sql = file_get_contents(__DIR__ . '/../database/migrations/20261119_create_identity_auto_decisions.sql');
            if ($sql === false) { throw new RuntimeException('Dual migration missing.'); }
            $pdo->exec($sql);
        }
        $tables[] = 'identity_auto_decisions';
    } elseif ($mode === 'health') {
        require_once __DIR__ . '/../backend/identity_verification.php';
        vnzIdentityReady($pdo);
        $probe = 'synthetic-local-identity-health-check';
        if (vnzIdentityDecrypt(vnzIdentityEncrypt($probe, vnzIdentityKey()), vnzIdentityKey()) !== $probe) {
            throw new RuntimeException('Encryption health check failed.');
        }
    } elseif ($mode !== 'inspect') { throw new RuntimeException('Unknown mode.'); }
    $result = ['tables' => [], 'reviewer_permission_roles' => []];
    foreach ($tables as $table) {
        $result['tables'][$table] = (bool) $pdo->query('SHOW TABLES LIKE ' . $pdo->quote($table))->fetchColumn();
    }
    $result['dual_schema'] = (bool) $pdo->query("SHOW TABLES LIKE 'identity_auto_decisions'")->fetchColumn();
    $result['reviewer_permission_roles'] = $pdo->query("SELECT r.name FROM roles r JOIN role_permissions rp ON rp.role_id=r.id JOIN permissions p ON p.id=rp.permission_id WHERE p.name='identity_review'")->fetchAll(PDO::FETCH_COLUMN);
    echo json_encode($result, JSON_THROW_ON_ERROR), PHP_EOL;
} catch (Throwable $error) {
    fwrite(STDERR, 'Identity deployment failed: ' . get_class($error) . PHP_EOL);
    exit(1);
}
