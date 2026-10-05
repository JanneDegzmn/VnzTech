<?php

require_once __DIR__ . '/../../backend/database.php';
require_once __DIR__ . '/../../backend/session.php';
require_once __DIR__ . '/../../backend/auth.php';

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
                    You do not have permission to view offers.
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
$negotiationFilter = trim($_GET['negotiation'] ?? '');


/*
|--------------------------------------------------------------------------
| Allowed Negotiation States
|--------------------------------------------------------------------------
|
| These are derived states, not raw submissions.status values -
| computed below from submissions.status + the latest offer's
| sender_type (see submission_review.php's $latestIsSeller /
| $latestIsAdmin / $submissionInNegotiation pattern).
|
*/

$allowedNegotiationStates = [
    'needs_response',
    'waiting_seller',
    'accepted',
    'declined'
];


/*
|--------------------------------------------------------------------------
| Build Query
|--------------------------------------------------------------------------
|
| Only submissions that have at least one row in `offers` are
| included (EXISTS guard). The latest offer per submission is
| pulled via correlated subqueries ordered by o.id DESC, mirroring
| the exact "latest offer" pattern already used in
| submission_review.php ($latestOffer = $offers[0] ?? null, where
| $offers is ORDER BY o.id DESC).
|
*/

$sql = "
    SELECT
        s.id,
        s.reference_number,
        s.seller_name,
        s.seller_mobile,
        s.selling_method,
        s.status AS submission_status,
        s.created_at,
        s.updated_at,

        (
            SELECT o.id
            FROM offers o
            WHERE o.submission_id = s.id
            ORDER BY o.id DESC
            LIMIT 1
        ) AS latest_offer_id,

        (
            SELECT o.sender_type
            FROM offers o
            WHERE o.submission_id = s.id
            ORDER BY o.id DESC
            LIMIT 1
        ) AS latest_offer_sender_type,

        (
            SELECT o.offer_amount
            FROM offers o
            WHERE o.submission_id = s.id
            ORDER BY o.id DESC
            LIMIT 1
        ) AS latest_offer_amount,

        (
            SELECT o.status
            FROM offers o
            WHERE o.submission_id = s.id
            ORDER BY o.id DESC
            LIMIT 1
        ) AS latest_offer_status,

        (
            SELECT o.feedback
            FROM offers o
            WHERE o.submission_id = s.id
            ORDER BY o.id DESC
            LIMIT 1
        ) AS latest_offer_feedback,

        (
            SELECT o.created_at
            FROM offers o
            WHERE o.submission_id = s.id
            ORDER BY o.id DESC
            LIMIT 1
        ) AS latest_offer_created_at,

        (
            SELECT COUNT(*)
            FROM offers o
            WHERE o.submission_id = s.id
        ) AS offer_count

    FROM submissions s

    WHERE EXISTS (
        SELECT 1
        FROM offers o
        WHERE o.submission_id = s.id
    )
";


$params = [];


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
}


/*
|--------------------------------------------------------------------------
| Sort
|--------------------------------------------------------------------------
|
| Most recently updated negotiations first, so anything the admin
| just acted on (or that the seller just responded to) surfaces
| at the top.
|
*/

$sql .= "
    ORDER BY s.updated_at DESC
";


/*
|--------------------------------------------------------------------------
| Execute
|--------------------------------------------------------------------------
*/

$stmt = $pdo->prepare($sql);
$stmt->execute($params);

$offerSubmissions = $stmt->fetchAll();


/*
|--------------------------------------------------------------------------
| Compute Negotiation State Per Row
|--------------------------------------------------------------------------
|
| Mirrors submission_review.php:
|   $latestIsSeller = $latestSenderType === 'seller';
|   $latestIsAdmin  = $latestSenderType === 'admin';
|   $submissionInNegotiation = $submission['status'] === 'offer_sent';
|
*/

foreach ($offerSubmissions as &$row) {

    $status = $row['submission_status'];
    $senderType = $row['latest_offer_sender_type'];

    $inNegotiation = $status === 'offer_sent';
    $latestIsSeller = $senderType === 'seller';
    $latestIsAdmin = $senderType === 'admin';

    if ($inNegotiation && $latestIsSeller) {
        $row['negotiation_state'] = 'needs_response';
    } elseif ($inNegotiation && $latestIsAdmin) {
        $row['negotiation_state'] = 'waiting_seller';
    } elseif ($status === 'accepted') {
        $row['negotiation_state'] = 'accepted';
    } elseif ($status === 'declined') {
        $row['negotiation_state'] = 'declined';
    } else {
        $row['negotiation_state'] = 'other';
    }

}
unset($row);


/*
|--------------------------------------------------------------------------
| Apply Negotiation Filter (server-side, so a bookmarked/shared
| URL with ?negotiation= works even before JS runs)
|--------------------------------------------------------------------------
*/

if (
    $negotiationFilter !== ''
    && in_array($negotiationFilter, $allowedNegotiationStates, true)
) {

    $offerSubmissions = array_values(
        array_filter(
            $offerSubmissions,
            static function ($row) use ($negotiationFilter) {
                return $row['negotiation_state'] === $negotiationFilter;
            }
        )
    );

}


/*
|--------------------------------------------------------------------------
| Counts (for the summary card + filter option counts)
|--------------------------------------------------------------------------
*/

$needsResponseCount = 0;

foreach ($offerSubmissions as $row) {
    if ($row['negotiation_state'] === 'needs_response') {
        $needsResponseCount++;
    }
}


/*
|--------------------------------------------------------------------------
| Helper Functions
|--------------------------------------------------------------------------
*/

function offerSubmissionStatusLabel(string $status): string
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


function offerSubmissionStatusClass(string $status): string
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


function offerNegotiationLabel(string $state): string
{
    $labels = [

        'needs_response'
            => 'Needs Your Response',

        'waiting_seller'
            => 'Waiting for Seller',

        'accepted'
            => 'Accepted',

        'declined'
            => 'Declined',

        'other'
            => 'Other'
    ];

    return $labels[$state] ?? 'Other';
}


function offerSenderLabel($senderType): string
{
    return $senderType === 'seller'
        ? 'SELLER'
        : 'VNZ TECH';
}


function offerSenderClass($senderType): string
{
    return $senderType === 'seller'
        ? 'offer-sender-seller'
        : 'offer-sender-admin';
}


function offerPesoAmount($amount): string
{
    return '₱' . number_format(
        (float) $amount,
        2
    );
}


function offerSubmissionDate($date): string
{
    if (!$date) {
        return '—';
    }

    $timestamp = strtotime($date);

    if (!$timestamp) {
        return htmlspecialchars((string) $date);
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
    href="/VNZ-TECH/assets/css/admin_submissions.css?v=<?= filemtime(__DIR__ . '/../../assets/css/admin_submissions.css') ?>"
>

<link
    rel="stylesheet"
    href="/VNZ-TECH/assets/css/admin_submission_review.css"
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
                Offers
            </h1>

            <p>
                Track active negotiations and offer history across all seller submissions.
            </p>

        </div>


        <div class="submissions-count-card">

            <span>
                NEEDS YOUR RESPONSE
            </span>

            <strong>
                <?= number_format($needsResponseCount) ?>
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
                id="offerSearch"
                placeholder="Search reference, seller, or mobile..."
                value="<?= htmlspecialchars($search) ?>"
                autocomplete="off"
            >

        </div>


        <select
            id="offerNegotiationFilter"
            class="submission-filter-select"
        >

            <option value="">
                All Offers
            </option>

            <option
                value="needs_response"
                <?= $negotiationFilter === 'needs_response' ? 'selected' : '' ?>
            >
                Needs Your Response
            </option>

            <option
                value="waiting_seller"
                <?= $negotiationFilter === 'waiting_seller' ? 'selected' : '' ?>
            >
                Waiting for Seller
            </option>

            <option
                value="accepted"
                <?= $negotiationFilter === 'accepted' ? 'selected' : '' ?>
            >
                Accepted
            </option>

            <option
                value="declined"
                <?= $negotiationFilter === 'declined' ? 'selected' : '' ?>
            >
                Declined
            </option>

        </select>


        <button
            type="button"
            id="clearOfferFilters"
            class="clear-submission-filters"
        >
            CLEAR
        </button>

    </div>



    <!-- =====================================================
         OFFERS TABLE
    ====================================================== -->

    <div class="submissions-card">


        <div class="submissions-card-header">

            <div>

                <h2>
                    Submissions With Offers
                </h2>

                <span id="offerVisibleCount">
                    Showing
                    <?= number_format(count($offerSubmissions)) ?>
                    offer<?= count($offerSubmissions) !== 1 ? 's' : '' ?>
                </span>

            </div>

        </div>



        <?php if (empty($offerSubmissions)): ?>


            <!-- =================================================
                 EMPTY
            ================================================== -->

            <div class="submissions-empty">

                <div class="empty-symbol">
                    +
                </div>

                <h3>
                    No offers yet
                </h3>

                <p>
                    Submissions with a VNZ TECH offer or seller
                    counter-offer will appear here.
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
                                LATEST OFFER
                            </th>

                            <th>
                                SUBMISSION STATUS
                            </th>

                            <th>
                                NEGOTIATION
                            </th>

                            <th>
                                DATE
                            </th>

                            <th>
                                ACTION
                            </th>

                        </tr>

                    </thead>


                    <tbody id="offersTableBody">

                    <?php foreach ($offerSubmissions as $row): ?>

                        <?php

                        $reference =
                            $row['reference_number']
                            ?? '—';

                        $sellerName =
                            $row['seller_name']
                            ?? '—';

                        $sellerMobile =
                            $row['seller_mobile']
                            ?? '—';

                        $submissionStatus =
                            $row['submission_status']
                            ?? '';

                        $negotiationState =
                            $row['negotiation_state']
                            ?? 'other';

                        $needsResponse =
                            $negotiationState === 'needs_response';

                        $searchData = strtolower(
                            $reference
                            . ' '
                            . $sellerName
                            . ' '
                            . $sellerMobile
                        );

                        ?>

                        <tr
                            class="submission-row"
                            data-search="<?= htmlspecialchars($searchData) ?>"
                            data-negotiation="<?= htmlspecialchars($negotiationState) ?>"
                        >


                            <!-- REFERENCE -->

                            <td>

                                <div class="reference-cell">

                                    <strong>
                                        <?= htmlspecialchars($reference) ?>
                                    </strong>

                                    <?php if ($needsResponse): ?>

                                        <span class="offer-state needs-response-state">
                                            ACTION NEEDED
                                        </span>

                                    <?php endif; ?>

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


                            <!-- LATEST OFFER -->

                            <td>

                                <div class="item-cell">

                                    <strong>
                                        <?= offerPesoAmount(
                                            $row['latest_offer_amount'] ?? 0
                                        ) ?>
                                    </strong>

                                    <span
                                        class="offer-sender-tag <?= htmlspecialchars(
                                            offerSenderClass(
                                                $row['latest_offer_sender_type'] ?? ''
                                            )
                                        ) ?>"
                                    >
                                        <?= htmlspecialchars(
                                            offerSenderLabel(
                                                $row['latest_offer_sender_type'] ?? ''
                                            )
                                        ) ?>
                                    </span>

                                </div>

                            </td>


                            <!-- SUBMISSION STATUS -->

                            <td>

                                <span
                                    class="
                                        submission-status
                                        <?= htmlspecialchars(
                                            offerSubmissionStatusClass($submissionStatus)
                                        )
                                        ?>
                                    "
                                >
                                    <?= htmlspecialchars(
                                        offerSubmissionStatusLabel($submissionStatus)
                                    ) ?>
                                </span>

                            </td>


                            <!-- NEGOTIATION -->

                            <td>

                                <span
                                    class="offer-state <?= htmlspecialchars(
                                        str_replace('_', '-', $negotiationState)
                                    ) ?>-state"
                                >
                                    <?= htmlspecialchars(
                                        offerNegotiationLabel($negotiationState)
                                    ) ?>
                                </span>

                            </td>


                            <!-- DATE -->

                            <td>

                                <div class="submission-date">

                                    <?= offerSubmissionDate(
                                        $row['latest_offer_created_at']
                                        ?? $row['updated_at']
                                    ) ?>

                                </div>

                            </td>


                            <!-- ACTION -->

                            <td>

                                <button
                                    type="button"
                                    class="view-submission-button"
                                    data-submission-id="<?= (int) $row['id'] ?>"
                                >
                                    <?= $needsResponse
                                        ? 'RESPOND'
                                        : 'VIEW'
                                    ?>
                                </button>

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
                id="filteredOfferEmpty"
                class="filtered-submissions-empty"
                style="display: none;"
            >

                <div>
                    ⌕
                </div>

                <h3>
                    No matching offers
                </h3>

                <p>
                    Try changing your search or filter.
                </p>

            </div>


        <?php endif; ?>


    </div>


</div>



<script>

(function () {

    const searchInput =
        document.getElementById('offerSearch');

    const negotiationFilter =
        document.getElementById('offerNegotiationFilter');

    const clearButton =
        document.getElementById('clearOfferFilters');

    const visibleCount =
        document.getElementById('offerVisibleCount');

    const filteredEmpty =
        document.getElementById('filteredOfferEmpty');

    const rows =
        Array.from(
            document.querySelectorAll('.submission-row')
        );


    /*
    |--------------------------------------------------------------------------
    | Apply Filters
    |--------------------------------------------------------------------------
    */

    function applyFilters() {

        const search =
            searchInput
                ? searchInput.value
                    .trim()
                    .toLowerCase()
                : '';

        const selectedNegotiation =
            negotiationFilter
                ? negotiationFilter.value
                : '';

        let visibleRows = 0;


        rows.forEach(function (row) {

            const rowSearch =
                row.dataset.search || '';

            const rowNegotiation =
                row.dataset.negotiation || '';


            const matchesSearch =
                search === ''
                || rowSearch.includes(search);


            const matchesNegotiation =
                selectedNegotiation === ''
                || rowNegotiation === selectedNegotiation;


            const show =
                matchesSearch
                && matchesNegotiation;


            row.style.display =
                show ? '' : 'none';


            if (show) {
                visibleRows++;
            }

        });


        if (visibleCount) {

            visibleCount.textContent =
                'Showing '
                + visibleRows.toLocaleString()
                + ' offer'
                + (visibleRows !== 1 ? 's' : '');

        }


        if (filteredEmpty) {

            filteredEmpty.style.display =
                rows.length > 0 && visibleRows === 0
                    ? 'block'
                    : 'none';

        }

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
    | Negotiation Filter
    |--------------------------------------------------------------------------
    */

    if (negotiationFilter) {

        negotiationFilter.addEventListener(
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

                if (negotiationFilter) {
                    negotiationFilter.value = '';
                }

                applyFilters();

            }
        );

    }


    /*
    |--------------------------------------------------------------------------
    | View / Respond
    |--------------------------------------------------------------------------
    |
    | Same router mechanism as admin/pages/submissions.php - no
    | new JS, no new routing logic.
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
                        window.openAdminSubmissionModal(submissionId, 'offers');
                    }

                }
            );

        });


    /*
    |--------------------------------------------------------------------------
    | Initial Filter Pass
    |--------------------------------------------------------------------------
    |
    | Runs once on load so the visible count matches the
    | server-rendered rows before any user interaction.
    |
    */

    applyFilters();

})();

</script>