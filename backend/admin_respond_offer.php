<?php

require_once __DIR__ . '/database.php';
require_once __DIR__ . '/session.php';
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/csrf.php';

header('Content-Type: application/json; charset=utf-8');

ob_start();


/*
|--------------------------------------------------------------------------
| JSON RESPONSE
|--------------------------------------------------------------------------
*/

function jsonResponse(
    bool $success,
    string $message,
    int $httpCode = 200,
    array $extra = []
) {
    if (ob_get_length()) {
        ob_clean();
    }

    http_response_code($httpCode);

    echo json_encode(
        array_merge(
            [
                'success' => $success,
                'message' => $message
            ],
            $extra
        )
    );

    exit;
}


/*
|--------------------------------------------------------------------------
| Generate Unique Transaction Reference
|--------------------------------------------------------------------------
|
| Format:
| #TX-YYMMDD-XXXX
|
| Example:
| #TX-260911-4827
|
| Mirrors the same generator used in respond_offer.php so that
| transaction references stay consistent regardless of who
| accepted the offer (seller or admin).
|
*/
function generateTransactionReference(PDO $pdo)
{
    for ($attempt = 0; $attempt < 20; $attempt++) {

        $reference = '#TX-'
            . date('ymd')
            . '-'
            . random_int(1000, 9999);

        $stmt = $pdo->prepare("
            SELECT id
            FROM transactions
            WHERE transaction_reference = ?
            LIMIT 1
        ");

        $stmt->execute([$reference]);

        if (!$stmt->fetch()) {
            return $reference;
        }
    }

    throw new RuntimeException(
        'Unable to generate a unique transaction reference.'
    );
}


try {

    /*
    |--------------------------------------------------------------------------
    | METHOD
    |--------------------------------------------------------------------------
    */

    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {

        jsonResponse(
            false,
            'Method not allowed.',
            405
        );

    }


    /*
    |--------------------------------------------------------------------------
    | ADMIN SESSION
    |--------------------------------------------------------------------------
    */

    if (
        !function_exists('isAdminLoggedIn')
        ||
        !isAdminLoggedIn()
    ) {

        jsonResponse(
            false,
            'Your admin session has expired. Please log in again.',
            401
        );

    }


    /*
    |--------------------------------------------------------------------------
    | CSRF
    |--------------------------------------------------------------------------
    */

    if (
        !verifyCsrfToken(
            $_POST['csrf_token'] ?? null
        )
    ) {

        jsonResponse(
            false,
            'Your session has expired. Please refresh the page and try again.',
            419
        );

    }


    /*
    |--------------------------------------------------------------------------
    | PERMISSION
    |--------------------------------------------------------------------------
    */

    if (
        !hasPermission('manage_offers')
    ) {

        jsonResponse(
            false,
            'You do not have permission to manage offers.',
            403
        );

    }


    /*
    |--------------------------------------------------------------------------
    | CURRENT ADMIN
    |--------------------------------------------------------------------------
    */

    $currentAdmin =
        getCurrentAdmin();


    if (!$currentAdmin) {

        jsonResponse(
            false,
            'Admin session is invalid. Please log in again.',
            401
        );

    }


    $adminId =
        (int) $currentAdmin['id'];


    /*
    |--------------------------------------------------------------------------
    | REQUEST
    |--------------------------------------------------------------------------
    */

    $offerId =
        isset($_POST['offer_id'])
            ? (int) $_POST['offer_id']
            : 0;


    $response =
        strtolower(
            trim(
                (string) (
                    $_POST['response']
                    ?? ''
                )
            )
        );


    /*
    |--------------------------------------------------------------------------
    | VALIDATION
    |--------------------------------------------------------------------------
    */

    if ($offerId <= 0) {

        jsonResponse(
            false,
            'Invalid offer.',
            422
        );

    }


    if (
        !in_array(
            $response,
            [
                'accept',
                'decline'
            ],
            true
        )
    ) {

        jsonResponse(
            false,
            'Invalid response.',
            422
        );

    }


    /*
    |--------------------------------------------------------------------------
    | GET OFFER
    |--------------------------------------------------------------------------
    */

    $stmt =
        $pdo->prepare("
            SELECT
                o.id AS offer_id,
                o.submission_id,
                o.offer_amount,
                o.status AS offer_status,
                o.sender_type,

                s.reference_number,
                s.status AS submission_status

            FROM offers o

            INNER JOIN submissions s
                ON s.id = o.submission_id

            WHERE o.id = ?

            LIMIT 1
        ");


    $stmt->execute([
        $offerId
    ]);


    $offer =
        $stmt->fetch();


    if (!$offer) {

        jsonResponse(
            false,
            'Offer not found.',
            404
        );

    }


    /*
    |--------------------------------------------------------------------------
    | SELLER COUNTER ONLY
    |--------------------------------------------------------------------------
    */

    if (
        strtolower(
            (string)
            $offer['sender_type']
        )
        !==
        'seller'
    ) {

        jsonResponse(
            false,
            'This offer is not a seller counter-offer.',
            409
        );

    }


    /*
    |--------------------------------------------------------------------------
    | SUBMISSION MUST BE NEGOTIATING
    |--------------------------------------------------------------------------
    */

    if (
        $offer['submission_status']
        !==
        'offer_sent'
    ) {

        jsonResponse(
            false,
            'This submission is no longer available for negotiation.',
            409
        );

    }


    /*
    |--------------------------------------------------------------------------
    | TRANSACTION
    |--------------------------------------------------------------------------
    */

    $pdo->beginTransaction();


    /*
    |--------------------------------------------------------------------------
    | GET LATEST OFFER WITH LOCK
    |--------------------------------------------------------------------------
    */

    $latestStmt =
        $pdo->prepare("
            SELECT
                id,
                sender_type,
                status

            FROM offers

            WHERE submission_id = ?

            ORDER BY id DESC

            LIMIT 1

            FOR UPDATE
        ");


    $latestStmt->execute([
        $offer['submission_id']
    ]);


    $latestOffer =
        $latestStmt->fetch();


    if (!$latestOffer) {

        throw new RuntimeException(
            'No active offer was found.'
        );

    }


    /*
    |--------------------------------------------------------------------------
    | PREVENT OLD OFFER ACTION
    |--------------------------------------------------------------------------
    */

    if (
        (int)
        $latestOffer['id']
        !==
        $offerId
    ) {

        throw new RuntimeException(
            'This seller counter-offer is no longer active.'
        );

    }


    if (
        strtolower(
            (string)
            $latestOffer['sender_type']
        )
        !==
        'seller'
    ) {

        throw new RuntimeException(
            'The latest negotiation was not sent by the seller.'
        );

    }


    /*
    |--------------------------------------------------------------------------
    | READ OFFER STATUS ENUM
    |--------------------------------------------------------------------------
    */

    $columnStmt =
        $pdo->query("
            SHOW COLUMNS
            FROM offers
            LIKE 'status'
        ");


    $column =
        $columnStmt->fetch();


    if (!$column) {

        throw new RuntimeException(
            'The offers.status column could not be found.'
        );

    }


    $columnType =
        (string)
        $column['Type'];


    $enumValues = [];


    preg_match_all(
        "/'((?:[^'\\\\]|\\\\.)*)'/",
        $columnType,
        $matches
    );


    foreach (
        ($matches[1] ?? [])
        as $value
    ) {

        $enumValues[] =
            stripslashes(
                $value
            );

    }


    /*
    |--------------------------------------------------------------------------
    | FIND ACCEPTED / DECLINED
    |--------------------------------------------------------------------------
    */

    $acceptedStatus = null;
    $declinedStatus = null;


    foreach (
        $enumValues
        as $value
    ) {

        $normalized =
            strtolower(
                trim($value)
            );


        if (
            $normalized
            ===
            'accepted'
        ) {

            $acceptedStatus =
                $value;

        }


        if (
            $normalized
            ===
            'declined'
        ) {

            $declinedStatus =
                $value;

        }

    }


    if (
        $acceptedStatus === null
        ||
        $declinedStatus === null
    ) {

        throw new RuntimeException(
            'The offers.status ENUM does not contain accepted and declined values.'
        );

    }


    /*
    |--------------------------------------------------------------------------
    | DETERMINE NEW STATUS
    |--------------------------------------------------------------------------
    */

    $newOfferStatus =
        $response === 'accept'
            ? $acceptedStatus
            : $declinedStatus;


    $newSubmissionStatus =
        $response === 'accept'
            ? 'accepted'
            : 'declined';


    /*
    |--------------------------------------------------------------------------
    | UPDATE OFFER
    |--------------------------------------------------------------------------
    */

    $updateOffer =
        $pdo->prepare("
            UPDATE offers

            SET
                status = ?,
                responded_at = NOW(),
                updated_at = NOW()

            WHERE id = ?

            LIMIT 1
        ");


    $updateOffer->execute([
        $newOfferStatus,
        $offerId
    ]);


    /*
    |--------------------------------------------------------------------------
    | UPDATE SUBMISSION
    |--------------------------------------------------------------------------
    */

    $updateSubmission =
        $pdo->prepare("
            UPDATE submissions

            SET
                status = ?,
                updated_at = NOW()

            WHERE id = ?

            LIMIT 1
        ");


    $updateSubmission->execute([
        $newSubmissionStatus,
        $offer['submission_id']
    ]);


    /*
    |--------------------------------------------------------------------------
    | CREATE TRANSACTION ONLY WHEN ADMIN ACCEPTS
    |--------------------------------------------------------------------------
    |
    | Mirrors respond_offer.php (seller-accept path) so that every
    | "accepted" submission ends up with exactly one transactions
    | row, regardless of which side clicked accept.
    |
    */

    $transactionReference = null;


    if ($response === 'accept') {

        /*
        |--------------------------------------------------------------------------
        | Prevent Duplicate Transaction
        |--------------------------------------------------------------------------
        */

        $existingTransaction =
            $pdo->prepare("
                SELECT
                    id,
                    transaction_reference
                FROM transactions
                WHERE offer_id = ?
                LIMIT 1
                FOR UPDATE
            ");

        $existingTransaction->execute([
            $offer['offer_id']
        ]);

        $existing =
            $existingTransaction->fetch();

        if ($existing) {

            throw new RuntimeException(
                'A transaction already exists for this accepted offer.'
            );

        }


        /*
        |--------------------------------------------------------------------------
        | Generate Real Transaction Reference
        |--------------------------------------------------------------------------
        */

        $transactionReference =
            generateTransactionReference($pdo);


        /*
        |--------------------------------------------------------------------------
        | Insert Real Transaction
        |--------------------------------------------------------------------------
        |
        | Data comes directly from:
        | - the accepted seller counter-offer
        | - the related submission
        |
        | processed_by_admin_id is set here (unlike the seller-accept
        | path in respond_offer.php, where no admin is involved yet)
        | since we already have the authenticated admin in context.
        |
        */

        $transactionInsert =
            $pdo->prepare("
                INSERT INTO transactions (
                    submission_id,
                    offer_id,
                    transaction_reference,
                    final_amount,
                    payment_method,
                    payment_status,
                    transaction_status,
                    processed_by_admin_id,
                    notes,
                    created_at,
                    updated_at
                )

                VALUES (
                    ?,
                    ?,
                    ?,
                    ?,
                    NULL,
                    'pending',
                    'pending',
                    ?,
                    NULL,
                    NOW(),
                    NOW()
                )
            ");

        $transactionInsert->execute([
            $offer['submission_id'],
            $offer['offer_id'],
            $transactionReference,
            $offer['offer_amount'],
            $adminId
        ]);

    }


    /*
    |--------------------------------------------------------------------------
    | COMMIT
    |--------------------------------------------------------------------------
    */

    $pdo->commit();


    /*
    |--------------------------------------------------------------------------
    | SUCCESS
    |--------------------------------------------------------------------------
    */

    if ($response === 'accept') {

        jsonResponse(
            true,
            'Seller counter-offer accepted. Transaction created.',
            200,
            [
                'transaction_reference' => $transactionReference
            ]
        );

    }

    jsonResponse(
        true,
        'Seller counter-offer declined.'
    );


} catch (Throwable $e) {

    if (
        isset($pdo)
        &&
        $pdo instanceof PDO
        &&
        $pdo->inTransaction()
    ) {

        $pdo->rollBack();

    }


    jsonResponse(
        false,
        $e->getMessage()
            ?: 'Unable to update seller counter-offer.',
        500
    );

}