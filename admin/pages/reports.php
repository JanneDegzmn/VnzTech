<?php


require_once __DIR__ . '/../../backend/site_status.php';
vnzEnforceSiteMaintenance('auto', true);
require_once __DIR__ . '/../../backend/database.php';
require_once __DIR__ . '/../../backend/session.php';
require_once __DIR__ . '/../../backend/auth.php';
require_once __DIR__ . '/../../backend/csrf.php';
require_once __DIR__ . '/../../backend/pagination.php';

requireAdminLogin();

if (!hasPermission('view_reports')) {
    http_response_code(403);
    ?>
    <div class="admin-report-page">
        <div class="admin-report-empty">
            <h2>Access denied</h2>
            <p>You do not have permission to view reports.</p>
        </div>
    </div>
    <?php
    exit;
}

function reportEscape($value): string
{
    return htmlspecialchars(
        (string) ($value ?? ''),
        ENT_QUOTES,
        'UTF-8'
    );
}

function reportDateIsValid(string $value): bool
{
    $date = DateTimeImmutable::createFromFormat('!Y-m-d', $value);
    $errors = DateTimeImmutable::getLastErrors();

    return $date !== false
        && $date->format('Y-m-d') === $value
        && ($errors === false || ($errors['warning_count'] === 0 && $errors['error_count'] === 0));
}

function reportCsvValue($value): string
{
    $value = (string) ($value ?? '');

    if ($value !== '' && preg_match('/^[\s]*[=+@\-]/', $value)) {
        return "'" . $value;
    }

    return $value;
}

$today = new DateTimeImmutable('today');
$defaultFrom = $today->modify('first day of this month')->format('Y-m-d');
$defaultTo = $today->format('Y-m-d');
$dateFrom = trim((string) ($_GET['date_from'] ?? $defaultFrom));
$dateTo = trim((string) ($_GET['date_to'] ?? $defaultTo));
$dateError = '';

if (!reportDateIsValid($dateFrom) || !reportDateIsValid($dateTo) || $dateFrom > $dateTo) {
    $dateError = 'Choose a valid date range.';
    $dateFrom = $defaultFrom;
    $dateTo = $defaultTo;
}

$startAt = $dateFrom . ' 00:00:00';
$endBefore = (new DateTimeImmutable($dateTo))
    ->modify('+1 day')
    ->format('Y-m-d 00:00:00');

$submissionStmt = $pdo->prepare("
    SELECT status, COUNT(*) AS total
    FROM submissions
    WHERE created_at >= ? AND created_at < ?
    GROUP BY status
");
$submissionStmt->execute([$startAt, $endBefore]);
$submissionRows = $submissionStmt->fetchAll();
$submissionCounts = [];
$submissionTotal = 0;

foreach ($submissionRows as $row) {
    $submissionCounts[$row['status']] = (int) $row['total'];
    $submissionTotal += (int) $row['total'];
}

$pendingReview =
    ($submissionCounts['new_submission'] ?? 0)
    + ($submissionCounts['under_review'] ?? 0);

$offerStmt = $pdo->prepare("
    SELECT COUNT(*)
    FROM offers
    WHERE sent_at >= ? AND sent_at < ?
");
$offerStmt->execute([$startAt, $endBefore]);
$offersSent = (int) $offerStmt->fetchColumn();

$transactionCountStmt = $pdo->prepare("
    SELECT COUNT(*)
    FROM transactions
    WHERE created_at >= ? AND created_at < ?
");
$transactionCountStmt->execute([$startAt, $endBefore]);
$totalTransactions = (int) $transactionCountStmt->fetchColumn();
$pagination = vnzPaginationClamp(
    vnzPaginationState(12),
    $totalTransactions
);
$paginationHtml = vnzRenderPagination(
    $pagination,
    [
        'date_from' => $dateFrom,
        'date_to' => $dateTo
    ],
    'reports',
    'admin',
    '/VNZ-TECH/admin/dashboard.php'
);

$completedValueStmt = $pdo->prepare("
        SELECT COALESCE(SUM(final_amount), 0)
        FROM transactions
        WHERE transaction_status = 'completed'
            AND completed_at >= ?
            AND completed_at < ?
");
$completedValueStmt->execute([$startAt, $endBefore]);
$completedValue = (float) $completedValueStmt->fetchColumn();

$transactionSql = "
    SELECT
        t.transaction_reference,
        s.reference_number AS submission_reference,
        s.seller_name,
        t.final_amount,
        t.payment_method,
        t.payment_status,
        t.transaction_status,
        t.created_at
    FROM transactions t
    LEFT JOIN submissions s ON s.id = t.submission_id
    WHERE t.created_at >= ? AND t.created_at < ?
    ORDER BY t.created_at DESC
";

if (($_GET['format'] ?? '') === 'csv') {
    $exportStmt = $pdo->prepare($transactionSql);
    $exportStmt->execute([$startAt, $endBefore]);

    header('Content-Type: text/csv; charset=utf-8');
    header(
        'Content-Disposition: attachment; filename="vnz-tech-transactions-' .
        $dateFrom . '-to-' . $dateTo . '.csv"'
    );

    $output = fopen('php://output', 'w');
    fputcsv($output, [
        'Transaction Reference',
        'Submission Reference',
        'Seller',
        'Final Amount',
        'Payment Method',
        'Payment Status',
        'Transaction Status',
        'Created At'
    ]);

    while ($transaction = $exportStmt->fetch()) {
        fputcsv($output, array_map('reportCsvValue', [
            $transaction['transaction_reference'],
            $transaction['submission_reference'],
            $transaction['seller_name'],
            number_format((float) $transaction['final_amount'], 2, '.', ''),
            $transaction['payment_method'],
            $transaction['payment_status'],
            $transaction['transaction_status'],
            $transaction['created_at']
        ]));
    }

    fclose($output);
    exit;
}

$transactionStmt = $pdo->prepare(
    $transactionSql . " LIMIT {$pagination['per_page']} OFFSET {$pagination['offset']}"
);
$transactionStmt->execute([$startAt, $endBefore]);
$transactions = $transactionStmt->fetchAll();

$statusLabels = [
    'new_submission' => 'New submission',
    'under_review' => 'Under review',
    'offer_pending' => 'Offer pending',
    'offer_sent' => 'Offer sent',
    'accepted' => 'Accepted',
    'declined' => 'Declined',
    'cancelled' => 'Cancelled',
    'completed' => 'Completed'
];

?>

<link rel="stylesheet" href="/VNZ-TECH/assets/css/admin_operations.css">

<div class="admin-report-page">
    <header class="operations-header">
        <div>
            <span class="operations-eyebrow">PERFORMANCE</span>
            <h1>Reports</h1>
            <p>Submission activity, offers, and transaction value.</p>
        </div>
        <a
            class="operations-button operations-button-secondary"
            href="pages/reports.php?date_from=<?= reportEscape($dateFrom) ?>&amp;date_to=<?= reportEscape($dateTo) ?>&amp;format=csv"
        >
            <span aria-hidden="true">↓</span> Export CSV
        </a>
    </header>

    <?php if ($dateError !== ''): ?>
        <div class="operations-alert" role="alert"><?= reportEscape($dateError) ?></div>
    <?php endif; ?>

    <form class="operations-filter" method="get" action="/VNZ-TECH/admin/dashboard.php">
        <input type="hidden" name="page" value="reports">
        <label>
            <span>From</span>
            <input type="date" name="date_from" value="<?= reportEscape($dateFrom) ?>" required>
        </label>
        <label>
            <span>To</span>
            <input type="date" name="date_to" value="<?= reportEscape($dateTo) ?>" required>
        </label>
        <button class="operations-button" type="submit">Apply range</button>
    </form>

    <section class="operations-stats" aria-label="Report summary">
        <article class="operations-stat">
            <span>SUBMISSIONS</span>
            <strong><?= number_format($submissionTotal) ?></strong>
            <small>Received in selected period</small>
        </article>
        <article class="operations-stat">
            <span>AWAITING REVIEW</span>
            <strong><?= number_format($pendingReview) ?></strong>
            <small>New or under review</small>
        </article>
        <article class="operations-stat">
            <span>OFFERS SENT</span>
            <strong><?= number_format($offersSent) ?></strong>
            <small>Offers sent in selected period</small>
        </article>
        <article class="operations-stat">
            <span>COMPLETED VALUE</span>
            <strong>₱<?= number_format($completedValue, 2) ?></strong>
            <small>Completed during selected period</small>
        </article>
    </section>

    <section class="operations-panel">
        <div class="operations-panel-heading">
            <div>
                <h2>Submission status</h2>
                <p><?= reportEscape($dateFrom) ?> to <?= reportEscape($dateTo) ?></p>
            </div>
        </div>
        <div class="operations-status-grid">
            <?php foreach ($statusLabels as $status => $label): ?>
                <div class="operations-status-row">
                    <span><?= reportEscape($label) ?></span>
                    <strong><?= number_format($submissionCounts[$status] ?? 0) ?></strong>
                </div>
            <?php endforeach; ?>
        </div>
    </section>

    <section class="operations-panel">
        <div class="operations-panel-heading">
            <div>
                <h2>Transactions</h2>
                <p>
                    Showing <?= number_format(count($transactions)) ?> of
                    <?= number_format($totalTransactions) ?> transactions
                </p>
            </div>
        </div>
        <?php if ($pagination['last_page'] > 1): ?>
            <?= $paginationHtml ?>
        <?php endif; ?>

        <?php if (!$transactions): ?>
            <div class="operations-empty">No transactions in this date range.</div>
        <?php else: ?>
            <div class="operations-table-wrap">
                <table class="operations-table">
                    <thead>
                        <tr>
                            <th>TRANSACTION</th>
                            <th>SELLER</th>
                            <th>AMOUNT</th>
                            <th>PAYMENT</th>
                            <th>STATUS</th>
                            <th>DATE</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($transactions as $transaction): ?>
                            <tr>
                                <td>
                                    <strong><?= reportEscape($transaction['transaction_reference']) ?></strong>
                                    <small><?= reportEscape($transaction['submission_reference'] ?? '—') ?></small>
                                </td>
                                <td><?= reportEscape($transaction['seller_name'] ?? '—') ?></td>
                                <td>₱<?= number_format((float) $transaction['final_amount'], 2) ?></td>
                                <td>
                                    <?= reportEscape(ucfirst((string) ($transaction['payment_status'] ?? 'pending'))) ?>
                                    <small><?= reportEscape($transaction['payment_method'] ?? '—') ?></small>
                                </td>
                                <td><?= reportEscape(ucwords(str_replace('_', ' ', (string) $transaction['transaction_status']))) ?></td>
                                <td><?= reportEscape(date('M j, Y', strtotime($transaction['created_at']))) ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </section>
</div>