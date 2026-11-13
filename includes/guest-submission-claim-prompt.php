<?php if (!isset($guestSubmissionOnboarding) || $guestSubmissionOnboarding === null) { return; } ?>
<section class="guest-claim"
    data-guest-claim
    data-phase="<?= htmlspecialchars($guestSubmissionOnboarding['phase'], ENT_QUOTES, 'UTF-8') ?>"
    data-auto-open="<?= $guestSubmissionOnboarding['auto_show'] ? '1' : '0' ?>"
    data-guest-flow="<?= ($guestSubmissionOnboarding['guest_flow'] ?? true) ? '1' : '0' ?>"
    data-verification-result="<?= htmlspecialchars($guestSubmissionOnboarding['verification_result'] ?? '', ENT_QUOTES, 'UTF-8') ?>"
    data-feedback-id="<?= htmlspecialchars($guestSubmissionOnboarding['feedback_id'] ?? '', ENT_QUOTES, 'UTF-8') ?>"
    data-last-sent="<?= (int) $guestSubmissionOnboarding['last_sent_at'] ?>"
    data-csrf="<?= htmlspecialchars(generateCsrfToken(), ENT_QUOTES, 'UTF-8') ?>">
    <aside class="guest-claim-banner" aria-label="Guest submission">
        <span class="guest-claim-symbol" aria-hidden="true">↗</span>
        <div>
            <strong data-guest-claim-banner-title>Bring your guest submission with you</strong>
            <p data-guest-claim-banner-copy>Verify your email to link eligible guest submissions to your account.</p>
        </div>
        <button type="button" data-guest-claim-open>Continue</button>
    </aside>

    <dialog class="guest-claim-dialog" aria-labelledby="guestClaimTitle" aria-describedby="guestClaimDescription" data-guest-claim-dialog>
        <div class="guest-claim-panel">
            <button type="button" class="guest-claim-close" aria-label="Close" data-guest-claim-close>×</button>
            <span class="guest-claim-eyebrow" data-guest-claim-eyebrow>ONE ACCOUNT, ALL YOUR UPDATES</span>
            <span class="guest-claim-hero" aria-hidden="true">
                <svg viewBox="0 0 32 32" fill="none"><path d="M9 4h10l5 5v19H9V4Z" stroke="currentColor" stroke-width="1.8" stroke-linejoin="round"/><path d="M19 4v6h5M13 15h7M13 19h4" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"/><circle cx="24" cy="24" r="7" fill="var(--panel)" stroke="currentColor" stroke-width="1.8"/><path d="m21 24 2 2 4-4" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"/></svg>
            </span>
            <h2 id="guestClaimTitle" data-guest-claim-title>Claim your guest submission</h2>
            <p id="guestClaimDescription" class="guest-claim-description" data-guest-claim-copy>Submitted before signing up? Verify the same email you used as a guest so we can securely link eligible submissions to this account.</p>
            <p class="guest-claim-email"><?= htmlspecialchars($guestSubmissionOnboarding['email'], ENT_QUOTES, 'UTF-8') ?></p>
            <ol class="guest-claim-steps" aria-label="Claim steps" data-guest-claim-steps>
                <li data-guest-claim-step="1"><span>1</span><span data-guest-claim-step-label="1">Confirm</span></li>
                <li data-guest-claim-step="2"><span>2</span><span data-guest-claim-step-label="2">Verify email</span></li>
                <li data-guest-claim-step="3"><span>3</span><span data-guest-claim-step-label="3">Linked</span></li>
            </ol>
            <p class="guest-claim-feedback" role="status" aria-live="polite" data-guest-claim-feedback></p>
            <div class="guest-claim-actions">
                <button type="button" class="guest-claim-secondary" data-guest-claim-later>Maybe later</button>
                <button type="button" class="guest-claim-primary" data-guest-claim-primary>Yes, claim it</button>
            </div>
            <button type="button" class="guest-claim-resend" data-guest-claim-resend hidden>Resend verification email</button>
        </div>
    </dialog>
</section>
