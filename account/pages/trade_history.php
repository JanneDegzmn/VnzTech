<?php
require_once __DIR__ . '/../../backend/database.php';
require_once __DIR__ . '/../../backend/session.php';

if (empty($_SESSION['user_logged_in']) || empty($_SESSION['user_id'])) {
    header('Location: /VNZ-TECH/account/login.php');
    exit;
}

$userId = (int) $_SESSION['user_id'];
$tradeRequestsStmt = $pdo->prepare("
    SELECT
        tr.id,
        tr.trade_item_id,
        tr.request_reference,
        tr.status,
        tr.requested_at,
        tr.reviewed_at,
        tr.approved_at,
        tr.completed_at,
        tr.rejected_at,
        tr.cancelled_at,
        tr.admin_notes,
        ti.name AS trade_item_name,
        ti.`condition` AS trade_item_condition,
        ti.description AS trade_item_description,
        ti.image_path AS trade_item_image
    FROM trade_requests tr
    INNER JOIN trade_items ti ON ti.id = tr.trade_item_id
    WHERE tr.user_id = ?
    ORDER BY tr.requested_at DESC, tr.id DESC
");
$tradeRequestsStmt->execute([$userId]);
$tradeRequests = $tradeRequestsStmt->fetchAll();

function tradeHistoryStatusLabel(string $status): string
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

function tradeHistoryFormatDate(?string $date): string
{
    return $date ? date('M d, Y · h:i A', strtotime($date)) : '';
}
?>

<div class="trade-history-page">
    <div class="page-header-block">
        <div>
            <div class="page-kicker">TRADE HISTORY</div>
            <h1>My Trade Requests</h1>
        </div>
    </div>

    <?php if (empty($tradeRequests)): ?>
        <div class="portal-card portal-empty-state">
            <h2>No trade requests yet</h2>
            <p>Your submitted trade requests will appear here once you request an item.</p>
        </div>
    <?php else: ?>
        <div class="trade-history-list">
            <?php foreach ($tradeRequests as $tradeRequest): ?>
                <?php
                $requirementsStmt = $pdo->prepare("
                    SELECT requirement_type, quantity, storage, description
                    FROM trade_requirements
                    WHERE trade_item_id = ?
                    ORDER BY sort_order ASC, id ASC
                ");
                $requirementsStmt->execute([(int) $tradeRequest['trade_item_id']]);
                $requirements = $requirementsStmt->fetchAll();
                $tradeImage = !empty($tradeRequest['trade_item_image'])
                    ? '/VNZ-TECH/' . $tradeRequest['trade_item_image']
                    : '/VNZ-TECH/assets/images/vnz.png';
                ?>
                <article class="portal-card trade-history-item">
                    <div class="trade-history-header">
                        <div>
                            <div class="trade-history-reference"><?= htmlspecialchars($tradeRequest['request_reference'], ENT_QUOTES, 'UTF-8') ?></div>
                            <h2><?= htmlspecialchars($tradeRequest['trade_item_name'], ENT_QUOTES, 'UTF-8') ?></h2>
                        </div>
                        <span class="trade-status-badge status-<?= strtolower((string) $tradeRequest['status']) ?>"><?= htmlspecialchars(tradeHistoryStatusLabel((string) $tradeRequest['status']), ENT_QUOTES, 'UTF-8') ?></span>
                    </div>

                    <div class="trade-history-item-details">
                        <img src="<?= htmlspecialchars($tradeImage, ENT_QUOTES, 'UTF-8') ?>" alt="<?= htmlspecialchars($tradeRequest['trade_item_name'], ENT_QUOTES, 'UTF-8') ?>">
                        <div class="trade-history-meta">
                            <div><strong>Condition:</strong> <?= htmlspecialchars($tradeRequest['trade_item_condition'], ENT_QUOTES, 'UTF-8') ?></div>
                            <div><strong>Requested:</strong> <?= htmlspecialchars(tradeHistoryFormatDate($tradeRequest['requested_at']), ENT_QUOTES, 'UTF-8') ?></div>
                            <?php if ($tradeRequest['reviewed_at']): ?><div><strong>Reviewed:</strong> <?= htmlspecialchars(tradeHistoryFormatDate($tradeRequest['reviewed_at']), ENT_QUOTES, 'UTF-8') ?></div><?php endif; ?>
                        </div>
                    </div>

                    <?php if (!empty($tradeRequest['trade_item_description'])): ?>
                        <p class="trade-history-description"><?= htmlspecialchars($tradeRequest['trade_item_description'], ENT_QUOTES, 'UTF-8') ?></p>
                    <?php endif; ?>

                    <section class="trade-history-section">
                        <h3>Required Trade Items</h3>
                        <?php if ($requirements): ?>
                            <ul class="trade-history-requirements">
                                <?php foreach ($requirements as $requirement): ?>
                                    <li>
                                        <strong><?= (int) $requirement['quantity'] ?> × <?= htmlspecialchars($requirement['requirement_type'] === 'scrap_board' ? 'Scrap Board' : 'Damaged Phone', ENT_QUOTES, 'UTF-8') ?></strong>
                                        <span><?= htmlspecialchars($requirement['requirement_type'] === 'scrap_board' ? 'Classification' : 'Storage', ENT_QUOTES, 'UTF-8') ?>: <?= htmlspecialchars($requirement['storage'], ENT_QUOTES, 'UTF-8') ?></span>
                                        <?php if (!empty($requirement['description'])): ?><small><?= htmlspecialchars($requirement['description'], ENT_QUOTES, 'UTF-8') ?></small><?php endif; ?>
                                    </li>
                                <?php endforeach; ?>
                            </ul>
                        <?php else: ?>
                            <p>Requirements are no longer configured for this trade item.</p>
                        <?php endif; ?>
                    </section>

                    <?php if (!empty($tradeRequest['admin_notes'])): ?>
                        <div class="trade-history-admin-notes">
                            <strong>VNZ TECH Notes</strong>
                            <p><?= nl2br(htmlspecialchars($tradeRequest['admin_notes'], ENT_QUOTES, 'UTF-8')) ?></p>
                        </div>
                    <?php endif; ?>

                    <div class="trade-history-timeline">
                        <?php foreach (['approved_at' => 'Approved', 'completed_at' => 'Completed', 'rejected_at' => 'Rejected', 'cancelled_at' => 'Cancelled'] as $dateField => $dateLabel): ?>
                            <?php if ($tradeRequest[$dateField]): ?><div><strong><?= $dateLabel ?>:</strong> <?= htmlspecialchars(tradeHistoryFormatDate($tradeRequest[$dateField]), ENT_QUOTES, 'UTF-8') ?></div><?php endif; ?>
                        <?php endforeach; ?>
                    </div>
                </article>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</div>

<style>
    .trade-history-page { padding: 24px; }
    .page-header-block { margin-bottom: 20px; }
    .page-kicker { font-size: 12px; letter-spacing: 0.18em; color: #7d7d86; text-transform: uppercase; }
    .trade-history-list { display: grid; gap: 20px; }
    .trade-history-item { padding: 22px; }
    .trade-history-header { display: flex; justify-content: space-between; gap: 16px; align-items: flex-start; }
    .trade-history-reference { font-size: 12px; color: #6a7282; text-transform: uppercase; }
    .trade-history-item-details { display: flex; align-items: center; gap: 16px; margin-top: 14px; }
    .trade-history-item-details img { width: 88px; height: 72px; object-fit: cover; border-radius: 6px; }
    .trade-history-meta { display: flex; gap: 18px; flex-wrap: wrap; color: #3a3f4a; }
    .trade-history-description { margin-top: 12px; color: #4b5363; }
    .trade-history-section { margin-top: 18px; }
    .trade-history-section h3 { margin-bottom: 8px; }
    .trade-history-requirements { margin: 0; padding-left: 18px; color: #2f3440; }
    .trade-history-requirements li { margin-bottom: 8px; }
    .trade-history-requirements span, .trade-history-requirements small { display: block; }
    .trade-history-admin-notes { margin-top: 18px; padding: 14px; border-left: 3px solid #c69b50; background: rgba(198, 155, 80, 0.08); }
    .trade-history-admin-notes p { margin: 6px 0 0; white-space: pre-line; }
    .trade-history-timeline { display: flex; flex-wrap: wrap; gap: 18px; margin-top: 18px; color: #485164; }
    .trade-status-badge { display: inline-flex; align-items: center; justify-content: center; border-radius: 999px; font-size: 12px; font-weight: 700; padding: 6px 12px; text-transform: uppercase; }
    .status-pending { background: #fff1d8; color: #9a6a00; }
    .status-approved { background: #ddf6e8; color: #0b7a46; }
    .status-completed { background: #e0f5ea; color: #116b3d; }
    .status-rejected { background: #fde1df; color: #b2282a; }
    .status-cancelled { background: #efeef7; color: #4d4a64; }
    @media (max-width: 768px) {
        .trade-history-page { padding: 16px; }
        .trade-history-header, .trade-history-item-details { align-items: flex-start; flex-direction: column; }
        .trade-history-meta { flex-direction: column; gap: 10px; }
    }
</style>