<?php
/* Presentation only: use the existing submission/latest-offer state, never an ETA. */
function vnzSellerSubmissionNextStep(array $submission): string
{
    $status = strtolower(trim((string) ($submission['status'] ?? '')));
    $offer = strtolower(trim((string) ($submission['latest_offer_status'] ?? '')));
    $sender = strtolower(trim((string) ($submission['latest_offer_sender'] ?? '')));
    if (in_array($status, ['offer_pending', 'offer_sent'], true)) {
        if (in_array($offer, ['sent', 'offer_sent', 'pending', 'active'], true)) {
            if ($sender === 'admin') return 'Open Offers to review the offer and send your response.';
            if ($sender === 'seller') return 'Your counter-offer is with the team. Check Offers for their response.';
        }
        return 'Check Offers and submission details for the latest update.';
    }
    return match ($status) {
        'new_submission' => 'Your item is in the review queue. Check here for updates.',
        'under_review' => 'The team is reviewing your item. Check Offers for any response.',
        'accepted' => 'Open Transactions for the next steps and transaction details.',
        'declined' => 'Open submission details to read the team feedback.',
        'cancelled' => 'This submission is cancelled. Open details to review its history.',
        'completed' => 'This submission is complete. Open Transactions for its history.',
        default => 'Open submission details for the latest status.'
    };
}
