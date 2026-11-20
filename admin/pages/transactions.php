<?php


require_once __DIR__ . '/../../backend/site_status.php';
vnzEnforceSiteMaintenance('auto', true);
require_once __DIR__ . '/../../backend/database.php';
require_once __DIR__ . '/../../backend/session.php';
require_once __DIR__ . '/../../backend/auth.php';
require_once __DIR__ . '/../../backend/pagination.php';

requireAdminLogin();

/*
|--------------------------------------------------------------------------
| Permission Check
|--------------------------------------------------------------------------
|
| We use the existing submissions permission for now because your current
| permission system already has view_submissions.
|
*/

if (!hasPermission('view_submissions')) {

    http_response_code(403);
    ?>
    <link rel="stylesheet" href="/VNZ-TECH/assets/css/admin_transactions.css">
    
    <div class="transactions-page">

        <div class="transaction-access-denied">

            <div class="access-denied-icon">
                !
            </div>

            <div>

                <h2>
                    Access Denied
                </h2>

                <p>
                    You do not have permission to view transactions.
                </p>

            </div>

        </div>

    </div>

    <?php
    exit;
}


/*
|--------------------------------------------------------------------------
| Filters
|--------------------------------------------------------------------------
*/

$search = trim($_GET['search'] ?? '');
$statusFilter = trim($_GET['status'] ?? '');
$pagination = vnzPaginationState(12);


/*
|--------------------------------------------------------------------------
| Allowed Transaction Statuses
|--------------------------------------------------------------------------
*/

$allowedStatuses = [
    'pending',
    'completed',
    'cancelled'
];


/*
|--------------------------------------------------------------------------
| Build Transaction Query
|--------------------------------------------------------------------------
|
| Admin can see ALL transactions.
|
| transactions
|      ↓
| submissions
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

        s.reference_number AS submission_reference,
        s.seller_name,
        s.seller_mobile,
        s.seller_address

    FROM transactions t

    LEFT JOIN submissions s
        ON s.id = t.submission_id

    WHERE 1 = 1
";

$params = [];
$countSql = "
    SELECT COUNT(*)
    FROM transactions t
    LEFT JOIN submissions s ON s.id = t.submission_id
    WHERE 1 = 1
";
$countParams = [];


/*
|--------------------------------------------------------------------------
| Search
|--------------------------------------------------------------------------
*/

if ($search !== '') {

    $sql .= "
        AND (
            t.transaction_reference LIKE ?
            OR s.reference_number LIKE ?
            OR s.seller_name LIKE ?
            OR s.seller_mobile LIKE ?
        )
    ";

    $searchValue = '%' . $search . '%';

    $params[] = $searchValue;
    $params[] = $searchValue;
    $params[] = $searchValue;
    $params[] = $searchValue;
    $countSql .= "
        AND (
            t.transaction_reference LIKE ?
            OR s.reference_number LIKE ?
            OR s.seller_name LIKE ?
            OR s.seller_mobile LIKE ?
        )
    ";
    array_push(
        $countParams,
        $searchValue,
        $searchValue,
        $searchValue,
        $searchValue
    );
}


/*
|--------------------------------------------------------------------------
| Status Filter
|--------------------------------------------------------------------------
*/

if (
    $statusFilter !== ''
    && in_array($statusFilter, $allowedStatuses, true)
) {

    $sql .= "
        AND t.transaction_status = ?
    ";

    $params[] = $statusFilter;
    $countSql .= ' AND t.transaction_status = ?';
    $countParams[] = $statusFilter;
}

$countStmt = $pdo->prepare($countSql);
$countStmt->execute($countParams);
$pagination = vnzPaginationClamp($pagination, (int) $countStmt->fetchColumn());


/*
|--------------------------------------------------------------------------
| Sort
|--------------------------------------------------------------------------
*/

$sql .= "
    ORDER BY t.created_at DESC
    LIMIT {$pagination['per_page']} OFFSET {$pagination['offset']}
";


/*
|--------------------------------------------------------------------------
| Execute
|--------------------------------------------------------------------------
*/

$stmt = $pdo->prepare($sql);
$stmt->execute($params);

$transactions = $stmt->fetchAll();
$paginationHtml = vnzRenderPagination(
    $pagination,
    array_filter([
        'search' => $search,
        'status' => $statusFilter
    ], static fn ($value): bool => $value !== ''),
    'transactions',
    'admin',
    '/VNZ-TECH/admin/dashboard.php'
);


/*
|--------------------------------------------------------------------------
| Helper Functions
|--------------------------------------------------------------------------
*/

function transactionStatusLabel(string $status): string
{
    $labels = [

        'pending'
            => 'Pending',

        'completed'
            => 'Completed',

        'cancelled'
            => 'Cancelled'

    ];

    return $labels[$status]
        ?? ucwords(str_replace('_', ' ', $status));
}


function transactionStatusClass(string $status): string
{
    $classes = [

        'pending'
            => 'status-pending',

        'completed'
            => 'status-completed',

        'cancelled'
            => 'status-cancelled'

    ];

    return $classes[$status]
        ?? 'status-default';
}


function transactionPeso($amount): string
{
    return '₱' . number_format(
        (float) $amount,
        2
    );
}


function transactionDate(string $date): string
{
    $timestamp = strtotime($date);

    if (!$timestamp) {
        return htmlspecialchars($date);
    }

    return
        date('M d, Y', $timestamp)
        .
        '<span class="transaction-time">'
        .
        date('h:i A', $timestamp)
        .
        '</span>';
}


/*
|--------------------------------------------------------------------------
| Total
|--------------------------------------------------------------------------
*/

$totalTransactions = $pagination['total'];

?>

<link
    rel="stylesheet"
    href="/VNZ-TECH/assets/css/admin_transactions.css"
>

<link
    rel="stylesheet"
    href="/VNZ-TECH/assets/css/transaction_review.css?v=<?= filemtime(__DIR__ . '/../../assets/css/transaction_review.css') ?>"
>


<div class="transactions-page">


    <!-- =====================================================
         PAGE HEADER
    ====================================================== -->

    <div class="transactions-header">

        <div class="transactions-heading">

            <div class="transactions-kicker">
                VNZ TECH MANAGEMENT
            </div>

            <h1>
                Transactions
            </h1>

            <p>
                Monitor and manage transactions created from accepted offers.
            </p>

        </div>


        <div class="transactions-count-card">

            <span>
                TOTAL TRANSACTIONS
            </span>

            <strong>
                <?= number_format($totalTransactions) ?>
            </strong>

        </div>

    </div>



    <!-- =====================================================
         FILTER BAR
    ====================================================== -->

    <div class="transactions-filter-card">

        <div class="transaction-search">

            <span class="search-symbol">
                ⌕
            </span>

            <input
                type="text"
                id="transactionSearch"
                placeholder="Search transaction, submission, or seller..."
                value="<?= htmlspecialchars($search) ?>"
                autocomplete="off"
            >

        </div>


        <select
            id="transactionStatusFilter"
            class="transaction-filter-select"
        >

            <option value="">
                All Statuses
            </option>

            <?php foreach ($allowedStatuses as $status): ?>

                <option
                    value="<?= htmlspecialchars($status) ?>"
                    <?= $statusFilter === $status ? 'selected' : '' ?>
                >
                    <?= htmlspecialchars(
                        transactionStatusLabel($status)
                    ) ?>
                </option>

            <?php endforeach; ?>

        </select>


        <button
            type="button"
            id="clearTransactionFilters"
            class="clear-transaction-filters"
        >
            CLEAR
        </button>

    </div>


    <?php if ($pagination['last_page'] > 1): ?>
        <?= $paginationHtml ?>
    <?php endif; ?>


    <!-- =====================================================
         TRANSACTIONS TABLE
    ====================================================== -->

    <div class="transactions-card">


        <div class="transactions-card-header">

            <div>

                <h2>
                    All Transactions
                </h2>

                <span id="transactionVisibleCount">

                    <?= number_format($totalTransactions) ?>
                    transaction<?= $totalTransactions !== 1 ? 's' : '' ?> total

                </span>

            </div>

        </div>



        <?php if (empty($transactions)): ?>


            <!-- =================================================
                 EMPTY
            ================================================== -->

            <div class="transactions-empty">

                <div class="empty-symbol">
                    +
                </div>

                <h3>
                    No transactions yet
                </h3>

                <p>
                    Transactions will appear here once a seller accepts
                    an offer and a transaction is created.
                </p>

            </div>


        <?php else: ?>


            <!-- =================================================
                 TABLE
            ================================================== -->

            <div class="transactions-table-wrapper">

                <table class="transactions-table">

                    <thead>

                        <tr>

                            <th>
                                TRANSACTION
                            </th>

                            <th>
                                SELLER
                            </th>

                            <th>
                                SUBMISSION
                            </th>

                            <th>
                                FINAL AMOUNT
                            </th>

                            <th>
                                PAYMENT
                            </th>

                            <th>
                                STATUS
                            </th>

                            <th>
                                DATE
                            </th>

                            <th>
                                ACTION
                            </th>

                        </tr>

                    </thead>


                    <tbody id="transactionsTableBody">


                    <?php foreach ($transactions as $transaction): ?>

                        <?php

                        $reference =
                            $transaction['transaction_reference']
                            ?? '—';

                        $sellerName =
                            $transaction['seller_name']
                            ?? '—';

                        $sellerMobile =
                            $transaction['seller_mobile']
                            ?? '—';

                        $submissionReference =
                            $transaction['submission_reference']
                            ?? '—';

                        $paymentMethod =
                            $transaction['payment_method']
                            ?? '—';

                        $paymentStatus =
                            $transaction['payment_status']
                            ?? '—';

                        $transactionStatus =
                            $transaction['transaction_status']
                            ?? '';

                        $finalAmount =
                            (float) (
                                $transaction['final_amount']
                                ?? 0
                            );

                        $searchData = strtolower(

                            $reference
                            . ' '
                            . $sellerName
                            . ' '
                            . $sellerMobile
                            . ' '
                            . $submissionReference

                        );

                        ?>


                        <tr

                            class="transaction-row"

                            data-search="<?= htmlspecialchars(
                                $searchData
                            ) ?>"

                            data-status="<?= htmlspecialchars(
                                $transactionStatus
                            ) ?>"

                        >


                            <!-- TRANSACTION -->

                            <td>

                                <div class="transaction-reference-cell">

                                    <strong>
                                        <?= htmlspecialchars(
                                            $reference
                                        ) ?>
                                    </strong>

                                    <span>
                                        Offer #<?= (int) $transaction['offer_id'] ?>
                                    </span>

                                </div>

                            </td>


                            <!-- SELLER -->

                            <td>

                                <div class="transaction-seller-cell">

                                    <strong>
                                        <?= htmlspecialchars(
                                            $sellerName
                                        ) ?>
                                    </strong>

                                    <span>
                                        <?= htmlspecialchars(
                                            $sellerMobile
                                        ) ?>
                                    </span>

                                </div>

                            </td>


                            <!-- SUBMISSION -->

                            <td>

                                <div class="transaction-submission-cell">

                                    <strong>
                                        <?= htmlspecialchars(
                                            $submissionReference
                                        ) ?>
                                    </strong>

                                    <span>
                                        Submission
                                    </span>

                                </div>

                            </td>


                            <!-- FINAL AMOUNT -->

                            <td>

                                <span class="transaction-amount-cell">

                                    <?= transactionPeso(
                                        $finalAmount
                                    ) ?>

                                </span>

                            </td>


                            <!-- PAYMENT -->

                            <td>

                                <div class="transaction-payment-cell">

                                    <strong>
                                        <?= htmlspecialchars(
                                            ucfirst(
                                                $paymentStatus
                                            )
                                        ) ?>
                                    </strong>

                                    <span>
                                        <?= htmlspecialchars(
                                            ucfirst(
                                                str_replace(
                                                    '_',
                                                    ' ',
                                                    $paymentMethod
                                                )
                                            )
                                        ) ?>
                                    </span>

                                </div>

                            </td>


                            <!-- STATUS -->

                            <td>

                                <span
                                    class="transaction-status <?= htmlspecialchars(
                                        transactionStatusClass(
                                            $transactionStatus
                                        )
                                    ) ?>"
                                >

                                    <?= htmlspecialchars(
                                        transactionStatusLabel(
                                            $transactionStatus
                                        )
                                    ) ?>

                                </span>

                            </td>


                            <!-- DATE -->

                            <td>

                                <div class="transaction-date">

                                    <?= transactionDate(
                                        $transaction['created_at']
                                    ) ?>

                                </div>

                            </td>


                            <!-- ACTION -->

                            <td>

                                <button
                                    type="button"
                                    class="view-transaction-button"
                                    data-transaction-id="<?= (int) $transaction['id'] ?>"
                                >
                                    VIEW
                                </button>

                            </td>


                        </tr>


                    <?php endforeach; ?>


                    </tbody>

                </table>

            </div>

            <!-- =================================================
                 FILTERED EMPTY
            ================================================== -->

            <div
                id="filteredTransactionEmpty"
                class="filtered-transactions-empty"
                style="display: none;"
            >

                <div>
                    ⌕
                </div>

                <h3>
                    No matching transactions
                </h3>

                <p>
                    Try changing your search or status filter.
                </p>

            </div>


        <?php endif; ?>


    </div>


</div>



<!-- =====================================================
     TRANSACTION DETAILS MODAL
====================================================== -->

<div
    id="transactionDetailsModal"
    class="admin-transaction-modal"
    aria-hidden="true"
>

    <div
        class="admin-transaction-modal-backdrop"
        data-close-transaction-modal
    ></div>


    <div
        class="admin-transaction-modal-panel"
        role="dialog"
        aria-modal="true"
        aria-labelledby="adminTransactionModalTitle"
    >

        <div class="admin-transaction-modal-header">

            <div>

                <div class="admin-transaction-modal-kicker">
                    VNZ TECH MANAGEMENT
                </div>

                <h2 id="adminTransactionModalTitle">
                    Transaction Details
                </h2>

            </div>


            <button
                type="button"
                class="admin-transaction-modal-close"
                data-close-transaction-modal
                aria-label="Close"
            >
                ×
            </button>

        </div>


        <div
            id="adminTransactionModalBody"
            class="admin-transaction-modal-body"
        >

            <div class="admin-transaction-loading">
                Loading transaction...
            </div>

        </div>

    </div>

</div>


<!-- =====================================================
     PHOTO LIGHTBOX
====================================================== -->

<div
    id="transactionPhotoLightbox"
    class="transaction-photo-lightbox"
    aria-hidden="true"
>

    <div
        class="transaction-photo-lightbox-backdrop"
        data-close-photo-lightbox
    ></div>

    <button
        type="button"
        class="transaction-photo-lightbox-close"
        data-close-photo-lightbox
        aria-label="Close"
    >
        ×
    </button>

    <img
        src=""
        alt=""
        class="transaction-photo-lightbox-img"
    >

</div>

<script>

(function () {

    const searchInput =
        document.getElementById(
            'transactionSearch'
        );

    const statusFilter =
        document.getElementById(
            'transactionStatusFilter'
        );

    const clearButton =
        document.getElementById(
            'clearTransactionFilters'
        );

    let filterTimer = null;
    function applyTransactionFilters() {
        window.clearTimeout(filterTimer);
        filterTimer = window.setTimeout(() => {
            window.loadAdminPage?.('transactions', {
                search: searchInput?.value.trim() || '',
                status: statusFilter?.value || '',
                list_page: 1
            });
        }, 250);
    }


    /*
    |--------------------------------------------------------------------------
    | SEARCH
    |--------------------------------------------------------------------------
    */

    if (searchInput) {

        searchInput.addEventListener(
            'input',
            applyTransactionFilters
        );

    }


    /*
    |--------------------------------------------------------------------------
    | STATUS
    |--------------------------------------------------------------------------
    */

    if (statusFilter) {

        statusFilter.addEventListener(
            'change',
            applyTransactionFilters
        );

    }


    /*
    |--------------------------------------------------------------------------
    | CLEAR
    |--------------------------------------------------------------------------
    */

    if (clearButton) {

        clearButton.addEventListener(
            'click',
            function () {

                if (searchInput) {
                    searchInput.value = '';
                }

                if (statusFilter) {
                    statusFilter.value = '';
                }

                applyTransactionFilters();

            }
        );

    }


    /*
    |--------------------------------------------------------------------------
    | MODAL
    |--------------------------------------------------------------------------
    */

    const modal =
        document.getElementById(
            'transactionDetailsModal'
        );

    const modalBody =
        document.getElementById(
            'adminTransactionModalBody'
        );


    function closeModal() {

        if (!modal) {
            return;
        }

        modal.classList.remove(
            'is-open'
        );

        modal.setAttribute(
            'aria-hidden',
            'true'
        );

        document.body.classList.remove(
            'admin-transaction-modal-open'
        );

    }


    async function openModal(transactionId) {

        if (!modal || !modalBody) {
            return;
        }

        if (!transactionId) {
            return;
        }


        modal.classList.add(
            'is-open'
        );

        modal.setAttribute(
            'aria-hidden',
            'false'
        );

        document.body.classList.add(
            'admin-transaction-modal-open'
        );


        modalBody.innerHTML = `

            <div class="admin-transaction-loading">
                Loading transaction...
            </div>

        `;


        try {

            const response =
                await fetch(
                    'pages/transaction_review.php?id='
                    +
                    encodeURIComponent(
                        transactionId
                    )
                    +
                    '&modal=1',
                    {
                        method: 'GET',
                        credentials: 'same-origin',
                        headers: {
                            'X-Requested-With':
                                'XMLHttpRequest'
                        }
                    }
                );


            if (!response.ok) {
                throw new Error(
                    'Unable to load transaction.'
                );
            }


            const html =
                await response.text();


            modalBody.innerHTML =
                html;


        } catch (error) {

            modalBody.innerHTML = `

                <div class="admin-transaction-error">

                    <strong>
                        Unable to load transaction
                    </strong>

                    <p>
                        Please close this window and try again.
                    </p>

                </div>

            `;

        }

    }


    /*
    |--------------------------------------------------------------------------
    | VIEW BUTTONS
    |--------------------------------------------------------------------------
    */

    document
        .querySelectorAll(
            '.view-transaction-button'
        )
        .forEach(function (button) {

            button.addEventListener(
                'click',
                function () {

                    const transactionId =
                        this.dataset.transactionId;

                    openModal(
                        transactionId
                    );

                }
            );

        });


    /*
    |--------------------------------------------------------------------------
    | CLOSE
    |--------------------------------------------------------------------------
    */

    document
        .querySelectorAll(
            '[data-close-transaction-modal]'
        )
        .forEach(function (element) {

            element.addEventListener(
                'click',
                closeModal
            );

        });


    /*
    |--------------------------------------------------------------------------
    | ESCAPE
    |--------------------------------------------------------------------------
    */

    document.addEventListener(
        'keydown',
        function (event) {

            if (
                event.key === 'Escape'
                &&
                modal
                &&
                modal.classList.contains(
                    'is-open'
                )
            ) {

                closeModal();

            }

        }
    );


    /*
    |--------------------------------------------------------------------------
    | PHOTO LIGHTBOX
    |--------------------------------------------------------------------------
    */

    const photoLightbox =
        document.getElementById(
            'transactionPhotoLightbox'
        );

    const photoLightboxImg =
        photoLightbox
            ? photoLightbox.querySelector(
                '.transaction-photo-lightbox-img'
            )
            : null;


    function openPhotoLightbox(src, alt) {

        if (!photoLightbox || !photoLightboxImg) {
            return;
        }

        photoLightboxImg.src = src;
        photoLightboxImg.alt = alt || '';

        photoLightbox.classList.add('is-open');
        photoLightbox.setAttribute('aria-hidden', 'false');

        document.body.classList.add(
            'transaction-photo-lightbox-open'
        );

    }


    function closePhotoLightbox() {

        if (!photoLightbox) {
            return;
        }

        photoLightbox.classList.remove('is-open');
        photoLightbox.setAttribute('aria-hidden', 'true');

        document.body.classList.remove(
            'transaction-photo-lightbox-open'
        );

        if (photoLightboxImg) {
            photoLightboxImg.src = '';
        }

    }


    document.addEventListener('click', function (event) {

        const photoLink =
            event.target.closest(
                '.transaction-review-photo-link'
            );

        if (photoLink) {

            event.preventDefault();

            const img =
                photoLink.querySelector('img');

            openPhotoLightbox(
                photoLink.getAttribute('href'),
                img ? img.alt : ''
            );

            return;
        }

        if (event.target.closest('[data-close-photo-lightbox]')) {
            closePhotoLightbox();
        }

    });


    document.addEventListener('keydown', function (event) {

        if (
            event.key === 'Escape'
            &&
            photoLightbox
            &&
            photoLightbox.classList.contains('is-open')
        ) {

            closePhotoLightbox();

        }

    });


})();

</script>