<?php

require_once __DIR__ . '/database.php';
require_once __DIR__ . '/session.php';
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/csrf.php';

class ChatHttpException extends RuntimeException
{
    public $httpStatus;

    public function __construct(string $message, int $httpStatus)
    {
        parent::__construct($message);
        $this->httpStatus = $httpStatus;
    }
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

function chatGetCurrentActor(): array
{
    $isSellerSession =
        isset($_SESSION['user_logged_in'])
        && $_SESSION['user_logged_in'] === true
        && !empty($_SESSION['user_id']);

    $isAdminSession =
        !empty($_SESSION['admin_id']);

    if (!$isSellerSession && !$isAdminSession) {
        throw new ChatHttpException('Authentication required.', 401);
    }

    if ($isSellerSession && $isAdminSession) {
        throw new ChatHttpException(
            'This session has more than one active identity. Please sign in again.',
            409
        );
    }

    global $pdo;

    if ($isSellerSession) {
        $sellerId = filter_var(
            $_SESSION['user_id'],
            FILTER_VALIDATE_INT
        );

        if ($sellerId === false || $sellerId <= 0) {
            throw new ChatHttpException('Authentication required.', 401);
        }

        $stmt = $pdo->prepare("
            SELECT id
            FROM users
            WHERE id = ?
              AND is_active = 1
            LIMIT 1
        ");
        $stmt->execute([$sellerId]);

        if (!$stmt->fetchColumn()) {
            throw new ChatHttpException('Authentication required.', 401);
        }

        return [
            'actor_type' => 'seller',
            'actor_id' => (int) $sellerId
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
        'actor_id' => (int) $admin['id']
    ];
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

    if ($context['seller_id'] === null) {
        throw new ChatHttpException(
            'Chat is unavailable because this submission is not linked to a seller account.',
            403
        );
    }

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
        $actor['actor_type'] === 'seller'
        && (int) $actor['actor_id'] !== $sellerId
    ) {
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

    $context['seller_id'] = $sellerId;
    $context['responsible_admin_id'] = $adminId;

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
            SELECT id, full_name AS display_name
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
            'display_name' => (string) $identity['display_name']
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
            'display_name' => trim((string) $identity['display_name']) ?: 'Admin'
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

    $lockName = 'vnz-chat-global';
    chatAcquireNamedLock($lockName);

    try {
        $pdo->beginTransaction();
        $stmt = $pdo->query("
            SELECT id
            FROM conversations
            WHERE type = 'global'
              AND transaction_id IS NULL
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
            'display_name' => $target['display_name'],
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
                'display_name' => trim((string) $row['display_name']) ?: 'Admin',
                'profile_picture' => ''
            ];
        }
    }

    foreach ($contacts as &$contact) {
        $contact['conversation_id'] = chatFindPrivateConversationId($actor, $contact);
        $contact['last_message'] = '';
        if ($contact['conversation_id'] !== null) {
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

    $stmt = $pdo->prepare("
        SELECT
            c.id AS conversation_id,
            c.type AS conversation_type,
            c.transaction_id
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
        if ($conversation['transaction_id'] === null) {
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
        $conversation['responsible_admin_id'] =
            $context['responsible_admin_id'];
    } elseif ($conversation['conversation_type'] === 'global') {
        if ($conversation['transaction_id'] !== null) {
            throw new ChatHttpException('Unauthorized.', 403);
        }
        $conversation['display_name'] = 'Global Chat';
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
        $conversation['display_name'] = $otherActor['display_name'];
    } else {
        throw new ChatHttpException('Unauthorized.', 403);
    }

    return $conversation;
}
