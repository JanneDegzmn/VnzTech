<?php

/** Only verification handlers create these seller-bound presentation events. */
function vnzRememberEmailVerificationFeedback(array &$session, int $sellerId, string $result): void
{
    if ($sellerId <= 0 || !in_array($result, ['success', 'invalid'], true)) {
        return;
    }
    $session['email_verification_feedback'] = [
        'seller_id' => $sellerId,
        'result' => $result,
        'id' => bin2hex(random_bytes(16))
    ];
}

function vnzEmailVerificationFeedbackForSeller(array $session, array $seller): ?array
{
    $feedback = $session['email_verification_feedback'] ?? null;
    if (
        !is_array($feedback)
        || (int) ($seller['id'] ?? 0) <= 0
        || (int) ($seller['is_active'] ?? 0) !== 1
        || (int) ($feedback['seller_id'] ?? 0) !== (int) $seller['id']
        || !in_array($feedback['result'] ?? null, ['success', 'invalid'], true)
        || !is_string($feedback['id'] ?? null)
        || ($feedback['result'] === 'success' && empty($seller['email_verified_at']))
    ) {
        return null;
    }
    return $feedback;
}

function vnzDismissEmailVerificationFeedback(array &$session, int $sellerId, string $feedbackId): void
{
    $feedback = $session['email_verification_feedback'] ?? null;
    if (
        is_array($feedback)
        && (int) ($feedback['seller_id'] ?? 0) === $sellerId
        && $feedbackId !== ''
        && hash_equals((string) ($feedback['id'] ?? ''), $feedbackId)
    ) {
        unset($session['email_verification_feedback']);
    }
}
