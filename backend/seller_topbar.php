<?php
/** Read-only seller header data. No acknowledgement or notification writes. */
function vnzSellerTopbarProfile(array $user): array
{
    $name = trim((string) ($user['full_name'] ?? '')) ?: 'Seller';
    $picture = str_replace('\\', '/', trim((string) ($user['profile_picture'] ?? '')));
    if (preg_match('~^https?://~i', $picture) && filter_var($picture, FILTER_VALIDATE_URL)) {
        $avatar = $picture;
    } elseif (preg_match('~^(?:/VNZ-TECH/|/)?uploads/[a-zA-Z0-9_./-]+$~D', $picture) && !str_contains($picture, '..')) {
        $avatar = '/VNZ-TECH/' . preg_replace('~^(?:/VNZ-TECH/|/)~', '', $picture);
    } else {
        $avatar = '';
    }
    return ['full_name' => $name, 'first_name' => explode(' ', $name)[0], 'avatar_url' => $avatar];
}

function vnzSellerTopbarUpdates(PDO $pdo, int $userId): array
{
    $where = "s.user_id = ? AND (
        (s.status = 'offer_sent' AND
            (SELECT o.sender_type FROM offers o WHERE o.submission_id = s.id ORDER BY o.id DESC LIMIT 1) = 'admin')
        OR (s.status IN ('accepted', 'declined') AND s.result_seen_by_seller = 0)
    )";
    $count = $pdo->prepare("SELECT COUNT(*) FROM submissions s WHERE $where");
    $count->execute([$userId]);
    $total = (int) $count->fetchColumn();
    $query = $pdo->prepare("SELECT s.id, s.reference_number, s.status, s.updated_at
        FROM submissions s WHERE $where ORDER BY s.updated_at DESC, s.id DESC LIMIT 20");
    $query->execute([$userId]);
    $items = [];
    foreach ($query->fetchAll(PDO::FETCH_ASSOC) as $row) {
        $kind = $row['status'] === 'offer_sent' ? 'offer' : (string) $row['status'];
        $items[] = [
            'submission_id' => (int) $row['id'],
            'reference' => (string) $row['reference_number'],
            'kind' => $kind,
            'updated_at' => (string) $row['updated_at'],
            'page' => 'submission'
        ];
    }
    return ['total' => $total, 'items' => $items];
}
