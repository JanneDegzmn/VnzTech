<?php
/** Per-submission private selfie. Presence is not identity verification or liveness. */
require_once __DIR__ . '/identity_verification.php';

function vnzSubmissionFacePolicy(): string
{
    return 'face-submission-v6';
}

function vnzSubmissionFaceActor(string $context): ?int
{
    if (!in_array($context, ['guest', 'account'], true)) {
        throw new DomainException('Refresh the submission form and try again.');
    }
    if ($context === 'guest') { return null; }
    $id = (int) ($_SESSION['user_id'] ?? 0);
    if (empty($_SESSION['user_logged_in']) || $id < 1) {
        throw new DomainException('Please log in to your seller account again.');
    }
    return $id;
}

function vnzSubmissionFaceToken(string $context, bool $refresh = false): string
{
    $actor = vnzSubmissionFaceActor($context);
    $entry = $_SESSION['submission_face_nonce'][$context] ?? [];
    if ($refresh || !is_array($entry) || !preg_match('/^[a-f0-9]{48}$/D', (string) ($entry['nonce'] ?? ''))
        || ($entry['user_id'] ?? null) !== $actor || (int) ($entry['issued_at'] ?? 0) < time() - 600
        || (int) ($entry['issued_at'] ?? 0) > time() + 60) {
        $entry = ['nonce' => bin2hex(random_bytes(24)), 'user_id' => $actor, 'issued_at' => time()];
        $_SESSION['submission_face_nonce'][$context] = $entry;
    }
    return $entry['nonce'];
}

function vnzSubmissionFaceCheckNonce(string $context, ?int $userId, string $nonce): void
{
    $actor = vnzSubmissionFaceActor($context);
    $entry = $_SESSION['submission_face_nonce'][$context] ?? [];
    if ($actor !== $userId || !is_array($entry) || ($entry['user_id'] ?? null) !== $actor
        || !preg_match('/^[a-f0-9]{48}$/D', $nonce) || !is_string($entry['nonce'] ?? null)
        || !hash_equals($entry['nonce'], $nonce) || (int) ($entry['issued_at'] ?? 0) < time() - 600
        || (int) ($entry['issued_at'] ?? 0) > time() + 60) {
        throw new DomainException('Your selfie step expired. Open the camera and take a new photo.');
    }
}

function vnzSubmissionFaceForget(string $context, string $nonce): void
{
    $current = $_SESSION['submission_face_nonce'][$context]['nonce'] ?? null;
    if (is_string($current) && hash_equals($current, $nonce)) {
        unset($_SESSION['submission_face_nonce'][$context]);
    }
}

function vnzSubmissionFaceReady(PDO $pdo, ?array $config = null): void
{
    $config ??= vnzIdentityConfig();
    vnzIdentityReadyBase($pdo);
    if (($config['enabled'] ?? false) !== true || !function_exists('proc_open') || !extension_loaded('gd')) {
        throw new RuntimeException('The selfie step is unavailable. Please try again later.');
    }
    $pdo->query('SELECT submission_id,file_name,photo_hash,nonce_hash,context,user_id,policy_version,detector_version,consent_at,created_at,terminal_at,purge_after,purged_at FROM submission_security_faces LIMIT 1');
    $node = realpath((string) ($config['face_submission']['node_binary'] ?? $config['dual']['node_binary'] ?? 'C:/Program Files/nodejs/node.exe'));
    $approvedNode = realpath('C:/Program Files/nodejs/node.exe');
    if (!$node || !$approvedNode || strcasecmp($node, $approvedNode) !== 0 || !is_file(dirname(__DIR__) . '/tools/identity_match_worker.cjs')) {
        throw new RuntimeException('The local face check is unavailable. Please try again later.');
    }
    $vendor = dirname(__DIR__) . '/assets/vendor/face-api/';
    $manifest = json_decode((string) @file_get_contents($vendor . 'manifest.json'), true);
    if (!is_array($manifest) || ($manifest['package'] ?? '') !== 'face-api.js' || ($manifest['version'] ?? '') !== '0.22.2'
        || ($manifest['license'] ?? '') !== 'MIT' || count($manifest['files'] ?? []) !== 9) {
        throw new RuntimeException('The local face model is unavailable. Please try again later.');
    }
    foreach ($manifest['files'] as $name => $asset) {
        if (!preg_match('~^(?:models/)?[a-zA-Z0-9_.-]+$~D', $name) || !is_file($vendor . $name)
            || filesize($vendor . $name) !== ($asset['bytes'] ?? null)
            || !hash_equals((string) ($asset['sha256'] ?? ''), (string) hash_file('sha256', $vendor . $name))) {
            throw new RuntimeException('The local face model integrity check failed. Please try again later.');
        }
    }
}

function vnzSubmissionFaceImage(array $file): string
{
    if (($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK || !is_string($file['tmp_name'] ?? null) || !is_file($file['tmp_name'])) {
        throw new DomainException('Take a camera selfie before submitting your item.');
    }
    $size = filesize($file['tmp_name']);
    if (!$size || $size > 5 * 1024 * 1024) {
        throw new DomainException('Your selfie must be smaller than 5 MB. Please take another photo.');
    }
    $info = @getimagesize($file['tmp_name']);
    $mime = (new finfo(FILEINFO_MIME_TYPE))->file($file['tmp_name']);
    if (!$info || !in_array($mime, ['image/jpeg', 'image/png', 'image/webp'], true) || $mime !== ($info['mime'] ?? '')
        || min($info[0], $info[1]) < 128 || max($info[0], $info[1]) > 6000 || $info[0] * $info[1] > 16000000) {
        throw new DomainException('Use a clear camera photo, 128 to 6000 pixels per side.');
    }
    $image = @imagecreatefromstring((string) file_get_contents($file['tmp_name']));
    if (!$image) { throw new DomainException('The selfie could not be read. Please take another photo.'); }
    try {
        // Smaller canonical photo strips metadata/trailing bytes and bounds private storage/CPU.
        $ratio = min(1, 1280 / max(imagesx($image), imagesy($image)));
        $scaled = imagecreatetruecolor((int) round(imagesx($image) * $ratio), (int) round(imagesy($image) * $ratio));
        try {
            imagefill($scaled, 0, 0, imagecolorallocate($scaled, 255, 255, 255));
            imagecopyresampled($scaled, $image, 0, 0, 0, 0, imagesx($scaled), imagesy($scaled), imagesx($image), imagesy($image));
            ob_start();
            imagejpeg($scaled, null, 90);
            $jpeg = ob_get_clean();
        } finally { imagedestroy($scaled); }
    } finally { imagedestroy($image); }
    if (!is_string($jpeg) || $jpeg === '' || strlen($jpeg) > 5 * 1024 * 1024) {
        throw new RuntimeException('Could not prepare the private selfie. Please try again.');
    }
    return $jpeg;
}

function vnzSubmissionFacePresence(string $jpeg, array $config): array
{
    require_once __DIR__ . '/identity_face_matcher.php';
    $deadline = microtime(true) + min(30.0, max(0.05, (float) ($config['face_submission']['local_timeout_seconds'] ?? 30)));
    $lock = null;
    try {
        $root = vnzIdentityRoot();
        $lock = @fopen($root . '/local-match.lock', 'c');
        if (!$lock || !flock($lock, LOCK_EX | LOCK_NB)) {
            return ['outcome' => 'unavailable', 'code' => 'worker_busy'];
        }
        $image = vnzIdentityLocalImage($jpeg);
        try { $tensor = vnzIdentityLocalTensor($image); }
        finally { imagedestroy($image); }
        $result = vnzIdentityLocalRun(['action' => 'presence', 'images' => [$tensor]], $config, $deadline);
        if ($result['outcome'] === 'pass'
            && ($result['code'] !== 'face_present' || ($result['face_counts'] ?? null) !== [1])) {
            throw new RuntimeException('worker_protocol');
        }
        return ['outcome' => $result['outcome'], 'code' => $result['code']];
    } catch (DomainException $error) {
        return ['outcome' => 'retry', 'code' => 'image_quality'];
    } catch (Throwable $error) {
        return ['outcome' => 'unavailable', 'code' => 'worker_unavailable'];
    } finally {
        if (is_resource($lock)) { flock($lock, LOCK_UN); fclose($lock); }
    }
}

function vnzSubmissionFacePrepare(PDO $pdo, string $context, ?int $userId, array $file, string $nonce, bool $consent): array
{
    if (!$consent) {
        throw new DomainException('Please agree to the private selfie notice before submitting.');
    }
    if ($pdo->inTransaction()) {
        throw new RuntimeException('Run the face check before opening the submission transaction.');
    }
    vnzSubmissionFaceCheckNonce($context, $userId, $nonce);
    vnzSubmissionFaceReady($pdo);
    if ($context === 'account' && (int) vnzIdentityUser($pdo)['id'] !== $userId) {
        throw new DomainException('Please log in to your active seller account again.');
    }
    $nonceHash = hash('sha256', $nonce);
    if (vnzIdentityFetch($pdo, 'SELECT submission_id FROM submission_security_faces WHERE nonce_hash=?', [$nonceHash])) {
        throw new DomainException('This selfie was already submitted. Take a new photo for another submission.');
    }
    $jpeg = vnzSubmissionFaceImage($file);
    $recent = array_values(array_filter((array) ($_SESSION['submission_face_rate'] ?? []), fn($when) => is_int($when) && $when > time() - 3600));
    if (count($recent) >= 20) {
        throw new DomainException('Too many selfie checks. Please try again later.');
    }
    $recent[] = time();
    $_SESSION['submission_face_rate'] = $recent;
    $checked = vnzSubmissionFacePresence($jpeg, vnzIdentityConfig());
    if ($checked['outcome'] !== 'pass') {
        if ($checked['outcome'] === 'unavailable') {
            throw new RuntimeException('The local face check is busy or unavailable. Your item draft is still here; please try again.');
        }
        $messages = [
            'no_face' => 'No clear face was found. Face the camera and take another photo.',
            'multiple_faces' => 'Keep only your own face in the frame and take another photo.',
            'image_quality' => 'Move into good lighting and keep your full face clearly in the frame.',
        ];
        throw new DomainException($messages[$checked['code']] ?? 'Please take another clear selfie.');
    }
    return ['jpeg' => $jpeg, 'photo_hash' => hash('sha256', $jpeg), 'nonce' => $nonce, 'nonce_hash' => $nonceHash,
        'context' => $context, 'user_id' => $userId, 'created_at' => vnzIdentityNow(),
        'policy_version' => vnzSubmissionFacePolicy(), 'detector_version' => 'face-api.js-0.22.2', 'consent_version' => 'face_submission_v6'];
}

function vnzSubmissionFaceStore(PDO $pdo, int $submissionId, array $prepared): string
{
    require_once __DIR__ . '/identity_face_matcher.php';
    if (!$pdo->inTransaction() || $submissionId < 1) {
        throw new RuntimeException('A private selfie must be linked inside the submission transaction.');
    }
    vnzSubmissionFaceCheckNonce((string) ($prepared['context'] ?? ''), $prepared['user_id'] ?? null, (string) ($prepared['nonce'] ?? ''));
    $jpeg = $prepared['jpeg'] ?? '';
    if (!is_string($jpeg) || ($prepared['policy_version'] ?? '') !== vnzSubmissionFacePolicy()
        || ($prepared['detector_version'] ?? '') !== 'face-api.js-0.22.2' || ($prepared['consent_version'] ?? '') !== 'face_submission_v6'
        || !hash_equals((string) ($prepared['photo_hash'] ?? ''), hash('sha256', $jpeg))
        || !hash_equals((string) ($prepared['nonce_hash'] ?? ''), hash('sha256', $prepared['nonce']))
        || !is_string($prepared['created_at'] ?? null) || !preg_match('/^\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2}$/D', $prepared['created_at'])
        || $prepared['created_at'] < vnzIdentityAfter(-600) || $prepared['created_at'] > vnzIdentityAfter(60)) {
        throw new RuntimeException('The checked selfie changed or expired. Please take another photo.');
    }
    $image = vnzIdentityLocalImage($jpeg);
    imagedestroy($image);
    $submission = vnzIdentityFetch($pdo, 'SELECT id,user_id FROM submissions WHERE id=?' . vnzIdentityLock($pdo), [$submissionId]);
    if (!$submission || ($submission['user_id'] === null ? null : (int) $submission['user_id']) !== $prepared['user_id']) {
        throw new RuntimeException('The selfie does not belong to this submission.');
    }
    if ($prepared['context'] === 'account' && !vnzIdentityFetch($pdo, 'SELECT id FROM users WHERE id=? AND is_active=1' . vnzIdentityLock($pdo), [$prepared['user_id']])) {
        throw new DomainException('Please log in to your active seller account again.');
    }
    $name = bin2hex(random_bytes(24)) . '.enc';
    $path = vnzIdentityEvidencePath($name);
    $stream = null;
    $created = false;
    try {
        $blob = vnzIdentityEncrypt($jpeg, vnzIdentityKey());
        $stream = @fopen($path, 'xb');
        $created = is_resource($stream);
        if (!$stream || !flock($stream, LOCK_EX) || fwrite($stream, $blob) !== strlen($blob) || !fflush($stream)) {
            throw new RuntimeException('Could not save the private selfie. Please try again.');
        }
        flock($stream, LOCK_UN);
        fclose($stream);
        $stream = null;
        $pdo->prepare('INSERT INTO submission_security_faces (submission_id,file_name,photo_hash,nonce_hash,context,user_id,policy_version,detector_version,consent_at,created_at) VALUES (?,?,?,?,?,?,?,?,?,?)')
            ->execute([$submissionId, $name, $prepared['photo_hash'], $prepared['nonce_hash'], $prepared['context'], $prepared['user_id'],
                vnzSubmissionFacePolicy(), 'face-api.js-0.22.2', $prepared['created_at'], vnzIdentityNow()]);
        return $name;
    } catch (Throwable $error) {
        if (is_resource($stream)) { fclose($stream); }
        if ($created && is_file($path)) { @unlink($path); }
        if ($error instanceof PDOException) {
            throw new RuntimeException('This selfie step was already used or could not be saved. Take a new photo and try again.', 0, $error);
        }
        throw $error;
    }
}

function vnzSubmissionFaceRemove(string $name): void
{
    $path = vnzIdentityEvidencePath($name);
    if (is_file($path) && !unlink($path)) {
        throw new RuntimeException('Could not remove the uncommitted private selfie.');
    }
}

function vnzSubmissionFaceMarkTerminal(PDO $pdo, int $submissionId, string $status): void
{
    if (!in_array($status, ['accepted', 'declined'], true)) { return; }
    $now = vnzIdentityNow();
    $pdo->prepare('UPDATE submission_security_faces SET terminal_at=?,purge_after=? WHERE submission_id=? AND terminal_at IS NULL')
        ->execute([$now, vnzIdentityAfter(30 * 86400), $submissionId]);
}

/** Caller must authorize active-admin/view_submissions before calling this or Photo. */
function vnzSubmissionFaceMeta(PDO $pdo, int $submissionId): ?array
{
    $row = vnzIdentityFetch($pdo, 'SELECT f.*,s.status AS submission_status,s.updated_at AS submission_updated_at FROM submission_security_faces f INNER JOIN submissions s ON s.id=f.submission_id WHERE f.submission_id=?', [$submissionId]);
    if (!$row) { return null; }
    if (!$row['terminal_at'] && in_array($row['submission_status'], ['accepted', 'declined'], true)) {
        // Recovery for a terminal transition made by a legacy caller without the hook.
        // The local database trigger latches the real transition timestamp in the status transaction.
        $when = (string) $row['submission_updated_at'];
        if (!preg_match('/^\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2}$/D', $when) || $when < $row['created_at'] || $when > vnzIdentityNow()) { $when = vnzIdentityNow(); }
        $after = gmdate('Y-m-d H:i:s', (int) strtotime($when . ' UTC') + 30 * 86400);
        $pdo->prepare('UPDATE submission_security_faces SET terminal_at=?,purge_after=? WHERE submission_id=? AND terminal_at IS NULL')->execute([$when, $after, $submissionId]);
        $row = vnzIdentityFetch($pdo, 'SELECT * FROM submission_security_faces WHERE submission_id=?', [$submissionId]);
    }
    $state = $row['purged_at'] ? 'purged' : (!empty($row['purge_after']) && $row['purge_after'] <= vnzIdentityNow() ? 'expired' : 'available');
    if ($state === 'available' && !is_file(vnzIdentityEvidencePath($row['file_name']))) { $state = 'unavailable'; }
    $row['available'] = $state === 'available';
    $row['state'] = $state;
    unset($row['submission_status'], $row['submission_updated_at']);
    return $row;
}

/** Returns only the uncached endpoint's already-authorized submission photo. */
function vnzSubmissionFacePhoto(PDO $pdo, int $submissionId): string
{
    $row = vnzSubmissionFaceMeta($pdo, $submissionId);
    if (!$row || !$row['available']) {
        throw new DomainException('This submission photo is unavailable or has expired.');
    }
    $blob = @file_get_contents(vnzIdentityEvidencePath($row['file_name']));
    if (!is_string($blob) || strlen($blob) > 5 * 1024 * 1024 + 32) { throw new RuntimeException('The private photo is unavailable.'); }
    $jpeg = vnzIdentityDecrypt($blob, vnzIdentityKey());
    if (!hash_equals($row['photo_hash'], hash('sha256', $jpeg))) { throw new RuntimeException('The private photo integrity check failed.'); }
    return $jpeg;
}

function vnzSubmissionFaceCleanup(PDO $pdo): array
{
    $latched = 0;
    $rows = $pdo->query("SELECT f.submission_id FROM submission_security_faces f INNER JOIN submissions s ON s.id=f.submission_id WHERE f.terminal_at IS NULL AND s.status IN ('accepted','declined') LIMIT 500")->fetchAll(PDO::FETCH_ASSOC);
    foreach ($rows as $row) { vnzSubmissionFaceMeta($pdo, (int) $row['submission_id']); $latched++; }
    $due = $pdo->prepare('SELECT submission_id,file_name FROM submission_security_faces WHERE purged_at IS NULL AND purge_after IS NOT NULL AND purge_after<=? LIMIT 500');
    $due->execute([vnzIdentityNow()]);
    $purged = 0;
    foreach ($due->fetchAll(PDO::FETCH_ASSOC) as $row) {
        vnzSubmissionFaceRemove($row['file_name']);
        $pdo->prepare('UPDATE submission_security_faces SET purged_at=? WHERE submission_id=? AND purged_at IS NULL')->execute([vnzIdentityNow(), $row['submission_id']]);
        $purged++;
    }
    return ['face_terminal_synced' => $latched, 'face_purged' => $purged];
}
