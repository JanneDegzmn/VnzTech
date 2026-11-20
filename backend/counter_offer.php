<?php


require_once __DIR__ . '/site_status.php';
vnzEnforceSiteMaintenance('auto', false, true, 'offers_trades');
require_once __DIR__ . '/database.php';
require_once __DIR__ . '/session.php';
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

if (!verifyCsrfToken($_POST['csrf_token'] ?? null)) {
    http_response_code(419);

    echo json_encode([
        'success' => false,
        'message' => 'Your session has expired. Please refresh the page and try again.'
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
    || !preg_match('/^\d+(?:\.\d{1,2})?$/D', $counterAmountRaw)
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
| Transaction
|--------------------------------------------------------------------------
*/

try {

    $pdo->beginTransaction();

    $submissionStmt = $pdo->prepare("
        SELECT
            id,
            reference_number,
            seller_mobile,
            user_id,
            status
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

    if (
        strtoupper((string) $submission['reference_number']) !== $reference
        || normalizeMobile($submission['seller_mobile']) !== $mobile
    ) {
        throw new RuntimeException(
            'The reference number and mobile number do not match.',
            403
        );
    }

    $sellerId = (int) ($_SESSION['user_id'] ?? 0);
    if (
        !empty($_SESSION['user_logged_in'])
        && $sellerId > 0
        && $submission['user_id'] !== null
        && (int) $submission['user_id'] !== $sellerId
    ) {
        throw new RuntimeException(
            'You are not authorized to counter this submission.',
            403
        );
    }

    if ($submission['status'] !== 'offer_sent') {
        throw new RuntimeException(
            'This submission is no longer available for negotiation.',
            409
        );
    }

    /*
    |--------------------------------------------------------------------------
    | Lock Latest Offer
    |--------------------------------------------------------------------------
    */

    $latestStmt = $pdo->prepare("
        SELECT
            id,
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

    $latestStmt->execute([
        (int) $submissionId
    ]);

    $latestOffer = $latestStmt->fetch();


    if (!$latestOffer) {

        throw new RuntimeException(
            'No active offer was found.',
            409
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
            'This offer is no longer the active negotiation.',
            409
        );

    }


    if (
        strtolower((string) $latestOffer['sender_type']) !== 'admin'
        || !in_array(
            strtolower((string) $latestOffer['status']),
            ['sent', 'offer_sent', 'pending', 'active'],
            true
        )
    ) {

        throw new RuntimeException(
            'The latest offer is no longer available for a counter-offer.',
            409
        );

    }

    $currentOffer = $latestOffer;


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
          AND status = 'offer_sent'
    ");

    $updateSubmission->execute([
        (int) $submissionId
    ]);

    if ($updateSubmission->rowCount() !== 1) {
        throw new RuntimeException(
            'This submission is no longer available for negotiation.',
            409
        );
    }


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


    if (str_contains(strtolower($message), 'database configuration error')) {
        http_response_code(500);

        echo json_encode([
            'success' => false,
            'message' => $message
        ]);

        exit;
    }

    $httpStatus = $e instanceof RuntimeException
        ? (int) $e->getCode()
        : 500;
    if (!in_array($httpStatus, [403, 404, 409], true)) {
        $httpStatus = 500;
    }

    http_response_code($httpStatus);

    echo json_encode([
        'success' => false,
        'message' => $httpStatus === 500 && $message === ''
            ? 'Unable to send counter-offer.'
            : $message
    ]);

    exit;
}