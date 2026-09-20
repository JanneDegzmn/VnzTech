<?php

require_once __DIR__ . '/database.php';
require_once __DIR__ . '/session.php';
require_once __DIR__ . '/auth.php';

header('Content-Type: application/json; charset=utf-8');

try {

    if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
        http_response_code(405);

        echo json_encode([
            'success' => false,
            'message' => 'Method not allowed.'
        ]);

        exit;
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
        http_response_code(401);

        echo json_encode([
            'success' => false,
            'message' => 'Admin session has expired.'
        ]);

        exit;
    }

    /*
    |--------------------------------------------------------------------------
    | NEW SUBMISSIONS
    |--------------------------------------------------------------------------
    */

    $submissionStmt = $pdo->query("
        SELECT COUNT(*)
        FROM submissions
        WHERE seen_by_admin = 0
    ");

    $newSubmissionCount =
        (int) $submissionStmt->fetchColumn();


    /*
    |--------------------------------------------------------------------------
    | NEW SELLER COUNTER-OFFERS
    |--------------------------------------------------------------------------
    |
    | counter_offer.php sets seen_by_admin = 0 when a seller
    | creates a new counter-offer.
    |
    */

    $counterOfferStmt = $pdo->query("
        SELECT COUNT(*)
        FROM offers
        WHERE sender_type = 'seller'
        AND seen_by_admin = 0
    ");

    $newCounterOfferCount =
        (int) $counterOfferStmt->fetchColumn();


    /*
    |--------------------------------------------------------------------------
    | SUBMISSIONS BADGE TOTAL
    |--------------------------------------------------------------------------
    |
    | The Submissions badge represents both:
    |
    |   1. New seller submissions
    |   2. New seller counter-offers
    |
    */

    $submissionsBadgeCount =
        $newSubmissionCount +
        $newCounterOfferCount;


    /*
    |--------------------------------------------------------------------------
    | NEW TRANSACTIONS
    |--------------------------------------------------------------------------
    */

    $transactionStmt = $pdo->query("
        SELECT COUNT(*)
        FROM transactions
        WHERE seen_by_admin = 0
    ");

    $newTransactionCount =
        (int) $transactionStmt->fetchColumn();


    /*
    |--------------------------------------------------------------------------
    | RESPONSE
    |--------------------------------------------------------------------------
    */

    echo json_encode([
        'success' => true,

        'submissions_count' =>
            $submissionsBadgeCount,

        'new_submissions_count' =>
            $newSubmissionCount,

        'new_counter_offers_count' =>
            $newCounterOfferCount,

        'transactions_count' =>
            $newTransactionCount
    ]);

    exit;

} catch (Throwable $e) {

    http_response_code(500);

    echo json_encode([
        'success' => false,
        'message' => 'Unable to load admin notification counts.'
    ]);

    exit;
}