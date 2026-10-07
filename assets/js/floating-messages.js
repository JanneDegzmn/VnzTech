(() => {
    const roots = new WeakSet();
    const panelCloseTimers = new WeakMap();
    const chatCloseTimers = new WeakMap();
    const pendingOpens = new WeakMap();
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
            contacts: 0
        };
        let total = 0;

        conversations.forEach(conversation => {
            const unread = Number(conversation.unread_count) || 0;
            total += unread;
            if (conversation.conversation_type === 'global') {
                totals.global += unread;
            } else if (conversation.conversation_type === 'private') {
                totals.private += unread;
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
                root.dataset.conversations = JSON.stringify(conversations);
                updateUnreadBadges(root, conversations, pulse);
                const panel = root.querySelector('[data-floating-messages-panel]');
                const category = root.dataset.activeCategory;
                if (!panel.hidden && category) {
                    refreshSelection(root, category, conversations);
                }
            })
            .catch(error => {
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
        const transactionItems = conversations.filter(
            conversation => conversation.conversation_type === 'transaction'
        );

        if (category === 'contacts' && transactionItems.length > 0) {
            addHeading('TRANSACTION CHATS');
            transactionItems.forEach(conversation => {
                const button = document.createElement('button');
                const title = document.createElement('strong');
                const reference = document.createElement('span');
                const status = document.createElement('small');
                button.type = 'button';
                button.className = 'floating-messages-conversation';
                button.dataset.transactionId = String(conversation.transaction_id);
                button.dataset.transactionReference = String(conversation.transaction_reference || '');
                title.textContent = String(conversation.display_name || 'ADMIN');
                reference.textContent = String(
                    conversation.transaction_reference || 'Transaction'
                );
                status.textContent = String(conversation.transaction_status || 'pending')
                    .replaceAll('_', ' ');
                button.append(title, reference, status);
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
                const button = document.createElement('button');
                const avatar = document.createElement('span');
                const name = document.createElement('strong');
                const status = document.createElement('small');
                button.type = 'button';
                button.className = 'floating-messages-conversation is-contact';
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
                    ? (String(contact.last_message || '').trim() || 'Private conversation')
                    : 'Start private conversation';
                button.append(avatar, name, status);
                addUnread(
                    button,
                    unreadByConversationId.get(Number(contact.conversation_id)) || 0
                );
                list.append(button);
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
            if (category === 'contacts' || !Array.isArray(availableConversations)) {
                try {
                    const conversationData = await getJson(
                        '/VNZ-TECH/backend/chat_conversations.php?action=list'
                    );
                    availableConversations = conversationData.conversations || [];
                } catch (error) {
                    console.error('Unable to load transaction conversation list:', error);
                    if (category === 'contacts') {
                        throw error;
                    }
                    availableConversations = [];
                }
            }
            if (root.dataset.activeCategory !== category) {
                return;
            }
            renderSelectionList(
                root,
                contacts,
                availableConversations,
                category
            );
        } catch (error) {
            if (root.dataset.activeCategory === category && !root.querySelector('[data-floating-messages-panel]').hidden) {
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
        const actorType = root.dataset.actorType;
        const endpoint = actorType === 'admin'
            ? '/VNZ-TECH/admin/pages/transaction-chat-window.php'
            : '/VNZ-TECH/account/pages/transaction-chat-window.php';
        const requestId = details.conversationType === 'transaction'
            ? details.transactionId
            : details.conversationId;
        const requestField = details.conversationType === 'transaction'
            ? 'transaction_id'
            : 'conversation_id';
        const windowElement = document.createElement('section');
        const header = document.createElement('header');
        const title = document.createElement('strong');
        const actions = document.createElement('div');
        const minimizeButton = document.createElement('button');
        const closeButton = document.createElement('button');
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
        title.textContent = details.displayName
            || details.reference
            || 'Conversation';
        actions.className = 'floating-chat-window-actions';
        minimizeButton.type = 'button';
        minimizeButton.dataset.chatMinimize = '';
        minimizeButton.setAttribute('aria-label', 'Minimize chat');
        minimizeButton.textContent = '—';
        closeButton.type = 'button';
        closeButton.dataset.chatClose = '';
        closeButton.setAttribute('aria-label', 'Close chat');
        closeButton.textContent = '×';
        body.className = 'floating-chat-window-body';
        body.innerHTML = '<p class="floating-chat-window-loading">Opening conversation…</p>';
        actions.append(minimizeButton, closeButton);
        header.append(title, actions);
        windowElement.append(header, body);
        windows.prepend(windowElement);
        updateWindowVisibility(root);

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
            const headerReference = widget?.dataset.transactionReference;
            const conversationTitle = widget?.dataset.conversationTitle;
            if (details.conversationType === 'transaction' && headerReference) {
                title.textContent = headerReference;
            } else if (conversationTitle) {
                title.textContent = conversationTitle;
            }
            widget?.querySelector('[data-chat-input]')?.focus();
            windowElement.classList.remove('is-opening');
            windowElement.classList.add('is-open');
            refreshUnread(root);
        } catch (error) {
            body.replaceChildren();
            const message = document.createElement('p');
            message.className = 'floating-chat-window-error';
            message.textContent = error.message || 'Unable to open this conversation.';
            body.append(message);
        }
    }

    async function openTransactionWindow(root, transactionId, reference = '') {
        if (!Number.isInteger(transactionId) || transactionId <= 0) {
            return;
        }
        return openFloatingWindow(root, {
            conversationType: 'transaction',
            transactionId,
            conversationId: 0,
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
                displayName: existing.querySelector('.floating-chat-window-header > strong')?.textContent
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
            const transaction = event.target.closest('[data-transaction-id]');
            if (transaction) {
                openTransactionWindow(
                    root,
                    Number(transaction.dataset.transactionId),
                    transaction.dataset.transactionReference || ''
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
            const close = event.target.closest('[data-chat-close]');
            const minimize = event.target.closest('[data-chat-minimize]');
            if (close) {
                const windowElement = close.closest('[data-floating-chat-window]');
                windowElement?.classList.add('is-closing');
                if (windowElement) {
                    chatCloseTimers.set(windowElement, window.setTimeout(() => {
                        windowElement.remove();
                        chatCloseTimers.delete(windowElement);
                    }, 160));
                }
                refreshUnread(root);
            } else if (minimize) {
                const windowElement = minimize.closest('[data-floating-chat-window]');
                windowElement?.classList.toggle('is-minimized');
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
        window.addEventListener('resize', () => updateWindowVisibility(root));

        refreshUnread(root);
        window.setInterval(() => refreshUnread(root), 15000);
        updateWindowVisibility(root);
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
