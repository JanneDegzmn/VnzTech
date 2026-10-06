<?php

require_once __DIR__ . '/database.php';
require_once __DIR__ . '/session.php';
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/csrf.php';
require_once __DIR__ . '/admin_activity.php';

header('Content-Type: application/json; charset=utf-8');

ob_start();


/*
|--------------------------------------------------------------------------
| JSON RESPONSE
|--------------------------------------------------------------------------
|
| Same shape/behavior as the jsonResponse() helper in
| admin_respond_offer.php, so every admin-facing offer
| endpoint now speaks the same JSON contract.
|
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

    if (!hasPermission('manage_offers')) {

        jsonResponse(
            false,
            'You do not have permission to send offers.',
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
    | Input
    |--------------------------------------------------------------------------
    */

    $submissionId = isset($_POST['submission_id'])
        ? (int) $_POST['submission_id']
        : 0;

    $offerAmountRaw = trim(
        (string) ($_POST['offer_amount'] ?? '')
    );

    $feedback = trim(
        (string) ($_POST['feedback'] ?? '')
    );


    if ($submissionId <= 0) {

        jsonResponse(
            false,
            'Invalid submission ID.',
            400
        );

    }


    if (
        $offerAmountRaw === ''
        ||
        !is_numeric($offerAmountRaw)
    ) {

        jsonResponse(
            false,
            'Please enter a valid offer amount.',
            422
        );

    }


    $offerAmount =
        (float) $offerAmountRaw;


    if ($offerAmount < 0) {

        jsonResponse(
            false,
            'Offer amount cannot be negative.',
            422
        );

    }


    if ($offerAmount > 999999999) {

        jsonResponse(
            false,
            'Offer amount is too large.',
            422
        );

    }


    $feedback = $feedback !== ''
        ? mb_substr($feedback, 0, 3000)
        : null;


    /*
    |--------------------------------------------------------------------------
    | Allowed States
    |--------------------------------------------------------------------------
    |
    | First offer can be sent from:
    |
    | new_submission
    | under_review
    | offer_pending
    | offer_sent
    |
    | offer_sent is included because it also supports another
    | VNZ TECH counter-offer after a seller counter.
    |
    */

    $allowedSubmissionStatuses = [
        'new_submission',
        'under_review',
        'offer_pending',
        'offer_sent'
    ];


    /*
    |--------------------------------------------------------------------------
    | Determine "sent" status in offers table
    |--------------------------------------------------------------------------
    |
    | Read once, before the transaction - this only reads column
    | metadata (SHOW COLUMNS), it does not touch submission/offer
    | rows, so there's nothing here for FOR UPDATE to protect.
    |
    */

    $statusValue = 'sent';


    $statusStmt =
        $pdo->query("
            SHOW COLUMNS
            FROM offers
            LIKE 'status'
        ");


    $statusColumn =
        $statusStmt->fetch();


    if ($statusColumn) {

        $columnType =
            strtolower(
                (string) $statusColumn['Type']
            );


        if (
            str_starts_with(
                $columnType,
                'enum('
            )
        ) {

            preg_match_all(
                "/'((?:[^'\\\\]|\\\\.)*)'/",
                $columnType,
                $matches
            );


            $enumValues =
                $matches[1] ?? [];


            $foundStatus =
                null;


            foreach (
                $enumValues as $enumValue
            ) {

                $decodedValue =
                    stripslashes(
                        $enumValue
                    );


                if (
                    strtolower(
                        $decodedValue
                    )
                    ===
                    'sent'
                ) {

                    $foundStatus =
                        $decodedValue;

                    break;

                }

            }


            if ($foundStatus !== null) {

                $statusValue =
                    $foundStatus;

            }
            elseif (!empty($enumValues)) {

                $statusValue =
                    stripslashes(
                        $enumValues[0]
                    );

            }

        }

    }


    /*
    |--------------------------------------------------------------------------
    | Transaction
    |--------------------------------------------------------------------------
    |
    | RACE-CONDITION FIX (unchanged from previous pass):
    |
    | The submission's status is fetched WITH a lock (FOR UPDATE)
    | *inside* the transaction. If two admins click "Send Offer"
    | on the same submission at nearly the same instant, the
    | second request blocks on the row lock until the first one
    | commits, then re-reads the (now-updated) status and
    | correctly rejects itself with a 409 - instead of both
    | requests reading "under_review" and both inserting an
    | offer / racing to update the same submission.
    |
    | This mirrors the same FOR UPDATE pattern already used in
    | counter_offer.php.
    |
    */

    $pdo->beginTransaction();


    /*
    |--------------------------------------------------------------------------
    | Lock + Re-check Submission
    |--------------------------------------------------------------------------
    */

    $submissionStmt =
        $pdo->prepare("
            SELECT
                id,
                reference_number,
                status
            FROM submissions
            WHERE id = ?
            LIMIT 1
            FOR UPDATE
        ");

    $submissionStmt->execute([
        $submissionId
    ]);

    $submission =
        $submissionStmt->fetch();


    if (!$submission) {

        throw new RuntimeException(
            'Submission not found.',
            404
        );

    }


    if (
        !in_array(
            $submission['status'],
            $allowedSubmissionStatuses,
            true
        )
    ) {

        throw new RuntimeException(
            'This submission is no longer available for negotiation.',
            409
        );

    }


    /*
    |--------------------------------------------------------------------------
    | Insert Admin Offer
    |--------------------------------------------------------------------------
    */

    $offerStmt =
        $pdo->prepare("
            INSERT INTO offers
            (
                submission_id,
                created_by_admin_id,
                sender_type,
                offer_amount,
                feedback,
                status,
                sent_at,
                responded_at,
                created_at,
                updated_at
            )
            VALUES
            (
                ?,
                ?,
                'admin',
                ?,
                ?,
                ?,
                NOW(),
                NULL,
                NOW(),
                NOW()
            )
        ");


    $offerStmt->execute([
        $submissionId,
        $adminId,
        $offerAmount,
        $feedback,
        $statusValue
    ]);


    $newOfferId =
        (int) $pdo->lastInsertId();


    /*
    |--------------------------------------------------------------------------
    | Update Submission Status
    |--------------------------------------------------------------------------
    */

    $submissionUpdate =
        $pdo->prepare("
            UPDATE submissions
            SET
                status = 'offer_sent',
                updated_at = NOW()
            WHERE id = ?
        ");


    $submissionUpdate->execute([
        $submissionId
    ]);


    /*
    |--------------------------------------------------------------------------
    | Commit
    |--------------------------------------------------------------------------
    */

    logAdminActivity(
        $adminId,
        'offer.sent',
        'Offer sent for submission #' . $submissionId . '.'
    );
    $pdo->commit();


    /*
    |--------------------------------------------------------------------------
    | Success
    |--------------------------------------------------------------------------
    */

    jsonResponse(
        true,
        'Offer sent successfully.',
        200,
        [
            'submission_id' => $submissionId,
            'offer_id'      => $newOfferId,
            'offer_amount'  => $offerAmount
        ]
    );


} catch (RuntimeException $e) {

    if (
        isset($pdo)
        &&
        $pdo instanceof PDO
        &&
        $pdo->inTransaction()
    ) {

        $pdo->rollBack();

    }


    $httpCode =
        (int) $e->getCode();

    jsonResponse(
        false,
        $e->getMessage(),
        $httpCode >= 400
            ? $httpCode
            : 500
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
        'Unable to send offer: ' . $e->getMessage(),
        500
    );

}