<?php

require_once __DIR__ . '/database.php';
require_once __DIR__ . '/session.php';
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/csrf.php';

header('Content-Type: application/json; charset=utf-8');

ob_start();

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

    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        jsonResponse(
            false,
            'Method not allowed.',
            405
        );
    }

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

    if (!hasPermission('manage_offers')) {
        jsonResponse(
            false,
            'You do not have permission to manage submissions.',
            403
        );
    }

    $currentAdmin = getCurrentAdmin();

    if (!$currentAdmin) {
        jsonResponse(
            false,
            'Admin session is invalid. Please log in again.',
            401
        );
    }

    $adminId = (int) $currentAdmin['id'];

    $submissionId = isset($_POST['submission_id'])
        ? (int) $_POST['submission_id']
        : 0;

    $response = strtolower(
        trim(
            (string) ($_POST['response'] ?? '')
        )
    );

    if ($submissionId <= 0) {
        jsonResponse(
            false,
            'Invalid submission ID.',
            422
        );
    }

    if (!in_array($response, ['accept', 'decline'], true)) {
        jsonResponse(
            false,
            'Invalid response.',
            422
        );
    }

    $pdo->beginTransaction();

    /*
     * Lock submission so two admin actions cannot
     * process the same submission simultaneously.
     */
    $submissionStmt = $pdo->prepare("
        SELECT
            id,
            status
        FROM submissions
        WHERE id = ?
        LIMIT 1
        FOR UPDATE
    ");

    $submissionStmt->execute([
        $submissionId
    ]);

    $submission = $submissionStmt->fetch();

    if (!$submission) {
        throw new RuntimeException(
            'Submission not found.',
            404
        );
    }

    /*
     * Direct Accept / Decline is only for submissions
     * that have not entered an offer negotiation.
     */
    $allowedStatuses = [
        'new_submission',
        'under_review'
    ];

    if (
        !in_array(
            $submission['status'],
            $allowedStatuses,
            true
        )
    ) {
        throw new RuntimeException(
            'This submission can no longer be directly accepted or declined.',
            409
        );
    }

    /*
     * DECLINE
     */
    if ($response === 'decline') {

        $updateSubmission = $pdo->prepare("
            UPDATE submissions
            SET
                status = 'declined',
                updated_at = NOW()
            WHERE id = ?
        ");

        $updateSubmission->execute([
            $submissionId
        ]);

        $pdo->commit();

        jsonResponse(
            true,
            'Submission declined successfully.'
        );
    }

    /*
     * ACCEPT
     *
     * Direct Admin Accept uses the seller's asking price.
     * No fake offer row is created.
     */

    $askingPriceStmt = $pdo->prepare("
        SELECT
            COALESCE(
                SUM(seller_asking_price),
                0
            )
        FROM submission_items
        WHERE submission_id = ?
    ");

    $askingPriceStmt->execute([
        $submissionId
    ]);

    $totalAskingPrice =
        (float) $askingPriceStmt->fetchColumn();

    if ($totalAskingPrice <= 0) {
        throw new RuntimeException(
            'Cannot accept this submission because the seller asking price is invalid.',
            422
        );
    }

    /*
     * Prevent duplicate transaction for this submission.
     */
    $transactionCheck = $pdo->prepare("
        SELECT
            id,
            transaction_reference
        FROM transactions
        WHERE submission_id = ?
        LIMIT 1
        FOR UPDATE
    ");

    $transactionCheck->execute([
        $submissionId
    ]);

    $existingTransaction =
        $transactionCheck->fetch();

    if ($existingTransaction) {
        throw new RuntimeException(
            'A transaction already exists for this submission.',
            409
        );
    }

    $transactionReference =
        generateTransactionReference($pdo);

    /*
     * Direct Accept:
     *
     * offer_id = NULL
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
            updated_at
        )
        VALUES (
            ?,
            NULL,
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
        $submissionId,
        $transactionReference,
        $totalAskingPrice,
        $adminId
    ]);

    /*
     * Mark submission accepted.
     */
    $updateSubmission = $pdo->prepare("
        UPDATE submissions
        SET
            status = 'accepted',
            updated_at = NOW()
        WHERE id = ?
    ");

    $updateSubmission->execute([
        $submissionId
    ]);

    $pdo->commit();

    jsonResponse(
        true,
        'Submission accepted successfully. Transaction created.',
        200,
        [
            'transaction_reference' =>
                $transactionReference,
            'final_amount' =>
                $totalAskingPrice
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

    $httpCode = (int) $e->getCode();

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
        'Unable to process submission: '
        . $e->getMessage(),
        500
    );
}