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

/*
|--------------------------------------------------------------------------
| Helpers
|--------------------------------------------------------------------------
*/

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


function getSentStatus(PDO $pdo)
{
    $fallback = 'sent';

    try {

        $stmt = $pdo->query("
            SHOW COLUMNS
            FROM offers
            LIKE 'status'
        ");

        $column = $stmt->fetch();

        if (!$column) {
            return $fallback;
        }

        $type = strtolower(
            (string) $column['Type']
        );

        if (!str_starts_with($type, 'enum(')) {
            return $fallback;
        }

        preg_match_all(
            "/'((?:[^'\\\\]|\\\\.)*)'/",
            $type,
            $matches
        );

        $values = $matches[1] ?? [];

        foreach ($values as $value) {

            $decoded = stripslashes($value);

            if (strtolower($decoded) === 'sent') {
                return $decoded;
            }

        }

        foreach ($values as $value) {

            $decoded = stripslashes($value);

            if (
                in_array(
                    strtolower($decoded),
                    [
                        'offer_sent',
                        'pending',
                        'active'
                    ],
                    true
                )
            ) {
                return $decoded;
            }

        }

        if (!empty($values)) {
            return stripslashes($values[0]);
        }

    } catch (Throwable $e) {

        return $fallback;

    }

    return $fallback;
}


/*
|--------------------------------------------------------------------------
| Request Data
|--------------------------------------------------------------------------
*/

$offerId = isset($_POST['offer_id'])
    ? (int) $_POST['offer_id']
    : 0;

$reference = normalizeReference(
    $_POST['reference'] ?? ''
);

$mobile = normalizeMobile(
    $_POST['mobile'] ?? ''
);

$counterAmountRaw = trim(
    (string) (
        $_POST['counter_amount'] ?? ''
    )
);

$feedback = trim(
    (string) (
        $_POST['feedback'] ?? ''
    )
);


/*
|--------------------------------------------------------------------------
| Basic Validation
|--------------------------------------------------------------------------
*/

if ($offerId <= 0) {

    http_response_code(422);

    echo json_encode([
        'success' => false,
        'message' => 'Invalid offer.'
    ]);

    exit;
}


if ($reference === '' || $mobile === '') {

    http_response_code(422);

    echo json_encode([
        'success' => false,
        'message' =>
            'Reference number and mobile number are required.'
    ]);

    exit;
}


if (
    $counterAmountRaw === ''
    ||
    !is_numeric($counterAmountRaw)
) {

    http_response_code(422);

    echo json_encode([
        'success' => false,
        'message' =>
            'Please enter a valid counter-offer amount.'
    ]);

    exit;
}


$counterAmount = (float) $counterAmountRaw;


if ($counterAmount <= 0) {

    http_response_code(422);

    echo json_encode([
        'success' => false,
        'message' =>
            'Counter-offer amount must be greater than ₱0.00.'
    ]);

    exit;
}


if ($counterAmount > 999999999) {

    http_response_code(422);

    echo json_encode([
        'success' => false,
        'message' =>
            'Counter-offer amount is too large.'
    ]);

    exit;
}


$feedback = $feedback !== ''
    ? mb_substr($feedback, 0, 3000)
    : null;


/*
|--------------------------------------------------------------------------
| Get Current Offer
|--------------------------------------------------------------------------
*/

$stmt = $pdo->prepare("
    SELECT
        o.id AS offer_id,
        o.submission_id,
        o.offer_amount,
        o.status AS offer_status,
        o.sender_type,

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

$currentOffer = $stmt->fetch();


if (!$currentOffer) {

    http_response_code(404);

    echo json_encode([
        'success' => false,
        'message' => 'Offer not found.'
    ]);

    exit;
}


/*
|--------------------------------------------------------------------------
| Verify Reference
|--------------------------------------------------------------------------
*/

if (
    strtoupper(
        trim($currentOffer['reference_number'])
    )
    !==
    $reference
) {

    http_response_code(403);

    echo json_encode([
        'success' => false,
        'message' =>
            'Reference number does not match.'
    ]);

    exit;
}


/*
|--------------------------------------------------------------------------
| Verify Mobile
|--------------------------------------------------------------------------
*/

if (
    normalizeMobile(
        $currentOffer['seller_mobile']
    )
    !==
    $mobile
) {

    http_response_code(403);

    echo json_encode([
        'success' => false,
        'message' =>
            'Mobile number does not match.'
    ]);

    exit;
}


/*
|--------------------------------------------------------------------------
| Seller Can Only Counter an Admin Offer
|--------------------------------------------------------------------------
*/

if (
    strtolower(
        (string) $currentOffer['sender_type']
    ) !== 'admin'
) {

    http_response_code(409);

    echo json_encode([
        'success' => false,
        'message' =>
            'Only a VNZ TECH offer can be countered.'
    ]);

    exit;
}


/*
|--------------------------------------------------------------------------
| Submission Must Be in Negotiation
|--------------------------------------------------------------------------
*/

if (
    !in_array(
        $currentOffer['submission_status'],
        [
            'offer_sent',
            'offer_pending',
            'under_review'
        ],
        true
    )
) {

    http_response_code(409);

    echo json_encode([
        'success' => false,
        'message' =>
            'This submission is no longer available for negotiation.'
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
    | Lock Latest Offer
    |--------------------------------------------------------------------------
    */

    $latestStmt = $pdo->prepare("
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
        $currentOffer['submission_id']
    ]);

    $latestOffer = $latestStmt->fetch();


    if (!$latestOffer) {

        throw new RuntimeException(
            'No active offer was found.'
        );

    }


    /*
    |--------------------------------------------------------------------------
    | Make Sure Seller Is Countering the Latest Admin Offer
    |--------------------------------------------------------------------------
    */

    if (
        (int) $latestOffer['id']
        !==
        (int) $offerId
    ) {

        throw new RuntimeException(
            'This offer is no longer the active negotiation.'
        );

    }


    if (
        strtolower(
            (string) $latestOffer['sender_type']
        ) !== 'admin'
    ) {

        throw new RuntimeException(
            'The latest offer was not sent by VNZ TECH.'
        );

    }


    /*
    |--------------------------------------------------------------------------
    | Determine Status
    |--------------------------------------------------------------------------
    */

    $statusValue = getSentStatus($pdo);


    /*
    |--------------------------------------------------------------------------
    | Check created_by_admin_id Nullability
    |--------------------------------------------------------------------------
    */

    $columnStmt = $pdo->prepare("
        SELECT
            IS_NULLABLE

        FROM information_schema.COLUMNS

        WHERE TABLE_SCHEMA = DATABASE()
        AND TABLE_NAME = 'offers'
        AND COLUMN_NAME = 'created_by_admin_id'

        LIMIT 1
    ");

    $columnStmt->execute();

    $columnInfo = $columnStmt->fetch();

    $adminIdNullable =
        $columnInfo
        &&
        strtoupper(
            (string) $columnInfo['IS_NULLABLE']
        ) === 'YES';


    /*
    |--------------------------------------------------------------------------
    | Insert Seller Counter
    |--------------------------------------------------------------------------
    */

    if ($adminIdNullable) {

        $insertStmt = $pdo->prepare("
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
                updated_at,
                seen_by_admin
            )
            VALUES
            (
                ?,
                NULL,
                'seller',
                ?,
                ?,
                ?,
                NOW(),
                NULL,
                NOW(),
                NOW(),
                0
            )
        ");

        $insertStmt->execute([
            $currentOffer['submission_id'],
            $counterAmount,
            $feedback,
            $statusValue
        ]);

    } else {

        /*
        |--------------------------------------------------------------------------
        | Database Safety Fallback
        |--------------------------------------------------------------------------
        |
        | Seller counters require created_by_admin_id to accept NULL.
        | We intentionally stop here instead of inserting invalid data.
        |
        */

        throw new RuntimeException(
            'Database configuration error: offers.created_by_admin_id must allow NULL for seller counter-offers.'
        );

    }


    /*
    |--------------------------------------------------------------------------
    | Keep Submission in Negotiation
    |--------------------------------------------------------------------------
    */

    $updateSubmission = $pdo->prepare("
        UPDATE submissions

        SET
            status = 'offer_sent',
            updated_at = NOW()

        WHERE id = ?
    ");

    $updateSubmission->execute([
        $currentOffer['submission_id']
    ]);


    /*
    |--------------------------------------------------------------------------
    | Commit
    |--------------------------------------------------------------------------
    */

    $pdo->commit();


    echo json_encode([
        'success' => true,
        'message' =>
            'Counter-offer sent successfully.'
    ]);

    exit;


} catch (Throwable $e) {

    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }


    /*
    |--------------------------------------------------------------------------
    | Return Useful Error During Development
    |--------------------------------------------------------------------------
    */

    $message = $e->getMessage();


    if (
        str_contains(
            strtolower($message),
            'database configuration error'
        )
    ) {

        http_response_code(500);

        echo json_encode([
            'success' => false,
            'message' => $message
        ]);

        exit;
    }


    http_response_code(500);

    echo json_encode([
        'success' => false,
        'message' =>
            $message !== ''
                ? $message
                : 'Unable to send counter-offer.'
    ]);

    exit;
}