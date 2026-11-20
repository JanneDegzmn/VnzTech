<?php
/** Approved local-only additive migration/activation. Never prints config or records. */
declare(strict_types=1);
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
ini_set('display_errors', '0');
ini_set('log_errors', '0');
session_save_path(sys_get_temp_dir());
ob_start(static fn(string $unexpected): string => '');
require_once __DIR__ . '/identity_dual_private.php';

function faceSetupTrigger(PDO $pdo): ?array
{
    $stmt = $pdo->prepare("SELECT ACTION_TIMING,EVENT_MANIPULATION,EVENT_OBJECT_TABLE,ACTION_STATEMENT FROM information_schema.TRIGGERS WHERE TRIGGER_SCHEMA=DATABASE() AND TRIGGER_NAME='vnz_submission_face_terminal_v6'");
    $stmt->execute();
    return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
}

function faceSetupTriggerReady(PDO $pdo): bool
{
    $trigger = faceSetupTrigger($pdo);
    if (!$trigger || $trigger['ACTION_TIMING'] !== 'AFTER' || $trigger['EVENT_MANIPULATION'] !== 'UPDATE' || $trigger['EVENT_OBJECT_TABLE'] !== 'submissions') { return false; }
    $source = (string) file_get_contents(__DIR__ . '/../database/migrations/20261119_submission_face_terminal_trigger.sql');
    $body = preg_split('/FOR EACH ROW\s*/i', $source, 2)[1] ?? '';
    $normalize = static fn(string $sql): string => strtolower(trim((string) preg_replace('/\s+/', ' ', str_replace('`', '', rtrim(trim($sql), ';')))));
    return hash_equals($normalize($body), $normalize($trigger['ACTION_STATEMENT']));
}

function faceSetupBackup(PDO $pdo, array $config): void
{
    $directory = vnzIdentityRoot() . '/backups';
    if (!is_dir($directory) || !is_writable($directory)) { throw new RuntimeException('backup_directory'); }
    $backup = ['recorded_at' => gmdate(DATE_ATOM), 'config' => $config, 'schema' => [], 'terminal_trigger' => faceSetupTrigger($pdo)];
    foreach (['submissions', 'identity_attempts', 'identity_bindings', 'identity_audit', 'submission_security_faces'] as $table) {
        if ($pdo->query('SHOW TABLES LIKE ' . $pdo->quote($table))->fetchColumn()) {
            $backup['schema'][$table] = $pdo->query('SHOW CREATE TABLE `' . $table . '`')->fetch(PDO::FETCH_NUM)[1];
        }
    }
    // Only configuration and schemas are affected. No user/evidence rows are read.
    $encrypted = vnzIdentityEncrypt(json_encode($backup, JSON_THROW_ON_ERROR), vnzIdentityKey());
    $path = $directory . '/pre-face-v6-' . gmdate('Ymd-His') . '-' . bin2hex(random_bytes(8)) . '.enc';
    $file = @fopen($path, 'xb');
    if (!$file) { throw new RuntimeException('backup_write'); }
    try {
        if (fwrite($file, $encrypted) !== strlen($encrypted) || !fflush($file)) { throw new RuntimeException('backup_write'); }
        if (function_exists('fsync') && !fsync($file)) { throw new RuntimeException('backup_sync'); }
    } finally { fclose($file); }
}

function faceSetupWorkerHealth(array $candidate): void
{
    $image = imagecreatetruecolor(160, 160);
    ob_start();
    imagejpeg($image, null, 90);
    $jpeg = ob_get_clean();
    imagedestroy($image);
    $result = vnzSubmissionFacePresence($jpeg, $candidate);
    if (($result['outcome'] ?? '') !== 'retry' || ($result['code'] ?? '') !== 'no_face') {
        throw new RuntimeException('worker_health');
    }
    $probe = 'synthetic-face-v6-encryption-health';
    if (vnzIdentityDecrypt(vnzIdentityEncrypt($probe, vnzIdentityKey()), vnzIdentityKey()) !== $probe) { throw new RuntimeException('encryption_health'); }
}

$lock = null;
$stage = 'arguments';
try {
    $action = $argv[1] ?? '--check';
    if (count($argv) > 2 || !in_array($action, ['--inspect', '--migrate', '--check', '--activate'], true)
        || getenv('VNZ_IDENTITY_CONFIG_PATH')) { throw new RuntimeException('arguments'); }
    require_once __DIR__ . '/../backend/database.php';
    require_once __DIR__ . '/../backend/identity_verification.php';
    $stage = 'private_configuration';
    $path = identityDualSetupConfigPath();
    $lock = @fopen(dirname($path) . '/face-mode-setup.lock', 'c');
    if (!$lock || !flock($lock, LOCK_EX | LOCK_NB)) { throw new RuntimeException('setup_lock'); }
    $original = @file_get_contents($path);
    $config = require $path;
    if (!is_string($original) || !is_array($config) || ($config['enabled'] ?? false) !== true
        || !in_array($config['mode'] ?? 'manual_v2', ['manual_v2', 'auto_dual_v4', 'face_submission_v6'], true)) { throw new RuntimeException('private_configuration'); }
    $candidate = array_replace($config, ['mode' => 'face_submission_v6', 'face_submission' => [
        'node_binary' => 'C:/Program Files/nodejs/node.exe', 'local_timeout_seconds' => 30,
    ]]);
    $column = $pdo->query("SHOW COLUMNS FROM submissions LIKE 'id'")->fetch(PDO::FETCH_ASSOC);
    $idType = strtolower((string) ($column['Type'] ?? ''));
    if (!preg_match('/^(?:tinyint|smallint|mediumint|int|bigint)(?:\(\d+\))?(?: unsigned)?$/D', $idType)) { throw new RuntimeException('submission_schema'); }
    $tableExists = (bool) $pdo->query("SHOW TABLES LIKE 'submission_security_faces'")->fetchColumn();
    $triggerExists = faceSetupTrigger($pdo) !== null;
    if ($action === '--inspect') {
        fwrite(STDOUT, json_encode(['mode' => $config['mode'] ?? 'manual_v2', 'face_table' => $tableExists, 'terminal_trigger' => $triggerExists, 'submission_id_type' => $idType, 'provider_request_sent' => false], JSON_THROW_ON_ERROR) . PHP_EOL);
        exit;
    }
    if ($action === '--migrate') {
        $stage = 'private_backup';
        vnzIdentityReadyBase($pdo);
        if (!$tableExists || !$triggerExists) { faceSetupBackup($pdo, $config); }
        $stage = 'additive_migration';
        if (!$tableExists) {
            $sql = (string) file_get_contents(__DIR__ . '/../database/migrations/20261119_create_submission_security_faces_v6.sql');
            // Foreign keys must use the existing id's exact signedness/width.
            $sql = str_replace('submission_id INT UNSIGNED', 'submission_id ' . strtoupper($idType), $sql);
            $pdo->exec($sql);
        }
        if (!$triggerExists) { $pdo->exec((string) file_get_contents(__DIR__ . '/../database/migrations/20261119_submission_face_terminal_trigger.sql')); }
        vnzSubmissionFaceReady($pdo, $candidate);
        if (!faceSetupTriggerReady($pdo)) { throw new RuntimeException('terminal_trigger'); }
        fwrite(STDOUT, json_encode(['migrated' => true, 'table_created' => !$tableExists, 'trigger_created' => !$triggerExists, 'activated' => false, 'provider_request_sent' => false], JSON_THROW_ON_ERROR) . PHP_EOL);
        exit;
    }
    $stage = 'local_health';
    vnzSubmissionFaceReady($pdo, $candidate);
    if (!faceSetupTriggerReady($pdo)) { throw new RuntimeException('terminal_trigger'); }
    faceSetupWorkerHealth($candidate);
    if ($action === '--activate') {
        $stage = 'private_backup';
        faceSetupBackup($pdo, $config);
        if (!hash_equals(hash('sha256', $original), (string) hash_file('sha256', $path))) { throw new RuntimeException('configuration_changed'); }
        $stage = 'atomic_activation';
        $marker = __DIR__ . '/../backend/submission_face_required.flag';
        if (is_link($marker)) { throw new RuntimeException('marker_path'); }
        $markerExisted = is_file($marker);
        identityDualSetupWrite($marker, "face-submission-v6\n");
        try { identityDualSetupWrite($path, "<?php\nreturn " . var_export($candidate, true) . ";\n"); }
        catch (Throwable $error) {
            // If config did not change, restore the still-active previous gate on a failed switch.
            if (!$markerExisted && hash_equals(hash('sha256', $original), (string) hash_file('sha256', $path))) { @unlink($marker); }
            throw $error;
        }
        if (function_exists('opcache_invalidate')) { @opcache_invalidate($path, true); }
    }
    fwrite(STDOUT, json_encode(['ready' => true, 'activated' => $action === '--activate', 'mode' => $action === '--activate' ? 'face_submission_v6' : ($config['mode'] ?? 'manual_v2'), 'blank_worker_refused' => true, 'provider_request_sent' => false], JSON_THROW_ON_ERROR) . PHP_EOL);
} catch (Throwable $error) {
    fwrite(STDERR, json_encode(['success' => false, 'stage' => $stage, 'provider_request_sent' => false], JSON_THROW_ON_ERROR) . PHP_EOL);
    exit(1);
} finally {
    if (is_resource($lock)) { flock($lock, LOCK_UN); fclose($lock); }
}
