<?php

require_once __DIR__ . '/email_verification_feedback.php';

/** Presentation state only. This marker never authorizes a submission claim. */
function vnzRememberGuestSubmissionOnboarding(
    array &$session,
    int $sellerId,
    string $email
): void {
    $prefill = $session['guest_registration_prefill'] ?? null;
    if (
        $sellerId <= 0
        || !is_array($prefill)
        || !is_string($prefill['email'] ?? null)
        || !filter_var($email, FILTER_VALIDATE_EMAIL)
        || strcasecmp(trim($prefill['email']), trim($email)) !== 0
    ) {
        return;
    }

    $submissionId = filter_var($prefill['submission_id'] ?? null, FILTER_VALIDATE_INT);
    $session['guest_submission_onboarding'] = [
        'seller_id' => $sellerId,
        'email' => strtolower(trim($email)),
        'submission_id' => $submissionId > 0 ? $submissionId : null,
        'auto_show' => true,
        'last_sent_at' => 0
    ];
}

function vnzGuestSubmissionOnboardingForSeller(array $session, array $seller): ?array
{
    $state = $session['guest_submission_onboarding'] ?? null;
    $guestFlow = is_array($state)
        && (int) ($seller['id'] ?? 0) > 0
        && (int) ($state['seller_id'] ?? 0) === (int) $seller['id']
        && (int) ($seller['is_active'] ?? 0) === 1
        && is_string($state['email'] ?? null)
        && strcasecmp($state['email'], trim((string) ($seller['email'] ?? ''))) === 0;
    $verificationFeedback = vnzEmailVerificationFeedbackForSeller($session, $seller);
    if (!$guestFlow && $verificationFeedback === null) {
        return null;
    }
    if (!$guestFlow) $state = [];

    $verified = !empty($seller['email_verified_at']);
    return [
        'phase' => $verified ? 'verified' : 'verify',
        'auto_show' => ($state['auto_show'] ?? false) === true
            || ($guestFlow && $verified && empty($state['verified_notice_seen']))
            || $verificationFeedback !== null,
        'email' => (string) $seller['email'],
        'last_sent_at' => max(0, (int) ($state['last_sent_at'] ?? 0)),
        'submission_id' => $state['submission_id'] ?? null,
        'guest_flow' => $guestFlow,
        'verification_result' => $verificationFeedback['result'] ?? null,
        'feedback_id' => $verificationFeedback['id'] ?? ''
    ];
}

function vnzGuestSubmissionOnboardingStatus(
    PDO $pdo,
    array $session,
    array $seller
): ?array {
    $state = vnzGuestSubmissionOnboardingForSeller($session, $seller);
    if ($state === null || $state['phase'] === 'verify' || $state['submission_id'] === null) {
        return $state;
    }

    // Check actual ownership, including verification completed in another tab/device.
    $owned = $pdo->prepare('SELECT COUNT(*) FROM submissions WHERE id = ? AND user_id = ?');
    $owned->execute([(int) $state['submission_id'], (int) $seller['id']]);
    $state['phase'] = (int) $owned->fetchColumn() > 0 ? 'linked' : 'verified';
    return $state;
}

function vnzRecordGuestSubmissionVerificationSent(array &$session, int $sellerId): void
{
    if ((int) ($session['guest_submission_onboarding']['seller_id'] ?? 0) === $sellerId) {
        $session['guest_submission_onboarding']['last_sent_at'] = time();
    }
}
