<?php

require_once __DIR__ . '/../backend/guest_submission_link.php';

function testAssert(bool $condition, string $message): void
{
    if (!$condition) {
        throw new RuntimeException($message);
    }
}

function createTestDatabase(): PDO
{
    $pdo = new PDO('sqlite::memory:');
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);

    foreach ([
        "CREATE TABLE users (
            id INTEGER PRIMARY KEY,
            email TEXT NOT NULL,
            email_verified_at TEXT NULL,
            is_active INTEGER NOT NULL
        )",
        "CREATE TABLE submissions (
            id INTEGER PRIMARY KEY,
            user_id INTEGER NULL,
            seller_email TEXT NULL,
            status TEXT NOT NULL,
            assigned_admin_id INTEGER NULL
        )",
        "CREATE TABLE guest_chat_identities (
            id INTEGER PRIMARY KEY,
            submission_id INTEGER NOT NULL,
            expires_at TEXT NOT NULL,
            revoked_at TEXT NULL
        )",
        "CREATE TABLE transactions (
            id INTEGER PRIMARY KEY,
            submission_id INTEGER NOT NULL,
            offer_id INTEGER NULL,
            processed_by_admin_id INTEGER NULL
        )",
        "CREATE TABLE offers (
            id INTEGER PRIMARY KEY,
            submission_id INTEGER NOT NULL,
            sender_type TEXT NOT NULL DEFAULT 'admin',
            created_by_admin_id INTEGER NULL
        )",
        "CREATE TABLE admins (id INTEGER PRIMARY KEY)",
        "CREATE TABLE guest_submission_claims (
            submission_id INTEGER PRIMARY KEY,
            seller_id INTEGER NOT NULL,
            guest_identity_id INTEGER NOT NULL,
            proof_method TEXT NOT NULL
        )",
        "CREATE TABLE conversations (
            id INTEGER PRIMARY KEY,
            type TEXT NOT NULL,
            transaction_id INTEGER NULL,
            submission_id INTEGER NULL
        )",
        "CREATE TABLE conversation_participants (
            id INTEGER PRIMARY KEY,
            conversation_id INTEGER NOT NULL,
            actor_type TEXT NOT NULL,
            actor_id INTEGER NOT NULL,
            last_read_message_id INTEGER NULL,
            UNIQUE (conversation_id, actor_type, actor_id)
        )",
        "CREATE TABLE messages (
            id INTEGER PRIMARY KEY,
            conversation_id INTEGER NOT NULL,
            sender_type TEXT NOT NULL,
            sender_id INTEGER NOT NULL
        )",
        "CREATE TABLE message_deliveries (
            message_id INTEGER NOT NULL,
            recipient_type TEXT NOT NULL,
            recipient_id INTEGER NOT NULL,
            delivered_at TEXT NOT NULL,
            PRIMARY KEY (message_id, recipient_type, recipient_id)
        )",
        "CREATE TABLE message_reactions (
            message_id INTEGER NOT NULL,
            actor_type TEXT NOT NULL,
            actor_id INTEGER NOT NULL,
            reaction TEXT NOT NULL,
            UNIQUE (message_id, actor_type, actor_id, reaction)
        )",
        "CREATE TABLE message_attachments (
            id INTEGER PRIMARY KEY,
            message_id INTEGER NOT NULL,
            storage_key TEXT NOT NULL
        )"
    ] as $sql) {
        $pdo->exec($sql);
    }

    $pdo->exec("
        INSERT INTO users (id, email, email_verified_at, is_active)
        VALUES (1, 'seller@example.test', '2026-10-12 12:00:00', 1)
    ");

    return $pdo;
}

function seedEligibleSubmission(PDO $pdo, int $submissionId = 101): void
{
    $pdo->prepare("
        INSERT INTO submissions (
            id, user_id, seller_email, status, assigned_admin_id
        )
        VALUES (?, NULL, 'Seller@Example.Test', 'accepted', 9)
    ")->execute([$submissionId]);

    $pdo->prepare("
        INSERT INTO guest_chat_identities (
            id, submission_id, expires_at, revoked_at
        )
        VALUES (?, ?, datetime('now', '+1 day'), NULL)
    ")->execute([$submissionId + 10, $submissionId]);

    $pdo->prepare("
        INSERT INTO transactions (
            id, submission_id, offer_id, processed_by_admin_id
        )
        VALUES (?, ?, NULL, 9)
    ")->execute([$submissionId + 20, $submissionId]);
    $pdo->prepare("INSERT INTO admins (id) VALUES (9)")->execute();

    $pdo->prepare("
        INSERT INTO conversations (id, type, transaction_id, submission_id)
        VALUES (?, 'private', NULL, ?)
    ")->execute([$submissionId + 30, $submissionId]);
    $pdo->prepare("
        INSERT INTO conversation_participants (
            id, conversation_id, actor_type, actor_id, last_read_message_id
        )
        VALUES
            (?, ?, 'guest', ?, 57),
            (?, ?, 'admin', 9, 81)
    ")->execute([
        $submissionId + 40,
        $submissionId + 30,
        $submissionId + 10,
        $submissionId + 41,
        $submissionId + 30
    ]);
    $pdo->prepare("
        INSERT INTO messages (id, conversation_id, sender_type, sender_id)
        VALUES (?, ?, 'guest', ?)
    ")->execute([
        $submissionId + 50,
        $submissionId + 30,
        $submissionId + 10
    ]);
    $pdo->prepare("
        INSERT INTO message_deliveries (
            message_id, recipient_type, recipient_id, delivered_at
        )
        VALUES (?, 'guest', ?, '2026-10-12 12:01:00')
    ")->execute([$submissionId + 50, $submissionId + 10]);
    $pdo->prepare("
        INSERT INTO message_reactions (
            message_id, actor_type, actor_id, reaction
        )
        VALUES (?, 'guest', ?, '👍')
    ")->execute([$submissionId + 50, $submissionId + 10]);
    $pdo->prepare("
        INSERT INTO message_attachments (id, message_id, storage_key)
        VALUES (?, ?, 'preserved-object')
    ")->execute([$submissionId + 60, $submissionId + 50]);
}

function runTest(string $name, callable $test): void
{
    $test();
    echo "PASS $name\n";
}

runTest('links verified guest submissions and preserves chat history', static function (): void {
    $pdo = createTestDatabase();
    seedEligibleSubmission($pdo);

    $pdo->beginTransaction();
    $linked = linkVerifiedGuestSubmissions($pdo, 1);
    $pdo->commit();

    testAssert($linked === 1, 'Expected one guest submission to link.');
    testAssert(
        (int) $pdo->query('SELECT user_id FROM submissions WHERE id = 101')->fetchColumn() === 1,
        'Expected ownership to be assigned to the verified Seller.'
    );
    testAssert(
        $pdo->query('SELECT proof_method FROM guest_submission_claims WHERE submission_id = 101')->fetchColumn()
            === 'verified_email',
        'Expected the audit to record email-verification proof.'
    );
    testAssert(
        (int) $pdo->query("
            SELECT actor_id
            FROM conversation_participants
            WHERE conversation_id = 131 AND actor_type = 'seller'
        ")->fetchColumn() === 1,
        'Expected the existing guest participant to become the Seller.'
    );
    testAssert(
        (int) $pdo->query("
            SELECT last_read_message_id
            FROM conversation_participants
            WHERE conversation_id = 131 AND actor_type = 'seller'
        ")->fetchColumn() === 57,
        'Expected the guest read marker to remain on its participant row.'
    );
    testAssert(
        $pdo->query("
            SELECT sender_type
            FROM messages
            WHERE id = 151
        ")->fetchColumn() === 'seller',
        'Expected existing guest-authored messages to retain their IDs and become Seller-authored.'
    );
    testAssert(
        (int) $pdo->query("
            SELECT recipient_id
            FROM message_deliveries
            WHERE message_id = 151
        ")->fetchColumn() === 1,
        'Expected existing delivery state to transfer to the Seller.'
    );
    testAssert(
        (int) $pdo->query("
            SELECT actor_id
            FROM message_reactions
            WHERE message_id = 151
        ")->fetchColumn() === 1,
        'Expected existing reactions to transfer to the Seller.'
    );
    testAssert(
        (int) $pdo->query("
            SELECT COUNT(*)
            FROM message_attachments
            WHERE message_id = 151 AND storage_key = 'preserved-object'
        ")->fetchColumn() === 1,
        'Expected the existing message attachment to remain linked.'
    );
    testAssert(
        (int) $pdo->query("
            SELECT COUNT(*)
            FROM conversations
            WHERE submission_id = 101
        ")->fetchColumn() === 1,
        'Expected the existing submission conversation to be reused.'
    );
    testAssert(
        (int) $pdo->query("
            SELECT COUNT(*)
            FROM transactions t
            INNER JOIN submissions s
                ON s.id = t.submission_id
            WHERE s.user_id = 1
        ")->fetchColumn() === 1,
        'Expected the accepted transaction to appear under the registered Seller.'
    );
    testAssert(
        (int) $pdo->query("
            SELECT COUNT(*)
            FROM conversations
            WHERE transaction_id = 121
        ")->fetchColumn() === 0,
        'Automatic linking must not create a duplicate transaction conversation.'
    );
    testAssert(
        guestSubmissionSellerDisplayName('  Ruth Seller ') === 'Ruth Seller'
            && guestSubmissionSellerDisplayName('  ') === 'Seller',
        'Expected the registered Seller name with a safe fallback.'
    );
});

runTest('links eligible submissions when an existing verified Seller signs in', static function (): void {
    $pdo = createTestDatabase();
    seedEligibleSubmission($pdo);

    $linked = linkVerifiedGuestSubmissionsAfterSignIn($pdo, 1);

    testAssert($linked === 1, 'Expected sign-in to link the eligible guest submission.');
    testAssert(
        (int) $pdo->query('SELECT user_id FROM submissions WHERE id = 101')->fetchColumn() === 1,
        'Expected the existing verified Seller to become the submission owner.'
    );
});

runTest('sign-in does not link submissions for an unverified Seller', static function (): void {
    $pdo = createTestDatabase();
    seedEligibleSubmission($pdo);
    $pdo->exec("UPDATE users SET email_verified_at = NULL WHERE id = 1");

    $linked = linkVerifiedGuestSubmissionsAfterSignIn($pdo, 1);

    testAssert($linked === 0, 'An unverified Seller must not link guest submissions.');
    testAssert(
        $pdo->query('SELECT user_id FROM submissions WHERE id = 101')->fetchColumn() === null,
        'An unverified Seller must not become the submission owner.'
    );
});

runTest('does not link before Seller email verification', static function (): void {
    $pdo = createTestDatabase();
    seedEligibleSubmission($pdo);
    $pdo->exec("UPDATE users SET email_verified_at = NULL WHERE id = 1");

    $pdo->beginTransaction();
    $linked = linkVerifiedGuestSubmissions($pdo, 1);
    $pdo->commit();

    testAssert($linked === 0, 'An unverified account must not link submissions.');
    testAssert(
        $pdo->query('SELECT user_id FROM submissions WHERE id = 101')->fetchColumn() === null,
        'An unverified account must not become the submission owner.'
    );
});

runTest('does not take over a submission owned by another Seller', static function (): void {
    $pdo = createTestDatabase();
    $pdo->exec("
        INSERT INTO users (id, email, email_verified_at, is_active)
        VALUES (2, 'other@example.test', '2026-10-12 12:00:00', 1)
    ");
    seedEligibleSubmission($pdo);
    $pdo->exec('UPDATE submissions SET user_id = 2 WHERE id = 101');

    $pdo->beginTransaction();
    $linked = linkVerifiedGuestSubmissions($pdo, 1);
    $pdo->commit();

    testAssert($linked === 0, 'Conflicting ownership must not be linked.');
    testAssert(
        (int) $pdo->query('SELECT user_id FROM submissions WHERE id = 101')->fetchColumn() === 2,
        'The existing Seller owner must remain unchanged.'
    );
});

runTest('requires the guest identity to own the existing Admin conversation', static function (): void {
    $pdo = createTestDatabase();
    seedEligibleSubmission($pdo);
    $pdo->exec("
        UPDATE conversation_participants
        SET actor_id = 999
        WHERE conversation_id = 131 AND actor_type = 'guest'
    ");

    $pdo->beginTransaction();
    $linked = linkVerifiedGuestSubmissions($pdo, 1);
    $pdo->commit();

    testAssert($linked === 0, 'A mismatched guest identity must not link.');
    testAssert(
        $pdo->query('SELECT user_id FROM submissions WHERE id = 101')->fetchColumn() === null,
        'A mismatched identity must leave submission ownership unchanged.'
    );
});

runTest('is idempotent on a repeated verification callback', static function (): void {
    $pdo = createTestDatabase();
    seedEligibleSubmission($pdo);

    $pdo->beginTransaction();
    $firstLinkCount = linkVerifiedGuestSubmissions($pdo, 1);
    $pdo->commit();
    $pdo->beginTransaction();
    $retryLinkCount = linkVerifiedGuestSubmissions($pdo, 1);
    $pdo->commit();

    testAssert($firstLinkCount === 1, 'Expected the first callback to link the submission.');
    testAssert($retryLinkCount === 0, 'A repeated callback must not relink the submission.');
    testAssert(
        (int) $pdo->query('SELECT COUNT(*) FROM guest_submission_claims WHERE submission_id = 101')->fetchColumn() === 1,
        'A repeated callback must not create duplicate audit rows.'
    );
});

runTest('does not link an ambiguous duplicate email identity', static function (): void {
    $pdo = createTestDatabase();
    $pdo->exec("
        INSERT INTO users (id, email, email_verified_at, is_active)
        VALUES (2, ' SELLER@example.test ', '2026-10-12 12:00:00', 1)
    ");
    seedEligibleSubmission($pdo);

    $pdo->beginTransaction();
    $linked = linkVerifiedGuestSubmissions($pdo, 1);
    $pdo->commit();

    testAssert($linked === 0, 'Duplicate matching Seller identities must block linking.');
    testAssert(
        $pdo->query('SELECT user_id FROM submissions WHERE id = 101')->fetchColumn() === null,
        'Ambiguous email ownership must leave the submission unassigned.'
    );
});
