<?php
declare(strict_types=1);

/** Site status has no bootstrap side effects: HTTP entries call the guard explicitly. */
function vnzSiteStatusPaths(): array
{
    $root = getenv('VNZ_SITE_STATUS_ROOT') ?: dirname(__DIR__, 3) . '/private/VNZ-TECH/site-status';
    return ['root' => $root, 'snapshot' => $root . '/maintenance.json', 'lock' => $root . '/maintenance.lock',
        'ready' => getenv('VNZ_SITE_STATUS_READY_FLAG') ?: __DIR__ . '/site_status_ready.flag'];
}

function vnzSiteStatusDefault(): array
{
    return ['version' => 0, 'active' => false, 'reason' => '', 'started_at' => null,
        'duration_minutes' => 0, 'estimated_end_at' => null, 'service_mode' => 'seller_portal', 'updated_by' => null];
}

function vnzSiteMaintenanceModes(): array
{
    return [
        'seller_portal' => 'Seller portal & workflows',
        'sell_page' => 'Sell landing page (sell.php)',
        'submissions' => 'Submissions & verification',
        'offers_trades' => 'Offers & trades',
        'account_settings' => 'Seller account & settings',
        'messaging' => 'Chat & messages',
        'all_seller_services' => 'All seller services',
    ];
}

function vnzSiteMaintenanceAffectsScope(array $state, string $scope): bool
{
    if (empty($state['active'])) { return false; }
    $sellerScopes = ['seller_portal', 'submissions', 'offers_trades', 'account_settings', 'messaging'];
    if ($scope === 'public' || $scope === 'admin') { return false; }
    if ($scope !== 'sell_page' && !in_array($scope, $sellerScopes, true)) {
        throw new InvalidArgumentException('Unknown maintenance service scope.');
    }
    $mode = $state['service_mode'] ?? 'seller_portal';
    if ($mode === 'all_seller_services') { return true; }
    if ($mode === 'seller_portal') { return $scope !== 'sell_page'; }
    return $mode === $scope;
}

function vnzSiteStatusRead(): array
{
    $paths = vnzSiteStatusPaths();
    if (!is_file($paths['ready'])) {
        return vnzSiteStatusDefault() + ['configured' => false];
    }
    try {
        $state = json_decode((string) @file_get_contents($paths['snapshot']), true, 32, JSON_THROW_ON_ERROR);
        if (!is_array($state) || !is_int($state['version'] ?? null) || $state['version'] < 0
            || !is_bool($state['active'] ?? null) || !is_string($state['reason'] ?? null) || mb_strlen($state['reason']) > 1000
            || !is_string($state['service_mode'] ?? 'seller_portal') || !array_key_exists($state['service_mode'] ?? 'seller_portal', vnzSiteMaintenanceModes())
            || !is_int($state['duration_minutes'] ?? null)
            || ($state['active'] && (trim($state['reason']) === '' || $state['duration_minutes'] < 1
                || $state['duration_minutes'] > 10080 || !is_string($state['started_at'] ?? null)
                || strtotime($state['started_at']) === false || !is_string($state['estimated_end_at'] ?? null)
                || strtotime($state['estimated_end_at']) === false))) {
            throw new RuntimeException('Invalid maintenance state.');
        }
        return array_replace(vnzSiteStatusDefault(), array_intersect_key($state, vnzSiteStatusDefault()), ['configured' => true]);
    } catch (Throwable $e) {
        error_log('Site maintenance snapshot unavailable.');
        return array_replace(vnzSiteStatusDefault(), ['active' => true, 'reason' => 'The service is temporarily unavailable. Please check again shortly.',
            'service_mode' => 'seller_portal', 'configured' => true, 'state_error' => true]);
    }
}

function vnzSiteStatusPublic(array $state): array
{
    return array_intersect_key($state, array_flip(['version', 'active', 'reason', 'started_at', 'duration_minutes', 'estimated_end_at', 'service_mode']))
        + ['overdue' => $state['active'] && !empty($state['estimated_end_at']) && strtotime($state['estimated_end_at']) <= time()];
}

function vnzSiteStatusAtomicWrite(array $state): void
{
    $paths = vnzSiteStatusPaths();
    $root = realpath($paths['root']);
    $webroot = realpath(dirname(__DIR__, 2));
    if ($root === false || $webroot === false || str_starts_with(strtolower(str_replace('\\', '/', $root)) . '/', strtolower(str_replace('\\', '/', $webroot)) . '/')) {
        throw new RuntimeException('Maintenance storage must exist outside the application webroot.');
    }
    $temporary = tempnam($root, 'status-');
    if ($temporary === false) { throw new RuntimeException('Maintenance storage is not writable.'); }
    try {
        $json = json_encode(array_intersect_key($state, vnzSiteStatusDefault()), JSON_THROW_ON_ERROR | JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
        if (@file_put_contents($temporary, $json, LOCK_EX) === false || !@rename($temporary, $paths['snapshot'])) {
            throw new RuntimeException('Could not publish maintenance state.');
        }
        @chmod($paths['snapshot'], 0600);
    } finally { if (is_file($temporary)) { unlink($temporary); } }
}

function vnzSiteMaintenanceInput(array $input): array
{
    if (!in_array($input['active'] ?? null, ['0', '1'], true)) { throw new DomainException('Choose a valid maintenance mode.'); }
    $active = $input['active'] === '1';
    $serviceMode = $input['service_mode'] ?? 'seller_portal';
    if (!is_string($serviceMode) || !array_key_exists($serviceMode, vnzSiteMaintenanceModes())) {
        throw new DomainException('Choose a valid service mode.');
    }
    if (isset($input['reason']) && !is_string($input['reason'])) { throw new DomainException('Enter a valid maintenance reason.'); }
    $reason = trim((string) ($input['reason'] ?? ''));
    $hours = filter_var($input['hours'] ?? 0, FILTER_VALIDATE_INT);
    $minutes = filter_var($input['minutes'] ?? 0, FILTER_VALIDATE_INT);
    if ($hours === false || $minutes === false || $hours < 0 || $hours > 168 || $minutes < 0 || $minutes > 59) {
        throw new DomainException('Enter valid estimated hours and minutes.');
    }
    $duration = $hours * 60 + $minutes;
    if ($active && ($reason === '' || mb_strlen($reason) > 1000 || $duration < 1 || $duration > 10080)) {
        throw new DomainException('Maintenance needs a reason (up to 1,000 characters) and an estimate between 1 minute and 168 hours.');
    }
    return ['active' => $active, 'reason' => $active ? $reason : '', 'duration_minutes' => $active ? $duration : 0,
        'service_mode' => $serviceMode];
}

function vnzSiteMaintenanceSave(PDO $pdo, int $adminId, array $input): array
{
    $values = vnzSiteMaintenanceInput($input);
    $paths = vnzSiteStatusPaths();
    if (!is_file($paths['ready']) || !is_dir($paths['root'])) { throw new RuntimeException('Complete the local Site Updates setup first.'); }
    $lock = @fopen($paths['lock'], 'c');
    if ($lock === false || !flock($lock, LOCK_EX)) { throw new RuntimeException('Maintenance controls are busy. Try again.'); }
    $old = vnzSiteStatusRead();
    $published = false;
    try {
        if (!empty($old['state_error']) || !isset($input['version']) || (string) $old['version'] !== (string) $input['version']) {
            throw new DomainException('Maintenance changed or needs recovery. Refresh before saving.');
        }
        $started = $values['active'] ? gmdate('c') : null;
        $state = array_replace(vnzSiteStatusDefault(), $values, ['version' => $old['version'] + 1, 'started_at' => $started,
            'estimated_end_at' => $values['active'] ? gmdate('c', time() + $values['duration_minutes'] * 60) : null, 'updated_by' => $adminId]);
        $pdo->beginTransaction();
        // File lock serializes app/CLI writers; row lock also serializes database writers.
        if ((int) $pdo->query('SELECT id FROM site_maintenance WHERE id = 1 FOR UPDATE')->fetchColumn() !== 1) { throw new RuntimeException('Maintenance setup is incomplete.'); }
        $stmt = $pdo->prepare('UPDATE site_maintenance SET version = ?, state_json = ?, updated_by = ?, updated_at = UTC_TIMESTAMP() WHERE id = 1');
        $stmt->execute([$state['version'], json_encode($state, JSON_THROW_ON_ERROR), $adminId]);
        $audit = $pdo->prepare('INSERT INTO admin_activity_logs (admin_id, action, details, created_at) VALUES (?, ?, ?, UTC_TIMESTAMP())');
        $audit->execute([$adminId, 'site.maintenance_updated', $state['active'] ? 'Maintenance enabled/updated; estimate ' . $state['duration_minutes'] . ' minutes.' : 'Maintenance disabled.']);
        vnzSiteStatusAtomicWrite($state);
        $published = true;
        $pdo->commit();
        return $state;
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) { $pdo->rollBack(); }
        if ($published) { vnzSiteStatusAtomicWrite($old); }
        throw $e;
    } finally { flock($lock, LOCK_UN); fclose($lock); }
}

function vnzAnnouncementInput(array $input): array
{
    foreach (['title', 'message', 'status', 'expires_at'] as $field) { if (isset($input[$field]) && !is_string($input[$field])) { throw new DomainException('Enter valid announcement fields.'); } }
    $title = trim((string) ($input['title'] ?? ''));
    $message = trim((string) ($input['message'] ?? ''));
    $status = (string) ($input['status'] ?? 'draft');
    if ($title === '' || mb_strlen($title) > 150 || $message === '' || mb_strlen($message) > 5000
        || !in_array($status, ['draft', 'published', 'archived'], true)) {
        throw new DomainException('Enter a title (up to 150 characters), message (up to 5,000 characters) and valid status.');
    }
    $expiry = trim((string) ($input['expires_at'] ?? ''));
    if ($expiry !== '') {
        $date = DateTimeImmutable::createFromFormat('!Y-m-d\\TH:i', $expiry, new DateTimeZone('Asia/Manila'));
        if (!$date || $date->format('Y-m-d\\TH:i') !== $expiry || $date->getTimestamp() <= time()) {
            throw new DomainException('Expiration must be a valid future Philippine date and time.');
        }
        $expiry = $date->setTimezone(new DateTimeZone('UTC'))->format('Y-m-d H:i:s');
    }
    return ['title' => $title, 'message' => $message, 'status' => $status, 'expires_at' => $expiry === '' ? null : $expiry];
}

function vnzAnnouncements(PDO $pdo, int $page = 1, bool $admin = false, string $filter = ''): array
{
    $where = $admin ? '1=1' : "status = 'published' AND (expires_at IS NULL OR expires_at > UTC_TIMESTAMP())";
    $params = [];
    if ($admin && $filter !== '') {
        if (!in_array($filter, ['draft', 'published', 'archived'], true)) { throw new DomainException('Invalid announcement filter.'); }
        $where .= ' AND status = ?'; $params[] = $filter;
    }
    $count = $pdo->prepare('SELECT COUNT(*) FROM site_announcements WHERE ' . $where); $count->execute($params);
    $total = (int) $count->fetchColumn(); $pages = max(1, (int) ceil($total / 10)); $page = min(max(1, $page), $pages);
    $columns = $admin ? '*' : 'id, title, message, published_at, expires_at';
    $stmt = $pdo->prepare('SELECT ' . $columns . ' FROM site_announcements WHERE ' . $where . ' ORDER BY published_at DESC, id DESC LIMIT 10 OFFSET ' . (($page - 1) * 10));
    $stmt->execute($params);
    return ['items' => $stmt->fetchAll(PDO::FETCH_ASSOC), 'total' => $total, 'page' => $page, 'pages' => $pages];
}

function vnzAnnouncementSave(PDO $pdo, int $adminId, array $input): void
{
    $data = vnzAnnouncementInput($input);
    $id = filter_var($input['id'] ?? 0, FILTER_VALIDATE_INT);
    if ($id === false || $id < 0) { throw new DomainException('Invalid announcement.'); }
    $pdo->beginTransaction();
    try {
        if ($id > 0) {
            $version = filter_var($input['version'] ?? null, FILTER_VALIDATE_INT);
            // published_at uses the old status before the status assignment (MySQL evaluates assignments left-to-right).
            $stmt = $pdo->prepare("UPDATE site_announcements SET published_at=CASE WHEN ?='published' AND status <> 'published' THEN UTC_TIMESTAMP() ELSE published_at END, title=?, message=?, status=?, expires_at=?, updated_by=?, version=version+1, updated_at=UTC_TIMESTAMP() WHERE id=? AND version=?");
            $stmt->execute([$data['status'], $data['title'], $data['message'], $data['status'], $data['expires_at'], $adminId, $id, $version]);
            if ($stmt->rowCount() !== 1) { throw new DomainException('This announcement changed. Refresh before editing.'); }
        } else {
            $stmt = $pdo->prepare("INSERT INTO site_announcements (title,message,status,expires_at,created_by,updated_by,published_at) VALUES (?,?,?,?,?,?,CASE WHEN ?='published' THEN UTC_TIMESTAMP() ELSE NULL END)");
            $stmt->execute([$data['title'], $data['message'], $data['status'], $data['expires_at'], $adminId, $adminId, $data['status']]);
            $id = (int) $pdo->lastInsertId();
        }
        $audit = $pdo->prepare('INSERT INTO admin_activity_logs (admin_id, action, details, created_at) VALUES (?, ?, ?, UTC_TIMESTAMP())');
        $audit->execute([$adminId, 'site.announcement_saved', 'Announcement #' . $id . ' saved as ' . $data['status'] . '.']);
        $pdo->commit();
    } catch (Throwable $e) { if ($pdo->inTransaction()) { $pdo->rollBack(); } throw $e; }
}

function vnzSiteMaintenancePage(array $state, string $serviceScope = ''): string
{
    $mode = (string) ($state['service_mode'] ?? 'seller_portal');
    $sellPage = $mode === 'sell_page' || $serviceScope === 'sell_page';
    $displayScope = $serviceScope !== '' ? $serviceScope : $mode;
    $reason = htmlspecialchars($state['reason'], ENT_QUOTES, 'UTF-8');
    $estimate = !empty($state['estimated_end_at']) ? (new DateTimeImmutable($state['estimated_end_at']))->setTimezone(new DateTimeZone('Asia/Manila'))->format('M j, Y g:i A') . ' PHT' : 'We will provide an updated estimate.';
    $duration = (int) $state['duration_minutes'];
    $durationText = $duration > 0 ? 'Estimated duration: ' . intdiv($duration, 60) . 'h ' . ($duration % 60) . 'm.' : '';
    $overdue = !empty($state['estimated_end_at']) && strtotime($state['estimated_end_at']) <= time();
    $timing = $overdue ? 'Maintenance is taking longer than estimated. Please check again shortly.' : 'Estimated finish: ' . $estimate;
    $status = $sellPage ? 'The Sell page is temporarily unavailable' : 'This seller service is temporarily unavailable';
    $intro = $sellPage
        ? 'Our Sell page is taking a short break while we make improvements.'
        : 'This seller service is taking a short break while we make improvements to VNZ TECH.';
    $publicNote = $sellPage || $mode === 'all_seller_services'
        ? 'Other parts of the VNZ TECH site remain available.'
        : 'The public VNZ TECH site remains available during this update.';
    $area = $sellPage ? 'public' : 'seller';
    return '<!doctype html><html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><title>Service update | VNZ TECH</title><link rel="stylesheet" href="/VNZ-TECH/assets/css/site-status.css"><script src="/VNZ-TECH/assets/js/site-status.js" defer data-site-status-area="' . $area . '" data-site-status-scope="' . htmlspecialchars($displayScope, ENT_QUOTES, 'UTF-8') . '" data-maintenance-page="true"></script></head><body class="site-maintenance-page"><main class="site-maintenance-card"><section class="site-maintenance-content"><a class="site-maintenance-brand" href="/VNZ-TECH/index.php" aria-label="VNZ TECH public site"><img src="/VNZ-TECH/assets/images/vnz.png" alt=""><span><strong>VNZ TECH</strong><small>We buy. We recover. We revalue.</small></span></a><p class="site-maintenance-status"><span aria-hidden="true"></span>' . htmlspecialchars($status, ENT_QUOTES, 'UTF-8') . '</p><h1>We’re making things better.</h1><p class="site-maintenance-intro">' . htmlspecialchars($intro, ENT_QUOTES, 'UTF-8') . '</p><section class="site-maintenance-details" aria-label="Maintenance details"><div><span>What’s happening</span><p class="site-status-copy">' . $reason . '</p></div><div><span>Estimated return</span><strong>' . htmlspecialchars($estimate, ENT_QUOTES, 'UTF-8') . '</strong><small>' . htmlspecialchars($durationText, ENT_QUOTES, 'UTF-8') . '</small></div></section><p class="site-maintenance-public-note">' . htmlspecialchars($publicNote, ENT_QUOTES, 'UTF-8') . '</p><div class="site-maintenance-actions"><button type="button" data-maintenance-recheck>Check status</button></div><p class="site-maintenance-feedback" role="status" aria-live="polite" data-maintenance-feedback>' . htmlspecialchars($timing, ENT_QUOTES, 'UTF-8') . '</p></section><aside class="site-maintenance-art" aria-hidden="true"><span>VNZ TECH</span><svg viewBox="0 0 160 160" focusable="false"><circle cx="80" cy="80" r="58"></circle><path d="M103 47a29 29 0 0 0-35 36L45 106a12 12 0 0 0 17 17l23-23a29 29 0 0 0 36-35l-17 17-17-5-5-17z"></path></svg><small>Thoughtful updates. Better tools.</small></aside></main></body></html>';
}

function vnzEnforceSiteMaintenance(string $format = 'auto', bool $allowAdmin = false, bool $sellerFeature = false, string $serviceScope = ''): void
{
    if ($serviceScope === '' && $sellerFeature) { $serviceScope = 'seller_portal'; }
    $GLOBALS['vnzSiteStatusScope'] = $serviceScope;
    if (PHP_SAPI === 'cli') { return; }
    $state = vnzSiteStatusRead();
    if (!$state['active']) { return; }
    if ($allowAdmin) {
        // The auth bootstrap assigns PDO; keep that handle in the scope auth helpers read.
        global $pdo;
        require_once __DIR__ . '/auth.php';
        if (getCurrentAdmin() !== null) { return; }
    }
    if (!$sellerFeature && $serviceScope === '' && !$allowAdmin) { return; }
    if ($serviceScope !== '' && !vnzSiteMaintenanceAffectsScope($state, $serviceScope)) { return; }
    if ($serviceScope === '' && $allowAdmin
        && !in_array($state['service_mode'] ?? 'seller_portal', ['seller_portal', 'all_seller_services'], true)) { return; }
    $json = $format === 'json' || ($format === 'auto' && (str_contains(strtolower($_SERVER['HTTP_ACCEPT'] ?? ''), 'application/json')
        || strtolower($_SERVER['HTTP_X_REQUESTED_WITH'] ?? '') === 'xmlhttprequest'));
    http_response_code(503);
    header('Cache-Control: no-store, max-age=0');
    header('Retry-After: ' . max(60, min(3600, !empty($state['estimated_end_at']) ? strtotime($state['estimated_end_at']) - time() : 60)));
    header('Content-Type: ' . ($json ? 'application/json' : 'text/html') . '; charset=utf-8');
    if ($json) { echo json_encode(['success' => false, 'code' => 'maintenance', 'message' => $state['reason'], 'maintenance' => vnzSiteStatusPublic($state)], JSON_UNESCAPED_SLASHES); }
    else { echo vnzSiteMaintenancePage($state, $serviceScope); }
    exit;
}
