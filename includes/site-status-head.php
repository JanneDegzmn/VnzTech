<?php // Shared shell/standalone entry integration; no additional database bootstrap.
$siteStatusScope = $siteStatusScope ?? ($GLOBALS['vnzSiteStatusScope'] ?? '');
?>
<link rel="stylesheet" href="/VNZ-TECH/assets/css/site-status.css?v=<?= filemtime(__DIR__ . '/../assets/css/site-status.css') ?>">
<script src="/VNZ-TECH/assets/js/site-status.js?v=<?= filemtime(__DIR__ . '/../assets/js/site-status.js') ?>" defer data-site-status-area="<?= htmlspecialchars($siteStatusArea ?? 'public', ENT_QUOTES, 'UTF-8') ?>" data-site-status-scope="<?= htmlspecialchars($siteStatusScope, ENT_QUOTES, 'UTF-8') ?>"></script>
