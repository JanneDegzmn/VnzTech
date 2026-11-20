<?php
require_once __DIR__ . '/../../backend/database.php';
require_once __DIR__ . '/../../backend/session.php';
require_once __DIR__ . '/../../backend/auth.php';
require_once __DIR__ . '/../../backend/csrf.php';
require_once __DIR__ . '/../../backend/pagination.php';

requireAdminLogin();

if (!hasPermission('manage_trades')) {
    http_response_code(403);
    echo '<div class="trade-page"><div class="trade-access-denied"><div class="trade-access-denied-icon">!</div><div><h2>Access Denied</h2><p>You do not have permission to manage trade requests.</p></div></div></div>';
    exit;
}

$csrfToken = generateCsrfToken();
$pagination = vnzPaginationState(12);
$requestCount = (int) $pdo->query('SELECT COUNT(*) FROM trade_requests')->fetchColumn();
$pagination = vnzPaginationClamp($pagination, $requestCount);
$tradeRequestsStmt = $pdo->prepare("
    SELECT
        tr.id,
        tr.request_reference,
        tr.status,
        tr.requested_at,
        tr.reviewed_at,
        tr.approved_at,
        tr.completed_at,
        tr.rejected_at,
        tr.cancelled_at,
        tr.admin_notes,
        u.full_name AS user_name,
        u.email AS user_email,
        ti.id AS trade_item_id,
        ti.name AS trade_item_name,
        ti.`condition` AS trade_item_condition,
        ti.description AS trade_item_description
    FROM trade_requests tr
    INNER JOIN users u ON u.id = tr.user_id
    INNER JOIN trade_items ti ON ti.id = tr.trade_item_id
    ORDER BY
        CASE tr.status
            WHEN 'PENDING' THEN 0
            WHEN 'APPROVED' THEN 1
            ELSE 2
        END ASC,
        tr.requested_at DESC,
        tr.id DESC
    LIMIT {$pagination['per_page']} OFFSET {$pagination['offset']}
");
$tradeRequestsStmt->execute();
$tradeRequests = $tradeRequestsStmt->fetchAll();
$paginationHtml = vnzRenderPagination(
    $pagination,
    [],
    'trade_requests',
    'admin',
    '/VNZ-TECH/admin/dashboard.php'
);

function tradeRequestStatusLabel(string $status): string
{
    $map = [
        'PENDING' => 'Pending',
        'APPROVED' => 'Approved',
        'COMPLETED' => 'Completed',
        'REJECTED' => 'Rejected',
        'CANCELLED' => 'Cancelled',
    ];
    return $map[$status] ?? ucfirst(strtolower($status));
}

function tradeRequestFormatDate(?string $date): string
{
    return $date ? date('M d, Y · h:i A', strtotime($date)) : '';
}
?>

<link rel="stylesheet" href="/VNZ-TECH/assets/css/admin_trade_items.css?v=<?= filemtime(__DIR__ . '/../../assets/css/admin_trade_items.css') ?>">

<div class="trade-page trade-requests-page">
    <div class="trade-header">
        <div class="trade-heading">
            <div class="trade-kicker">VNZ TECH MANAGEMENT</div>
            <h1>Trade Requests</h1>
            <p>Review requests and verify the customer’s scrap in person.</p>
        </div>
    </div>

    <?php if ($pagination['last_page'] > 1): ?>
    <?php endif; ?>

    <?php if (empty($tradeRequests)): ?>
        <div class="trade-empty-state">No trade requests have been submitted yet.</div>
    <?php else: ?>
        <div class="trade-card-list admin-trade-request-list">
            <?php foreach ($tradeRequests as $request): ?>
                <?php
                $requirementsStmt = $pdo->prepare("
                    SELECT requirement_type, quantity, storage, description
                    FROM trade_requirements
                    WHERE trade_item_id = ?
                    ORDER BY sort_order ASC, id ASC
                ");
                $requirementsStmt->execute([(int) $request['trade_item_id']]);
                $requirements = $requirementsStmt->fetchAll();
                ?>
                <article class="trade-item-card admin-trade-request-card" data-request-id="<?= (int) $request['id'] ?>">
                    <div class="trade-item-content">
                        <div class="trade-item-topline">
                            <div>
                                <h4><?= htmlspecialchars($request['request_reference'], ENT_QUOTES, 'UTF-8') ?></h4>
                            </div>
                            <span class="trade-status-badge status-<?= strtolower((string) $request['status']) ?>"><?= htmlspecialchars(tradeRequestStatusLabel((string) $request['status']), ENT_QUOTES, 'UTF-8') ?></span>
                        </div>

                        <div class="admin-trade-request-summary">
                            <section class="admin-trade-request-summary-block">
                                <span class="admin-trade-request-label">Customer</span>
                                <strong><?= htmlspecialchars($request['user_name'], ENT_QUOTES, 'UTF-8') ?></strong>
                                <span class="admin-trade-request-secondary"><?= htmlspecialchars($request['user_email'], ENT_QUOTES, 'UTF-8') ?></span>
                            </section>

                            <section class="admin-trade-request-summary-block">
                                <span class="admin-trade-request-label">Trade item</span>
                                <strong><?= htmlspecialchars($request['trade_item_name'], ENT_QUOTES, 'UTF-8') ?></strong>
                                <div class="admin-trade-request-item-meta">
                                    <span><?= htmlspecialchars($request['trade_item_condition'], ENT_QUOTES, 'UTF-8') ?></span>
                                    <span>Requested <?= htmlspecialchars(tradeRequestFormatDate($request['requested_at']), ENT_QUOTES, 'UTF-8') ?></span>
                                    <?php if ($request['reviewed_at']): ?><span>Reviewed <?= htmlspecialchars(tradeRequestFormatDate($request['reviewed_at']), ENT_QUOTES, 'UTF-8') ?></span><?php endif; ?>
                                </div>
                            </section>
                        </div>

                        <?php if (!empty($request['trade_item_description'])): ?>
                            <p class="trade-item-description"><?= htmlspecialchars($request['trade_item_description'], ENT_QUOTES, 'UTF-8') ?></p>
                        <?php endif; ?>

                        <section class="admin-trade-requirements">
                            <div class="admin-trade-section-heading">
                                <h5>Required Trade Items</h5>
                                <span><?= count($requirements) ?> <?= count($requirements) === 1 ? 'requirement' : 'requirements' ?></span>
                            </div>
                            <?php if ($requirements): ?>
                                <?php foreach ($requirements as $requirement): ?>
                                    <div class="admin-trade-requirement">
                                        <strong><?= (int) $requirement['quantity'] ?> × <?= htmlspecialchars($requirement['requirement_type'] === 'scrap_board' ? 'Scrap Board' : 'Damaged Phone', ENT_QUOTES, 'UTF-8') ?></strong>
                                        <span><?= htmlspecialchars($requirement['requirement_type'] === 'scrap_board' ? 'Classification' : 'Storage', ENT_QUOTES, 'UTF-8') ?>: <?= htmlspecialchars($requirement['storage'], ENT_QUOTES, 'UTF-8') ?></span>
                                        <?php if (!empty($requirement['description'])): ?><small><?= htmlspecialchars($requirement['description'], ENT_QUOTES, 'UTF-8') ?></small><?php endif; ?>
                                    </div>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <p>Requirements are no longer configured for this trade item.</p>
                            <?php endif; ?>
                        </section>

                        <?php if (!empty($request['admin_notes'])): ?>
                            <p class="admin-trade-existing-notes"><strong>Current notes:</strong> <?= nl2br(htmlspecialchars($request['admin_notes'], ENT_QUOTES, 'UTF-8')) ?></p>
                        <?php endif; ?>

                        <?php if (in_array((string) $request['status'], ['PENDING', 'APPROVED'], true)): ?>
                            <div class="admin-trade-notes-section">
                                <label class="admin-trade-note-label" for="tradeNotes<?= (int) $request['id'] ?>">Staff notes <span>Optional</span></label>
                                <textarea class="admin-trade-notes" id="tradeNotes<?= (int) $request['id'] ?>" maxlength="5000" placeholder="Add verification details or a reason for your decision."><?= htmlspecialchars((string) ($request['admin_notes'] ?? ''), ENT_QUOTES, 'UTF-8') ?></textarea>
                            </div>
                        <?php endif; ?>

                        <div class="trade-form-actions admin-trade-actions">
                            <button
                                type="button"
                                class="trade-secondary-btn"
                                data-open-trade-request-chat="<?= (int) $request['id'] ?>"
                            >Chat with seller</button>
                            <?php if ((string) $request['status'] === 'PENDING'): ?>
                                <button type="button" class="trade-primary-btn trade-request-action" data-request-id="<?= (int) $request['id'] ?>" data-status-action="approve">Approve</button>
                                <button type="button" class="trade-secondary-btn trade-request-action" data-request-id="<?= (int) $request['id'] ?>" data-status-action="reject">Reject</button>
                                <button type="button" class="trade-secondary-btn trade-request-action" data-request-id="<?= (int) $request['id'] ?>" data-status-action="cancel">Cancel</button>
                            <?php elseif ((string) $request['status'] === 'APPROVED'): ?>
                                <button type="button" class="trade-primary-btn trade-request-action" data-request-id="<?= (int) $request['id'] ?>" data-status-action="complete">Complete</button>
                            <?php endif; ?>
                        </div>
                    </div>
                </article>
            <?php endforeach; ?>
        </div>
        <?= $paginationHtml ?>
    <?php endif; ?>
</div>

<script>
    (function () {
        const csrfToken = <?= json_encode($csrfToken, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>;

        document.addEventListener('click', async function (event) {
            const button = event.target.closest('.trade-request-action');
            if (!button) {
                return;
            }

            const requestId = Number(button.dataset.requestId || 0);
            const statusAction = button.dataset.statusAction || '';
            if (!requestId || !statusAction) {
                return;
            }

            const card = button.closest('[data-request-id]');
            const notesField = card?.querySelector('.admin-trade-notes');
            const body = new URLSearchParams();
            body.set('action', 'admin_update_status');
            body.set('request_id', String(requestId));
            body.set('status_action', statusAction);
            body.set('admin_notes', notesField?.value || '');
            body.set('csrf_token', csrfToken);

            button.disabled = true;
            const previousText = button.textContent;
            button.textContent = 'Working...';

            try {
                const response = await fetch('/VNZ-TECH/backend/trade_requests.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8',
                        'X-Requested-With': 'XMLHttpRequest'
                    },
                    credentials: 'same-origin',
                    body: body.toString(),
                    cache: 'no-store'
                });
                const data = await response.json();
                if (!response.ok || !data.success) {
                    throw new Error(data.message || 'Unable to update this trade request.');
                }
                window.location.reload();
            } catch (error) {
                await window.VNZDialog.alert({
                    title: 'Unable to Update Trade Request',
                    message: error.message || 'Unable to update this request.',
                    confirmLabel: 'Close'
                });
                button.disabled = false;
                button.textContent = previousText;
            }
        });
    })();
</script>