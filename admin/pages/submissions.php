<?php

require_once __DIR__ . '/../../backend/database.php';
require_once __DIR__ . '/../../backend/session.php';
require_once __DIR__ . '/../../backend/auth.php';
require_once __DIR__ . '/../../backend/pagination.php';

requireAdminLogin();

/*
|--------------------------------------------------------------------------
| Permission Check
|--------------------------------------------------------------------------
*/

if (!hasPermission('view_submissions')) {
    http_response_code(403);
    ?>

    <div class="submissions-page">
        <div class="submission-access-denied">

            <div class="access-denied-icon">!</div>

            <div>
                <h2>Access Denied</h2>
                <p>
                    You do not have permission to view submissions.
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
$itemTypeFilter = trim($_GET['item_type'] ?? '');
$pagination = vnzPaginationState(12);


/*
|--------------------------------------------------------------------------
| Allowed Statuses
|--------------------------------------------------------------------------
*/

$allowedStatuses = [
    'new_submission',
    'under_review',
    'offer_pending',
    'offer_sent',
    'accepted',
    'declined',
    'cancelled',
    'completed'
];


/*
|--------------------------------------------------------------------------
| Allowed Item Types
|--------------------------------------------------------------------------
*/

$allowedItemTypes = [
    'Whole Phone',
    'Scrap Board'
];


/*
|--------------------------------------------------------------------------
| Build Query
|--------------------------------------------------------------------------
|
| One submission may contain multiple submission_items.
| We GROUP BY submission so each submission appears only once.
|
*/

$sql = "
    SELECT
        s.id,
        s.reference_number,
        s.seller_name,
        s.seller_mobile,
        s.seller_address,
        s.selling_method,
        s.status,
        s.assigned_admin_id,
        s.seller_notes,
        s.admin_feedback,
        s.created_at,
        s.updated_at,

        (
            SELECT COUNT(*)
            FROM offers pending_offer
            WHERE pending_offer.submission_id = s.id
              AND pending_offer.id = (
                  SELECT MAX(latest_offer.id)
                  FROM offers latest_offer
                  WHERE latest_offer.submission_id = s.id
              )
              AND pending_offer.sender_type = 'seller'
              AND LOWER(TRIM(pending_offer.status)) IN (
                  'sent',
                  'offer_sent',
                  'pending',
                  'active'
              )
        ) AS pending_counter_offer_count,

        COUNT(si.id) AS item_count,

        COALESCE(
            SUM(
                CASE
                    WHEN si.quantity IS NOT NULL
                    THEN si.quantity
                    ELSE 0
                END
            ),
            0
        ) AS total_quantity,

        GROUP_CONCAT(
            DISTINCT si.item_type
            ORDER BY si.item_type
            SEPARATOR ', '
        ) AS item_types,

        GROUP_CONCAT(
            DISTINCT
            CASE

                WHEN si.item_type = 'Whole Phone'
                THEN CONCAT(
                    COALESCE(NULLIF(si.brand, ''), 'Unknown Brand'),
                    ' ',
                    COALESCE(NULLIF(si.model, ''), 'Unknown Model')
                )

                WHEN si.item_type = 'Scrap Board'
                THEN CONCAT(
                    'Board ',
                    COALESCE(NULLIF(si.board_code, ''), 'Unknown Code')
                )

                ELSE
                    COALESCE(
                        NULLIF(si.item_type, ''),
                        'Unknown Item'
                    )

            END
            ORDER BY si.id
            SEPARATOR ' | '
        ) AS item_summary,

        COALESCE(
            SUM(
                CASE
                    WHEN si.seller_asking_price IS NOT NULL
                    THEN si.seller_asking_price
                    ELSE 0
                END
            ),
            0
        ) AS total_asking_price

    FROM submissions s

    LEFT JOIN submission_items si
        ON si.submission_id = s.id

    WHERE 1 = 1
";


$params = [];
$countSql = 'SELECT COUNT(*) FROM submissions s WHERE 1 = 1';
$countParams = [];


/*
|--------------------------------------------------------------------------
| Search
|--------------------------------------------------------------------------
*/

if ($search !== '') {

    $sql .= "
        AND (
            s.reference_number LIKE ?
            OR s.seller_name LIKE ?
            OR s.seller_mobile LIKE ?
        )
    ";

    $searchValue = '%' . $search . '%';

    $params[] = $searchValue;
    $params[] = $searchValue;
    $params[] = $searchValue;
    $countSql .= "
        AND (
            s.reference_number LIKE ?
            OR s.seller_name LIKE ?
            OR s.seller_mobile LIKE ?
        )
    ";
    array_push($countParams, $searchValue, $searchValue, $searchValue);
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
        AND s.status = ?
    ";

    $params[] = $statusFilter;
    $countSql .= ' AND s.status = ?';
    $countParams[] = $statusFilter;
}


/*
|--------------------------------------------------------------------------
| Item Type Filter
|--------------------------------------------------------------------------
*/

if (
    $itemTypeFilter !== ''
    && in_array($itemTypeFilter, $allowedItemTypes, true)
) {

    $sql .= "
        AND EXISTS (
            SELECT 1
            FROM submission_items filter_items
            WHERE filter_items.submission_id = s.id
            AND filter_items.item_type = ?
        )
    ";

    $params[] = $itemTypeFilter;
    $countSql .= "
        AND EXISTS (
            SELECT 1
            FROM submission_items filter_items
            WHERE filter_items.submission_id = s.id
            AND filter_items.item_type = ?
        )
    ";
    $countParams[] = $itemTypeFilter;
}

$countStmt = $pdo->prepare($countSql);
$countStmt->execute($countParams);
$pagination = vnzPaginationClamp($pagination, (int) $countStmt->fetchColumn());

/*
|--------------------------------------------------------------------------
| Group + Sort
|--------------------------------------------------------------------------
*/

$sql .= "
    GROUP BY
        s.id,
        s.reference_number,
        s.seller_name,
        s.seller_mobile,
        s.seller_address,
        s.selling_method,
        s.status,
        s.assigned_admin_id,
        s.seller_notes,
        s.admin_feedback,
        s.created_at,
        s.updated_at

    ORDER BY s.created_at DESC
";
$sql .= ' LIMIT ' . $pagination['per_page']
    . ' OFFSET ' . $pagination['offset'];


/*
|--------------------------------------------------------------------------
| Execute
|--------------------------------------------------------------------------
*/

$stmt = $pdo->prepare($sql);
$stmt->execute($params);

$submissions = $stmt->fetchAll();
$paginationHtml = vnzRenderPagination(
    $pagination,
    array_filter([
        'search' => $search,
        'status' => $statusFilter,
        'item_type' => $itemTypeFilter
    ], static fn ($value): bool => $value !== ''),
    'submissions',
    'admin',
    '/VNZ-TECH/admin/dashboard.php'
);


/*
|--------------------------------------------------------------------------
| Helper Functions
|--------------------------------------------------------------------------
*/

function submissionStatusLabel(string $status): string
{
    $labels = [

        'new_submission'
            => 'New Submission',

        'under_review'
            => 'Under Review',

        'offer_pending'
            => 'Offer Pending',

        'offer_sent'
            => 'Offer Sent',

        'accepted'
            => 'Accepted',

        'declined'
            => 'Declined',

        'cancelled'
            => 'Cancelled',

        'completed'
            => 'Completed'
    ];

    return $labels[$status]
        ?? ucwords(str_replace('_', ' ', $status));
}


function submissionStatusClass(string $status): string
{
    $classes = [

        'new_submission'
            => 'status-new',

        'under_review'
            => 'status-review',

        'offer_pending'
            => 'status-pending',

        'offer_sent'
            => 'status-offer',

        'accepted'
            => 'status-accepted',

        'declined'
            => 'status-declined',

        'cancelled'
            => 'status-cancelled',

        'completed'
            => 'status-completed'
    ];

    return $classes[$status]
        ?? 'status-default';
}


function sellingMethodLabel(string $method): string
{
    $labels = [

        'per_piece'
            => 'Per Piece',

        'wholesale'
            => 'Wholesale'
    ];

    return $labels[$method]
        ?? ucwords(str_replace('_', ' ', $method));
}


function pesoAmount($amount): string
{
    return '₱' . number_format(
        (float) $amount,
        2
    );
}


function submissionDate(string $date): string
{
    $timestamp = strtotime($date);

    if (!$timestamp) {
        return htmlspecialchars($date);
    }

    return
        date('M d, Y', $timestamp)
        .
        '<span class="submission-time">'
        .
        date('h:i A', $timestamp)
        .
        '</span>';
}

?>

<link
    rel="stylesheet"
    href="/VNZ-TECH/assets/css/admin_submissions.css"
>


<div class="submissions-page">


    <!-- =====================================================
         PAGE HEADER
    ====================================================== -->

    <div class="submissions-header">

        <div class="submissions-heading">

            <div class="submissions-kicker">
                VNZ TECH MANAGEMENT
            </div>

            <h1>
                Submissions
            </h1>

            <p>
                Review and manage seller submissions received by VNZ TECH.
            </p>

        </div>


        <div class="submissions-count-card">

            <span>
                TOTAL SUBMISSIONS
            </span>

            <strong>
                <?= number_format(count($submissions)) ?>
            </strong>

        </div>

    </div>



    <!-- =====================================================
         FILTER BAR
    ====================================================== -->

    <div class="submissions-filter-card">

        <div class="submission-search">

            <span class="search-symbol">
                ⌕
            </span>

            <input
                type="text"
                id="submissionSearch"
                placeholder="Search reference, seller, or mobile..."
                value="<?= htmlspecialchars($search) ?>"
                autocomplete="off"
            >

        </div>


        <select
            id="submissionStatusFilter"
            class="submission-filter-select"
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
                        submissionStatusLabel($status)
                    ) ?>
                </option>

            <?php endforeach; ?>

        </select>


        <select
            id="submissionItemTypeFilter"
            class="submission-filter-select"
        >

            <option value="">
                All Item Types
            </option>

            <?php foreach ($allowedItemTypes as $itemType): ?>

                <option
                    value="<?= htmlspecialchars($itemType) ?>"
                    <?= $itemTypeFilter === $itemType ? 'selected' : '' ?>
                >
                    <?= htmlspecialchars($itemType) ?>
                </option>

            <?php endforeach; ?>

        </select>


        <button
            type="button"
            id="clearSubmissionFilters"
            class="clear-submission-filters"
        >
            CLEAR
        </button>

    </div>



    <!-- =====================================================
         SUBMISSIONS TABLE
    ====================================================== -->

    <?php if ($pagination['last_page'] > 1): ?>
        <?= $paginationHtml ?>
    <?php endif; ?>


    <div class="submissions-card">


        <div class="submissions-card-header">

            <div>

                <h2>
                    Seller Submissions
                </h2>

                <span id="submissionVisibleCount">
                    <?= number_format($pagination['total']) ?>
                    submission<?= $pagination['total'] !== 1 ? 's' : '' ?> total
                </span>

            </div>

        </div>



        <?php if (empty($submissions)): ?>


            <!-- =================================================
                 EMPTY DATABASE
            ================================================== -->

            <div class="submissions-empty">

                <div class="empty-symbol">
                    +
                </div>

                <h3>
                    No submissions yet
                </h3>

                <p>
                    Seller submissions will appear here once someone
                    submits an item to VNZ TECH.
                </p>

            </div>


        <?php else: ?>


            <!-- =================================================
                 TABLE
            ================================================== -->

            <div class="submissions-table-wrapper">

                <table class="submissions-table">

                    <thead>

                        <tr>

                            <th>
                                REFERENCE
                            </th>

                            <th>
                                SELLER
                            </th>

                            <th>
                                ITEM
                            </th>

                            <th>
                                QTY
                            </th>

                            <th>
                                ASKING PRICE
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


                    <tbody id="submissionsTableBody">

                    <?php foreach ($submissions as $submission): ?>

                        <?php

                        $reference =
                            $submission['reference_number']
                            ?? '—';

                        $sellerName =
                            $submission['seller_name']
                            ?? '—';

                        $sellerMobile =
                            $submission['seller_mobile']
                            ?? '—';

                        $itemTypes =
                            $submission['item_types']
                            ?? '';

                        $itemSummary =
                            $submission['item_summary']
                            ?? '';

                        $status =
                            $submission['status']
                            ?? '';

                        $sellingMethod =
                            sellingMethodLabel(
                                $submission['selling_method']
                                ?? ''
                            );

                        $quantity =
                            (int) (
                                $submission['total_quantity']
                                ?? 0
                            );

                        $askingPrice =
                            (float) (
                                $submission['total_asking_price']
                                ?? 0
                            );

                        $searchData = strtolower(
                            $reference
                            . ' '
                            . $sellerName
                            . ' '
                            . $sellerMobile
                            . ' '
                            . $itemTypes
                            . ' '
                            . $itemSummary
                        );

                        ?>

                        <tr
                            class="submission-row"
                            data-search="<?= htmlspecialchars($searchData) ?>"
                            data-status="<?= htmlspecialchars($status) ?>"
                            data-item-type="<?= htmlspecialchars($itemTypes) ?>"
                        >


                            <!-- REFERENCE -->

                            <td>

                                <div class="reference-cell">

                                    <strong>
                                        <?= htmlspecialchars($reference) ?>
                                    </strong>

                                    <span>
                                        <?= htmlspecialchars($sellingMethod) ?>
                                    </span>

                                </div>

                            </td>


                            <!-- SELLER -->

                            <td>

                                <div class="seller-cell">

                                    <strong>
                                        <?= htmlspecialchars($sellerName) ?>
                                    </strong>

                                    <span>
                                        <?= htmlspecialchars($sellerMobile) ?>
                                    </span>

                                </div>

                            </td>


                            <!-- ITEM -->

                            <td>

                                <div class="item-cell">

                                    <strong>
                                        <?= htmlspecialchars(
                                            $itemTypes !== ''
                                                ? $itemTypes
                                                : 'No item data'
                                        ) ?>
                                    </strong>

                                    <?php if ($itemSummary !== ''): ?>

                                        <span>
                                            <?= htmlspecialchars(
                                                $itemSummary
                                            ) ?>
                                        </span>

                                    <?php endif; ?>

                                </div>

                            </td>


                            <!-- QUANTITY -->

                            <td>

                                <span class="quantity-cell">
                                    <?= number_format($quantity) ?>
                                </span>

                            </td>


                            <!-- ASKING PRICE -->

                            <td>

                                <span class="asking-price-cell">
                                    <?= pesoAmount($askingPrice) ?>
                                </span>

                            </td>


                            <!-- STATUS -->

                            <td>

                                <span
                                    class="
                                        submission-status
                                        <?= htmlspecialchars(
                                            submissionStatusClass($status)
                                        )
                                        ?>
                                    "
                                >
                                    <?= htmlspecialchars(
                                        submissionStatusLabel($status)
                                    ) ?>
                                </span>

                            </td>


                            <!-- DATE -->

                            <td>

                                <div class="submission-date">

                                    <?= submissionDate(
                                        $submission['created_at']
                                    ) ?>

                                </div>

                            </td>


                            <!-- ACTION -->

                            <td class="submission-action-cell">

                                <button
                                    type="button"
                                    class="view-submission-button"
                                    data-submission-id="<?= (int) $submission['id'] ?>"
                                >
                                    VIEW
                                </button>

                                <?php
                                $pendingCounterOfferCount = (int) (
                                    $submission['pending_counter_offer_count']
                                    ?? 0
                                );
                                ?>

                                <?php if ($pendingCounterOfferCount > 0): ?>

                                    <span
                                        class="submission-counter-offer-badge"
                                        title="<?= $pendingCounterOfferCount ?> seller counter-offer<?= $pendingCounterOfferCount === 1 ? '' : 's' ?> awaiting review"
                                        aria-label="<?= $pendingCounterOfferCount ?> seller counter-offer<?= $pendingCounterOfferCount === 1 ? '' : 's' ?> awaiting review"
                                    >
                                        <?= number_format($pendingCounterOfferCount) ?>
                                    </span>

                                <?php endif; ?>

                            </td>

                        </tr>

                    <?php endforeach; ?>

                    </tbody>

                </table>

            </div>

            <!-- =================================================
                 NO FILTER MATCH
            ================================================== -->

            <div
                id="filteredSubmissionEmpty"
                class="filtered-submissions-empty"
                style="display: none;"
            >

                <div>
                    ⌕
                </div>

                <h3>
                    No matching submissions
                </h3>

                <p>
                    Try changing your search or filters.
                </p>

            </div>


        <?php endif; ?>


    </div>


</div>



<script>

(function () {

    const searchInput =
        document.getElementById('submissionSearch');

    const statusFilter =
        document.getElementById('submissionStatusFilter');

    const itemTypeFilter =
        document.getElementById('submissionItemTypeFilter');

    const clearButton =
        document.getElementById('clearSubmissionFilters');

    let searchTimer = null;
    function applyFilters() {
        window.clearTimeout(searchTimer);
        searchTimer = window.setTimeout(() => {
            window.loadAdminPage?.('submissions', {
                search: searchInput?.value.trim() || '',
                status: statusFilter?.value || '',
                item_type: itemTypeFilter?.value || '',
                list_page: 1
            });
        }, 250);
    }


    /*
    |--------------------------------------------------------------------------
    | Search
    |--------------------------------------------------------------------------
    */

    if (searchInput) {

        searchInput.addEventListener(
            'input',
            applyFilters
        );

    }


    /*
    |--------------------------------------------------------------------------
    | Status Filter
    |--------------------------------------------------------------------------
    */

    if (statusFilter) {

        statusFilter.addEventListener(
            'change',
            applyFilters
        );

    }


    /*
    |--------------------------------------------------------------------------
    | Item Type Filter
    |--------------------------------------------------------------------------
    */

    if (itemTypeFilter) {

        itemTypeFilter.addEventListener(
            'change',
            applyFilters
        );

    }


    /*
    |--------------------------------------------------------------------------
    | Clear Filters
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

                if (itemTypeFilter) {
                    itemTypeFilter.value = '';
                }

                applyFilters();

            }
        );

    }


    /*
    |--------------------------------------------------------------------------
    | View Submission
    |--------------------------------------------------------------------------
    |
    | Step 2 will connect this button to the actual
    | submission review page.
    |
    */

    document
        .querySelectorAll('.view-submission-button')
        .forEach(function (button) {

            button.addEventListener(
                'click',
                function () {

                    const submissionId =
                        this.dataset.submissionId;

                    if (!submissionId) {
                        return;
                    }


                    if (typeof window.openAdminSubmissionModal === 'function') {
                        window.openAdminSubmissionModal(submissionId, 'submissions');
                    }

                }
            );

        });


})();

</script>