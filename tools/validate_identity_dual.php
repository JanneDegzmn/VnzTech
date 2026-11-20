<?php
/** Privileged, explicit-consent staging only. Never called by the web application. */
declare(strict_types=1);
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
ini_set('display_errors', '0');
ini_set('log_errors', '0');
ob_start(static fn(string $unexpectedOutput): string => '');
require_once __DIR__ . '/identity_dual_private.php';

function identityDualValidationPath(mixed $path, string $root): string
{
    if (!is_string($path) || strlen($path) > 1024 || str_contains($path, "\0")) { throw new RuntimeException('validation_path'); }
    $actual = realpath($path);
    $prefix = strtolower(str_replace('\\', '/', $root)) . '/validation/';
    if (!$actual || !is_file($actual) || is_link($path) || !str_starts_with(strtolower(str_replace('\\', '/', $actual)), $prefix)) {
        throw new RuntimeException('validation_path');
    }
    // Freshness is also explicitly attested by the owner; mtime alone proves no identity.
    $modified = filemtime($actual);
    if ($modified === false || $modified < time() - 3600 || $modified > time() + 60) { throw new RuntimeException('validation_freshness'); }
    return $actual;
}

$lock = null;
$stage = 'consent';
try {
    if (count($argv) !== 1) { throw new RuntimeException('no_path_or_credential_arguments'); }
    $input = stream_get_contents(STDIN, 16385);
    if (!is_string($input) || strlen($input) > 16384) { throw new RuntimeException('validation_input'); }
    $payload = json_decode($input, true, 12, JSON_THROW_ON_ERROR);
    if (!is_array($payload) || ($payload['fresh_external_processing_consent'] ?? null) !== true || ($payload['provider_contract_confirmed'] ?? null) !== true) {
        throw new RuntimeException('consent_required');
    }
    $stage = 'pair_plan';
    $pairs = $payload['pairs'] ?? null;
    if (!is_array($pairs) || !array_is_list($pairs) || count($pairs) < 2 || count($pairs) > 6) { throw new RuntimeException('validation_pairs'); }
    $counts = ['same' => 0, 'different' => 0];
    foreach ($pairs as $pair) {
        if (!is_array($pair) || !in_array($pair['expected'] ?? null, ['same', 'different'], true)
            || ($pair['all_people_consented'] ?? null) !== true || ($pair['captured_for_this_validation'] ?? null) !== true
            || !is_string($pair['id_path'] ?? null) || !is_string($pair['selfie_path'] ?? null)) {
            throw new RuntimeException('validation_pairs');
        }
        $counts[$pair['expected']]++;
    }
    if (!$counts['same'] || !$counts['different']) { throw new RuntimeException('validation_coverage'); }
    if (getenv('VNZ_IDENTITY_CONFIG_PATH')) { throw new RuntimeException('override_not_allowed_for_private_setup'); }
    $stage = 'private_configuration';
    $configPath = identityDualSetupConfigPath();
    $lock = @fopen(dirname($configPath) . '/dual-config.lock', 'c');
    if (!$lock || !flock($lock, LOCK_EX | LOCK_NB)) { throw new RuntimeException('private_lock'); }
    $original = @file_get_contents($configPath);
    $config = require $configPath;
    if (!is_string($original) || !is_array($config) || ($config['enabled'] ?? null) !== true
        || !in_array($config['mode'] ?? 'manual_v2', ['manual_v2', 'auto_dual_v4'], true)) { throw new RuntimeException('private_configuration'); }
    $root = realpath((string) ($config['storage_path'] ?? ''));
    if (!$root || strcasecmp(str_replace('\\', '/', $root), str_replace('\\', '/', dirname($configPath))) !== 0) { throw new RuntimeException('private_root'); }
    $stage = 'validation_paths';
    foreach ($pairs as &$pair) {
        $pair['id_path'] = identityDualValidationPath($pair['id_path'], $root);
        $pair['selfie_path'] = identityDualValidationPath($pair['selfie_path'], $root);
    }
    unset($pair);
    // Readiness bypass exists ONLY in this privileged CLI candidate, never in a request/config write.
    $candidate = $config;
    $candidate['mode'] = 'auto_dual_v4';
    $candidate['dual']['contract_confirmed'] = true;
    $candidate['dual']['matching_validated'] = true;
    $candidate['dual']['validation_policy'] = 'dual-v4-portrait-v1';
    $stage = 'private_schema_or_runtime';
    session_save_path(sys_get_temp_dir());
    require_once __DIR__ . '/../backend/database.php';
    require_once __DIR__ . '/../backend/identity_verification.php';
    require_once __DIR__ . '/../backend/identity_face_matcher.php';
    require_once __DIR__ . '/../backend/identity_faceplusplus.php';
    if (!isset($pdo) || !$pdo instanceof PDO || !function_exists('vnzIdentityDualReady')) { throw new RuntimeException('deployment'); }
    vnzIdentityDualReady($pdo, $candidate, true);
    $stage = 'matching';
    $completed = ['same' => 0, 'different' => 0];
    $codes = [];
    foreach ($pairs as $pair) {
        $idImage = vnzIdentityValidateImage(['error' => UPLOAD_ERR_OK, 'tmp_name' => $pair['id_path']]);
        $selfieImage = vnzIdentityValidateImage(['error' => UPLOAD_ERR_OK, 'tmp_name' => $pair['selfie_path']]);
        $local = vnzIdentityLocalMatch($idImage['bytes'], $selfieImage['bytes'], $candidate);
        $cloud = ($local['outcome'] ?? null) === 'pass'
            ? vnzIdentityFacePlusPlusMatch($local['id_crop'], $local['selfie_crop'], $candidate)
            : null;
        $decision = vnzIdentityDualResult($local, $cloud);
        $valid = $pair['expected'] === 'same'
            ? ($decision['status'] ?? null) === 'approved' && ($local['outcome'] ?? null) === 'pass' && ($cloud['outcome'] ?? null) === 'pass'
            : (($decision['status'] ?? null) === 'retry' && (
                (($local['outcome'] ?? null) === 'retry' && ($local['code'] ?? null) === 'not_matching'
                    && (is_int($local['distance'] ?? null) || is_float($local['distance'] ?? null))
                    && is_finite((float) $local['distance']) && $local['distance'] > 0.45)
                || (($local['outcome'] ?? null) === 'pass' && ($cloud['outcome'] ?? null) === 'retry'
                    && ($cloud['code'] ?? null) === 'provider_no_match'
                    && is_float($cloud['confidence'] ?? null) && is_float($cloud['threshold'] ?? null)
                    && $cloud['confidence'] <= $cloud['threshold'])
            ));
        if (!$valid) { throw new RuntimeException('validation_unexpected_or_unavailable'); }
        $completed[$pair['expected']]++;
        $codes[] = $local['code'] . '/' . ($cloud['code'] ?? 'provider_short_circuited');
        unset($idImage, $selfieImage, $local, $cloud, $decision);
    }
    $stage = 'private_write';
    if (!hash_equals(hash('sha256', $original), hash_file('sha256', $configPath))) { throw new RuntimeException('configuration_changed'); }
    $config['dual']['contract_confirmed'] = true;
    $config['dual']['matching_validated'] = true;
    $config['dual']['validation_policy'] = 'dual-v4-portrait-v1';
    $config['dual']['validation_recorded_at'] = gmdate(DATE_ATOM);
    $config['dual']['validation_pair_counts'] = $completed;
    identityDualSetupWrite($configPath, "<?php\nreturn " . var_export($config, true) . ";\n");
    fwrite(STDOUT, json_encode(['validated' => true, 'activated' => false, 'pair_counts' => $completed, 'codes' => $codes], JSON_THROW_ON_ERROR) . PHP_EOL);
} catch (Throwable $error) {
    // No original paths, images, distances, provider text, credentials or identities in output.
    fwrite(STDERR, json_encode(['success' => false, 'stage' => $stage, 'activated' => false], JSON_THROW_ON_ERROR) . PHP_EOL);
    exit(1);
} finally {
    if (is_resource($lock)) { flock($lock, LOCK_UN); fclose($lock); }
}
