<?php


require_once __DIR__ . '/../../backend/site_status.php';
vnzEnforceSiteMaintenance('auto', true);
require_once __DIR__ . '/../../backend/database.php';
require_once __DIR__ . '/../../backend/session.php';
require_once __DIR__ . '/../../backend/auth.php';

requireAdminLogin();


/*
|--------------------------------------------------------------------------
| Dashboard Statistics
|--------------------------------------------------------------------------
*/

$submissionCount = 0;
$pendingReviewCount = 0;
$pendingOfferCount = 0;
$acceptedCount = 0;


/*
|--------------------------------------------------------------------------
| Total Submissions
|--------------------------------------------------------------------------
*/

$stmt = $pdo->query("
    SELECT COUNT(*)
    FROM submissions
");

$submissionCount = (int) $stmt->fetchColumn();


/*
|--------------------------------------------------------------------------
| Pending Review
|--------------------------------------------------------------------------
*/

$stmt = $pdo->query("
    SELECT COUNT(*)
    FROM submissions
    WHERE status = 'new_submission'
");

$pendingReviewCount = (int) $stmt->fetchColumn();


/*
|--------------------------------------------------------------------------
| Pending Offers
|--------------------------------------------------------------------------
*/

$stmt = $pdo->query("
    SELECT COUNT(*)
    FROM submissions
    WHERE status IN ('offer_pending', 'offer_sent')
");

$pendingOfferCount = (int) $stmt->fetchColumn();


/*
|--------------------------------------------------------------------------
| Accepted
|--------------------------------------------------------------------------
*/

$stmt = $pdo->query("
    SELECT COUNT(*)
    FROM submissions
    WHERE status = 'accepted'
");

$acceptedCount = (int) $stmt->fetchColumn();

$recentActivity = [];
$activityUnavailable = false;

try {
    $submissionActivityStmt = $pdo->query("
        SELECT reference_number, seller_name, status, created_at
        FROM submissions
        ORDER BY created_at DESC
        LIMIT 8
    ");

    foreach ($submissionActivityStmt->fetchAll() as $activity) {
        $recentActivity[] = [
            'title' => 'New seller submission',
            'description' => trim(
                (string) ($activity['reference_number'] ?? '')
                . ' · '
                . (string) ($activity['seller_name'] ?? '')
                . ' · '
                . ucwords(str_replace('_', ' ', (string) ($activity['status'] ?? '')))
            ),
            'actor' => 'Seller',
            'created_at' => (string) $activity['created_at'],
        ];
    }
} catch (PDOException $e) {
    error_log('Admin dashboard submission activity query failed: ' . $e->getMessage());
    $activityUnavailable = true;
}

try {
    $adminActivityStmt = $pdo->query("
        SELECT
            l.action,
            l.details,
            l.created_at,
            a.first_name,
            a.last_name,
            a.username
        FROM admin_activity_logs l
        LEFT JOIN admins a ON a.id = l.admin_id
        ORDER BY l.created_at DESC, l.id DESC
        LIMIT 8
    ");

    foreach ($adminActivityStmt->fetchAll() as $activity) {
        $actor = trim(
            (string) ($activity['first_name'] ?? '')
            . ' '
            . (string) ($activity['last_name'] ?? '')
        );
        $recentActivity[] = [
            'title' => ucwords(str_replace(
                ['.', '_'],
                ' ',
                (string) $activity['action']
            )),
            'description' => (string) ($activity['details'] ?? ''),
            'actor' => $actor !== '' ? $actor : (string) ($activity['username'] ?? 'Admin'),
            'created_at' => (string) $activity['created_at'],
        ];
    }
} catch (PDOException $e) {
    error_log('Admin dashboard admin activity query failed: ' . $e->getMessage());
    $activityUnavailable = true;
}

usort(
    $recentActivity,
    static fn (array $left, array $right): int =>
        (strtotime($right['created_at']) ?: 0) <=> (strtotime($left['created_at']) ?: 0)
);
$recentActivity = array_slice($recentActivity, 0, 8);

$databaseAvailable = false;
try {
    $pdo->query('SELECT 1');
    $databaseAvailable = true;
} catch (PDOException $e) {
    error_log('Admin dashboard database status check failed: ' . $e->getMessage());
}

$currentAdmin = getCurrentAdmin();
$authenticationAvailable = $currentAdmin !== null;
$adminSessionAvailable = session_status() === PHP_SESSION_ACTIVE
    && isset($_SESSION['admin_id'])
    && $authenticationAvailable;

?>

<div class="dashboard-page">


    <!-- =====================================================
         WELCOME
    ====================================================== -->

    <div class="dashboard-welcome">

        <div>

            <div class="dashboard-eyebrow">
                VNZ TECH OVERVIEW
            </div>

            <h1>
                Dashboard
            </h1>

            <p>
                Monitor submissions, offers, and transactions
                from one place.
            </p>

        </div>

    </div>


    <!-- =====================================================
         STATISTICS
    ====================================================== -->

    <div class="stats-grid">


        <div class="stat-card">

            <div class="stat-top">

                <span class="stat-label">
                    TOTAL SUBMISSIONS
                </span>

                <span class="stat-icon">
                    ▤
                </span>

            </div>

            <div class="stat-number">
                <?= $submissionCount ?>
            </div>

            <div class="stat-description">
                All seller submissions
            </div>

        </div>


        <div class="stat-card">

            <div class="stat-top">

                <span class="stat-label">
                    PENDING REVIEW
                </span>

                <span class="stat-icon">
                    ◷
                </span>

            </div>

            <div class="stat-number">
                <?= $pendingReviewCount ?>
            </div>

            <div class="stat-description">
                Waiting for admin review
            </div>

        </div>


        <div class="stat-card">

            <div class="stat-top">

                <span class="stat-label">
                    PENDING OFFERS
                </span>

                <span class="stat-icon">
                    ₱
                </span>

            </div>

            <div class="stat-number">
                <?= $pendingOfferCount ?>
            </div>

            <div class="stat-description">
                Offers awaiting action
            </div>

        </div>


        <div class="stat-card">

            <div class="stat-top">

                <span class="stat-label">
                    ACCEPTED
                </span>

                <span class="stat-icon">
                    ✓
                </span>

            </div>

            <div class="stat-number">
                <?= $acceptedCount ?>
            </div>

            <div class="stat-description">
                Accepted submissions
            </div>

        </div>

    </div>


    <!-- =====================================================
         QUICK OVERVIEW
    ====================================================== -->

    <div class="dashboard-grid">


        <div class="dashboard-panel">

            <div class="panel-header">

                <div>

                    <h2>
                        Recent Activity
                    </h2>

                    <p>Latest seller submissions and admin actions.</p>

                </div>

            </div>


            <?php if ($recentActivity): ?>
                <div class="dashboard-activity-list">
                    <?php foreach ($recentActivity as $activity): ?>
                        <article class="dashboard-activity-item">
                            <span class="dashboard-activity-marker" aria-hidden="true"></span>
                            <div class="dashboard-activity-copy">
                                <strong><?= htmlspecialchars($activity['title'], ENT_QUOTES, 'UTF-8') ?></strong>
                                <?php if ($activity['description'] !== ''): ?>
                                    <span><?= htmlspecialchars($activity['description'], ENT_QUOTES, 'UTF-8') ?></span>
                                <?php endif; ?>
                                <small><?= htmlspecialchars($activity['actor'], ENT_QUOTES, 'UTF-8') ?></small>
                            </div>
                            <time datetime="<?= htmlspecialchars(date(DATE_ATOM, strtotime($activity['created_at'])), ENT_QUOTES, 'UTF-8') ?>">
                                <?= htmlspecialchars(date('M j, Y g:i A', strtotime($activity['created_at'])), ENT_QUOTES, 'UTF-8') ?>
                            </time>
                        </article>
                    <?php endforeach; ?>
                </div>
            <?php elseif ($activityUnavailable): ?>
                <div class="empty-state">
                    <h3>Activity could not be loaded</h3>
                    <p>Check the server log or confirm the activity-log database migration is applied.</p>
                </div>
            <?php else: ?>
                <div class="empty-state">
                    <div class="empty-icon">—</div>
                    <h3>No activity yet</h3>
                    <p>New seller submissions and admin actions will appear here.</p>
                </div>
            <?php endif; ?>

        </div>


        <div class="dashboard-panel">

            <div class="panel-header">

                <div>

                    <h2>
                        System Status
                    </h2>

                    <p>
                        Current platform status.
                    </p>

                </div>

            </div>


            <div class="system-status-list">

                <div class="system-status-item">

                    <div class="status-indicator <?= $databaseAvailable ? 'online' : 'offline' ?>"></div>

                    <div>

                        <strong>
                            Database
                        </strong>

                        <span>
                            <?= $databaseAvailable ? 'Connected' : 'Unavailable' ?>
                        </span>

                    </div>

                </div>


                <div class="system-status-item">

                    <div class="status-indicator <?= $authenticationAvailable ? 'online' : 'offline' ?>"></div>

                    <div>

                        <strong>
                            Authentication
                        </strong>

                        <span>
                            <?= $authenticationAvailable ? 'Verified admin' : 'Unavailable' ?>
                        </span>

                    </div>

                </div>


                <div class="system-status-item">

                    <div class="status-indicator <?= $adminSessionAvailable ? 'online' : 'offline' ?>"></div>

                    <div>

                        <strong>
                            Admin Session
                        </strong>

                        <span>
                            <?= $adminSessionAvailable ? 'Active' : 'Not active' ?>
                        </span>

                    </div>

                </div>

            </div>

        </div>


    </div>

</div>