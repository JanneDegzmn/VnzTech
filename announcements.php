<?php
declare(strict_types=1);
require_once __DIR__ . '/backend/site_status.php';
vnzEnforceSiteMaintenance('auto', false);
require_once __DIR__ . '/backend/database.php';
header('Cache-Control: no-store');
$announcements = ['items' => [], 'page' => 1, 'pages' => 1];
$unavailable = false;
try { $announcements = vnzAnnouncements($pdo, max(1, (int) ($_GET['page'] ?? 1))); }
catch (Throwable $e) { $unavailable = true; http_response_code(503); }
$escape = static fn ($text): string => htmlspecialchars((string) $text, ENT_QUOTES, 'UTF-8');
?>
<!doctype html><html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><title>Announcements | VNZ TECH</title>
<?php $siteStatusArea = 'public'; require __DIR__ . '/includes/site-status-head.php'; ?>
</head><body class="site-maintenance-page"><main class="site-maintenance-card"><h1>Announcements</h1><a href="/VNZ-TECH/sell.php">Back to VNZ TECH</a>
<?php if ($unavailable): ?><p>Announcements are temporarily unavailable. Please try again shortly.</p>
<?php elseif (!$announcements['items']): ?><p>No active announcements.</p><?php endif; ?>
<?php foreach ($announcements['items'] as $item): ?><article class="site-status-card"><h2><?= $escape($item['title']) ?></h2><p class="site-status-copy"><?= $escape($item['message']) ?></p></article><?php endforeach; ?>
<nav class="site-status-actions" aria-label="Announcement pages"><?php if ($announcements['page'] > 1): ?><a href="?page=<?= $announcements['page'] - 1 ?>">Previous</a><?php endif; ?><span>Page <?= $announcements['page'] ?> of <?= $announcements['pages'] ?></span><?php if ($announcements['page'] < $announcements['pages']): ?><a href="?page=<?= $announcements['page'] + 1 ?>">Next</a><?php endif; ?></nav>
</main></body></html>
