<?php
require_once __DIR__ . '/../backend/seller_topbar.php';
$topbarProfile = vnzSellerTopbarProfile($sellerAccount);
$topbarFilipino = ($sellerAccount['language'] ?? '') === 'fil';
$topbarEscape = static fn($value) => htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
?>
<header class="seller-topbar" data-seller-topbar data-language="<?= $topbarFilipino ? 'fil' : 'en' ?>">
    <div class="seller-topbar-start">
        <span data-seller-menu-slot></span>
    </div>
    <nav class="seller-topbar-actions" aria-label="Seller account controls">
        <div class="seller-topbar-notifications">
            <button class="seller-topbar-icon" type="button" data-topbar-notifications-toggle aria-label="<?= $topbarFilipino ? 'Mga update' : 'Notifications' ?>" aria-controls="sellerTopbarNotifications" aria-expanded="false">
                <svg viewBox="0 0 24 24" fill="none" aria-hidden="true"><path d="M18 8a6 6 0 0 0-12 0c0 7-3 7-3 9h18c0-2-3-2-3-9ZM10 21h4" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round"/></svg>
                <span class="seller-topbar-badge" data-topbar-notifications-badge hidden></span>
            </button>
            <section class="seller-topbar-popover" id="sellerTopbarNotifications" aria-label="<?= $topbarFilipino ? 'Mga update' : 'Notifications' ?>" hidden>
                <div class="seller-topbar-popover-heading">
                    <h2 data-topbar-notifications-title><?= $topbarFilipino ? 'Mga update' : 'Notifications' ?></h2>
                    <button type="button" class="seller-topbar-icon" data-topbar-notifications-close aria-label="Close notifications">&times;</button>
                </div>
                <p class="seller-topbar-description" data-topbar-description><?= $topbarFilipino ? 'Mga offer at results na kailangan mong tingnan.' : 'Offers and results that need your attention.' ?></p>
                <p class="seller-topbar-state" data-topbar-state role="status" aria-live="polite"></p>
                <div class="seller-topbar-update-list" data-topbar-updates></div>
                <button type="button" class="seller-topbar-retry" data-topbar-retry hidden><?= $topbarFilipino ? 'Subukan ulit' : 'Try again' ?></button>
            </section>
        </div>
        <?php $floatingMessagesPlacement = 'seller-topbar'; require __DIR__ . '/floating-messages.php'; unset($floatingMessagesPlacement); ?>
        <a class="seller-topbar-profile" href="?page=profile" data-topbar-profile aria-label="<?= $topbarFilipino ? 'Buksan ang profile' : 'Open profile' ?>">
            <span class="seller-topbar-avatar">
                <span data-topbar-initial aria-hidden="true"><?= $topbarEscape(strtoupper(substr($topbarProfile['full_name'], 0, 1))) ?></span>
                <img data-topbar-avatar <?= $topbarProfile['avatar_url'] !== '' ? 'src="' . $topbarEscape($topbarProfile['avatar_url']) . '"' : 'hidden' ?> alt="" width="48" height="48" referrerpolicy="no-referrer">
            </span>
            <span class="seller-topbar-profile-name" data-topbar-name><?= $topbarEscape($topbarProfile['full_name']) ?></span>
        </a>
    </nav>
</header>
