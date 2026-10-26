(() => {
    const roots = new WeakSet();
    const panelCloseTimers = new WeakMap();
    const chatCloseTimers = new WeakMap();
    const pendingOpens = new WeakMap();
    const unreadRefreshVersions = new WeakMap();
    const selectionRefreshVersions = new WeakMap();
    const callControllerPromises = new WeakMap();
    const callableConversations = new WeakMap();
    const refreshEvents = [
        'vnz:chat-state-changed',
        'vnz:chat-message-received'
    ];

    function unreadLabel(count) {
        return count > 99 ? '99+' : String(count);
    }

    function setUnreadBadge(root, category, count, pulse) {
        const badge = root.querySelector(
            `[data-category-unread-badge="${category}"]`
        );
        if (!badge) {
            return;
        }

        const button = badge.closest('[data-message-category]');
        const baseLabel = category === 'global'
            ? 'Global Chat'
            : category === 'private'
                ? 'Private Chat'
                : category === 'guest-sellers'
                    ? 'Guest Sellers'
                    : (root.dataset.actorType === 'seller' ? 'Admin' : 'Sellers');
        const label = unreadLabel(count);
        badge.textContent = label;
        badge.hidden = count === 0;
        button.setAttribute(
            'aria-label',
            count > 0 ? `${baseLabel}, ${label} unread` : baseLabel
        );
        if (pulse && count > 0) {
            badge.classList.remove('is-pulsing');
            void badge.offsetWidth;
            badge.classList.add('is-pulsing');
        }
    }

    function updateUnreadBadges(root, conversations, pulse) {
        const totals = {
            global: 0,
            private: 0,
            contacts: 0,
            'guest-sellers': 0
        };
        let total = 0;

        conversations.forEach(conversation => {
            const unread = Number(conversation.unread_count) || 0;
            total += unread;
            if (conversation.conversation_type === 'global') {
                totals.global += unread;
            } else if (conversation.conversation_class === 'claimed_submission') {
                totals.contacts += unread;
            } else if (conversation.conversation_type === 'private') {
                const category = conversation.guest_submission === true
                    && root.dataset.actorType === 'admin'
                    ? 'guest-sellers'
                    : 'private';
                totals[category] += unread;
            } else if (conversation.conversation_type === 'transaction') {
                totals.contacts += unread;
            }
        });

        const mainBadge = root.querySelector('[data-floating-messages-badge]');
        mainBadge.textContent = unreadLabel(total);
        mainBadge.hidden = total === 0;
        if (pulse && total > 0) {
            mainBadge.classList.remove('is-pulsing');
            void mainBadge.offsetWidth;
            mainBadge.classList.add('is-pulsing');
        }

        Object.entries(totals).forEach(([category, count]) => {
            setUnreadBadge(root, category, count, pulse);
        });
    }

    function initialsFor(displayName) {
        return String(displayName)
            .split(/\s+/)
            .filter(Boolean)
            .slice(0, 2)
            .map(part => Array.from(part)[0].toLocaleUpperCase())
            .join('');
    }

    function presenceLabel(presence, includeLastSeen = false) {
        if (Number.isInteger(Number(presence?.online_count))) {
            return `${Number(presence.online_count)} online`;
        }
        const participant = presence?.participants?.[0];
        if (!participant) {
            return 'Offline';
        }
        let status;
        if (participant.is_online) {
            status = 'Online';
        } else if (includeLastSeen && participant.last_seen_at) {
            const lastSeen = new Date(String(participant.last_seen_at).replace(' ', 'T'));
            if (!Number.isNaN(lastSeen.getTime())) {
                status = `Last seen ${new Intl.DateTimeFormat(undefined, {
                    dateStyle: 'short',
                    timeStyle: 'short'
                }).format(lastSeen)}`;
            }
        }
        status = status || 'Offline';
        return status;
    }

    function updateConversationPresence(root, conversations) {
        const byId = new Map(conversations.map(conversation => [
            Number(conversation.conversation_id),
            conversation
        ]));
        root.querySelectorAll('[data-transaction-chat]').forEach(widget => {
            const conversation = byId.get(Number(widget.dataset.conversationId));
            const labelText = conversation
                ? presenceLabel(conversation.presence, true)
                : 'Offline';
            const label = widget.querySelector('[data-chat-presence]');
            if (label) {
                label.textContent = labelText;
            }
            const floatingWindow = widget.closest('[data-floating-chat-window]');
            const floatingLabel = floatingWindow?.querySelector('[data-floating-chat-presence]');
            if (floatingLabel) {
                floatingLabel.textContent = labelText;
            }
        });
    }

    function setPanelOpen(root, open) {
        const panel = root.querySelector('[data-floating-messages-panel]');
        const closeTimer = panelCloseTimers.get(root);
        if (closeTimer) {
            window.clearTimeout(closeTimer);
            panelCloseTimers.delete(root);
        }

        if (open) {
            panel.hidden = false;
            panel.inert = false;
            panel.classList.remove('is-closing');
            panel.setAttribute('aria-hidden', 'false');
            return;
        }

        panel.classList.add('is-closing');
        panel.inert = true;
        panel.setAttribute('aria-hidden', 'true');
        panelCloseTimers.set(root, window.setTimeout(() => {
            panel.hidden = true;
            panel.classList.remove('is-closing');
            panelCloseTimers.delete(root);
        }, 160));
    }

    function setCategoryMenuOpen(root, open) {
        const toggle = root.querySelector('[data-floating-messages-toggle]');
        const categories = root.querySelector('[data-floating-messages-categories]');
        root.classList.toggle('is-menu-open', open);
        categories.setAttribute('aria-hidden', String(!open));
        toggle.setAttribute('aria-expanded', String(open));
        categories.querySelectorAll('[data-message-category]').forEach(button => {
            button.tabIndex = open ? 0 : -1;
        });
    }

    function maximumWindows() {
        if (window.innerWidth <= 680) {
            return 1;
        }
        return Math.max(
            1,
            Math.min(3, Math.floor((window.innerWidth - 106) / 362))
        );
    }

    function refreshUnread(root, pulse = false) {
        const requestVersion = (unreadRefreshVersions.get(root) || 0) + 1;
        unreadRefreshVersions.set(root, requestVersion);
        fetch('/VNZ-TECH/backend/chat_conversations.php?action=list', {
            method: 'GET',
            credentials: 'same-origin',
            cache: 'no-store'
        })
            .then(async response => {
                let payload;
                try {
                    payload = await response.json();
                } catch {
                    throw new Error('Unable to read conversation status.');
                }
                if (!response.ok || payload.success !== true) {
                    throw new Error(payload.message || 'Unable to load conversations.');
                }
                return Array.isArray(payload.data?.conversations)
                    ? payload.data.conversations
                    : [];
            })
            .then(conversations => {
                if (unreadRefreshVersions.get(root) !== requestVersion) {
                    return;
                }
                root.dataset.conversations = JSON.stringify(conversations);
                updateUnreadBadges(root, conversations, pulse);
                updateConversationPresence(root, conversations);
                const panel = root.querySelector('[data-floating-messages-panel]');
                const category = root.dataset.activeCategory;
                if (!panel.hidden && category) {
                    refreshSelection(root, category, conversations);
                }
            })
            .catch(error => {
                if (unreadRefreshVersions.get(root) !== requestVersion) {
                    return;
                }
                console.error('Unable to refresh messaging unread counts:', error);
                root.querySelector('[data-floating-messages-badge]').hidden = true;
                root.querySelectorAll('[data-category-unread-badge]').forEach(badge => {
                    badge.hidden = true;
                    const button = badge.closest('[data-message-category]');
                    const category = badge.dataset.categoryUnreadBadge;
                    button.setAttribute(
                        'aria-label',
                        category === 'global'
                            ? 'Global Chat'
                            : category === 'private'
                                ? 'Private Chat'
                                : category === 'guest-sellers'
                                    ? 'Guest Sellers'
                                    : (root.dataset.actorType === 'seller' ? 'Admin' : 'Sellers')
                    );
                });
                const panel = root.querySelector('[data-floating-messages-panel]');
                const category = root.dataset.activeCategory;
                if (!panel.hidden && category) {
                    refreshSelection(root, category, []);
                }
            });
    }

    function renderSelectionList(root, contacts, conversations, category) {
        const list = root.querySelector('[data-floating-messages-list]');
        list.replaceChildren();

        const addHeading = text => {
            const heading = document.createElement('h3');
            heading.className = 'floating-messages-section-heading';
            heading.textContent = text;
            list.append(heading);
        };
        const addUnread = (button, unreadCount) => {
            const unread = Number(unreadCount) || 0;
            if (unread > 0) {
                const badge = document.createElement('span');
                badge.className = 'floating-messages-conversation-unread';
                badge.textContent = unreadLabel(unread);
                button.append(badge);
            }
        };
        const appendCallActions = (row, conversation) => {
            if (root.dataset.actorType !== 'seller' || !conversation.call_eligible) {
                return;
            }
            const actions = document.createElement('span');
            actions.className = 'floating-messages-call-actions';
            ['audio', 'video'].forEach(mode => {
                const callButton = document.createElement('button');
                callButton.type = 'button';
                callButton.className = 'floating-messages-call-action';
                callButton.dataset.callRequest = '';
                callButton.dataset.callMode = mode;
                callButton.dataset.conversationId = String(conversation.conversation_id);
                callButton.dataset.conversationType = conversation.conversation_type;
                callButton.dataset.transactionId = String(conversation.transaction_id || 0);
                callButton.setAttribute(
                    'aria-label',
                    `${mode === 'audio' ? 'Voice' : 'Video'} call ${conversation.display_name || 'participant'}`
                );
                callButton.title = mode === 'audio' ? 'Start voice call' : 'Start video call';
                callButton.textContent = mode === 'audio' ? '☎' : '▣';
                actions.append(callButton);
            });
            row.append(actions);
        };
        const transactionItems = conversations.filter(
            conversation => conversation.conversation_class === 'transaction'
                || conversation.conversation_class === 'claimed_submission'
        );

        if (category === 'contacts' && transactionItems.length > 0) {
            addHeading('TRANSACTION CHATS');
            transactionItems.forEach(conversation => {
                const row = document.createElement('div');
                const button = document.createElement('button');
                const title = document.createElement('strong');
                const reference = document.createElement('span');
                const status = document.createElement('small');
                button.type = 'button';
                button.className = 'floating-messages-conversation';
                row.className = 'floating-messages-call-row';
                button.dataset.transactionId = String(conversation.transaction_id);
                button.dataset.conversationId = String(conversation.conversation_id);
                button.dataset.conversationType = conversation.conversation_type;
                button.dataset.transactionReference = String(conversation.transaction_reference || '');
                title.textContent = String(conversation.display_name || 'ADMIN');
                reference.textContent = String(
                    conversation.transaction_reference || 'Transaction'
                );
                status.textContent = `${presenceLabel(conversation.presence)} · ${String(
                    conversation.transaction_status || 'pending'
                ).replaceAll('_', ' ')}`;
                button.append(title, reference, status);
                addUnread(button, conversation.unread_count);
                row.append(button);
                appendCallActions(row, conversation);
                list.append(row);
            });
        }

        const guestConversations = (
            category === 'guest-sellers'
            || (category === 'private' && root.dataset.actorType !== 'admin')
        )
            ? conversations.filter(conversation => (
                conversation.guest_submission === true
                && conversation.conversation_type === 'private'
            ))
            : [];
        if (
            category === 'guest-sellers'
            || (category === 'private' && root.dataset.actorType !== 'admin')
        ) {
            if (category === 'guest-sellers' && guestConversations.length === 0) {
                const empty = document.createElement('p');
                empty.className = 'floating-messages-list-state';
                empty.textContent = 'No guest seller conversations are available.';
                list.append(empty);
            } else if (guestConversations.length > 0) {
                addHeading('GUEST SELLER CHATS');
            }
            guestConversations.forEach(conversation => {
                const button = document.createElement('button');
                const avatar = document.createElement('span');
                const name = document.createElement('strong');
                const reference = document.createElement('span');
                const preview = document.createElement('small');
                button.type = 'button';
                button.className = 'floating-messages-conversation is-guest-seller';
                button.dataset.existingPrivateConversation = '';
                button.dataset.conversationId = String(conversation.conversation_id);
                button.dataset.conversationTitle = String(
                    conversation.guest_seller_name || 'Guest Seller'
                );
                avatar.className = 'floating-messages-contact-avatar';
                avatar.setAttribute('aria-hidden', 'true');
                avatar.textContent = initialsFor(conversation.guest_seller_name || 'Guest Seller');
                name.textContent = String(conversation.guest_seller_name || 'Guest Seller');
                reference.textContent = `Submission ${String(
                    conversation.submission_reference || ''
                )} · ${presenceLabel(conversation.presence)}`;
                preview.textContent = String(conversation.last_message || '').trim()
                    || 'No messages yet';
                button.append(avatar, name, reference, preview);
                addUnread(button, conversation.unread_count);
                list.append(button);
            });
        }

        const candidates = category === 'private' ? contacts : [];
        const unreadByConversationId = new Map(
            conversations.map(conversation => [
                Number(conversation.conversation_id),
                Number(conversation.unread_count) || 0
            ])
        );

        if (candidates.length > 0) {
            addHeading('AVAILABLE PEOPLE');
            candidates.forEach(contact => {
                const row = document.createElement('div');
                const button = document.createElement('button');
                const avatar = document.createElement('span');
                const name = document.createElement('strong');
                const status = document.createElement('small');
                button.type = 'button';
                button.className = 'floating-messages-conversation is-contact';
                row.className = 'floating-messages-call-row';
                avatar.className = 'floating-messages-contact-avatar';
                avatar.setAttribute('aria-hidden', 'true');
                const displayName = String(contact.display_name || 'Contact');
                const profilePath = String(contact.profile_picture || '');
                if (
                    contact.actor_type === 'seller'
                    && /^uploads\/profile_pictures\/[A-Za-z0-9][A-Za-z0-9._-]*$/.test(profilePath)
                ) {
                    const image = document.createElement('img');
                    image.src = `/VNZ-TECH/${profilePath}`;
                    image.alt = '';
                    image.loading = 'lazy';
                    image.addEventListener('error', () => {
                        image.remove();
                        avatar.textContent = initialsFor(displayName);
                    }, {once: true});
                    avatar.append(image);
                } else {
                    avatar.textContent = initialsFor(displayName);
                }
                button.dataset.targetType = contact.actor_type;
                button.dataset.targetId = String(contact.actor_id);
                button.dataset.conversationId = contact.conversation_id
                    ? String(contact.conversation_id)
                    : '';
                name.textContent = displayName;
                status.textContent = contact.conversation_id
                    ? `${presenceLabel(contact.presence)} · ${
                        String(contact.last_message || '').trim() || 'Private conversation'
                    }`
                    : 'Start private conversation';
                button.append(avatar, name, status);
                addUnread(
                    button,
                    unreadByConversationId.get(Number(contact.conversation_id)) || 0
                );
                row.append(button);
                appendCallActions(row, {
                    conversation_id: contact.conversation_id,
                    conversation_type: 'private',
                    call_eligible: contact.call_eligible,
                    display_name: contact.display_name
                });
                list.append(row);
            });
        }

        if (list.childElementCount === 0) {
            const empty = document.createElement('p');
            empty.className = 'floating-messages-list-state';
            empty.textContent = category === 'private'
                ? 'There are no other eligible chat contacts yet.'
                : 'No transaction conversations are available yet.';
            list.append(empty);
        }
    }

    async function readJsonResponse(response) {
        let payload;
        try {
            payload = await response.json();
        } catch {
            throw new Error('The server returned an invalid response.');
        }
        if (!response.ok || payload.success !== true) {
            throw new Error(payload.message || 'The request could not be completed.');
        }
        return payload.data || {};
    }

    async function postForm(fields) {
        const response = await fetch('/VNZ-TECH/backend/chat_conversations.php', {
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

    async function refreshSelection(root, category, conversations = null) {
        const requestVersion = (selectionRefreshVersions.get(root) || 0) + 1;
        selectionRefreshVersions.set(root, requestVersion);
        const list = root.querySelector('[data-floating-messages-list]');
        const errorPanel = root.querySelector('[data-floating-messages-error]');
        try {
            let contacts = [];
            let availableConversations = conversations;
            if (category === 'private') {
                const contactData = await getJson(
                    '/VNZ-TECH/backend/chat_conversations.php?action=contacts&category=private'
                );
                contacts = contactData.contacts || [];
            }
            if (
                category === 'contacts'
                || !Array.isArray(availableConversations)
            ) {
                try {
                    const conversationData = await getJson(
                        '/VNZ-TECH/backend/chat_conversations.php?action=list'
                    );
                    availableConversations = conversationData.conversations || [];
                } catch (error) {
                    console.error('Unable to load transaction conversation list:', error);
                    if (category === 'contacts' || category === 'guest-sellers') {
                        throw error;
                    }
                    availableConversations = [];
                }
            }
            if (
                root.dataset.activeCategory !== category
                || selectionRefreshVersions.get(root) !== requestVersion
            ) {
                return;
            }
            renderSelectionList(
                root,
                contacts,
                availableConversations,
                category
            );
        } catch (error) {
            if (
                root.dataset.activeCategory === category
                && selectionRefreshVersions.get(root) === requestVersion
                && !root.querySelector('[data-floating-messages-panel]').hidden
            ) {
                list.replaceChildren();
                errorPanel.textContent = error.message || 'Unable to refresh eligible conversations.';
                errorPanel.hidden = false;
            }
        }
    }

    function updateWindowVisibility(root) {
        const windows = Array.from(root.querySelectorAll('[data-floating-chat-window]'));
        const visibleLimit = maximumWindows();
        windows.forEach((windowElement, index) => {
            windowElement.hidden = index < windows.length - visibleLimit;
        });
        root.classList.toggle(
            'is-mobile-chat-open',
            window.innerWidth <= 700
                && windows.some(windowElement => (
                    !windowElement.hidden
                    && !windowElement.classList.contains('is-minimized')
                    && !windowElement.classList.contains('is-closing')
                    && !windowElement.classList.contains('is-call-active')
                ))
        );
    }

    function preserveCallOwners(root, container) {
        const host = root.querySelector('[data-call-owner-host]');
        if (!host || !container) {
            return;
        }
        container.querySelectorAll('[data-transaction-chat][data-active-call-owner]')
            .forEach(widget => host.append(widget));
    }

    function controllerMap(root) {
        let controllers = callControllerPromises.get(root);
        if (!controllers) {
            controllers = new Map();
            callControllerPromises.set(root, controllers);
        }
        return controllers;
    }

    function callableConversationMap(root) {
        let conversations = callableConversations.get(root);
        if (!conversations) {
            conversations = new Map();
            callableConversations.set(root, conversations);
        }
        return conversations;
    }

    async function loadCallableConversations(root) {
        const data = await getJson('/VNZ-TECH/backend/chat_conversations.php?action=list');
        const conversations = Array.isArray(data.conversations)
            ? data.conversations.filter(conversation => (
                conversation.call_eligible === true
                && ['transaction', 'private'].includes(conversation.conversation_type)
                && Number(conversation.conversation_id) > 0
            ))
            : [];
        const byId = callableConversationMap(root);
        byId.clear();
        conversations.forEach(conversation => {
            byId.set(Number(conversation.conversation_id), conversation);
        });
        return conversations;
    }

    function waitForCallController(widget) {
        return new Promise((resolve, reject) => {
            let timeoutId = null;
            const finish = error => {
                observer.disconnect();
                if (timeoutId !== null) {
                    window.clearTimeout(timeoutId);
                }
                if (error) {
                    reject(error);
                } else {
                    resolve(widget);
                }
            };
            const check = () => {
                if (widget.dataset.callControllerState === 'ready') {
                    finish();
                } else if (widget.dataset.callControllerState === 'unavailable') {
                    finish(new Error(
                        widget.dataset.callControllerError
                        || 'Calling is unavailable for this conversation.'
                    ));
                }
            };
            const observer = new MutationObserver(check);
            observer.observe(widget, {
                attributes: true,
                attributeFilter: ['data-call-controller-state']
            });
            timeoutId = window.setTimeout(() => {
                finish(new Error('The call controller did not become ready.'));
            }, 20000);
            check();
        });
    }

    function ensureCallController(root, conversation) {
        const conversationId = Number(conversation.conversation_id);
        const controllers = controllerMap(root);
        if (controllers.has(conversationId)) {
            return controllers.get(conversationId);
        }

        const opening = (async () => {
            const host = root.querySelector('[data-call-owner-host]');
            if (!host) {
                throw new Error('The persistent call host is unavailable.');
            }
            const actorType = root.dataset.actorType;
            const endpoint = getConversationWindowEndpoint(actorType);
            const response = await fetch(
                `${endpoint}?conversation_id=${encodeURIComponent(conversationId)}`,
                {
                    method: 'GET',
                    credentials: 'same-origin',
                    cache: 'no-store',
                    headers: {'X-Requested-With': 'XMLHttpRequest'}
                }
            );
            const html = await response.text();
            if (!response.ok) {
                throw new Error(html.replace(/<[^>]*>/g, ' ').trim() || 'Unable to prepare calling.');
            }
            const template = document.createElement('template');
            template.innerHTML = html;
            const widget = template.content.querySelector('[data-transaction-chat]');
            if (!widget) {
                throw new Error('The server did not return an authorized conversation.');
            }
            if (
                Number(widget.dataset.conversationId) !== conversationId
                || (
                    conversation.conversation_type === 'transaction'
                    && Number(widget.dataset.transactionId) !== Number(conversation.transaction_id)
                )
            ) {
                throw new Error('The server returned a different conversation than the one selected.');
            }
            if (widget.dataset.callEnabled !== 'true') {
                throw new Error('Calling is not available for this conversation.');
            }
            widget.dataset.callControllerOnly = '';
            widget.dataset.callControllerConversationId = String(conversationId);
            host.append(widget);
            window.VNZTransactionChat?.scan?.(widget);
            try {
                return await waitForCallController(widget);
            } catch (error) {
                widget.remove();
                window.VNZTransactionChat?.scan?.(document);
                throw error;
            }
        })();
        controllers.set(conversationId, opening);
        opening.catch(() => controllers.delete(conversationId));
        return opening;
    }

    function showCallRequestError(root, error) {
        const errorPanel = root.querySelector('[data-floating-messages-error]');
        errorPanel.textContent = error.message || 'Unable to start this call.';
        errorPanel.hidden = false;
        setPanelOpen(root, true);
    }

    async function requestCall(root, conversationId, mode) {
        if (root.dataset.actorType !== 'seller') {
            throw new Error('Calls are only available between eligible Seller accounts.');
        }
        if (!Number.isInteger(conversationId) || conversationId <= 0 || !['audio', 'video'].includes(mode)) {
            throw new Error('The selected call destination is invalid.');
        }
        await loadCallableConversations(root);
        const conversation = callableConversationMap(root).get(conversationId);
        if (!conversation) {
            throw new Error('No authorized one-to-one conversation is available for this call.');
        }
        const widget = await ensureCallController(root, conversation);
        const button = widget.querySelector(
            mode === 'audio' ? '[data-chat-call-audio]' : '[data-chat-call-video]'
        );
        if (!button || button.disabled) {
            throw new Error('Calling is not ready yet. Please try again shortly.');
        }
        button.click();
    }

    async function handleIncomingCallInvite(root, payload) {
        const conversationId = Number(payload?.conversation_id);
        if (
            root.dataset.actorType !== 'seller'
            ||
            payload?.event !== 'call.invite'
            || !Number.isInteger(conversationId)
            || conversationId <= 0
            || payload.actor_type !== 'seller'
            || payload.to_actor_type !== 'seller'
            || payload.to_actor_type !== root.dataset.actorType
            || Number(payload.to_actor_id) !== Number(root.dataset.actorId)
            || (
                payload.actor_type === root.dataset.actorType
                && Number(payload.actor_id) === Number(root.dataset.actorId)
            )
            || !['audio', 'video'].includes(payload.mode)
            || !/^[0-9a-f]{8}-[0-9a-f]{4}-[1-5][0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/i
                .test(String(payload.call_id || ''))
        ) {
            return;
        }
        await loadCallableConversations(root);
        const conversation = callableConversationMap(root).get(conversationId);
        if (!conversation) {
            return;
        }
        const widget = await ensureCallController(root, conversation);
        widget.dispatchEvent(new CustomEvent('vnz:call-signal', {detail: payload}));
    }

    function initializeCallInbox(root) {
        if (root.dataset.actorType !== 'seller') {
            return;
        }
        const channelId = root.dataset.channelId || '';
        let incomingQueue = Promise.resolve();
        if (!channelId || !window.VNZTransactionChat?.acquireCallInbox) {
            console.error('Incoming call monitoring is unavailable on this page.');
            return;
        }
        window.VNZTransactionChat.acquireCallInbox(
            channelId,
            root.dataset.csrfToken || '',
            {
                onEvent: payload => {
                    incomingQueue = incomingQueue.then(
                        () => handleIncomingCallInvite(root, payload)
                    ).catch(error => {
                        console.error('Unable to prepare the incoming call:', error);
                    });
                },
                onState: (ready, error) => {
                    if (!ready && error) {
                        console.error('Incoming call monitoring is unavailable:', error.message);
                    }
                }
            }
        ).catch(error => {
            console.error('Incoming call monitoring could not be initialized:', error.message);
        });
    }

    function getConversationWindowEndpoint(actorType) {
        return actorType === 'admin'
            ? '/VNZ-TECH/admin/pages/transaction-chat-window.php'
            : '/VNZ-TECH/account/pages/transaction-chat-window.php';
    }

    async function loadConversationWindow(root, windowElement, details) {
        const body = windowElement.querySelector('.floating-chat-window-body');
        const title = windowElement.querySelector('[data-floating-chat-title]');
        const actorType = root.dataset.actorType;
        const endpoint = getConversationWindowEndpoint(actorType);
        const hasConversationId = Number(details.conversationId) > 0;
        const requestId = hasConversationId
            ? details.conversationId
            : details.transactionId;
        const requestField = hasConversationId ? 'conversation_id' : 'transaction_id';

        windowElement.dataset.conversationType = details.conversationType;
        windowElement.dataset.conversationId = String(details.conversationId || 0);
        windowElement.dataset.transactionId = String(details.transactionId || 0);
        if (details.targetType) {
            windowElement.dataset.targetType = details.targetType;
            windowElement.dataset.targetId = String(details.targetId);
        } else {
            delete windowElement.dataset.targetType;
            delete windowElement.dataset.targetId;
        }

        preserveCallOwners(root, body);
        body.replaceChildren();
        const loading = document.createElement('p');
        loading.className = 'floating-chat-window-loading';
        loading.textContent = 'Opening conversation…';
        body.append(loading);

        try {
            const response = await fetch(
                `${endpoint}?${requestField}=${encodeURIComponent(requestId)}`,
                {
                    method: 'GET',
                    credentials: 'same-origin',
                    cache: 'no-store',
                    headers: {'X-Requested-With': 'XMLHttpRequest'}
                }
            );
            const html = await response.text();
            if (!response.ok) {
                throw new Error(html.replace(/<[^>]*>/g, ' ').trim() || 'Unable to open this conversation.');
            }

            body.innerHTML = html;
            const widget = body.querySelector('[data-transaction-chat]');
            if (!widget) {
                throw new Error('The server did not return a conversation widget.');
            }
            if (
                hasConversationId
                && Number(widget.dataset.conversationId) !== Number(details.conversationId)
            ) {
                throw new Error('The server returned a different conversation than the one selected.');
            }
            if (
                Number(details.transactionId) > 0
                && Number(widget.dataset.transactionId) !== Number(details.transactionId)
            ) {
                throw new Error('The selected transaction does not match this conversation.');
            }
            const headerReference = widget?.dataset.transactionReference;
            const conversationTitle = widget?.dataset.conversationTitle;
            title.textContent = Number(details.transactionId) > 0 && headerReference
                ? headerReference
                : (conversationTitle || details.displayName || 'Conversation');
            windowElement.classList.add('is-mobile-chat-open');
            widget?.querySelector('[data-chat-input]')?.focus();
            refreshUnread(root);
        } catch (error) {
            body.replaceChildren();
            const message = document.createElement('p');
            message.className = 'floating-chat-window-error';
            message.textContent = error.message || 'Unable to open this conversation.';
            body.append(message);
        }
    }

    function renderWorkspaceSection(list, title) {
        const heading = document.createElement('h3');
        heading.className = 'floating-chat-workspace-section';
        heading.textContent = title;
        list.append(heading);
    }

    function renderWorkspaceConversation(list, item, activeWindow) {
        const button = document.createElement('button');
        const icon = document.createElement('span');
        const text = document.createElement('span');
        const name = document.createElement('strong');
        const preview = document.createElement('small');
        button.type = 'button';
        button.className = 'floating-chat-workspace-item';
        button.dataset.workspaceConversation = '';
        button.dataset.conversationType = item.conversation_type;
        button.dataset.conversationId = String(item.conversation_id || 0);
        button.dataset.transactionId = String(item.transaction_id || 0);
        if (item.target_type) {
            button.dataset.targetType = item.target_type;
            button.dataset.targetId = String(item.target_id);
        }
        if (
            item.conversation_type === activeWindow.dataset.conversationType
            && Number(item.conversation_id || 0) === Number(activeWindow.dataset.conversationId || 0)
            && Number(item.transaction_id || 0) === Number(activeWindow.dataset.transactionId || 0)
        ) {
            button.classList.add('is-active');
        }

        const displayName = String(item.display_name || 'Conversation');
        icon.className = 'floating-chat-workspace-item-icon';
        icon.setAttribute('aria-hidden', 'true');
        icon.textContent = item.conversation_type === 'global'
            ? 'G'
            : initialsFor(displayName);
        text.className = 'floating-chat-workspace-item-text';
        name.textContent = displayName;
        preview.textContent = item.conversation_class === 'transaction'
            || item.conversation_class === 'claimed_submission'
            ? String(item.transaction_reference || 'Transaction chat')
            : item.conversation_type === 'global'
                ? 'Global conversation'
                : (String(item.last_message || '').trim() || 'Private conversation');
        text.append(name, preview);
        button.append(icon, text);
        list.append(button);
    }

    async function refreshWorkspaceSidebar(root, windowElement) {
        const list = windowElement.querySelector('[data-workspace-conversation-list]');
        list.replaceChildren();
        const loading = document.createElement('p');
        loading.className = 'floating-messages-list-state';
        loading.textContent = 'Loading conversations…';
        list.append(loading);

        const [conversationData, contactData] = await Promise.all([
            getJson('/VNZ-TECH/backend/chat_conversations.php?action=list'),
            getJson('/VNZ-TECH/backend/chat_conversations.php?action=contacts&category=private')
        ]);
        const conversations = Array.isArray(conversationData.conversations)
            ? conversationData.conversations
            : [];
        const contacts = Array.isArray(contactData.contacts)
            ? contactData.contacts
            : [];

        list.replaceChildren();
        const globalConversations = conversations.filter(item => item.conversation_type === 'global');
        renderWorkspaceSection(list, 'Global');
        if (globalConversations.length) {
            globalConversations.forEach(item => renderWorkspaceConversation(list, item, windowElement));
        } else {
            renderWorkspaceConversation(list, {
                conversation_type: 'global',
                conversation_id: 0,
                display_name: 'Global Chat'
            }, windowElement);
        }

        renderWorkspaceSection(list, 'Private chats');
        const privateConversations = conversations.filter(item => (
            item.conversation_type === 'private'
            && item.conversation_class !== 'claimed_submission'
        ));
        privateConversations.forEach(item => {
            renderWorkspaceConversation(list, item, windowElement);
        });

        const newContacts = contacts.filter(contact => !contact.conversation_id);
        newContacts.forEach(contact => {
            renderWorkspaceConversation(list, {
                conversation_type: 'private',
                conversation_id: 0,
                display_name: contact.display_name,
                last_message: 'Start private conversation',
                target_type: contact.actor_type,
                target_id: contact.actor_id
            }, windowElement);
        });

        renderWorkspaceSection(list, 'Transaction chats');
        conversations
            .filter(item => item.conversation_class === 'transaction'
                || item.conversation_class === 'claimed_submission')
            .forEach(item => renderWorkspaceConversation(list, item, windowElement));
    }

    async function openFloatingWindow(root, details) {
        const existing = Array.from(root.querySelectorAll('[data-floating-chat-window]'))
            .find(item => details.conversationType === 'transaction'
                ? Number(item.dataset.transactionId) === details.transactionId
                : Number(item.dataset.conversationId) === details.conversationId);
        if (existing) {
            const closeTimer = chatCloseTimers.get(existing);
            if (closeTimer) {
                window.clearTimeout(closeTimer);
                chatCloseTimers.delete(existing);
            }
            existing.classList.remove('is-minimized');
            existing.classList.remove('is-closing');
            if (existing.classList.contains('is-call-active')) {
                existing.classList.remove('is-call-active');
                existing.classList.add('is-call-chat-visible');
            }
            existing.hidden = false;
            existing.querySelector('[data-chat-input]')?.focus();
            updateWindowVisibility(root);
            setPanelOpen(root, false);
            setCategoryMenuOpen(root, false);
            return;
        }

        if (
            details.conversationType !== 'transaction'
            && (!Number.isInteger(details.conversationId) || details.conversationId <= 0)
        ) {
            throw new Error('The conversation could not be opened.');
        }

        const windows = root.querySelector('[data-floating-chat-windows]');
        const windowCount = windows.querySelectorAll('[data-floating-chat-window]').length;
        if (windowCount >= maximumWindows()) {
            const errorPanel = root.querySelector('[data-floating-messages-error]');
            errorPanel.textContent = window.innerWidth <= 680
                ? 'Close the current chat window before opening another on this screen.'
                : 'Close a chat window before opening another.';
            errorPanel.hidden = false;
            root.querySelector('[data-floating-messages-list]').hidden = false;
            root.querySelector('[data-floating-messages-placeholder]').hidden = true;
            setPanelOpen(root, true);
            setCategoryMenuOpen(root, false);
            return;
        }

        setPanelOpen(root, false);
        setCategoryMenuOpen(root, false);
        const windowElement = document.createElement('section');
        const header = document.createElement('header');
        const titleGroup = document.createElement('div');
        const title = document.createElement('strong');
        const presence = document.createElement('small');
        const actions = document.createElement('div');
        const maximizeButton = document.createElement('button');
        const minimizeButton = document.createElement('button');
        const closeButton = document.createElement('button');
        const sidebar = document.createElement('aside');
        const sidebarHeader = document.createElement('div');
        const sidebarList = document.createElement('nav');
        const backButton = document.createElement('button');
        const body = document.createElement('div');

        windowElement.className = 'floating-chat-window is-opening';
        windowElement.dataset.floatingChatWindow = '';
        windowElement.dataset.conversationType = details.conversationType;
        windowElement.dataset.conversationId = String(details.conversationId || 0);
        windowElement.dataset.transactionId = String(details.transactionId || 0);
        if (details.targetType) {
            windowElement.dataset.targetType = details.targetType;
            windowElement.dataset.targetId = String(details.targetId);
        }
        header.className = 'floating-chat-window-header';
        titleGroup.className = 'floating-chat-window-title';
        title.dataset.floatingChatTitle = '';
        title.textContent = details.displayName
            || details.reference
            || 'Conversation';
        presence.dataset.floatingChatPresence = '';
        presence.textContent = 'Offline';
        titleGroup.append(title, presence);
        actions.className = 'floating-chat-window-actions';
        maximizeButton.type = 'button';
        maximizeButton.dataset.chatMaximize = '';
        maximizeButton.setAttribute('aria-label', 'Maximize chat');
        maximizeButton.setAttribute('aria-pressed', 'false');
        maximizeButton.title = 'Maximize chat';
        maximizeButton.textContent = '□';
        minimizeButton.type = 'button';
        minimizeButton.dataset.chatMinimize = '';
        minimizeButton.setAttribute('aria-label', 'Minimize chat');
        minimizeButton.textContent = '—';
        closeButton.type = 'button';
        closeButton.dataset.chatClose = '';
        closeButton.setAttribute('aria-label', 'Close chat');
        closeButton.textContent = '×';
        sidebar.className = 'floating-chat-workspace-sidebar';
        sidebarHeader.className = 'floating-chat-workspace-sidebar-header';
        sidebarHeader.textContent = 'Conversations';
        sidebarList.className = 'floating-chat-workspace-list';
        sidebarList.dataset.workspaceConversationList = '';
        sidebarList.setAttribute('aria-label', 'Conversations');
        backButton.type = 'button';
        backButton.className = 'floating-chat-workspace-back';
        backButton.dataset.chatWorkspaceBack = '';
        backButton.setAttribute('aria-label', 'Back to conversations');
        backButton.title = 'Back to conversations';
        backButton.textContent = '‹';
        sidebar.append(sidebarHeader, sidebarList);
        body.className = 'floating-chat-window-body';
        body.innerHTML = '<p class="floating-chat-window-loading">Opening conversation…</p>';
        actions.append(backButton, maximizeButton, minimizeButton, closeButton);
        header.append(titleGroup, actions);
        windowElement.append(header, sidebar, body);
        windows.prepend(windowElement);
        updateWindowVisibility(root);

        await loadConversationWindow(root, windowElement, details);
        windowElement.classList.remove('is-opening');
        windowElement.classList.add('is-open');
        updateWindowVisibility(root);
    }

    async function openWorkspaceConversation(root, windowElement, button) {
        const conversationType = button.dataset.conversationType;
        let conversationId = Number(button.dataset.conversationId) || 0;
        const transactionId = Number(button.dataset.transactionId) || 0;
        const targetType = button.dataset.targetType || '';
        const targetId = Number(button.dataset.targetId) || 0;

        if (
            conversationType !== 'transaction'
            && conversationId <= 0
        ) {
            const fields = {
                action: 'open',
                conversation_type: conversationType,
                csrf_token: root.dataset.csrfToken
            };
            if (conversationType === 'private') {
                fields.target_type = targetType;
                fields.target_id = String(targetId);
            }
            const opened = await postForm(fields);
            conversationId = Number(opened.conversation_id);
            button.dataset.conversationId = String(conversationId);
        }

        await loadConversationWindow(root, windowElement, {
            conversationType,
            conversationId,
            transactionId,
            displayName: button.querySelector('strong')?.textContent || 'Conversation',
            targetType,
            targetId
        });
        windowElement.classList.remove('is-workspace-list-open');
        const sidebar = windowElement.querySelector('[data-workspace-conversation-list]');
        sidebar.querySelectorAll('[data-workspace-conversation]').forEach(item => {
            item.classList.toggle(
                'is-active',
                item.dataset.conversationType === conversationType
                && Number(item.dataset.conversationId || 0) === conversationId
                && Number(item.dataset.transactionId || 0) === transactionId
            );
        });
        if (window.innerWidth <= 700) {
            windowElement.classList.add('is-mobile-chat-open');
        }
    }

    async function openTransactionWindow(
        root,
        transactionId,
        reference = '',
        conversationId = 0,
        conversationType = 'transaction'
    ) {
        if (!Number.isInteger(transactionId) || transactionId <= 0) {
            return;
        }
        return openFloatingWindow(root, {
            conversationType,
            transactionId,
            conversationId,
            reference,
            displayName: reference || 'Transaction chat'
        });
    }

    async function openDirectConversation(root, conversationType, target = null) {
        const pendingKey = conversationType === 'global'
            ? 'global'
            : `private:${target.actor_type}:${target.actor_id}`;
        let pendingByKey = pendingOpens.get(root);
        if (!pendingByKey) {
            pendingByKey = new Map();
            pendingOpens.set(root, pendingByKey);
        }
        if (pendingByKey.has(pendingKey)) {
            return pendingByKey.get(pendingKey);
        }

        const existing = Array.from(root.querySelectorAll('[data-floating-chat-window]'))
            .find(windowElement => conversationType === 'global'
                ? windowElement.dataset.conversationType === 'global'
                : windowElement.dataset.conversationType === 'private'
                    && windowElement.dataset.targetType === target.actor_type
                    && Number(windowElement.dataset.targetId) === Number(target.actor_id));
        if (existing) {
            return openFloatingWindow(root, {
                conversationType,
                conversationId: Number(existing.dataset.conversationId),
                transactionId: 0,
                displayName: existing.querySelector('[data-floating-chat-title]')?.textContent
                    || 'Conversation'
            });
        }

        if (
            root.querySelectorAll('[data-floating-chat-window]').length
            >= maximumWindows()
        ) {
            const errorPanel = root.querySelector('[data-floating-messages-error]');
            errorPanel.textContent = window.innerWidth <= 680
                ? 'Close the current chat window before opening another on this screen.'
                : 'Close a chat window before opening another.';
            errorPanel.hidden = false;
            root.querySelector('[data-floating-messages-list]').hidden = false;
            root.querySelector('[data-floating-messages-placeholder]').hidden = true;
            setPanelOpen(root, true);
            setCategoryMenuOpen(root, false);
            return;
        }

        const opening = (async () => {
            const fields = {
                action: 'open',
                conversation_type: conversationType,
                csrf_token: root.dataset.csrfToken
            };
            if (conversationType === 'private') {
                fields.target_type = target.actor_type;
                fields.target_id = String(target.actor_id);
            }
            const conversation = await postForm(fields);
            await openFloatingWindow(root, {
                conversationType: conversation.conversation_type,
                conversationId: Number(conversation.conversation_id),
                transactionId: 0,
                displayName: conversation.display_name || 'Conversation',
                targetType: conversation.target_type || target?.actor_type,
                targetId: Number(conversation.target_id || target?.actor_id || 0)
            });
            refreshUnread(root);
        })();

        pendingByKey.set(pendingKey, opening);
        try {
            await opening;
        } finally {
            pendingByKey.delete(pendingKey);
        }
    }

    async function openCategory(root, category) {
        const title = root.querySelector('[data-floating-messages-title]');
        const list = root.querySelector('[data-floating-messages-list]');
        const placeholder = root.querySelector('[data-floating-messages-placeholder]');
        const errorPanel = root.querySelector('[data-floating-messages-error]');
        const actorType = root.dataset.actorType;

        root.querySelectorAll('[data-message-category]').forEach(button => {
            button.classList.toggle(
                'is-active',
                button.dataset.messageCategory === category
            );
        });
        setCategoryMenuOpen(root, false);
        setPanelOpen(root, false);
        root.dataset.activeCategory = '';
        errorPanel.hidden = true;
        placeholder.hidden = true;

        if (category === 'global') {
            try {
                await openDirectConversation(root, 'global');
            } catch (error) {
                title.textContent = 'Global Chat';
                list.hidden = true;
                placeholder.textContent = error.message || 'Unable to open Global Chat.';
                placeholder.hidden = false;
                setPanelOpen(root, true);
            }
            return;
        }

        title.textContent = category === 'private'
            ? 'Private Chat'
            : category === 'guest-sellers'
                ? 'Guest Sellers'
                : (actorType === 'seller' ? 'Admin conversations' : 'Seller conversations');
        root.dataset.activeCategory = category;
        list.hidden = false;
        list.replaceChildren();
        const loading = document.createElement('p');
        loading.className = 'floating-messages-list-state';
        loading.textContent = 'Loading eligible conversations…';
        list.append(loading);
        setPanelOpen(root, true);

        try {
            await refreshSelection(root, category);
            const firstContact = list.querySelector('button');
            (firstContact || root.querySelector('[data-floating-messages-close]')).focus();
        } catch (error) {
            list.replaceChildren();
            errorPanel.textContent = error.message || 'Unable to load eligible conversations.';
            errorPanel.hidden = false;
        }
    }

    function init(root) {
        if (roots.has(root)) {
            return;
        }
        roots.add(root);

        const toggle = root.querySelector('[data-floating-messages-toggle]');
        const csrfToken = root.dataset.csrfToken || '';
        const panel = root.querySelector('[data-floating-messages-panel]');
        const list = root.querySelector('[data-floating-messages-list]');

        toggle.addEventListener('click', () => {
            const opening = !root.classList.contains('is-menu-open');
            setCategoryMenuOpen(root, opening);
            if (opening) {
                setPanelOpen(root, false);
                root.dataset.activeCategory = '';
                root.querySelector('[data-floating-messages-error]').hidden = true;
                root.querySelector('[data-message-category]')?.focus();
            } else {
                toggle.focus();
            }
        });

        root.querySelector('[data-floating-messages-categories]').addEventListener('click', event => {
            const categoryButton = event.target.closest('[data-message-category]');
            if (categoryButton) {
                openCategory(root, categoryButton.dataset.messageCategory);
            }
        });

        root.querySelector('[data-floating-messages-close]').addEventListener('click', () => {
            setPanelOpen(root, false);
            toggle.focus();
        });

        list.addEventListener('click', event => {
            const callRequest = event.target.closest('[data-call-request]');
            if (callRequest) {
                event.preventDefault();
                event.stopPropagation();
                requestCall(
                    root,
                    Number(callRequest.dataset.conversationId),
                    callRequest.dataset.callMode
                ).catch(error => showCallRequestError(root, error));
                return;
            }
            const guestConversation = event.target.closest('[data-existing-private-conversation]');
            if (guestConversation) {
                openFloatingWindow(root, {
                    conversationType: 'private',
                    conversationId: Number(guestConversation.dataset.conversationId),
                    transactionId: 0,
                    displayName: guestConversation.dataset.conversationTitle || 'Guest Seller'
                }).catch(error => {
                    const errorPanel = root.querySelector('[data-floating-messages-error]');
                    errorPanel.textContent = error.message || 'Unable to open this guest conversation.';
                    errorPanel.hidden = false;
                });
                return;
            }
            const transaction = event.target.closest('[data-transaction-id]');
            if (transaction) {
                openTransactionWindow(
                    root,
                    Number(transaction.dataset.transactionId),
                    transaction.dataset.transactionReference || '',
                    Number(transaction.dataset.conversationId) || 0,
                    transaction.dataset.conversationType || 'transaction'
                );
                return;
            }
            const contact = event.target.closest('[data-target-type][data-target-id]');
            if (contact) {
                openDirectConversation(root, 'private', {
                    actor_type: contact.dataset.targetType,
                    actor_id: Number(contact.dataset.targetId)
                }).catch(error => {
                    const errorPanel = root.querySelector('[data-floating-messages-error]');
                    errorPanel.textContent = error.message || 'Unable to open this private conversation.';
                    errorPanel.hidden = false;
                });
            }
        });

        root.querySelector('[data-floating-chat-windows]').addEventListener('click', event => {
            const workspaceConversation = event.target.closest('[data-workspace-conversation]');
            if (workspaceConversation) {
                const windowElement = workspaceConversation.closest('[data-floating-chat-window]');
                openWorkspaceConversation(root, windowElement, workspaceConversation)
                    .catch(error => {
                        const list = windowElement.querySelector('[data-workspace-conversation-list]');
                        list.replaceChildren();
                        const message = document.createElement('p');
                        message.className = 'floating-messages-list-state';
                        message.textContent = error.message || 'Unable to open this conversation.';
                        list.append(message);
                    });
                return;
            }

            const maximize = event.target.closest('[data-chat-maximize]');
            if (maximize) {
                const windowElement = maximize.closest('[data-floating-chat-window]');
                const maximized = !windowElement.classList.contains('is-maximized');
                windowElement.classList.toggle('is-maximized', maximized);
                windowElement.classList.remove('is-minimized');
                windowElement.classList.toggle('is-mobile-chat-open', window.innerWidth > 700);
                maximize.setAttribute('aria-pressed', String(maximized));
                maximize.setAttribute(
                    'aria-label',
                    maximized ? 'Restore chat size' : 'Maximize chat'
                );
                maximize.title = maximized ? 'Restore chat size' : 'Maximize chat';
                maximize.textContent = maximized ? '↙' : '□';
                const minimize = windowElement.querySelector('[data-chat-minimize]');
                minimize?.setAttribute('aria-label', 'Minimize chat');
                if (maximized) {
                    refreshWorkspaceSidebar(root, windowElement).catch(error => {
                        const list = windowElement.querySelector('[data-workspace-conversation-list]');
                        list.replaceChildren();
                        const message = document.createElement('p');
                        message.className = 'floating-messages-list-state';
                        message.textContent = error.message || 'Unable to load conversations.';
                        list.append(message);
                    });
                }
                return;
            }

            const workspaceBack = event.target.closest('[data-chat-workspace-back]');
            if (workspaceBack) {
                const windowElement = workspaceBack.closest('[data-floating-chat-window]');
                if (!windowElement) {
                    return;
                }

                windowElement.classList.remove('is-maximized');
                windowElement.classList.add('is-workspace-list-open');
                windowElement.classList.remove('is-minimized', 'is-mobile-chat-open');
                const maximize = windowElement.querySelector('[data-chat-maximize]');
                maximize?.setAttribute('aria-pressed', 'false');
                maximize?.setAttribute('aria-label', 'Maximize chat');
                if (maximize) {
                    maximize.title = 'Maximize chat';
                    maximize.textContent = '□';
                }
                windowElement.querySelector('[data-chat-minimize]')
                    ?.setAttribute('aria-label', 'Minimize chat');
                updateWindowVisibility(root);
                refreshWorkspaceSidebar(root, windowElement).catch(error => {
                    const list = windowElement.querySelector('[data-workspace-conversation-list]');
                    list.replaceChildren();
                    const message = document.createElement('p');
                    message.className = 'floating-chat-window-error';
                    message.textContent = error.message || 'Unable to load conversations.';
                    list.append(message);
                });
                return;
            }

            const close = event.target.closest('[data-chat-close]');
            const minimize = event.target.closest('[data-chat-minimize]');
            if (close) {
                const windowElement = close.closest('[data-floating-chat-window]');
                preserveCallOwners(root, windowElement);
                windowElement?.classList.add('is-closing');
                if (windowElement) {
                    chatCloseTimers.set(windowElement, window.setTimeout(() => {
                        windowElement.remove();
                        chatCloseTimers.delete(windowElement);
                        updateWindowVisibility(root);
                    }, 160));
                }
                refreshUnread(root);
            } else if (minimize) {
                const windowElement = minimize.closest('[data-floating-chat-window]');
                windowElement?.classList.remove('is-maximized', 'is-mobile-chat-open');
                const maximizeButton = windowElement?.querySelector('[data-chat-maximize]');
                maximizeButton?.setAttribute('aria-pressed', 'false');
                maximizeButton?.setAttribute('aria-label', 'Maximize chat');
                if (maximizeButton) {
                    maximizeButton.title = 'Maximize chat';
                    maximizeButton.textContent = '□';
                }
                windowElement?.classList.toggle('is-minimized');
                updateWindowVisibility(root);
                minimize.setAttribute(
                    'aria-label',
                    windowElement?.classList.contains('is-minimized')
                        ? 'Restore chat'
                        : 'Minimize chat'
                );
                minimize.textContent = windowElement?.classList.contains('is-minimized')
                    ? '□'
                    : '—';
            }
        });

        document.addEventListener('click', event => {
            if (!root.contains(event.target) && root.classList.contains('is-menu-open')) {
                setCategoryMenuOpen(root, false);
            }

            const shortcut = event.target.closest('[data-open-transaction-chat]');
            if (shortcut) {
                event.preventDefault();
                openTransactionWindow(
                    root,
                    Number(shortcut.dataset.transactionId),
                    shortcut.dataset.transactionReference || ''
                );
            }
        });

        document.addEventListener('keydown', event => {
            if (event.key === 'Escape') {
                if (root.classList.contains('is-menu-open')) {
                    setCategoryMenuOpen(root, false);
                    toggle.focus();
                } else if (!panel.hidden) {
                    setPanelOpen(root, false);
                    toggle.focus();
                }
            }
        });

        window.openTransactionChat = transactionId => {
            setCategoryMenuOpen(root, false);
            setPanelOpen(root, false);
            return openTransactionWindow(root, Number(transactionId));
        };

        refreshEvents.forEach(eventName => {
            window.addEventListener(eventName, () => refreshUnread(root, true));
        });
        window.addEventListener('vnz:chat-presence-changed', () => refreshUnread(root));
        window.addEventListener('resize', () => updateWindowVisibility(root));
        window.addEventListener('vnz:call-request', event => {
            const conversationId = Number(event.detail?.conversationId);
            const mode = event.detail?.mode;
            requestCall(root, conversationId, mode)
                .catch(error => showCallRequestError(root, error));
        });
        window.addEventListener('vnz:call-media-error', event => {
            const message = event.detail?.message;
            if (typeof message === 'string' && message.trim() !== '') {
                showCallRequestError(root, new Error(message));
            }
        });
        window.addEventListener('vnz:call-controller-released', event => {
            const conversationId = Number(event.detail?.conversationId);
            if (Number.isInteger(conversationId)) {
                controllerMap(root).delete(conversationId);
            }
        });

        const sendPresenceHeartbeat = () => {
            fetch('/VNZ-TECH/backend/chat_presence.php', {
                method: 'POST',
                credentials: 'same-origin',
                cache: 'no-store',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8'
                },
                body: new URLSearchParams({
                    csrf_token: csrfToken
                })
            }).then(async response => {
                const payload = await response.json();
                if (!response.ok || payload.success !== true) {
                    throw new Error(payload.message || 'Presence heartbeat failed.');
                }
            }).catch(error => {
                console.warn('Chat presence heartbeat failed:', error.message);
            });
        };
        sendPresenceHeartbeat();
        window.setInterval(sendPresenceHeartbeat, 20000);

        refreshUnread(root);
        window.setInterval(() => refreshUnread(root), 15000);
        updateWindowVisibility(root);
        initializeCallInbox(root);
    }

    function scan(root = document) {
        if (root.matches?.('[data-floating-messages]')) {
            init(root);
        }
        root.querySelectorAll?.('[data-floating-messages]').forEach(init);
    }

    function start() {
        scan();
        const observer = new MutationObserver(records => {
            records.forEach(record => {
                record.addedNodes.forEach(node => {
                    if (node.nodeType === Node.ELEMENT_NODE) {
                        scan(node);
                    }
                });
            });
        });
        observer.observe(document.body, {childList: true, subtree: true});
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', start, {once: true});
    } else {
        start();
    }
})();
