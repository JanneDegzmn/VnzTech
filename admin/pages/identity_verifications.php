<?php
require_once __DIR__ . '/../../backend/database.php';
require_once __DIR__ . '/../../backend/session.php';
require_once __DIR__ . '/../../backend/auth.php';
require_once __DIR__ . '/../../backend/csrf.php';
require_once __DIR__ . '/../../backend/identity_verification.php';

requireAdminLogin();
header('Cache-Control: no-store, private');
$identityEscape = static fn($value): string => htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
if (vnzSubmissionFaceMode()) {
    http_response_code(410);
    ?>
    <section class="vnz-identity" data-identity-area="admin" data-identity-retired aria-labelledby="identity-retired-title">
        <p class="identity-eyebrow">Submission photos</p>
        <h2 id="identity-retired-title">ID verification is closed</h2>
        <p class="identity-status" role="status">Open a submission to see its private submission selfie in Seller Information. There is no separate ID review or approval step.</p>
        <p class="identity-notice">A detected face is not proof of identity. Historical submissions are preserved.</p>
    </section>
    <?php
    return;
}
if (vnzIdentityAutomatic()) {
    http_response_code(410);
    ?>
    <section class="vnz-identity" data-identity-area="admin" data-identity-retired aria-labelledby="identity-retired-title">
        <p class="identity-eyebrow">Automatic face matching</p>
        <h2 id="identity-retired-title">Manual ID review is closed</h2>
        <div class="identity-status" role="status">New submissions use the local matcher and Face++ together. Both must pass; an unavailable check requests a retry. Admins cannot approve an attempt or view automatic ID evidence here.</div>
        <p class="identity-notice">Face matching checks similarity. It does not authenticate an ID document or establish certified liveness. Historical submissions and minimal audit records are preserved.</p>
    </section>
    <?php
    return;
}
if (!vnzIdentityReviewer($pdo)) {
    http_response_code(403);
    ?>
    <section class="vnz-identity" data-identity-area="admin"><h2>Identity review is restricted</h2><p>Your role needs the dedicated identity-review permission to view ID evidence.</p></section>
    <?php
    exit;
}
$identityQueue = [];
$identityQueueError = '';
try {
    if (!vnzIdentityEnabled()) {
        $identityQueueError = 'Identity verification is not enabled yet. Finish setup before accepting ID and selfie uploads.';
    } else {
        $identityQueue = vnzIdentityQueue($pdo);
    }
} catch (Throwable $error) {
    $identityQueueError = 'Identity review is unavailable. Check the private storage and migration setup before trying again.';
}
?>
<section class="vnz-identity" data-identity-area="admin" data-identity-admin data-csrf="<?= $identityEscape(generateCsrfToken()) ?>" aria-labelledby="identity-queue-title">
    <header class="identity-review-header">
        <div>
            <p class="identity-eyebrow">Private seller verification</p>
            <h2 id="identity-queue-title">ID and selfie reviews</h2>
            <p>Review the original ID and selfie, then record your decision.</p>
        </div>
        <button type="button" class="identity-button" data-identity-queue-refresh>Refresh queue</button>
    </header>
    <p class="identity-notice">Face comparison assists your review. It does not authenticate a document or prove liveness. Check the person's face and the ID details yourself. Your decision applies only to this exact evidence version. Do not download or share these private photos.</p>
    <?php if ($identityQueueError !== ''): ?>
        <div class="identity-status" data-tone="error" role="status"><?= $identityEscape($identityQueueError) ?></div>
    <?php elseif (!$identityQueue): ?>
        <div class="identity-status" role="status">No identity attempts to review yet.</div>
    <?php else: ?>
        <p><small>Recent attempts are listed below. Expired or deleted evidence cannot be approved.</small></p>
        <div class="identity-review-list">
            <?php foreach ($identityQueue as $identityAttempt):
                $identityAttemptId = (string) $identityAttempt['id'];
                $identityStatus = (string) $identityAttempt['status'];
                $identityHasEvidence = !empty($identityAttempt['evidence_available']);
                $identityPending = $identityStatus === 'pending_review' && $identityHasEvidence;
                $identityCanRevoke = $identityStatus === 'approved';
                $identityActionable = $identityPending || $identityCanRevoke;
                ?>
                <details class="identity-review-card" data-identity-review
                         data-attempt-id="<?= $identityEscape($identityAttemptId) ?>"
                         data-evidence-hash="<?= $identityEscape($identityAttempt['evidence_hash'] ?? '') ?>">
                    <summary>
                        <span><strong><?= $identityEscape($identityAttempt['email'] ?? 'No email') ?></strong><small><?= ($identityAttempt['context'] ?? '') === 'account' ? 'Seller account' : 'Guest submission' ?> · Review #<?= $identityEscape($identityAttemptId) ?></small></span>
                        <span class="identity-badge" data-identity-review-badge data-status="<?= $identityEscape($identityStatus) ?>"><?= $identityEscape(str_replace('_', ' ', $identityStatus)) ?></span>
                    </summary>
                    <div class="identity-review-body">
                        <div class="identity-review-facts">
                            <span>Sent (UTC): <?= $identityEscape($identityAttempt['created_at'] ?? '') ?></span>
                            <?php if (!empty($identityAttempt['expires_at'])): ?><span>Attempt expiry (UTC): <?= $identityEscape($identityAttempt['expires_at']) ?></span><?php endif; ?>
                            <?php if (!empty($identityAttempt['reviewed_at'])): ?><span>Reviewed (UTC): <?= $identityEscape($identityAttempt['reviewed_at']) ?></span><?php endif; ?>
                        </div>
                        <?php if (!empty($identityAttempt['review_reason'] ?? $identityAttempt['reason'] ?? '')): ?>
                            <p class="identity-notice">Review note: <?= $identityEscape($identityAttempt['review_reason'] ?? $identityAttempt['reason']) ?></p>
                        <?php endif; ?>
                        <?php if ($identityHasEvidence): ?>
                            <div class="identity-evidence-grid">
                                <div class="identity-field"><span class="identity-label">Original ID photo</span><div class="identity-preview"><img data-identity-evidence="id" alt="Private ID evidence for review <?= $identityEscape($identityAttemptId) ?>"></div></div>
                                <div class="identity-field"><span class="identity-label">Original camera selfie</span><div class="identity-preview"><img data-identity-evidence="selfie" alt="Private selfie evidence for review <?= $identityEscape($identityAttemptId) ?>"></div></div>
                            </div>
                            <button type="button" class="identity-button" data-identity-review-compare>Compare these photos again</button>
                        <?php else: ?>
                            <p class="identity-notice">Private evidence is unavailable or has been deleted under the retention policy. This attempt cannot receive a new approval.</p>
                        <?php endif; ?>
                        <div class="identity-status" data-identity-review-message role="status" aria-live="polite"><?= $identityHasEvidence ? 'Open this review to load the private originals and compare their faces.' : 'Minimal status and audit history remain; no private photos are displayed.' ?></div>
                        <?php if ($identityActionable): ?>
                            <div class="identity-field">
                                <label for="identity-reason-<?= $identityEscape($identityAttemptId) ?>">Review note <small>Required for rejection or revocation. Do not copy ID numbers.</small></label>
                                <textarea id="identity-reason-<?= $identityEscape($identityAttemptId) ?>" rows="3" maxlength="500" data-identity-review-reason></textarea>
                            </div>
                            <label class="identity-consent" for="identity-confirm-<?= $identityEscape($identityAttemptId) ?>">
                                <input id="identity-confirm-<?= $identityEscape($identityAttemptId) ?>" type="checkbox" data-identity-review-confirm>
                                <span>I personally reviewed this attempt and confirm the decision below. Face similarity alone is not proof of identity.</span>
                            </label>
                            <div class="identity-actions">
                                <?php if ($identityPending): ?>
                                    <button type="button" class="identity-button identity-primary" data-identity-decision="approve" disabled>Approve identity</button>
                                    <button type="button" class="identity-button identity-danger" data-identity-decision="reject" disabled>Reject and request retry</button>
                                <?php endif; ?>
                                <?php if ($identityCanRevoke): ?>
                                    <button type="button" class="identity-button identity-danger" data-identity-decision="revoke" disabled>Revoke approval</button>
                                <?php endif; ?>
                            </div>
                        <?php endif; ?>
                    </div>
                </details>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</section>
<script>window.VNZIdentity?.init(document);</script>
