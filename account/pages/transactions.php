<?php

require_once __DIR__ . '/../../backend/database.php';
require_once __DIR__ . '/../../backend/session.php';

/*
|--------------------------------------------------------------------------
| AUTHENTICATION
|--------------------------------------------------------------------------
*/

if (
    empty($_SESSION['user_logged_in']) ||
    empty($_SESSION['user_id'])
) {
    http_response_code(401);

    echo '
        <div class="portal-error">
            <h2>Account unavailable</h2>
            <p>Your account session is no longer available.</p>
        </div>
    ';

    exit;
}

$userId = (int) $_SESSION['user_id'];

if ($userId <= 0) {
    http_response_code(401);

    echo '
        <div class="portal-error">
            <h2>Account unavailable</h2>
            <p>Your account session is no longer available.</p>
        </div>
    ';

    exit;
}

/*
|--------------------------------------------------------------------------
| SELLER ACCOUNT
|--------------------------------------------------------------------------
*/

try {
    $userStmt = $pdo->prepare("
        SELECT
            id,
            full_name,
            email,
            profile_picture,
            is_active
        FROM users
        WHERE id = ?
        LIMIT 1
    ");

    $userStmt->execute([$userId]);
    $user = $userStmt->fetch();

    if (!$user || (int) $user['is_active'] !== 1) {
        http_response_code(401);

        echo '
            <div class="portal-error">
                <h2>Account unavailable</h2>
                <p>Your account is currently unavailable.</p>
            </div>
        ';

        exit;
    }
} catch (Throwable $e) {
    http_response_code(500);

    echo '
        <div class="portal-error">
            <h2>Unable to load transactions</h2>
            <p>Something went wrong while loading your transactions.</p>
        </div>
    ';

    exit;
}

/*
|--------------------------------------------------------------------------
| FILTERS
|--------------------------------------------------------------------------
*/

$allowedStatuses = [
    'all',
    'pending',
    'completed',
    'cancelled'
];

$status = strtolower(trim($_GET['status'] ?? 'all'));

if (!in_array($status, $allowedStatuses, true)) {
    $status = 'all';
}

$search = trim($_GET['search'] ?? '');
$fromDate = trim($_GET['from'] ?? '');
$toDate = trim($_GET['to'] ?? '');

$allowedDateRanges = [
    'today',
    'this_week',
    'this_month',
    'last_month',
    'custom'
];

$dateRange = strtolower(trim($_GET['range'] ?? 'custom'));

if (!in_array($dateRange, $allowedDateRanges, true)) {
    $dateRange = 'custom';
}

/*
|--------------------------------------------------------------------------
| QUICK DATE RANGE
|--------------------------------------------------------------------------
*/

$today = new DateTime('today');

switch ($dateRange) {

    case 'today':

        $fromDate = $today->format('Y-m-d');
        $toDate = $today->format('Y-m-d');

        break;


    case 'this_week':

        $weekStart = clone $today;
        $weekStart->modify('monday this week');

        $weekEnd = clone $weekStart;
        $weekEnd->modify('+6 days');

        $fromDate = $weekStart->format('Y-m-d');
        $toDate = $weekEnd->format('Y-m-d');

        break;


    case 'this_month':

        $monthStart = new DateTime(
            $today->format('Y-m-01')
        );

        $monthEnd = clone $monthStart;
        $monthEnd->modify('last day of this month');

        $fromDate = $monthStart->format('Y-m-d');
        $toDate = $monthEnd->format('Y-m-d');

        break;


    case 'last_month':

        $lastMonthStart = new DateTime(
            $today->format('Y-m-01')
        );

        $lastMonthStart->modify('-1 month');

        $lastMonthEnd = clone $lastMonthStart;
        $lastMonthEnd->modify('last day of this month');

        $fromDate = $lastMonthStart->format('Y-m-d');
        $toDate = $lastMonthEnd->format('Y-m-d');

        break;


    case 'custom':
    default:

        // Keep manually selected From / To dates.

        break;
}

/*
|--------------------------------------------------------------------------
| TRANSACTION QUERY
|--------------------------------------------------------------------------
|
| Seller ownership comes from:
|
| transactions -> submissions -> user_id
|
*/

$sql = "
    SELECT
        t.id,
        t.transaction_reference,
        t.submission_id,
        t.offer_id,
        t.final_amount,
        t.payment_method,
        t.payment_status,
        t.transaction_status,
        t.notes,
        t.completed_at,
        t.created_at,
        t.updated_at,
        s.reference_number AS submission_reference
    FROM transactions t
    INNER JOIN submissions s
        ON s.id = t.submission_id
    WHERE s.user_id = ?
";

$params = [$userId];

if ($status !== 'all') {
    $sql .= "
        AND t.transaction_status = ?
    ";

    $params[] = $status;
}

if ($search !== '') {
    $sql .= "
        AND (
            t.transaction_reference LIKE ?
            OR s.reference_number LIKE ?
        )
    ";

    $searchValue = '%' . $search . '%';
    $params[] = $searchValue;
    $params[] = $searchValue;
}

if ($fromDate !== '') {
    $sql .= "
        AND t.created_at >= ?
    ";

    $params[] = $fromDate . ' 00:00:00';
}

if ($toDate !== '') {
    $sql .= "
        AND t.created_at <= ?
    ";

    $params[] = $toDate . ' 23:59:59';
}

$sql .= "
    ORDER BY t.id DESC
";

$transactionStmt = $pdo->prepare($sql);
$transactionStmt->execute($params);
$transactions = $transactionStmt->fetchAll();

/*
|--------------------------------------------------------------------------
| HELPERS
|--------------------------------------------------------------------------
*/

function transactionEscape($value)
{
    return htmlspecialchars(
        (string) $value,
        ENT_QUOTES,
        'UTF-8'
    );
}

function transactionFormatDate($value)
{
    if (empty($value)) {
        return '—';
    }

    $timestamp = strtotime($value);

    if (!$timestamp) {
        return transactionEscape($value);
    }

    return date('M d, Y · h:i A', $timestamp);
}

function transactionStatusLabel($status)
{
    return ucwords(
        str_replace(
            '_',
            ' ',
            (string) $status
        )
    );
}

function transactionStatusClass($status)
{
    $allowed = [
        'pending',
        'completed',
        'cancelled'
    ];

    if (!in_array($status, $allowed, true)) {
        return 'status-default';
    }

    return 'status-' . $status;
}

$totalTransactions = count($transactions);

?>

<div class="portal-submissions-page">

    <!-- =====================================================
         HEADER
    ====================================================== -->

    <header class="submissions-header">

        <div class="header-copy">

            <div class="eyebrow">
                SELLER PORTAL
            </div>

            <h1>
                Transactions.
            </h1>

            <p>
                Track your accepted offers
                and transaction progress with VNZ TECH.
            </p>

        </div>

    </header>


    <!-- =====================================================
         TRANSACTION FILTERS
    ====================================================== -->

    <section class="filter-panel transaction-filter-panel">

        <div class="filter-heading">

            <div>
                <span>TRANSACTIONS</span>

                <strong>
                    <?= number_format($totalTransactions) ?>
                </strong>
            </div>

            <p>
                Search and filter your transactions.
            </p>

        </div>

        <div class="transaction-date-ranges">

    <span class="transaction-filter-label">
        QUICK RANGE
    </span>

        <div class="transaction-date-range-buttons">

            <button
                type="submit"
                form="transaction-filter-form"
                name="range"
                value="today"
                class="transaction-date-range-button <?= $dateRange === 'today' ? 'active' : '' ?>"
            >
                TODAY
            </button>

            <button
                type="submit"
                form="transaction-filter-form"
                name="range"
                value="this_week"
                class="transaction-date-range-button <?= $dateRange === 'this_week' ? 'active' : '' ?>"
            >
                THIS WEEK
            </button>

            <button
                type="submit"
                form="transaction-filter-form"
                name="range"
                value="this_month"
                class="transaction-date-range-button <?= $dateRange === 'this_month' ? 'active' : '' ?>"
            >
                THIS MONTH
            </button>

            <button
                type="submit"
                form="transaction-filter-form"
                name="range"
                value="last_month"
                class="transaction-date-range-button <?= $dateRange === 'last_month' ? 'active' : '' ?>"
            >
                LAST MONTH
            </button>

            <button
                type="submit"
                form="transaction-filter-form"
                name="range"
                value="custom"
                class="transaction-date-range-button <?= $dateRange === 'custom' ? 'active' : '' ?>"
            >
                CUSTOM
            </button>

        </div>

    </div>


        <form
            id="transaction-filter-form"
            method="GET"
            action="/VNZ-TECH/account/dashboard.php"
            class="transaction-filter-form"
        >

            <input
                type="hidden"
                name="page"
                value="transactions"
            >


            <div class="transaction-filter-field">

                <label
                    for="transaction-search"
                    class="transaction-filter-label"
                >
                    SEARCH
                </label>

                <input
                    id="transaction-search"
                    type="text"
                    name="search"
                    value="<?= transactionEscape($search) ?>"
                    placeholder="#TX-... or #SP-..."
                    class="transaction-filter-input"
                >

            </div>


            <div class="transaction-filter-field">

                <label
                    for="transaction-status"
                    class="transaction-filter-label"
                >
                    STATUS
                </label>

                <select
                    id="transaction-status"
                    name="status"
                    class="transaction-filter-input"
                >

                    <option
                        value="all"
                        <?= $status === 'all' ? 'selected' : '' ?>
                    >
                        All
                    </option>

                    <option
                        value="pending"
                        <?= $status === 'pending' ? 'selected' : '' ?>
                    >
                        Pending
                    </option>

                    <option
                        value="completed"
                        <?= $status === 'completed' ? 'selected' : '' ?>
                    >
                        Completed
                    </option>

                    <option
                        value="cancelled"
                        <?= $status === 'cancelled' ? 'selected' : '' ?>
                    >
                        Cancelled
                    </option>

                </select>

            </div>


            <div class="transaction-filter-field">

                <label
                    for="transaction-from"
                    class="transaction-filter-label"
                >
                    FROM
                </label>

                <input
                    id="transaction-from"
                    type="date"
                    name="from"
                    value="<?= transactionEscape($fromDate) ?>"
                    class="transaction-filter-input"
                >

            </div>


            <div class="transaction-filter-field">

                <label
                    for="transaction-to"
                    class="transaction-filter-label"
                >
                    TO
                </label>

                <input
                    id="transaction-to"
                    type="date"
                    name="to"
                    value="<?= transactionEscape($toDate) ?>"
                    class="transaction-filter-input"
                >

            </div>


            <button
                type="submit"
                class="transaction-filter-apply"
            >
                APPLY
            </button>

        </form>

    </section>


    <!-- =====================================================
         TRANSACTIONS
    ====================================================== -->

    <section class="submission-section">

        <?php if (!$transactions): ?>

            <div class="empty-state">

                <div class="empty-icon">
                    ▤
                </div>

                <span class="empty-label">
                    NO TRANSACTIONS FOUND
                </span>

                <h2>
                    Nothing here yet.
                </h2>

                <p>
                    Your accepted offers will appear
                    here once a transaction has been created.
                </p>

            </div>

        <?php else: ?>

            <div class="submission-list">

                <?php foreach ($transactions as $transaction): ?>

                    <article class="submission-card transaction-card">

                        <div class="submission-card-top">

                            <div class="submission-identity">

                                <div class="reference-block">

                                    <span>
                                        TRANSACTION
                                    </span>

                                    <strong>
                                        <?= transactionEscape(
                                            $transaction['transaction_reference']
                                        ) ?>
                                    </strong>

                                </div>


                                <div class="submission-date">

                                    <span>
                                        CREATED
                                    </span>

                                    <strong>
                                        <?= transactionFormatDate(
                                            $transaction['created_at']
                                        ) ?>
                                    </strong>

                                </div>

                            </div>


                            <span
                                class="status-badge <?= transactionStatusClass(
                                    $transaction['transaction_status']
                                ) ?>"
                            >
                                <?= transactionEscape(
                                    transactionStatusLabel(
                                        $transaction['transaction_status']
                                    )
                                ) ?>
                            </span>

                        </div>


                        <div class="submission-card-body">

                            <div class="submission-item-summary">

                                <div class="summary-label">
                                    SUBMISSION
                                </div>

                                <h2>
                                    <?= transactionEscape(
                                        $transaction['submission_reference']
                                    ) ?>
                                </h2>

                                <div class="summary-meta">
                                    <span>
                                        <?php if ($transaction['offer_id'] !== null): ?>

                                            Offer #<?= transactionEscape(
                                                $transaction['offer_id']
                                            ) ?>

                                        <?php else: ?>

                                            Direct Admin Acceptance

                                        <?php endif; ?>
                                    </span>
                                </div>

                            </div>


                            <div class="submission-financial transaction-financial">

                                <div>

                                    <span>
                                        FINAL AMOUNT
                                    </span>

                                    <strong class="offer-amount">
                                        ₱<?= number_format(
                                            (float) $transaction['final_amount'],
                                            2
                                        ) ?>
                                    </strong>

                                </div>


                                <div>

                                    <span>
                                        PAYMENT
                                    </span>

                                    <strong>
                                        <?= transactionEscape(
                                            ucfirst(
                                                $transaction['payment_status']
                                            )
                                        ) ?>
                                    </strong>

                                </div>

                            </div>

                        </div>


                        <div class="submission-card-footer">

                            <div class="footer-status-text">
                                <strong>
                                    Transaction created
                                </strong>
                            </div>


                            <button
                                type="button"
                                class="view-button transaction-view-button"
                                data-transaction-id="<?= (int) $transaction['id'] ?>"
                                aria-haspopup="dialog"
                            >
                                VIEW DETAILS
                                <span>→</span>
                            </button>

                        </div>

                    </article>

                <?php endforeach; ?>

            </div>

        <?php endif; ?>

    </section>

</div>

<!-- =====================================================
     TRANSACTION DETAILS MODAL
====================================================== -->

<div
    id="transactionDetailsModal"
    class="transaction-details-modal"
    aria-hidden="true"
>
    <div
        class="transaction-details-modal-backdrop"
        data-transaction-modal-close
    ></div>

    <div
        class="transaction-details-modal-panel"
        role="dialog"
        aria-modal="true"
        aria-labelledby="transactionDetailsModalTitle"
    >

        <div class="transaction-details-modal-header">

            <div>
                <div class="transaction-details-modal-eyebrow">
                    TRANSACTION
                </div>

                <h2 id="transactionDetailsModalTitle">
                    Transaction Details
                </h2>
            </div>

            <button
                type="button"
                class="transaction-details-modal-close"
                data-transaction-modal-close
                aria-label="Close transaction details"
            >
                ×
            </button>

        </div>

        <div
            id="transactionDetailsModalBody"
            class="transaction-details-modal-body"
        >
            <div class="transaction-details-modal-loading">
                Loading transaction details...
            </div>
        </div>

    </div>
</div>

<script>
(function () {
    const modal = document.getElementById('transactionDetailsModal');
    const modalBody = document.getElementById('transactionDetailsModalBody');

    if (!modal || !modalBody) {
        return;
    }

    const closeButtons = modal.querySelectorAll(
        '[data-transaction-modal-close]'
    );

    function closeModal() {
        modal.classList.remove('is-open');
        modal.setAttribute('aria-hidden', 'true');
        document.body.classList.remove('transaction-details-modal-open');
        modalBody.innerHTML =
            '<div class="transaction-details-modal-loading">Loading transaction details...</div>';
    }

    async function openModal(transactionId) {
        if (!transactionId) {
            return;
        }

        modal.classList.add('is-open');
        modal.setAttribute('aria-hidden', 'false');
        document.body.classList.add('transaction-details-modal-open');

        modalBody.innerHTML =
            '<div class="transaction-details-modal-loading">Loading transaction details...</div>';

        try {
            const response = await fetch(
                'pages/transaction.php?id=' + encodeURIComponent(transactionId) + '&modal=1',
                {
                    method: 'GET',
                    credentials: 'same-origin',
                    headers: {
                        'X-Requested-With': 'XMLHttpRequest'
                    }
                }
            );

            if (!response.ok) {
                throw new Error('Unable to load transaction details.');
            }

            const html = await response.text();
            modalBody.innerHTML = html;

        } catch (error) {
            modalBody.innerHTML =
                '<div class="transaction-details-modal-error">' +
                    '<strong>Unable to load transaction</strong>' +
                    '<p>Please close this window and try again.</p>' +
                '</div>';
        }
    }

    document
        .querySelectorAll('.transaction-view-button[data-transaction-id]')
        .forEach(function (button) {
            button.addEventListener('click', function () {
                openModal(this.dataset.transactionId);
            });
        });

    closeButtons.forEach(function (button) {
        button.addEventListener('click', closeModal);
    });

    document.addEventListener('keydown', function (event) {
        if (
            event.key === 'Escape' &&
            modal.classList.contains('is-open')
        ) {
            closeModal();
        }
    });
})();
</script>
