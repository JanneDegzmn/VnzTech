<?php

ob_start();
ini_set('display_errors', '0');
header('Cache-Control: no-store, private');
header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/chat_service.php';

function guestClaimRespond(bool $success, string $message, int $status): void
{
    if (ob_get_length()) {
        ob_clean();
    }
    http_response_code($status);
    echo json_encode([
        'success' => $success,
        'message' => $message
    ], JSON_THROW_ON_ERROR);
    exit;
}

function guestClaimSessionProofs(): array
{
    $proofs = $_SESSION['guest_submission_claim_proofs'] ?? [];
    if (!is_array($proofs)) {
        return [];
    }

    $now = time();
    foreach ($proofs as $submissionId => $proof) {
        $stagedAt = is_array($proof)
            ? filter_var($proof['staged_at'] ?? null, FILTER_VALIDATE_INT)
            : false;
        $identityId = is_array($proof)
            ? filter_var($proof['guest_identity_id'] ?? null, FILTER_VALIDATE_INT)
            : false;
        if (
            !ctype_digit((string) $submissionId)
            || $stagedAt === false
            || $stagedAt <= 0
            || $now - $stagedAt > 1800
            || $identityId === false
            || $identityId <= 0
        ) {
            unset($proofs[$submissionId]);
        }
    }

    $_SESSION['guest_submission_claim_proofs'] = $proofs;
    return $proofs;
}

function guestClaimGetSubmissionConversation(
    int $submissionId,
    int $guestIdentityId
): int {
    global $pdo;

    $stmt = $pdo->prepare("
        SELECT c.id
        FROM conversations c
        INNER JOIN conversation_participants guest_participant
            ON guest_participant.conversation_id = c.id
           AND guest_participant.actor_type = 'guest'
           AND guest_participant.actor_id = ?
        WHERE c.type = 'private'
          AND c.transaction_id IS NULL
          AND c.submission_id = ?
        LIMIT 1
        FOR UPDATE
    ");
    $stmt->execute([$guestIdentityId, $submissionId]);
    $conversationId = $stmt->fetchColumn();
    if ($conversationId === false) {
        throw new ChatHttpException(
            'The submission chat is unavailable or inconsistent.',
            409
        );
    }

    return (int) $conversationId;
}

try {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        throw new ChatHttpException('Method not allowed.', 405);
    }
    if (!verifyCsrfToken($_POST['csrf_token'] ?? null)) {
        throw new ChatHttpException(
            'Your session has expired. Refresh the page and try again.',
            419
        );
    }

    $action = $_POST['action'] ?? '';
    if (!is_string($action) || !in_array($action, ['stage', 'claim'], true)) {
        throw new ChatHttpException('Invalid claim action.', 422);
    }

    $actor = chatGetCurrentActor();
    global $pdo;

    if ($action === 'stage') {
        if ($actor['actor_type'] !== 'guest') {
            throw new ChatHttpException(
                'Stage this submission before signing in to a Seller account.',
                403
            );
        }
        if (!chatGuestSubmissionClaimsSchemaAvailable()) {
            throw new ChatHttpException(
                'Submission claiming is not available yet.',
                503
            );
        }

        $submissionId = chatPositiveInteger(
            $_POST['submission_id'] ?? null,
            'submission ID'
        );
        $submissionStmt = $pdo->prepare("
            SELECT id, user_id, status
            FROM submissions
            WHERE id = ?
            LIMIT 1
        ");
        $submissionStmt->execute([$submissionId]);
        $submission = $submissionStmt->fetch(PDO::FETCH_ASSOC);
        if (
            !$submission
            || $submission['user_id'] !== null
            || $submission['status'] !== 'accepted'
        ) {
            throw new ChatHttpException(
                'This submission is not currently eligible to be claimed.',
                409
            );
        }

        $identityStmt = $pdo->prepare("
            SELECT submission_id
            FROM guest_chat_identities
            WHERE id = ?
              AND submission_id = ?
              AND revoked_at IS NULL
              AND expires_at > NOW()
            LIMIT 1
        ");
        $identityStmt->execute([
            (int) $actor['actor_id'],
            $submissionId
        ]);
        if (!$identityStmt->fetchColumn()) {
            throw new ChatHttpException(
                'The guest proof is missing, expired, revoked, or for another submission. Track the submission again to recover access if it has not been revoked.',
                403
            );
        }

        $conversationId = guestClaimGetSubmissionConversation(
            $submissionId,
            (int) $actor['actor_id']
        );
        $conversation = chatRequireConversationMembership(
            $conversationId,
            $actor
        );
        if (
            $conversation['conversation_type'] !== 'private'
            || (int) $conversation['submission_id'] !== $submissionId
            || $conversation['transaction_id'] !== null
        ) {
            throw new ChatHttpException(
                'The submission chat is inconsistent.',
                409
            );
        }

        $proofs = guestClaimSessionProofs();
        $proofs[(string) $submissionId] = [
            'guest_identity_id' => (int) $actor['actor_id'],
            'staged_at' => time()
        ];
        if (count($proofs) > 20) {
            uasort(
                $proofs,
                static fn(array $left, array $right): int =>
                    (int) ($left['staged_at'] ?? 0)
                    <=> (int) ($right['staged_at'] ?? 0)
            );
            $proofs = array_slice($proofs, -20, null, true);
        }
        $_SESSION['guest_submission_claim_proofs'] = $proofs;

        guestClaimRespond(
            true,
            'Submission ownership proof staged in this browser. Continue to Seller account registration or sign in.',
            200
        );
    }

    if ($actor['actor_type'] !== 'seller') {
        throw new ChatHttpException(
            'Sign in to an active Seller account to claim submissions.',
            401
        );
    }
    if (!chatGuestSubmissionClaimsSchemaAvailable()) {
        throw new ChatHttpException(
            'Submission claiming is not available yet.',
            503
        );
    }

    $submissionIdsInput = $_POST['submission_ids'] ?? null;
    if (!is_array($submissionIdsInput) || count($submissionIdsInput) < 1 || count($submissionIdsInput) > 20) {
        throw new ChatHttpException('Select between one and 20 staged submissions.', 422);
    }
    $submissionIds = [];
    foreach ($submissionIdsInput as $submissionIdInput) {
        $submissionIds[] = chatPositiveInteger($submissionIdInput, 'submission ID');
    }
    $submissionIds = array_values(array_unique($submissionIds));
    sort($submissionIds, SORT_NUMERIC);

    $proofs = guestClaimSessionProofs();
    $pdo->beginTransaction();

    $sellerStmt = $pdo->prepare("
        SELECT id, is_active, email_verified_at
        FROM users
        WHERE id = ?
        LIMIT 1
        FOR UPDATE
    ");
    $sellerStmt->execute([(int) $actor['actor_id']]);
    $seller = $sellerStmt->fetch(PDO::FETCH_ASSOC);
    if (!$seller || (int) $seller['is_active'] !== 1) {
        throw new ChatHttpException('The Seller account is unavailable.', 401);
    }
    if ($seller['email_verified_at'] === null) {
        throw new ChatHttpException(
            'Verify your email address before claiming submissions.',
            403
        );
    }

    $submissions = [];
    $identityIds = [];
    foreach ($submissionIds as $submissionId) {
        $submissionStmt = $pdo->prepare("
            SELECT id, user_id, status
            FROM submissions
            WHERE id = ?
            LIMIT 1
            FOR UPDATE
        ");
        $submissionStmt->execute([$submissionId]);
        $submission = $submissionStmt->fetch(PDO::FETCH_ASSOC);
        if (!$submission) {
            throw new ChatHttpException(
                'One or more selected submissions can no longer be claimed.',
                409
            );
        }
        $submissions[$submissionId] = $submission;

        $proof = $proofs[(string) $submissionId] ?? null;
        if (is_array($proof)) {
            $identityId = filter_var(
                $proof['guest_identity_id'] ?? null,
                FILTER_VALIDATE_INT
            );
            if ($identityId !== false && $identityId > 0) {
                $identityIds[$submissionId] = $identityId;
            }
        }
    }

    $claimedCount = 0;
    $idempotentIds = [];
    foreach ($submissionIds as $submissionId) {
        $submission = $submissions[$submissionId];
        $auditStmt = $pdo->prepare("
            SELECT seller_id, guest_identity_id
            FROM guest_submission_claims
            WHERE submission_id = ?
            LIMIT 1
            FOR UPDATE
        ");
        $auditStmt->execute([$submissionId]);
        $existingClaim = $auditStmt->fetch(PDO::FETCH_ASSOC);
        if ($existingClaim) {
            if (
                $submission['user_id'] === null
                || (int) $submission['user_id'] !== (int) $actor['actor_id']
                || (int) $existingClaim['seller_id'] !== (int) $actor['actor_id']
            ) {
                throw new ChatHttpException(
                    'One or more selected submissions can no longer be claimed.',
                    409
                );
            }
            $conversationStmt = $pdo->prepare("
                SELECT id
                FROM conversations
                WHERE type = 'private'
                  AND transaction_id IS NULL
                  AND submission_id = ?
                LIMIT 1
                FOR UPDATE
            ");
            $conversationStmt->execute([$submissionId]);
            $conversationId = $conversationStmt->fetchColumn();
            if ($conversationId === false) {
                throw new ChatHttpException(
                    'One or more claimed submission conversations are inconsistent.',
                    409
                );
            }
            chatRequireConversationMembership($conversationId, $actor);
            $idempotentIds[] = $submissionId;
            continue;
        }

        $proof = $proofs[(string) $submissionId] ?? null;
        $stagedAt = is_array($proof)
            ? filter_var($proof['staged_at'] ?? null, FILTER_VALIDATE_INT)
            : false;
        $identityId = $identityIds[$submissionId] ?? 0;
        if (
            $submission['user_id'] !== null
            || $submission['status'] !== 'accepted'
            || $stagedAt === false
            || $stagedAt <= 0
            || time() - $stagedAt > 1800
            || $identityId <= 0
        ) {
            throw new ChatHttpException(
                'One or more staged proofs are missing or expired. Track and stage each eligible submission again.',
                403
            );
        }

        $identityStmt = $pdo->prepare("
            SELECT id, submission_id, revoked_at, expires_at > NOW() AS is_active
            FROM guest_chat_identities
            WHERE id = ?
              AND submission_id = ?
            LIMIT 1
            FOR UPDATE
        ");
        $identityStmt->execute([$identityId, $submissionId]);
        $identity = $identityStmt->fetch(PDO::FETCH_ASSOC);
        if (
            !$identity
            || $identity['revoked_at'] !== null
            || !(bool) $identity['is_active']
        ) {
            throw new ChatHttpException(
                'One or more guest proofs have expired or been revoked.',
                403
            );
        }

        $responsibleAdminId =
            chatGetSubmissionResponsibleAdminId($submissionId);
        $conversationId = guestClaimGetSubmissionConversation(
            $submissionId,
            $identityId
        );
        $guestActor = [
            'actor_type' => 'guest',
            'actor_id' => $identityId
        ];
        $conversation = chatRequireConversationMembership(
            $conversationId,
            $guestActor
        );
        if (
            $conversation['conversation_type'] !== 'private'
            || (int) $conversation['submission_id'] !== $submissionId
            || $conversation['transaction_id'] !== null
            || (int) $responsibleAdminId <= 0
        ) {
            throw new ChatHttpException(
                'One or more submission conversations are inconsistent.',
                409
            );
        }

        $transferStmt = $pdo->prepare("
            UPDATE submissions
            SET user_id = ?
            WHERE id = ?
              AND user_id IS NULL
        ");
        $transferStmt->execute([(int) $actor['actor_id'], $submissionId]);
        if ($transferStmt->rowCount() !== 1) {
            throw new ChatHttpException(
                'One or more selected submissions can no longer be claimed.',
                409
            );
        }

        $auditInsertStmt = $pdo->prepare("
            INSERT INTO guest_submission_claims (
                submission_id,
                seller_id,
                guest_identity_id,
                proof_method
            )
            VALUES (?, ?, ?, 'guest_chat_capability')
        ");
        $auditInsertStmt->execute([
            $submissionId,
            (int) $actor['actor_id'],
            $identityId
        ]);

        $deliveryTransferStmt = $pdo->prepare("
            UPDATE message_deliveries deliveries
            INNER JOIN messages m
                ON m.id = deliveries.message_id
            SET deliveries.recipient_type = 'seller',
                deliveries.recipient_id = ?
            WHERE m.conversation_id = ?
              AND deliveries.recipient_type = 'guest'
              AND deliveries.recipient_id = ?
        ");
        $deliveryTransferStmt->execute([
            (int) $actor['actor_id'],
            $conversationId,
            $identityId
        ]);

        $reactionTransferStmt = $pdo->prepare("
            UPDATE message_reactions reactions
            INNER JOIN messages m
                ON m.id = reactions.message_id
            SET reactions.actor_type = 'seller',
                reactions.actor_id = ?
            WHERE m.conversation_id = ?
              AND reactions.actor_type = 'guest'
              AND reactions.actor_id = ?
        ");
        $reactionTransferStmt->execute([
            (int) $actor['actor_id'],
            $conversationId,
            $identityId
        ]);

        $participantTransferStmt = $pdo->prepare("
            UPDATE conversation_participants
            SET actor_type = 'seller',
                actor_id = ?
            WHERE conversation_id = ?
              AND actor_type = 'guest'
              AND actor_id = ?
        ");
        $participantTransferStmt->execute([
            (int) $actor['actor_id'],
            $conversationId,
            $identityId
        ]);
        if ($participantTransferStmt->rowCount() !== 1) {
            throw new ChatHttpException(
                'One or more submission conversations are inconsistent.',
                409
            );
        }

        $revokeStmt = $pdo->prepare("
            UPDATE guest_chat_identities
            SET revoked_at = NOW()
            WHERE id = ?
              AND submission_id = ?
              AND revoked_at IS NULL
              AND expires_at > NOW()
        ");
        $revokeStmt->execute([$identityId, $submissionId]);
        if ($revokeStmt->rowCount() !== 1) {
            throw new ChatHttpException(
                'Guest proof changed while the claim was being processed.',
                409
            );
        }

        $claimedCount++;
    }

    $pdo->commit();
    foreach ($submissionIds as $submissionId) {
        unset($proofs[(string) $submissionId]);
    }
    if ($proofs) {
        $_SESSION['guest_submission_claim_proofs'] = $proofs;
    } else {
        unset($_SESSION['guest_submission_claim_proofs']);
    }

    guestClaimRespond(
        true,
        $claimedCount > 0
            ? $claimedCount . ' submission' . ($claimedCount === 1 ? '' : 's') . ' claimed successfully.'
            : 'These submissions were already claimed by your Seller account.',
        200
    );
} catch (ChatHttpException $e) {
    if (isset($pdo) && $pdo instanceof PDO && $pdo->inTransaction()) {
        $pdo->rollBack();
    }
    guestClaimRespond(false, $e->getMessage(), $e->httpStatus);
} catch (Throwable $e) {
    if (isset($pdo) && $pdo instanceof PDO && $pdo->inTransaction()) {
        $pdo->rollBack();
    }
    error_log(
        'Guest submission claim failed; error type: ' . get_class($e)
    );
    guestClaimRespond(
        false,
        'Unable to complete the claim right now. Please try again later.',
        500
    );
}
