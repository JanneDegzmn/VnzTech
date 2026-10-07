<?php

require_once __DIR__ . '/../backend/csrf.php';
require_once __DIR__ . '/../backend/chat_scaledrone.php';

try {
    $transactionChatActor = chatGetCurrentActor();
} catch (ChatHttpException $e) {
    ?>
    <section class="transaction-chat-unavailable" role="alert">
        <span class="transaction-chat-unavailable-label">CHAT UNAVAILABLE</span>
        <strong>We could not verify this session for transaction chat.</strong>
        <p><?= htmlspecialchars($e->getMessage(), ENT_QUOTES, 'UTF-8') ?></p>
    </section>
    <?php
    return;
}

$transactionChatConfig = chatScaledroneConfig();
$transactionChatChannelId = $transactionChatConfig['channel_id'] ?? '';
$transactionChatCsrfToken = generateCsrfToken();
$transactionChatConversationType = $transactionChatConversationType ?? 'transaction';
$transactionChatConversationDbId = $transactionChatConversationDbId ?? 0;
$transactionChatDisplayName = $transactionChatDisplayName ?? (string) $transactionChatReference;
$transactionChatHasContext = $transactionChatConversationType === 'transaction';
$transactionChatInputId = $transactionChatHasContext
    ? 'transaction-' . (int) $transactionChatId
    : 'conversation-' . (int) $transactionChatConversationDbId;
$transactionChatItems = $transactionChatSubmissionItems ?? [];
$transactionChatPhotos = $transactionChatPhotosByItem ?? [];
$transactionChatPhoto = null;
$transactionChatTotalQuantity = 0;

foreach ($transactionChatItems as $transactionChatItem) {
    $transactionChatTotalQuantity += (int) ($transactionChatItem['quantity'] ?? 0);
    $itemPhotos = $transactionChatPhotos[(int) ($transactionChatItem['id'] ?? 0)] ?? [];
    if (!$transactionChatPhoto && $itemPhotos) {
        $transactionChatPhoto = $itemPhotos[0];
    }
}

?>
<section
    class="transaction-chat"
    data-transaction-chat
    data-conversation-type="<?= htmlspecialchars($transactionChatConversationType, ENT_QUOTES, 'UTF-8') ?>"
    data-conversation-id="<?= (int) $transactionChatConversationDbId ?>"
    data-transaction-id="<?= (int) $transactionChatId ?>"
    data-actor-type="<?= htmlspecialchars(
        $transactionChatActor['actor_type'],
        ENT_QUOTES,
        'UTF-8'
    ) ?>"
    data-actor-id="<?= (int) $transactionChatActor['actor_id'] ?>"
    data-transaction-reference="<?= htmlspecialchars(
        (string) $transactionChatReference,
        ENT_QUOTES,
        'UTF-8'
    ) ?>"
    data-conversation-title="<?= htmlspecialchars(
        $transactionChatDisplayName,
        ENT_QUOTES,
        'UTF-8'
    ) ?>"
    data-channel-id="<?= htmlspecialchars(
        $transactionChatChannelId,
        ENT_QUOTES,
        'UTF-8'
    ) ?>"
    data-csrf-token="<?= htmlspecialchars(
        $transactionChatCsrfToken,
        ENT_QUOTES,
        'UTF-8'
    ) ?>"
>
    <header class="transaction-chat-header">
        <div>
            <span class="transaction-chat-eyebrow">
                <?= $transactionChatHasContext
                    ? 'TRANSACTION MESSAGES'
                    : htmlspecialchars(strtoupper($transactionChatConversationType) . ' CHAT', ENT_QUOTES, 'UTF-8')
                ?>
            </span>
            <h2><?= htmlspecialchars(
                $transactionChatHasContext ? 'Chat about this transaction' : $transactionChatDisplayName,
                ENT_QUOTES,
                'UTF-8'
            ) ?></h2>
            <?php if ($transactionChatHasContext): ?>
                <p><?= htmlspecialchars((string) $transactionChatReference, ENT_QUOTES, 'UTF-8') ?></p>
            <?php endif; ?>
        </div>
        <span class="transaction-chat-connection" data-chat-connection>
            <span class="transaction-chat-connection-dot" aria-hidden="true"></span>
            <span data-chat-connection-label>Connecting</span>
        </span>
    </header>

    <?php if ($transactionChatHasContext): ?>
    <section class="transaction-chat-info" aria-label="Transaction information">
        <div class="transaction-chat-info-heading">
            <span class="transaction-chat-eyebrow">INFO</span>
            <span class="transaction-chat-info-quantity">
                <?= number_format($transactionChatTotalQuantity) ?> pcs
            </span>
        </div>

        <div class="transaction-chat-info-body">
            <?php if ($transactionChatPhoto): ?>
                <img
                    class="transaction-chat-info-photo"
                    src="<?= htmlspecialchars((string) $transactionChatPhoto['file_path'], ENT_QUOTES, 'UTF-8') ?>"
                    alt="<?= htmlspecialchars((string) ($transactionChatPhoto['original_name'] ?: 'Submission photo'), ENT_QUOTES, 'UTF-8') ?>"
                    loading="lazy"
                >
            <?php else: ?>
                <div class="transaction-chat-info-photo-placeholder" aria-label="No submission photo available">
                    No photo
                </div>
            <?php endif; ?>

            <div class="transaction-chat-info-details">
                <div class="transaction-chat-info-items">
                    <?php foreach ($transactionChatItems as $transactionChatItem): ?>
                        <?php
                        $itemType = (string) ($transactionChatItem['item_type'] ?? '');
                        $itemNameParts = array_filter([
                            $transactionChatItem['brand'] ?? '',
                            $transactionChatItem['model'] ?? '',
                            $transactionChatItem['storage'] ?? '',
                            $transactionChatItem['board_code'] ?? '',
                            $transactionChatItem['issue'] ?? '',
                            $transactionChatItem['issue_details'] ?? ''
                        ], static function ($value): bool {
                            return trim((string) $value) !== '';
                        });
                        $itemName = $itemNameParts
                            ? implode(' ', array_map('strval', $itemNameParts))
                            : ucwords(str_replace('_', ' ', $itemType ?: 'Submitted item'));
                        ?>
                        <div class="transaction-chat-info-item">
                            <span>
                                Item:
                                <?= htmlspecialchars($itemName, ENT_QUOTES, 'UTF-8') ?>
                            </span>
                            <strong>
                                <?= number_format((int) ($transactionChatItem['quantity'] ?? 0)) ?> pcs
                            </strong>
                        </div>
                    <?php endforeach; ?>
                </div>

                <dl class="transaction-chat-info-meta">
                    <div>
                        <dt><?= isset($transactionChatOfferAmount) && $transactionChatOfferAmount !== null ? 'Offer' : 'Accepted amount' ?></dt>
                        <dd>₱<?= number_format((float) ($transactionChatOfferAmount ?? $transactionChatFinalAmount ?? 0), 2) ?></dd>
                    </div>
                    <div>
                        <dt>Submission</dt>
                        <dd><?= htmlspecialchars((string) ($transactionChatSubmissionReference ?? '—'), ENT_QUOTES, 'UTF-8') ?></dd>
                    </div>
                    <div>
                        <dt>Transaction</dt>
                        <dd><?= htmlspecialchars((string) $transactionChatReference, ENT_QUOTES, 'UTF-8') ?></dd>
                    </div>
                    <div>
                        <dt>Transaction status</dt>
                        <dd><?= htmlspecialchars(ucwords(str_replace('_', ' ', (string) ($transactionChatStatus ?? 'unknown'))), ENT_QUOTES, 'UTF-8') ?></dd>
                    </div>
                    <div>
                        <dt>Submission status</dt>
                        <dd><?= htmlspecialchars(ucwords(str_replace('_', ' ', (string) ($transactionChatSubmissionStatus ?? 'unknown'))), ENT_QUOTES, 'UTF-8') ?></dd>
                    </div>
                    <div>
                        <dt>Payment status</dt>
                        <dd><?= htmlspecialchars(ucwords(str_replace('_', ' ', (string) ($transactionChatPaymentStatus ?? 'unknown'))), ENT_QUOTES, 'UTF-8') ?></dd>
                    </div>
                </dl>
            </div>
        </div>
    </section>
    <?php endif; ?>

    <div
        class="transaction-chat-status"
        data-chat-status
        role="status"
        aria-live="polite"
    >Loading conversation…</div>

    <div
        class="transaction-chat-messages"
        data-chat-messages
        role="log"
        aria-label="Transaction messages"
        aria-live="polite"
        aria-relevant="additions text"
    >
        <div class="transaction-chat-empty" data-chat-empty>
            <span class="transaction-chat-empty-icon" aria-hidden="true">↗</span>
            <strong>No messages yet</strong>
            <span><?= $transactionChatHasContext
                ? 'Messages about this transaction will appear here.'
                : 'Start the conversation with a message.'
            ?></span>
        </div>
    </div>

    <div class="transaction-chat-history-controls">
        <button type="button" data-chat-load-older hidden>Load earlier messages</button>
    </div>

    <form class="transaction-chat-composer" data-chat-form>
        <label class="visually-hidden" for="transaction-chat-input-<?= htmlspecialchars($transactionChatInputId, ENT_QUOTES, 'UTF-8') ?>">
            Write a message
        </label>
        <textarea
            id="transaction-chat-input-<?= htmlspecialchars($transactionChatInputId, ENT_QUOTES, 'UTF-8') ?>"
            data-chat-input
            name="content"
            rows="2"
            maxlength="4000"
            placeholder="Write a message…"
            autocomplete="off"
            required
            disabled
        ></textarea>
        <div class="transaction-chat-composer-footer">
            <span>Text messages · up to 4,000 characters</span>
            <button type="submit" data-chat-send disabled>
                <span>Send</span>
                <span aria-hidden="true">↗</span>
            </button>
        </div>
    </form>
</section>
