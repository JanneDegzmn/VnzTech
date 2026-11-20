<?php


require_once __DIR__ . '/site_status.php';
vnzEnforceSiteMaintenance('auto', false, true, 'offers_trades');
require_once __DIR__ . '/database.php';
require_once __DIR__ . '/session.php';
require_once __DIR__ . '/csrf.php';
require_once __DIR__ . '/offer_response_authorization.php';

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store, private');

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
        SELECT
            id,
            reference_number,
            seller_mobile,
            user_id,
            assigned_admin_id,
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

    if (!offerResponseSessionIsAuthorized($submission, $_SESSION)) {
        throw new RuntimeException(
            'You are not authorized to respond to this submission.',
            403
        );
    }

    if ($submission['status'] !== 'offer_sent') {
        throw new RuntimeException(
            'This offer can no longer be responded to.',
            409
        );
    }

    $latestOfferStmt = $pdo->prepare("
        SELECT
            id AS offer_id,
            submission_id,
            sender_type,
            offer_amount,
            status,
            created_by_admin_id
        FROM offers
        WHERE submission_id = ?
        ORDER BY id DESC
        LIMIT 1
        FOR UPDATE
    ");
    $latestOfferStmt->execute([(int) $submissionId]);
    $data = $latestOfferStmt->fetch(PDO::FETCH_ASSOC);

    if (!offerResponseOfferBelongsToSubmission(
        $data ?: null,
        $offerId,
        (int) $submissionId
    )) {
        throw new RuntimeException(
            'This offer is no longer the active negotiation.',
            409
        );
    }

    if (!offerResponseOfferIsAvailable($data)) {
        throw new RuntimeException(
            'This offer is not available for a response.',
            409
        );
    }

    if ($response === 'accept') {
        $existingTransaction = $pdo->prepare("
            SELECT id
            FROM transactions
            WHERE submission_id = ?
            LIMIT 1
            FOR UPDATE
        ");
        $existingTransaction->execute([(int) $submissionId]);
        if ($existingTransaction->fetchColumn()) {
            throw new RuntimeException(
                'A transaction already exists for this submission.',
                409
            );
        }
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
        AND sender_type = 'admin'
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
            'This offer is no longer available for a response.',
            409
        );
    }

    /*
    |--------------------------------------------------------------------------
    | Update Submission
    |--------------------------------------------------------------------------
    */

    $submissionStatus = $response === 'accept'
        ? 'accepted'
        : 'declined';

    $submissionUpdate = $pdo->prepare("
        UPDATE submissions
        SET
            status = ?,
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
    $guestChatToken = null;
    $guestChatConversationId = null;
    $guestChatAvailable = false;

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

        if (
            $submission['user_id'] === null
            && empty($_SESSION['user_logged_in'])
            && empty($_SESSION['admin_id'])
        ) {
            $pdo->exec('SAVEPOINT guest_chat_provisioning');
            try {
                $responsibleAdminId = (int) (
                    $data['created_by_admin_id']
                    ?: ($submission['assigned_admin_id'] ?? 0)
                );
                $adminStmt = $pdo->prepare("
                    SELECT a.id
                    FROM admins a
                    INNER JOIN role_permissions rp
                        ON rp.role_id = a.role_id
                    INNER JOIN permissions p
                        ON p.id = rp.permission_id
                       AND p.name = 'view_submissions'
                    WHERE a.id = ?
                      AND a.is_active = 1
                    LIMIT 1
                ");
                $adminStmt->execute([$responsibleAdminId]);
                $chatAdminId = (int) ($adminStmt->fetchColumn() ?: 0);

                if ($chatAdminId > 0) {
                    $guestChatToken = rtrim(
                        strtr(base64_encode(random_bytes(32)), '+/', '-_'),
                        '='
                    );
                    $guestChatIdentityStmt = $pdo->prepare("
                        INSERT INTO guest_chat_identities (
                            submission_id,
                            token_hash,
                            expires_at
                        )
                        VALUES (?, ?, DATE_ADD(NOW(), INTERVAL 365 DAY))
                    ");
                    $guestChatIdentityStmt->execute([
                        (int) $submissionId,
                        hash('sha256', $guestChatToken)
                    ]);
                    $guestChatIdentityId = (int) $pdo->lastInsertId();

                    $conversationStmt = $pdo->prepare("
                        INSERT INTO conversations (type, submission_id)
                        VALUES ('private', ?)
                        ON DUPLICATE KEY UPDATE
                            id = LAST_INSERT_ID(id),
                            updated_at = updated_at
                    ");
                    $conversationStmt->execute([(int) $submissionId]);
                    $guestChatConversationId = (int) $pdo->lastInsertId();

                    $conversationCheck = $pdo->prepare("
                        SELECT id
                        FROM conversations
                        WHERE id = ?
                          AND type = 'private'
                          AND submission_id = ?
                          AND transaction_id IS NULL
                        LIMIT 1
                    ");
                    $conversationCheck->execute([
                        $guestChatConversationId,
                        (int) $submissionId
                    ]);
                    if (!$conversationCheck->fetchColumn()) {
                        throw new RuntimeException(
                            'Unable to create the guest chat conversation.'
                        );
                    }

                    $participantStmt = $pdo->prepare("
                        INSERT INTO conversation_participants (
                            conversation_id,
                            actor_type,
                            actor_id
                        )
                        VALUES (?, ?, ?), (?, 'admin', ?)
                    ");
                    $participantStmt->execute([
                        $guestChatConversationId,
                        'guest',
                        $guestChatIdentityId,
                        $guestChatConversationId,
                        $chatAdminId
                    ]);
                } else {
                    error_log(
                        'Guest chat was not provisioned because no eligible Admin was associated with the accepted offer.'
                    );
                }
                $pdo->exec('RELEASE SAVEPOINT guest_chat_provisioning');
            } catch (Throwable $chatError) {
                $pdo->exec('ROLLBACK TO SAVEPOINT guest_chat_provisioning');
                $pdo->exec('RELEASE SAVEPOINT guest_chat_provisioning');
                $guestChatToken = null;
                $guestChatConversationId = null;
                error_log(
                    'Guest chat provisioning failed; offer acceptance will continue without chat. Error type: '
                    . get_class($chatError)
                );
            }
        }
    }

    /*
    |--------------------------------------------------------------------------
    | Commit
    |--------------------------------------------------------------------------
    */

    $pdo->commit();

    if ($guestChatToken !== null) {
        $isSecure = isset($_SERVER['HTTPS'])
            && $_SERVER['HTTPS'] !== ''
            && strtolower((string) $_SERVER['HTTPS']) !== 'off';
        $cookieIssued = setcookie('vnz_guest_chat', $guestChatToken, [
            'expires' => time() + 365 * 24 * 60 * 60,
            'path' => '/VNZ-TECH/',
            'secure' => $isSecure,
            'httponly' => true,
            'samesite' => 'Lax'
        ]);
        if ($cookieIssued) {
            $guestChatAvailable = true;
        } else {
            error_log('Guest chat capability cookie could not be issued.');
            try {
                $pdo->prepare("
                    DELETE FROM conversations
                    WHERE id = ?
                      AND submission_id = ?
                ")->execute([
                    $guestChatConversationId,
                    (int) $submissionId
                ]);
                $pdo->prepare("
                    DELETE FROM guest_chat_identities
                    WHERE submission_id = ?
                ")->execute([(int) $submissionId]);
            } catch (Throwable $cleanupError) {
                error_log(
                    'Failed to clean up guest chat provisioning after cookie issuance failed.'
                );
            }
            $guestChatConversationId = null;
        }
    }

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
            'offer_amount' => (float) $data['offer_amount'],
            'guest_chat_available' => $guestChatAvailable
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
    if (!in_array($httpStatus, [403, 404, 409], true)) {
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