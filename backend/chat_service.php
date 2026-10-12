<?php

require_once __DIR__ . '/database.php';
require_once __DIR__ . '/session.php';
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/csrf.php';
require_once __DIR__ . '/guest_submission_link.php';

class ChatHttpException extends RuntimeException
{
    public $httpStatus;

    public function __construct(string $message, int $httpStatus)
    {
        parent::__construct($message);
        $this->httpStatus = $httpStatus;
    }
}

function chatParticipantDisplayNameForViewer(
    array $viewer,
    string $participantType,
    string $displayName
): string {
    if (
        in_array($viewer['actor_type'], ['seller', 'guest'], true)
        && $participantType === 'admin'
    ) {
        return 'Admin';
    }

    $displayName = trim($displayName);
    if ($displayName !== '') {
        return $displayName;
    }

    return match ($participantType) {
        'seller' => 'Seller',
        'guest' => 'Guest Seller',
        default => 'Admin'
    };
}

function chatGetConversationPresence(int $conversationId, array $actor): array
{
    global $pdo;

    $membershipStmt = $pdo->prepare("
        SELECT type
        FROM conversations
        WHERE id = ?
          AND EXISTS (
              SELECT 1
              FROM conversation_participants current_participant
              WHERE current_participant.conversation_id = conversations.id
                AND current_participant.actor_type = ?
                AND current_participant.actor_id = ?
          )
        LIMIT 1
    ");
    $membershipStmt->execute([
        $conversationId,
        $actor['actor_type'],
        $actor['actor_id']
    ]);
    $conversationType = $membershipStmt->fetchColumn();
    if ($conversationType === false) {
        throw new ChatHttpException('Unauthorized.', 403);
    }

    if ($conversationType === 'global') {
        $onlineStmt = $pdo->prepare("
            SELECT COUNT(DISTINCT CONCAT(p.actor_type, ':', p.actor_id))
            FROM conversation_participants p
            INNER JOIN chat_presence_sessions presence
                ON presence.actor_type = p.actor_type
               AND presence.actor_id = p.actor_id
               AND presence.ended_at IS NULL
               AND presence.heartbeat_at >= DATE_SUB(NOW(), INTERVAL 60 SECOND)
            WHERE p.conversation_id = ?
              AND NOT (p.actor_type = ? AND p.actor_id = ?)
        ");
        $onlineStmt->execute([
            $conversationId,
            $actor['actor_type'],
            $actor['actor_id']
        ]);
        return ['online_count' => (int) $onlineStmt->fetchColumn()];
    }

    $participantStmt = $pdo->prepare("
        SELECT
            p.actor_type,
            p.actor_id,
            CASE
                WHEN p.actor_type = 'seller' THEN COALESCE(
                    NULLIF(TRIM(u.full_name), ''),
                    'Seller'
                )
                WHEN p.actor_type = 'admin' THEN COALESCE(
                    NULLIF(TRIM(CONCAT(a.first_name, ' ', a.last_name)), ''),
                    'Admin'
                )
                ELSE 'Guest Seller'
            END AS display_name,
            MAX(COALESCE(presence.ended_at, presence.heartbeat_at)) AS last_seen_at,
            MAX(
                CASE
                    WHEN presence.ended_at IS NULL
                     AND presence.heartbeat_at >= DATE_SUB(NOW(), INTERVAL 60 SECOND)
                    THEN 1
                    ELSE 0
                END
            ) AS is_online
        FROM conversation_participants p
        LEFT JOIN users u
            ON p.actor_type = 'seller'
           AND u.id = p.actor_id
        LEFT JOIN admins a
            ON p.actor_type = 'admin'
           AND a.id = p.actor_id
        LEFT JOIN chat_presence_sessions presence
            ON presence.actor_type = p.actor_type
           AND presence.actor_id = p.actor_id
        WHERE p.conversation_id = ?
          AND NOT (p.actor_type = ? AND p.actor_id = ?)
        GROUP BY p.actor_type, p.actor_id, display_name
    ");
    $participantStmt->execute([
        $conversationId,
        $actor['actor_type'],
        $actor['actor_id']
    ]);

    $participants = [];
    foreach ($participantStmt->fetchAll(PDO::FETCH_ASSOC) as $participant) {
        $lastSeen = $participant['last_seen_at'] ?: null;
        $participants[] = [
            'display_name' => chatParticipantDisplayNameForViewer(
                $actor,
                (string) $participant['actor_type'],
                (string) $participant['display_name']
            ),
            'is_online' => (bool) $participant['is_online'],
            'last_seen_at' => $lastSeen
        ];
    }

    return ['participants' => $participants];
}

function chatJsonResponse(
    bool $success,
    string $message,
    int $httpStatus = 200,
    array $data = []
): void {
    if (ob_get_length()) {
        ob_clean();
    }

    http_response_code($httpStatus);
    header('Content-Type: application/json; charset=utf-8');

    echo json_encode(
        [
            'success' => $success,
            'message' => $message,
            'data' => (object) $data
        ],
        JSON_UNESCAPED_UNICODE
    );

    exit;
}

function chatRequireMethod(string $method): void
{
    if (($_SERVER['REQUEST_METHOD'] ?? '') !== $method) {
        header('Allow: ' . $method);
        throw new ChatHttpException('Method not allowed.', 405);
    }
}

function chatRequireCsrf(): void
{
    if (!verifyCsrfToken($_POST['csrf_token'] ?? null)) {
        throw new ChatHttpException(
            'Your session has expired. Please refresh and try again.',
            403
        );
    }
}

function chatRequestAction(): string
{
    $requestMethod = $_SERVER['REQUEST_METHOD'] ?? '';
    $value = $requestMethod === 'GET'
        ? ($_GET['action'] ?? '')
        : ($_POST['action'] ?? '');

    if (!is_string($value)) {
        throw new ChatHttpException('Invalid action.', 422);
    }

    return strtolower(trim($value));
}

function chatPositiveInteger($value, string $fieldName): int
{
    if (
        (!is_int($value) && !is_string($value))
        || filter_var($value, FILTER_VALIDATE_INT) === false
        || (int) $value <= 0
    ) {
        throw new ChatHttpException('Invalid ' . $fieldName . '.', 422);
    }

    return (int) $value;
}

function chatGuestSubmissionSchemaAvailable(): bool
{
    static $isAvailable = null;
    if ($isAvailable !== null) {
        return $isAvailable;
    }

    global $pdo;
    $stmt = $pdo->prepare("
        SELECT COUNT(*)
        FROM information_schema.TABLES
        WHERE TABLE_SCHEMA = DATABASE()
          AND TABLE_NAME = 'guest_chat_identities'
    ");
    $stmt->execute();
    if ((int) $stmt->fetchColumn() !== 1) {
        $isAvailable = false;
        return $isAvailable;
    }

    $columnsStmt = $pdo->prepare("
        SELECT COUNT(*)
        FROM information_schema.COLUMNS
        WHERE TABLE_SCHEMA = DATABASE()
          AND (
              (TABLE_NAME = 'conversations' AND COLUMN_NAME = 'submission_id')
              OR (
                  TABLE_NAME = 'conversation_participants'
                  AND COLUMN_NAME = 'actor_type'
                  AND COLUMN_TYPE LIKE ?
              )
              OR (
                  TABLE_NAME = 'messages'
                  AND COLUMN_NAME = 'sender_type'
                  AND COLUMN_TYPE LIKE ?
              )
              OR (
                  TABLE_NAME = 'message_reactions'
                  AND COLUMN_NAME = 'actor_type'
                  AND COLUMN_TYPE LIKE ?
              )
              OR (
                  TABLE_NAME = 'message_deliveries'
                  AND COLUMN_NAME = 'recipient_type'
                  AND COLUMN_TYPE LIKE ?
              )
              OR (
                  TABLE_NAME = 'chat_presence_sessions'
                  AND COLUMN_NAME = 'actor_type'
                  AND COLUMN_TYPE LIKE ?
              )
          )
    ");
    $columnsStmt->execute(array_fill(0, 5, "%'guest'%"));
    $isAvailable = (int) $columnsStmt->fetchColumn() === 6;

    return $isAvailable;
}

function chatGetCurrentActor(): array
{
    $isSellerSession =
        isset($_SESSION['user_logged_in'])
        && $_SESSION['user_logged_in'] === true
        && !empty($_SESSION['user_id']);

    $isAdminSession =
        !empty($_SESSION['admin_id']);

    if ($isSellerSession && $isAdminSession) {
        throw new ChatHttpException(
            'This session has more than one active identity. Please sign in again.',
            409
        );
    }

    global $pdo;

    if (!$isSellerSession && !$isAdminSession) {
        $token = $_COOKIE['vnz_guest_chat'] ?? '';
        if (
            !is_string($token)
            || preg_match('/\A[A-Za-z0-9_-]{43}\z/', $token) !== 1
            || !chatGuestSubmissionSchemaAvailable()
        ) {
            throw new ChatHttpException('Authentication required.', 401);
        }

        $guestStmt = $pdo->prepare("
            SELECT id
            FROM guest_chat_identities
            WHERE token_hash = ?
              AND revoked_at IS NULL
              AND expires_at > NOW()
            LIMIT 1
        ");
        $guestStmt->execute([hash('sha256', $token)]);
        $guestId = $guestStmt->fetchColumn();
        if (!$guestId) {
            throw new ChatHttpException('Authentication required.', 401);
        }

        return [
            'actor_type' => 'guest',
            'actor_id' => (int) $guestId,
            'display_name' => 'Guest Seller',
            'profile_picture' => ''
        ];
    }

    if ($isSellerSession) {
        $sellerId = filter_var(
            $_SESSION['user_id'],
            FILTER_VALIDATE_INT
        );

        if ($sellerId === false || $sellerId <= 0) {
            throw new ChatHttpException('Authentication required.', 401);
        }

        $stmt = $pdo->prepare("
            SELECT id, full_name, profile_picture
            FROM users
            WHERE id = ?
              AND is_active = 1
            LIMIT 1
        ");
        $stmt->execute([$sellerId]);
        $seller = $stmt->fetch();

        if (!$seller) {
            throw new ChatHttpException('Authentication required.', 401);
        }

        $profilePicture = (string) ($seller['profile_picture'] ?? '');
        return [
            'actor_type' => 'seller',
            'actor_id' => (int) $sellerId,
            'display_name' => trim((string) ($seller['full_name'] ?? '')) ?: 'Seller',
            'profile_picture' => preg_match(
                '/\Auploads\/profile_pictures\/[A-Za-z0-9][A-Za-z0-9._-]*\z/',
                $profilePicture
            ) === 1
                ? $profilePicture
                : ''
        ];
    }

    $admin = getCurrentAdmin();

    if (!$admin) {
        throw new ChatHttpException('Authentication required.', 401);
    }

    if (!hasPermission('view_submissions')) {
        throw new ChatHttpException('Unauthorized.', 403);
    }

    return [
        'actor_type' => 'admin',
        'actor_id' => (int) $admin['id'],
        'display_name' => 'Admin',
        'profile_picture' => ''
    ];
}

function chatFindGuestSubmissionConversation(
    int $submissionId,
    int $adminId,
    ?int $guestIdentityId = null
): ?int {
    global $pdo;

    if (!chatGuestSubmissionSchemaAvailable()) {
        throw new ChatHttpException(
            'Guest chat is unavailable because its required database schema is missing.',
            503
        );
    }

    $guestActorCondition = '';
    $params = [];
    if ($guestIdentityId !== null) {
        $guestActorCondition = 'AND guest_participant.actor_id = ?';
        $params[] = $guestIdentityId;

        $identityStmt = $pdo->prepare("
            SELECT id
            FROM guest_chat_identities
            WHERE id = ?
              AND submission_id = ?
              AND revoked_at IS NULL
              AND expires_at > NOW()
            LIMIT 1
        ");
        $identityStmt->execute([$guestIdentityId, $submissionId]);
        if (!$identityStmt->fetchColumn()) {
            throw new ChatHttpException(
                'Guest chat authorization is invalid or expired.',
                403
            );
        }
    }

    $stmt = $pdo->prepare("
        SELECT c.id
        FROM conversations c
        INNER JOIN conversation_participants guest_participant
            ON guest_participant.conversation_id = c.id
           AND guest_participant.actor_type = 'guest'
        INNER JOIN guest_chat_identities guest_identity
            ON guest_identity.id = guest_participant.actor_id
           AND guest_identity.submission_id = c.submission_id
        INNER JOIN conversation_participants admin_participant
            ON admin_participant.conversation_id = c.id
           AND admin_participant.actor_type = 'admin'
           AND admin_participant.actor_id = ?
        WHERE c.type = 'private'
          AND c.transaction_id IS NULL
          AND c.submission_id = ?
          $guestActorCondition
          AND (
              SELECT COUNT(*)
              FROM conversation_participants all_participants
              WHERE all_participants.conversation_id = c.id
          ) = 2
        LIMIT 1
    ");
    $stmt->execute(array_merge([$adminId, $submissionId], $params));
    $conversationId = $stmt->fetchColumn();

    return $conversationId === false
        ? null
        : (int) $conversationId;
}

function chatGetSubmissionResponsibleAdminId(int $submissionId): int
{
    global $pdo;

    $stmt = $pdo->prepare("
        SELECT
            t.id,
            t.submission_id,
            t.offer_id,
            t.processed_by_admin_id,
            o.submission_id AS offer_submission_id,
            o.created_by_admin_id AS offer_admin_id,
            s.assigned_admin_id
        FROM transactions t
        INNER JOIN submissions s
            ON s.id = t.submission_id
        LEFT JOIN offers o
            ON o.id = t.offer_id
        WHERE s.id = ?
        ORDER BY t.id ASC
        LIMIT 2
    ");
    $stmt->execute([$submissionId]);
    $transactions = $stmt->fetchAll(PDO::FETCH_ASSOC);
    if (
        count($transactions) !== 1
        || (int) $transactions[0]['submission_id'] !== $submissionId
        || $transactions[0]['offer_id'] !== null
            && (int) $transactions[0]['offer_submission_id'] !== $submissionId
    ) {
        throw new ChatHttpException(
            'Submission transaction relationship is unavailable or inconsistent.',
            409
        );
    }

    $adminIds = [];
    foreach ([
        $transactions[0]['processed_by_admin_id'],
        $transactions[0]['offer_admin_id'],
        $transactions[0]['assigned_admin_id']
    ] as $adminId) {
        if ($adminId !== null) {
            $adminIds[(int) $adminId] = true;
        }
    }
    if (count($adminIds) !== 1) {
        throw new ChatHttpException(
            'Submission Admin attribution is unavailable or inconsistent.',
            409
        );
    }

    return (int) array_key_first($adminIds);
}

function chatGuestSubmissionClaimsSchemaAvailable(): bool
{
    static $isAvailable = null;
    if ($isAvailable !== null) {
        return $isAvailable;
    }

    global $pdo;
    $stmt = $pdo->prepare("
        SELECT COUNT(*)
        FROM information_schema.TABLES
        WHERE TABLE_SCHEMA = DATABASE()
          AND TABLE_NAME = 'guest_submission_claims'
    ");
    $stmt->execute();
    $isAvailable = (int) $stmt->fetchColumn() === 1;

    return $isAvailable;
}

function chatGetTransactionContext(
    int $transactionId,
    array $actor,
    bool $lockForUpdate = false
): array {
    global $pdo;

    $sql = "
        SELECT
            t.id AS transaction_id,
            t.transaction_reference,
            t.transaction_status,
            t.processed_by_admin_id,
            t.offer_id,
            o.created_by_admin_id AS offer_admin_id,
            o.submission_id AS offer_submission_id,
            s.id AS submission_id,
            s.reference_number AS submission_reference,
            s.user_id AS seller_id,
            s.assigned_admin_id,
            seller.full_name AS seller_full_name
        FROM transactions t
        INNER JOIN submissions s
            ON s.id = t.submission_id
        LEFT JOIN offers o
            ON o.id = t.offer_id
        LEFT JOIN users seller
            ON seller.id = s.user_id
        WHERE t.id = ?
        LIMIT 1
    ";

    if ($lockForUpdate) {
        $sql .= ' FOR UPDATE';
    }

    $stmt = $pdo->prepare($sql);
    $stmt->execute([$transactionId]);
    $context = $stmt->fetch();

    if (!$context) {
        throw new ChatHttpException('Transaction not found.', 404);
    }

    if (
        $context['offer_id'] !== null
        && (int) $context['offer_submission_id'] !== (int) $context['submission_id']
    ) {
        throw new ChatHttpException(
            'The transaction offer and submission relationship is inconsistent.',
            409
        );
    }

    if ($context['seller_id'] !== null) {
        $sellerId = (int) $context['seller_id'];
        $sellerStmt = $pdo->prepare("
            SELECT id
            FROM users
            WHERE id = ?
              AND is_active = 1
            LIMIT 1
        ");
        $sellerStmt->execute([$sellerId]);

        if (!$sellerStmt->fetchColumn()) {
            throw new ChatHttpException(
                'Chat is unavailable because the seller account is not active.',
                403
            );
        }

        if (
            $actor['actor_type'] !== 'admin'
            && (
                $actor['actor_type'] !== 'seller'
                || (int) $actor['actor_id'] !== $sellerId
            )
        ) {
            throw new ChatHttpException('Unauthorized.', 403);
        }
    } elseif (!in_array($actor['actor_type'], ['admin', 'guest'], true)) {
        throw new ChatHttpException('Unauthorized.', 403);
    }

    $adminIds = [];

    foreach ([
        $context['processed_by_admin_id'],
        $context['offer_admin_id'],
        $context['assigned_admin_id']
    ] as $adminId) {
        if ($adminId !== null) {
            $adminIds[(int) $adminId] = true;
        }
    }

    if (count($adminIds) === 0) {
        throw new ChatHttpException(
            'Chat is unavailable because no responsible admin is recorded for this transaction.',
            409
        );
    }

    if (count($adminIds) > 1) {
        throw new ChatHttpException(
            'Chat is unavailable because the responsible admin is ambiguous.',
            409
        );
    }

    $adminId = (int) array_key_first($adminIds);
    $adminStmt = $pdo->prepare("
        SELECT id
        FROM admins
        WHERE id = ?
        LIMIT 1
    ");
    $adminStmt->execute([$adminId]);

    if (!$adminStmt->fetchColumn()) {
        throw new ChatHttpException(
            'Chat is unavailable because the responsible admin account could not be verified.',
            409
        );
    }

    if (
        $actor['actor_type'] === 'admin'
        && (int) $actor['actor_id'] !== $adminId
    ) {
        throw new ChatHttpException('Unauthorized.', 403);
    }

    if ($context['seller_id'] !== null) {
        $claim = null;
        if (chatGuestSubmissionClaimsSchemaAvailable()) {
            $claimedConversationStmt = $pdo->prepare("
                SELECT
                    c.id AS conversation_id,
                    c.type AS conversation_type,
                    c.transaction_id,
                    claims.seller_id,
                    claims.guest_identity_id,
                    guest_identity.submission_id AS identity_submission_id,
                    guest_identity.revoked_at AS identity_revoked_at
                FROM guest_submission_claims claims
                LEFT JOIN conversations c
                    ON c.submission_id = claims.submission_id
                LEFT JOIN guest_chat_identities guest_identity
                    ON guest_identity.id = claims.guest_identity_id
                WHERE claims.submission_id = ?
                LIMIT 1
            ");
            $claimedConversationStmt->execute([(int) $context['submission_id']]);
            $claim = $claimedConversationStmt->fetch(PDO::FETCH_ASSOC) ?: null;
        }
        if ($claim) {
            if (
                (int) $claim['seller_id'] !== (int) $context['seller_id']
                || (int) $claim['identity_submission_id'] !== (int) $context['submission_id']
                || $claim['identity_revoked_at'] === null
                || $claim['conversation_id'] === null
                || $claim['conversation_type'] !== 'private'
                || $claim['transaction_id'] !== null
                || !in_array($actor['actor_type'], ['admin', 'seller'], true)
                || $actor['actor_type'] === 'seller'
                    && (int) $actor['actor_id'] !== (int) $claim['seller_id']
            ) {
                throw new ChatHttpException(
                    'Claimed submission conversation is inconsistent.',
                    409
                );
            }

            $context['seller_id'] = (int) $claim['seller_id'];
            $context['responsible_admin_id'] = $adminId;
            $context['conversation_class'] = 'claimed_submission';
            $context['claimed_submission'] = true;
            $context['guest_submission'] = false;
            $context['guest_chat_conversation_id'] =
                (int) $claim['conversation_id'];
            $context['guest_identity_id'] =
                (int) $claim['guest_identity_id'];
            return $context;
        }

        $submissionConversationStmt = $pdo->prepare("
            SELECT id
            FROM conversations
            WHERE submission_id = ?
            LIMIT 1
        ");
        $submissionConversationStmt->execute([(int) $context['submission_id']]);
        if ($submissionConversationStmt->fetchColumn()) {
            throw new ChatHttpException(
                'Submission conversation claim is inconsistent.',
                409
            );
        }

    }

    if ($context['seller_id'] === null) {
        if ($actor['actor_type'] === 'guest') {
            $conversationId = chatFindGuestSubmissionConversation(
                (int) $context['submission_id'],
                $adminId,
                (int) $actor['actor_id']
            );
        } elseif ($actor['actor_type'] === 'admin') {
            $conversationId = chatFindGuestSubmissionConversation(
                (int) $context['submission_id'],
                $adminId
            );
        } else {
            throw new ChatHttpException('Unauthorized.', 403);
        }

        if ($conversationId === null) {
            throw new ChatHttpException(
                'Guest chat is not yet provisioned for this submission.',
                409
            );
        }

        $context['seller_id'] = null;
        $context['seller_full_name'] = 'Guest Seller';
        $context['responsible_admin_id'] = $adminId;
        $context['conversation_class'] = 'unclaimed_guest_submission';
        $context['claimed_submission'] = false;
        $context['guest_submission'] = true;
        $context['guest_chat_conversation_id'] = $conversationId;

        return $context;
    }

    $sellerId = (int) $context['seller_id'];
    $context['seller_id'] = $sellerId;
    $context['responsible_admin_id'] = $adminId;
    $context['conversation_class'] = 'transaction';
    $context['claimed_submission'] = false;
    $context['guest_submission'] = false;

    return $context;
}

function chatEnsureParticipant(
    int $conversationId,
    string $actorType,
    int $actorId
): void {
    global $pdo;

    $stmt = $pdo->prepare("
        INSERT INTO conversation_participants (
            conversation_id,
            actor_type,
            actor_id
        )
        VALUES (?, ?, ?)
        ON DUPLICATE KEY UPDATE id = id
    ");
    $stmt->execute([
        $conversationId,
        $actorType,
        $actorId
    ]);
}

function chatValidateActorIdentity(string $actorType, int $actorId): array
{
    global $pdo;

    if ($actorType === 'seller') {
        $stmt = $pdo->prepare("
            SELECT id, full_name AS display_name, profile_picture
            FROM users
            WHERE id = ?
              AND is_active = 1
            LIMIT 1
        ");
        $stmt->execute([$actorId]);
        $identity = $stmt->fetch();
        if (!$identity) {
            throw new ChatHttpException('The selected account is unavailable.', 404);
        }

        return [
            'actor_type' => 'seller',
            'actor_id' => $actorId,
            'display_name' => (string) $identity['display_name'],
            'profile_picture' => preg_match(
                '/\Auploads\/profile_pictures\/[A-Za-z0-9][A-Za-z0-9._-]*\z/',
                (string) ($identity['profile_picture'] ?? '')
            ) === 1
                ? (string) $identity['profile_picture']
                : ''
        ];
    }

    if ($actorType === 'guest') {
        $stmt = $pdo->prepare("
            SELECT id
            FROM guest_chat_identities
            WHERE id = ?
            LIMIT 1
        ");
        $stmt->execute([$actorId]);
        if (!$stmt->fetchColumn()) {
            throw new ChatHttpException('The selected account is unavailable.', 404);
        }

        return [
            'actor_type' => 'guest',
            'actor_id' => $actorId,
            'display_name' => 'Guest Seller',
            'profile_picture' => ''
        ];
    }

    if ($actorType === 'admin') {
        $stmt = $pdo->prepare("
            SELECT
                a.id,
                TRIM(CONCAT(a.first_name, ' ', a.last_name)) AS display_name
            FROM admins a
            INNER JOIN roles r
                ON r.id = a.role_id
            INNER JOIN role_permissions rp
                ON rp.role_id = a.role_id
            INNER JOIN permissions p
                ON p.id = rp.permission_id
               AND p.name = 'view_submissions'
            WHERE a.id = ?
              AND a.is_active = 1
            LIMIT 1
        ");
        $stmt->execute([$actorId]);
        $identity = $stmt->fetch();
        if (!$identity) {
            throw new ChatHttpException('The selected account is unavailable.', 404);
        }

        return [
            'actor_type' => 'admin',
            'actor_id' => $actorId,
            'display_name' => trim((string) $identity['display_name']) ?: 'Admin',
            'profile_picture' => ''
        ];
    }

    throw new ChatHttpException('Invalid participant type.', 422);
}

function chatAcquireNamedLock(string $lockName): void
{
    global $pdo;

    $stmt = $pdo->prepare('SELECT GET_LOCK(?, 5)');
    $stmt->execute([$lockName]);
    if ((int) $stmt->fetchColumn() !== 1) {
        throw new ChatHttpException(
            'The conversation is busy. Please try again.',
            503
        );
    }
}

function chatReleaseNamedLock(string $lockName): void
{
    global $pdo;

    $stmt = $pdo->prepare('SELECT RELEASE_LOCK(?)');
    $stmt->execute([$lockName]);
    if ((int) $stmt->fetchColumn() !== 1) {
        error_log('A messaging conversation lock could not be released normally.');
    }
}

function chatFindPrivateConversationId(array $firstActor, array $secondActor): ?int
{
    global $pdo;

    $submissionCondition = chatGuestSubmissionSchemaAvailable()
        ? 'AND c.submission_id IS NULL'
        : '';
    $stmt = $pdo->prepare("
        SELECT c.id
        FROM conversations c
        INNER JOIN conversation_participants p1
            ON p1.conversation_id = c.id
           AND p1.actor_type = ?
           AND p1.actor_id = ?
        INNER JOIN conversation_participants p2
            ON p2.conversation_id = c.id
           AND p2.actor_type = ?
           AND p2.actor_id = ?
        WHERE c.type = 'private'
          AND c.transaction_id IS NULL
          $submissionCondition
          AND p1.id <> p2.id
          AND (
              SELECT COUNT(*)
              FROM conversation_participants px
              WHERE px.conversation_id = c.id
          ) = 2
        LIMIT 2
    ");
    $stmt->execute([
        $firstActor['actor_type'],
        $firstActor['actor_id'],
        $secondActor['actor_type'],
        $secondActor['actor_id']
    ]);
    $conversationIds = $stmt->fetchAll(PDO::FETCH_COLUMN);

    if (count($conversationIds) > 1) {
        throw new ChatHttpException(
            'More than one private conversation exists for this participant pair.',
            409
        );
    }

    return $conversationIds
        ? (int) $conversationIds[0]
        : null;
}

function chatGetOrCreateGlobalConversation(array $actor): array
{
    global $pdo;

    if ($actor['actor_type'] === 'guest') {
        throw new ChatHttpException('Unauthorized.', 403);
    }

    $lockName = 'vnz-chat-global';
    chatAcquireNamedLock($lockName);

    try {
        $pdo->beginTransaction();
        $stmt = $pdo->query("
            SELECT id
            FROM conversations
            WHERE type = 'global'
              AND transaction_id IS NULL
              AND " . (
                chatGuestSubmissionSchemaAvailable()
                    ? 'submission_id IS NULL'
                    : '1 = 1'
            ) . "
            ORDER BY id
            FOR UPDATE
        ");
        $conversationIds = $stmt->fetchAll(PDO::FETCH_COLUMN);

        if (count($conversationIds) > 1) {
            throw new ChatHttpException(
                'More than one global conversation exists.',
                409
            );
        }

        if ($conversationIds) {
            $conversationId = (int) $conversationIds[0];
        } else {
            $insertStmt = $pdo->prepare("
                INSERT INTO conversations (type, transaction_id)
                VALUES ('global', NULL)
            ");
            $insertStmt->execute();
            $conversationId = (int) $pdo->lastInsertId();
        }

        chatEnsureParticipant(
            $conversationId,
            $actor['actor_type'],
            (int) $actor['actor_id']
        );
        $pdo->commit();

        return [
            'conversation_id' => $conversationId,
            'conversation_type' => 'global',
            'display_name' => 'Global Chat'
        ];
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        throw $e;
    } finally {
        chatReleaseNamedLock($lockName);
    }
}

function chatGetOrCreatePrivateConversation(
    array $actor,
    string $targetType,
    int $targetId
): array {
    global $pdo;

    if (
        $actor['actor_type'] === 'guest'
        || !in_array($targetType, ['seller', 'admin'], true)
    ) {
        throw new ChatHttpException('Unauthorized.', 403);
    }

    $target = chatValidateActorIdentity($targetType, $targetId);
    if (
        $target['actor_type'] === $actor['actor_type']
        && (int) $target['actor_id'] === (int) $actor['actor_id']
    ) {
        throw new ChatHttpException(
            'You cannot start a private conversation with yourself.',
            422
        );
    }

    $pair = [
        $actor['actor_type'] . ':' . (int) $actor['actor_id'],
        $target['actor_type'] . ':' . (int) $target['actor_id']
    ];
    sort($pair, SORT_STRING);
    $lockName = 'vnz-chat-private-' . substr(hash('sha256', implode('|', $pair)), 0, 40);
    chatAcquireNamedLock($lockName);

    try {
        $pdo->beginTransaction();
        $conversationId = chatFindPrivateConversationId($actor, $target);
        if ($conversationId === null) {
            $insertStmt = $pdo->prepare("
                INSERT INTO conversations (type, transaction_id)
                VALUES ('private', NULL)
            ");
            $insertStmt->execute();
            $conversationId = (int) $pdo->lastInsertId();

            chatEnsureParticipant(
                $conversationId,
                $actor['actor_type'],
                (int) $actor['actor_id']
            );
            chatEnsureParticipant(
                $conversationId,
                $target['actor_type'],
                (int) $target['actor_id']
            );
        }
        $pdo->commit();

        return [
            'conversation_id' => $conversationId,
            'conversation_type' => 'private',
            'display_name' => chatParticipantDisplayNameForViewer(
                $actor,
                $target['actor_type'],
                $target['display_name']
            ),
            'target_type' => $target['actor_type'],
            'target_id' => (int) $target['actor_id']
        ];
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        throw $e;
    } finally {
        chatReleaseNamedLock($lockName);
    }
}

function chatListPrivateContacts(array $actor, string $category): array
{
    global $pdo;

    if ($actor['actor_type'] === 'guest') {
        throw new ChatHttpException('Unauthorized.', 403);
    }

    if (!in_array($category, ['private', 'contacts'], true)) {
        throw new ChatHttpException('Invalid contact category.', 422);
    }

    $includeSellers = $category === 'private' || $actor['actor_type'] === 'admin';
    $includeAdmins = $category === 'private' || $actor['actor_type'] === 'seller';
    $contacts = [];

    if ($includeSellers) {
        $sql = "
            SELECT
                id AS actor_id,
                full_name AS display_name,
                profile_picture
            FROM users
            WHERE is_active = 1
        ";
        $params = [];
        if ($actor['actor_type'] === 'seller') {
            $sql .= ' AND id <> ?';
            $params[] = (int) $actor['actor_id'];
        }
        $sql .= ' ORDER BY full_name, id';

        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        foreach ($stmt->fetchAll() as $row) {
            $contacts[] = [
                'actor_type' => 'seller',
                'actor_id' => (int) $row['actor_id'],
                'display_name' => trim((string) $row['display_name']) ?: 'Seller',
                'profile_picture' => (string) ($row['profile_picture'] ?? '')
            ];
        }
    }

    if ($includeAdmins) {
        $sql = "
            SELECT
                a.id AS actor_id,
                TRIM(CONCAT(a.first_name, ' ', a.last_name)) AS display_name
            FROM admins a
            WHERE a.is_active = 1
              AND EXISTS (
                  SELECT 1
                  FROM role_permissions rp
                  INNER JOIN permissions p
                      ON p.id = rp.permission_id
                  WHERE rp.role_id = a.role_id
                    AND p.name = 'view_submissions'
              )
        ";
        $params = [];
        if ($actor['actor_type'] === 'admin') {
            $sql .= ' AND a.id <> ?';
            $params[] = (int) $actor['actor_id'];
        }
        $sql .= ' ORDER BY a.id';

        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        foreach ($stmt->fetchAll() as $row) {
            $contacts[] = [
                'actor_type' => 'admin',
                'actor_id' => (int) $row['actor_id'],
                'display_name' => chatParticipantDisplayNameForViewer(
                    $actor,
                    'admin',
                    (string) $row['display_name']
                ),
                'profile_picture' => ''
            ];
        }
    }

    foreach ($contacts as &$contact) {
        $contact['conversation_id'] = chatFindPrivateConversationId($actor, $contact);
        $contact['last_message'] = '';
        if ($contact['conversation_id'] !== null) {
            try {
                chatGetOneToOneConversationPeer(
                    (int) $contact['conversation_id'],
                    $actor
                );
                $contact['call_eligible'] = true;
            } catch (ChatHttpException $e) {
                if (!in_array($e->httpStatus, [403, 404, 409], true)) {
                    throw $e;
                }
                $contact['call_eligible'] = false;
            }
            $contact['presence'] = chatGetConversationPresence(
                (int) $contact['conversation_id'],
                $actor
            );
            $messageStmt = $pdo->prepare("
                SELECT content
                FROM messages
                WHERE conversation_id = ?
                ORDER BY created_at DESC, id DESC
                LIMIT 1
            ");
            $messageStmt->execute([$contact['conversation_id']]);
            $lastMessage = (string) ($messageStmt->fetchColumn() ?: '');
            $contact['last_message'] = mb_strlen($lastMessage, 'UTF-8') > 120
                ? mb_substr($lastMessage, 0, 120, 'UTF-8') . '…'
                : $lastMessage;
        } else {
            $contact['call_eligible'] = false;
        }
    }
    unset($contact);

    return $contacts;
}

function chatGetParticipants(int $conversationId): array
{
    global $pdo;

    $stmt = $pdo->prepare("
        SELECT
            p.actor_type,
            p.actor_id,
            CASE
                WHEN p.actor_type = 'seller' THEN u.full_name
                WHEN p.actor_type = 'admin' THEN 'Admin'
                ELSE 'Guest Seller'
            END AS display_name
        FROM conversation_participants p
        LEFT JOIN users u
            ON p.actor_type = 'seller'
           AND u.id = p.actor_id
        LEFT JOIN admins a
            ON p.actor_type = 'admin'
           AND a.id = p.actor_id
        WHERE p.conversation_id = ?
          AND (
              (p.actor_type = 'seller' AND u.id IS NOT NULL)
              OR (p.actor_type = 'admin' AND a.id IS NOT NULL)
              OR p.actor_type = 'guest'
          )
        ORDER BY p.actor_type, p.actor_id
    ");
    $stmt->execute([$conversationId]);

    return $stmt->fetchAll();
}

function getOrCreateTransactionConversation(
    int $transactionId
): array {
    global $pdo;

    $actor = chatGetCurrentActor();
    $pdo->beginTransaction();

    try {
        $context = chatGetTransactionContext(
            $transactionId,
            $actor,
            true
        );

        if (!empty($context['guest_chat_conversation_id'])) {
            $conversationId = (int) $context['guest_chat_conversation_id'];
            $conversation = chatRequireConversationMembership(
                $conversationId,
                $actor
            );
            if (
                $conversation['conversation_type'] !== 'private'
                || (int) $conversation['submission_id'] !== (int) $context['submission_id']
            ) {
                throw new ChatHttpException(
                    'The guest conversation is not linked to this transaction.',
                    409
                );
            }

            $participants = chatGetParticipants($conversationId);
            $pdo->commit();

            return [
                'conversation_id' => $conversationId,
                'conversation_type' => 'private',
                'transaction_id' => $transactionId,
                'submission_id' => (int) $context['submission_id'],
                'conversation_class' => $context['conversation_class'],
                'claimed_submission' => !empty($context['claimed_submission']),
                'guest_submission' => !empty($context['guest_submission']),
                'participants' => $participants
            ];
        }

        $conversationStmt = $pdo->prepare("
            INSERT INTO conversations (
                type,
                transaction_id
            )
            VALUES ('transaction', ?)
            ON DUPLICATE KEY UPDATE
                id = LAST_INSERT_ID(id),
                updated_at = updated_at
        ");
        $conversationStmt->execute([$transactionId]);

        $conversationId = (int) $pdo->lastInsertId();

        $verifyStmt = $pdo->prepare("
            SELECT id, type, transaction_id
            FROM conversations
            WHERE id = ?
              AND type = 'transaction'
              AND transaction_id = ?
              AND " . (
                chatGuestSubmissionSchemaAvailable()
                    ? 'submission_id IS NULL'
                    : '1 = 1'
            ) . "
            LIMIT 1
        ");
        $verifyStmt->execute([
            $conversationId,
            $transactionId
        ]);
        $conversation = $verifyStmt->fetch();

        if (!$conversation) {
            throw new ChatHttpException(
                'Unable to open the transaction conversation.',
                409
            );
        }

        chatEnsureParticipant(
            $conversationId,
            'seller',
            $context['seller_id']
        );
        chatEnsureParticipant(
            $conversationId,
            'admin',
            $context['responsible_admin_id']
        );
        chatEnsureParticipant(
            $conversationId,
            $actor['actor_type'],
            $actor['actor_id']
        );

        $pdo->commit();

        return [
            'conversation_id' => $conversationId,
            'conversation_type' => 'transaction',
            'transaction_id' => $transactionId,
            'participants' => chatGetParticipants($conversationId)
        ];
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }

        throw $e;
    }
}

function chatRequireConversationMembership(
    int $conversationId,
    array $actor
): array {
    global $pdo;

    $submissionIdSelect = chatGuestSubmissionSchemaAvailable()
        ? 'c.submission_id'
        : 'NULL AS submission_id';
    $stmt = $pdo->prepare("
        SELECT
            c.id AS conversation_id,
            c.type AS conversation_type,
            c.transaction_id,
            $submissionIdSelect
        FROM conversations c
        INNER JOIN conversation_participants p
            ON p.conversation_id = c.id
           AND p.actor_type = ?
           AND p.actor_id = ?
        WHERE c.id = ?
        LIMIT 1
    ");
    $stmt->execute([
        $actor['actor_type'],
        $actor['actor_id'],
        $conversationId
    ]);
    $conversation = $stmt->fetch();

    if (!$conversation) {
        throw new ChatHttpException('Unauthorized.', 403);
    }

    if ($conversation['conversation_type'] === 'transaction') {
        if (
            $conversation['transaction_id'] === null
            || $conversation['submission_id'] !== null
        ) {
            throw new ChatHttpException('Unauthorized.', 403);
        }
        $context = chatGetTransactionContext(
            (int) $conversation['transaction_id'],
            $actor
        );

        $conversation['transaction_reference'] =
            $context['transaction_reference'];
        $conversation['transaction_status'] =
            $context['transaction_status'];
        $conversation['seller_full_name'] =
            $context['seller_full_name'];
        $conversation['seller_id'] =
            $context['seller_id'];
        $conversation['responsible_admin_id'] =
            $context['responsible_admin_id'];
        $conversation['conversation_class'] = 'transaction';
        $conversation['claimed_submission'] = false;
        $conversation['guest_submission'] = false;
    } elseif ($conversation['conversation_type'] === 'global') {
        if (
            $conversation['transaction_id'] !== null
            || $conversation['submission_id'] !== null
        ) {
            throw new ChatHttpException('Unauthorized.', 403);
        }
        $conversation['display_name'] = 'Global Chat';
        $conversation['conversation_class'] = 'global';
        $conversation['claimed_submission'] = false;
        $conversation['guest_submission'] = false;
    } elseif ($conversation['conversation_type'] === 'private') {
        if ($conversation['transaction_id'] !== null) {
            throw new ChatHttpException('Unauthorized.', 403);
        }

        global $pdo;
        $participantStmt = $pdo->prepare("
            SELECT actor_type, actor_id
            FROM conversation_participants
            WHERE conversation_id = ?
            ORDER BY actor_type, actor_id
        ");
        $participantStmt->execute([$conversationId]);
        $participants = $participantStmt->fetchAll();
        if (count($participants) !== 2) {
            throw new ChatHttpException('Unauthorized.', 403);
        }

        $guestParticipant = null;
        $sellerParticipant = null;
        $adminParticipant = null;
        foreach ($participants as $participant) {
            if ($participant['actor_type'] === 'guest') {
                $guestParticipant = $participant;
            } elseif ($participant['actor_type'] === 'seller') {
                $sellerParticipant = $participant;
            } elseif ($participant['actor_type'] === 'admin') {
                $adminParticipant = $participant;
            }
        }

        if ($conversation['submission_id'] !== null) {
            $submissionId = (int) $conversation['submission_id'];
            $submissionStmt = $pdo->prepare("
                SELECT id, user_id, reference_number, seller_name
                FROM submissions
                WHERE id = ?
                LIMIT 1
            ");
            $submissionStmt->execute([$submissionId]);
            $submission = $submissionStmt->fetch(PDO::FETCH_ASSOC);
            if (!$submission || !$adminParticipant) {
                throw new ChatHttpException('Unauthorized.', 403);
            }

            $identityStmt = $pdo->prepare("
                SELECT
                    id,
                    revoked_at IS NULL AND expires_at > NOW() AS is_active,
                    revoked_at
                FROM guest_chat_identities
                WHERE submission_id = ?
                LIMIT 1
            ");
            $identityStmt->execute([$submissionId]);
            $guestIdentity = $identityStmt->fetch(PDO::FETCH_ASSOC);
            if (!$guestIdentity) {
                throw new ChatHttpException('Unauthorized.', 403);
            }

            $responsibleAdminId =
                chatGetSubmissionResponsibleAdminId($submissionId);
            if ((int) $adminParticipant['actor_id'] !== $responsibleAdminId) {
                throw new ChatHttpException('Unauthorized.', 403);
            }

            $isUnclaimedGuestConversation =
                count($participants) === 2
                && $guestParticipant !== null
                && $sellerParticipant === null
                && $submission['user_id'] === null
                && (int) $guestParticipant['actor_id'] === (int) $guestIdentity['id'];
            $isClaimedSellerConversation =
                count($participants) === 2
                && $guestParticipant === null
                && $sellerParticipant !== null
                && $submission['user_id'] !== null
                && (int) $sellerParticipant['actor_id'] === (int) $submission['user_id'];

            if ($isUnclaimedGuestConversation) {
                if (
                    $actor['actor_type'] === 'guest'
                    && (
                        (int) $actor['actor_id'] !== (int) $guestIdentity['id']
                        || !(bool) $guestIdentity['is_active']
                    )
                ) {
                    throw new ChatHttpException('Unauthorized.', 403);
                }
                if (!in_array($actor['actor_type'], ['guest', 'admin'], true)) {
                    throw new ChatHttpException('Unauthorized.', 403);
                }
                $conversation['conversation_class'] = 'unclaimed_guest_submission';
                $conversation['claimed_submission'] = false;
                $conversation['guest_submission'] = true;
            } elseif ($isClaimedSellerConversation) {
                if (!chatGuestSubmissionClaimsSchemaAvailable()) {
                    throw new ChatHttpException('Unauthorized.', 403);
                }
                $auditStmt = $pdo->prepare("
                    SELECT 1
                    FROM guest_submission_claims
                    WHERE submission_id = ?
                      AND seller_id = ?
                      AND guest_identity_id = ?
                      AND proof_method IN (
                          'guest_chat_capability',
                          'verified_email'
                      )
                    LIMIT 1
                ");
                $auditStmt->execute([
                    $submissionId,
                    (int) $submission['user_id'],
                    (int) $guestIdentity['id']
                ]);
                if (
                    !$auditStmt->fetchColumn()
                    || $guestIdentity['revoked_at'] === null
                    || !in_array($actor['actor_type'], ['seller', 'admin'], true)
                    || $actor['actor_type'] === 'seller'
                        && (int) $actor['actor_id'] !== (int) $submission['user_id']
                ) {
                    throw new ChatHttpException('Unauthorized.', 403);
                }

                $transactionStmt = $pdo->prepare("
                    SELECT id
                    FROM transactions
                    WHERE submission_id = ?
                    ORDER BY id ASC
                    LIMIT 2
                ");
                $transactionStmt->execute([$submissionId]);
                $transactions = $transactionStmt->fetchAll(PDO::FETCH_COLUMN);
                if (count($transactions) !== 1) {
                    throw new ChatHttpException('Unauthorized.', 403);
                }
                $transactionContext = chatGetTransactionContext(
                    (int) $transactions[0],
                    $actor
                );
                if (
                    ($transactionContext['conversation_class'] ?? null)
                        !== 'claimed_submission'
                    || (int) ($transactionContext['guest_chat_conversation_id'] ?? 0)
                        !== $conversationId
                    || (int) $sellerParticipant['actor_id']
                        !== (int) $transactionContext['seller_id']
                    || (int) $adminParticipant['actor_id']
                        !== (int) $transactionContext['responsible_admin_id']
                ) {
                    throw new ChatHttpException('Unauthorized.', 403);
                }

                $conversation['conversation_class'] = 'claimed_submission';
                $conversation['claimed_submission'] = true;
                $conversation['guest_submission'] = false;
                $conversation['transaction_id'] =
                    (int) $transactionContext['transaction_id'];
                $conversation['transaction_reference'] =
                    $transactionContext['transaction_reference'];
                $conversation['transaction_status'] =
                    $transactionContext['transaction_status'];
                $conversation['seller_id'] =
                    (int) $transactionContext['seller_id'];
                $conversation['responsible_admin_id'] =
                    (int) $transactionContext['responsible_admin_id'];
            } else {
                throw new ChatHttpException('Unauthorized.', 403);
            }

            $conversation['submission_reference'] =
                (string) $submission['reference_number'];
            $conversation['submission_seller_name'] =
                trim((string) ($submission['seller_name'] ?? ''));
        } elseif ($guestParticipant !== null) {
            throw new ChatHttpException('Unauthorized.', 403);
        } else {
            $conversation['conversation_class'] = 'private';
            $conversation['claimed_submission'] = false;
            $conversation['guest_submission'] = false;
        }

        foreach ($participants as $participant) {
            chatValidateActorIdentity(
                (string) $participant['actor_type'],
                (int) $participant['actor_id']
            );
        }

        $otherParticipant = null;
        foreach ($participants as $participant) {
            if (
                $participant['actor_type'] !== $actor['actor_type']
                || (int) $participant['actor_id'] !== (int) $actor['actor_id']
            ) {
                $otherParticipant = $participant;
                break;
            }
        }
        if ($otherParticipant === null) {
            throw new ChatHttpException('Unauthorized.', 403);
        }

        $otherActor = chatValidateActorIdentity(
            (string) $otherParticipant['actor_type'],
            (int) $otherParticipant['actor_id']
        );
        $conversation['display_name'] = chatParticipantDisplayNameForViewer(
            $actor,
            $otherActor['actor_type'],
            $otherActor['display_name']
        );
        if ($conversation['submission_id'] !== null) {
            if ($conversation['conversation_class'] === 'claimed_submission') {
                $conversation['display_name'] = $actor['actor_type'] === 'admin'
                    ? 'SELLER - ' . (trim((string) $otherActor['display_name']) ?: 'Seller')
                    : 'ADMIN';
            } else {
                $conversation['display_name'] = $actor['actor_type'] === 'admin'
                    ? 'Guest Seller · ' . $conversation['submission_reference']
                    : 'Admin';
            }
        }
    } else {
        throw new ChatHttpException('Unauthorized.', 403);
    }

    return $conversation;
}

function chatGetOneToOneConversationPeer(
    int $conversationId,
    array $actor
): array {
    $conversation = chatRequireConversationMembership($conversationId, $actor);
    if (!in_array($conversation['conversation_type'], ['transaction', 'private'], true)) {
        throw new ChatHttpException('Calls are only available in one-to-one conversations.', 403);
    }
    if (
        $conversation['submission_id'] !== null
        && ($conversation['conversation_class'] ?? null) !== 'claimed_submission'
    ) {
        throw new ChatHttpException(
            'Calls are unavailable for guest-submission conversations.',
            403
        );
    }

    global $pdo;
    $stmt = $pdo->prepare("
        SELECT actor_type, actor_id
        FROM conversation_participants
        WHERE conversation_id = ?
        ORDER BY actor_type, actor_id
    ");
    $stmt->execute([$conversationId]);
    $participants = $stmt->fetchAll(PDO::FETCH_ASSOC);

    if (
        $conversation['conversation_type'] === 'private'
        && $conversation['transaction_id'] === null
        && $conversation['submission_id'] === null
        && ($conversation['conversation_class'] ?? null) === 'private'
        && empty($conversation['claimed_submission'])
        && empty($conversation['guest_submission'])
        && $actor['actor_type'] === 'seller'
        && count($participants) === 2
        && count(array_unique(array_column($participants, 'actor_type'))) === 1
        && $participants[0]['actor_type'] === 'seller'
        && $participants[1]['actor_type'] === 'seller'
        && count(array_unique(array_column($participants, 'actor_id'))) === 2
    ) {
        $sellerIdentities = [];
        foreach ($participants as $participant) {
            $identity = chatValidateActorIdentity(
                'seller',
                (int) $participant['actor_id']
            );
            $sellerIdentities[(int) $identity['actor_id']] = $identity;
        }

        $actorId = (int) $actor['actor_id'];
        if (!isset($sellerIdentities[$actorId])) {
            throw new ChatHttpException('Unauthorized.', 403);
        }

        foreach ($sellerIdentities as $sellerId => $identity) {
            if ($sellerId === $actorId) {
                continue;
            }

            return [
                'conversation' => $conversation,
                'peer' => [
                    'actor_type' => 'seller',
                    'actor_id' => (int) $identity['actor_id'],
                    'display_name' => chatParticipantDisplayNameForViewer(
                        $actor,
                        'seller',
                        $identity['display_name']
                    ),
                    'profile_picture' => $identity['profile_picture'] ?? ''
                ]
            ];
        }

        throw new ChatHttpException('Unauthorized.', 403);
    }

    if (
        count($participants) !== 2
        || count(array_unique(array_column($participants, 'actor_type'))) !== 2
        || !in_array('admin', array_column($participants, 'actor_type'), true)
        || !in_array('seller', array_column($participants, 'actor_type'), true)
    ) {
        throw new ChatHttpException('Calls are only available between one Admin and one Seller.', 403);
    }

    $participantsByType = [];
    foreach ($participants as $participant) {
        $participantsByType[$participant['actor_type']] = chatValidateActorIdentity(
            (string) $participant['actor_type'],
            (int) $participant['actor_id']
        );
    }

    if (
        in_array(
            $conversation['conversation_class'] ?? $conversation['conversation_type'],
            ['transaction', 'claimed_submission'],
            true
        )
        && (
            (int) $participantsByType['seller']['actor_id']
                !== (int) $conversation['seller_id']
            || (int) $participantsByType['admin']['actor_id']
                !== (int) $conversation['responsible_admin_id']
        )
    ) {
        throw new ChatHttpException(
            'Calls are unavailable because this transaction conversation does not match its current seller and responsible Admin.',
            403
        );
    }

    $peer = null;
    foreach ($participantsByType as $participantType => $identity) {
        if (
            $participantType === $actor['actor_type']
            && (int) $identity['actor_id'] === (int) $actor['actor_id']
        ) {
            continue;
        }
        $peer = [
            'actor_type' => $identity['actor_type'],
            'actor_id' => $identity['actor_id'],
            'display_name' => chatParticipantDisplayNameForViewer(
                $actor,
                $identity['actor_type'],
                $identity['display_name']
            ),
            'profile_picture' => $identity['profile_picture'] ?? ''
        ];
    }

    if ($peer === null) {
        throw new ChatHttpException('Unauthorized.', 403);
    }

    return [
        'conversation' => $conversation,
        'peer' => $peer
    ];
}

function chatWebRtcIceServers(): array
{
    $configured = getenv('WEBRTC_ICE_SERVERS');
    if ($configured === false || trim($configured) === '') {
        $localConfigPath = __DIR__ . '/scaledrone_config.local.php';
        $localConfig = is_file($localConfigPath)
            ? require $localConfigPath
            : [];
        if (!is_array($localConfig)) {
            throw new RuntimeException('Realtime local configuration must return an array.');
        }
        $servers = $localConfig['ice_servers'] ?? [
            ['urls' => 'stun:stun.l.google.com:19302']
        ];
    } else {
        try {
            $servers = json_decode($configured, true, 16, JSON_THROW_ON_ERROR);
        } catch (JsonException $e) {
            throw new RuntimeException('WebRTC ICE server configuration is invalid.');
        }
    }

    if (!is_array($servers) || !array_is_list($servers) || count($servers) > 8) {
        throw new RuntimeException('WebRTC ICE server configuration is invalid.');
    }
    foreach ($servers as $server) {
        if (!is_array($server) || !isset($server['urls'])) {
            throw new RuntimeException('WebRTC ICE server configuration is invalid.');
        }
        $urls = is_array($server['urls']) ? $server['urls'] : [$server['urls']];
        if ($urls === [] || count($urls) > 4) {
            throw new RuntimeException('WebRTC ICE server configuration is invalid.');
        }
        foreach ($urls as $url) {
            if (
                !is_string($url)
                || preg_match('/\A(?:stun|stuns|turn|turns):[^\s]{1,500}\z/i', $url) !== 1
            ) {
                throw new RuntimeException('WebRTC ICE server configuration is invalid.');
            }
        }
        foreach (['username', 'credential'] as $key) {
            if (isset($server[$key]) && !is_string($server[$key])) {
                throw new RuntimeException('WebRTC ICE server configuration is invalid.');
            }
        }
    }

    return $servers;
}
