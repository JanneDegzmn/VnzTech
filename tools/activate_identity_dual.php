<?php
/** CLI only. Credential preparation never activates; activation has no fallback. */
declare(strict_types=1);
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
ini_set('display_errors', '0');
ini_set('log_errors', '0');
// Required private config/database files must never accidentally echo secrets.
ob_start(static fn(string $unexpectedOutput): string => '');
require_once __DIR__ . '/../backend/identity_faceplusplus.php';
require_once __DIR__ . '/identity_dual_private.php';

$lock = null;
$stage = 'arguments';
try {
    $action = $argv[1] ?? '--check';
    if (count($argv) > 2 || !in_array($action, ['--prepare', '--check', '--activate'], true)) { throw new RuntimeException('arguments'); }
    if (getenv('VNZ_IDENTITY_CONFIG_PATH')) { throw new RuntimeException('override_not_allowed_for_private_setup'); }
    $stage = 'private_configuration';
    $configPath = identityDualSetupConfigPath();
    $lock = @fopen(dirname($configPath) . '/dual-config.lock', 'c');
    if (!$lock || !flock($lock, LOCK_EX | LOCK_NB)) { throw new RuntimeException('private_lock'); }
    $original = @file_get_contents($configPath);
    $config = require $configPath;
    if (!is_string($original) || !is_array($config) || !is_bool($config['enabled'] ?? null)) { throw new RuntimeException('private_configuration'); }
    $currentMode = $config['mode'] ?? 'manual_v2';
    if (!in_array($currentMode, ['manual_v2', 'auto_dual_v4'], true)) { throw new RuntimeException('private_mode'); }
    if ($action === '--prepare') {
        $stage = 'credential_input';
        $input = stream_get_contents(STDIN, 4097);
        if (!is_string($input) || strlen($input) > 4096) { throw new RuntimeException('credential_input'); }
        $payload = json_decode($input, true, 8, JSON_THROW_ON_ERROR);
        if (!is_array($payload) || array_diff(array_keys($payload), ['region', 'api_key', 'api_secret'])) { throw new RuntimeException('credential_input'); }
        $dual = array_replace(is_array($config['dual'] ?? null) ? $config['dual'] : [], [
            'node_binary' => 'C:/Program Files/nodejs/node.exe',
            'region' => $payload['region'] ?? null,
            'api_key' => $payload['api_key'] ?? null,
            'api_secret' => $payload['api_secret'] ?? null,
            'contract_confirmed' => false,
            'matching_validated' => false,
            'validation_policy' => '',
            'validation_recorded_at' => null,
            'validation_pair_counts' => null,
        ]);
        // Validate credential structure without inventing provider/matching evidence.
        $structural = $dual;
        $structural['contract_confirmed'] = true;
        $structural['matching_validated'] = true;
        if (!vnzIdentityFacePlusPlusConfigReady(['dual' => $structural])) { throw new RuntimeException('credential_input'); }
        $candidate = array_replace($config, ['mode' => $currentMode, 'dual' => $dual]);
        $stage = 'private_write';
        if (!hash_equals(hash('sha256', $original), hash_file('sha256', $configPath))) { throw new RuntimeException('configuration_changed'); }
        identityDualSetupWrite($configPath, "<?php\nreturn " . var_export($candidate, true) . ";\n");
        unset($input, $payload, $dual, $structural, $candidate, $config, $original);
        fwrite(STDOUT, json_encode(['prepared' => true, 'mode' => $currentMode, 'contract_confirmed' => false, 'matching_validated' => false], JSON_THROW_ON_ERROR) . PHP_EOL);
    } else {
        $candidate = array_replace($config, ['mode' => 'auto_dual_v4']);
        $stage = 'provider_configuration';
        if (!vnzIdentityFacePlusPlusConfigReady($candidate)) { throw new RuntimeException('provider_configuration'); }
        $stage = 'private_schema_or_runtime';
        session_save_path(sys_get_temp_dir());
        require_once __DIR__ . '/../backend/database.php';
        require_once __DIR__ . '/../backend/identity_verification.php';
        if (!isset($pdo) || !$pdo instanceof PDO || !function_exists('vnzIdentityDualReady')) { throw new RuntimeException('deployment'); }
        vnzIdentityDualReady($pdo, $candidate, true);
        if ($action === '--activate') {
            $stage = 'activation_write';
            if (!hash_equals(hash('sha256', $original), hash_file('sha256', $configPath))) { throw new RuntimeException('configuration_changed'); }
            if (($config['enabled'] ?? null) !== true) { throw new RuntimeException('disabled_instance'); }
            // Marker FIRST: a crash before config replacement must block manual fallback.
            $marker = __DIR__ . '/../backend/identity_dual_required.flag';
            if (is_link($marker)) { throw new RuntimeException('marker_path'); }
            identityDualSetupWrite($marker, "dual-v4-portrait-v1\n");
            identityDualSetupWrite($configPath, "<?php\nreturn " . var_export($candidate, true) . ";\n");
        }
        fwrite(STDOUT, json_encode(['ready' => true, 'activated' => $action === '--activate', 'policy' => 'dual-v4-portrait-v1', 'provider_request_sent' => false], JSON_THROW_ON_ERROR) . PHP_EOL);
    }
} catch (Throwable $error) {
    // Stage is fixed internal vocabulary. Never print exception/provider/config values.
    fwrite(STDERR, json_encode(['success' => false, 'stage' => $stage, 'provider_request_sent' => false], JSON_THROW_ON_ERROR) . PHP_EOL);
    exit(1);
} finally {
    if (is_resource($lock)) { flock($lock, LOCK_UN); fclose($lock); }
}
