<?php

header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Pragma: no-cache');
header('Expires: 0');

require_once __DIR__ . '/../../backend/database.php';
require_once __DIR__ . '/../../backend/session.php';
require_once __DIR__ . '/../../backend/pagination.php';

if (empty($_SESSION['user_logged_in']) || empty($_SESSION['user_id'])) {
    http_response_code(401);

    echo '
        <div class="portal-error">
            <h2>Session expired</h2>
            <p>Please log in again.</p>
        </div>
    ';

    exit;
}

$userId = (int) $_SESSION['user_id'];
$offerStates = ['all', 'needs_response', 'waiting_vnz', 'closed', 'other'];
$offerStateFilter = trim((string) ($_GET['offer_state'] ?? 'all'));
if (!in_array($offerStateFilter, $offerStates, true)) {
    $offerStateFilter = 'all';
}
$offerSearch = trim((string) ($_GET['offer_search'] ?? ''));
$pagination = vnzPaginationState(5);

if ($userId <= 0) {
    http_response_code(401);

    echo '
        <div class="portal-error">
            <h2>Invalid session</h2>
            <p>Please log in again.</p>
        </div>
    ';

    exit;
}

function sellerOfferEscape($value): string
{
    return htmlspecialchars(
        (string) ($value ?? ''),
        ENT_QUOTES,
        'UTF-8'
    );
}

function sellerOfferMoney($amount): string
{
    return '₱' . number_format((float) $amount, 2);
}

function sellerOfferDate($date): string
{
    $timestamp = $date ? strtotime((string) $date) : false;

    return $timestamp
        ? date('M d, Y · h:i A', $timestamp)
        : 'Date unavailable';
}

try {
    $sellerStmt = $pdo->prepare("
        SELECT id, is_active
        FROM users
        WHERE id = ?
        LIMIT 1
    ");
    $sellerStmt->execute([$userId]);
    $seller = $sellerStmt->fetch();

    if (!$seller || (int) $seller['is_active'] !== 1) {
        http_response_code(401);

        echo '
            <div class="portal-error">
                <h2>Account unavailable</h2>
                <p>Please log in again or contact VNZ TECH.</p>
            </div>
        ';

        exit;
    }

    $whereSql = "
        WHERE s.user_id = ?
          AND EXISTS (SELECT 1 FROM offers offer_exists WHERE offer_exists.submission_id = s.id)
    ";
    $queryParams = [$userId];
    if ($offerSearch !== '') {
        $whereSql .= "
            AND (
                s.reference_number LIKE ?
                OR EXISTS (
                    SELECT 1
                    FROM submission_items search_item
                    WHERE search_item.submission_id = s.id
                      AND CONCAT_WS(' ', search_item.item_type, search_item.brand, search_item.model) LIKE ?
                )
            )
        ";
        $searchValue = '%' . $offerSearch . '%';
        array_push($queryParams, $searchValue, $searchValue);
    }
    $stateSql = match ($offerStateFilter) {
        'needs_response' => " AND s.status = 'offer_sent' AND (SELECT sender_type FROM offers WHERE submission_id = s.id ORDER BY id DESC LIMIT 1) = 'admin'",
        'waiting_vnz' => " AND s.status = 'offer_sent' AND (SELECT sender_type FROM offers WHERE submission_id = s.id ORDER BY id DESC LIMIT 1) = 'seller'",
        'closed' => " AND s.status IN ('accepted', 'declined', 'cancelled', 'completed')",
        'other' => " AND s.status NOT IN ('offer_sent', 'accepted', 'declined', 'cancelled', 'completed')",
        default => ''
    };
    $whereSql .= $stateSql;

    $countStmt = $pdo->prepare('SELECT COUNT(*) FROM submissions s ' . $whereSql);
    $countStmt->execute($queryParams);
    $pagination = vnzPaginationClamp($pagination, (int) $countStmt->fetchColumn());

    $offersStmt = $pdo->prepare("
        SELECT
            s.id AS submission_id,
            s.reference_number,
            s.status AS submission_status,
            s.updated_at AS submission_updated_at,
            o.id AS offer_id,
            o.offer_amount,
            o.status AS offer_status,
            o.sender_type,
            o.feedback,
            COALESCE(o.sent_at, o.created_at) AS offer_date,
            (
                SELECT COUNT(*)
                FROM offers offer_count
                WHERE offer_count.submission_id = s.id
            ) AS offer_count,
            (
                SELECT GROUP_CONCAT(
                    DISTINCT CONCAT(
                        si.item_type,
                        CASE
                            WHEN si.brand IS NOT NULL AND si.brand <> ''
                            THEN CONCAT(' · ', si.brand)
                            ELSE ''
                        END,
                        CASE
                            WHEN si.model IS NOT NULL AND si.model <> ''
                            THEN CONCAT(' ', si.model)
                            ELSE ''
                        END
                    )
                    ORDER BY si.id ASC
                    SEPARATOR ', '
                )
                FROM submission_items si
                WHERE si.submission_id = s.id
            ) AS item_summary
        FROM submissions s
        INNER JOIN offers o
            ON o.id = (
                SELECT latest_offer.id
                FROM offers latest_offer
                WHERE latest_offer.submission_id = s.id
                ORDER BY latest_offer.id DESC
                LIMIT 1
            )
        {$whereSql}
        ORDER BY o.id DESC
        LIMIT {$pagination['per_page']} OFFSET {$pagination['offset']}
    ");
    $offersStmt->execute($queryParams);
    $offers = $offersStmt->fetchAll();

    $stateSummaryStmt = $pdo->prepare("
        SELECT
            SUM(CASE WHEN submission_status = 'offer_sent' AND latest_sender = 'admin' THEN 1 ELSE 0 END) AS needs_response,
            SUM(CASE WHEN submission_status = 'offer_sent' AND latest_sender = 'seller' THEN 1 ELSE 0 END) AS waiting_vnz,
            SUM(CASE WHEN submission_status IN ('accepted', 'declined', 'cancelled', 'completed') THEN 1 ELSE 0 END) AS closed,
            SUM(CASE WHEN submission_status NOT IN ('offer_sent', 'accepted', 'declined', 'cancelled', 'completed') THEN 1 ELSE 0 END) AS other
        FROM (
            SELECT
                s.status AS submission_status,
                (
                    SELECT latest_offer.sender_type
                    FROM offers latest_offer
                    WHERE latest_offer.submission_id = s.id
                    ORDER BY latest_offer.id DESC
                    LIMIT 1
                ) AS latest_sender
            FROM submissions s
            WHERE s.user_id = ?
              AND EXISTS (
                  SELECT 1 FROM offers offer_exists
                  WHERE offer_exists.submission_id = s.id
              )
        ) offer_states
    ");
    $stateSummaryStmt->execute([$userId]);
    $counts = array_map(
        'intval',
        $stateSummaryStmt->fetch(PDO::FETCH_ASSOC) ?: []
    );
    $counts += [
        'needs_response' => 0,
        'waiting_vnz' => 0,
        'closed' => 0,
        'other' => 0
    ];
    $totalOffers = array_sum($counts);
    $paginationHtml = vnzRenderPagination(
        $pagination,
        array_filter([
            'offer_state' => $offerStateFilter,
            'offer_search' => $offerSearch
        ], static fn ($value): bool => $value !== '' && $value !== 'all'),
        'offers',
        'seller',
        '/VNZ-TECH/account/dashboard.php?page=offers'
    );
} catch (Throwable $e) {
    error_log('Seller offers page error: ' . $e->getMessage());
    http_response_code(500);

    echo '
        <div class="portal-error">
            <h2>Unable to load offers</h2>
            <p>Something went wrong while loading your offers. Please try again later.</p>
        </div>
    ';

    exit;
}

foreach ($offers as &$offer) {
    $submissionStatus = strtolower((string) $offer['submission_status']);
    $senderType = strtolower((string) $offer['sender_type']);

    if ($submissionStatus === 'offer_sent' && $senderType === 'admin') {
        $offer['negotiation_state'] = 'needs_response';
    } elseif ($submissionStatus === 'offer_sent' && $senderType === 'seller') {
        $offer['negotiation_state'] = 'waiting_vnz';
    } elseif (in_array($submissionStatus, ['accepted', 'declined', 'cancelled', 'completed'], true)) {
        $offer['negotiation_state'] = 'closed';
    } else {
        $offer['negotiation_state'] = 'other';
    }

}
unset($offer);

$stateLabels = [
    'needs_response' => 'Your response needed',
    'waiting_vnz' => 'Waiting for VNZ TECH',
    'closed' => 'Negotiation closed',
    'other' => 'Under review'
];

$stateDescriptions = [
    'needs_response' => 'VNZ TECH sent an offer. Review it to accept, decline, or make a counter-offer.',
    'waiting_vnz' => 'Your counter-offer was sent. VNZ TECH will review it and update this page.',
    'closed' => 'This negotiation has ended. Open the submission to review the final result.',
    'other' => 'An offer exists for this submission. Open it to see the latest details.'
];

?>

<section class="portal-offers-page">
    <header class="offers-header">
        <div>
            <span class="eyebrow">SELLER PORTAL</span>
            <h1>Offers.</h1>
            <p>Review VNZ TECH offers and follow every counter-offer in one place.</p>
        </div>
        <a
            href="/VNZ-TECH/account/dashboard.php?page=submissions"
            class="offers-submissions-link"
            data-seller-page="submissions"
        >
            VIEW MY SUBMISSIONS <span aria-hidden="true">→</span>
        </a>
    </header>

    <div class="offers-summary" aria-label="Offer summary">
        <article class="offers-summary-card">
            <span>NEEDS YOUR RESPONSE</span>
            <strong><?= number_format($counts['needs_response']) ?></strong>
            <small>Offers from VNZ TECH</small>
        </article>
        <article class="offers-summary-card">
            <span>WAITING FOR VNZ TECH</span>
            <strong><?= number_format($counts['waiting_vnz']) ?></strong>
            <small>Your counter-offers</small>
        </article>
        <article class="offers-summary-card">
            <span>CLOSED</span>
            <strong><?= number_format($counts['closed']) ?></strong>
            <small>Accepted, declined, or completed</small>
        </article>
        <article class="offers-summary-card">
            <span>TOTAL OFFERS</span>
            <strong><?= number_format($totalOffers) ?></strong>
            <small>Submissions with an offer</small>
        </article>
    </div>

    <div class="offers-toolbar">
        <div>
            <h2>Your negotiations</h2>
            <p>Each card shows the latest offer. Open a submission to see its full history and available actions.</p>
        </div>
        <form class="offers-search" data-offers-search-form>
            <input id="offers-search-field" type="search" name="offer_search" data-offers-search aria-label="Search offers" placeholder="Search by reference or item" value="<?= sellerOfferEscape($offerSearch) ?>">
            <input type="hidden" name="offer_state" value="<?= sellerOfferEscape($offerStateFilter) ?>">
            <button type="submit">Search</button>
        </form>
    </div>

    <div class="offers-filters" role="group" aria-label="Filter offers">
        <button type="button" class="<?= $offerStateFilter === 'all' ? 'is-selected' : '' ?>" data-offer-filter="all" aria-pressed="<?= $offerStateFilter === 'all' ? 'true' : 'false' ?>">All (<?= number_format($totalOffers) ?>)</button>
        <button type="button" class="<?= $offerStateFilter === 'needs_response' ? 'is-selected' : '' ?>" data-offer-filter="needs_response" aria-pressed="<?= $offerStateFilter === 'needs_response' ? 'true' : 'false' ?>">Needs your response (<?= number_format($counts['needs_response']) ?>)</button>
        <button type="button" class="<?= $offerStateFilter === 'waiting_vnz' ? 'is-selected' : '' ?>" data-offer-filter="waiting_vnz" aria-pressed="<?= $offerStateFilter === 'waiting_vnz' ? 'true' : 'false' ?>">Waiting for VNZ TECH (<?= number_format($counts['waiting_vnz']) ?>)</button>
        <button type="button" class="<?= $offerStateFilter === 'closed' ? 'is-selected' : '' ?>" data-offer-filter="closed" aria-pressed="<?= $offerStateFilter === 'closed' ? 'true' : 'false' ?>">Closed (<?= number_format($counts['closed']) ?>)</button>
        <button type="button" class="<?= $offerStateFilter === 'other' ? 'is-selected' : '' ?>" data-offer-filter="other" aria-pressed="<?= $offerStateFilter === 'other' ? 'true' : 'false' ?>">Under review (<?= number_format($counts['other']) ?>)</button>
    </div>

    <?php if (empty($offers)): ?>
        <div class="offers-empty">
            <span class="offers-empty-icon" aria-hidden="true">₱</span>
            <h2>No offers yet</h2>
            <p>When VNZ TECH reviews one of your submissions and sends an offer, it will appear here.</p>
            <a
                href="/VNZ-TECH/account/dashboard.php?page=submissions"
                data-seller-page="submissions"
            >
                Go to my submissions <span aria-hidden="true">→</span>
            </a>
        </div>
    <?php else: ?>
        <div class="offers-list" data-offers-list>
            <?php foreach ($offers as $offer): ?>
                <?php
                $state = $offer['negotiation_state'];
                $isVnzOffer = strtolower((string) $offer['sender_type']) === 'admin';
                $itemSummary = trim((string) ($offer['item_summary'] ?? ''));
                $searchText = strtolower(
                    (string) $offer['reference_number'] . ' ' . $itemSummary
                );
                $senderLabel = $isVnzOffer ? 'OFFER FROM VNZ TECH' : 'YOUR COUNTER-OFFER';
                $offerDate = $offer['offer_date'] ?: $offer['submission_updated_at'];
                ?>
                <article
                    class="seller-offer-card"
                    data-offer-card
                    data-state="<?= sellerOfferEscape($state) ?>"
                    data-search="<?= sellerOfferEscape($searchText) ?>"
                >
                    <div class="seller-offer-card-main">
                        <div class="seller-offer-card-top">
                            <a
                                class="seller-offer-reference"
                                href="/VNZ-TECH/account/dashboard.php?page=submission&amp;ref=<?= rawurlencode((string) $offer['reference_number']) ?>"
                                data-seller-submission-modal
                            ><?= sellerOfferEscape($offer['reference_number']) ?></a>
                            <span class="seller-offer-state seller-offer-state-<?= sellerOfferEscape($state) ?>">
                                <?= sellerOfferEscape($stateLabels[$state]) ?>
                            </span>
                        </div>

                        <h3><?= sellerOfferEscape($itemSummary !== '' ? $itemSummary : 'Submitted items') ?></h3>
                        <p class="seller-offer-explainer"><?= sellerOfferEscape($stateDescriptions[$state]) ?></p>

                        <?php if (!empty($offer['feedback'])): ?>
                            <div class="seller-offer-message">
                                <span><?= sellerOfferEscape($senderLabel === 'OFFER FROM VNZ TECH' ? 'MESSAGE FROM VNZ TECH' : 'YOUR MESSAGE') ?></span>
                                <p><?= nl2br(sellerOfferEscape($offer['feedback'])) ?></p>
                            </div>
                        <?php endif; ?>
                    </div>

                    <aside class="seller-offer-card-side">
                        <span class="seller-offer-sender"><?= sellerOfferEscape($senderLabel) ?></span>
                        <strong><?= sellerOfferMoney($offer['offer_amount']) ?></strong>
                        <span class="seller-offer-date"><?= sellerOfferEscape(sellerOfferDate($offerDate)) ?></span>
                        <span class="seller-offer-count"><?= number_format((int) $offer['offer_count']) ?> offer<?= (int) $offer['offer_count'] === 1 ? '' : 's' ?> in this negotiation</span>
                        <a
                            class="seller-offer-action <?= $state === 'needs_response' ? 'seller-offer-action-primary' : '' ?>"
                            href="/VNZ-TECH/account/dashboard.php?page=submission&amp;ref=<?= rawurlencode((string) $offer['reference_number']) ?>"
                            data-seller-submission-modal
                        >
                            <?= $state === 'needs_response' ? 'REVIEW & RESPOND' : 'VIEW NEGOTIATION' ?>
                            <span aria-hidden="true">→</span>
                        </a>
                    </aside>
                </article>
            <?php endforeach; ?>
            <div class="offers-no-results" data-offers-empty hidden>
                <strong>No matching offers</strong>
                <span>Try a different reference or item name.</span>
            </div>
        </div>
        <?= $paginationHtml ?>
    <?php endif; ?>
</section>

<script>
(function () {
    const page = document.querySelector('.portal-offers-page');

    if (!page) {
        return;
    }

    const searchInput = page.querySelector('[data-offers-search]');
    const searchForm = page.querySelector('[data-offers-search-form]');
    const filters = Array.from(page.querySelectorAll('[data-offer-filter]'));

    filters.forEach((filter) => {
        filter.addEventListener('click', () => {
            window.loadSellerPage('offers', true, {
                offer_state: filter.dataset.offerFilter || 'all',
                offer_search: searchInput ? searchInput.value.trim() : ''
            });
        });
    });

    if (searchForm) {
        searchForm.addEventListener('submit', (event) => {
            event.preventDefault();
            const formData = new FormData(searchForm);
            window.loadSellerPage('offers', true, {
                offer_state: formData.get('offer_state') || 'all',
                offer_search: formData.get('offer_search') || ''
            });
        });
    }
})();
</script>
