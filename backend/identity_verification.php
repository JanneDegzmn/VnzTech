<?php
/** Identity evidence is private. Similarity scores are never authorization. */
require_once __DIR__ . '/identity_automation.php';
require_once __DIR__ . '/submission_security_face.php';

function vnzIdentityConfig(): array
{
    $override = getenv('VNZ_IDENTITY_CONFIG_PATH');
    $path = $override ?: 'C:/xampp/private/VNZ-TECH/identity/config.php';
    if (!is_file($path)) {
        if (!$override && is_file(__DIR__ . '/identity_required.flag')) {
            throw new RuntimeException('Required identity verification configuration is unavailable.');
        }
        return ['enabled' => false];
    }
    $config = require $path;
    if (!is_array($config)) {
        throw new RuntimeException('Identity verification configuration is unavailable.');
    }
    return $config;
}

function vnzIdentityEnabled(): bool
{
    try {
        if (vnzSubmissionFaceMode()) { return false; } // Legacy ID gate only; v6 has its own mandatory gate.
        return vnzIdentityAutomatic() || !empty(vnzIdentityConfig()['enabled']);
    } catch (Throwable $error) {
        // Keep pages renderable with the mandatory gate active; Ready returns the safe error.
        return true;
    }
}

function vnzIdentityNow(): string
{
    return gmdate('Y-m-d H:i:s');
}

function vnzIdentityAfter(int $seconds): string
{
    return gmdate('Y-m-d H:i:s', time() + $seconds);
}

function vnzIdentityRoot(): string
{
    $config = vnzIdentityConfig();
    $root = realpath((string) ($config['storage_path'] ?? ''));
    if (!$root || vnzIdentityPublicPath($root)) {
        throw new RuntimeException('Private identity storage is unavailable.');
    }
    if (!is_dir($root . '/evidence') || !is_writable($root . '/evidence')) {
        throw new RuntimeException('Private identity storage is unavailable.');
    }
    return $root;
}

function vnzIdentityKey(): string
{
    $path = (string) (vnzIdentityConfig()['key_path'] ?? '');
    $resolved = realpath($path);
    if (!$resolved || vnzIdentityPublicPath($resolved)) {
        throw new RuntimeException('Identity encryption is unavailable.');
    }
    $key = file_get_contents($resolved);
    if (!is_string($key) || strlen($key) !== 32) {
        throw new RuntimeException('Identity encryption is unavailable.');
    }
    return $key;
}

function vnzIdentityPublicPath(string $path): bool
{
    $path = strtolower(str_replace('\\', '/', $path));
    // CLI lacks DOCUMENT_ROOT; the repository's parent is the XAMPP webroot.
    foreach (array_filter([realpath((string) ($_SERVER['DOCUMENT_ROOT'] ?? '')), realpath(dirname(__DIR__, 2))]) as $publicRoot) {
        $publicRoot = strtolower(str_replace('\\', '/', $publicRoot));
        if ($path === $publicRoot || str_starts_with($path . '/', $publicRoot . '/')) {
            return true;
        }
    }
    return false;
}

function vnzIdentityReady(PDO $pdo): void
{
    if (vnzSubmissionFaceMode()) {
        vnzSubmissionFaceReady($pdo);
    } elseif (vnzIdentityAutomatic()) {
        vnzIdentityDualReady($pdo);
    } else {
        vnzIdentityReadyBase($pdo);
    }
}

function vnzIdentityReadyBase(PDO $pdo): void
{
    vnzIdentityKey();
    $heartbeat = vnzIdentityRoot() . '/cleanup-heartbeat.json';
    $data = is_file($heartbeat) ? json_decode((string) file_get_contents($heartbeat), true) : null;
    if (!is_array($data) || empty($data['success_at']) || time() - (int) $data['success_at'] > 172800 || (int) $data['success_at'] > time() + 60) {
        throw new RuntimeException('Identity verification maintenance is unavailable. Please contact support.');
    }
    foreach (['identity_attempts', 'identity_bindings', 'identity_audit'] as $table) {
        $pdo->query('SELECT 1 FROM ' . $table . ' LIMIT 1');
    }
}

function vnzIdentityLock(PDO $pdo): string
{
    return $pdo->getAttribute(PDO::ATTR_DRIVER_NAME) === 'mysql' ? ' FOR UPDATE' : '';
}

function vnzIdentityFetch(PDO $pdo, string $sql, array $params = []): ?array
{
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    return $row ?: null;
}

function vnzIdentityAudit(PDO $pdo, int $attempt, string $event, ?int $admin = null, ?int $user = null): void
{
    $pdo->prepare('INSERT INTO identity_audit (attempt_id,event,admin_id,user_id,created_at) VALUES (?,?,?,?,?)')
        ->execute([$attempt, $event, $admin, $user, vnzIdentityNow()]);
}

function vnzIdentityReviewer(PDO $pdo): bool
{
    if (vnzSubmissionFaceMode() || vnzIdentityAutomatic()) {
        return false;
    }
    $admin = (int) ($_SESSION['admin_id'] ?? 0);
    if ($admin < 1) {
        return false;
    }
    $row = vnzIdentityFetch($pdo, 'SELECT a.id FROM admins a INNER JOIN roles r ON r.id=a.role_id AND r.is_active=1 INNER JOIN role_permissions rp ON rp.role_id=a.role_id INNER JOIN permissions p ON p.id=rp.permission_id WHERE a.id=? AND a.is_active=1 AND p.name=? LIMIT 1', [$admin, 'identity_review']);
    return $row !== null;
}

function vnzIdentityUser(PDO $pdo): array
{
    if (empty($_SESSION['user_logged_in']) || (int) ($_SESSION['user_id'] ?? 0) < 1) {
        throw new DomainException('Please log in to your seller account.');
    }
    $row = vnzIdentityFetch($pdo, 'SELECT id,email,email_verified_at,is_active FROM users WHERE id=? AND is_active=1', [(int) $_SESSION['user_id']]);
    if (!$row) {
        throw new DomainException('Please log in to an active seller account.');
    }
    return $row;
}

function vnzIdentityGuest(): array
{
    if (!isset($_SESSION['identity_guest_current']) || !is_array($_SESSION['identity_guest_current'])) {
        $_SESSION['identity_guest_current'] = ['draft_id' => bin2hex(random_bytes(24)), 'capability' => bin2hex(random_bytes(32))];
    }
    return $_SESSION['identity_guest_current'];
}

function vnzIdentityGuestOwns(array $attempt): bool
{
    foreach (['identity_guest_current', 'identity_guest_last'] as $key) {
        $guest = $_SESSION[$key] ?? [];
        if (!empty($guest['capability']) && !empty($guest['draft_id']) && hash_equals((string) $attempt['draft_id'], (string) $guest['draft_id']) && hash_equals((string) $attempt['capability_hash'], hash('sha256', (string) $guest['capability']))) {
            return true;
        }
    }
    return false;
}

function vnzIdentityEffectiveStatus(array $attempt): string
{
    $status = (string) $attempt['status'];
    if (in_array($status, ['pending_review', 'processing', 'approved', 'retry', 'unavailable'], true) && !empty($attempt['expires_at']) && $attempt['expires_at'] <= vnzIdentityNow()) {
        return 'expired';
    }
    return $status;
}

function vnzIdentityCarryoverEligible(array $attempt, ?PDO $pdo = null): bool
{
    $status = $pdo ? vnzIdentityPolicyStatus($pdo, $attempt) : vnzIdentityEffectiveStatus($attempt);
    if (vnzIdentityAutomatic() && !$pdo) { return false; }
    return in_array($status, ['pending_review', 'processing'], true) || ($status === 'approved' && !empty($attempt['grant_expires_at']) && $attempt['grant_expires_at'] > vnzIdentityNow());
}

function vnzIdentityStatus(PDO $pdo, string $context): array
{
    if (!in_array($context, ['guest', 'account'], true)) {
        throw new DomainException('Invalid verification context.');
    }
    $base = ['success' => true, 'enabled' => vnzIdentityEnabled(), 'available' => true, 'mode' => vnzIdentityMode(), 'policy_version' => vnzIdentityAutomatic() ? vnzIdentityPolicy() : 'manual-v2', 'outcome_code' => '', 'status' => 'not_started', 'approved' => false, 'attempt_id' => null, 'draft_id' => null, 'email' => '', 'message' => '', 'expires_at' => null];
    if (!$base['enabled']) {
        $base['status'] = 'disabled';
        return $base;
    }
    vnzIdentityReady($pdo);
    if ($context === 'account') {
        $user = vnzIdentityUser($pdo);
        $base['email'] = $user['email'];
        $attempt = vnzIdentityFetch($pdo, 'SELECT a.* FROM identity_attempts a INNER JOIN identity_bindings b ON b.attempt_id=a.id WHERE b.user_id=?', [(int) $user['id']]);
    } else {
        $guest = vnzIdentityGuest();
        $base['draft_id'] = $guest['draft_id'];
        $attempt = vnzIdentityFetch($pdo, 'SELECT * FROM identity_attempts WHERE draft_id=? ORDER BY id DESC LIMIT 1', [$guest['draft_id']]);
        if ($attempt && !vnzIdentityGuestOwns($attempt)) {
            $attempt = null;
        }
        if ($attempt && !empty($attempt['submission_id'])) {
            $_SESSION['identity_guest_last'] = $guest;
            unset($_SESSION['identity_guest_current']);
            $base['draft_id'] = vnzIdentityGuest()['draft_id'];
            $attempt = null;
        }
    }
    if ($attempt) {
        $status = vnzIdentityPolicyStatus($pdo, $attempt);
        if ($context === 'guest' && $status === 'approved' && (empty($attempt['grant_expires_at']) || $attempt['grant_expires_at'] <= vnzIdentityNow())) {
            $status = 'expired';
        }
        $base = array_merge($base, ['attempt_id' => (int) $attempt['id'], 'email' => $attempt['email'], 'status' => $status, 'approved' => $status === 'approved', 'expires_at' => $context === 'guest' && $status === 'approved' ? $attempt['grant_expires_at'] : $attempt['expires_at'], 'message' => (string) ($attempt['review_reason'] ?? '')]);
        if (vnzIdentityAutomatic()) {
            $d = vnzIdentityAutoDecision($pdo, (int) $attempt['id']);
            $base['outcome_code'] = (string) ($d['outcome_code'] ?? '');
            $base['message'] = vnzIdentityAutoMessage($status, $base['outcome_code']);
        }
    }
    return $base;
}

function vnzIdentityValidateImage(array $file): array
{
    if (($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK || empty($file['tmp_name']) || !is_file($file['tmp_name'])) {
        throw new DomainException('Please upload both your ID image and camera selfie.');
    }
    $size = filesize($file['tmp_name']);
    if (!$size || $size > 5 * 1024 * 1024) {
        throw new DomainException('Each identity image must be smaller than 5 MB.');
    }
    $info = @getimagesize($file['tmp_name']);
    $mime = (new finfo(FILEINFO_MIME_TYPE))->file($file['tmp_name']);
    if (!$info || !in_array($mime, ['image/jpeg', 'image/png', 'image/webp'], true) || $mime !== ($info['mime'] ?? '') || $info[0] < 128 || $info[1] < 128 || $info[0] > 6000 || $info[1] > 6000 || $info[0] * $info[1] > 16000000) {
        throw new DomainException('Use a clear JPG, PNG, or WEBP image, 128–6000 pixels per side.');
    }
    $bytes = file_get_contents($file['tmp_name']);
    $decoded = @imagecreatefromstring($bytes);
    if (!$decoded) {
        throw new DomainException('The identity image could not be read.');
    }
    // Re-encode to strip uploaded metadata and trailing content.
    ob_start();
    imagejpeg($decoded, null, 90);
    $clean = ob_get_clean();
    imagedestroy($decoded);
    if (!is_string($clean) || $clean === '' || strlen($clean) > 5 * 1024 * 1024) {
        throw new RuntimeException('Could not prepare the private image.');
    }
    return ['bytes' => $clean, 'mime' => 'image/jpeg'];
}

function vnzIdentityEncrypt(string $plain, string $key): string
{
    $iv = random_bytes(12);
    $cipher = openssl_encrypt($plain, 'aes-256-gcm', $key, OPENSSL_RAW_DATA, $iv, $tag, 'VNZ-ID-v1');
    if ($cipher === false) {
        throw new RuntimeException('Could not encrypt identity evidence.');
    }
    return 'VNZ1' . $iv . $tag . $cipher;
}

function vnzIdentityDecrypt(string $blob, string $key): string
{
    if (strlen($blob) < 33 || substr($blob, 0, 4) !== 'VNZ1') {
        throw new RuntimeException('Private evidence is unavailable.');
    }
    $plain = openssl_decrypt(substr($blob, 32), 'aes-256-gcm', $key, OPENSSL_RAW_DATA, substr($blob, 4, 12), substr($blob, 16, 16), 'VNZ-ID-v1');
    if ($plain === false) {
        throw new RuntimeException('Private evidence integrity check failed.');
    }
    return $plain;
}

function vnzIdentityEvidencePath(string $name): string
{
    if (!preg_match('/^[a-f0-9]{48}\.enc$/D', $name)) {
        throw new RuntimeException('Invalid private evidence reference.');
    }
    return vnzIdentityRoot() . '/evidence/' . $name;
}

function vnzIdentitySubmit(PDO $pdo, string $context, string $email, array $id, array $selfie, bool $consent, string $consentVersion = ''): array
{
    if (!vnzIdentityEnabled()) {
        throw new DomainException('Identity verification is not available yet.');
    }
    vnzIdentityReady($pdo);
    $automatic = vnzIdentityAutomatic();
    if ($automatic && ($consentVersion !== 'auto_dual_v4' || $pdo->inTransaction())) {
        throw new DomainException('Please accept the new local and Face++ processing notice before checking your face.');
    }
    if (!$consent || !in_array($context, ['account', 'guest'], true)) {
        throw new DomainException('Please confirm consent before sending your identity images.');
    }
    $user = $context === 'account' ? vnzIdentityUser($pdo) : null;
    $email = strtolower(trim($user ? $user['email'] : $email));
    if (!filter_var($email, FILTER_VALIDATE_EMAIL) || strlen($email) > 190) {
        throw new DomainException('Enter the email you will use for this submission and seller account.');
    }
    $status = vnzIdentityStatus($pdo, $context);
    if (in_array($status['status'], ['pending_review', 'processing', 'approved'], true)) {
        throw new DomainException('Your current verification is already pending or approved.');
    }
    $guest = $context === 'guest' ? vnzIdentityGuest() : null;
    $draft = $guest ? $guest['draft_id'] : bin2hex(random_bytes(24));
    $capHash = $guest ? hash('sha256', $guest['capability']) : null;
    // Per-actor DB count survives new tabs and account session refreshes.
    $stmt = $pdo->prepare('SELECT COUNT(*) FROM identity_attempts WHERE created_at>? AND ' . ($user ? 'user_id=?' : 'capability_hash=?'));
    $stmt->execute([vnzIdentityAfter(-3600), $user ? $user['id'] : $capHash]);
    if ((int) $stmt->fetchColumn() >= 5) {
        throw new DomainException('Too many verification attempts. Please try again in an hour.');
    }
    $rateHash = hash_hmac('sha256', (string) ($_SERVER['REMOTE_ADDR'] ?? 'cli'), vnzIdentityKey());
    $stmt = $pdo->prepare('SELECT COUNT(*) FROM identity_attempts WHERE created_at>? AND rate_limit_hash=?');
    $stmt->execute([vnzIdentityAfter(-3600), $rateHash]);
    if ((int) $stmt->fetchColumn() >= 20) {
        throw new DomainException('Too many identity requests from this connection. Please try again later.');
    }
    $images = [vnzIdentityValidateImage($id), vnzIdentityValidateImage($selfie)];
    $hash = hash('sha256', $images[0]['bytes'] . "\0" . $images[1]['bytes']);
    $names = [];
    $started = !$pdo->inTransaction();
    try {
        foreach ($images as $image) {
            $name = bin2hex(random_bytes(24)) . '.enc';
            $names[] = $name;
            if (file_put_contents(vnzIdentityEvidencePath($name), vnzIdentityEncrypt($image['bytes'], vnzIdentityKey()), LOCK_EX) === false) {
                throw new RuntimeException('Could not save private identity images.');
            }
        }
        if ($started) {
            $pdo->beginTransaction();
        }
        if ($user) {
            vnzIdentityFetch($pdo, 'SELECT id FROM users WHERE id=?' . vnzIdentityLock($pdo), [$user['id']]);
            $current = vnzIdentityFetch($pdo, 'SELECT a.* FROM identity_attempts a INNER JOIN identity_bindings b ON b.attempt_id=a.id WHERE b.user_id=?', [$user['id']]);
            if ($current && in_array(vnzIdentityPolicyStatus($pdo, $current), ['pending_review', 'processing', 'approved'], true)) {
                throw new DomainException('Your current verification is already pending or approved.');
            }
        }
        $stmt = $pdo->prepare('INSERT INTO identity_attempts (context,user_id,draft_id,capability_hash,email,status,id_file,selfie_file,evidence_hash,consent_at,created_at,updated_at,expires_at,rate_limit_hash) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?)');
        $now = vnzIdentityNow();
        $stmt->execute([$context, $user['id'] ?? null, $draft, $capHash, $email, $automatic ? 'processing' : 'pending_review', $names[0], $names[1], $hash, $now, $now, $now, vnzIdentityAfter(($automatic ? 1 : 7) * 86400), $rateHash]);
        $attemptId = (int) $pdo->lastInsertId();
        $lease = bin2hex(random_bytes(32));
        if ($automatic) {
            $pdo->prepare('INSERT INTO identity_auto_decisions (attempt_id,policy_version,evidence_hash,consent_version,processing_nonce_hash,lease_expires_at,outcome_code) VALUES (?,?,?,?,?,?,?)')
                ->execute([$attemptId, vnzIdentityPolicy(), $hash, 'auto_dual_v4', hash('sha256', $lease), vnzIdentityAfter(90), 'processing']);
            $pdo->prepare('UPDATE identity_attempts SET purge_after=? WHERE id=?')->execute([vnzIdentityAfter(86400), $attemptId]);
        }
        if ($user) {
            $pdo->prepare('DELETE FROM identity_bindings WHERE user_id=?')->execute([$user['id']]);
            $pdo->prepare('INSERT INTO identity_bindings (user_id,attempt_id,created_at) VALUES (?,?,?)')->execute([$user['id'], $attemptId, $now]);
        }
        vnzIdentityAudit($pdo, $attemptId, 'submitted', null, $user['id'] ?? null);
        if ($started) {
            $pdo->commit();
        }
    } catch (Throwable $e) {
        if ($started && $pdo->inTransaction()) {
            $pdo->rollBack();
        }
        foreach ($names as $name) {
            $path = vnzIdentityEvidencePath($name);
            if (is_file($path)) {
                unlink($path);
            }
        }
        throw $e;
    }
    if ($automatic) {
        // Commit the lease before inference; release PHP's session lock for status polling.
        if (session_status() === PHP_SESSION_ACTIVE) { session_write_close(); }
        @set_time_limit(75);
        vnzIdentityRunDual($pdo, $attemptId, $lease, $hash, $images[0]['bytes'], $images[1]['bytes']);
    }
    return vnzIdentityStatus($pdo, $context);
}

function vnzIdentityEvidence(PDO $pdo, int $attemptId, string $kind): string
{
    if (!vnzIdentityEnabled() || !in_array($kind, ['id', 'selfie'], true)) {
        throw new DomainException('Private evidence is unavailable.');
    }
    vnzIdentityReady($pdo);
    $attempt = vnzIdentityFetch($pdo, 'SELECT * FROM identity_attempts WHERE id=?', [$attemptId]);
    $userId = !empty($_SESSION['user_logged_in']) ? (int) ($_SESSION['user_id'] ?? 0) : 0;
    $activeOwner = $attempt && $userId > 0 && (int) $attempt['user_id'] === $userId && vnzIdentityFetch($pdo, 'SELECT id FROM users WHERE id=? AND is_active=1', [$userId]);
    $owner = $attempt && ($activeOwner || vnzIdentityGuestOwns($attempt));
    if (!$attempt || (!$owner && !vnzIdentityReviewer($pdo)) || !empty($attempt['purged_at']) || (!empty($attempt['purge_after']) && $attempt['purge_after'] <= vnzIdentityNow()) || vnzIdentityEffectiveStatus($attempt) === 'expired') {
        throw new DomainException('Private evidence is unavailable or access was denied.');
    }
    $path = vnzIdentityEvidencePath((string) $attempt[$kind === 'id' ? 'id_file' : 'selfie_file']);
    if (!is_file($path)) {
        throw new DomainException('Private evidence has expired. Please verify again if needed.');
    }
    return vnzIdentityDecrypt((string) file_get_contents($path), vnzIdentityKey());
}

function vnzIdentityQueue(PDO $pdo): array
{
    if (vnzIdentityAutomatic() || !vnzIdentityEnabled() || !vnzIdentityReviewer($pdo)) {
        return [];
    }
    vnzIdentityReady($pdo);
    $rows = $pdo->query('SELECT id,context,user_id,email,status,evidence_hash,created_at,expires_at,reviewed_at,review_reason,purged_at,purge_after,id_file,selfie_file FROM identity_attempts ORDER BY id DESC LIMIT 100')->fetchAll(PDO::FETCH_ASSOC);
    foreach ($rows as &$row) {
        $row['status'] = vnzIdentityEffectiveStatus($row);
        $row['evidence_available'] = empty($row['purged_at']) && $row['status'] !== 'expired' && (empty($row['purge_after']) || $row['purge_after'] > vnzIdentityNow()) && is_file(vnzIdentityEvidencePath($row['id_file'])) && is_file(vnzIdentityEvidencePath($row['selfie_file']));
        unset($row['id_file'], $row['selfie_file']);
    }
    return $rows;
}

function vnzIdentityReview(PDO $pdo, int $attemptId, string $hash, string $decision, string $reason, bool $confirmed): void
{
    if (vnzIdentityAutomatic()) { throw new DomainException('Manual identity decisions are unavailable under automatic matching.'); }
    vnzIdentityReady($pdo);
    if (!vnzIdentityEnabled() || !vnzIdentityReviewer($pdo) || !$confirmed || !in_array($decision, ['approve', 'reject', 'revoke'], true)) {
        throw new DomainException('An authorized reviewer must confirm this decision.');
    }
    $reason = trim($reason);
    if (strlen($reason) > 500 || ($decision !== 'approve' && $reason === '')) {
        throw new DomainException('Add a short review reason (maximum 500 characters).');
    }
    $pdo->beginTransaction();
    try {
        $attempt = vnzIdentityFetch($pdo, 'SELECT * FROM identity_attempts WHERE id=?' . vnzIdentityLock($pdo), [$attemptId]);
        if (!$attempt || !hash_equals((string) $attempt['evidence_hash'], $hash) || vnzIdentityEffectiveStatus($attempt) === 'expired') {
            throw new DomainException('This evidence version changed or expired. Refresh the queue.');
        }
        if (($decision === 'revoke' && $attempt['status'] !== 'approved') || ($decision !== 'revoke' && $attempt['status'] !== 'pending_review')) {
            throw new DomainException('This request has already been reviewed. Refresh the queue.');
        }
        if ($decision !== 'revoke') {
            // Integrity and retention checked against the exact originals the reviewer sees.
            $id = vnzIdentityEvidence($pdo, $attemptId, 'id');
            $selfie = vnzIdentityEvidence($pdo, $attemptId, 'selfie');
            if (!hash_equals($hash, hash('sha256', $id . "\0" . $selfie))) {
                throw new DomainException('Private evidence integrity check failed.');
            }
        }
        $status = ['approve' => 'approved', 'reject' => 'rejected', 'revoke' => 'revoked'][$decision];
        $now = vnzIdentityNow();
        $bound = vnzIdentityFetch($pdo, 'SELECT user_id FROM identity_bindings WHERE attempt_id=?', [$attemptId]);
        $expires = $status === 'approved' && ($attempt['context'] === 'account' || $bound) ? null : vnzIdentityAfter(7 * 86400);
        $pdo->prepare('UPDATE identity_attempts SET status=?,review_reason=?,reviewed_by=?,reviewed_at=?,updated_at=?,expires_at=?,grant_expires_at=?,purge_after=? WHERE id=?')
            ->execute([$status, $reason, (int) $_SESSION['admin_id'], $now, $now, $expires, $status === 'approved' ? vnzIdentityAfter(86400) : null, vnzIdentityAfter(7 * 86400), $attemptId]);
        vnzIdentityAudit($pdo, $attemptId, $status, (int) $_SESSION['admin_id'], $bound['user_id'] ?? $attempt['user_id']);
        $pdo->commit();
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        throw $e;
    }
}

function vnzIdentityRequireSubmission(PDO $pdo, ?int $userId, string $email, string $draft): ?array
{
    if (!vnzIdentityEnabled()) {
        return null;
    }
    vnzIdentityReady($pdo);
    if ($userId !== null) {
        $row = vnzIdentityFetch($pdo, 'SELECT a.* FROM identity_attempts a INNER JOIN identity_bindings b ON b.attempt_id=a.id INNER JOIN users u ON u.id=b.user_id WHERE b.user_id=? AND u.is_active=1' . vnzIdentityLock($pdo), [$userId]);
    } else {
        $guest = $_SESSION['identity_guest_current'] ?? [];
        if ($draft === '' || !hash_equals((string) ($guest['draft_id'] ?? ''), $draft)) {
            throw new DomainException('Complete identity verification for this submission first.');
        }
        $row = vnzIdentityFetch($pdo, 'SELECT * FROM identity_attempts WHERE draft_id=? ORDER BY id DESC LIMIT 1' . vnzIdentityLock($pdo), [$draft]);
        if (!$row || !vnzIdentityGuestOwns($row) || !empty($row['submission_id']) || strtolower(trim($email)) !== $row['email']) {
            throw new DomainException('Your guest verification expired, was used, or uses another email. Please verify again.');
        }
    }
    if (!$row || vnzIdentityPolicyStatus($pdo, $row) !== 'approved') {
        throw new DomainException(vnzIdentityAutomatic() ? 'Complete both automatic ID and selfie face checks before you submit.' : 'Your ID and selfie need admin approval before you can submit.');
    }
    if ($userId === null && (empty($row['grant_expires_at']) || $row['grant_expires_at'] <= vnzIdentityNow())) {
        throw new DomainException('Your guest verification expired. Please verify again.');
    }
    return $row;
}

function vnzIdentityConsumeSubmission(PDO $pdo, ?int $userId, string $email, string $draft, int $submissionId): void
{
    if (!vnzIdentityEnabled()) {
        return;
    }
    if (!$pdo->inTransaction()) {
        throw new LogicException('Identity grant must be consumed within the submission transaction.');
    }
    $attempt = vnzIdentityRequireSubmission($pdo, $userId, $email, $draft);
    if ($userId === null) {
        $stmt = $pdo->prepare('UPDATE identity_attempts SET submission_id=?,updated_at=? WHERE id=? AND submission_id IS NULL AND status=?');
        $stmt->execute([$submissionId, vnzIdentityNow(), $attempt['id'], 'approved']);
        if ($stmt->rowCount() !== 1) {
            throw new DomainException('This guest verification has already been used.');
        }
    }
    vnzIdentityAudit($pdo, (int) $attempt['id'], 'submission_created', null, $userId);
}

function vnzIdentityReserveGuest(PDO $pdo, int $userId, string $email): void
{
    if (!vnzIdentityEnabled()) {
        return;
    }
    vnzIdentityReady($pdo);
    if (!$pdo->inTransaction()) {
        throw new LogicException('Identity reservation requires the signup transaction.');
    }
    foreach (['identity_guest_current', 'identity_guest_last'] as $key) {
        $guest = $_SESSION[$key] ?? [];
        if (empty($guest['draft_id']) || empty($guest['capability'])) {
            continue;
        }
        $attempt = vnzIdentityFetch($pdo, 'SELECT * FROM identity_attempts WHERE draft_id=? ORDER BY id DESC LIMIT 1' . vnzIdentityLock($pdo), [$guest['draft_id']]);
        if (!$attempt || !vnzIdentityGuestOwns($attempt) || strtolower(trim($email)) !== $attempt['email'] || !vnzIdentityCarryoverEligible($attempt, $pdo) || !empty($attempt['reserved_user_id']) || !empty($attempt['user_id'])) {
            continue;
        }
        $stmt = $pdo->prepare('UPDATE identity_attempts SET reserved_user_id=?,reservation_expires_at=?,updated_at=? WHERE id=? AND reserved_user_id IS NULL AND user_id IS NULL');
        $stmt->execute([$userId, vnzIdentityAfter(7 * 86400), vnzIdentityNow(), $attempt['id']]);
        if ($stmt->rowCount() === 1) {
            vnzIdentityAudit($pdo, (int) $attempt['id'], 'signup_reserved', null, $userId);
        }
        return;
    }
}

function vnzIdentityFinalizeAccount(PDO $pdo, int $userId): void
{
    if (!vnzIdentityEnabled()) {
        return;
    }
    vnzIdentityReady($pdo);
    if (!$pdo->inTransaction()) {
        throw new LogicException('Identity binding requires the email-verification transaction.');
    }
    $user = vnzIdentityFetch($pdo, 'SELECT id,email,email_verified_at,is_active FROM users WHERE id=?', [$userId]);
    if (!$user || !(int) $user['is_active'] || empty($user['email_verified_at'])) {
        return;
    }
    $email = strtolower(trim($user['email']));
    $stmt = $pdo->prepare('SELECT COUNT(*) FROM users WHERE LOWER(email)=? AND is_active=1');
    $stmt->execute([$email]);
    if ((int) $stmt->fetchColumn() !== 1 || vnzIdentityFetch($pdo, 'SELECT user_id FROM identity_bindings WHERE user_id=?', [$userId])) {
        return;
    }
    $attempt = vnzIdentityFetch($pdo, 'SELECT * FROM identity_attempts WHERE reserved_user_id=? AND user_id IS NULL ORDER BY id DESC LIMIT 1' . vnzIdentityLock($pdo), [$userId]);
    if (!$attempt || $attempt['email'] !== $email || empty($attempt['reservation_expires_at']) || $attempt['reservation_expires_at'] <= vnzIdentityNow() || !vnzIdentityCarryoverEligible($attempt, $pdo)) {
        return;
    }
    $pdo->prepare('INSERT INTO identity_bindings (user_id,attempt_id,created_at) VALUES (?,?,?)')->execute([$userId, $attempt['id'], vnzIdentityNow()]);
    $pdo->prepare('UPDATE identity_attempts SET user_id=?,capability_hash=NULL,reservation_expires_at=NULL,expires_at=?,updated_at=? WHERE id=?')
        ->execute([$userId, $attempt['status'] === 'approved' ? null : $attempt['expires_at'], vnzIdentityNow(), $attempt['id']]);
    vnzIdentityAudit($pdo, (int) $attempt['id'], 'account_bound', null, $userId);
}

function vnzIdentityCleanup(PDO $pdo): array
{
    $root = vnzIdentityRoot();
    vnzIdentityKey();
    $now = vnzIdentityNow();
    $faceTable = false;
    try { $pdo->query('SELECT 1 FROM submission_security_faces LIMIT 1'); $faceTable = true; }
    catch (PDOException $error) { if (vnzSubmissionFaceMode()) { throw $error; } }
    $faceCleanup = $faceTable ? vnzSubmissionFaceCleanup($pdo) : ['purged' => 0];
    $expired = $pdo->prepare("UPDATE identity_attempts SET status='expired',grant_expires_at=NULL,purge_after=?,updated_at=? WHERE status IN ('pending_review','processing','retry','unavailable') AND expires_at<=?");
    $expired->execute([$now, $now, $now]);
    $rows = $pdo->prepare('SELECT id,id_file,selfie_file FROM identity_attempts WHERE purged_at IS NULL AND purge_after IS NOT NULL AND purge_after<=?');
    $rows->execute([$now]);
    $purged = 0;
    foreach ($rows->fetchAll(PDO::FETCH_ASSOC) as $row) {
        foreach (['id_file', 'selfie_file'] as $field) {
            $path = vnzIdentityEvidencePath($row[$field]);
            if (is_file($path) && !unlink($path)) {
                throw new RuntimeException('Could not remove expired private evidence.');
            }
        }
        $pdo->prepare('UPDATE identity_attempts SET purged_at=?,updated_at=? WHERE id=? AND purged_at IS NULL')->execute([$now, $now, $row['id']]);
        vnzIdentityAudit($pdo, (int) $row['id'], 'evidence_purged');
        $purged++;
    }
    // Unreferenced encrypted files (failed/crashed upload) never survive beyond 24 hours.
    $orphans = 0;
    foreach (glob($root . '/evidence/*.enc') ?: [] as $path) {
        if (!is_file($path) || filemtime($path) > time() - 86400) {
            continue;
        }
        $name = basename($path);
        vnzIdentityEvidencePath($name); // Reject unexpected filenames; never recurse.
        $exists = vnzIdentityFetch($pdo, 'SELECT id FROM identity_attempts WHERE id_file=? OR selfie_file=? LIMIT 1', [$name, $name]);
        if (!$exists && $faceTable) {
            $exists = vnzIdentityFetch($pdo, 'SELECT submission_id FROM submission_security_faces WHERE file_name=? LIMIT 1', [$name]);
        }
        if (!$exists && unlink($path)) {
            $orphans++;
        }
    }
    if (file_put_contents($root . '/cleanup-heartbeat.json', json_encode(['success_at' => time()]), LOCK_EX) === false) {
        throw new RuntimeException('Could not record identity maintenance status.');
    }
    return ['expired' => $expired->rowCount(), 'purged' => $purged, 'orphaned' => $orphans, 'submission_faces' => $faceCleanup];
}
