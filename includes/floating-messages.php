<?php

require_once __DIR__ . '/../backend/chat_scaledrone.php';

$floatingMessagesCsrfToken = generateCsrfToken();
$floatingMessagesScaledroneConfig = chatScaledroneConfig();

try {
    $floatingMessagesActor = chatGetCurrentActor();
} catch (ChatHttpException $e) {
    ?>
    <div class="floating-messages-unavailable" role="status">
        Messages unavailable: <?= htmlspecialchars($e->getMessage(), ENT_QUOTES, 'UTF-8') ?>
    </div>
    <?php
    return;
}
?>
<div
    class="floating-messages-root"
    data-placement="<?= ($floatingMessagesPlacement ?? '') === 'seller-topbar' && ($floatingMessagesActor['actor_type'] ?? '') === 'seller' ? 'seller-topbar' : 'floating' ?>"
    data-floating-messages
    data-actor-type="<?= htmlspecialchars($floatingMessagesActor['actor_type'], ENT_QUOTES, 'UTF-8') ?>"
    data-actor-id="<?= (int) $floatingMessagesActor['actor_id'] ?>"
    data-channel-id="<?= htmlspecialchars($floatingMessagesScaledroneConfig['channel_id'] ?? '', ENT_QUOTES, 'UTF-8') ?>"
    data-csrf-token="<?= htmlspecialchars($floatingMessagesCsrfToken, ENT_QUOTES, 'UTF-8') ?>"
>
    <section
        class="floating-messages-panel"
        id="floating-messages-panel"
        data-floating-messages-panel
        aria-label="Messages"
        aria-hidden="true"
        inert
        hidden
    >
        <header class="floating-messages-panel-header">
            <div>
                <span>VNZ TECH · MESSAGES</span>
                <h2 data-floating-messages-title>Conversations</h2>
            </div>
            <button type="button" data-floating-messages-close aria-label="Close conversations">×</button>
        </header>
        <div class="floating-messages-list" data-floating-messages-list>
            <p class="floating-messages-list-state">Loading conversations…</p>
        </div>
        <p class="floating-messages-placeholder" data-floating-messages-placeholder hidden></p>
        <div class="floating-messages-error" data-floating-messages-error role="status" hidden></div>
    </section>

    <div
        class="floating-messages-categories"
        id="floating-messages-categories"
        data-floating-messages-categories
        aria-label="Message categories"
        aria-hidden="true"
    >
        <button
            class="floating-messages-category"
            type="button"
            data-message-category="global"
            aria-label="Global Chat"
            aria-describedby="floating-messages-tooltip-global"
            tabindex="-1"
        >
            <svg viewBox="0 0 24 24" aria-hidden="true">
                <circle cx="12" cy="12" r="9"></circle>
                <path d="M3.5 12h17M12 3c2.2 2.4 3.3 5.4 3.3 9s-1.1 6.6-3.3 9c-2.2-2.4-3.3-5.4-3.3-9S9.8 5.4 12 3Z"></path>
            </svg>
            <span
                class="floating-messages-category-badge"
                data-category-unread-badge="global"
                aria-hidden="true"
                hidden
            ></span>
            <span class="floating-messages-tooltip" id="floating-messages-tooltip-global" aria-hidden="true">Global Chat</span>
        </button>
        <button
            class="floating-messages-category"
            type="button"
            data-message-category="private"
            aria-label="Private Chat"
            aria-describedby="floating-messages-tooltip-private"
            tabindex="-1"
        >
            <svg viewBox="0 0 24 24" aria-hidden="true">
                <circle cx="12" cy="8" r="3.5"></circle>
                <path d="M5 20c.5-3.5 3.2-5.5 7-5.5s6.5 2 7 5.5"></path>
            </svg>
            <span
                class="floating-messages-category-badge"
                data-category-unread-badge="private"
                aria-hidden="true"
                hidden
            ></span>
            <span class="floating-messages-tooltip" id="floating-messages-tooltip-private" aria-hidden="true">Private Chat</span>
        </button>
        <?php if ($floatingMessagesActor['actor_type'] === 'seller'): ?>
            <button
                class="floating-messages-category"
                type="button"
                data-message-category="contacts"
                aria-label="Admin"
                aria-describedby="floating-messages-tooltip-contacts"
                tabindex="-1"
            >
                <svg viewBox="0 0 24 24" aria-hidden="true">
                    <path d="M14.5 6.5a4 4 0 0 0-5.2 5.2L4 17l3 3 5.3-5.3a4 4 0 0 0 5.2-5.2l-2.6 2.6-2.8-2.8 2.4-2.8Z"></path>
                    <path d="m4 4 2.2.4L7 6.6 5.4 8.2 3.2 7.4 2.8 5.2 4 4Z"></path>
                </svg>
                <span
                    class="floating-messages-category-badge"
                    data-category-unread-badge="contacts"
                    aria-hidden="true"
                    hidden
                ></span>
                <span class="floating-messages-tooltip" id="floating-messages-tooltip-contacts" aria-hidden="true">Admin</span>
            </button>
        <?php else: ?>
            <button
                class="floating-messages-category"
                type="button"
                data-message-category="contacts"
                aria-label="Sellers"
                aria-describedby="floating-messages-tooltip-contacts"
                tabindex="-1"
            >
                <svg viewBox="0 0 24 24" aria-hidden="true">
                    <circle cx="9" cy="8" r="3"></circle>
                    <circle cx="17" cy="9" r="2.3"></circle>
                    <path d="M3.5 19c.4-3.2 2.4-5 5.5-5s5.1 1.8 5.5 5M14.5 14.5c3.7-.7 5.8.9 6 3.5"></path>
                </svg>
                <span
                    class="floating-messages-category-badge"
                    data-category-unread-badge="contacts"
                    aria-hidden="true"
                    hidden
                ></span>
                <span class="floating-messages-tooltip" id="floating-messages-tooltip-contacts" aria-hidden="true">Sellers</span>
            </button>
        <?php endif; ?>
        <?php if ($floatingMessagesActor['actor_type'] === 'admin'): ?>
            <button
                class="floating-messages-category"
                type="button"
                data-message-category="guest-sellers"
                aria-label="Guest Sellers"
                aria-describedby="floating-messages-tooltip-guest-sellers"
                tabindex="-1"
            >
                <svg viewBox="0 0 24 24" aria-hidden="true">
                    <circle cx="8" cy="8" r="3"></circle>
                    <path d="M2.5 19c.4-3.4 2.3-5.2 5.5-5.2s5.1 1.8 5.5 5.2"></path>
                    <path d="M17 4.5v6m-3-3h6"></path>
                </svg>
                <span
                    class="floating-messages-category-badge"
                    data-category-unread-badge="guest-sellers"
                    aria-hidden="true"
                    hidden
                ></span>
                <span class="floating-messages-tooltip" id="floating-messages-tooltip-guest-sellers" aria-hidden="true">Guest Sellers</span>
            </button>
        <?php endif; ?>
        <?php if (
            $floatingMessagesActor['actor_type'] === 'seller'
            || hasPermission('manage_trades')
        ): ?>
            <button
                class="floating-messages-category"
                type="button"
                data-message-category="trade-requests"
                aria-label="Trade Requests"
                aria-describedby="floating-messages-tooltip-trade-requests"
                tabindex="-1"
            >
                <svg viewBox="0 0 24 24" aria-hidden="true">
                    <path d="M3 7.5 12 3l9 4.5v9L12 21l-9-4.5v-9Z"></path>
                    <path d="m3.5 7.8 8.5 4.4 8.5-4.4M12 12.2V21"></path>
                </svg>
                <span
                    class="floating-messages-category-badge"
                    data-category-unread-badge="trade-requests"
                    aria-hidden="true"
                    hidden
                ></span>
                <span class="floating-messages-tooltip" id="floating-messages-tooltip-trade-requests" aria-hidden="true">Trade Requests</span>
            </button>
        <?php endif; ?>
    </div>

    <button
        class="floating-messages-launcher"
        type="button"
        data-floating-messages-toggle
        aria-label="Messages"
        aria-expanded="false"
        aria-controls="floating-messages-categories floating-messages-panel"
    >
        <svg viewBox="0 0 24 24" aria-hidden="true">
            <path d="M20.5 11.5a8.5 8.5 0 0 1-12.7 7.4L3 20l1.2-4.1A8.5 8.5 0 1 1 20.5 11.5Z"></path>
            <path d="M8 11.5h.01M12 11.5h.01M16 11.5h.01"></path>
        </svg>
        <span class="floating-messages-badge" data-floating-messages-badge hidden></span>
    </button>

    <div class="floating-chat-windows" data-floating-chat-windows></div>
    <div data-call-owner-host hidden aria-hidden="true"></div>
</div>
