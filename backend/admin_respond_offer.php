<?php

require_once __DIR__ . '/database.php';

header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);

    echo json_encode([
        'success' => false,
        'message' => 'Method not allowed.'
    ]);

    exit;
}

function normalizeReference($value)
{
    $value = trim((string) $value);

    if ($value === '') {
        return '';
    }

    if ($value[0] !== '#') {
        $value = '#' . $value;
    }

    return strtoupper($value);
}

function normalizeMobile($value)
{
    return preg_replace('/\D+/', '', (string) $value);
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

$reference = normalizeReference(
    $_POST['reference'] ?? ''
);

$mobile = normalizeMobile(
    $_POST['mobile'] ?? ''
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

if ($reference === '' || $mobile === '') {
    http_response_code(422);

    echo json_encode([
        'success' => false,
        'message' => 'Reference number and mobile number are required.'
    ]);

    exit;
}

/*
|--------------------------------------------------------------------------
| Get Offer + Submission
|--------------------------------------------------------------------------
*/

$stmt = $pdo->prepare("
    SELECT
        o.id AS offer_id,
        o.submission_id,
        o.offer_amount,
        o.status AS offer_status,

        s.reference_number,
        s.seller_mobile,
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

$data = $stmt->fetch();

if (!$data) {
    http_response_code(404);

    echo json_encode([
        'success' => false,
        'message' => 'Offer not found.'
    ]);

    exit;
}

/*
|--------------------------------------------------------------------------
| Verify Reference + Mobile
|--------------------------------------------------------------------------
*/

if (
    strtoupper($data['reference_number'])
    !== $reference
) {
    http_response_code(403);

    echo json_encode([
        'success' => false,
        'message' => 'Reference number does not match.'
    ]);

    exit;
}

if (
    normalizeMobile($data['seller_mobile'])
    !== $mobile
) {
    http_response_code(403);

    echo json_encode([
        'success' => false,
        'message' => 'Mobile number does not match.'
    ]);

    exit;
}

/*
|--------------------------------------------------------------------------
| Make Sure Offer Is Still Respondable
|--------------------------------------------------------------------------
*/

if ($data['submission_status'] !== 'offer_sent') {
    http_response_code(409);

    echo json_encode([
        'success' => false,
        'message' => 'This offer can no longer be responded to.'
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
    ");

    $offerUpdate->execute([
        $actualOfferStatus,
        $offerId,
        $data['submission_id']
    ]);

    /*
    |--------------------------------------------------------------------------
    | Make Sure Offer Was Actually Updated
    |--------------------------------------------------------------------------
    */

    if ($offerUpdate->rowCount() !== 1) {
        throw new RuntimeException(
            'Unable to update the selected offer.'
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
    ");

    $submissionUpdate->execute([
        $submissionStatus,
        $data['submission_id']
    ]);

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
            WHERE offer_id = ?
            LIMIT 1
            FOR UPDATE
        ");

        $existingTransaction->execute([
            $data['offer_id']
        ]);

        $existing = $existingTransaction->fetch();

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
            $data['submission_id'],
            $data['offer_id'],
            $transactionReference,
            $data['offer_amount']
        ]);
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
            'transaction_reference' => $transactionReference
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

    http_response_code(500);

    echo json_encode([
        'success' => false,
        'message' => 'Unable to update the offer.'
    ]);

    exit;
}