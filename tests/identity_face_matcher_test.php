<?php
require_once __DIR__ . '/../backend/identity_face_matcher.php';
$checks = 0;
function localCheck(bool $ok, string $name): void {
    global $checks;
    if (!$ok) { throw new RuntimeException('FAILED ' . $name); }
    $checks++;
    echo 'PASS ' . $name . PHP_EOL;
}
function localThrows(callable $fn, string $name): void {
    try { $fn(); } catch (Throwable $e) { localCheck(true, $name); return; }
    localCheck(false, $name);
}
$root = sys_get_temp_dir() . '/vnz-local-match-test-' . bin2hex(random_bytes(6));
mkdir($root, 0700);
$config = ['storage_path' => $root, 'dual' => ['node_binary' => 'C:/Program Files/nodejs/node.exe']];
$image = imagecreatetruecolor(400, 300);
imagefill($image, 0, 0, imagecolorallocate($image, 65, 90, 120));
imagefilledrectangle($image, 90, 60, 250, 240, imagecolorallocate($image, 180, 110, 80));
ob_start(); imagejpeg($image, null, 90); $jpeg = ob_get_clean(); imagedestroy($image);
$box = ['x' => 0.25, 'y' => 0.2, 'width' => 0.4, 'height' => 0.6, 'score' => 0.9];
try {
    $crop = vnzIdentityLocalCrop($jpeg, $box);
    localCheck($crop === vnzIdentityLocalCrop($jpeg, $box), 'portrait crop bytes are deterministic');
    $size = getimagesizefromstring($crop);
    localCheck($size[0] === 224 && $size[1] === 252 && $size['mime'] === 'image/jpeg', '20 percent padding uses canonical JPEG bounds');
    $edge = vnzIdentityLocalCrop($jpeg, ['x'=>0, 'y'=>0, 'width'=>1, 'height'=>1, 'score'=>1]);
    $size = getimagesizefromstring($edge);
    localCheck($size[0] === 400 && $size[1] === 300, 'portrait crop clamps to original boundaries');
    localThrows(fn() => vnzIdentityLocalCrop($jpeg, array_merge($box, ['x'=>NAN])), 'nonfinite bounding box rejected');
    localThrows(fn() => vnzIdentityLocalCrop($jpeg, array_merge($box, ['width'=>0])), 'zero-size face rejected');
    localThrows(fn() => vnzIdentityLocalCrop($jpeg, array_merge($box, ['score'=>0.49])), 'low-confidence face rejected');
    localThrows(fn() => vnzIdentityLocalCrop($jpeg, array_merge($box, ['width'=>0.01,'height'=>0.01])), 'tiny unreadable portrait rejected');
    $bad = vnzIdentityLocalMatch('not-an-image', $jpeg, $config);
    localCheck($bad['outcome'] === 'retry' && $bad['code'] === 'image_quality' && !isset($bad['id_crop']), 'malformed JPEG cannot produce approval or crop bytes');
    $handle = fopen($root . '/local-match.lock', 'c'); flock($handle, LOCK_EX);
    $busy = vnzIdentityLocalMatch($jpeg, $jpeg, $config);
    localCheck($busy['outcome'] === 'unavailable' && $busy['code'] === 'worker_busy', 'occupied private semaphore fails fast');
    flock($handle, LOCK_UN); fclose($handle);
    $missingNode = $config; $missingNode['dual']['node_binary'] = $root . '/not-node.exe';
    $bad = vnzIdentityLocalMatch($jpeg, $jpeg, $missingNode);
    localCheck($bad['outcome'] === 'unavailable' && !isset($bad['id_crop']), 'missing/unapproved executable cannot approve');
    $short = $config; $short['dual']['local_timeout_seconds'] = 0.05;
    $started = microtime(true);
    $timed = vnzIdentityLocalMatch($jpeg, $jpeg, $short);
    localCheck($timed['outcome'] === 'unavailable' && $timed['code'] === 'worker_timeout' && microtime(true) - $started < 5, 'worker timeout terminates process and blocks approval');
    $blankImage = imagecreatetruecolor(128,128); ob_start(); imagejpeg($blankImage,null,90); $blank = ob_get_clean(); imagedestroy($blankImage);
    $result = vnzIdentityLocalMatch($blank, $blank, $config);
    localCheck($result['outcome'] === 'retry' && $result['code'] === 'no_face' && !isset($result['id_crop']) && $result['threshold'] === 0.45, 'real worker blank inference cannot pass and releases prior timeout lock');
    $public = $config; $public['storage_path'] = dirname(__DIR__);
    localCheck(vnzIdentityLocalMatch($jpeg,$jpeg,$public)['outcome'] === 'unavailable', 'semaphore cannot be placed in public documentroot');
    echo 'Completed ' . $checks . ' synthetic local matcher checks; no personal evidence or provider calls.' . PHP_EOL;
} finally {
    $path = $root . '/local-match.lock';
    if (is_file($path)) { unlink($path); }
    rmdir($root);
}
