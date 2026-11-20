<?php
declare(strict_types=1);
require_once __DIR__ . '/../backend/site_status.php';

$mode = $argv[1] ?? 'sell_page';
if (!array_key_exists($mode, vnzSiteMaintenanceModes())) {
    throw new InvalidArgumentException('Unknown fixture service mode.');
}

$state = array_replace(vnzSiteStatusDefault(), [
    'version' => 1,
    'active' => true,
    'reason' => 'The Sell page is receiving a scheduled update.',
    'started_at' => gmdate('c'),
    'duration_minutes' => 90,
    'estimated_end_at' => gmdate('c', time() + 5400),
    'service_mode' => $mode,
]);

echo vnzSiteMaintenancePage($state, 'sell_page');
