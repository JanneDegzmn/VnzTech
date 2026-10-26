<?php

require_once __DIR__ . '/../backend/offer_response_authorization.php';
require_once __DIR__ . '/../backend/csrf.php';

function offerAuthorizationAssert(bool $condition, string $message): void
{
    if (!$condition) {
        throw new RuntimeException($message);
    }
}

$guestSession = [];
grantGuestOfferResponseAccess($guestSession, 101, null);

offerAuthorizationAssert(
    offerResponseSessionIsAuthorized(
        ['id' => 101, 'user_id' => null],
        $guestSession
    ),
    'The guest session that created the submission must be allowed to respond.'
);

offerAuthorizationAssert(
    !offerResponseSessionIsAuthorized(
        ['id' => 101, 'user_id' => null],
        []
    ),
    'Reference and mobile details without the creating guest session must not authorize a response.'
);

offerAuthorizationAssert(
    !offerResponseSessionIsAuthorized(
        ['id' => 102, 'user_id' => null],
        $guestSession
    ),
    'A guest capability must not authorize another submission.'
);

offerAuthorizationAssert(
    !offerResponseSessionIsAuthorized(
        ['id' => 101, 'user_id' => null],
        ['admin_id' => 7] + $guestSession
    ),
    'An Admin session must not use guest offer-response access.'
);

$sellerGuestSession = ['user_logged_in' => true, 'user_id' => 25];
grantGuestOfferResponseAccess($sellerGuestSession, 103, 25);
offerAuthorizationAssert(
    offerResponseSessionIsAuthorized(
        ['id' => 103, 'user_id' => null],
        $sellerGuestSession
    ),
    'A Seller using the public guest-submission flow must retain access in the same session.'
);

offerAuthorizationAssert(
    !offerResponseSessionIsAuthorized(
        ['id' => 103, 'user_id' => null],
        ['user_logged_in' => true, 'user_id' => 26]
            + $sellerGuestSession
    ),
    'A different Seller account must not use the guest submission capability.'
);

$ownerSession = ['user_logged_in' => true, 'user_id' => 25];
offerAuthorizationAssert(
    offerResponseSessionIsAuthorized(
        ['id' => 201, 'user_id' => 25],
        $ownerSession
    ),
    'The authenticated owner must retain access to an account submission.'
);

offerAuthorizationAssert(
    !offerResponseSessionIsAuthorized(
        ['id' => 201, 'user_id' => 25],
        []
    ),
    'Anonymous reference/mobile access must not authorize an account submission.'
);

offerAuthorizationAssert(
    !offerResponseSessionIsAuthorized(
        ['id' => 201, 'user_id' => 25],
        ['user_logged_in' => true, 'user_id' => 26]
    ),
    'A different Seller account must not access an account submission.'
);

$activeOffer = [
    'offer_id' => 501,
    'submission_id' => 101,
    'sender_type' => 'admin',
    'status' => 'sent'
];
offerAuthorizationAssert(
    offerResponseOfferBelongsToSubmission($activeOffer, 501, 101)
        && offerResponseOfferIsAvailable($activeOffer),
    'The latest active Admin offer for the authorized submission must be answerable.'
);

offerAuthorizationAssert(
    !offerResponseOfferBelongsToSubmission($activeOffer, 502, 101),
    'A different offer ID must not be used to respond to the submission.'
);

offerAuthorizationAssert(
    !offerResponseOfferBelongsToSubmission($activeOffer, 501, 102),
    'An offer must not be transferable to another submission.'
);

offerAuthorizationAssert(
    !offerResponseOfferIsAvailable([
        ...$activeOffer,
        'status' => 'declined'
    ]),
    'A declined or otherwise inactive offer must not be answerable.'
);

offerAuthorizationAssert(
    !offerResponseOfferIsAvailable([
        ...$activeOffer,
        'sender_type' => 'seller'
    ]),
    'A Seller counter-offer must not be processed by the Seller response endpoint.'
);

$_SESSION['csrf_token'] = str_repeat('a', 64);
offerAuthorizationAssert(
    verifyCsrfToken(str_repeat('a', 64)),
    'A valid session CSRF token must remain accepted.'
);
offerAuthorizationAssert(
    !verifyCsrfToken(str_repeat('b', 64)),
    'An invalid CSRF token must be rejected.'
);

echo "Offer response authorization tests passed.\n";
