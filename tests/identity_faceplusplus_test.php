<?php
/** Synthetic parser/config/input checks. No credentials, network or personal photos. */
declare(strict_types=1);
require_once __DIR__ . '/../backend/identity_faceplusplus.php';
$checks = 0;
function facePlusAssert(bool $condition, string $name): void
{
    global $checks;
    if (!$condition) { throw new RuntimeException('FAILED: ' . $name); }
    $checks++;
    echo 'PASS ', $name, PHP_EOL;
}
function facePlusFixture(): array
{
    $face = ['face_rectangle' => ['top' => 1, 'left' => 2, 'width' => 120, 'height' => 140], 'face_token' => 'discard-synthetic-token'];
    return ['faces1' => [$face], 'faces2' => [$face], 'confidence' => 89.0,
        'thresholds' => ['1e-3' => 60.0, '1e-4' => 69.0, '1e-5' => 74.0],
        'request_id' => 'discard-synthetic-request', 'image_id1' => 'discard-synthetic-image'];
}
function facePlusParse(array $data): array
{
    return vnzIdentityFacePlusPlusParse(200, json_encode($data, JSON_THROW_ON_ERROR));
}
$us = vnzIdentityFacePlusPlusPolicy('us');
$cn = vnzIdentityFacePlusPlusPolicy('cn');
facePlusAssert($us['endpoint'] === 'https://api-us.faceplusplus.com/facepp/v3/compare'
    && $cn['endpoint'] === 'https://api-cn.faceplusplus.com/facepp/v3/compare', 'only explicit regional Compare endpoints');
foreach (['https://example.invalid', 'US', 'ca', 'us/../cn', 'us?redirect=1'] as $region) {
    facePlusAssert(vnzIdentityFacePlusPlusPolicy($region) === null, 'unrecognized region rejected: ' . $region);
}
facePlusAssert($us['verify_peer'] === true && $us['verify_host'] === 2 && !$us['follow_redirects'], 'TLS peer/hostname verification and no redirects');
facePlusAssert($us['connect_timeout'] === 10 && $us['timeout'] === 15 && $us['max_response_bytes'] === 65536, 'bounded connection/request/response policy');
$config = ['dual' => ['region' => 'us', 'api_key' => str_repeat('k', 24), 'api_secret' => str_repeat('s', 24), 'contract_confirmed' => true, 'matching_validated' => true]];
facePlusAssert(vnzIdentityFacePlusPlusConfigReady($config), 'complete strict configuration is structurally ready');
foreach (['region', 'api_key', 'api_secret', 'contract_confirmed', 'matching_validated'] as $field) {
    $bad = $config;
    unset($bad['dual'][$field]);
    facePlusAssert(!vnzIdentityFacePlusPlusConfigReady($bad), 'missing prerequisite fails closed: ' . $field);
}
$bad = $config; $bad['dual']['contract_confirmed'] = 'true';
facePlusAssert(!vnzIdentityFacePlusPlusConfigReady($bad), 'string truthiness cannot assert verified contract');
$bad = $config; $bad['dual']['api_secret'] = str_repeat('s', 20) . "\n";
facePlusAssert(!vnzIdentityFacePlusPlusConfigReady($bad), 'credential controls rejected');
$bad = $config; $bad['dual']['api_key'] = 'YOUR_API_KEY_REPLACE_ME';
facePlusAssert(!vnzIdentityFacePlusPlusConfigReady($bad), 'example credentials cannot become ready');
facePlusAssert(vnzIdentityFacePlusPlusMatch('', '', [])['outcome'] === 'unavailable', 'unconfigured match cannot reach provider');
facePlusAssert(vnzIdentityFacePlusPlusMatch('https://example.invalid/id.jpg', 'not-a-jpeg', $config)['code'] === 'provider_input', 'caller URLs/non-images rejected before transport');
facePlusAssert(!vnzIdentityFacePlusPlusCropValid(str_repeat('x', 2 * 1024 * 1024 + 1)), 'oversized image rejected before decoding');
$image = imagecreatetruecolor(96, 112);
imagefill($image, 0, 0, imagecolorallocate($image, 80, 110, 130));
ob_start(); imagejpeg($image); $syntheticJpeg = ob_get_clean(); imagedestroy($image);
facePlusAssert(vnzIdentityFacePlusPlusCropValid($syntheticJpeg), 'bounded synthetic JPEG crop accepted as transport input');
// Deliberately do not send the valid JPEG to Match: every check remains offline.
$pass = facePlusParse(facePlusFixture());
facePlusAssert($pass['outcome'] === 'pass' && $pass['confidence'] === 89.0 && $pass['threshold'] === 74.0, 'strict returned threshold drives normalized pass');
facePlusAssert(array_keys($pass) === ['outcome', 'code', 'confidence', 'threshold', 'threshold_key', 'engine'], 'only normalized minimal metadata survives');
facePlusAssert(!str_contains(json_encode($pass), 'discard-synthetic'), 'face/image/request tokens discarded');
$data = facePlusFixture(); $data['confidence'] = 74;
facePlusAssert(facePlusParse($data)['outcome'] === 'retry', 'confidence equal to strict threshold retries');
$data['confidence'] = 73.999;
facePlusAssert(facePlusParse($data)['outcome'] === 'retry', 'below-threshold response never approves');
$data['confidence'] = 100;
facePlusAssert(facePlusParse($data)['outcome'] === 'pass', 'finite maximum numeric confidence accepted');
foreach (['89', null, true, -1, 100.01, ['score' => 89]] as $value) {
    $data = facePlusFixture(); $data['confidence'] = $value;
    facePlusAssert(facePlusParse($data)['outcome'] === 'unavailable', 'malformed/out-of-range confidence fails closed');
}
foreach ([null, [], ['1e-4' => 69], ['1e-5' => '74'], ['1e-5' => 0], ['1e-5' => 100], ['1e-5' => 101]] as $thresholds) {
    $data = facePlusFixture(); $data['thresholds'] = $thresholds;
    facePlusAssert(facePlusParse($data)['code'] === 'provider_threshold', 'missing/invalid strict operating point fails closed');
}
foreach (['faces1', 'faces2'] as $name) {
    $data = facePlusFixture(); $data[$name] = [];
    facePlusAssert(facePlusParse($data)['outcome'] === 'retry', 'zero provider faces retries: ' . $name);
    $data[$name] = array_fill(0, 2, facePlusFixture()[$name][0]);
    facePlusAssert(facePlusParse($data)['outcome'] === 'retry', 'multiple provider faces retries: ' . $name);
    unset($data[$name]);
    facePlusAssert(facePlusParse($data)['outcome'] === 'unavailable', 'absent face schema fails closed: ' . $name);
}
$data = facePlusFixture(); $data['faces1'][0]['face_rectangle']['width'] = -1;
facePlusAssert(facePlusParse($data)['outcome'] === 'unavailable', 'invalid provider rectangle fails closed');
$data = facePlusFixture(); $data['error_message'] = 'SYNTHETIC_SECRET_NEVER_RETURN';
$error = facePlusParse($data);
facePlusAssert($error['outcome'] === 'unavailable' && !str_contains(json_encode($error), 'SYNTHETIC_SECRET'), 'provider error cannot override success or leak details');
foreach ([0, 301, 302, 400, 401, 403, 413, 429, 500, 503] as $status) {
    facePlusAssert(vnzIdentityFacePlusPlusParse($status, json_encode(facePlusFixture()))['outcome'] === 'unavailable', 'non-200 response fails closed: ' . $status);
}
foreach ([28, 35, 60, 6, 7, 23] as $curlError) {
    facePlusAssert(vnzIdentityFacePlusPlusParse(200, json_encode(facePlusFixture()), $curlError)['outcome'] === 'unavailable', 'timeout/TLS/network/truncation failure never approves: ' . $curlError);
}
foreach (['', '{', 'null', '[]', '{"confidence":89}', str_repeat('[', 20) . str_repeat(']', 20)] as $body) {
    facePlusAssert(vnzIdentityFacePlusPlusParse(200, $body)['outcome'] === 'unavailable', 'missing/malformed/deep response fails closed');
}
facePlusAssert(vnzIdentityFacePlusPlusParse(200, str_repeat(' ', 65537))['code'] === 'provider_response_size', 'oversized response never parsed');
require_once __DIR__ . '/../tools/identity_dual_private.php';
$temporary = sys_get_temp_dir() . '/vnz-dual-setup-test-' . bin2hex(random_bytes(8));
mkdir($temporary, 0700);
try {
    file_put_contents($temporary . '/config.php', 'old-test-state');
    identityDualSetupWrite($temporary . '/config.php', 'new-test-state');
    facePlusAssert(file_get_contents($temporary . '/config.php') === 'new-test-state', 'atomic replacement works on this platform without partial config');
    facePlusAssert(count(glob($temporary . '/*')) === 1 && count(glob($temporary . '/.*-dual-*.tmp')) === 0, 'atomic replacement leaves no plaintext temporary copies');
    try { identityDualSetupWrite($temporary . '/missing/config.php', 'must-not-write'); $failed = false; }
    catch (Throwable $error) { $failed = true; }
    facePlusAssert($failed && file_get_contents($temporary . '/config.php') === 'new-test-state', 'failed private write preserves existing configuration');
} finally {
    unlink($temporary . '/config.php');
    rmdir($temporary);
}
$pair = ['expected' => 'same', 'all_people_consented' => true, 'captured_for_this_validation' => true, 'id_path' => 'synthetic-id.jpg', 'selfie_path' => 'synthetic-selfie.jpg'];
$negative = array_replace($pair, ['expected' => 'different']);
$base = ['fresh_external_processing_consent' => true, 'provider_contract_confirmed' => true, 'pairs' => [$pair, $negative]];
$invalidPlans = [
    [[], 'consent'],
    [array_replace($base, ['fresh_external_processing_consent' => false]), 'consent'],
    [array_replace($base, ['fresh_external_processing_consent' => 'true']), 'consent'],
    [array_replace($base, ['provider_contract_confirmed' => false]), 'consent'],
    [array_replace($base, ['pairs' => []]), 'pair_plan'],
    [array_replace($base, ['pairs' => [$pair, $pair]]), 'pair_plan'],
    [array_replace($base, ['pairs' => array_fill(0, 7, $pair)]), 'pair_plan'],
    [array_replace($base, ['pairs' => [array_replace($pair, ['all_people_consented' => false]), $negative]]), 'pair_plan'],
];
foreach ($invalidPlans as [$payload, $expectedStage]) {
    $pipes = [];
    $process = proc_open([PHP_BINARY, '-d', 'display_errors=0', __DIR__ . '/../tools/validate_identity_dual.php'], [0 => ['pipe', 'r'], 1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes);
    if (!is_resource($process)) { throw new RuntimeException('Could not start isolated CLI validation rejection check.'); }
    fwrite($pipes[0], json_encode($payload, JSON_THROW_ON_ERROR)); fclose($pipes[0]);
    $stdout = stream_get_contents($pipes[1]); fclose($pipes[1]);
    $stderr = stream_get_contents($pipes[2]); fclose($pipes[2]);
    $exit = proc_close($process);
    $result = json_decode($stderr, true);
    facePlusAssert($exit === 1 && $stdout === '' && ($result['stage'] ?? '') === $expectedStage && ($result['activated'] ?? null) === false,
        'CLI rejects incomplete consent/coverage before private files, models or network');
}
echo 'Completed ', $checks, ' offline Face++ checks.', PHP_EOL;
