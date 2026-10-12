<?php

require_once __DIR__ . '/../../backend/database.php';
require_once __DIR__ . '/../../backend/session.php';
require_once __DIR__ . '/../../backend/csrf.php';
require_once __DIR__ . '/../../backend/chat_service.php';

if (
    empty($_SESSION['user_logged_in']) ||
    empty($_SESSION['user_id'])
) {
    http_response_code(401);

    echo '
        <div class="portal-error">
            <h2>Session expired</h2>
            <p>Please log in again.</p>
        </div>
    ';

    exit;
}

$userId = (int) $_SESSION['user_id'];

if ($userId <= 0) {
    http_response_code(401);

    echo '
        <div class="portal-error">
            <h2>Invalid session</h2>
            <p>Please log in again.</p>
        </div>
    ';

    exit;
}


/*
|--------------------------------------------------------------------------
| GET CURRENT USER
|--------------------------------------------------------------------------
*/

try {

    $userStmt = $pdo->prepare("
        SELECT
            id,
            full_name,
            mobile,
            email,
            email_verified_at,
            profile_picture,
            language,
            is_active,
            created_at
        FROM users
        WHERE id = ?
        LIMIT 1
    ");

    $userStmt->execute([$userId]);

    $user = $userStmt->fetch();

    if (!$user) {
        http_response_code(404);

        echo '
            <div class="portal-error">
                <h2>User account not found</h2>
                <p>Please contact VNZ TECH.</p>
            </div>
        ';

        exit;
    }

    if ((int) $user['is_active'] !== 1) {
        http_response_code(403);

        echo '
            <div class="portal-error">
                <h2>Account inactive</h2>
                <p>Your account is currently inactive.</p>
            </div>
        ';

        exit;
    }


    /*
    |--------------------------------------------------------------------------
    | TOTAL SUBMISSIONS
    |--------------------------------------------------------------------------
    */

    $submissionCountStmt = $pdo->prepare("
        SELECT COUNT(*)
        FROM submissions
        WHERE user_id = ?
    ");

    $submissionCountStmt->execute([$userId]);

    $submissionCount = (int) $submissionCountStmt->fetchColumn();


    /*
    |--------------------------------------------------------------------------
    | ACTIVE OFFERS
    |--------------------------------------------------------------------------
    */

    $activeOffersStmt = $pdo->prepare("
        SELECT COUNT(*)
        FROM offers o
        INNER JOIN submissions s
            ON s.id = o.submission_id
        WHERE s.user_id = ?
          AND o.status IN ('sent', 'pending')
    ");

    $activeOffersStmt->execute([$userId]);

    $activeOffers = (int) $activeOffersStmt->fetchColumn();


    /*
    |--------------------------------------------------------------------------
    | COMPLETED TRANSACTIONS
    |--------------------------------------------------------------------------
    */

    $completedTransactionsStmt = $pdo->prepare("
        SELECT COUNT(*)
        FROM transactions t
        INNER JOIN submissions s
            ON s.id = t.submission_id
        WHERE s.user_id = ?
          AND t.transaction_status = 'completed'
    ");

    $completedTransactionsStmt->execute([$userId]);

    $completedTransactions = (int) $completedTransactionsStmt->fetchColumn();


    /*
    |--------------------------------------------------------------------------
    | TOTAL PAID
    |--------------------------------------------------------------------------
    */

    $totalPaidStmt = $pdo->prepare("
        SELECT COALESCE(SUM(t.final_amount), 0)
        FROM transactions t
        INNER JOIN submissions s
            ON s.id = t.submission_id
        WHERE s.user_id = ?
          AND t.transaction_status = 'completed'
    ");

    $totalPaidStmt->execute([$userId]);

    $totalPaid = (float) $totalPaidStmt->fetchColumn();


    /*
    |--------------------------------------------------------------------------
    | RECENT SUBMISSIONS
    |--------------------------------------------------------------------------
    */

    $recentSubmissionsStmt = $pdo->prepare("
        SELECT
            s.id,
            s.reference_number,
            s.status,
            s.selling_method,
            s.created_at,

            (
                SELECT COUNT(*)
                FROM submission_items si
                WHERE si.submission_id = s.id
            ) AS item_count,

            (
                SELECT COALESCE(SUM(si.quantity), 0)
                FROM submission_items si
                WHERE si.submission_id = s.id
            ) AS total_quantity,

            (
                SELECT COALESCE(
                    SUM(
                        si.seller_asking_price * si.quantity
                    ),
                    0
                )
                FROM submission_items si
                WHERE si.submission_id = s.id
            ) AS asking_total

        FROM submissions s

        WHERE s.user_id = ?

        ORDER BY s.created_at DESC

        LIMIT 5
    ");

    $recentSubmissionsStmt->execute([$userId]);

    $recentSubmissions = $recentSubmissionsStmt->fetchAll();


    /*
    |--------------------------------------------------------------------------
    | RECENT TRANSACTIONS
    |--------------------------------------------------------------------------
    */

    $recentTransactionsStmt = $pdo->prepare("
        SELECT
            t.id,
            t.transaction_reference,
            t.final_amount,
            t.payment_method,
            t.payment_status,
            t.transaction_status,
            t.completed_at,
            s.reference_number

        FROM transactions t

        INNER JOIN submissions s
            ON s.id = t.submission_id

        WHERE s.user_id = ?

        ORDER BY t.created_at DESC

        LIMIT 5
    ");

    $recentTransactionsStmt->execute([$userId]);

    $recentTransactions = $recentTransactionsStmt->fetchAll();

    $claimableGuestSubmissions = [];
    $stagedProofs = $_SESSION['guest_submission_claim_proofs'] ?? [];
    if (is_array($stagedProofs) && $stagedProofs && chatGuestSubmissionClaimsSchemaAvailable()) {
        $stagedIds = [];
        $now = time();
        foreach ($stagedProofs as $stagedId => $proof) {
            $stagedAt = is_array($proof)
                ? filter_var($proof['staged_at'] ?? null, FILTER_VALIDATE_INT)
                : false;
            $identityId = is_array($proof)
                ? filter_var($proof['guest_identity_id'] ?? null, FILTER_VALIDATE_INT)
                : false;
            if (
                !ctype_digit((string) $stagedId)
                || $stagedAt === false
                || $stagedAt <= 0
                || $now - $stagedAt > 1800
                || $identityId === false
                || $identityId <= 0
            ) {
                unset($stagedProofs[$stagedId]);
                continue;
            }
            $stagedIds[(int) $stagedId] = (int) $identityId;
        }

        if ($stagedIds) {
            $placeholders = implode(',', array_fill(0, count($stagedIds), '?'));
            $claimableStmt = $pdo->prepare("
                SELECT s.id, s.reference_number, s.status, g.id AS guest_identity_id
                FROM submissions s
                INNER JOIN guest_chat_identities g
                    ON g.submission_id = s.id
                WHERE s.id IN ($placeholders)
                  AND s.user_id IS NULL
                  AND s.status = 'accepted'
                  AND g.revoked_at IS NULL
                  AND g.expires_at > NOW()
                ORDER BY s.created_at DESC
            ");
            $claimableStmt->execute(array_keys($stagedIds));
            foreach ($claimableStmt->fetchAll(PDO::FETCH_ASSOC) as $claimable) {
                $submissionId = (int) $claimable['id'];
                if (
                    (int) $claimable['guest_identity_id'] === ($stagedIds[$submissionId] ?? 0)
                ) {
                    $claimableGuestSubmissions[] = $claimable;
                }
            }
        }
        $_SESSION['guest_submission_claim_proofs'] = $stagedProofs;
    }

} catch (Throwable $e) {

    http_response_code(500);

    echo '
        <div class="portal-error">
            <h2>Unable to load dashboard</h2>
            <p>
                Something went wrong while loading
                your dashboard.
            </p>
        </div>
    ';

    exit;
}


/*
|--------------------------------------------------------------------------
| HELPERS
|--------------------------------------------------------------------------
*/

function dashboardEscape($value): string
{
    return htmlspecialchars(
        (string) ($value ?? ''),
        ENT_QUOTES,
        'UTF-8'
    );
}


function dashboardMoney($amount): string
{
    return '₱' . number_format(
        (float) $amount,
        2
    );
}


function dashboardStatusLabel($status): string
{
    $status = strtolower(
        trim((string) $status)
    );

    return ucwords(
        str_replace(
            '_',
            ' ',
            $status
        )
    );
}


function dashboardStatusClass($status): string
{
    $status = strtolower(
        trim((string) $status)
    );

    $allowed = [
        'new_submission',
        'under_review',
        'offer_pending',
        'offer_sent',
        'accepted',
        'declined',
        'cancelled',
        'completed'
    ];

    if (
        in_array(
            $status,
            $allowed,
            true
        )
    ) {
        return $status;
    }

    return 'default';
}


function dashboardDate($date): string
{
    if (empty($date)) {
        return '—';
    }

    $timestamp = strtotime($date);

    if (!$timestamp) {
        return '—';
    }

    return date(
        'M d, Y',
        $timestamp
    );
}


function dashboardTime($date): string
{
    if (empty($date)) {
        return '';
    }

    $timestamp = strtotime($date);

    if (!$timestamp) {
        return '';
    }

    return date(
        'h:i A',
        $timestamp
    );
}


function dashboardProfilePicture($profilePicture): string
{
    if (
        !empty($profilePicture) &&
        is_string($profilePicture)
    ) {
        $profilePicture = trim(
            $profilePicture
        );

        if ($profilePicture !== '') {
            $normalizedPath = str_replace(
                '\\',
                '/',
                $profilePicture
            );

            if (
                str_starts_with(
                    $normalizedPath,
                    'http://'
                ) ||
                str_starts_with(
                    $normalizedPath,
                    'https://'
                ) ||
                str_starts_with(
                    $normalizedPath,
                    '/'
                )
            ) {
                return $normalizedPath;
            }

            return '/VNZ-TECH/' .
                ltrim(
                    $normalizedPath,
                    '/'
                );
        }
    }

    return '';
}


/*
|--------------------------------------------------------------------------
| DISPLAY DATA
|--------------------------------------------------------------------------
*/

$fullName = trim(
    (string) ($user['full_name'] ?? '')
);

if ($fullName === '') {
    $fullName = 'Seller';
}

$firstName = explode(
    ' ',
    $fullName
)[0];

$profilePicture = dashboardProfilePicture(
    $user['profile_picture'] ?? ''
);

$initial = strtoupper(
    substr(
        $fullName,
        0,
        1
    )
);

?>

<section class="dashboard-page">

    <?php if ($claimableGuestSubmissions): ?>
        <section class="guest-claim-card" aria-labelledby="guestClaimTitle">
            <div class="guest-claim-heading">
                <span class="eyebrow">GUEST SUBMISSIONS</span>
                <h2 id="guestClaimTitle">Link your eligible submissions</h2>
                <p>These submissions were verified from this browser. Select the ones to move into this Seller account; their existing Admin chat history will be preserved.</p>
            </div>
            <form data-guest-claim-form>
                <input type="hidden" name="action" value="claim">
                <input type="hidden" name="csrf_token" value="<?= dashboardEscape(generateCsrfToken()) ?>">
                <fieldset <?= empty($user['email_verified_at']) ? 'disabled' : '' ?>>
                    <legend class="visually-hidden">Select submissions to claim</legend>
                    <?php foreach ($claimableGuestSubmissions as $claimable): ?>
                        <label class="guest-claim-option">
                            <input
                                type="checkbox"
                                name="submission_ids[]"
                                value="<?= (int) $claimable['id'] ?>"
                            >
                            <span>
                                <strong><?= dashboardEscape($claimable['reference_number']) ?></strong>
                                <small><?= dashboardEscape(dashboardStatusLabel($claimable['status'])) ?></small>
                            </span>
                        </label>
                    <?php endforeach; ?>
                </fieldset>
                <?php if (empty($user['email_verified_at'])): ?>
                    <p class="guest-claim-note">Verify your email address in Settings before claiming submissions.</p>
                <?php endif; ?>
                <p class="guest-claim-feedback" data-guest-claim-feedback role="status" aria-live="polite"></p>
                <button
                    class="guest-claim-submit"
                    type="submit"
                    <?= empty($user['email_verified_at']) ? 'disabled' : '' ?>
                >Review selected submissions</button>
                <dialog class="guest-claim-confirm" data-guest-claim-dialog>
                    <h3>Confirm submission claim</h3>
                    <p>The selected submission ownership and its existing Admin conversation will be transferred to this Seller account. This cannot be automatically merged with another account.</p>
                    <div>
                        <button type="button" data-close-guest-claim>Cancel</button>
                        <button type="button" data-confirm-guest-claim>Confirm claim</button>
                    </div>
                </dialog>
            </form>
        </section>
    <?php endif; ?>

    <!-- =====================================================
         HEADER
    ====================================================== -->

    <header class="dashboard-header">

        <div>

            <span class="eyebrow">
                SELLER PORTAL
            </span>

            <h1>
                Welcome back,
                <?= dashboardEscape($firstName) ?>.
            </h1>

            <p>
                Track your submissions, offers,
                and completed transactions
                from one place.
            </p>

        </div>


        <div class="dashboard-profile">

            <?php if ($profilePicture !== ''): ?>

                <img
                    src="<?= dashboardEscape($profilePicture) ?>"
                    alt="<?= dashboardEscape($fullName) ?>"
                    class="profile-avatar"
                >

            <?php else: ?>

                <div class="profile-avatar profile-avatar-fallback">
                    <?= dashboardEscape($initial) ?>
                </div>

            <?php endif; ?>


            <div class="profile-info">

                <strong>
                    <?= dashboardEscape($fullName) ?>
                </strong>

                <span>
                    <?= dashboardEscape($user['email']) ?>
                </span>

            </div>

        </div>

    </header>


    <!-- =====================================================
         YOUR ACCOUNT
    ====================================================== -->

    <section class="dashboard-section dashboard-account-section">

        <div class="section-heading">

            <div>

                <span class="eyebrow">
                    ACCOUNT
                </span>

                <h2>
                    Your account
                </h2>

            </div>

        </div>


        <div class="account-info-grid">

            <div class="account-info-card">

                <span>
                    Full Name
                </span>

                <strong>
                    <?= dashboardEscape(
                        $user['full_name']
                    ) ?>
                </strong>

            </div>


            <div class="account-info-card">

                <span>
                    Email
                </span>

                <strong>
                    <?= dashboardEscape(
                        $user['email']
                    ) ?>
                </strong>

            </div>


            <div class="account-info-card">

                <span>
                    Mobile
                </span>

                <strong>
                    <?= dashboardEscape(
                        $user['mobile']
                        ?: 'Not provided'
                    ) ?>
                </strong>

            </div>


            <div class="account-info-card">

                <span>
                    Member Since
                </span>

                <strong>
                    <?= dashboardDate(
                        $user['created_at']
                    ) ?>
                </strong>

            </div>

        </div>

    </section>


    <!-- =====================================================
         QUICK ACTION
    ====================================================== -->

    <section class="dashboard-action">

        <div>

            <span class="eyebrow">
                READY TO SELL?
            </span>

            <h2>
                Submit another device.
            </h2>

            <p>
                Tell us about your phone,
                device, or scrap board and
                our team will review it.
            </p>

        </div>


        <a
            href="/VNZ-TECH/sell.php"
            class="sell-button"
        >
            SELL TO VNZ TECH
        </a>

    </section>


    <!-- =====================================================
         STATISTICS
    ====================================================== -->

    <section class="stats-grid">

        <article class="stat-card">

            <div class="stat-card-label">
                TOTAL SUBMISSIONS
            </div>

            <div class="stat-card-value">
                <?= number_format(
                    $submissionCount
                ) ?>
            </div>

            <div class="stat-card-note">
                All submitted devices
            </div>

        </article>


        <article class="stat-card">

            <div class="stat-card-label">
                ACTIVE OFFERS
            </div>

            <div class="stat-card-value">
                <?= number_format(
                    $activeOffers
                ) ?>
            </div>

            <div class="stat-card-note">
                Offers awaiting action
            </div>

        </article>


        <article class="stat-card">

            <div class="stat-card-label">
                COMPLETED
            </div>

            <div class="stat-card-value">
                <?= number_format(
                    $completedTransactions
                ) ?>
            </div>

            <div class="stat-card-note">
                Completed transactions
            </div>

        </article>


        <article class="stat-card">

            <div class="stat-card-label">
                TOTAL PAID
            </div>

            <div class="stat-card-value stat-card-money">
                <?= dashboardMoney(
                    $totalPaid
                ) ?>
            </div>

            <div class="stat-card-note">
                From completed transactions
            </div>

        </article>

    </section>


    <!-- =====================================================
         RECENT SUBMISSIONS
    ====================================================== -->

    <section class="dashboard-section">

        <div class="section-heading">

            <div>

                <span class="eyebrow">
                    ACTIVITY
                </span>

                <h2>
                    Recent submissions
                </h2>

            </div>


            <button
                type="button"
                class="section-link"
                onclick="window.loadSellerPage('submissions')"
            >
                VIEW ALL
            </button>

        </div>


        <?php if (empty($recentSubmissions)): ?>

            <div class="empty-state">

                <div class="empty-state-icon">
                    +
                </div>

                <h3>
                    No submissions yet.
                </h3>

                <p>
                    Your submitted devices will
                    appear here once you make
                    your first submission.
                </p>

                <a
                    href="/VNZ-TECH/sell.php"
                    class="empty-state-button"
                >
                    SUBMIT A DEVICE
                </a>

            </div>

        <?php else: ?>

            <div class="recent-list">

                <?php foreach (
                    $recentSubmissions
                    as $submission
                ): ?>

                    <?php

                    $status = strtolower(
                        trim(
                            (string) (
                                $submission['status']
                                ?? ''
                            )
                        )
                    );

                    ?>

                    <article class="recent-item">

                        <div class="recent-item-main">

                            <div class="recent-reference">

                                <?= dashboardEscape(
                                    $submission[
                                        'reference_number'
                                    ]
                                ) ?>

                            </div>


                            <div class="recent-meta">

                                <span>
                                    <?= dashboardDate(
                                        $submission[
                                            'created_at'
                                        ]
                                    ) ?>
                                </span>

                                <span>
                                    <?= dashboardTime(
                                        $submission[
                                            'created_at'
                                        ]
                                    ) ?>
                                </span>

                                <span>
                                    <?= number_format(
                                        (int) (
                                            $submission[
                                                'item_count'
                                            ] ?? 0
                                        )
                                    ) ?>
                                    items
                                </span>

                            </div>

                        </div>


                        <div class="recent-item-details">

                            <div class="recent-amount">

                                <?= dashboardMoney(
                                    $submission[
                                        'asking_total'
                                    ] ?? 0
                                ) ?>

                            </div>


                            <span
                                class="status-badge status-<?= dashboardEscape(
                                    dashboardStatusClass(
                                        $status
                                    )
                                ) ?>"
                            >
                                <?= dashboardEscape(
                                    dashboardStatusLabel(
                                        $status
                                    )
                                ) ?>
                            </span>

                        </div>

                    </article>

                <?php endforeach; ?>

            </div>

        <?php endif; ?>

    </section>


    <!-- =====================================================
         RECENT TRANSACTIONS
    ====================================================== -->

    <section class="dashboard-section">

        <div class="section-heading">

            <div>

                <span class="eyebrow">
                    HISTORY
                </span>

                <h2>
                    Recent transactions
                </h2>

            </div>


            <button
                type="button"
                class="section-link"
                disabled
            >
                VIEW ALL
            </button>

        </div>


        <?php if (empty($recentTransactions)): ?>

            <div class="empty-state compact">

                <h3>
                    No transactions yet.
                </h3>

                <p>
                    Completed transactions will
                    appear here.
                </p>

            </div>

        <?php else: ?>

            <div class="recent-list">

                <?php foreach (
                    $recentTransactions
                    as $transaction
                ): ?>

                    <article class="recent-item">

                        <div class="recent-item-main">

                            <div class="recent-reference">

                                <?= dashboardEscape(
                                    $transaction[
                                        'transaction_reference'
                                    ]
                                ) ?>

                            </div>


                            <div class="recent-meta">

                                <span>
                                    Submission:
                                    <?= dashboardEscape(
                                        $transaction[
                                            'reference_number'
                                        ]
                                    ) ?>
                                </span>


                                <?php if (
                                    !empty(
                                        $transaction[
                                            'completed_at'
                                        ]
                                    )
                                ): ?>

                                    <span>
                                        <?= dashboardDate(
                                            $transaction[
                                                'completed_at'
                                            ]
                                        ) ?>
                                    </span>

                                <?php endif; ?>

                            </div>

                        </div>


                        <div class="recent-item-details">

                            <div class="recent-amount">

                                <?= dashboardMoney(
                                    $transaction[
                                        'final_amount'
                                    ]
                                ) ?>

                            </div>


                            <span
                                class="status-badge status-completed"
                            >
                                <?= dashboardEscape(
                                    dashboardStatusLabel(
                                        $transaction[
                                            'transaction_status'
                                        ]
                                    )
                                ) ?>
                            </span>

                        </div>

                    </article>

                <?php endforeach; ?>

            </div>

        <?php endif; ?>

    </section>


</section>