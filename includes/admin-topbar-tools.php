<?php
$adminTopbarName = trim(($currentAdmin['first_name'] ?? '') . ' ' . ($currentAdmin['last_name'] ?? '')) ?: ($currentAdmin['username'] ?? 'Admin');
$adminTopbarEscape = static fn($value) => htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
?>
<nav class="seller-topbar-actions" aria-label="Admin account controls">
    <div class="seller-topbar-notifications">
        <button class="seller-topbar-icon" type="button" data-admin-updates-toggle aria-label="Notifications" aria-expanded="false" aria-controls="adminTopbarUpdates">
            <svg viewBox="0 0 24 24" fill="none" aria-hidden="true"><path d="M18 8a6 6 0 0 0-12 0c0 7-3 7-3 9h18c0-2-3-2-3-9ZM10 21h4" stroke="currentColor" stroke-width="1.7" stroke-linecap="round"/></svg>
            <span class="seller-topbar-badge" data-admin-updates-badge hidden></span>
        </button>
        <section id="adminTopbarUpdates" class="seller-topbar-popover" aria-label="Admin updates" hidden>
            <div class="seller-topbar-popover-heading"><h2>Notifications</h2><button type="button" class="seller-topbar-icon" data-admin-updates-close aria-label="Close notifications">&times;</button></div>
            <p class="seller-topbar-state" data-admin-updates-state role="status">Loading updates...</p>
            <div class="seller-topbar-update-list" data-admin-updates-list></div>
            <button type="button" class="seller-topbar-retry" data-admin-updates-retry hidden>Try again</button>
        </section>
    </div>
    <?php if (hasPermission('view_submissions')): $floatingMessagesPlacement = 'admin-topbar'; require __DIR__ . '/floating-messages.php'; unset($floatingMessagesPlacement); endif; ?>
    <a class="seller-topbar-profile" href="?page=profile" aria-label="Open admin profile">
        <span class="seller-topbar-avatar" aria-hidden="true"><?= $adminTopbarEscape(strtoupper(substr($adminTopbarName, 0, 1))) ?></span>
        <span class="seller-topbar-profile-name"><?= $adminTopbarEscape($adminTopbarName) ?></span>
    </a>
</nav>
