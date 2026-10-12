<?php

require_once __DIR__ . '/database.php';
require_once __DIR__ . '/session.php';
require_once __DIR__ . '/csrf.php';
require_once __DIR__ . '/chat_service.php';

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

function trackGuestChatRecoveryIsRateLimited(int $submissionId): bool
{
    $ipAddress = $_SERVER['REMOTE_ADDR'] ?? '';
    $key = hash(
        'sha256',
        $submissionId . '|' . session_id() . '|' . $ipAddress
    );
    $attempts = $_SESSION['guest_chat_recovery_attempts'] ?? [];
    $lastRecoveryAt = is_array($attempts)
        ? (int) ($attempts[$key] ?? 0)
        : 0;

    return $lastRecoveryAt > 0 && time() - $lastRecoveryAt < 60;
}

function trackGuestChatRecoveryWasAttempted(int $submissionId): void
{
    $now = time();
    $ipAddress = $_SERVER['REMOTE_ADDR'] ?? '';
    $key = hash(
        'sha256',
        $submissionId . '|' . session_id() . '|' . $ipAddress
    );
    $attempts = $_SESSION['guest_chat_recovery_attempts'] ?? [];
    if (!is_array($attempts)) {
        $attempts = [];
    }

    foreach ($attempts as $attemptKey => $attemptAt) {
        if (!is_int($attemptAt) || $now - $attemptAt >= 3600) {
            unset($attempts[$attemptKey]);
        }
    }
    if (count($attempts) >= 50) {
        array_shift($attempts);
    }
    $attempts[$key] = $now;
    $_SESSION['guest_chat_recovery_attempts'] = $attempts;
}

function trackRecoverGuestChat(
    int $submissionId,
    string $reference,
    string $mobile,
    string $requestToken
): array {
    global $pdo;

    if (!chatGuestSubmissionSchemaAvailable()) {
        throw new RuntimeException('Guest chat schema is unavailable.');
    }

    $pdo->beginTransaction();
    try {
        $submissionStmt = $pdo->prepare("
            SELECT id, reference_number, seller_mobile, user_id, status
            FROM submissions
            WHERE id = ?
            LIMIT 1
            FOR UPDATE
        ");
        $submissionStmt->execute([$submissionId]);
        $submission = $submissionStmt->fetch(PDO::FETCH_ASSOC);
        if (
            !$submission
            || strtoupper((string) $submission['reference_number']) !== $reference
            || normalizeMobile($submission['seller_mobile']) !== $mobile
            || $submission['user_id'] !== null
            || $submission['status'] !== 'accepted'
        ) {
            throw new RuntimeException('Guest submission is no longer eligible for chat.');
        }

        $transactionStmt = $pdo->prepare("
            SELECT
                t.id,
                t.processed_by_admin_id,
                o.created_by_admin_id AS offer_admin_id,
                s.assigned_admin_id
            FROM transactions t
            INNER JOIN submissions s
                ON s.id = t.submission_id
            LEFT JOIN offers o
                ON o.id = t.offer_id
            WHERE t.submission_id = ?
            LIMIT 1
            FOR UPDATE
        ");
        $transactionStmt->execute([$submissionId]);
        $transaction = $transactionStmt->fetch(PDO::FETCH_ASSOC);
        if (!$transaction) {
            throw new RuntimeException('Accepted guest submission has no transaction.');
        }

        $responsibleAdminIds = [];
        foreach ([
            $transaction['processed_by_admin_id'],
            $transaction['offer_admin_id'],
            $transaction['assigned_admin_id']
        ] as $adminId) {
            if ($adminId !== null) {
                $responsibleAdminIds[(int) $adminId] = true;
            }
        }
        if (count($responsibleAdminIds) !== 1) {
            throw new RuntimeException('Transaction Admin attribution is unavailable or ambiguous.');
        }
        $adminId = (int) array_key_first($responsibleAdminIds);
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
        $adminStmt->execute([$adminId]);
        if (!$adminStmt->fetchColumn()) {
            throw new RuntimeException('Responsible Admin is not eligible for guest chat.');
        }

        $identityStmt = $pdo->prepare("
            SELECT
                id,
                token_hash,
                expires_at > NOW() AS is_active,
                revoked_at
            FROM guest_chat_identities
            WHERE submission_id = ?
            LIMIT 1
            FOR UPDATE
        ");
        $identityStmt->execute([$submissionId]);
        $identity = $identityStmt->fetch(PDO::FETCH_ASSOC);
        if ($identity && $identity['revoked_at'] !== null) {
            $pdo->commit();
            return [
                'conversation_id' => null,
                'token' => null,
                'state_changed' => false
            ];
        }

        $requestTokenHash = preg_match(
            '/\A[A-Za-z0-9_-]{43}\z/',
            $requestToken
        ) === 1
            ? hash('sha256', $requestToken)
            : '';
        $tokenMatchesIdentity = $identity
            && (bool) $identity['is_active']
            && $requestTokenHash !== ''
            && hash_equals((string) $identity['token_hash'], $requestTokenHash);

        if ($tokenMatchesIdentity) {
            $existingConversationId = chatFindGuestSubmissionConversation(
                $submissionId,
                $adminId,
                (int) $identity['id']
            );
            if ($existingConversationId === null) {
                throw new RuntimeException(
                    'The existing guest capability has no valid linked conversation.'
                );
            }

            $pdo->commit();
            return [
                'conversation_id' => $existingConversationId,
                'token' => null,
                'state_changed' => false
            ];
        }

        $newToken = rtrim(
            strtr(base64_encode(random_bytes(32)), '+/', '-_'),
            '='
        );
        if ($identity) {
            $rotateStmt = $pdo->prepare("
                UPDATE guest_chat_identities
                SET token_hash = ?,
                    expires_at = DATE_ADD(NOW(), INTERVAL 365 DAY)
                WHERE id = ?
                  AND revoked_at IS NULL
            ");
            $rotateStmt->execute([
                hash('sha256', $newToken),
                (int) $identity['id']
            ]);
            if ($rotateStmt->rowCount() !== 1) {
                throw new RuntimeException('Guest chat capability could not be recovered.');
            }
        } else {
            $insertIdentityStmt = $pdo->prepare("
                INSERT INTO guest_chat_identities (
                    submission_id,
                    token_hash,
                    expires_at
                )
                VALUES (?, ?, DATE_ADD(NOW(), INTERVAL 365 DAY))
            ");
            $insertIdentityStmt->execute([
                $submissionId,
                hash('sha256', $newToken)
            ]);
            $identity = [
                'id' => (int) $pdo->lastInsertId()
            ];
        }

        $conversationInsertStmt = $pdo->prepare("
            INSERT INTO conversations (type, submission_id)
            VALUES ('private', ?)
            ON DUPLICATE KEY UPDATE
                id = LAST_INSERT_ID(id)
        ");
        $conversationInsertStmt->execute([$submissionId]);
        $conversationId = (int) $pdo->lastInsertId();

        $conversationStmt = $pdo->prepare("
            SELECT id
            FROM conversations
            WHERE id = ?
              AND type = 'private'
              AND transaction_id IS NULL
              AND submission_id = ?
            LIMIT 1
            FOR UPDATE
        ");
        $conversationStmt->execute([$conversationId, $submissionId]);
        if (!$conversationStmt->fetchColumn()) {
            throw new RuntimeException('Submission chat is linked to an incompatible conversation.');
        }

        $participantsStmt = $pdo->prepare("
            SELECT actor_type, actor_id
            FROM conversation_participants
            WHERE conversation_id = ?
            FOR UPDATE
        ");
        $participantsStmt->execute([$conversationId]);
        $participants = $participantsStmt->fetchAll(PDO::FETCH_ASSOC);
        if (count($participants) > 2) {
            throw new RuntimeException('Submission chat has unexpected participants.');
        }
        foreach ($participants as $participant) {
            $expectedActorId = $participant['actor_type'] === 'guest'
                ? (int) $identity['id']
                : ($participant['actor_type'] === 'admin' ? $adminId : 0);
            if ((int) $participant['actor_id'] !== $expectedActorId) {
                throw new RuntimeException('Submission chat participant attribution is inconsistent.');
            }
        }

        $participantInsertStmt = $pdo->prepare("
            INSERT INTO conversation_participants (
                conversation_id,
                actor_type,
                actor_id
            )
            VALUES (?, 'guest', ?), (?, 'admin', ?)
            ON DUPLICATE KEY UPDATE id = id
        ");
        $participantInsertStmt->execute([
            $conversationId,
            (int) $identity['id'],
            $conversationId,
            $adminId
        ]);

        $verifiedConversationId = chatFindGuestSubmissionConversation(
            $submissionId,
            $adminId,
            (int) $identity['id']
        );
        if ($verifiedConversationId !== $conversationId) {
            throw new RuntimeException('Submission chat authorization could not be verified.');
        }

        $pdo->commit();

        return [
            'conversation_id' => $conversationId,
            'token' => $newToken,
            'state_changed' => $newToken !== null
        ];
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        throw $e;
    }
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
        user_id,
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

$guestChatConversationId = null;
$guestChatToken = $_COOKIE['vnz_guest_chat'] ?? '';
if (
    $submission['status'] === 'accepted'
    && $submission['user_id'] === null
    && empty($_SESSION['user_logged_in'])
    && empty($_SESSION['admin_id'])
    && chatGuestSubmissionSchemaAvailable()
) {
    $requestToken = is_string($guestChatToken)
        ? $guestChatToken
        : '';
    $requestTokenHash = preg_match('/\A[A-Za-z0-9_-]{43}\z/', $requestToken) === 1
        ? hash('sha256', $requestToken)
        : '';
    $knownGuestIdentityStmt = $pdo->prepare("
        SELECT id
        FROM guest_chat_identities
        WHERE submission_id = ?
          AND token_hash = ?
          AND revoked_at IS NULL
          AND expires_at > NOW()
        LIMIT 1
    ");
    $knownGuestIdentityStmt->execute([
        (int) $submission['id'],
        $requestTokenHash
    ]);
    $hasCurrentCapability = (bool) $knownGuestIdentityStmt->fetchColumn();
    $mayRecoverCapability = !$hasCurrentCapability;
    $rateLimited = $mayRecoverCapability
        && trackGuestChatRecoveryIsRateLimited((int) $submission['id']);

    if (!$rateLimited) {
        try {
            $recovery = trackRecoverGuestChat(
                (int) $submission['id'],
                strtoupper((string) $submission['reference_number']),
                $storedMobile,
                $requestToken
            );
            $guestChatConversationId = $recovery['conversation_id'];
            if ($recovery['token'] !== null) {
                $isSecure = isset($_SERVER['HTTPS'])
                    && $_SERVER['HTTPS'] !== ''
                    && strtolower((string) $_SERVER['HTTPS']) !== 'off';
                $cookieIssued = setcookie('vnz_guest_chat', $recovery['token'], [
                    'expires' => time() + 365 * 24 * 60 * 60,
                    'path' => '/VNZ-TECH/',
                    'secure' => $isSecure,
                    'httponly' => true,
                    'samesite' => 'Lax'
                ]);
                if (!$cookieIssued) {
                    $guestChatConversationId = null;
                    error_log('Guest chat recovery capability cookie could not be issued.');
                }
                if ($recovery['state_changed']) {
                    trackGuestChatRecoveryWasAttempted((int) $submission['id']);
                }
            }
        } catch (Throwable $chatRecoveryError) {
            $guestChatConversationId = null;
            error_log(
                'Guest chat recovery failed for submission '
                . (int) $submission['id']
                . '; error type: '
                . get_class($chatRecoveryError)
            );
        }
    }
}

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
            $offer ?: null,

        'guest_chat_available' =>
            $guestChatConversationId !== null,

        'guest_chat_conversation_id' =>
            $guestChatConversationId
    ]
]);