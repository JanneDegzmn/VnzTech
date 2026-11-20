<?php
/* Read-only queue query helpers. No session, database connection or state writes. */
function vnzAdminQueueGroups(): array
{
    return [
        'new' => ['label' => 'New', 'statuses' => ['new_submission']],
        'review' => ['label' => 'Under review', 'statuses' => ['under_review', 'offer_pending', 'offer_sent']],
        'waiting' => ['label' => 'Waiting seller', 'statuses' => ['offer_pending', 'offer_sent']],
        'done' => ['label' => 'Done', 'statuses' => ['accepted', 'declined', 'cancelled', 'completed']],
    ];
}

function vnzAdminQueueState(array $input): array
{
    $queue = is_string($input['queue'] ?? null) ? $input['queue'] : '';
    $assignment = is_string($input['assignment'] ?? null) ? $input['assignment'] : 'all';
    return [
        'queue' => isset(vnzAdminQueueGroups()[$queue]) ? $queue : '',
        'assignment' => in_array($assignment, ['all', 'mine', 'unassigned'], true) ? $assignment : 'all',
        'sort' => ($input['sort'] ?? '') === 'oldest' ? 'oldest' : 'newest',
    ];
}

function vnzAdminQueueClause(array $state, int $adminId): array
{
    $sql = '';
    $params = [];
    $group = vnzAdminQueueGroups()[$state['queue']] ?? null;
    if ($group) {
        $params = $group['statuses'];
        $sql .= ' AND s.status IN (' . implode(',', array_fill(0, count($params), '?')) . ')';
        // The status remains offer_sent for seller counter-offers. Only the latest
        // pending admin offer is actually waiting on a seller response.
        $waitingSeller = "EXISTS (
            SELECT 1 FROM offers queue_offer
            WHERE queue_offer.submission_id = s.id
              AND queue_offer.id = (SELECT MAX(queue_latest.id) FROM offers queue_latest WHERE queue_latest.submission_id = s.id)
              AND queue_offer.sender_type = 'admin'
              AND LOWER(TRIM(queue_offer.status)) IN ('sent', 'offer_sent', 'pending', 'active')
        )";
        if ($state['queue'] === 'waiting') {
            $sql .= ' AND ' . $waitingSeller;
        } elseif ($state['queue'] === 'review') {
            $sql .= " AND (s.status = 'under_review' OR NOT " . $waitingSeller . ')';
        }
    }
    if ($state['assignment'] === 'mine') {
        $sql .= ' AND s.assigned_admin_id = ?';
        $params[] = $adminId;
    } elseif ($state['assignment'] === 'unassigned') {
        $sql .= ' AND (s.assigned_admin_id IS NULL OR s.assigned_admin_id = 0)';
    }
    return [$sql, $params];
}

function vnzAdminQueueOrder(array $state): string
{
    $direction = $state['sort'] === 'oldest' ? 'ASC' : 'DESC';
    return ' ORDER BY s.created_at ' . $direction . ', s.id ' . $direction;
}
