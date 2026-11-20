<?php
declare(strict_types=1);
require_once __DIR__ . '/../backend/site_status.php';
$checks = 0;
function check(bool $ok, string $label): void { global $checks; if (!$ok) { throw new RuntimeException($label); } $checks++; }
function rejects(callable $fn, string $label): void { try { $fn(); } catch (DomainException $e) { check(true, $label); return; } throw new RuntimeException($label); }
$root = sys_get_temp_dir() . '/vnz-status-test-' . bin2hex(random_bytes(8));
mkdir($root, 0700);
putenv('VNZ_SITE_STATUS_ROOT=' . $root);
putenv('VNZ_SITE_STATUS_READY_FLAG=' . $root . '/ready');
try {
    check(!vnzSiteStatusRead()['configured'], 'Unconfigured is default off');
    check(!vnzSiteStatusRead()['active'], 'Default off');
    file_put_contents($root . '/ready', 'test');
    check(vnzSiteStatusRead()['active'] && vnzSiteStatusRead()['state_error'], 'Configured missing snapshot fails closed');
    vnzSiteStatusAtomicWrite(vnzSiteStatusDefault());
    check(vnzSiteStatusRead()['configured'] && !vnzSiteStatusRead()['active'], 'Atomic default snapshot');
    $state = array_replace(vnzSiteStatusDefault(), ['version' => 2, 'active' => true, 'reason' => '<script>test</script>', 'started_at' => gmdate('c'), 'duration_minutes' => 1, 'estimated_end_at' => gmdate('c', time() - 1), 'updated_by' => 999]);
    vnzSiteStatusAtomicWrite($state);
    check(vnzSiteStatusRead()['version'] === 2, 'Snapshot version retained');
    check(!array_key_exists('updated_by', vnzSiteStatusPublic($state)), 'No public actor metadata');
    check(vnzSiteStatusPublic($state)['overdue'], 'Overdue estimate');
    $maintenancePage = vnzSiteMaintenancePage($state);
    check(str_contains($maintenancePage, '&lt;script&gt;test&lt;/script&gt;'), 'HTML reason escaped');
    check(!str_contains($maintenancePage, '<script>test</script>'), 'No raw reason HTML');
    check(str_contains($maintenancePage, '/assets/images/vnz.png') && str_contains($maintenancePage, 'We buy. We recover. We revalue.'), 'Maintenance page uses VNZ TECH branding');
    check(str_contains($maintenancePage, 'The public VNZ TECH site remains available') && str_contains($maintenancePage, 'data-site-status-area="seller"'), 'Maintenance message targets seller tools only');
    check(!str_contains($maintenancePage, 'Admin sign in'), 'Maintenance page has no Admin sign-in link');
    check(str_contains($maintenancePage, 'data-site-status-scope="seller_portal"'), 'Legacy seller page exposes its maintenance scope');
    $sellPage = vnzSiteMaintenancePage(array_replace($state, ['service_mode' => 'sell_page']));
    check(str_contains($sellPage, 'The Sell page is temporarily unavailable') && str_contains($sellPage, 'data-site-status-area="public"') && str_contains($sellPage, 'data-site-status-scope="sell_page"'), 'Sell page gets its own public maintenance view');
    check(!str_contains($sellPage, 'Admin sign in'), 'Sell page maintenance has no Admin sign-in link');
    $allServicesPage = vnzSiteMaintenancePage(array_replace($state, ['service_mode' => 'all_seller_services']), 'sell_page');
    check(str_contains($allServicesPage, 'Other parts of the VNZ TECH site remain available.'), 'All-services copy does not claim the Sell page remains open');
    check(str_contains($allServicesPage, 'data-site-status-area="public"') && str_contains($allServicesPage, 'data-site-status-scope="sell_page"'), 'All-services mode keeps the Sell page in public theme scope');
    $scopeCases = [
        'seller_portal' => ['seller_portal', 'submissions', 'offers_trades', 'account_settings', 'messaging'],
        'sell_page' => ['sell_page'],
        'submissions' => ['submissions'],
        'offers_trades' => ['offers_trades'],
        'account_settings' => ['account_settings'],
        'messaging' => ['messaging'],
        'all_seller_services' => ['seller_portal', 'sell_page', 'submissions', 'offers_trades', 'account_settings', 'messaging'],
    ];
    $knownScopes = ['seller_portal', 'sell_page', 'submissions', 'offers_trades', 'account_settings', 'messaging'];
    foreach ($scopeCases as $mode => $affectedScopes) {
        $modeState = array_replace($state, ['service_mode' => $mode]);
        foreach ($knownScopes as $scope) {
            check(vnzSiteMaintenanceAffectsScope($modeState, $scope) === in_array($scope, $affectedScopes, true), 'Maintenance mode applies only to its declared service scopes');
        }
        check(!vnzSiteMaintenanceAffectsScope($modeState, 'public') && !vnzSiteMaintenanceAffectsScope($modeState, 'admin'), 'Public and Admin scopes remain available');
    }
    $legacy = $state;
    unset($legacy['service_mode']);
    file_put_contents($root . '/maintenance.json', json_encode($legacy));
    $legacyRead = vnzSiteStatusRead();
    check($legacyRead['service_mode'] === 'seller_portal' && !vnzSiteMaintenanceAffectsScope($legacyRead, 'sell_page'), 'Legacy active state remains seller-only by default');
    foreach (['{}', '{', '{"version":-1}', '{"version":0,"active":"false"}'] as $invalid) {
        file_put_contents($root . '/maintenance.json', $invalid);
        check(vnzSiteStatusRead()['active'] && vnzSiteStatusRead()['state_error'], 'Malformed snapshot fails closed');
    }
    check(vnzSiteMaintenanceInput(['active' => '0'])['duration_minutes'] === 0, 'Disable clears estimate');
    rejects(fn()=>vnzSiteMaintenanceInput([]), 'Missing maintenance mode rejected');
    rejects(fn()=>vnzSiteMaintenanceInput(['active'=>'yes']), 'Unknown maintenance mode rejected');
    check(vnzSiteMaintenanceInput(['active'=>'0', 'service_mode'=>'sell_page'])['service_mode'] === 'sell_page', 'Landing-page service mode accepted');
    rejects(fn()=>vnzSiteMaintenanceInput(['active'=>'0', 'service_mode'=>'unknown']), 'Unknown service scope rejected');
    check(vnzSiteMaintenanceInput(['active'=>'1', 'reason'=>'Reason', 'hours'=>'168', 'minutes'=>'0'])['duration_minutes'] === 10080, 'Maximum duration accepted');
    foreach ([['reason'=>''], ['hours'=>'168','minutes'=>'1'], ['hours'=>'-1'], ['minutes'=>'60'], ['hours'=>'x'], ['hours'=>'0','minutes'=>'0'], ['reason'=>str_repeat('a',1001)]] as $bad) {
        rejects(fn()=>vnzSiteMaintenanceInput(array_replace(['active'=>'1','reason'=>'Reason','hours'=>'1','minutes'=>'0'], $bad)), 'Invalid maintenance rejected');
    }
    foreach ([['title'=>''], ['message'=>''], ['status'=>'deleted'], ['expires_at'=>'2020-01-01T00:00'], ['expires_at'=>'2099-02-30T00:00']] as $bad) {
        rejects(fn()=>vnzAnnouncementInput(array_replace(['title'=>'Title','message'=>'Message','status'=>'draft'], $bad)), 'Invalid announcement rejected');
    }
    check(vnzAnnouncementInput(['title'=>'T','message'=>'M','expires_at'=>'2099-01-01T08:00'])['expires_at'] === '2099-01-01 00:00:00', 'Philippine input converted UTC');
    $pdo = new PDO('sqlite::memory:');
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->sqliteCreateFunction('UTC_TIMESTAMP', fn()=>gmdate('Y-m-d H:i:s'));
    $pdo->exec('CREATE TABLE site_announcements (id INTEGER PRIMARY KEY AUTOINCREMENT, title TEXT, message TEXT, status TEXT, expires_at TEXT, created_by INT, updated_by INT, published_at TEXT, version INT DEFAULT 1, updated_at TEXT)');
    $pdo->exec('CREATE TABLE admin_activity_logs (admin_id INT, action TEXT, details TEXT, created_at TEXT)');
    vnzAnnouncementSave($pdo, 99, ['title'=>'Private draft', 'message'=>'Secret draft']);
    vnzAnnouncementSave($pdo, 99, ['title'=>'Published', 'message'=>'Visible', 'status'=>'published']);
    vnzAnnouncementSave($pdo, 99, ['title'=>'Archived', 'message'=>'Hidden', 'status'=>'archived']);
    $public = vnzAnnouncements($pdo);
    check($public['total'] === 1 && $public['items'][0]['title'] === 'Published', 'Draft/archive privacy');
    check(!array_key_exists('created_by', $public['items'][0]), 'Public excludes editor metadata');
    check(vnzAnnouncements($pdo, 1, true, 'draft')['total'] === 1, 'Admin draft filter');
    rejects(fn()=>vnzAnnouncements($pdo, 1, true, 'other'), 'Invalid status filter');
    rejects(fn()=>vnzAnnouncementSave($pdo, 99, ['id'=>2,'version'=>999,'title'=>'Stale','message'=>'M','status'=>'published']), 'Stale edit rejected');
    check(!$pdo->inTransaction(), 'Stale edit rolled back');
    check(vnzAnnouncements($pdo)['items'][0]['title'] === 'Published', 'Stale edit preserves record');
    vnzAnnouncementSave($pdo, 99, ['id'=>1,'version'=>1,'title'=>'Released draft','message'=>'Now visible','status'=>'published']);
    check(vnzAnnouncements($pdo)['total'] === 2, 'Draft publish transition');
    check($pdo->query('SELECT published_at FROM site_announcements WHERE id=1')->fetchColumn() !== null, 'Publish timestamp set');
    vnzAnnouncementSave($pdo, 99, ['id'=>1,'version'=>2,'title'=>'Archived draft','message'=>'Hidden again','status'=>'archived']);
    check(vnzAnnouncements($pdo)['total'] === 1, 'Archive transition removes public entry');
    for ($i=0; $i<15; $i++) { vnzAnnouncementSave($pdo, 99, ['title'=>'Post '.$i,'message'=>'Message','status'=>'published']); }
    check(count(vnzAnnouncements($pdo)['items']) === 10, 'Public bounded page');
    check(vnzAnnouncements($pdo, 999)['page'] === 2, 'Oversized page clamped');
    $pdo->exec("UPDATE site_announcements SET expires_at='2000-01-01 00:00:00' WHERE id=2");
    check(vnzAnnouncements($pdo)['total'] === 15, 'Expired excluded');
    echo "PASS: $checks isolated Site Updates service checks\n";
} finally {
    foreach (glob($root . '/*') ?: [] as $path) { unlink($path); }
    rmdir($root);
    putenv('VNZ_SITE_STATUS_ROOT'); putenv('VNZ_SITE_STATUS_READY_FLAG');
}
