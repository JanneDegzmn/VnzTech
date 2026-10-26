<?php

function grantGuestOfferResponseAccess(
    array &$session,
    int $submissionId,
    ?int $sellerId
): void {
    if ($submissionId <= 0) {
        return;
    }

    $access = $session['guest_offer_response_access'] ?? [];
    if (!is_array($access)) {
        $access = [];
    }

    $access[(string) $submissionId] = [
        'seller_id' => $sellerId !== null && $sellerId > 0
            ? $sellerId
            : null,
        'granted_at' => time()
    ];

    while (count($access) > 100) {
        array_shift($access);
    }

    $session['guest_offer_response_access'] = $access;
}

function offerResponseSessionIsAuthorized(
    array $submission,
    array $session
): bool {
    if (!empty($session['admin_id'])) {
        return false;
    }

    if ($submission['user_id'] !== null) {
        $sellerId = (int) ($session['user_id'] ?? 0);
        return ($session['user_logged_in'] ?? false) === true
            && $sellerId > 0
            && $sellerId === (int) $submission['user_id'];
    }

    $sellerId = (int) ($session['user_id'] ?? 0);
    $sessionSellerId = ($session['user_logged_in'] ?? false) === true
        && $sellerId > 0
        ? $sellerId
        : null;
    $access = $session['guest_offer_response_access'] ?? null;
    $grant = is_array($access)
        ? ($access[(string) (int) ($submission['id'] ?? 0)] ?? null)
        : null;

    return is_array($grant)
        && array_key_exists('seller_id', $grant)
        && $grant['seller_id'] === $sessionSellerId;
}

function offerResponseOfferBelongsToSubmission(
    ?array $offer,
    int $offerId,
    int $submissionId
): bool {
    return $offer !== null
        && (int) ($offer['offer_id'] ?? 0) === $offerId
        && (int) ($offer['submission_id'] ?? 0) === $submissionId;
}

function offerResponseOfferIsAvailable(array $offer): bool
{
    return strtolower((string) ($offer['sender_type'] ?? '')) === 'admin'
        && in_array(
            strtolower((string) ($offer['status'] ?? '')),
            ['sent', 'offer_sent', 'pending', 'active'],
            true
        );
}
