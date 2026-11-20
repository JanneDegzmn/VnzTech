<?php
/** Trusted local matcher for dual-v4. Input/output images stay in memory. */

function vnzIdentityLocalResult(string $outcome, string $code): array
{
    return ['outcome' => $outcome, 'code' => $code, 'distance' => null, 'threshold' => 0.45, 'crop_hash' => null, 'engine' => 'face-api.js-0.22.2', 'crop_policy' => 'portrait-v1'];
}

function vnzIdentityLocalImage(string $jpeg): GdImage
{
    $info = @getimagesizefromstring($jpeg);
    if (strlen($jpeg) > 5 * 1024 * 1024 || !$info || ($info['mime'] ?? '') !== 'image/jpeg' || min($info[0], $info[1]) < 128 || max($info[0], $info[1]) > 6000 || $info[0] * $info[1] > 16000000) {
        throw new DomainException('image_quality');
    }
    $image = @imagecreatefromstring($jpeg);
    if (!$image) {
        throw new DomainException('image_quality');
    }
    return $image;
}

function vnzIdentityLocalTensor(GdImage $image): array
{
    $ratio = min(1, 640 / max(imagesx($image), imagesy($image)));
    $width = max(1, (int) round(imagesx($image) * $ratio));
    $height = max(1, (int) round(imagesy($image) * $ratio));
    if (min($width, $height) < 128) {
        throw new DomainException('image_quality');
    }
    $scaled = imagecreatetruecolor($width, $height);
    try {
        imagecopyresampled($scaled, $image, 0, 0, 0, 0, $width, $height, imagesx($image), imagesy($image));
        $rgb = '';
        for ($y = 0; $y < $height; $y++) {
            for ($x = 0; $x < $width; $x++) {
                $pixel = imagecolorat($scaled, $x, $y);
                $rgb .= chr(($pixel >> 16) & 255) . chr(($pixel >> 8) & 255) . chr($pixel & 255);
            }
        }
        return ['width' => $width, 'height' => $height, 'rgb_base64' => base64_encode($rgb)];
    } finally {
        imagedestroy($scaled);
    }
}

function vnzIdentityLocalCrop(string $jpeg, array $box): string
{
    foreach (['x', 'y', 'width', 'height', 'score'] as $key) {
        if (!isset($box[$key]) || !is_numeric($box[$key]) || !is_finite((float) $box[$key])) {
            throw new RuntimeException('worker_protocol');
        }
    }
    if ($box['width'] <= 0 || $box['height'] <= 0 || $box['width'] > 1.2 || $box['height'] > 1.2 || $box['x'] < -0.2 || $box['y'] < -0.2 || $box['x'] > 1 || $box['y'] > 1 || $box['score'] < 0.5 || $box['score'] > 1) {
        throw new DomainException('image_quality');
    }
    $image = vnzIdentityLocalImage($jpeg);
    $crop = null;
    $scaled = null;
    try {
        $imageWidth = imagesx($image);
        $imageHeight = imagesy($image);
        $x = max(0, (int) floor(($box['x'] - $box['width'] * 0.2) * $imageWidth));
        $y = max(0, (int) floor(($box['y'] - $box['height'] * 0.2) * $imageHeight));
        $right = min($imageWidth, (int) ceil(($box['x'] + $box['width'] * 1.2) * $imageWidth));
        $bottom = min($imageHeight, (int) ceil(($box['y'] + $box['height'] * 1.2) * $imageHeight));
        $width = $right - $x;
        $height = $bottom - $y;
        if (min($width, $height) < 128) {
            throw new DomainException('image_quality');
        }
        $crop = imagecrop($image, ['x' => $x, 'y' => $y, 'width' => $width, 'height' => $height]);
        if (!$crop) {
            throw new DomainException('image_quality');
        }
        $scale = min(1, 640 / max($width, $height));
        $scaled = imagecreatetruecolor((int) round($width * $scale), (int) round($height * $scale));
        imagecopyresampled($scaled, $crop, 0, 0, 0, 0, imagesx($scaled), imagesy($scaled), $width, $height);
        ob_start();
        imagejpeg($scaled, null, 90);
        $bytes = ob_get_clean();
        if (!is_string($bytes) || $bytes === '' || strlen($bytes) > 1024 * 1024) {
            throw new DomainException('image_quality');
        }
        return $bytes;
    } finally {
        if ($scaled) { imagedestroy($scaled); }
        if ($crop) { imagedestroy($crop); }
        imagedestroy($image);
    }
}

function vnzIdentityLocalRun(array $payload, array $config, float $deadline): array
{
    $node = realpath((string) (($payload['action'] ?? '') === 'presence'
        ? ($config['face_submission']['node_binary'] ?? $config['dual']['node_binary'] ?? 'C:/Program Files/nodejs/node.exe')
        : ($config['dual']['node_binary'] ?? 'C:/Program Files/nodejs/node.exe')));
    $approvedNode = realpath('C:/Program Files/nodejs/node.exe');
    $worker = dirname(__DIR__) . '/tools/identity_match_worker.cjs';
    if (microtime(true) >= $deadline) {
        throw new RuntimeException('worker_timeout');
    }
    if (!$node || !$approvedNode || strcasecmp($node, $approvedNode) !== 0 || !is_file($worker)) {
        throw new RuntimeException('worker_unavailable');
    }
    $payload['schema_version'] = 1;
    $payload['nonce'] = bin2hex(random_bytes(16));
    $input = json_encode($payload, JSON_THROW_ON_ERROR);
    if (strlen($input) > 4 * 1024 * 1024) {
        throw new DomainException('image_quality');
    }
    $pipes = [];
    $environment = ['SystemRoot' => (string) (getenv('SystemRoot') ?: 'C:/Windows'), 'WINDIR' => (string) (getenv('WINDIR') ?: 'C:/Windows'), 'PATH' => dirname($node), 'TEMP' => sys_get_temp_dir(), 'TMP' => sys_get_temp_dir()];
    $process = @proc_open([$node, '--max-old-space-size=256', $worker], [0 => ['pipe', 'r'], 1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes, dirname(__DIR__), $environment, ['bypass_shell' => true, 'blocking_pipes' => false, 'suppress_errors' => true]);
    if (!is_resource($process)) {
        throw new RuntimeException('worker_unavailable');
    }
    $output = '';
    $offset = 0;
    try {
        foreach ($pipes as $pipe) { stream_set_blocking($pipe, false); }
        while (true) {
            if (microtime(true) >= $deadline) {
                throw new RuntimeException('worker_timeout');
            }
            if (isset($pipes[0])) {
                $written = @fwrite($pipes[0], substr($input, $offset, 4096));
                if ($written === false) { throw new RuntimeException('worker_unavailable'); }
                $offset += $written;
                if ($offset === strlen($input)) { fclose($pipes[0]); unset($pipes[0]); }
            }
            // Windows anonymous pipes ignore nonblocking reads. The fixed worker
            // emits one sub-4KB JSON response only after fully consuming stdin.
            // Poll lifetime before reading; otherwise an empty read deadlocks both ends.
            $status = proc_get_status($process);
            if (!$status['running']) {
                $output .= (string) stream_get_contents($pipes[1], 8193);
                $errorBytes = strlen((string) stream_get_contents($pipes[2], 8193));
                if ($errorBytes > 8192) { throw new RuntimeException('worker_protocol'); }
                break;
            }
            usleep(1000);
        }
        $result = json_decode($output, true, 16, JSON_THROW_ON_ERROR);
        if (strlen($output) > 8192 || !is_array($result) || ($result['schema_version'] ?? null) !== 1 || !is_string($result['nonce'] ?? null) || !hash_equals($payload['nonce'], $result['nonce']) || ($result['action'] ?? '') !== $payload['action'] || ($result['engine'] ?? '') !== 'face-api.js-0.22.2' || !in_array($result['outcome'] ?? '', ['pass', 'retry', 'unavailable'], true) || !in_array($result['code'] ?? '', ['faces_detected', 'face_present', 'matched', 'not_matching', 'no_face', 'multiple_faces', 'image_quality', 'invalid_request', 'worker_unavailable'], true)) {
            throw new RuntimeException('worker_protocol');
        }
        if ($result['outcome'] === 'pass' && $status['exitcode'] !== 0) {
            throw new RuntimeException('worker_protocol');
        }
        return $result;
    } finally {
        foreach ($pipes as $pipe) { if (is_resource($pipe)) { fclose($pipe); } }
        $status = proc_get_status($process);
        if ($status['running']) { proc_terminate($process, 9); }
        proc_close($process);
    }
}

function vnzIdentityLocalMatch(string $idJpeg, string $selfieJpeg, array $config): array
{
    $budget = min(30.0, max(0.05, (float) ($config['dual']['local_timeout_seconds'] ?? 30)));
    $deadline = microtime(true) + $budget;
    $lock = null;
    try {
        $root = realpath((string) ($config['storage_path'] ?? ''));
        if (!$root || (function_exists('vnzIdentityPublicPath') ? vnzIdentityPublicPath($root) : str_starts_with(strtolower(str_replace('\\', '/', $root)) . '/', strtolower(str_replace('\\', '/', dirname(__DIR__, 2))) . '/'))) {
            throw new RuntimeException('worker_unavailable');
        }
        $lock = @fopen($root . '/local-match.lock', 'c');
        if (!$lock || !flock($lock, LOCK_EX | LOCK_NB)) {
            return vnzIdentityLocalResult('unavailable', 'worker_busy');
        }
        $tensors = [];
        // Decode originals one at a time so two maximum-size uploads cannot double GD memory.
        foreach ([$idJpeg, $selfieJpeg] as $jpeg) {
            $image = vnzIdentityLocalImage($jpeg);
            try { $tensors[] = vnzIdentityLocalTensor($image); }
            finally { imagedestroy($image); }
        }
        $detected = vnzIdentityLocalRun(['action' => 'detect', 'images' => $tensors], $config, $deadline);
        unset($tensors);
        if ($detected['outcome'] !== 'pass') {
            return vnzIdentityLocalResult($detected['outcome'], $detected['code']);
        }
        if ($detected['code'] !== 'faces_detected' || ($detected['face_counts'] ?? null) !== [1, 1] || !is_array($detected['boxes'] ?? null) || count($detected['boxes']) !== 2) {
            throw new RuntimeException('worker_protocol');
        }
        $idCrop = vnzIdentityLocalCrop($idJpeg, $detected['boxes'][0]);
        $selfieCrop = vnzIdentityLocalCrop($selfieJpeg, $detected['boxes'][1]);
        if (strlen($idCrop) + strlen($selfieCrop) > 2 * 1024 * 1024) {
            throw new DomainException('image_quality');
        }
        $cropped = [vnzIdentityLocalImage($idCrop), vnzIdentityLocalImage($selfieCrop)];
        try {
            $compared = vnzIdentityLocalRun(['action' => 'compare', 'images' => array_map('vnzIdentityLocalTensor', $cropped)], $config, $deadline);
        } finally {
            foreach ($cropped as $image) { imagedestroy($image); }
        }
        if ($compared['outcome'] !== 'pass') {
            $result = vnzIdentityLocalResult($compared['outcome'], $compared['code']);
            if (isset($compared['distance']) && is_numeric($compared['distance']) && is_finite((float) $compared['distance'])) { $result['distance'] = (float) $compared['distance']; }
            return $result;
        }
        if ($compared['code'] !== 'matched' || ($compared['face_counts'] ?? null) !== [1, 1] || !isset($compared['distance']) || !is_numeric($compared['distance']) || !is_finite((float) $compared['distance']) || $compared['distance'] < 0 || $compared['distance'] > 0.45 || ($compared['threshold'] ?? null) !== 0.45) {
            throw new RuntimeException('worker_protocol');
        }
        return array_merge(vnzIdentityLocalResult('pass', 'matched'), ['distance' => (float) $compared['distance'], 'crop_hash' => hash('sha256', $idCrop . "\0" . $selfieCrop), 'id_crop' => $idCrop, 'selfie_crop' => $selfieCrop]);
    } catch (DomainException $error) {
        return vnzIdentityLocalResult('retry', 'image_quality');
    } catch (Throwable $error) {
        return vnzIdentityLocalResult('unavailable', in_array($error->getMessage(), ['worker_timeout', 'worker_protocol'], true) ? $error->getMessage() : 'worker_unavailable');
    } finally {
        if (is_resource($lock)) { flock($lock, LOCK_UN); fclose($lock); }
    }
}
