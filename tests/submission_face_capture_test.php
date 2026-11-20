<?php
/** Synthetic isolated checks: no app database, personal photos or cloud API. */
require_once __DIR__ . '/../backend/submission_security_face.php';
$checks = 0;
function faceCheck(bool $ok, string $name): void {
    global $checks;
    if (!$ok) { throw new RuntimeException('FAILED: ' . $name); }
    $checks++;
    echo 'PASS ' . $name . PHP_EOL;
}
function faceReject(callable $fn, string $name, string $part = ''): void {
    try { $fn(); } catch (Throwable $error) {
        faceCheck($part === '' || str_contains($error->getMessage(), $part), $name);
        return;
    }
    faceCheck(false, $name);
}
function facePreparedFixture(string $jpeg, string $context, ?int $userId, string $nonce): array {
    // Trusted preparation fixture for transaction/storage tests only.
    // This deliberately is NOT evidence of a model accepting this blank image.
    return ['jpeg' => $jpeg, 'photo_hash' => hash('sha256', $jpeg), 'nonce' => $nonce, 'nonce_hash' => hash('sha256', $nonce),
        'context' => $context, 'user_id' => $userId, 'created_at' => vnzIdentityNow(), 'policy_version' => vnzSubmissionFacePolicy(),
        'detector_version' => 'face-api.js-0.22.2', 'consent_version' => 'face_submission_v6'];
}
$root = sys_get_temp_dir() . '/vnz-submission-face-' . bin2hex(random_bytes(6));
mkdir($root, 0700);
mkdir($root . '/evidence', 0700);
file_put_contents($root . '/key.bin', random_bytes(32));
$config = ['enabled' => true, 'mode' => 'face_submission_v6', 'storage_path' => $root, 'key_path' => $root . '/key.bin',
    'face_submission' => ['node_binary' => 'C:/Program Files/nodejs/node.exe', 'local_timeout_seconds' => 30]];
file_put_contents($root . '/config.php', '<?php return ' . var_export($config, true) . ';');
putenv('VNZ_IDENTITY_CONFIG_PATH=' . $root . '/config.php');
$_SESSION = [];
$pdo = new PDO('sqlite::memory:', null, null, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC]);
$pdo->exec('PRAGMA foreign_keys=ON');
$pdo->exec('CREATE TABLE users (id INTEGER PRIMARY KEY,email TEXT,email_verified_at TEXT,is_active INTEGER)');
$pdo->exec("INSERT INTO users VALUES (1,'seller@example.invalid','2026-01-01',1),(2,'inactive@example.invalid',NULL,0)");
$pdo->exec('CREATE TABLE submissions (id INTEGER PRIMARY KEY,user_id INTEGER NULL,status TEXT,updated_at TEXT)');
$pdo->exec('CREATE TABLE identity_attempts (id INTEGER,status TEXT,grant_expires_at TEXT,purge_after TEXT,updated_at TEXT,expires_at TEXT,purged_at TEXT,id_file TEXT,selfie_file TEXT)');
foreach (['identity_bindings','identity_audit'] as $table) { $pdo->exec('CREATE TABLE ' . $table . ' (id INTEGER)'); }
$pdo->exec('CREATE TABLE submission_security_faces (submission_id INTEGER PRIMARY KEY,file_name TEXT UNIQUE NOT NULL,photo_hash TEXT NOT NULL,nonce_hash TEXT UNIQUE NOT NULL,context TEXT,user_id INTEGER NULL,policy_version TEXT,detector_version TEXT,consent_at TEXT,created_at TEXT,terminal_at TEXT NULL,purge_after TEXT NULL,purged_at TEXT NULL,FOREIGN KEY(submission_id) REFERENCES submissions(id))');
$blank = imagecreatetruecolor(160, 180);
imagefill($blank, 0, 0, imagecolorallocate($blank, 50, 80, 100));
imagejpeg($blank, $root . '/blank.jpg', 90);
imagedestroy($blank);
$file = ['error' => UPLOAD_ERR_OK, 'tmp_name' => $root . '/blank.jpg'];
try {
    $guest = vnzSubmissionFaceToken('guest');
    faceCheck(strlen($guest) === 48 && $guest === vnzSubmissionFaceToken('guest'), 'guest capture nonce is random and stable within this draft');
    vnzSubmissionFaceCheckNonce('guest', null, $guest);
    faceCheck(true, 'guest nonce belongs to the guest context even if an account exists later');
    faceReject(fn() => vnzSubmissionFaceToken('account'), 'account token requires seller login');
    faceReject(fn() => vnzSubmissionFaceToken('admin'), 'unsupported actor context rejected');
    $_SESSION['user_logged_in'] = true;
    $_SESSION['user_id'] = 1;
    $account = vnzSubmissionFaceToken('account');
    faceCheck($account !== $guest, 'guest and account drafts never share a nonce');
    faceReject(fn() => vnzSubmissionFaceCheckNonce('account', 1, $guest), 'guest token cannot authorize account submission');
    faceReject(fn() => vnzSubmissionFaceCheckNonce('guest', 1, $guest), 'guest actor never inherits the logged-in seller');
    $_SESSION['user_id'] = 2;
    faceReject(fn() => vnzSubmissionFaceCheckNonce('account', 2, $account), 'changing account invalidates the earlier account token');
    $_SESSION['user_id'] = 1;
    $refreshed = vnzSubmissionFaceToken('guest', true);
    faceCheck($refreshed !== $guest, 'opening camera can refresh an old draft nonce');
    faceReject(fn() => vnzSubmissionFaceCheckNonce('guest', null, $guest), 'refresh invalidates the stale nonce');
    $_SESSION['submission_face_nonce']['guest']['issued_at'] = time() - 601;
    faceReject(fn() => vnzSubmissionFaceCheckNonce('guest', null, $refreshed), 'nonce expires in ten minutes');
    $guest = vnzSubmissionFaceToken('guest');
    faceReject(fn() => vnzSubmissionFaceCheckNonce('guest', null, str_repeat('z', 48)), 'malformed nonce rejected');
    faceReject(fn() => vnzSubmissionFacePrepare($pdo, 'guest', null, $file, $guest, false), 'explicit selfie consent required', 'agree');
    $pdo->beginTransaction();
    faceReject(fn() => vnzSubmissionFacePrepare($pdo, 'guest', null, $file, $guest, true), 'model work refused inside database transaction');
    $pdo->rollBack();
    faceReject(fn() => vnzSubmissionFaceReady($pdo), 'activation requires current cleanup heartbeat');
    file_put_contents($root . '/cleanup-heartbeat.json', json_encode(['success_at' => time()]));
    vnzSubmissionFaceReady($pdo);
    faceCheck(true, 'presence readiness uses pinned models/private storage with no Face++ keys or validation flags');
    file_put_contents($root . '/cleanup-heartbeat.json', json_encode(['success_at' => time() - 172801]));
    faceReject(fn() => vnzSubmissionFaceReady($pdo), 'stale cleanup blocks replacement mode');
    file_put_contents($root . '/cleanup-heartbeat.json', json_encode(['success_at' => time()]));
    $badNode = $config; $badNode['face_submission']['node_binary'] = $root . '/fake-node.exe';
    faceReject(fn() => vnzSubmissionFaceReady($pdo, $badNode), 'unapproved or missing node binary blocks readiness');
    file_put_contents($root . '/fake.jpg', '<?php echo "fake";');
    faceReject(fn() => vnzSubmissionFaceImage(['error' => 0, 'tmp_name' => $root . '/fake.jpg']), 'file content validation rejects fake image');
    faceReject(fn() => vnzSubmissionFaceImage(['error' => UPLOAD_ERR_NO_FILE]), 'missing camera image rejected');
    file_put_contents($root . '/with-tail.jpg', file_get_contents($file['tmp_name']) . 'PRIVATE-METADATA-TAIL');
    $clean = vnzSubmissionFaceImage(['error' => 0, 'tmp_name' => $root . '/with-tail.jpg']);
    faceCheck(!str_contains($clean, 'PRIVATE-METADATA-TAIL') && getimagesizefromstring($clean)['mime'] === 'image/jpeg', 'canonical JPEG strips trailing data and uploaded metadata');
    $wide = imagecreatetruecolor(2000, 1000); imagepng($wide, $root . '/wide.png'); imagedestroy($wide);
    $scaled = getimagesizefromstring(vnzSubmissionFaceImage(['error' => 0, 'tmp_name' => $root . '/wide.png']));
    faceCheck($scaled[0] === 1280 && $scaled[1] === 640, 'private canonical image has bounded dimensions');
    $blocked = fopen($root . '/local-match.lock', 'c'); flock($blocked, LOCK_EX);
    faceCheck(vnzSubmissionFacePresence($clean, $config) === ['outcome' => 'unavailable', 'code' => 'worker_busy'], 'shared occupied semaphore blocks another model run immediately');
    flock($blocked, LOCK_UN); fclose($blocked);
    faceCheck(vnzSubmissionFacePresence($clean, $badNode)['outcome'] === 'unavailable', 'missing local binary cannot produce a face pass');
    $short = $config; $short['face_submission']['local_timeout_seconds'] = 0.05;
    $start = microtime(true);
    faceCheck(vnzSubmissionFacePresence($clean, $short)['outcome'] === 'unavailable' && microtime(true) - $start < 5, 'presence worker timeout fails closed within a bounded wait');
    faceCheck(vnzSubmissionFacePresence($clean, $config) === ['outcome' => 'retry', 'code' => 'no_face'], 'real one-image CPU detector refuses the blank synthetic photo');
    faceReject(fn() => vnzSubmissionFacePrepare($pdo, 'guest', null, $file, $guest, true), 'real preparation rejects blank face before permanent evidence or submission', 'No clear face');
    faceCheck(count(glob($root . '/evidence/*.enc') ?: []) === 0, 'rejected model input creates no stored evidence');
    $_SESSION['user_id'] = 2;
    $inactiveNonce = vnzSubmissionFaceToken('account', true);
    faceReject(fn() => vnzSubmissionFacePrepare($pdo, 'account', 2, $file, $inactiveNonce, true), 'inactive seller rejected before face processing', 'active seller');
    $_SESSION['user_id'] = 1;
    $_SESSION['submission_face_rate'] = array_fill(0, 20, time());
    faceReject(fn() => vnzSubmissionFacePrepare($pdo, 'guest', null, $file, $guest, true), 'session rate cap prevents another expensive check', 'Too many');
    unset($_SESSION['submission_face_rate']);
    $prepared = facePreparedFixture($clean, 'guest', null, $guest);
    faceReject(fn() => vnzSubmissionFaceStore($pdo, 1, $prepared), 'linking photo outside transaction rejected');
    $pdo->exec("INSERT INTO submissions VALUES (1,NULL,'new_submission','" . vnzIdentityNow() . "'),(2,NULL,'new_submission','" . vnzIdentityNow() . "'),(3,1,'new_submission','" . vnzIdentityNow() . "')");
    $pdo->beginTransaction();
    $changed = $prepared; $changed['jpeg'] .= 'altered';
    faceReject(fn() => vnzSubmissionFaceStore($pdo, 1, $changed), 'photo mutation after check rejected before encryption');
    $changed = $prepared; $changed['created_at'] = vnzIdentityAfter(-601);
    faceReject(fn() => vnzSubmissionFaceStore($pdo, 1, $changed), 'stale prepared check cannot link to a new submission');
    $changed = $prepared; $changed['policy_version'] = 'manual-v2';
    faceReject(fn() => vnzSubmissionFaceStore($pdo, 1, $changed), 'stale identity policy cannot authorize this face capture');
    faceReject(fn() => vnzSubmissionFaceStore($pdo, 3, $prepared), 'guest selfie cannot be attached to account-owned submission');
    $name = vnzSubmissionFaceStore($pdo, 1, $prepared);
    $pdo->commit();
    faceCheck(preg_match('/^[a-f0-9]{48}\.enc$/D', $name) === 1, 'evidence has private random filename with no user data');
    $path = vnzIdentityEvidencePath($name);
    $blob = file_get_contents($path);
    faceCheck($blob !== $clean && substr($blob, 0, 4) === 'VNZ1' && vnzIdentityDecrypt($blob, vnzIdentityKey()) === $clean, 'committed private photo is authenticated AES-GCM ciphertext');
    faceCheck(vnzSubmissionFacePhoto($pdo, 1) === $clean, 'already-authorized photo helper returns exact checked canonical bytes');
    $meta = vnzSubmissionFaceMeta($pdo, 1);
    faceCheck($meta['state'] === 'available' && $meta['available'] && $meta['policy_version'] === vnzSubmissionFacePolicy(), 'inline metadata distinguishes available photo from older submissions');
    faceCheck(vnzSubmissionFaceMeta($pdo, 999) === null, 'historical submission without photo has no fabricated evidence');
    faceReject(fn() => vnzSubmissionFacePrepare($pdo, 'guest', null, $file, $guest, true), 'committed nonce replay rejected before model execution', 'already submitted');
    $pdo->beginTransaction();
    faceReject(fn() => vnzSubmissionFaceStore($pdo, 2, $prepared), 'database unique nonce prevents attaching one check to two submissions');
    $pdo->rollBack();
    faceCheck(count(glob($root . '/evidence/*.enc') ?: []) === 1, 'failed duplicate attachment deletes only its newly written evidence');
    vnzSubmissionFaceForget('guest', str_repeat('f', 48));
    vnzSubmissionFaceCheckNonce('guest', null, $guest);
    faceCheck(true, 'forget never consumes a newer or unrelated nonce');
    vnzSubmissionFaceForget('guest', $guest);
    faceReject(fn() => vnzSubmissionFaceCheckNonce('guest', null, $guest), 'successful commit consumes the matching session nonce');
    $next = vnzSubmissionFaceToken('guest');
    faceCheck($next !== $guest, 'next guest submission requires a fresh capture nonce');
    $pending = facePreparedFixture($clean, 'guest', null, $next);
    $pdo->beginTransaction();
    $rollbackName = vnzSubmissionFaceStore($pdo, 2, $pending);
    $pdo->rollBack();
    vnzSubmissionFaceRemove($rollbackName);
    faceCheck(vnzSubmissionFaceMeta($pdo, 2) === null && !is_file(vnzIdentityEvidencePath($rollbackName)), 'submission rollback leaves neither linked row nor private photo');
    vnzSubmissionFaceCheckNonce('guest', null, $next);
    faceCheck(true, 'rollback leaves current nonce usable for a retry');
    $pdo->beginTransaction(); $pendingName = vnzSubmissionFaceStore($pdo, 2, $pending); $pdo->commit();
    $activeNonce = vnzSubmissionFaceToken('account', true);
    $accountPrepared = facePreparedFixture($clean, 'account', 1, $activeNonce);
    $pdo->beginTransaction();
    $pdo->exec('UPDATE users SET is_active=0 WHERE id=1');
    faceReject(fn() => vnzSubmissionFaceStore($pdo, 3, $accountPrepared), 'seller deactivation during model work blocks transactional attachment', 'active seller');
    $pdo->rollBack();
    $pdo->beginTransaction();
    $accountName = vnzSubmissionFaceStore($pdo, 3, $accountPrepared);
    $pdo->commit();
    faceCheck(vnzSubmissionFaceMeta($pdo, 3)['context'] === 'account' && vnzSubmissionFacePhoto($pdo, 3) === $clean, 'active seller receives a new exact submission-linked private selfie');
    $bad = $blob; $bad[strlen($bad)-1] = chr(ord($bad[strlen($bad)-1]) ^ 1); file_put_contents($path, $bad);
    faceReject(fn() => vnzSubmissionFacePhoto($pdo, 1), 'tampered ciphertext cannot be served');
    file_put_contents($path, $blob);
    $pdo->prepare('UPDATE submission_security_faces SET photo_hash=? WHERE submission_id=1')->execute([str_repeat('a',64)]);
    faceReject(fn() => vnzSubmissionFacePhoto($pdo, 1), 'DB photo hash binds the exact decrypted canonical photo');
    $pdo->prepare('UPDATE submission_security_faces SET photo_hash=? WHERE submission_id=1')->execute([hash('sha256', $clean)]);
    $pdo->beginTransaction();
    $pdo->exec("UPDATE submissions SET status='accepted' WHERE id=1");
    vnzSubmissionFaceMarkTerminal($pdo, 1, 'accepted');
    $pdo->commit();
    $terminal = vnzSubmissionFaceMeta($pdo, 1);
    faceCheck($terminal['terminal_at'] !== null && strtotime($terminal['purge_after'] . ' UTC') - strtotime($terminal['terminal_at'] . ' UTC') === 30 * 86400, 'terminal transition latches exactly thirty days of retention');
    $pdo->exec("UPDATE submissions SET updated_at='2099-01-01 00:00:00' WHERE id=1");
    vnzSubmissionFaceMarkTerminal($pdo, 1, 'accepted');
    faceCheck(vnzSubmissionFaceMeta($pdo, 1)['purge_after'] === $terminal['purge_after'], 'later notification updates and repeated terminal calls never extend retention');
    $pdo->prepare('UPDATE submission_security_faces SET terminal_at=?,purge_after=? WHERE submission_id=1')->execute([vnzIdentityAfter(-31*86400), vnzIdentityAfter(-86400)]);
    $expired = vnzSubmissionFaceMeta($pdo, 1);
    faceCheck($expired['state'] === 'expired' && !$expired['available'] && is_file($path), 'expired photo is denied before next physical cleanup');
    faceReject(fn() => vnzSubmissionFacePhoto($pdo, 1), 'expired terminal photo cannot be read');
    $result = vnzSubmissionFaceCleanup($pdo);
    faceCheck($result['face_purged'] === 1 && !is_file($path) && vnzSubmissionFaceMeta($pdo, 1)['state'] === 'purged', 'cleanup removes expired raw photo but preserves minimal record');
    faceCheck(is_file(vnzIdentityEvidencePath($pendingName)) && vnzSubmissionFaceMeta($pdo, 2)['available'], 'pending submission photo is retained until terminal state');
    faceCheck(vnzSubmissionFaceCleanup($pdo)['face_purged'] === 0, 'cleanup is idempotent');
    touch(vnzIdentityEvidencePath($pendingName), time() - 2 * 86400);
    $orphanName = bin2hex(random_bytes(24)) . '.enc';
    file_put_contents(vnzIdentityEvidencePath($orphanName), vnzIdentityEncrypt($clean, vnzIdentityKey()));
    touch(vnzIdentityEvidencePath($orphanName), time() - 2 * 86400);
    $sharedCleanup = vnzIdentityCleanup($pdo);
    faceCheck(is_file(vnzIdentityEvidencePath($pendingName)), 'shared legacy orphan sweep preserves linked selfie older than 24 hours');
    faceCheck($sharedCleanup['orphaned'] === 1 && !is_file(vnzIdentityEvidencePath($orphanName)), 'shared daily cleanup still removes genuinely unlinked abandoned evidence');
    $pdo->prepare('UPDATE submission_security_faces SET file_name=? WHERE submission_id=2')->execute(['../key.bin']);
    faceReject(fn() => vnzSubmissionFaceMeta($pdo, 2), 'private path traversal fails before file access');
    $pdo->prepare('UPDATE submission_security_faces SET file_name=? WHERE submission_id=2')->execute([$pendingName]);
    unlink(vnzIdentityEvidencePath($pendingName));
    faceCheck(vnzSubmissionFaceMeta($pdo, 2)['state'] === 'unavailable', 'missing private evidence is unavailable rather than a fabricated pass');
    echo 'Completed ' . $checks . ' isolated selfie checks. Synthetic trusted fixtures cover storage only; real blank model refusal is separate. No identity/liveness claim.' . PHP_EOL;
} finally {
    if ($pdo->inTransaction()) { $pdo->rollBack(); }
    foreach (glob($root . '/evidence/*') ?: [] as $path) { if (is_file($path)) { unlink($path); } }
    foreach (glob($root . '/*') ?: [] as $path) { if (is_file($path)) { unlink($path); } }
    rmdir($root . '/evidence'); rmdir($root);
    putenv('VNZ_IDENTITY_CONFIG_PATH');
}
