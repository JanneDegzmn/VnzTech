<?php

require_once __DIR__ . '/../backend/csrf.php';
require_once __DIR__ . '/../backend/chat_scaledrone.php';
require_once __DIR__ . '/../backend/chat_attachments.php';

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
$transactionChatConversationClass = $transactionChatConversationClass ?? 'private';
$transactionChatConversationDbId = $transactionChatConversationDbId ?? 0;
$transactionChatDisplayName = $transactionChatDisplayName ?? (string) $transactionChatReference;
$transactionChatHasContext = $transactionChatConversationType === 'transaction'
    || !empty($transactionChatShowTransactionContext);
$transactionChatGuestSubmission = $transactionChatGuestSubmission ?? false;
$transactionChatCallsEnabled = $transactionChatActor['actor_type'] === 'seller'
    && !$transactionChatGuestSubmission
    && $transactionChatConversationClass === 'private'
    && $transactionChatConversationType === 'private';
$transactionChatCallPeer = null;
if ($transactionChatCallsEnabled && (int) $transactionChatConversationDbId > 0) {
    try {
        $transactionChatCallPeer = chatGetOneToOneConversationPeer(
            (int) $transactionChatConversationDbId,
            $transactionChatActor
        )['peer'];
    } catch (ChatHttpException $e) {
        $transactionChatCallsEnabled = false;
    }
}
$transactionChatIceServers = $transactionChatCallsEnabled
    ? chatWebRtcIceServers()
    : [];
$transactionChatInputId = $transactionChatGuestSubmission
    ? 'conversation-' . (int) $transactionChatConversationDbId
    : ($transactionChatHasContext
        ? 'transaction-' . (int) $transactionChatId
        : 'conversation-' . (int) $transactionChatConversationDbId);
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
    data-conversation-class="<?= htmlspecialchars($transactionChatConversationClass, ENT_QUOTES, 'UTF-8') ?>"
    data-conversation-id="<?= (int) $transactionChatConversationDbId ?>"
    data-transaction-id="<?= (int) $transactionChatId ?>"
    data-actor-type="<?= htmlspecialchars(
        $transactionChatActor['actor_type'],
        ENT_QUOTES,
        'UTF-8'
    ) ?>"
    data-actor-id="<?= (int) $transactionChatActor['actor_id'] ?>"
    data-actor-display-name="<?= htmlspecialchars(
        $transactionChatActor['display_name'],
        ENT_QUOTES,
        'UTF-8'
    ) ?>"
    data-actor-profile-picture="<?= htmlspecialchars(
        $transactionChatActor['profile_picture'],
        ENT_QUOTES,
        'UTF-8'
    ) ?>"
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
    data-guest-submission-chat="<?= $transactionChatGuestSubmission ? 'true' : 'false' ?>"
    data-conversation-peer-name="<?= htmlspecialchars(
        (string) ($transactionChatCallPeer['display_name'] ?? ''),
        ENT_QUOTES,
        'UTF-8'
    ) ?>"
    data-conversation-peer-picture="<?= htmlspecialchars(
        (string) ($transactionChatCallPeer['profile_picture'] ?? ''),
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
    data-attachment-max-bytes="<?= chatAttachmentMaxBytes() ?>"
    data-document-max-bytes="<?= chatDocumentMaxBytes() ?>"
    data-voice-max-bytes="<?= chatVoiceMaxBytes() ?>"
    data-video-max-bytes="<?= chatVideoMaxBytes() ?>"
    data-voice-max-duration-seconds="<?= chatVoiceMaxDurationSeconds() ?>"
    data-call-enabled="<?= $transactionChatCallsEnabled ? 'true' : 'false' ?>"
    data-call-ice-servers="<?= htmlspecialchars(
        json_encode($transactionChatIceServers, JSON_THROW_ON_ERROR),
        ENT_QUOTES,
        'UTF-8'
    ) ?>"
>
    <header class="transaction-chat-header">
        <div>
            <span class="transaction-chat-eyebrow">
                <?= $transactionChatHasContext
                    ? ($transactionChatGuestSubmission ? 'SUBMISSION MESSAGES' : 'TRANSACTION MESSAGES')
                    : htmlspecialchars(strtoupper($transactionChatConversationType) . ' CHAT', ENT_QUOTES, 'UTF-8')
                ?>
            </span>
            <h2><?= htmlspecialchars(
                $transactionChatHasContext
                    ? ($transactionChatGuestSubmission
                        ? 'Chat about this submission'
                        : 'Chat about this transaction')
                    : $transactionChatDisplayName,
                ENT_QUOTES,
                'UTF-8'
            ) ?></h2>
            <?php if ($transactionChatHasContext): ?>
                <p><?= htmlspecialchars((string) $transactionChatReference, ENT_QUOTES, 'UTF-8') ?></p>
            <?php endif; ?>
            <p class="transaction-chat-presence-label" data-chat-presence>Presence unavailable</p>
        </div>
        <div class="transaction-chat-header-actions">
            <?php if (
                $transactionChatGuestSubmission
                && $transactionChatActor['actor_type'] === 'admin'
                && hasPermission('manage_offers')
            ): ?>
                <button
                    type="button"
                    class="transaction-chat-revoke-guest"
                    data-chat-revoke-guest
                    aria-label="Revoke guest access to this conversation"
                >Revoke guest access</button>
            <?php endif; ?>
            <?php if ($transactionChatCallsEnabled): ?>
                <button type="button" class="transaction-chat-call-button" data-chat-call-audio>
                    <span aria-hidden="true">☎</span> Audio call
                </button>
                <button type="button" class="transaction-chat-call-button" data-chat-call-video>
                    <span aria-hidden="true">▣</span> Video call
                </button>
            <?php endif; ?>
            <span class="transaction-chat-connection" data-chat-connection>
                <span class="transaction-chat-connection-dot" aria-hidden="true"></span>
                <span data-chat-connection-label>Connecting</span>
            </span>
        </div>
    </header>

    <?php if ($transactionChatCallsEnabled): ?>
    <section
        class="transaction-chat-call-panel"
        data-chat-call-panel
        role="dialog"
        aria-modal="false"
        aria-label="Voice and video call"
        hidden
    >
        <div class="transaction-chat-call-card">
            <header class="transaction-chat-call-header" data-chat-call-drag-handle>
                <div class="transaction-chat-call-heading">
                    <strong class="transaction-chat-call-title" data-chat-call-title>Call</strong>
                    <span class="transaction-chat-call-kind" data-chat-call-kind></span>
                </div>
                <div class="transaction-chat-call-window-actions">
                    <button type="button" data-chat-call-maximize aria-label="Maximize call window" title="Maximize call window">□</button>
                    <button type="button" data-chat-call-minimize aria-label="Minimize call" title="Minimize call">—</button>
                    <button type="button" data-chat-call-close aria-label="End call" title="End call">×</button>
                </div>
            </header>
            <div class="transaction-chat-call-body">
                <div class="transaction-chat-call-details">
                    <div class="transaction-chat-call-avatar" data-chat-call-avatar></div>
                    <span class="transaction-chat-call-status" data-chat-call-status>Ready</span>
                    <audio data-chat-call-remote-audio autoplay hidden></audio>
                    <div class="transaction-chat-call-controls">
                        <button type="button" class="is-accept-call" data-chat-call-accept hidden><span aria-hidden="true">✓</span> Accept</button>
                        <button type="button" class="is-decline-call" data-chat-call-decline hidden><span aria-hidden="true">✕</span> Decline</button>
                        <button type="button" data-chat-call-cancel hidden>Cancel call</button>
                        <button type="button" data-chat-call-sound hidden>Enable sound</button>
                        <button type="button" data-chat-call-mute hidden>Mute microphone</button>
                        <button type="button" data-chat-call-camera hidden>Turn camera off</button>
                        <button type="button" class="is-end-call" data-chat-call-end hidden>End call</button>
                    </div>
                </div>
                <div class="transaction-chat-call-media" data-chat-call-media hidden>
                    <div class="transaction-chat-call-video-placeholder" data-chat-call-video-placeholder>
                        Video will appear when the participant's camera connects.
                    </div>
                    <video
                        class="transaction-chat-call-remote-video"
                        data-chat-call-remote-video
                        autoplay
                        playsinline
                        aria-label="Other participant video"
                    ></video>
                    <video
                        class="transaction-chat-call-local-video"
                        data-chat-call-local-video
                        autoplay
                        muted
                        playsinline
                        aria-label="Your camera preview"
                    ></video>
                </div>
            </div>
        </div>
        <span class="transaction-chat-call-resize-handle is-n" data-chat-call-resize="n" aria-hidden="true"></span>
        <span class="transaction-chat-call-resize-handle is-ne" data-chat-call-resize="ne" aria-hidden="true"></span>
        <span class="transaction-chat-call-resize-handle is-e" data-chat-call-resize="e" aria-hidden="true"></span>
        <span class="transaction-chat-call-resize-handle is-se" data-chat-call-resize="se" aria-hidden="true"></span>
        <span class="transaction-chat-call-resize-handle is-s" data-chat-call-resize="s" aria-hidden="true"></span>
        <span class="transaction-chat-call-resize-handle is-sw" data-chat-call-resize="sw" aria-hidden="true"></span>
        <span class="transaction-chat-call-resize-handle is-w" data-chat-call-resize="w" aria-hidden="true"></span>
        <span class="transaction-chat-call-resize-handle is-nw" data-chat-call-resize="nw" aria-hidden="true"></span>
    </section>
    <button type="button" class="transaction-chat-call-indicator" data-chat-call-indicator hidden>
        <span class="transaction-chat-call-indicator-dot" aria-hidden="true"></span>
        <span class="transaction-chat-call-indicator-name" data-chat-call-indicator-name>Active call</span>
        <time data-chat-call-indicator-duration>00:00</time>
        <span class="transaction-chat-call-indicator-restore" aria-hidden="true">↗</span>
    </button>
    <?php endif; ?>

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

    <div class="transaction-chat-message-area">
        <div
            class="transaction-chat-messages"
            data-chat-messages
            role="log"
            aria-label="Transaction messages"
            aria-live="polite"
            aria-relevant="additions text"
        >
            <div class="transaction-chat-history-controls">
                <button type="button" data-chat-load-older hidden>Load earlier messages</button>
            </div>
            <div class="transaction-chat-empty" data-chat-empty>
                <span class="transaction-chat-empty-icon" aria-hidden="true">↗</span>
                <strong>No messages yet</strong>
                <span><?= $transactionChatHasContext
                    ? 'Messages about this transaction will appear here.'
                    : 'Start the conversation with a message.'
                ?></span>
            </div>
        </div>
        <button
            class="transaction-chat-scroll-latest"
            type="button"
            data-chat-scroll-latest
            aria-label="Scroll to latest messages"
            title="Scroll to latest messages"
            hidden
        >
            <svg viewBox="0 0 24 24" aria-hidden="true" focusable="false">
                <path d="M12 4v15m-6-6 6 6 6-6" />
            </svg>
        </button>
    </div>

    <form class="transaction-chat-composer" data-chat-form>
        <label class="visually-hidden" for="transaction-chat-input-<?= htmlspecialchars($transactionChatInputId, ENT_QUOTES, 'UTF-8') ?>">
            Write a message
        </label>
        <div class="transaction-chat-composer-input-row">
            <button
                type="button"
                class="transaction-chat-emoji-toggle"
                data-chat-emoji-toggle
                aria-label="Choose emoji"
                aria-expanded="false"
                disabled
            >😀</button>
            <button
                type="button"
                class="transaction-chat-attachment-toggle"
                data-chat-attachment-toggle
                aria-label="Attach an image, video, or PDF"
                title="Attach an image, video, or PDF"
                disabled
            >＋</button>
            <input
                type="file"
                data-chat-attachment-input
                accept="image/jpeg,image/png,image/webp,application/pdf,video/mp4,video/webm,.pdf,.mp4,.webm"
                hidden
            >
            <button
                type="button"
                class="transaction-chat-voice-toggle"
                data-chat-voice-toggle
                aria-label="Record a voice message"
                title="Record a voice message"
                disabled
            >🎙</button>
            <textarea
                id="transaction-chat-input-<?= htmlspecialchars($transactionChatInputId, ENT_QUOTES, 'UTF-8') ?>"
                data-chat-input
                name="content"
                rows="2"
                maxlength="4000"
                placeholder="Write a message…"
                autocomplete="off"
                disabled
            ></textarea>
        </div>
        <section class="transaction-chat-voice-composer" data-chat-voice-composer hidden>
            <span class="transaction-chat-voice-recording-status" data-chat-voice-status aria-live="polite">
                Ready to record
            </span>
            <audio data-chat-voice-preview controls preload="metadata" hidden></audio>
            <div class="transaction-chat-voice-actions">
                <button type="button" data-chat-voice-cancel>Cancel</button>
                <button type="button" data-chat-voice-send hidden>Send voice message</button>
            </div>
        </section>
        <div class="transaction-chat-attachment-preview" data-chat-attachment-preview hidden>
            <img data-chat-attachment-preview-image alt="Selected image preview" hidden>
            <video data-chat-attachment-preview-video controls playsinline preload="metadata" hidden></video>
            <span data-chat-attachment-name></span>
            <button
                type="button"
                data-chat-attachment-remove
                aria-label="Remove selected attachment"
            >×</button>
        </div>
        <div class="transaction-chat-upload-progress" data-chat-upload-progress hidden>
            <span data-chat-upload-progress-bar></span>
        </div>
        <div class="transaction-chat-composer-footer">
            <span>
                Up to 4,000 characters
                <?= $transactionChatActor['actor_type'] === 'admin'
                    ? ' · /bookme shares Lalamove drop-off details'
                    : ''
                ?>
            </span>
            <button type="submit" data-chat-send disabled>
                <span>Send</span>
                <span aria-hidden="true">↗</span>
            </button>
        </div>
        <div class="transaction-chat-upload-error" data-chat-upload-error role="status" aria-live="polite" hidden></div>
    </form>
</section>
