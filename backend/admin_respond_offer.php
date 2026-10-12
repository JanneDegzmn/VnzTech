<?php

require_once __DIR__ . '/database.php';
require_once __DIR__ . '/session.php';
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/csrf.php';

header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);

    echo json_encode([
        'success' => false,
        'message' => 'Method not allowed.'
    ]);

    exit;
}

if (!isAdminLoggedIn() || !getCurrentAdmin()) {
    http_response_code(401);

    echo json_encode([
        'success' => false,
        'message' => 'Your admin session has expired. Please log in again.'
    ]);

    exit;
}

if (!hasPermission('manage_offers')) {
    http_response_code(403);

    echo json_encode([
        'success' => false,
        'message' => 'You do not have permission to manage offers.'
    ]);

    exit;
}

if (!verifyCsrfToken($_POST['csrf_token'] ?? null)) {
    http_response_code(419);

    echo json_encode([
        'success' => false,
        'message' => 'Your session has expired. Please refresh the page and try again.'
    ]);

    exit;
}

function findEnumValue(
    PDO $pdo,
    string $table,
    string $column,
    string $wantedValue
) {
    $stmt = $pdo->query("
        SHOW COLUMNS
        FROM `$table`
        LIKE '$column'
    ");

    $columnInfo = $stmt->fetch();

    if (!$columnInfo) {
        return null;
    }

    $type = strtolower((string) $columnInfo['Type']);

    if (!str_starts_with($type, 'enum(')) {
        return $wantedValue;
    }

    preg_match_all(
        "/'((?:[^'\\\\]|\\\\.)*)'/",
        $type,
        $matches
    );

    $values = $matches[1] ?? [];

    foreach ($values as $value) {
        $decoded = stripslashes($value);

        if (strtolower($decoded) === strtolower($wantedValue)) {
            return $decoded;
        }
    }

    return null;
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

$offerId = isset($_POST['offer_id'])
    ? (int) $_POST['offer_id']
    : 0;

$response = strtolower(
    trim(
        (string) ($_POST['response'] ?? '')
    )
);

if ($offerId <= 0) {
    http_response_code(422);

    echo json_encode([
        'success' => false,
        'message' => 'Invalid offer.'
    ]);

    exit;
}

if (!in_array($response, ['accept', 'decline'], true)) {
    http_response_code(422);

    echo json_encode([
        'success' => false,
        'message' => 'Invalid response.'
    ]);

    exit;
}

/*
|--------------------------------------------------------------------------
| Resolve Submission
|--------------------------------------------------------------------------
*/

$offerSubmissionStmt = $pdo->prepare("
    SELECT submission_id
    FROM offers
    WHERE id = ?
    LIMIT 1
");

$offerSubmissionStmt->execute([$offerId]);
$submissionId = $offerSubmissionStmt->fetchColumn();

if (!$submissionId) {
    http_response_code(404);

    echo json_encode([
        'success' => false,
        'message' => 'Offer not found.'
    ]);

    exit;
}

/*
|--------------------------------------------------------------------------
| Determine Actual Offer ENUM Value
|--------------------------------------------------------------------------
*/

$wantedOfferStatus = $response === 'accept'
    ? 'accepted'
    : 'declined';

$actualOfferStatus = findEnumValue(
    $pdo,
    'offers',
    'status',
    $wantedOfferStatus
);

if ($actualOfferStatus === null) {
    http_response_code(500);

    echo json_encode([
        'success' => false,
        'message' =>
            'Your offers table does not currently allow '
            . $wantedOfferStatus
            . ' status.'
    ]);

    exit;
}

/*
|--------------------------------------------------------------------------
| Transaction
|--------------------------------------------------------------------------
*/

try {

    $pdo->beginTransaction();

    $submissionStmt = $pdo->prepare("
        SELECT id, status
        FROM submissions
        WHERE id = ?
        LIMIT 1
        FOR UPDATE
    ");
    $submissionStmt->execute([(int) $submissionId]);
    $submission = $submissionStmt->fetch(PDO::FETCH_ASSOC);

    if (!$submission) {
        throw new RuntimeException('Submission not found.', 404);
    }

    if ($submission['status'] !== 'offer_sent') {
        throw new RuntimeException(
            'This submission can no longer be responded to.',
            409
        );
    }

    $latestOfferStmt = $pdo->prepare("
        SELECT
            id AS offer_id,
            submission_id,
            offer_amount,
            sender_type,
            status
        FROM offers
        WHERE submission_id = ?
        ORDER BY id DESC
        LIMIT 1
        FOR UPDATE
    ");
    $latestOfferStmt->execute([(int) $submissionId]);
    $data = $latestOfferStmt->fetch(PDO::FETCH_ASSOC);

    if (!$data || (int) $data['offer_id'] !== $offerId) {
        throw new RuntimeException(
            'This counter-offer is no longer the active negotiation.',
            409
        );
    }

    if (
        strtolower((string) $data['sender_type']) !== 'seller'
        || !in_array(
            strtolower((string) $data['status']),
            ['sent', 'offer_sent', 'pending', 'active'],
            true
        )
    ) {
        throw new RuntimeException(
            'This counter-offer is not available for a response.',
            409
        );
    }

    /*
    |--------------------------------------------------------------------------
    | Update Offer
    |--------------------------------------------------------------------------
    */

    $offerUpdate = $pdo->prepare("
        UPDATE offers

        SET
            status = ?,
            responded_at = NOW(),
            updated_at = NOW()

        WHERE id = ?
        AND submission_id = ?
        AND sender_type = 'seller'
        AND status = ?
    ");

    $offerUpdate->execute([
        $actualOfferStatus,
        $offerId,
        (int) $submissionId,
        $data['status']
    ]);

    /*
    |--------------------------------------------------------------------------
    | Make Sure Offer Was Actually Updated
    |--------------------------------------------------------------------------
    */

    if ($offerUpdate->rowCount() !== 1) {
        throw new RuntimeException(
            'This counter-offer is no longer available for a response.',
            409
        );
    }

    /*
    |--------------------------------------------------------------------------
    | Update Submission
    |--------------------------------------------------------------------------
    |
    | result_seen_by_seller is reset to 0 here too, even though the
    | SELLER is the one performing this action. Per project decision:
    | ANY transition to accepted/declined should surface the
    | dashboard popup, regardless of which side triggered it — not
    | just the admin-driven path in admin_respond_offer.php.
    |
    */

    $submissionStatus = $response === 'accept'
        ? 'accepted'
        : 'declined';

    $submissionUpdate = $pdo->prepare("
        UPDATE submissions

        SET
            status = ?,
            result_seen_by_seller = 0,
            updated_at = NOW()

        WHERE id = ?
          AND status = 'offer_sent'
    ");

    $submissionUpdate->execute([
        $submissionStatus,
        (int) $submissionId
    ]);
    if ($submissionUpdate->rowCount() !== 1) {
        throw new RuntimeException(
            'This submission can no longer be responded to.',
            409
        );
    }

    /*
    |--------------------------------------------------------------------------
    | Create Transaction ONLY When Seller Accepts
    |--------------------------------------------------------------------------
    */

    $transactionReference = null;

    if ($response === 'accept') {

        /*
        |--------------------------------------------------------------------------
        | Prevent Duplicate Transaction
        |--------------------------------------------------------------------------
        */

        $existingTransaction = $pdo->prepare("
            SELECT
                id,
                transaction_reference
            FROM transactions
            WHERE submission_id = ?
            LIMIT 1
            FOR UPDATE
        ");

        $existingTransaction->execute([
            (int) $submissionId
        ]);

        $existing = $existingTransaction->fetch();

        if ($existing) {
            throw new RuntimeException(
                'A transaction already exists for this submission.',
                409
            );
        }

        /*
        |--------------------------------------------------------------------------
        | Generate Real Transaction Reference
        |--------------------------------------------------------------------------
        */

        $transactionReference = generateTransactionReference($pdo);

        /*
        |--------------------------------------------------------------------------
        | Insert Real Transaction
        |--------------------------------------------------------------------------
        |
        | Data comes directly from:
        | - accepted offer
        | - related submission
        |
        | No dummy/test values.
        |
        */

        $transactionInsert = $pdo->prepare("
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
                updated_at,
                seen_by_admin
            )
            VALUES (
                ?,
                ?,
                ?,
                ?,
                NULL,
                'pending',
                'pending',
                NULL,
                NULL,
                NOW(),
                NOW(),
                0
            )
        ");

        $transactionInsert->execute([
            (int) $submissionId,
            $data['offer_id'],
            $transactionReference,
            $data['offer_amount']
        ]);
        $transactionId = (int) $pdo->lastInsertId();
    }

    /*
    |--------------------------------------------------------------------------
    | Commit
    |--------------------------------------------------------------------------
    */

    $pdo->commit();

    /*
    |--------------------------------------------------------------------------
    | Success Response
    |--------------------------------------------------------------------------
    */

    if ($response === 'accept') {

        echo json_encode([
            'success' => true,
            'message' => 'Offer accepted successfully. Transaction created.',
            'transaction_reference' => $transactionReference,
            'transaction_id' => $transactionId,
            'offer_amount' => (float) $data['offer_amount']
        ]);

    } else {

        echo json_encode([
            'success' => true,
            'message' => 'Offer declined successfully.'
        ]);
    }

    exit;

} catch (Throwable $e) {

    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }

    $httpStatus = $e instanceof RuntimeException
        ? (int) $e->getCode()
        : 500;
    if (!in_array($httpStatus, [404, 409], true)) {
        $httpStatus = 500;
    }

    http_response_code($httpStatus);

    echo json_encode([
        'success' => false,
        'message' => $httpStatus === 500
            ? 'Unable to update the offer.'
            : $e->getMessage()
    ]);

    exit;
}