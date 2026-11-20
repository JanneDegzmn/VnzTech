<?php
require __DIR__ . '/../backend/admin_submission_queue.php';
require __DIR__ . '/../backend/pagination.php';
$checks = 0;
function checkQueue($condition, string $message): void {
    global $checks;
    if (!$condition) throw new RuntimeException($message);
    $checks++;
}
$db = new PDO('sqlite::memory:');
$db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
$db->exec('CREATE TABLE submissions (id INTEGER PRIMARY KEY, status TEXT, assigned_admin_id INTEGER, created_at TEXT)');
$db->exec('CREATE TABLE offers (id INTEGER PRIMARY KEY, submission_id INTEGER, sender_type TEXT, status TEXT)');
$offerInsert = $db->prepare('INSERT INTO offers VALUES (?,?,?,?)');
$insert = $db->prepare('INSERT INTO submissions VALUES (?,?,?,?)');
$statuses = ['new_submission', 'under_review', 'offer_pending', 'offer_sent', 'accepted', 'declined', 'cancelled', 'completed'];
for ($i = 1; $i <= 48; $i++) $insert->execute([$i, $statuses[($i - 1) % 8], $i % 3 === 0 ? null : ($i % 3 === 1 ? 7 : 8), '2026-11-20 10:00:00']);
for ($i = 1; $i <= 48; $i++) $offerInsert->execute([$i, $i, $i % 2 === 0 ? 'admin' : 'seller', 'sent']);
foreach (vnzAdminQueueGroups() as $key => $group) {
    foreach (['all', 'mine', 'unassigned'] as $assignment) {
        $state = vnzAdminQueueState(['queue' => $key, 'assignment' => $assignment, 'sort' => 'oldest']);
        [$where, $params] = vnzAdminQueueClause($state, 7);
        $count = $db->prepare('SELECT COUNT(*) FROM submissions s WHERE 1=1' . $where);
        $count->execute($params);
        $query = $db->prepare('SELECT * FROM submissions s WHERE 1=1' . $where . vnzAdminQueueOrder($state));
        $query->execute($params);
        $rows = $query->fetchAll(PDO::FETCH_ASSOC);
        checkQueue(count($rows) === (int) $count->fetchColumn(), 'List/count clauses agree');
        checkQueue(count($rows) > 0, 'Each synthetic queue has matching records');
        foreach ($rows as $row) {
            checkQueue(in_array($row['status'], $group['statuses'], true), 'Allowlisted group status');
            if ($key === 'waiting') checkQueue((int) $row['id'] % 2 === 0, 'Waiting seller requires latest admin offer');
            if ($key === 'review' && $row['status'] !== 'under_review') checkQueue((int) $row['id'] % 2 !== 0, 'Latest seller counter-offer belongs to review');
            checkQueue($assignment === 'all' || ($assignment === 'mine' ? (int) $row['assigned_admin_id'] === 7 : $row['assigned_admin_id'] === null), 'Correct assignment scope');
        }
        $ids = array_column($rows, 'id'); $sorted = $ids; sort($sorted);
        checkQueue($ids === $sorted, 'Equal timestamps use deterministic ID order');
    }
}
$state = vnzAdminQueueState(['queue' => "new') OR 1=1--", 'assignment' => ['mine'], 'sort' => 'ASC; DROP TABLE submissions']);
checkQueue($state === ['queue' => '', 'assignment' => 'all', 'sort' => 'newest'], 'Untrusted options normalize to safe defaults');
checkQueue(vnzAdminQueueClause($state, 7) === ['', []], 'Invalid filters cannot inject SQL');
checkQueue(vnzAdminQueueOrder($state) === ' ORDER BY s.created_at DESC, s.id DESC', 'Sort SQL is allowlisted');
[$waitingClause, $waitingParams] = vnzAdminQueueClause(vnzAdminQueueState(['queue' => 'waiting']), 7);
$waitingQuery = $db->prepare('SELECT s.id FROM submissions s WHERE 1=1' . $waitingClause);
$offerInsert->execute([100, 3, 'admin', 'sent']);
$waitingQuery->execute($waitingParams);
checkQueue(in_array(3, $waitingQuery->fetchAll(PDO::FETCH_COLUMN)), 'Latest admin offer supersedes earlier seller offer');
$offerInsert->execute([101, 3, 'seller', 'sent']);
$waitingQuery->execute($waitingParams);
checkQueue(!in_array(3, $waitingQuery->fetchAll(PDO::FETCH_COLUMN)), 'New seller counter-offer removes request from waiting seller');
$offerInsert->execute([102, 3, 'admin', 'rejected']);
$waitingQuery->execute($waitingParams);
checkQueue(!in_array(3, $waitingQuery->fetchAll(PDO::FETCH_COLUMN)), 'Decided latest offer cannot be shown as awaiting seller');
$pages = [];
foreach ([1, 2, 3, 4] as $page) {
    $_GET = ['list_page' => $page];
    $pagination = vnzPaginationClamp(vnzPaginationState(12), 48);
    $rows = $db->query('SELECT id FROM submissions s' . vnzAdminQueueOrder(vnzAdminQueueState(['sort' => 'oldest'])) . ' LIMIT 12 OFFSET ' . $pagination['offset'])->fetchAll(PDO::FETCH_COLUMN);
    checkQueue(count($rows) === 12, 'Twelve records per page');
    array_push($pages, ...$rows);
}
checkQueue(count(array_unique($pages)) === 48, 'No duplicate or omitted records across equal-timestamp pages');
$db->exec('DELETE FROM submissions WHERE id=7');
$lateBaseline = $db->query('SELECT COALESCE(MAX(id), 0) AS max_id, COUNT(*) AS total FROM submissions')->fetch(PDO::FETCH_ASSOC);
$insert->execute([7, 'cancelled', 7, '2026-11-20 10:00:00']);
$arrivalQuery = $db->prepare('SELECT COALESCE(SUM(CASE WHEN id > ? THEN 1 ELSE 0 END), 0) AS newer_count, COUNT(*) AS current_total FROM submissions');
$arrivalQuery->execute([$lateBaseline['max_id']]);
$lateCounts = $arrivalQuery->fetch(PDO::FETCH_ASSOC);
checkQueue((int) $lateCounts['newer_count'] === 0, 'Late lower ID cannot be found by max-ID comparison alone');
checkQueue(max((int) $lateCounts['newer_count'], max(0, (int) $lateCounts['current_total'] - (int) $lateBaseline['total'])) === 1, 'Total growth catches insert-only out-of-order visibility');
$baseline = (int) $db->query('SELECT MAX(id) FROM submissions')->fetchColumn();
for ($i = 49; $i <= 58; $i++) $insert->execute([$i, 'new_submission', null, '2026-11-20 10:00:00']);
$poll = $db->prepare('SELECT COUNT(*) FROM submissions WHERE id > ?');
$poll->execute([$baseline]); checkQueue((int) $poll->fetchColumn() === 10, 'Burst arrivals counted against fixed baseline');
$poll->execute([$baseline]); checkQueue((int) $poll->fetchColumn() === 10, 'Polling does not acknowledge or consume arrivals');
$html = vnzRenderPagination(vnzPaginationClamp(vnzPaginationState(), 58), ['queue' => 'review', 'assignment' => 'mine', 'sort' => 'oldest', 'search' => 'fixture'], 'submissions', 'admin', '/VNZ-TECH/admin/dashboard.php');
foreach (['queue=review','assignment=mine','sort=oldest','search=fixture'] as $part) checkQueue(str_contains($html, $part), 'Pagination retains ' . $part);
echo "PASS $checks queue query/pagination/burst assertions (SQLite synthetic data; not MySQL load testing)\n";
