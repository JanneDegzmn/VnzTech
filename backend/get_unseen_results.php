<?php

require_once __DIR__ . '/database.php';
require_once __DIR__ . '/session.php';

header('Content-Type: application/json; charset=utf-8');


/*
|--------------------------------------------------------------------------
| SELLER SESSION
|--------------------------------------------------------------------------
|
| Seller-facing endpoint — same auth style as track_submission.php /
| respond_offer.php, no CSRF (read-only, session-gated, low risk).
|
*/

if (
    empty($_SESSION['user_logged_in']) ||
    empty($_SESSION['user_id'])
) {

    http_response_code(401);

    echo json_encode([
        'success' => false,
        'message' => 'Not logged in.'
    ]);

    exit;
}


$userId =
    (int) $_SESSION['user_id'];


if (
    $userId <= 0
) {

    http_response_code(401);

    echo json_encode([
        'success' => false,
        'message' => 'Invalid session.'
    ]);

    exit;
}


/*
|--------------------------------------------------------------------------
| UNSEEN ADMIN-DECIDED RESULTS
|--------------------------------------------------------------------------
|
| Only surfaces submissions where result_seen_by_seller = 0, which
| is ONLY ever set by backend/admin_respond_offer.php (Admin
| accepting/declining the seller's own counter-offer). Submissions
| accepted/declined through the seller's own action in
| backend/respond_offer.php never flip this flag, so they never
| appear here — the seller already knows, they just did it.
|
| offer_amount pulls the most recent offer row tied to the
| submission, which at the point result_seen_by_seller = 0 was set
| is always the seller's own counter-offer that Admin just
| responded to.
|
*/

try {

    $stmt =
        $pdo->prepare("
            SELECT
                s.id,
                s.reference_number,
                s.status,
                s.admin_feedback,
                (
                    SELECT o.offer_amount
                    FROM offers o
                    WHERE o.submission_id = s.id
                    ORDER BY o.id DESC
                    LIMIT 1
                ) AS offer_amount
            FROM submissions s
            WHERE s.user_id = ?
              AND s.status IN ('accepted', 'declined')
              AND s.result_seen_by_seller = 0
            ORDER BY s.updated_at ASC
        ");

    $stmt->execute([
        $userId
    ]);

    $results =
        $stmt->fetchAll();

    echo json_encode([
        'success' => true,
        'results' => $results
    ]);

    exit;

} catch (Throwable $e) {

    http_response_code(500);

    echo json_encode([
        'success' => false,
        'message' => 'Unable to retrieve results.'
    ]);

    exit;

}