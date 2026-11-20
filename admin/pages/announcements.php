<?php

require_once __DIR__ . '/../../backend/site_status.php';
vnzEnforceSiteMaintenance('auto', true);
require_once __DIR__ . '/../../backend/auth.php';
require_once __DIR__ . '/../../backend/csrf.php';
$siteAdmin = getCurrentAdmin();
$canAnnounce = $siteAdmin && hasPermission('manage_announcements');
$canMaintain = $siteAdmin && hasPermission('manage_maintenance');
if (!$canAnnounce && !$canMaintain) { http_response_code(403); echo '<h1>Access denied</h1>'; return; }
?>
<link rel="stylesheet" href="/VNZ-TECH/assets/css/site-status.css?v=<?= filemtime(__DIR__ . '/../../assets/css/site-status.css') ?>">
<link rel="stylesheet" href="/VNZ-TECH/assets/css/admin_operations.css?v=<?= filemtime(__DIR__ . '/../../assets/css/admin_operations.css') ?>">
<section class="site-updates" data-site-updates data-can-announce="<?= $canAnnounce ? '1' : '0' ?>" data-can-maintain="<?= $canMaintain ? '1' : '0' ?>">
    <header class="operations-header">
        <div>
            <span class="operations-eyebrow">ADMINISTRATION</span>
            <h1>Announcements &amp; Maintenance</h1>
            <p>Share clear updates and manage service availability.</p>
        </div>
    </header>
    <div class="site-update-tabs" role="tablist" aria-label="Site Updates">
        <?php if ($canAnnounce): ?><button type="button" id="siteAnnouncementTab" role="tab" aria-controls="siteAnnouncementPanel" data-site-tab="announcements">Announcements</button><?php endif; ?>
        <?php if ($canMaintain): ?><button type="button" id="siteMaintenanceTab" role="tab" aria-controls="siteMaintenancePanel" data-site-tab="maintenance">Maintenance</button><?php endif; ?>
    </div>
    <p role="status" aria-live="polite" data-site-feedback></p>
    <?php if ($canAnnounce): ?>
    <div id="siteAnnouncementPanel" role="tabpanel" aria-labelledby="siteAnnouncementTab" data-site-panel="announcements">
        <form data-announcement-form class="site-status-card">
            <h2 data-announcement-heading>New announcement</h2>
            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(generateCsrfToken(), ENT_QUOTES, 'UTF-8') ?>">
            <input type="hidden" name="action" value="announcement_save"><input type="hidden" name="id" value="0"><input type="hidden" name="version" value="1">
        <label>Title<input name="title" maxlength="150" placeholder="Example: Seller submissions are temporarily unavailable" required></label>
        <label>Message for users<textarea name="message" rows="5" maxlength="5000" placeholder="Say what is affected, when it changes, and what users should do." required></textarea></label>
        <p class="site-updates-guidance">Use simple Filipino or English. Tell people what is affected, until when, and what they should do. Avoid internal terms.</p>
        <div class="site-status-fields"><label>Visibility<select name="status"><option value="draft">Draft — not shown to users</option><option value="published">Published — visible to everyone</option><option value="archived">Archived — hidden</option></select></label>
        <label>Hide after (optional, Manila time)<input type="datetime-local" name="expires_at"></label></div>
        <p>Only published announcements appear on VNZ TECH pages. Preview the message before you publish it.</p>
        <div class="site-status-actions"><button type="button" data-announcement-preview>Preview message</button><button type="button" data-announcement-new>Clear form</button><button type="submit">Save announcement</button></div>
        <article data-announcement-preview-box class="site-status-card" aria-live="polite" hidden><h3></h3><p class="site-status-copy"></p></article>
        </form>
    <section class="site-status-card"><h2>Announcement history</h2><label>Show<select data-announcement-filter><option value="">All announcements</option><option value="draft">Draft — not shown</option><option value="published">Published — visible</option><option value="archived">Archived — hidden</option></select></label>
            <div data-announcement-list></div><nav class="site-status-actions" aria-label="Announcement pages"><button type="button" data-announcement-prev>Previous</button><span data-announcement-range></span><button type="button" data-announcement-next>Next</button></nav>
        </section>
    </div>
    <?php endif; ?>
    <?php if ($canMaintain): ?>
    <div id="siteMaintenancePanel" role="tabpanel" aria-labelledby="siteMaintenanceTab" data-site-panel="maintenance" hidden>
        <form data-maintenance-form class="site-status-card">
            <h2>Service status</h2><p data-maintenance-state role="status" aria-live="polite">Loading service status…</p>
            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(generateCsrfToken(), ENT_QUOTES, 'UTF-8') ?>"><input type="hidden" name="action" value="maintenance"><input type="hidden" name="version" value="0"><input type="hidden" name="active" value="0">
            <label>Service to pause<select name="service_mode" required>
                <?php foreach (vnzSiteMaintenanceModes() as $mode => $label): ?>
                    <option value="<?= htmlspecialchars($mode, ENT_QUOTES, 'UTF-8') ?>"><?= htmlspecialchars($label, ENT_QUOTES, 'UTF-8') ?></option>
                <?php endforeach; ?>
            </select></label>
            <label>What should we tell users?<textarea name="reason" rows="4" maxlength="1000" placeholder="Use a short, clear explanation. This appears on the maintenance page."></textarea></label>
            <p class="site-updates-guidance">Choose one service, explain what is happening in simple words, and give an estimated return time. The estimate is not an automatic restart.</p>
            <div class="site-status-fields"><label>Estimated hours<input type="number" name="hours" min="0" max="168" value="1"></label><label>Extra minutes<input type="number" name="minutes" min="0" max="59" value="0"></label></div>
            <div class="site-status-actions"><button type="button" data-maintenance-refresh>Refresh status</button><button type="button" data-maintenance-toggle data-active="false" disabled>Enable maintenance</button></div>
        </form>
    </div>
    <?php endif; ?>
</section>
<script src="/VNZ-TECH/assets/js/admin-site-updates.js?v=<?= filemtime(__DIR__ . '/../../assets/js/admin-site-updates.js') ?>"></script>
