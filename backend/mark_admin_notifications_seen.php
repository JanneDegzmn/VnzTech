<?php

require_once __DIR__ . '/database.php';
require_once __DIR__ . '/session.php';
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/csrf.php';

header('Content-Type: application/json; charset=utf-8');

try {

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
    | CSRF
    |--------------------------------------------------------------------------
    */

    if (
        !verifyCsrfToken(
            $_POST['csrf_token'] ?? null
        )
    ) {
        http_response_code(419);

        echo json_encode([
            'success' => false,
            'message' => 'Your session has expired. Please refresh the page.'
        ]);

        exit;
    }

    /*
    |--------------------------------------------------------------------------
    | NOTIFICATION TYPE
    |--------------------------------------------------------------------------
    */

    $type = strtolower(
        trim(
            (string) ($_POST['type'] ?? '')
        )
    );

    if (
        !in_array(
            $type,
            [
                'submissions',
                'transactions'
            ],
            true
        )
    ) {
        http_response_code(422);

        echo json_encode([
            'success' => false,
            'message' => 'Invalid notification type.'
        ]);

        exit;
    }

    /*
    |--------------------------------------------------------------------------
    | MARK SUBMISSIONS NOTIFICATIONS AS SEEN
    |--------------------------------------------------------------------------
    |
    | This clears BOTH:
    |
    | 1. New seller submissions
    | 2. New seller counter-offers
    |
    | because both use the Admin "Submissions" badge.
    |
    */

    if ($type === 'submissions') {

        $submissionStmt = $pdo->prepare("
            UPDATE submissions
            SET seen_by_admin = 1
            WHERE seen_by_admin = 0
        ");

        $submissionStmt->execute();


        $counterOfferStmt = $pdo->prepare("
            UPDATE offers
            SET seen_by_admin = 1
            WHERE sender_type = 'seller'
            AND seen_by_admin = 0
        ");

        $counterOfferStmt->execute();
    }


    /*
    |--------------------------------------------------------------------------
    | MARK TRANSACTIONS AS SEEN
    |--------------------------------------------------------------------------
    */

    if ($type === 'transactions') {

        $transactionStmt = $pdo->prepare("
            UPDATE transactions
            SET seen_by_admin = 1
            WHERE seen_by_admin = 0
        ");

        $transactionStmt->execute();
    }


    /*
    |--------------------------------------------------------------------------
    | SUCCESS
    |--------------------------------------------------------------------------
    */

    echo json_encode([
        'success' => true
    ]);

    exit;

} catch (Throwable $e) {

    http_response_code(500);

    echo json_encode([
        'success' => false,
        'message' => 'Unable to mark notifications as seen.'
    ]);

    exit;
}