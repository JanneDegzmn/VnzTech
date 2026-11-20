<?php
/** Server-only Face++ Compare adapter. Never return bodies, tokens or credentials. */
declare(strict_types=1);

function vnzIdentityFacePlusPlusResult(string $outcome, string $code, ?float $confidence = null, ?float $threshold = null): array
{
    return [
        'outcome' => $outcome,
        'code' => $code,
        'confidence' => $confidence,
        'threshold' => $threshold,
        'threshold_key' => '1e-5',
        'engine' => 'faceplusplus-v3',
    ];
}

/** No custom endpoints, URLs, proxy or caller-selected operating point. */
function vnzIdentityFacePlusPlusPolicy(string $region): ?array
{
    $endpoints = [
        'us' => 'https://api-us.faceplusplus.com/facepp/v3/compare',
        'cn' => 'https://api-cn.faceplusplus.com/facepp/v3/compare',
    ];
    if (!isset($endpoints[$region])) { return null; }
    return [
        'endpoint' => $endpoints[$region],
        'connect_timeout' => 10,
        'timeout' => 15,
        'max_image_bytes' => 2 * 1024 * 1024,
        'max_response_bytes' => 65536,
        'max_header_bytes' => 16384,
        'verify_peer' => true,
        'verify_host' => 2,
        'follow_redirects' => false,
        'threshold_key' => '1e-5',
    ];
}

function vnzIdentityFacePlusPlusConfigReady(array $config): bool
{
    $dual = $config['dual'] ?? null;
    if (!is_array($dual) || !is_string($dual['region'] ?? null) || !vnzIdentityFacePlusPlusPolicy($dual['region'])) { return false; }
    foreach (['api_key', 'api_secret'] as $name) {
        // Face++ credentials are opaque printable tokens; controls/whitespace are never valid.
        $value = $dual[$name] ?? null;
        if (!is_string($value) || !preg_match('/\A[\x21-\x7e]{16,256}\z/D', $value)) { return false; }
        if (preg_match('/(?:REPLACE|YOUR[_ -]|EXAMPLE|PLACEHOLDER)/i', $value)) { return false; }
    }
    return ($dual['contract_confirmed'] ?? null) === true && ($dual['matching_validated'] ?? null) === true;
}

function vnzIdentityFacePlusPlusCropValid(string $bytes): bool
{
    if (strlen($bytes) < 64 || strlen($bytes) > 2 * 1024 * 1024) { return false; }
    $image = @getimagesizefromstring($bytes);
    return is_array($image) && ($image[2] ?? null) === IMAGETYPE_JPEG
        && ($image[0] ?? 0) >= 32 && $image[0] <= 2048
        && ($image[1] ?? 0) >= 32 && $image[1] <= 2048;
}

function vnzIdentityFacePlusPlusNumber(mixed $value): ?float
{
    if (!is_int($value) && !is_float($value)) { return null; }
    $number = (float) $value;
    return is_finite($number) && $number >= 0.0 && $number <= 100.0 ? $number : null;
}

/** Pure response validation for fixtures; it does not confer submission authority. */
function vnzIdentityFacePlusPlusParse(int $httpStatus, string $body, int $transportError = 0): array
{
    if ($transportError !== 0) { return vnzIdentityFacePlusPlusResult('unavailable', 'provider_transport'); }
    if (strlen($body) > 65536) { return vnzIdentityFacePlusPlusResult('unavailable', 'provider_response_size'); }
    if ($httpStatus !== 200) {
        $code = match ($httpStatus) {
            401, 403 => 'provider_auth_or_quota',
            429 => 'provider_rate_limit',
            default => 'provider_http',
        };
        return vnzIdentityFacePlusPlusResult('unavailable', $code);
    }
    try { $data = json_decode($body, true, 16, JSON_THROW_ON_ERROR); }
    catch (Throwable $error) { return vnzIdentityFacePlusPlusResult('unavailable', 'provider_response'); }
    if (!is_array($data) || array_is_list($data)) { return vnzIdentityFacePlusPlusResult('unavailable', 'provider_response'); }
    if (array_key_exists('error_message', $data)) {
        // Deliberately do not echo provider text: it can contain request details.
        return vnzIdentityFacePlusPlusResult('unavailable', 'provider_error');
    }
    foreach (['faces1', 'faces2'] as $name) {
        if (!isset($data[$name]) || !is_array($data[$name]) || !array_is_list($data[$name])) {
            return vnzIdentityFacePlusPlusResult('unavailable', 'provider_response');
        }
        if (count($data[$name]) !== 1) { return vnzIdentityFacePlusPlusResult('retry', 'provider_face_count'); }
        $rectangle = $data[$name][0]['face_rectangle'] ?? null;
        if (!is_array($rectangle)) { return vnzIdentityFacePlusPlusResult('unavailable', 'provider_response'); }
        foreach (['top', 'left', 'width', 'height'] as $field) {
            $value = $rectangle[$field] ?? null;
            if (!is_int($value) || $value < (in_array($field, ['width', 'height'], true) ? 1 : 0) || $value > 2048) {
                return vnzIdentityFacePlusPlusResult('unavailable', 'provider_response');
            }
        }
    }
    $confidence = vnzIdentityFacePlusPlusNumber($data['confidence'] ?? null);
    $thresholds = $data['thresholds'] ?? null;
    $threshold = is_array($thresholds) ? vnzIdentityFacePlusPlusNumber($thresholds['1e-5'] ?? null) : null;
    if ($confidence === null || $threshold === null || $threshold <= 0.0 || $threshold >= 100.0) {
        return vnzIdentityFacePlusPlusResult('unavailable', 'provider_threshold');
    }
    // Strictly exceed the returned operating point. Equality is a retry, not approval.
    return $confidence > $threshold
        ? vnzIdentityFacePlusPlusResult('pass', 'provider_match', $confidence, $threshold)
        : vnzIdentityFacePlusPlusResult('retry', 'provider_no_match', $confidence, $threshold);
}

/** Raw JPEG bytes from the trusted server cropper only; no transport injection. */
function vnzIdentityFacePlusPlusMatch(string $idCrop, string $selfieCrop, array $config): array
{
    if (!vnzIdentityFacePlusPlusConfigReady($config)) { return vnzIdentityFacePlusPlusResult('unavailable', 'provider_configuration'); }
    if (!vnzIdentityFacePlusPlusCropValid($idCrop) || !vnzIdentityFacePlusPlusCropValid($selfieCrop)) {
        return vnzIdentityFacePlusPlusResult('retry', 'provider_input');
    }
    if (!extension_loaded('curl') || !class_exists('CURLStringFile')) {
        return vnzIdentityFacePlusPlusResult('unavailable', 'provider_runtime');
    }
    $dual = $config['dual'];
    $policy = vnzIdentityFacePlusPlusPolicy($dual['region']);
    $handle = null;
    $body = '';
    $headerBytes = 0;
    $oversized = false;
    try {
        $handle = @curl_init($policy['endpoint']);
        if ($handle === false) { return vnzIdentityFacePlusPlusResult('unavailable', 'provider_runtime'); }
        $options = [
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => [
                'api_key' => $dual['api_key'],
                'api_secret' => $dual['api_secret'],
                'image_file1' => new CURLStringFile($idCrop, 'id-portrait.jpg', 'image/jpeg'),
                'image_file2' => new CURLStringFile($selfieCrop, 'camera-selfie.jpg', 'image/jpeg'),
            ],
            CURLOPT_HTTPHEADER => ['Accept: application/json', 'Expect:'],
            CURLOPT_CONNECTTIMEOUT => $policy['connect_timeout'],
            CURLOPT_TIMEOUT => $policy['timeout'],
            CURLOPT_NOSIGNAL => true,
            CURLOPT_SSL_VERIFYPEER => $policy['verify_peer'],
            CURLOPT_SSL_VERIFYHOST => $policy['verify_host'],
            CURLOPT_SSLVERSION => CURL_SSLVERSION_TLSv1_2,
            CURLOPT_FOLLOWLOCATION => $policy['follow_redirects'],
            CURLOPT_MAXREDIRS => 0,
            CURLOPT_PROTOCOLS => CURLPROTO_HTTPS,
            CURLOPT_REDIR_PROTOCOLS => CURLPROTO_HTTPS,
            CURLOPT_PROXY => '',
            CURLOPT_NOPROXY => '*',
            CURLOPT_VERBOSE => false,
            CURLOPT_HEADERFUNCTION => static function ($curl, string $line) use (&$headerBytes, &$oversized, $policy): int {
                $headerBytes += strlen($line);
                if ($headerBytes > $policy['max_header_bytes']) { $oversized = true; return 0; }
                return strlen($line);
            },
            CURLOPT_WRITEFUNCTION => static function ($curl, string $chunk) use (&$body, &$oversized, $policy): int {
                if (strlen($body) + strlen($chunk) > $policy['max_response_bytes']) { $oversized = true; return 0; }
                $body .= $chunk;
                return strlen($chunk);
            },
        ];
        if (!@curl_setopt_array($handle, $options)) { return vnzIdentityFacePlusPlusResult('unavailable', 'provider_runtime'); }
        @curl_exec($handle);
        if ($oversized) { return vnzIdentityFacePlusPlusResult('unavailable', 'provider_response_size'); }
        $transportError = curl_errno($handle);
        $status = (int) curl_getinfo($handle, CURLINFO_HTTP_CODE);
        $contentType = strtolower((string) curl_getinfo($handle, CURLINFO_CONTENT_TYPE));
        if ($transportError === 0 && $status === 200 && !preg_match('~\Aapplication/json(?:\s*;|\z)~', $contentType)) {
            return vnzIdentityFacePlusPlusResult('unavailable', 'provider_content_type');
        }
        return vnzIdentityFacePlusPlusParse($status, $body, $transportError);
    } catch (Throwable $error) {
        return vnzIdentityFacePlusPlusResult('unavailable', 'provider_runtime');
    } finally {
        if ($handle instanceof CurlHandle) { curl_close($handle); }
        unset($options, $dual, $body);
    }
}
