<?php
/** Trusted dual-engine policy. No client score or manual decision grants this policy. */

function vnzIdentityMode(): string
{
    $config = vnzIdentityConfig();
    $mode = $config['mode'] ?? 'manual_v2';
    if (!in_array($mode, ['manual_v2', 'auto_dual_v4', 'face_submission_v6'], true)
        || (!getenv('VNZ_IDENTITY_CONFIG_PATH') && is_file(__DIR__ . '/identity_dual_required.flag') && !in_array($mode, ['auto_dual_v4', 'face_submission_v6'], true))
        || (!getenv('VNZ_IDENTITY_CONFIG_PATH') && is_file(__DIR__ . '/submission_face_required.flag') && $mode !== 'face_submission_v6')) {
        throw new RuntimeException('The required identity policy is unavailable.');
    }
    return $mode;
}

function vnzIdentityAutomatic(): bool
{
    return vnzIdentityMode() === 'auto_dual_v4';
}

function vnzSubmissionFaceMode(): bool
{
    return vnzIdentityMode() === 'face_submission_v6';
}

function vnzIdentityPolicy(): string
{
    return 'dual-v4-portrait-v1';
}

function vnzIdentityDualReady(PDO $pdo, ?array $config = null, bool $activation = false): void
{
    $config ??= vnzIdentityConfig();
    vnzIdentityReadyBase($pdo);
    if (($config['enabled'] ?? false) !== true || ($config['mode'] ?? '') !== 'auto_dual_v4') {
        throw new RuntimeException('Dual face matching is not configured.');
    }
    require_once __DIR__ . '/identity_face_matcher.php';
    require_once __DIR__ . '/identity_faceplusplus.php';
    if (!vnzIdentityFacePlusPlusConfigReady($config) || ($config['dual']['validation_policy'] ?? '') !== vnzIdentityPolicy()
        || !function_exists('proc_open') || !function_exists('imagecreatefromstring')
        || !extension_loaded('curl') || !class_exists('CURLStringFile')) {
        throw new RuntimeException('Dual face matching setup is incomplete.');
    }
    $binary = realpath((string) ($config['dual']['node_binary'] ?? ''));
    if (!$binary || !is_file($binary) || vnzIdentityPublicPath($binary) || !preg_match('/^node(?:\.exe)?$/iD', basename($binary))) {
        throw new RuntimeException('The local face matcher is unavailable.');
    }
    $pdo->query('SELECT attempt_id,policy_version,evidence_hash,consent_version,crop_hash,crop_policy,local_engine,provider_engine,local_pass,provider_pass,local_distance,local_threshold,provider_confidence,provider_threshold,processing_nonce_hash,lease_expires_at,outcome_code,completed_at FROM identity_auto_decisions LIMIT 1');
    $vendor = __DIR__ . '/../assets/vendor/face-api/';
    $manifest = json_decode((string) @file_get_contents($vendor . 'manifest.json'), true);
    if (!is_array($manifest) || ($manifest['version'] ?? '') !== '0.22.2' || count($manifest['files'] ?? []) !== 9) {
        throw new RuntimeException('Pinned local face models are unavailable.');
    }
    foreach ($manifest['files'] as $path => $asset) {
        if (!preg_match('~^(?:models/)?[a-zA-Z0-9_.-]+$~D', $path) || !is_file($vendor . $path)
            || !hash_equals((string) ($asset['sha256'] ?? ''), (string) hash_file('sha256', $vendor . $path))) {
            throw new RuntimeException('Pinned local face model integrity failed.');
        }
    }
}

function vnzIdentityAutoDecision(PDO $pdo, int $attemptId): ?array
{
    return vnzIdentityFetch($pdo, 'SELECT * FROM identity_auto_decisions WHERE attempt_id=?', [$attemptId]);
}

function vnzIdentityAutoProvenance(PDO $pdo, array $attempt): bool
{
    $d = vnzIdentityAutoDecision($pdo, (int) $attempt['id']);
    return $d !== null && $d['policy_version'] === vnzIdentityPolicy()
        && hash_equals((string) $attempt['evidence_hash'], (string) $d['evidence_hash'])
        && $d['consent_version'] === 'auto_dual_v4' && $d['crop_policy'] === 'portrait-v1'
        && preg_match('/^[a-f0-9]{64}$/D', (string) $d['crop_hash'])
        && $d['local_engine'] === 'face-api.js-0.22.2' && $d['provider_engine'] === 'faceplusplus-v3'
        && (int) $d['local_pass'] === 1 && (int) $d['provider_pass'] === 1
        && is_numeric($d['local_distance']) && is_finite((float) $d['local_distance'])
        && (float) $d['local_distance'] >= 0 && (float) $d['local_distance'] <= 0.45
        && is_numeric($d['local_threshold']) && (float) $d['local_threshold'] === 0.45
        && is_numeric($d['provider_confidence']) && is_numeric($d['provider_threshold'])
        && is_finite((float) $d['provider_confidence']) && is_finite((float) $d['provider_threshold'])
        && (float) $d['provider_confidence'] <= 100 && (float) $d['provider_threshold'] > 0
        && (float) $d['provider_threshold'] < 100 && (float) $d['provider_confidence'] > (float) $d['provider_threshold']
        && !empty($d['completed_at']) && $d['completed_at'] <= vnzIdentityNow() && $d['outcome_code'] === 'both_pass';
}

function vnzIdentityPolicyStatus(PDO $pdo, array $attempt): string
{
    $status = vnzIdentityEffectiveStatus($attempt);
    if (!vnzIdentityAutomatic()) {
        return $status;
    }
    $decision = vnzIdentityAutoDecision($pdo, (int) $attempt['id']);
    if (!$decision || $decision['policy_version'] !== vnzIdentityPolicy()
        || !hash_equals((string) $attempt['evidence_hash'], (string) $decision['evidence_hash'])) {
        return 'needs_verification';
    }
    if ($status === 'approved' && !vnzIdentityAutoProvenance($pdo, $attempt)) {
        return 'needs_verification';
    }
    if ($status === 'processing' && (empty($decision['lease_expires_at']) || $decision['lease_expires_at'] <= vnzIdentityNow())) {
        return 'retry';
    }
    return $status;
}

function vnzIdentityAutoMessage(string $status, string $code): string
{
    return match ($status) {
        'approved' => 'Your ID portrait and selfie passed both face checks. You can continue.',
        'processing' => 'Checking your ID portrait and selfie. Please wait.',
        'retry' => $code === 'face_mismatch' || $code === 'provider_no_match'
            ? 'The face checks did not both pass. Take a clear selfie and upload your own ID to try again.'
            : 'We could not get a clear face match. Use a clear ID photo and retake your selfie.',
        'unavailable' => 'Face checking is temporarily unavailable. Please try again later; your identity has not been approved.',
        'needs_verification' => 'Complete the new automatic ID and selfie check before your next submission.',
        'expired' => 'This verification expired. Please upload your ID and retake your selfie.',
        default => '',
    };
}

/** Strictly normalize trusted adapter results before any database approval. */
function vnzIdentityDualResult(array $local, ?array $cloud): array
{
    $base = ['status' => 'unavailable', 'code' => 'matcher_unavailable', 'local' => $local, 'cloud' => $cloud];
    if (($local['outcome'] ?? '') !== 'pass') {
        $base['status'] = ($local['outcome'] ?? '') === 'retry' ? 'retry' : 'unavailable';
        $base['code'] = in_array($local['code'] ?? '', ['face_mismatch', 'not_matching'], true) ? 'face_mismatch' : ($base['status'] === 'retry' ? 'capture_quality' : 'matcher_unavailable');
        return $base;
    }
    if (($local['engine'] ?? '') !== 'face-api.js-0.22.2' || ($local['crop_policy'] ?? '') !== 'portrait-v1'
        || !is_numeric($local['distance'] ?? null) || !is_finite((float) $local['distance'])
        || (float) $local['distance'] < 0 || (float) $local['distance'] > 0.45 || (float) ($local['threshold'] ?? 0) !== 0.45
        || empty($local['id_crop']) || empty($local['selfie_crop'])
        || !hash_equals(hash('sha256', $local['id_crop'] . "\0" . $local['selfie_crop']), (string) ($local['crop_hash'] ?? ''))) {
        return $base;
    }
    if (($cloud['outcome'] ?? '') === 'retry') {
        $base['status'] = 'retry';
        $base['code'] = 'provider_no_match';
        return $base;
    }
    if (($cloud['outcome'] ?? '') !== 'pass' || ($cloud['engine'] ?? '') !== 'faceplusplus-v3'
        || ($cloud['threshold_key'] ?? '') !== '1e-5' || !is_numeric($cloud['confidence'] ?? null) || !is_numeric($cloud['threshold'] ?? null)
        || !is_finite((float) $cloud['confidence']) || !is_finite((float) $cloud['threshold'])
        || (float) $cloud['threshold'] <= 0 || (float) $cloud['threshold'] >= 100 || (float) $cloud['confidence'] > 100
        || (float) $cloud['confidence'] <= (float) $cloud['threshold']) {
        $base['code'] = 'provider_unavailable';
        return $base;
    }
    return array_merge($base, ['status' => 'approved', 'code' => 'both_pass']);
}

/** Finalization is independently testable; it accepts server adapter results, never POST data. */
function vnzIdentityAutoFinalize(PDO $pdo, int $attemptId, string $lease, string $evidenceHash, array $local, ?array $cloud): void
{
    vnzIdentityReady($pdo);
    if (!vnzIdentityAutomatic() || $pdo->inTransaction()) {
        throw new RuntimeException('Automatic face decision is unavailable.');
    }
    $result = vnzIdentityDualResult($local, $cloud);
    $pdo->beginTransaction();
    try {
        $attempt = vnzIdentityFetch($pdo, 'SELECT * FROM identity_attempts WHERE id=?' . vnzIdentityLock($pdo), [$attemptId]);
        $d = vnzIdentityFetch($pdo, 'SELECT * FROM identity_auto_decisions WHERE attempt_id=?' . vnzIdentityLock($pdo), [$attemptId]);
        if (!$attempt || !$d || $attempt['status'] !== 'processing' || !empty($attempt['purged_at'])
            || $d['policy_version'] !== vnzIdentityPolicy() || $d['consent_version'] !== 'auto_dual_v4'
            || !hash_equals((string) $d['processing_nonce_hash'], hash('sha256', $lease))
            || empty($d['lease_expires_at']) || $d['lease_expires_at'] <= vnzIdentityNow()
            || empty($attempt['expires_at']) || $attempt['expires_at'] <= vnzIdentityNow()
            || !hash_equals((string) $attempt['evidence_hash'], $evidenceHash) || !hash_equals((string) $d['evidence_hash'], $evidenceHash)) {
            throw new DomainException('This face check changed or expired. Refresh and try again.');
        }
        $bound = vnzIdentityFetch($pdo, 'SELECT user_id FROM identity_bindings WHERE attempt_id=?', [$attemptId]);
        $ownerId = $bound['user_id'] ?? $attempt['user_id'];
        if ($ownerId) {
            if (!$bound || !vnzIdentityFetch($pdo, 'SELECT id FROM users WHERE id=? AND is_active=1', [$ownerId])) {
                throw new DomainException('This face check no longer belongs to an active seller.');
            }
        } else {
            $latest = vnzIdentityFetch($pdo, 'SELECT id FROM identity_attempts WHERE draft_id=? ORDER BY id DESC LIMIT 1', [$attempt['draft_id']]);
            if (!$latest || (int) $latest['id'] !== $attemptId) {
                throw new DomainException('A newer face check replaced this request.');
            }
        }
        // Originals are immutable; authenticate and bind the actual stored pair, not just filenames.
        $originals = [];
        foreach (['id_file', 'selfie_file'] as $field) {
            $path = vnzIdentityEvidencePath($attempt[$field]);
            if (!is_file($path) || filesize($path) > 6 * 1024 * 1024) {
                throw new RuntimeException('Private face evidence integrity failed.');
            }
            $originals[] = vnzIdentityDecrypt((string) file_get_contents($path), vnzIdentityKey());
        }
        if (!hash_equals($evidenceHash, hash('sha256', $originals[0] . "\0" . $originals[1]))) {
            throw new RuntimeException('Private face evidence integrity failed.');
        }
        unset($originals);
        $pass = $result['status'] === 'approved';
        $localPass = ($local['outcome'] ?? '') === 'pass' && $result['code'] !== 'matcher_unavailable';
        $now = vnzIdentityNow();
        $pdo->prepare('UPDATE identity_auto_decisions SET crop_hash=?,crop_policy=?,local_engine=?,provider_engine=?,local_pass=?,provider_pass=?,local_distance=?,local_threshold=?,provider_confidence=?,provider_threshold=?,outcome_code=?,completed_at=?,lease_expires_at=NULL WHERE attempt_id=?')
            ->execute([$localPass ? $local['crop_hash'] : null, $localPass ? 'portrait-v1' : null, 'face-api.js-0.22.2', 'faceplusplus-v3', $localPass ? 1 : 0, $pass ? 1 : 0,
                $localPass ? (float) $local['distance'] : null, 0.45, $pass ? (float) $cloud['confidence'] : null, $pass ? (float) $cloud['threshold'] : null, $result['code'], $now, $attemptId]);
        $pdo->prepare('UPDATE identity_attempts SET status=?,updated_at=?,expires_at=?,grant_expires_at=?,purge_after=?,review_reason=NULL,reviewed_by=NULL WHERE id=? AND status=?')
            ->execute([$result['status'], $now, $pass && $ownerId ? null : vnzIdentityAfter(7 * 86400), $pass ? vnzIdentityAfter(86400) : null, vnzIdentityAfter(7 * 86400), $attemptId, 'processing']);
        vnzIdentityAudit($pdo, $attemptId, 'auto_' . $result['status'], null, $ownerId ? (int) $ownerId : null);
        $pdo->commit();
    } catch (Throwable $error) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        throw $error;
    }
}

function vnzIdentityRunDual(PDO $pdo, int $attemptId, string $lease, string $hash, string $idJpeg, string $selfieJpeg): void
{
    $config = vnzIdentityConfig();
    require_once __DIR__ . '/identity_face_matcher.php';
    require_once __DIR__ . '/identity_faceplusplus.php';
    $local = vnzIdentityLocalMatch($idJpeg, $selfieJpeg, $config);
    $cloud = null;
    // Validate local output before disclosing either crop to the provider.
    $validated = vnzIdentityDualResult($local, null);
    if (($local['outcome'] ?? '') === 'pass' && $validated['code'] === 'provider_unavailable') {
        $cloud = vnzIdentityFacePlusPlusMatch($local['id_crop'], $local['selfie_crop'], $config);
    }
    vnzIdentityAutoFinalize($pdo, $attemptId, $lease, $hash, $local, $cloud);
}
