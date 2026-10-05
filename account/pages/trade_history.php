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
            <p class="trade-history-intro">Track the status and requirements for the items you have requested.</p>
        </div>
        <div class="trade-history-total" aria-label="<?= count($tradeRequests) ?> total trade requests">
            <strong><?= count($tradeRequests) ?></strong>
            <span>REQUESTS</span>
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
    .trade-history-page {
        width: 100%;
        max-width: none;
        margin: 0;
        padding: 24px;
        color: #f3f6f8;
    }

    .trade-history-page .page-header-block {
        display: flex;
        align-items: flex-end;
        justify-content: space-between;
        gap: 24px;
        margin-bottom: 24px;
        padding-bottom: 20px;
        border-bottom: 1px solid #242c34;
    }

    .trade-history-page .page-kicker {
        margin-bottom: 7px;
        color: #8b97a2;
        font-size: 10px;
        font-weight: 800;
        letter-spacing: 0.16em;
        text-transform: uppercase;
    }

    .trade-history-page .page-header-block h1 {
        margin: 0;
        color: #f3f6f8;
        font-size: 30px;
        font-weight: 850;
        line-height: 1.15;
    }

    .trade-history-intro {
        margin: 8px 0 0;
        color: #8b97a2;
        font-size: 13px;
        line-height: 1.5;
    }

    .trade-history-total {
        display: flex;
        align-items: baseline;
        gap: 9px;
        flex: 0 0 auto;
        padding: 10px 13px;
        border: 1px solid #242c34;
        border-radius: 8px;
        background: #10151b;
    }

    .trade-history-total strong {
        color: #f3f6f8;
        font-size: 20px;
        line-height: 1;
    }

    .trade-history-total span {
        color: #8b97a2;
        font-size: 9px;
        font-weight: 800;
        letter-spacing: 0.12em;
    }

    .trade-history-list {
        display: grid;
        gap: 16px;
    }

    .trade-history-item {
        padding: 22px;
        border: 1px solid #242c34;
        border-radius: 10px;
        background: #10151b;
        box-shadow: 0 12px 30px rgba(0, 0, 0, 0.16);
    }

    .trade-history-header {
        display: flex;
        align-items: flex-start;
        justify-content: space-between;
        gap: 18px;
    }

    .trade-history-reference {
        margin-bottom: 6px;
        color: #68747f;
        font-size: 10px;
        font-weight: 750;
        letter-spacing: 0.08em;
        text-transform: uppercase;
    }

    .trade-history-header h2 {
        margin: 0;
        color: #f3f6f8;
        font-size: 21px;
        font-weight: 800;
        line-height: 1.2;
    }

    .trade-status-badge {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        flex: 0 0 auto;
        min-height: 28px;
        padding: 5px 10px;
        border: 1px solid transparent;
        border-radius: 6px;
        font-size: 10px;
        font-weight: 850;
        letter-spacing: 0.06em;
        text-transform: uppercase;
    }

    .trade-history-page .status-pending { color: #f2c964; background: rgba(242, 201, 100, 0.1); border-color: rgba(242, 201, 100, 0.22); }
    .trade-history-page .status-approved { color: #55d69e; background: rgba(34, 197, 94, 0.1); border-color: rgba(34, 197, 94, 0.22); }
    .trade-history-page .status-completed { color: #55d69e; background: rgba(34, 197, 94, 0.1); border-color: rgba(34, 197, 94, 0.22); }
    .trade-history-page .status-rejected { color: #ff8585; background: rgba(239, 68, 68, 0.1); border-color: rgba(239, 68, 68, 0.22); }
    .trade-history-page .status-cancelled { color: #aab4be; background: rgba(139, 151, 162, 0.1); border-color: rgba(139, 151, 162, 0.2); }

    .trade-history-item-details {
        display: grid;
        grid-template-columns: 112px minmax(0, 1fr);
        align-items: center;
        gap: 16px;
        margin-top: 18px;
    }

    .trade-history-item-details img {
        display: block;
        width: 112px;
        height: 88px;
        padding: 8px;
        object-fit: contain;
        border: 1px solid #303a44;
        border-radius: 8px;
        background: #151b22;
    }

    .trade-history-meta {
        display: grid;
        grid-template-columns: repeat(3, minmax(0, 1fr));
        gap: 10px;
        color: #c4ccd3;
        font-size: 12px;
        line-height: 1.45;
    }

    .trade-history-meta > div {
        min-width: 0;
        padding: 10px 12px;
        border: 1px solid #242c34;
        border-radius: 7px;
        background: #0d1217;
        overflow-wrap: anywhere;
    }

    .trade-history-meta strong {
        display: block;
        margin-bottom: 4px;
        color: #68747f;
        font-size: 9px;
        font-weight: 800;
        letter-spacing: 0.1em;
        text-transform: uppercase;
    }

    .trade-history-description {
        margin: 16px 0 0;
        color: #aab4be;
        font-size: 13px;
        line-height: 1.65;
    }

    .trade-history-section {
        margin-top: 20px;
        padding-top: 18px;
        border-top: 1px solid #242c34;
    }

    .trade-history-section h3 {
        margin: 0 0 12px;
        color: #dfe5ea;
        font-size: 12px;
        font-weight: 800;
        letter-spacing: 0.06em;
        text-transform: uppercase;
    }

    .trade-history-requirements {
        display: grid;
        grid-template-columns: repeat(2, minmax(0, 1fr));
        gap: 9px;
        margin: 0;
        padding: 0;
        list-style: none;
    }

    .trade-history-requirements li {
        min-width: 0;
        padding: 12px 14px;
        border: 1px solid #242c34;
        border-radius: 7px;
        background: #0d1217;
        color: #c4ccd3;
        font-size: 12px;
        line-height: 1.5;
        overflow-wrap: anywhere;
    }

    .trade-history-requirements strong {
        display: block;
        margin-bottom: 3px;
        color: #f3f6f8;
        font-size: 13px;
    }

    .trade-history-requirements span,
    .trade-history-requirements small {
        display: block;
        color: #8b97a2;
        font-size: 11px;
    }

    .trade-history-admin-notes {
        margin-top: 16px;
        padding: 14px 16px;
        border: 1px solid rgba(14, 165, 233, 0.2);
        border-left: 3px solid #0ea5e9;
        border-radius: 7px;
        background: rgba(14, 165, 233, 0.06);
        color: #c4ccd3;
        font-size: 12px;
        line-height: 1.6;
    }

    .trade-history-admin-notes strong {
        color: #e5f5fd;
        font-size: 10px;
        letter-spacing: 0.08em;
        text-transform: uppercase;
    }

    .trade-history-admin-notes p {
        margin: 6px 0 0;
        white-space: pre-line;
    }

    .trade-history-timeline {
        display: flex;
        flex-wrap: wrap;
        gap: 8px 18px;
        margin-top: 16px;
        padding-top: 14px;
        border-top: 1px solid #242c34;
        color: #8b97a2;
        font-size: 11px;
    }

    .trade-history-timeline strong {
        color: #c4ccd3;
    }

    .trade-history-page .portal-empty-state {
        padding: 32px;
        border: 1px solid #242c34;
        border-radius: 10px;
        background: #10151b;
        color: #c4ccd3;
    }

    .trade-history-page .portal-empty-state h2 {
        margin: 0 0 8px;
        color: #f3f6f8;
        font-size: 18px;
    }

    .trade-history-page .portal-empty-state p {
        margin: 0;
        color: #8b97a2;
        font-size: 13px;
        line-height: 1.6;
    }

    @media (max-width: 900px) {
        .trade-history-page { padding: 20px; }
        .trade-history-meta { grid-template-columns: repeat(2, minmax(0, 1fr)); }
    }

    @media (max-width: 600px) {
        .trade-history-page { padding: 16px; }
        .trade-history-page .page-header-block { align-items: flex-start; }
        .trade-history-page .page-header-block h1 { font-size: 25px; }
        .trade-history-total { gap: 6px; padding: 8px; }
        .trade-history-total span { font-size: 8px; }
        .trade-history-item { padding: 16px; }
        .trade-history-header { gap: 10px; }
        .trade-history-header h2 { font-size: 18px; }
        .trade-status-badge { min-height: 25px; padding: 4px 7px; font-size: 9px; }
        .trade-history-item-details { grid-template-columns: 1fr; }
        .trade-history-item-details img { width: 100%; height: 150px; }
        .trade-history-meta,
        .trade-history-requirements { grid-template-columns: 1fr; }
        .trade-history-timeline { flex-direction: column; gap: 8px; }
        .trade-history-page .portal-empty-state { padding: 22px; }
    }
</style>