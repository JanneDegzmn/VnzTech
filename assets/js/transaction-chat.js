(() => {
    const activeChats = new Map();
    let scaledroneLibraryPromise = null;

    function loadScaledroneLibrary() {
        if (typeof window.Scaledrone === 'function') {
            return Promise.resolve();
        }

        if (!scaledroneLibraryPromise) {
            scaledroneLibraryPromise = new Promise((resolve, reject) => {
                const script = document.createElement('script');
                script.src = 'https://cdn.scaledrone.com/scaledrone.min.js';
                script.async = true;
                script.onload = resolve;
                script.onerror = () => {
                    scaledroneLibraryPromise = null;
                    reject(new Error('Realtime client library could not be loaded.'));
                };
                document.head.appendChild(script);
            });
        }

        return scaledroneLibraryPromise;
    }

    async function readJsonResponse(response) {
        let payload;
        try {
            payload = await response.json();
        } catch (error) {
            throw new Error('The server returned an invalid response.');
        }

        if (!response.ok || payload.success !== true) {
            const error = new Error(
                payload.message || `Request failed (HTTP ${response.status}).`
            );
            error.status = response.status;
            throw error;
        }

        return payload.data || {};
    }

    async function postForm(url, fields) {
        const response = await fetch(url, {
            method: 'POST',
            credentials: 'same-origin',
            cache: 'no-store',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8'
            },
            body: new URLSearchParams(fields)
        });

        return readJsonResponse(response);
    }

    async function getJson(url) {
        const response = await fetch(url, {
            method: 'GET',
            credentials: 'same-origin',
            cache: 'no-store'
        });

        return readJsonResponse(response);
    }

    function toTimestamp(value) {
        const parsed = new Date(String(value).replace(' ', 'T'));
        return Number.isNaN(parsed.getTime()) ? null : parsed;
    }

    function formatTimestamp(value) {
        const date = toTimestamp(value);
        if (!date) {
            return String(value || '');
        }

        return new Intl.DateTimeFormat(undefined, {
            dateStyle: 'medium',
            timeStyle: 'short'
        }).format(date);
    }

    function compareMessages(left, right) {
        const leftTime = toTimestamp(left.created_at)?.getTime() || 0;
        const rightTime = toTimestamp(right.created_at)?.getTime() || 0;
        return leftTime - rightTime || Number(left.id) - Number(right.id);
    }

    function renderMessage(
        widget,
        message,
        currentActorType,
        currentActorId,
        conversationType
    ) {
        const row = document.createElement('article');
        const ownMessage = message.sender_type === currentActorType
            && Number(message.sender_id) === currentActorId;
        row.className = `transaction-chat-message${ownMessage ? ' is-own' : ''}`;
        row.dataset.messageId = String(message.id);

        const bubble = document.createElement('div');
        bubble.className = 'transaction-chat-bubble';

        const meta = document.createElement('div');
        meta.className = 'transaction-chat-message-meta';

        const sender = document.createElement('span');
        sender.className = 'transaction-chat-sender';
        if (conversationType === 'transaction') {
            sender.textContent = ownMessage
                ? `You · ${currentActorType === 'seller' ? 'Seller' : 'Admin'}`
                : (message.sender_type === 'seller' ? 'Seller' : 'Admin');
        } else {
            sender.textContent = ownMessage
                ? 'You'
                : String(
                    message.sender_display_name
                    || (message.sender_type === 'seller' ? 'Seller' : 'Admin')
                );
        }

        const time = document.createElement('time');
        time.dateTime = String(message.created_at || '');
        time.textContent = formatTimestamp(message.created_at);

        const content = document.createElement('p');
        content.className = 'transaction-chat-message-content';
        content.textContent = String(message.content || '');

        meta.append(sender, time);
        bubble.append(meta, content);
        row.append(bubble);
        return row;
    }

    function initChat(widget) {
        if (widget.dataset.chatInitialized === 'true') {
            return;
        }

        widget.dataset.chatInitialized = 'true';
        const transactionId = Number(widget.dataset.transactionId);
        const conversationType = widget.dataset.conversationType || 'transaction';
        const conversationTitle = widget.dataset.conversationTitle || 'Conversation';
        const channelId = widget.dataset.channelId || '';
        const csrfToken = widget.dataset.csrfToken || '';
        const currentActorType = widget.dataset.actorType || '';
        const currentActorId = Number(widget.dataset.actorId) || 0;
        const status = widget.querySelector('[data-chat-status]');
        const connection = widget.querySelector('[data-chat-connection]');
        const connectionLabel = widget.querySelector('[data-chat-connection-label]');
        const messageList = widget.querySelector('[data-chat-messages]');
        const emptyState = widget.querySelector('[data-chat-empty]');
        const form = widget.querySelector('[data-chat-form]');
        const input = widget.querySelector('[data-chat-input]');
        const sendButton = widget.querySelector('[data-chat-send]');
        const loadOlderButton = widget.querySelector('[data-chat-load-older]');
        const messageMap = new Map();
        let actualConversationId = Number(widget.dataset.conversationId) || 0;
        let drone = null;
        let roomSubscription = null;
        let oldestMessageCursor = null;
        let hasMoreMessages = false;
        let loadingHistory = false;
        let historyRefreshQueued = false;
        let sendingMessage = false;
        let sendOutcomeUnknown = false;
        let pendingAuthentication = null;
        let lastReadMessageId = 0;
        let readRequestInProgress = false;
        let queuedReadMessageId = 0;
        let disposed = false;

        function setStatus(message, kind = '') {
            status.textContent = message;
            status.classList.toggle('is-error', kind === 'error');
            status.classList.toggle('is-warning', kind === 'warning');
        }

        function setConnection(message, kind = '') {
            connectionLabel.textContent = message;
            connection.classList.toggle('is-connected', kind === 'connected');
            connection.classList.toggle('is-error', kind === 'error');
        }

        function isConversationViewed() {
            const floatingWindow = widget.closest('[data-floating-chat-window]');
            return !document.hidden
                && (!floatingWindow || (
                    !floatingWindow.hidden
                    && !floatingWindow.classList.contains('is-closing')
                    && !floatingWindow.classList.contains('is-minimized')
                ));
        }

        function notifyChatStateChanged() {
            window.dispatchEvent(new CustomEvent('vnz:chat-state-changed', {
                detail: {transactionId}
            }));
        }

        function shouldAutoScroll() {
            return messageList.scrollHeight
                - messageList.scrollTop
                - messageList.clientHeight < 100;
        }

        function renderMessages(autoScroll = false, preserveScroll = false) {
            const oldHeight = messageList.scrollHeight;
            const oldTop = messageList.scrollTop;
            const sorted = Array.from(messageMap.values()).sort(compareMessages);
            const fragment = document.createDocumentFragment();

            sorted.forEach(message => {
                fragment.append(renderMessage(
                    widget,
                    message,
                    currentActorType,
                    currentActorId,
                    conversationType
                ));
            });

            messageList.replaceChildren(fragment, emptyState);
            emptyState.hidden = sorted.length > 0;
            loadOlderButton.hidden = !hasMoreMessages;

            if (preserveScroll) {
                messageList.scrollTop = oldTop + (messageList.scrollHeight - oldHeight);
            } else if (autoScroll) {
                messageList.scrollTop = messageList.scrollHeight;
            }

            if (sorted.length && isConversationViewed()) {
                const newestId = Number(sorted[sorted.length - 1].id);
                if (newestId > lastReadMessageId) {
                    markRead(newestId);
                }
            }
        }

        function upsertMessages(messages, options = {}) {
            const shouldScroll = options.forceScroll === true || shouldAutoScroll();
            messages.forEach(message => {
                if (!message || !Number.isInteger(Number(message.id))) {
                    return;
                }
                messageMap.set(Number(message.id), message);
            });
            renderMessages(shouldScroll, options.preserveScroll === true);
        }

        async function markRead(messageId) {
            if (messageId > lastReadMessageId) {
                queuedReadMessageId = Math.max(queuedReadMessageId, messageId);
            }

            if (readRequestInProgress || disposed || !actualConversationId) {
                return;
            }

            readRequestInProgress = true;
            try {
                while (queuedReadMessageId > lastReadMessageId && !disposed) {
                    const targetMessageId = queuedReadMessageId;
                    queuedReadMessageId = 0;
                    const data = await postForm('/VNZ-TECH/backend/chat_messages.php', {
                        action: 'read',
                        conversation_id: String(actualConversationId),
                        message_id: String(targetMessageId),
                        csrf_token: csrfToken
                    });
                    lastReadMessageId =
                        Number(data.last_read_message_id) || targetMessageId;
                    notifyChatStateChanged();
                }
            } catch (error) {
                setStatus(`Messages loaded, but read status could not be updated: ${error.message}`, 'warning');
            } finally {
                readRequestInProgress = false;
                if (queuedReadMessageId > lastReadMessageId && !disposed) {
                    markRead(queuedReadMessageId);
                }
            }
        }

        async function loadHistory(beforeMessageId = null, options = {}) {
            if (disposed || !actualConversationId) {
                return;
            }
            if (loadingHistory) {
                if (beforeMessageId === null) {
                    historyRefreshQueued = true;
                }
                return;
            }

            loadingHistory = true;
            loadOlderButton.disabled = true;

            try {
                const params = new URLSearchParams({
                    action: 'history',
                    conversation_id: String(actualConversationId),
                    limit: '30'
                });
                if (beforeMessageId !== null) {
                    params.set('before_message_id', String(beforeMessageId));
                }

                const data = await getJson(`/VNZ-TECH/backend/chat_messages.php?${params.toString()}`);
                hasMoreMessages = data.has_more === true;
                oldestMessageCursor = data.next_cursor || oldestMessageCursor;
                upsertMessages(data.messages || [], {
                    forceScroll: options.forceScroll === true,
                    preserveScroll: options.preserveScroll === true
                });
                if (!data.messages?.length && messageMap.size === 0) {
                    emptyState.hidden = false;
                }
                loadOlderButton.hidden = !hasMoreMessages;
            } catch (error) {
                setStatus(`Unable to load message history: ${error.message}`, 'error');
            } finally {
                loadingHistory = false;
                loadOlderButton.disabled = false;
                if (historyRefreshQueued && !disposed) {
                    historyRefreshQueued = false;
                    loadHistory();
                }
            }
        }

        function subscribeAuthorizedRoom(roomName) {
            if (roomSubscription) {
                return Promise.resolve();
            }

            return new Promise((resolve, reject) => {
                const subscription = drone.subscribe(roomName);
                roomSubscription = subscription;

                subscription.on('open', error => {
                    if (error) {
                        roomSubscription = null;
                        reject(new Error('Could not subscribe to this conversation.'));
                        return;
                    }

                    subscription.on('message', event => {
                        const payload = event && event.data;
                        if (
                            payload
                            && payload.event === 'message.created'
                            && Number(payload.conversation_id) === actualConversationId
                        ) {
                            loadHistory().then(() => {
                                window.dispatchEvent(new CustomEvent('vnz:chat-message-received', {
                                    detail: {
                                        transactionId,
                                        conversationId: actualConversationId,
                                        conversationType
                                    }
                                }));
                            });
                        }
                    });
                    resolve();
                });
            });
        }

        async function authorizeAndSubscribe(clientId) {
            const authorization = await postForm(
                '/VNZ-TECH/backend/chat_scaledrone_auth.php',
                {
                    conversation_id: String(actualConversationId),
                    client_id: clientId,
                    csrf_token: csrfToken
                }
            );

            if (authorization.channel_id !== channelId) {
                throw new Error('Realtime channel configuration changed. Reload this page.');
            }

            await new Promise((resolve, reject) => {
                pendingAuthentication = {
                    resolve,
                    reject,
                    roomName: authorization.room
                };
                drone.authenticate(authorization.token);
            });
        }

        async function connectRealtime() {
            if (!channelId) {
                setConnection('Offline');
                setStatus('Realtime is not configured. Messages can still be loaded and sent.', 'warning');
                return;
            }

            try {
                setConnection('Connecting');
                await loadScaledroneLibrary();
                drone = new window.Scaledrone(channelId);

                drone.on('authenticate', error => {
                    const pending = pendingAuthentication;
                    if (!pending) {
                        return;
                    }
                    pendingAuthentication = null;

                    if (error) {
                        pending.reject(new Error('Realtime authentication was rejected.'));
                        return;
                    }

                    subscribeAuthorizedRoom(pending.roomName)
                        .then(pending.resolve)
                        .catch(pending.reject);
                });

                drone.on('open', async error => {
                    if (error || disposed) {
                        setConnection('Offline', 'error');
                        setStatus('Could not connect to realtime messaging. You can still use message history.', 'warning');
                        return;
                    }

                    try {
                        await authorizeAndSubscribe(drone.clientId);
                        setConnection('Connected', 'connected');
                        setStatus(`Connected to ${conversationTitle}.`);
                        input.disabled = false;
                        sendButton.disabled = false;
                    } catch (authError) {
                        setConnection('Unavailable', 'error');
                        setStatus(`Realtime authorization failed: ${authError.message}`, 'error');
                    }
                });

                drone.on('disconnect', () => {
                    setConnection('Reconnecting');
                });

                drone.on('reconnect', async () => {
                    if (disposed) {
                        return;
                    }
                    try {
                        roomSubscription = null;
                        await authorizeAndSubscribe(drone.clientId);
                        setConnection('Connected', 'connected');
                        setStatus('Realtime connection restored.');
                    } catch (error) {
                        setConnection('Unavailable', 'error');
                        setStatus(`Realtime reconnection failed: ${error.message}`, 'error');
                    }
                });

                drone.on('error', () => {
                    setConnection('Connection issue', 'error');
                });
            } catch (error) {
                setConnection('Unavailable', 'error');
                setStatus(`Realtime connection unavailable: ${error.message}`, 'warning');
            }
        }

        form.addEventListener('submit', async event => {
            event.preventDefault();
            const content = input.value.trim();

            if (!content || sendingMessage || sendOutcomeUnknown || !actualConversationId) {
                return;
            }

            sendingMessage = true;
            sendButton.disabled = true;
            setStatus('Sending message…');

            try {
                const data = await postForm('/VNZ-TECH/backend/chat_messages.php', {
                    action: 'send',
                    conversation_id: String(actualConversationId),
                    content,
                    message_type: 'text',
                    csrf_token: csrfToken
                });

                upsertMessages([data.message], {forceScroll: true});
                input.value = '';
                if (data.realtime_delivery === 'published') {
                    setStatus('Message sent.');
                } else {
                    setStatus(
                        `Message saved. Realtime delivery: ${String(data.realtime_delivery || 'unknown').replaceAll('_', ' ')}. The message remains in history.`,
                        'warning'
                    );
                }
            } catch (error) {
                if (Number.isInteger(error.status)) {
                    setStatus(`Message was not sent: ${error.message}`, 'error');
                } else {
                    sendOutcomeUnknown = true;
                    input.disabled = true;
                    setStatus(
                        `Message outcome is uncertain (${error.message}). Check message history before retrying.`,
                        'error'
                    );
                }
            } finally {
                sendingMessage = false;
                sendButton.disabled = sendOutcomeUnknown || !actualConversationId || input.disabled;
            }
        });

        loadOlderButton.addEventListener('click', () => {
            if (oldestMessageCursor !== null) {
                loadHistory(oldestMessageCursor, {preserveScroll: true});
            }
        });

        async function start() {
            if (!csrfToken || !currentActorType) {
                setStatus('Chat configuration is incomplete. Reload this page.', 'error');
                setConnection('Unavailable', 'error');
                return;
            }

            try {
                if (conversationType === 'transaction') {
                    if (!transactionId) {
                        throw new Error('Transaction context is missing.');
                    }
                    const opened = await postForm('/VNZ-TECH/backend/chat_conversations.php', {
                        action: 'open',
                        conversation_type: 'transaction',
                        transaction_id: String(transactionId),
                        csrf_token: csrfToken
                    });
                    actualConversationId = Number(opened.conversation_id);
                }
                if (!actualConversationId) {
                    throw new Error('The conversation could not be opened.');
                }

                input.disabled = false;
                sendButton.disabled = false;
                await loadHistory(null, {forceScroll: true});
                await connectRealtime();
            } catch (error) {
                setStatus(`Chat is unavailable: ${error.message}`, 'error');
                setConnection('Unavailable', 'error');
                input.disabled = true;
                sendButton.disabled = true;
            }
        }

        activeChats.set(widget, () => {
            disposed = true;
            if (drone && typeof drone.close === 'function') {
                drone.close();
            }
            activeChats.delete(widget);
        });

        start();
    }

    function scan(root = document) {
        if (root.matches?.('[data-transaction-chat]')) {
            initChat(root);
        }
        root.querySelectorAll?.('[data-transaction-chat]').forEach(initChat);

        for (const [widget, cleanup] of activeChats) {
            if (!widget.isConnected) {
                cleanup();
            }
        }
    }

    function startObserver() {
        scan();
        const observer = new MutationObserver(records => {
            records.forEach(record => {
                record.addedNodes.forEach(node => {
                    if (node.nodeType === Node.ELEMENT_NODE) {
                        scan(node);
                    }
                });
            });
            scan(document);
        });
        observer.observe(document.body, {childList: true, subtree: true});
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', startObserver, {once: true});
    } else {
        startObserver();
    }
})();
