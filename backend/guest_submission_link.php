<?php

function guestSubmissionLinkLockSuffix(PDO $pdo): string
{
    return $pdo->getAttribute(PDO::ATTR_DRIVER_NAME) === 'mysql'
        ? ' FOR UPDATE'
        : '';
}

function guestSubmissionLinkAuditSchemaAvailable(PDO $pdo): bool
{
    if ($pdo->getAttribute(PDO::ATTR_DRIVER_NAME) === 'sqlite') {
        $stmt = $pdo->prepare("
            SELECT COUNT(*)
            FROM sqlite_master
            WHERE type = 'table'
              AND name = 'guest_submission_claims'
        ");
    } else {
        $stmt = $pdo->prepare("
            SELECT COUNT(*)
            FROM information_schema.TABLES
            WHERE TABLE_SCHEMA = DATABASE()
              AND TABLE_NAME = 'guest_submission_claims'
        ");
    }

    $stmt->execute();
    return (int) $stmt->fetchColumn() === 1;
}

function guestSubmissionResponsibleAdminId(
    PDO $pdo,
    int $submissionId
): ?int {
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
        " . guestSubmissionLinkLockSuffix($pdo)
    );
    $stmt->execute([$submissionId]);
    $transactions = $stmt->fetchAll(PDO::FETCH_ASSOC);
    if (
        count($transactions) !== 1
        || (int) $transactions[0]['submission_id'] !== $submissionId
        || $transactions[0]['offer_id'] !== null
            && (int) $transactions[0]['offer_submission_id'] !== $submissionId
    ) {
        return null;
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
        return null;
    }

    $adminId = (int) array_key_first($adminIds);
    $adminStmt = $pdo->prepare("
        SELECT id
        FROM admins
        WHERE id = ?
        LIMIT 1
        " . guestSubmissionLinkLockSuffix($pdo)
    );
    $adminStmt->execute([$adminId]);

    return $adminStmt->fetchColumn() === false ? null : $adminId;
}

function guestSubmissionSellerDisplayName(?string $displayName): string
{
    return trim((string) $displayName) ?: 'Seller';
}

function linkVerifiedGuestSubmissions(PDO $pdo, int $sellerId): int
{
    if (!$pdo->inTransaction()) {
        throw new LogicException(
            'Guest submissions must be linked inside the email verification transaction.'
        );
    }
    if (!guestSubmissionLinkAuditSchemaAvailable($pdo)) {
        throw new RuntimeException(
            'Guest submission linking requires the guest submission claims migration.'
        );
    }

    $lockSuffix = guestSubmissionLinkLockSuffix($pdo);
    $sellerStmt = $pdo->prepare("
        SELECT id, email, email_verified_at, is_active
        FROM users
        WHERE id = ?
        LIMIT 1
        $lockSuffix
    ");
    $sellerStmt->execute([$sellerId]);
    $seller = $sellerStmt->fetch(PDO::FETCH_ASSOC);
    if (
        !$seller
        || (int) $seller['is_active'] !== 1
        || $seller['email_verified_at'] === null
        || trim((string) $seller['email']) === ''
    ) {
        return 0;
    }

    $matchingAccountsStmt = $pdo->prepare("
        SELECT id
        FROM users
        WHERE LOWER(TRIM(email)) = LOWER(TRIM(?))
        ORDER BY id ASC
        $lockSuffix
    ");
    $matchingAccountsStmt->execute([(string) $seller['email']]);
    $matchingAccountIds = array_map(
        'intval',
        $matchingAccountsStmt->fetchAll(PDO::FETCH_COLUMN)
    );
    if (
        count($matchingAccountIds) !== 1
        || $matchingAccountIds[0] !== $sellerId
    ) {
        error_log(
            'Guest submissions were not linked because the verified email matches multiple Seller accounts.'
        );
        return 0;
    }

    $submissionsStmt = $pdo->prepare("
        SELECT s.id, g.id AS guest_identity_id
        FROM submissions s
        INNER JOIN guest_chat_identities g
            ON g.submission_id = s.id
        WHERE LOWER(TRIM(s.seller_email)) = LOWER(TRIM(?))
          AND s.user_id IS NULL
          AND s.status = 'accepted'
          AND g.revoked_at IS NULL
          AND g.expires_at > CURRENT_TIMESTAMP
        ORDER BY s.id ASC
        $lockSuffix
    ");
    $submissionsStmt->execute([(string) $seller['email']]);
    $candidates = $submissionsStmt->fetchAll(PDO::FETCH_ASSOC);
    $linkedCount = 0;
    $skippedCount = 0;

    foreach ($candidates as $candidate) {
        $submissionId = (int) $candidate['id'];
        $identityId = (int) $candidate['guest_identity_id'];

        $claimStmt = $pdo->prepare("
            SELECT seller_id, guest_identity_id
            FROM guest_submission_claims
            WHERE submission_id = ?
            LIMIT 1
            $lockSuffix
        ");
        $claimStmt->execute([$submissionId]);
        if ($claimStmt->fetch(PDO::FETCH_ASSOC)) {
            $skippedCount++;
            continue;
        }

        $conversationStmt = $pdo->prepare("
            SELECT id, type, transaction_id
            FROM conversations
            WHERE submission_id = ?
            LIMIT 2
            $lockSuffix
        ");
        $conversationStmt->execute([$submissionId]);
        $conversations = $conversationStmt->fetchAll(PDO::FETCH_ASSOC);
        if (count($conversations) !== 1) {
            $skippedCount++;
            continue;
        }

        $conversation = $conversations[0];
        if (
            $conversation['type'] !== 'private'
            || $conversation['transaction_id'] !== null
        ) {
            $skippedCount++;
            continue;
        }

        $participantStmt = $pdo->prepare("
            SELECT actor_type, actor_id
            FROM conversation_participants
            WHERE conversation_id = ?
            ORDER BY actor_type, actor_id
            $lockSuffix
        ");
        $participantStmt->execute([(int) $conversation['id']]);
        $participants = $participantStmt->fetchAll(PDO::FETCH_ASSOC);

        $responsibleAdminId = guestSubmissionResponsibleAdminId(
            $pdo,
            $submissionId
        );
        if (
            count($participants) !== 2
            || $responsibleAdminId === null
        ) {
            $skippedCount++;
            continue;
        }

        $hasGuestParticipant = false;
        $hasResponsibleAdminParticipant = false;
        foreach ($participants as $participant) {
            if (
                $participant['actor_type'] === 'guest'
                && (int) $participant['actor_id'] === $identityId
            ) {
                $hasGuestParticipant = true;
            } elseif (
                $participant['actor_type'] === 'admin'
                && (int) $participant['actor_id'] === $responsibleAdminId
            ) {
                $hasResponsibleAdminParticipant = true;
            }
        }
        if (!$hasGuestParticipant || !$hasResponsibleAdminParticipant) {
            $skippedCount++;
            continue;
        }

        $transferStmt = $pdo->prepare("
            UPDATE submissions
            SET user_id = ?
            WHERE id = ?
              AND user_id IS NULL
              AND status = 'accepted'
              AND LOWER(TRIM(seller_email)) = LOWER(TRIM(?))
        ");
        $transferStmt->execute([
            $sellerId,
            $submissionId,
            (string) $seller['email']
        ]);
        if ($transferStmt->rowCount() !== 1) {
            $skippedCount++;
            continue;
        }

        $auditInsertStmt = $pdo->prepare("
            INSERT INTO guest_submission_claims (
                submission_id,
                seller_id,
                guest_identity_id,
                proof_method
            )
            VALUES (?, ?, ?, 'verified_email')
        ");
        $auditInsertStmt->execute([
            $submissionId,
            $sellerId,
            $identityId
        ]);

        $messageTransferStmt = $pdo->prepare("
            UPDATE messages
            SET sender_type = 'seller',
                sender_id = ?
            WHERE conversation_id = ?
              AND sender_type = 'guest'
              AND sender_id = ?
        ");
        $messageTransferStmt->execute([
            $sellerId,
            (int) $conversation['id'],
            $identityId
        ]);

        $deliveryTransferStmt = $pdo->prepare("
            UPDATE message_deliveries
            SET recipient_type = 'seller',
                recipient_id = ?
            WHERE recipient_type = 'guest'
              AND recipient_id = ?
              AND message_id IN (
                  SELECT id
                  FROM messages
                  WHERE conversation_id = ?
              )
        ");
        $deliveryTransferStmt->execute([
            $sellerId,
            $identityId,
            (int) $conversation['id']
        ]);

        $reactionTransferStmt = $pdo->prepare("
            UPDATE message_reactions
            SET actor_type = 'seller',
                actor_id = ?
            WHERE actor_type = 'guest'
              AND actor_id = ?
              AND message_id IN (
                  SELECT id
                  FROM messages
                  WHERE conversation_id = ?
              )
        ");
        $reactionTransferStmt->execute([
            $sellerId,
            $identityId,
            (int) $conversation['id']
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
            $sellerId,
            (int) $conversation['id'],
            $identityId
        ]);
        if ($participantTransferStmt->rowCount() !== 1) {
            throw new RuntimeException(
                'The guest conversation participant could not be transferred.'
            );
        }

        $revokeStmt = $pdo->prepare("
            UPDATE guest_chat_identities
            SET revoked_at = CURRENT_TIMESTAMP
            WHERE id = ?
              AND submission_id = ?
              AND revoked_at IS NULL
              AND expires_at > CURRENT_TIMESTAMP
        ");
        $revokeStmt->execute([$identityId, $submissionId]);
        if ($revokeStmt->rowCount() !== 1) {
            throw new RuntimeException(
                'The guest identity changed while its submission was being linked.'
            );
        }

        $linkedCount++;
    }

    if ($skippedCount > 0) {
        error_log(
            'Guest submission auto-link skipped ' .
            $skippedCount .
            ' submission(s) after ownership or chat integrity checks.'
        );
    }

    return $linkedCount;
}

function linkVerifiedGuestSubmissionsAfterSignIn(
    PDO $pdo,
    int $sellerId
): int {
    if ($pdo->inTransaction()) {
        throw new LogicException(
            'Guest submissions must be linked in their own transaction after sign-in.'
        );
    }

    $pdo->beginTransaction();
    try {
        $linkedCount = linkVerifiedGuestSubmissions($pdo, $sellerId);
        $pdo->commit();
        return $linkedCount;
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        throw $e;
    }
}
