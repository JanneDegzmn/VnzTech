<?php


require_once __DIR__ . '/../../backend/site_status.php';
vnzEnforceSiteMaintenance('auto', true);
require_once __DIR__ . '/../../backend/database.php';
require_once __DIR__ . '/../../backend/session.php';
require_once __DIR__ . '/../../backend/auth.php';
require_once __DIR__ . '/../../backend/csrf.php';
require_once __DIR__ . '/../../backend/pagination.php';

requireAdminLogin();

$currentAdmin = getCurrentAdmin();
if (!$currentAdmin) { http_response_code(401); exit('Your admin account is no longer active.'); }
$csrfToken = generateCsrfToken();
if (!hasPermission('manage_settings')) {
    http_response_code(403);
    ?>
    <link rel="stylesheet" href="/VNZ-TECH/assets/css/admin_operations.css?v=<?= filemtime(__DIR__ . '/../../assets/css/admin_operations.css') ?>">
    <div class="admin-settings-page">
        <div class="admin-report-empty">
            <h2>Access denied</h2>
            <p>You do not have permission to access this administrative module.</p>
        </div>
    </div>
    <?php
    exit;
}

$activityPagination = vnzPaginationClamp(
    vnzPaginationState(12),
    (int) $pdo->query('SELECT COUNT(*) FROM admin_activity_logs')->fetchColumn()
);
$activityStmt = $pdo->query("
    SELECT
        l.action,
        l.details,
        l.ip_address,
        l.created_at,
        a.first_name,
        a.last_name,
        a.username
    FROM admin_activity_logs l
    LEFT JOIN admins a ON a.id = l.admin_id
    ORDER BY l.created_at DESC, l.id DESC
    LIMIT {$activityPagination['per_page']} OFFSET {$activityPagination['offset']}
");
$recentActivity = $activityStmt->fetchAll();
$activityPaginationHtml = vnzRenderPagination(
    $activityPagination,
    [],
    'activity',
    'admin',
    '/VNZ-TECH/admin/dashboard.php'
);
?>
<link rel="stylesheet" href="/VNZ-TECH/assets/css/admin_operations.css?v=<?= filemtime(__DIR__ . '/../../assets/css/admin_operations.css') ?>">
<div class="admin-settings-page"><header class="operations-header"><div><span class="operations-eyebrow">ADMINISTRATION</span><h1>Activity Log</h1><p>Review administrative actions and account security events.</p></div></header>
<div class="operations-form-message" id="adminSettingsMessage" hidden role="status"></div>
<div class="operations-settings-grid">
        <section class="operations-settings-card admin-activity-card">
            <h2>Recent admin activity</h2>
            <p>Latest sign-ins and security or settings changes across admin accounts.</p>

            <?php if ($activityPagination['last_page'] > 1): ?>
                <?= $activityPaginationHtml ?>
            <?php endif; ?>

            <?php if (!$recentActivity): ?>
                <div class="admin-activity-empty">No admin activity has been recorded yet.</div>
            <?php else: ?>
                <div class="admin-activity-table-wrap">
                    <table class="admin-activity-table">
                        <thead>
                            <tr>
                                <th scope="col">Admin</th>
                                <th scope="col">Activity</th>
                                <th scope="col">Details</th>
                                <th scope="col">IP address</th>
                                <th scope="col">Date</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($recentActivity as $activity): ?>
                                <tr>
                                    <td><?= htmlspecialchars(trim(($activity['first_name'] ?? '') . ' ' . ($activity['last_name'] ?? '')) ?: ($activity['username'] ?? 'Unknown admin'), ENT_QUOTES, 'UTF-8') ?></td>
                                    <td><?= htmlspecialchars($activity['action'], ENT_QUOTES, 'UTF-8') ?></td>
                                    <td><?= htmlspecialchars($activity['details'] ?? '', ENT_QUOTES, 'UTF-8') ?></td>
                                    <td><?= htmlspecialchars($activity['ip_address'] ?? '', ENT_QUOTES, 'UTF-8') ?></td>
                                    <td><?= htmlspecialchars(date('M d, Y h:i A', strtotime($activity['created_at'])), ENT_QUOTES, 'UTF-8') ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </section>
</div></div>
