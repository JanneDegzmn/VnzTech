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
    return preg_replace(
        '/\D+/',
        '',
        (string) $value
    );
}


$reference =
    normalizeReference(
        $_POST['reference'] ?? ''
    );


$mobile =
    normalizeMobile(
        $_POST['mobile'] ?? ''
    );


if ($reference === '' || $mobile === '') {

    http_response_code(422);

    echo json_encode([
        'success' => false,
        'message' => 'Please enter your reference number and mobile number.'
    ]);

    exit;
}


/*
|--------------------------------------------------------------------------
| Find submission by reference
|--------------------------------------------------------------------------
*/

$stmt = $pdo->prepare("
    SELECT
        id,
        reference_number,
        seller_name,
        seller_mobile,
        seller_address,
        selling_method,
        status,
        seller_notes,
        admin_feedback,
        created_at,
        updated_at
    FROM submissions
    WHERE UPPER(reference_number) = ?
    LIMIT 1
");

$stmt->execute([
    $reference
]);

$submission =
    $stmt->fetch();


if (!$submission) {

    http_response_code(404);

    echo json_encode([
        'success' => false,
        'message' => 'Submission not found. Please check your reference number.'
    ]);

    exit;
}


/*
|--------------------------------------------------------------------------
| Verify Mobile
|--------------------------------------------------------------------------
*/

$storedMobile =
    normalizeMobile(
        $submission['seller_mobile']
    );


if (
    $storedMobile !== $mobile
) {

    http_response_code(403);

    echo json_encode([
        'success' => false,
        'message' => 'The reference number and mobile number do not match.'
    ]);

    exit;
}


/*
|--------------------------------------------------------------------------
| Items
|--------------------------------------------------------------------------
*/

$itemStmt = $pdo->prepare("
    SELECT
        id,
        item_type,
        brand,
        model,
        storage,
        issue,
        issue_details,
        damage_level,
        board_code,
        quantity,
        seller_asking_price,
        additional_information
    FROM submission_items
    WHERE submission_id = ?
    ORDER BY id ASC
");

$itemStmt->execute([
    $submission['id']
]);

$items =
    $itemStmt->fetchAll();


/*
|--------------------------------------------------------------------------
| Latest Offer
|--------------------------------------------------------------------------
*/

$offerStmt = $pdo->prepare("
    SELECT
        id,
        sender_type,
        offer_amount,
        feedback,
        status,
        sent_at,
        responded_at,
        created_at
    FROM offers
    WHERE submission_id = ?
    ORDER BY id DESC
    LIMIT 1
");

$offerStmt->execute([
    $submission['id']
]);

$offer =
    $offerStmt->fetch();


/*
|--------------------------------------------------------------------------
| Format Date
|--------------------------------------------------------------------------
*/

$submission['created_at'] =
    date(
        'M d, Y · h:i A',
        strtotime(
            $submission['created_at']
        )
    );


echo json_encode([
    'success' => true,
    'submission' => [
        'id' =>
            (int) $submission['id'],

        'reference_number' =>
            $submission['reference_number'],

        'seller_name' =>
            $submission['seller_name'],

        'seller_mobile' =>
            $submission['seller_mobile'],

        'selling_method' =>
            $submission['selling_method'],

        'status' =>
            $submission['status'],

        'created_at' =>
            $submission['created_at'],

        'items' =>
            $items,

        'offer' =>
            $offer ?: null
    ]
]);