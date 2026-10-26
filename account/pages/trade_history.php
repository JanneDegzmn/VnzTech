<?php
require_once __DIR__ . '/../../backend/database.php';
require_once __DIR__ . '/../../backend/session.php';
require_once __DIR__ . '/../../backend/csrf.php';
require_once __DIR__ . '/../../backend/pagination.php';

if (empty($_SESSION['user_logged_in']) || empty($_SESSION['user_id'])) {
    header('Location: /VNZ-TECH/account/login.php');
    exit;
}

$userId = (int) $_SESSION['user_id'];
$csrfToken = generateCsrfToken();
$pagination = vnzPaginationState(5);
$requestCountStmt = $pdo->prepare('SELECT COUNT(*) FROM trade_requests WHERE user_id = ?');
$requestCountStmt->execute([$userId]);
$pagination = vnzPaginationClamp($pagination, (int) $requestCountStmt->fetchColumn());
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
    LIMIT {$pagination['per_page']} OFFSET {$pagination['offset']}
");
$tradeRequestsStmt->execute([$userId]);
$tradeRequests = $tradeRequestsStmt->fetchAll();
$paginationHtml = vnzRenderPagination(
    $pagination,
    [],
    'trade_history',
    'seller',
    '/VNZ-TECH/account/dashboard.php?page=trade_history'
);

$activeTradeItemsStmt = $pdo->query("
    SELECT id, name, `condition`, description, image_path
    FROM trade_items
    WHERE is_active = 1
    ORDER BY sort_order ASC, id DESC
");
$activeTradeItems = $activeTradeItemsStmt->fetchAll();
$activeTradeRequirements = [];

if ($activeTradeItems) {
    $tradeItemIds = array_map(
        static fn (array $tradeItem): int => (int) $tradeItem['id'],
        $activeTradeItems
    );
    $placeholders = implode(',', array_fill(0, count($tradeItemIds), '?'));
    $activeTradeRequirementsStmt = $pdo->prepare("
        SELECT trade_item_id, requirement_type, quantity, storage, description
        FROM trade_requirements
        WHERE trade_item_id IN ($placeholders)
        ORDER BY sort_order ASC, id ASC
    ");
    $activeTradeRequirementsStmt->execute($tradeItemIds);

    foreach ($activeTradeRequirementsStmt->fetchAll() as $requirement) {
        $activeTradeRequirements[(int) $requirement['trade_item_id']][] = $requirement;
    }
}

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

<div class="trade-history-page" data-csrf-token="<?= htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8') ?>">
    <div class="page-header-block">
        <div>
            <div class="page-kicker">TRADE HISTORY</div>
            <h1>My Trade Requests</h1>
            <p class="trade-history-intro">Track the status and requirements for the items you have requested.</p>
        </div>
        <div class="trade-history-header-actions">
            <button type="button" class="trade-history-open-modal" data-open-trade-modal>Trade</button>
            <div class="trade-history-total" aria-label="<?= number_format($pagination['total']) ?> total trade requests">
                <strong><?= number_format($pagination['total']) ?></strong>
                <span>REQUESTS</span>
            </div>
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
                        <a
                            class="transaction-photo-link trade-history-image-link"
                            href="<?= htmlspecialchars($tradeImage, ENT_QUOTES, 'UTF-8') ?>"
                            aria-label="View <?= htmlspecialchars($tradeRequest['trade_item_name'], ENT_QUOTES, 'UTF-8') ?> image"
                        >
                            <img
                                src="<?= htmlspecialchars($tradeImage, ENT_QUOTES, 'UTF-8') ?>"
                                alt="<?= htmlspecialchars($tradeRequest['trade_item_name'], ENT_QUOTES, 'UTF-8') ?>"
                                loading="lazy"
                            >
                        </a>
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
                    <button
                        type="button"
                        class="trade-history-chat-button"
                        data-open-trade-request-chat="<?= (int) $tradeRequest['id'] ?>"
                    >Chat about this trade</button>
                </article>
            <?php endforeach; ?>
        </div>
        <?= $paginationHtml ?>
    <?php endif; ?>

    <div class="trade-request-modal" data-trade-modal hidden>
        <div class="trade-request-modal-backdrop" data-close-trade-modal></div>
        <section
            class="trade-request-modal-panel"
            role="dialog"
            aria-modal="true"
            aria-labelledby="tradeRequestModalTitle"
            tabindex="-1"
        >
            <header class="trade-request-modal-header">
                <div>
                    <div class="page-kicker">AVAILABLE TRADE ITEMS</div>
                    <h2 id="tradeRequestModalTitle">Choose an item to trade</h2>
                </div>
                <button type="button" class="trade-request-modal-close" data-close-trade-modal aria-label="Close trade items">×</button>
            </header>
            <div class="trade-request-modal-body">
                <?php if (!$activeTradeItems): ?>
                    <div class="trade-request-modal-empty">No trade items are currently available.</div>
                <?php else: ?>
                    <div class="trade-request-item-list">
                        <?php foreach ($activeTradeItems as $tradeItem): ?>
                            <?php
                            $tradeItemId = (int) $tradeItem['id'];
                            $requirements = $activeTradeRequirements[$tradeItemId] ?? [];
                            $tradeImage = !empty($tradeItem['image_path'])
                                ? '/VNZ-TECH/' . $tradeItem['image_path']
                                : '/VNZ-TECH/assets/images/vnz.png';
                            ?>
                            <article class="trade-request-option">
                                <img
                                    src="<?= htmlspecialchars($tradeImage, ENT_QUOTES, 'UTF-8') ?>"
                                    alt="<?= htmlspecialchars($tradeItem['name'], ENT_QUOTES, 'UTF-8') ?>"
                                    loading="lazy"
                                    onerror="this.src='/VNZ-TECH/assets/images/vnz.png';"
                                >
                                <div class="trade-request-option-content">
                                    <div class="trade-request-option-heading">
                                        <h3><?= htmlspecialchars($tradeItem['name'], ENT_QUOTES, 'UTF-8') ?></h3>
                                        <span><?= htmlspecialchars($tradeItem['condition'], ENT_QUOTES, 'UTF-8') ?></span>
                                    </div>
                                    <?php if (!empty($tradeItem['description'])): ?>
                                        <p class="trade-request-option-description"><?= htmlspecialchars($tradeItem['description'], ENT_QUOTES, 'UTF-8') ?></p>
                                    <?php endif; ?>
                                    <?php if ($requirements): ?>
                                        <ul class="trade-history-requirements trade-request-option-requirements">
                                            <?php foreach ($requirements as $requirement): ?>
                                                <?php $isBoard = $requirement['requirement_type'] === 'scrap_board'; ?>
                                                <li>
                                                    <strong><?= (int) $requirement['quantity'] ?> × <?= $isBoard ? 'Scrap Board' : 'Damaged Phone' ?></strong>
                                                    <span><?= $isBoard ? 'Classification' : 'Storage' ?>: <?= htmlspecialchars($requirement['storage'], ENT_QUOTES, 'UTF-8') ?></span>
                                                    <?php if (!empty($requirement['description'])): ?><small><?= htmlspecialchars($requirement['description'], ENT_QUOTES, 'UTF-8') ?></small><?php endif; ?>
                                                </li>
                                            <?php endforeach; ?>
                                        </ul>
                                        <button type="button" class="trade-request-submit" data-request-trade="<?= $tradeItemId ?>">Request Trade</button>
                                    <?php else: ?>
                                        <p class="trade-request-unavailable">This item has no trade requirements configured yet.</p>
                                        <button type="button" class="trade-request-submit" disabled>Unavailable</button>
                                    <?php endif; ?>
                                </div>
                            </article>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
        </section>
    </div>
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

    .trade-history-header-actions {
        display: flex;
        align-items: center;
        gap: 10px;
        flex: 0 0 auto;
    }

    .trade-history-open-modal,
    .trade-request-submit {
        min-height: 40px;
        padding: 0 17px;
        border: 1px solid #0ea5e9;
        border-radius: 7px;
        background: linear-gradient(135deg, #0ea5e9, #0284c7);
        color: #fff;
        font-size: 12px;
        font-weight: 800;
        cursor: pointer;
        transition: filter 0.18s ease, transform 0.18s ease;
    }

    .trade-history-open-modal:hover,
    .trade-request-submit:not(:disabled):hover {
        filter: brightness(1.12);
        transform: translateY(-1px);
    }

    .trade-request-submit:disabled {
        border-color: #303a44;
        background: #151b22;
        color: #68747f;
        cursor: not-allowed;
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

    .trade-history-item-details .trade-history-image-link {
        display: block;
        width: 112px;
        height: 88px;
        overflow: hidden;
        border: 1px solid #303a44;
        border-radius: 8px;
        background: #151b22;
        cursor: zoom-in;
    }

    .trade-history-item-details .trade-history-image-link img {
        display: block;
        width: 100%;
        height: 100%;
        object-fit: cover;
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

    .trade-request-modal[hidden] {
        display: none !important;
    }

    .trade-request-modal {
        position: fixed;
        inset: 0;
        z-index: 10020;
        display: flex;
        align-items: center;
        justify-content: center;
        padding: 24px;
        opacity: 0;
        visibility: hidden;
        transition: opacity 0.2s ease, visibility 0.2s ease;
    }

    .trade-request-modal.is-open {
        opacity: 1;
        visibility: visible;
    }

    .trade-request-modal-backdrop {
        position: absolute;
        inset: 0;
        background: rgba(2, 6, 10, 0.82);
        backdrop-filter: blur(8px);
    }

    .trade-request-modal-panel {
        position: relative;
        z-index: 1;
        display: flex;
        flex-direction: column;
        width: min(820px, 100%);
        max-height: min(82vh, 760px);
        overflow: hidden;
        border: 1px solid #303a44;
        border-radius: 13px;
        background: #0d1217;
        box-shadow: 0 24px 80px rgba(0, 0, 0, 0.55);
        transform: translateY(12px) scale(0.985);
        transition: transform 0.2s ease;
    }

    .trade-request-modal.is-open .trade-request-modal-panel {
        transform: translateY(0) scale(1);
    }

    .trade-request-modal-header {
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 16px;
        padding: 19px 22px;
        border-bottom: 1px solid #242c34;
    }

    .trade-request-modal-header h2 {
        margin: 0;
        color: #f3f6f8;
        font-size: 20px;
    }

    .trade-request-modal-close {
        display: grid;
        width: 36px;
        height: 36px;
        flex: 0 0 auto;
        place-items: center;
        border: 1px solid #303a44;
        border-radius: 7px;
        background: #151b22;
        color: #c4ccd3;
        font-size: 23px;
        cursor: pointer;
    }

    .trade-request-modal-body {
        overflow: auto;
        padding: 18px;
    }

    .trade-request-item-list {
        display: grid;
        gap: 12px;
    }

    .trade-request-option {
        display: grid;
        grid-template-columns: 120px minmax(0, 1fr);
        gap: 16px;
        padding: 14px;
        border: 1px solid #242c34;
        border-radius: 9px;
        background: #10151b;
    }

    .trade-request-option > img {
        width: 120px;
        height: 120px;
        border: 1px solid #303a44;
        border-radius: 7px;
        background: #151b22;
        object-fit: cover;
    }

    .trade-request-option-content {
        min-width: 0;
    }

    .trade-request-option-heading {
        display: flex;
        align-items: flex-start;
        justify-content: space-between;
        gap: 12px;
    }

    .trade-request-option-heading h3 {
        margin: 0;
        color: #f3f6f8;
        font-size: 17px;
    }

    .trade-request-option-heading > span {
        color: #8b97a2;
        font-size: 11px;
    }

    .trade-request-option-description,
    .trade-request-unavailable {
        margin: 8px 0 0;
        color: #aab4be;
        font-size: 12px;
        line-height: 1.55;
    }

    .trade-request-option-requirements {
        margin-top: 12px;
    }

    .trade-request-option-requirements li {
        padding: 9px 11px;
    }

    .trade-request-option .trade-request-submit {
        margin-top: 12px;
    }

    .trade-request-modal-empty,
    .trade-request-success {
        padding: 28px;
        border: 1px solid #242c34;
        border-radius: 9px;
        background: #10151b;
        color: #c4ccd3;
        text-align: center;
    }

    .trade-request-success h3 {
        margin: 0 0 8px;
        color: #55d69e;
        font-size: 19px;
    }

    .trade-request-success p {
        margin: 6px 0;
        font-size: 13px;
    }

    .trade-request-error {
        margin-bottom: 12px;
        padding: 11px 13px;
        border: 1px solid rgba(239, 68, 68, 0.3);
        border-radius: 7px;
        background: rgba(239, 68, 68, 0.08);
        color: #ff9b9b;
        font-size: 12px;
    }

    @media (max-width: 900px) {
        .trade-history-page { padding: 20px; }
        .trade-history-meta { grid-template-columns: repeat(2, minmax(0, 1fr)); }
    }

    @media (max-width: 600px) {
        .trade-history-page { padding: 16px; }
        .trade-history-page .page-header-block { align-items: flex-start; }
        .trade-history-page .page-header-block h1 { font-size: 25px; }
        .trade-history-header-actions { flex-direction: column-reverse; align-items: stretch; }
        .trade-history-open-modal { min-height: 36px; }
        .trade-history-total { gap: 6px; padding: 8px; }
        .trade-history-total span { font-size: 8px; }
        .trade-history-item { padding: 16px; }
        .trade-history-header { gap: 10px; }
        .trade-history-header h2 { font-size: 18px; }
        .trade-status-badge { min-height: 25px; padding: 4px 7px; font-size: 9px; }
        .trade-history-item-details { grid-template-columns: 1fr; }
        .trade-history-item-details .trade-history-image-link { width: 100%; height: 150px; }
        .trade-history-meta,
        .trade-history-requirements { grid-template-columns: 1fr; }
        .trade-history-timeline { flex-direction: column; gap: 8px; }
        .trade-history-page .portal-empty-state { padding: 22px; }
        .trade-request-modal { padding: 10px; }
        .trade-request-modal-panel { max-height: 90vh; }
        .trade-request-modal-header { padding: 15px; }
        .trade-request-modal-body { padding: 12px; }
        .trade-request-option { grid-template-columns: 1fr; gap: 12px; }
        .trade-request-option > img { width: 100%; height: 150px; }
        .trade-request-option-heading { flex-direction: column; gap: 4px; }
    }

    @media (prefers-reduced-motion: reduce) {
        .trade-history-open-modal,
        .trade-request-submit,
        .trade-request-modal,
        .trade-request-modal-panel {
            transition: none;
        }
    }
</style>

<script>
(function () {
    const page = document.querySelector('#contentArea .trade-history-page');
    if (!page || !page.matches('.trade-history-page')) {
        return;
    }

    const modal = page.querySelector('[data-trade-modal]');
    const modalPanel = modal?.querySelector('.trade-request-modal-panel');
    let requestSubmitted = false;
    let closeTimer = null;

    function openModal() {
        if (!modal) {
            return;
        }

        window.clearTimeout(closeTimer);
        modal.hidden = false;
        window.requestAnimationFrame(function () {
            modal.classList.add('is-open');
        });
        modalPanel?.focus();
    }

    function closeModal() {
        if (!modal) {
            return;
        }

        modal.classList.remove('is-open');
        closeTimer = window.setTimeout(function () {
            modal.hidden = true;

            if (
                requestSubmitted &&
                page.isConnected &&
                typeof window.loadSellerPage === 'function'
            ) {
                window.loadSellerPage('trade_history', false);
            }
        }, 210);
    }

    page.addEventListener('click', async function (event) {
        const openButton = event.target.closest('[data-open-trade-modal]');
        if (openButton) {
            openModal();
            return;
        }

        if (event.target.closest('[data-close-trade-modal]')) {
            closeModal();
            return;
        }

        const requestButton = event.target.closest('[data-request-trade]');
        if (!requestButton || requestButton.disabled) {
            return;
        }

        const formBody = new URLSearchParams();
        formBody.set('action', 'create_request');
        formBody.set('trade_item_id', requestButton.dataset.requestTrade);
        formBody.set('csrf_token', page.dataset.csrfToken || '');
        requestButton.disabled = true;
        requestButton.textContent = 'Submitting...';

        try {
            const response = await fetch('/VNZ-TECH/backend/trade_requests.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8',
                    'X-Requested-With': 'XMLHttpRequest'
                },
                credentials: 'same-origin',
                body: formBody.toString(),
                cache: 'no-store'
            });
            const data = await response.json();

            if (!response.ok || !data.success) {
                throw new Error(data.message || 'Unable to submit trade request.');
            }

            requestSubmitted = true;
            modal.querySelector('.trade-request-modal-body').innerHTML = `
                <div class="trade-request-success" role="status">
                    <h3>Trade request submitted</h3>
                    <p>Reference: <strong>${String(data.reference).replace(/[&<>"']/g, character => ({
                        '&': '&amp;',
                        '<': '&lt;',
                        '>': '&gt;',
                        '"': '&quot;',
                        "'": '&#039;'
                    })[character])}</strong></p>
                    <p>Status: <strong>${String(data.status).replace(/[&<>"']/g, '')}</strong></p>
                    <p>Close this window to see the updated request in your Trade History.</p>
                </div>
            `;
        } catch (error) {
            const errorElement = document.createElement('div');
            errorElement.className = 'trade-request-error';
            errorElement.setAttribute('role', 'alert');
            errorElement.textContent = error.message || 'Unable to submit trade request.';
            modal.querySelector('.trade-request-modal-body').prepend(errorElement);
            requestButton.disabled = false;
            requestButton.textContent = 'Request Trade';
        }
    });

    page.addEventListener('keydown', function (event) {
        if (event.key === 'Escape' && modal && !modal.hidden) {
            closeModal();
        }
    });
})();
</script>