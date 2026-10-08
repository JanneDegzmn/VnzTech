(() => {
    const activeChats = new Map();
    const bookMeCommand = '/bookme';
    const shareLocationUrl = 'https://maps.app.goo.gl/iqFq3UGQuqybbY6VA';
    const lalamoveBookingUrl = 'https://www.lalamove.com/en-ph/';
    const landmarkImageUrl = '/VNZ-TECH/assets/images/location.jpg';
    const bookingMessagePrefix = 'LALAMOVE_BOOKING_V1';
    const emojiCategories = [
        {
            id: 'smileys',
            label: 'Smileys',
            icon: '😀',
            emojis: [
                ['😀', 'grinning face'], ['😃', 'smiling face'], ['😄', 'smiling eyes'],
                ['😁', 'beaming face'], ['😆', 'laughing face'], ['😅', 'nervous laugh'],
                ['😂', 'tears of joy'], ['🤣', 'rolling laughter'], ['😊', 'blushing smile'],
                ['😇', 'halo'], ['🙂', 'slight smile'], ['😉', 'wink'],
                ['😍', 'heart eyes'], ['🥰', 'hearts'], ['😘', 'blowing kiss'],
                ['😋', 'tasty'], ['😎', 'sunglasses'], ['🤔', 'thinking'],
                ['🫡', 'salute'], ['🥳', 'celebration'], ['😴', 'sleepy'],
                ['😭', 'crying'], ['😡', 'angry'], ['🤯', 'mind blown']
            ]
        },
        {
            id: 'people',
            label: 'People',
            icon: '❤️',
            emojis: [
                ['❤️', 'red heart'], ['🧡', 'orange heart'], ['💛', 'yellow heart'],
                ['💚', 'green heart'], ['💙', 'blue heart'], ['💜', 'purple heart'],
                ['🖤', 'black heart'], ['🤍', 'white heart'], ['💔', 'broken heart'],
                ['👍', 'thumbs up'], ['👎', 'thumbs down'], ['👏', 'clapping'],
                ['🙌', 'raised hands'], ['🙏', 'folded hands'], ['🤝', 'handshake'],
                ['💪', 'flexed biceps'], ['👋', 'waving hand'], ['👌', 'ok hand'],
                ['🫶', 'heart hands'], ['💋', 'kiss mark'], ['✨', 'sparkles']
            ]
        },
        {
            id: 'animals',
            label: 'Animals',
            icon: '🐶',
            emojis: [
                ['🐶', 'dog'], ['🐱', 'cat'], ['🐭', 'mouse'], ['🐹', 'hamster'],
                ['🐰', 'rabbit'], ['🦊', 'fox'], ['🐻', 'bear'], ['🐼', 'panda'],
                ['🐨', 'koala'], ['🐯', 'tiger'], ['🦁', 'lion'], ['🐮', 'cow'],
                ['🐸', 'frog'], ['🐵', 'monkey'], ['🐔', 'chicken'], ['🐧', 'penguin'],
                ['🐦', 'bird'], ['🦋', 'butterfly'], ['🐢', 'turtle'], ['🐬', 'dolphin'],
                ['🐳', 'whale'], ['🦄', 'unicorn'], ['🐝', 'bee'], ['🐾', 'paw prints']
            ]
        },
        {
            id: 'food',
            label: 'Food',
            icon: '🍔',
            emojis: [
                ['🍏', 'green apple'], ['🍎', 'red apple'], ['🍌', 'banana'],
                ['🍉', 'watermelon'], ['🍇', 'grapes'], ['🍓', 'strawberry'],
                ['🥑', 'avocado'], ['🍅', 'tomato'], ['🥕', 'carrot'],
                ['🍕', 'pizza'], ['🍔', 'burger'], ['🍟', 'fries'],
                ['🌭', 'hot dog'], ['🥪', 'sandwich'], ['🌮', 'taco'],
                ['🍜', 'noodles'], ['🍣', 'sushi'], ['🍩', 'doughnut'],
                ['🍰', 'cake'], ['🍪', 'cookie'], ['☕', 'coffee'],
                ['🧋', 'bubble tea'], ['🥤', 'drink'], ['🍺', 'beer']
            ]
        },
        {
            id: 'activities',
            label: 'Activities',
            icon: '⚽',
            emojis: [
                ['⚽', 'soccer'], ['🏀', 'basketball'], ['🏈', 'football'],
                ['⚾', 'baseball'], ['🎾', 'tennis'], ['🏐', 'volleyball'],
                ['🏓', 'ping pong'], ['🏸', 'badminton'], ['🥊', 'boxing'],
                ['🎮', 'video game'], ['🎯', 'bullseye'], ['🎳', 'bowling'],
                ['🎸', 'guitar'], ['🎹', 'piano'], ['🎤', 'microphone'],
                ['🎧', 'headphones'], ['🎨', 'art'], ['🎬', 'movie'],
                ['🏆', 'trophy'], ['🎉', 'party popper'], ['🎁', 'gift']
            ]
        },
        {
            id: 'travel',
            label: 'Travel',
            icon: '🚗',
            emojis: [
                ['🚗', 'car'], ['🚕', 'taxi'], ['🚌', 'bus'], ['🚎', 'trolleybus'],
                ['🏍️', 'motorcycle'], ['🚲', 'bicycle'], ['🚆', 'train'],
                ['✈️', 'airplane'], ['🚁', 'helicopter'], ['🚀', 'rocket'],
                ['⛵', 'sailboat'], ['🚢', 'ship'], ['🏠', 'house'],
                ['🏢', 'office building'], ['🏖️', 'beach'], ['🏝️', 'island'],
                ['🌋', 'volcano'], ['⛰️', 'mountain'], ['🌇', 'sunset'],
                ['🌈', 'rainbow'], ['🌧️', 'rain'], ['☀️', 'sun']
            ]
        },
        {
            id: 'objects',
            label: 'Objects',
            icon: '💡',
            emojis: [
                ['💡', 'light bulb'], ['🔦', 'flashlight'], ['📱', 'mobile phone'],
                ['💻', 'laptop'], ['⌨️', 'keyboard'], ['🖥️', 'desktop computer'],
                ['📷', 'camera'], ['🔋', 'battery'], ['🔌', 'plug'],
                ['💰', 'money bag'], ['💳', 'credit card'], ['💎', 'gem'],
                ['🔑', 'key'], ['🛠️', 'tools'], ['🔧', 'wrench'],
                ['📦', 'package'], ['📌', 'pin'], ['📝', 'memo'],
                ['📚', 'books'], ['🕯️', 'candle'], ['🧸', 'teddy bear']
            ]
        },
        {
            id: 'symbols',
            label: 'Symbols',
            icon: '🔣',
            emojis: [
                ['✅', 'check mark'], ['☑️', 'checked box'], ['❌', 'cross mark'],
                ['⚠️', 'warning'], ['❓', 'question mark'], ['❗', 'exclamation'],
                ['💯', 'hundred points'], ['♻️', 'recycling'], ['🔔', 'bell'],
                ['🔕', 'muted bell'], ['⭐', 'star'], ['🌟', 'glowing star'],
                ['💤', 'sleep'], ['♻️', 'recycle symbol'], ['➕', 'plus'],
                ['➖', 'minus'], ['➡️', 'right arrow'], ['⬆️', 'up arrow'],
                ['🔒', 'locked'], ['🔓', 'unlocked'], ['❤️‍🔥', 'heart on fire']
            ]
        },
        {
            id: 'flags',
            label: 'Flags',
            icon: '🏳️',
            emojis: [
                ['🏳️', 'white flag'], ['🏴', 'black flag'], ['🏁', 'checkered flag'],
                ['🚩', 'red flag'], ['🇵🇭', 'Philippines'], ['🇺🇸', 'United States'],
                ['🇬🇧', 'United Kingdom'], ['🇨🇦', 'Canada'], ['🇦🇺', 'Australia'],
                ['🇯🇵', 'Japan'], ['🇰🇷', 'South Korea'], ['🇨🇳', 'China'],
                ['🇸🇬', 'Singapore'], ['🇹🇭', 'Thailand'], ['🇮🇩', 'Indonesia'],
                ['🇲🇾', 'Malaysia'], ['🇻🇳', 'Vietnam'], ['🇮🇳', 'India'],
                ['🇫🇷', 'France'], ['🇩🇪', 'Germany'], ['🇮🇹', 'Italy']
            ]
        }
    ];
    let landmarkDialogSequence = 0;
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

    function getMessageContentText(message) {
        const rawContent = String(message.content || '');
        if (message.deleted_at) {
            return 'This message was deleted';
        }
        return rawContent;
    }

    function buildReactionMap(reactions) {
        const map = new Map();
        (Array.isArray(reactions) ? reactions : []).forEach(entry => {
            if (!entry || !entry.reaction) {
                return;
            }
            map.set(entry.reaction, {
                reaction: entry.reaction,
                count: Number(entry.count) || 0,
                active: Boolean(entry.active),
                users: Array.isArray(entry.users) ? entry.users : []
            });
        });
        return map;
    }

    function renderMessage(
        widget,
        message,
        currentActorType,
        currentActorId,
        conversationType
    ) {
        const row = document.createElement('article');
        row.tabIndex = 0;
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

        const replyPreview = document.createElement('button');
        replyPreview.className = 'transaction-chat-reply-preview';
        const replyData = message.reply || null;
        if (replyData && replyData.id) {
            replyPreview.type = 'button';
            const replySender = document.createElement('span');
            replySender.className = 'transaction-chat-reply-sender';
            replySender.textContent = `↳ ${replyData.sender_display_name || 'Reply'}`;
            const replyText = document.createElement('span');
            replyText.className = 'transaction-chat-reply-text';
            replyText.textContent = replyData.preview || (replyData.content || 'This message was deleted');
            replyPreview.append(replySender, replyText);
            replyPreview.dataset.replyMessageId = String(replyData.id);
            replyPreview.title = 'Jump to the original message';
            replyPreview.setAttribute(
                'aria-label',
                `Reply to ${replyData.sender_display_name || 'message'}: ${replyText.textContent}`
            );
            replyPreview.addEventListener('click', () => {
                const target = document.querySelector(`.transaction-chat-message[data-message-id="${CSS.escape(String(replyData.id))}"]`);
                if (target) {
                    target.scrollIntoView({behavior: 'smooth', block: 'center'});
                    target.classList.add('is-highlighted');
                    setTimeout(() => target.classList.remove('is-highlighted'), 1600);
                }
            });
        }

        const content = document.createElement('p');
        content.className = 'transaction-chat-message-content';
        const messageContent = getMessageContentText(message);
        const bookingDetails = messageContent.split('\n');
        if (
            message.sender_type === 'admin'
            && bookingDetails.length === 4
            && bookingDetails[0] === bookingMessagePrefix
            && bookingDetails[1] === lalamoveBookingUrl
        ) {
            const card = document.createElement('section');
            card.className = 'transaction-chat-booking-card';

            const heading = document.createElement('strong');
            heading.className = 'transaction-chat-booking-title';
            heading.textContent = 'Lalamove delivery';

            const note = document.createElement('p');
            note.className = 'transaction-chat-booking-note';
            note.textContent = 'Open Lalamove to book, then enter this drop-off location.';

            const bookingLink = document.createElement('a');
            bookingLink.className = 'transaction-chat-booking-link';
            bookingLink.href = lalamoveBookingUrl;
            bookingLink.target = '_blank';
            bookingLink.rel = 'noopener noreferrer';
            bookingLink.textContent = 'Open Lalamove';

            const destination = document.createElement('div');
            destination.className = 'transaction-chat-booking-detail';
            const destinationLabel = document.createElement('span');
            destinationLabel.textContent = 'DROP-OFF';
            const destinationAddress = document.createElement('strong');
            destinationAddress.textContent = bookingDetails[2];

            const landmark = document.createElement('div');
            landmark.className = 'transaction-chat-booking-detail';
            const landmarkLabel = document.createElement('span');
            landmarkLabel.textContent = 'LANDMARK';
            const landmarkValue = document.createElement('strong');
            landmarkValue.textContent = bookingDetails[3];

            const landmarkPreview = document.createElement('button');
            landmarkPreview.className = 'transaction-chat-landmark-preview';
            landmarkPreview.type = 'button';
            landmarkPreview.dataset.landmarkPreview = 'true';
            landmarkPreview.setAttribute('aria-label', 'View landmark photo');
            const landmarkImage = document.createElement('img');
            landmarkImage.src = landmarkImageUrl;
            landmarkImage.alt = 'Landmark photo showing MJ’s Kitchen across from VNZ TECH';
            landmarkImage.loading = 'lazy';
            const previewHint = document.createElement('span');
            previewHint.textContent = 'View landmark photo';
            landmarkPreview.append(landmarkImage, previewHint);

            const copyButton = document.createElement('button');
            copyButton.className = 'transaction-chat-booking-copy';
            copyButton.type = 'button';
            copyButton.dataset.copyValue = bookingDetails[2];
            copyButton.textContent = 'Copy drop-off address';

            destination.append(destinationLabel, destinationAddress);
            landmark.append(landmarkLabel, landmarkValue);
            card.append(
                heading,
                note,
                bookingLink,
                destination,
                landmark,
                landmarkPreview,
                copyButton
            );
            content.append(card);
        } else if (messageContent === shareLocationUrl) {
            const locationLink = document.createElement('a');
            locationLink.href = shareLocationUrl;
            locationLink.target = '_blank';
            locationLink.rel = 'noopener noreferrer';
            locationLink.textContent = shareLocationUrl;
            content.append(locationLink);
        } else {
            content.textContent = messageContent;
        }

        const statusLine = document.createElement('div');
        statusLine.className = 'transaction-chat-message-status';
        if (message.is_edited || message.edited_at) {
            const edited = document.createElement('span');
            edited.className = 'transaction-chat-edited-indicator';
            edited.textContent = '(edited)';
            statusLine.append(edited);
        }

        const reactionChips = document.createElement('div');
        reactionChips.className = 'transaction-chat-reaction-chips';
        const reactionMap = buildReactionMap(message.reactions);
        const reactionOptions = ['👍', '❤️', '😂', '😮', '😢', '😡'];
        reactionOptions.forEach(reaction => {
            const reactionValue = reactionMap.get(reaction) || {count: 0, active: false};
            if (reactionValue.count < 1) {
                return;
            }

            const chip = document.createElement('button');
            chip.type = 'button';
            chip.className = `transaction-chat-reaction-chip${reactionValue.active ? ' is-active' : ''}`;
            chip.dataset.chatReaction = 'true';
            chip.dataset.messageId = String(message.id);
            chip.dataset.reaction = reaction;
            chip.setAttribute('aria-label', `${reactionValue.count} reactions: ${(reactionValue.users || []).join(', ')}`);

            const emoji = document.createElement('span');
            emoji.setAttribute('aria-hidden', 'true');
            emoji.textContent = reaction;
            const count = document.createElement('span');
            count.textContent = String(reactionValue.count);
            const people = document.createElement('span');
            people.className = 'transaction-chat-reaction-people';
            people.textContent = `${reaction} ${reactionValue.count}: ${(reactionValue.users || []).join(', ')}`;
            chip.append(emoji, count, people);
            reactionChips.append(chip);
        });

        const actionToolbar = document.createElement('div');
        actionToolbar.className = 'transaction-chat-message-actions';
        actionToolbar.setAttribute('aria-label', 'Message actions');
        reactionOptions.forEach(reaction => {
            const reactionValue = reactionMap.get(reaction) || {active: false};
            const reactionButton = document.createElement('button');
            reactionButton.type = 'button';
            reactionButton.className = `transaction-chat-reaction-button${reactionValue.active ? ' is-active' : ''}`;
            reactionButton.dataset.chatReaction = 'true';
            reactionButton.dataset.messageId = String(message.id);
            reactionButton.dataset.reaction = reaction;
            reactionButton.setAttribute('aria-label', `React ${reaction}`);
            reactionButton.title = `React ${reaction}`;
            reactionButton.textContent = reaction;
            actionToolbar.append(reactionButton);
        });

        const replyAction = document.createElement('button');
        replyAction.type = 'button';
        replyAction.className = 'transaction-chat-action-button';
        replyAction.dataset.chatReply = 'true';
        replyAction.dataset.messageId = String(message.id);
        replyAction.setAttribute('aria-label', 'Reply to message');
        replyAction.title = 'Reply';
        replyAction.textContent = 'Reply';
        actionToolbar.append(replyAction);

        const actionsToggle = document.createElement('button');
        actionsToggle.type = 'button';
        actionsToggle.className = 'transaction-chat-message-actions-toggle';
        actionsToggle.dataset.chatActionsToggle = 'true';
        actionsToggle.setAttribute('aria-label', 'Show message actions');
        actionsToggle.setAttribute('aria-expanded', 'false');
        actionsToggle.textContent = '⋯';

        if (ownMessage) {
            const menuWrap = document.createElement('div');
            menuWrap.className = 'transaction-chat-more-wrap';
            const moreButton = document.createElement('button');
            moreButton.type = 'button';
            moreButton.className = 'transaction-chat-more-button';
            moreButton.dataset.chatMore = 'true';
            moreButton.setAttribute('aria-label', 'More message actions');
            moreButton.setAttribute('aria-haspopup', 'menu');
            moreButton.setAttribute('aria-expanded', 'false');
            moreButton.title = 'More actions';
            moreButton.textContent = '⋯';

            const menu = document.createElement('div');
            menu.className = 'transaction-chat-message-menu';
            menu.setAttribute('role', 'menu');
            menu.hidden = true;

            const editAction = document.createElement('button');
            editAction.type = 'button';
            editAction.className = 'transaction-chat-menu-item';
            editAction.dataset.chatEdit = 'true';
            editAction.dataset.messageId = String(message.id);
            editAction.setAttribute('role', 'menuitem');
            editAction.textContent = 'Edit';

            const deleteAction = document.createElement('button');
            deleteAction.type = 'button';
            deleteAction.className = 'transaction-chat-menu-item is-danger';
            deleteAction.dataset.chatDelete = 'true';
            deleteAction.dataset.messageId = String(message.id);
            deleteAction.setAttribute('role', 'menuitem');
            deleteAction.textContent = 'Delete';

            menu.append(editAction, deleteAction);
            menuWrap.append(moreButton, menu);
            actionToolbar.append(menuWrap);
        }

        meta.append(sender, time);
        bubble.append(meta);
        if (replyPreview.childNodes.length) {
            bubble.append(replyPreview);
        }

        bubble.append(content, statusLine);
        row.append(bubble);
        row.append(actionsToggle);
        if (reactionChips.childElementCount) {
            row.append(reactionChips);
        }
        row.append(actionToolbar);
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
        const emojiToggle = widget.querySelector('[data-chat-emoji-toggle]');
        const loadOlderButton = widget.querySelector('[data-chat-load-older]');
        const widgetEventController = new AbortController();
        const typingIndicator = document.createElement('div');
        typingIndicator.className = 'transaction-chat-typing-indicator';
        typingIndicator.hidden = true;
        typingIndicator.setAttribute('role', 'status');
        typingIndicator.setAttribute('aria-live', 'polite');
        typingIndicator.setAttribute('aria-label', 'Another participant is typing');
        const typingDots = document.createElement('span');
        typingDots.className = 'transaction-chat-typing-dots';
        typingDots.setAttribute('aria-hidden', 'true');
        for (let index = 0; index < 3; index++) {
            const dot = document.createElement('span');
            dot.className = 'transaction-chat-typing-dot';
            typingDots.append(dot);
        }
        const typingNames = document.createElement('span');
        typingNames.className = 'transaction-chat-typing-names';
        typingIndicator.append(typingNames, typingDots);
        const composeState = document.createElement('div');
        composeState.className = 'transaction-chat-compose-state';
        composeState.hidden = true;
        form.appendChild(composeState);
        const emojiPicker = document.createElement('section');
        emojiPicker.className = 'transaction-chat-emoji-picker';
        emojiPicker.hidden = true;
        emojiPicker.setAttribute('aria-label', 'Emoji library');
        const emojiSearch = document.createElement('input');
        emojiSearch.className = 'transaction-chat-emoji-search';
        emojiSearch.type = 'search';
        emojiSearch.placeholder = 'Search emoji';
        emojiSearch.setAttribute('aria-label', 'Search emoji');
        const emojiCategoryList = document.createElement('div');
        emojiCategoryList.className = 'transaction-chat-emoji-categories';
        emojiCategoryList.setAttribute('role', 'tablist');
        emojiCategoryList.setAttribute('aria-label', 'Emoji categories');
        const emojiGrid = document.createElement('div');
        emojiGrid.className = 'transaction-chat-emoji-grid';
        emojiGrid.setAttribute('role', 'group');
        emojiGrid.setAttribute('aria-label', 'Emoji results');
        const emojiEmpty = document.createElement('p');
        emojiEmpty.className = 'transaction-chat-emoji-empty';
        emojiEmpty.textContent = 'No matching emoji';
        emojiEmpty.hidden = true;
        emojiPicker.append(emojiSearch, emojiCategoryList, emojiGrid, emojiEmpty);
        form.appendChild(emojiPicker);
        let activeEmojiCategory = emojiCategories[0].id;
        emojiCategories.forEach((category, index) => {
            const categoryButton = document.createElement('button');
            categoryButton.type = 'button';
            categoryButton.className = 'transaction-chat-emoji-category';
            categoryButton.dataset.emojiCategory = category.id;
            categoryButton.setAttribute('role', 'tab');
            categoryButton.setAttribute('aria-label', category.label);
            categoryButton.setAttribute('aria-selected', String(index === 0));
            categoryButton.title = category.label;
            categoryButton.textContent = category.icon;
            emojiCategoryList.appendChild(categoryButton);
        });

        function renderEmojiGrid() {
            const query = emojiSearch.value.trim().toLocaleLowerCase();
            const activeCategory = emojiCategories.find(category => category.id === activeEmojiCategory);
            const results = (query
                ? emojiCategories.flatMap(category => category.emojis)
                : activeCategory.emojis
            ).filter(([, name]) => !query || name.toLocaleLowerCase().includes(query));
            emojiGrid.replaceChildren();
            results.forEach(([emoji, name]) => {
                const emojiButton = document.createElement('button');
                emojiButton.type = 'button';
                emojiButton.className = 'transaction-chat-emoji-option';
                emojiButton.dataset.emojiValue = emoji;
                emojiButton.setAttribute('aria-label', name);
                emojiButton.title = name;
                emojiButton.textContent = emoji;
                emojiGrid.appendChild(emojiButton);
            });
            emojiEmpty.hidden = results.length > 0;
            emojiGrid.hidden = results.length === 0;
        }

        function closeEmojiPicker() {
            emojiPicker.hidden = true;
            emojiToggle.setAttribute('aria-expanded', 'false');
        }

        renderEmojiGrid();
        emojiToggle.addEventListener('click', () => {
            const opening = emojiPicker.hidden;
            emojiPicker.hidden = !opening;
            emojiToggle.setAttribute('aria-expanded', String(opening));
            if (opening) {
                emojiSearch.focus();
            }
        });
        emojiCategoryList.addEventListener('click', event => {
            const categoryButton = event.target.closest('[data-emoji-category]');
            if (!categoryButton) {
                return;
            }
            activeEmojiCategory = categoryButton.dataset.emojiCategory;
            emojiCategoryList.querySelectorAll('[data-emoji-category]').forEach(button => {
                button.setAttribute('aria-selected', String(button === categoryButton));
            });
            emojiSearch.value = '';
            renderEmojiGrid();
        });
        emojiSearch.addEventListener('input', renderEmojiGrid);
        emojiGrid.addEventListener('click', event => {
            const emojiButton = event.target.closest('[data-emoji-value]');
            if (!emojiButton) {
                return;
            }
            const start = input.selectionStart ?? input.value.length;
            const end = input.selectionEnd ?? start;
            const emoji = emojiButton.dataset.emojiValue;
            if (input.value.length - (end - start) + emoji.length > input.maxLength) {
                setStatus('Message limit reached. Remove some text to add an emoji.', 'warning');
                return;
            }
            input.setRangeText(emoji, start, end, 'end');
            input.dispatchEvent(new Event('input', {bubbles: true}));
            input.focus();
        });
        emojiPicker.addEventListener('keydown', event => {
            if (event.key === 'Escape') {
                closeEmojiPicker();
                emojiToggle.focus();
            }
        });
        document.addEventListener('click', event => {
            if (!emojiPicker.contains(event.target) && !emojiToggle.contains(event.target)) {
                closeEmojiPicker();
            }
        }, {signal: widgetEventController.signal});

        const messageMap = new Map();
        const landmarkDialog = document.createElement('dialog');
        landmarkDialog.className = 'transaction-chat-landmark-dialog';
        const dialogTitleId = `transaction-chat-landmark-title-${++landmarkDialogSequence}`;
        landmarkDialog.setAttribute('aria-labelledby', dialogTitleId);

        const dialogPanel = document.createElement('div');
        dialogPanel.className = 'transaction-chat-landmark-dialog-panel';
        const dialogHeader = document.createElement('header');
        dialogHeader.className = 'transaction-chat-landmark-dialog-header';
        const dialogTitle = document.createElement('h2');
        dialogTitle.id = dialogTitleId;
        dialogTitle.textContent = 'Landmark · MJ’s Kitchen';
        const dialogClose = document.createElement('button');
        dialogClose.className = 'transaction-chat-landmark-dialog-close';
        dialogClose.type = 'button';
        dialogClose.setAttribute('aria-label', 'Close landmark photo');
        dialogClose.textContent = '×';
        const dialogImage = document.createElement('img');
        dialogImage.className = 'transaction-chat-landmark-dialog-image';
        dialogImage.src = landmarkImageUrl;
        dialogImage.alt = 'Landmark photo showing MJ’s Kitchen across from VNZ TECH';
        const dialogCaption = document.createElement('p');
        dialogCaption.textContent = 'MJ’s Kitchen is directly across from VNZ TECH.';
        dialogHeader.append(dialogTitle, dialogClose);
        dialogPanel.append(dialogHeader, dialogImage, dialogCaption);
        landmarkDialog.append(dialogPanel);
        document.body.append(landmarkDialog);

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
        let editingMessageId = null;
        let replyToMessageId = null;
        let typingTimer = null;
        let typingUsers = new Map();
        let typingRequest = Promise.resolve();
        let typingActive = false;
        let activeRoomName = null;
        let realtimeReady = false;

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

        function clearComposerState() {
            editingMessageId = null;
            replyToMessageId = null;
            composeState.hidden = true;
            composeState.innerHTML = '';
        }

        function setComposerState(mode, messageId = null) {
            composeState.hidden = false;
            composeState.innerHTML = '';

            const label = document.createElement('span');
            label.className = 'transaction-chat-compose-state-label';
            const replyTarget = mode === 'reply'
                ? messageMap.get(Number(messageId))
                : null;
            label.textContent = mode === 'edit'
                ? 'Editing message'
                : `Replying to ${replyTarget?.sender_display_name || 'message'}`;
            composeState.appendChild(label);

            if (mode === 'edit' || mode === 'reply') {
                const cancelButton = document.createElement('button');
                cancelButton.type = 'button';
                cancelButton.className = 'transaction-chat-compose-cancel';
                cancelButton.textContent = 'Cancel';
                cancelButton.addEventListener('click', () => {
                    if (mode === 'edit') {
                        editingMessageId = null;
                        input.value = '';
                    }
                    clearComposerState();
                    input.focus();
                });
                composeState.appendChild(cancelButton);
            }

            if (mode === 'edit') {
                editingMessageId = Number(messageId);
                input.focus();
            }
            if (mode === 'reply') {
                replyToMessageId = Number(messageId);
                input.focus();
            }
        }

        function publishTypingState(isTyping) {
            if (
                !actualConversationId
                || !activeRoomName
                || !realtimeReady
                || disposed
            ) {
                return;
            }

            typingRequest = typingRequest.then(async () => {
                if (disposed || !realtimeReady || !activeRoomName) {
                    return;
                }

                await postForm('/VNZ-TECH/backend/chat_messages.php', {
                    action: 'typing',
                    conversation_id: String(actualConversationId),
                    state: isTyping ? 'started' : 'stopped',
                    csrf_token: csrfToken
                });
            }).catch(error => {
                if (!disposed) {
                    setStatus(`Typing status could not be delivered: ${error.message}`, 'warning');
                }
            });

            return typingRequest;
        }

        function renderTypingStatus() {
            const names = Array.from(typingUsers.values())
                .map(entry => entry.name)
                .filter(Boolean);
            const typingLabel = names.length === 1
                ? `${names[0]} is typing`
                : names.length === 2
                    ? `${names[0]} and ${names[1]} are typing`
                    : names.length > 2
                        ? `${names[0]}, ${names[1]} and ${names.length - 2} ${names.length - 2 === 1 ? 'other' : 'others'} are typing`
                        : '';
            typingIndicator.hidden = names.length === 0;
            typingNames.textContent = typingLabel;
            typingIndicator.setAttribute('aria-label', typingLabel || 'No one is typing');
        }

        function handleTypingEvent(payload) {
            if (!payload || Number(payload.conversation_id) !== actualConversationId) {
                return;
            }

            if (
                payload.actor_type === currentActorType
                && Number(payload.actor_id) === currentActorId
            ) {
                return;
            }

            const actorKey = `${payload.actor_type}:${payload.actor_id}`;
            const actorName = payload.actor_type === 'admin'
                ? 'Admin'
                : payload.display_name || 'Participant';
            if (payload.event === 'typing.started') {
                const current = typingUsers.get(actorKey);
                if (current) {
                    clearTimeout(current.timer);
                }
                const timer = setTimeout(() => {
                    typingUsers.delete(actorKey);
                    renderTypingStatus();
                }, 4000);
                typingUsers.set(actorKey, {name: actorName, timer});
            } else if (payload.event === 'typing.stopped') {
                const current = typingUsers.get(actorKey);
                if (current) {
                    clearTimeout(current.timer);
                }
                typingUsers.delete(actorKey);
            }

            renderTypingStatus();
        }

        dialogClose.addEventListener('click', () => landmarkDialog.close());
        landmarkDialog.addEventListener('click', event => {
            if (event.target === landmarkDialog) {
                landmarkDialog.close();
            }
        });

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

            messageList.replaceChildren(fragment, emptyState, typingIndicator);
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
                realtimeReady = false;
                activeRoomName = roomName;
                const subscription = drone.subscribe(roomName);
                roomSubscription = subscription;

                subscription.on('open', error => {
                    if (error) {
                        roomSubscription = null;
                        activeRoomName = null;
                        realtimeReady = false;
                        reject(new Error('Could not subscribe to this conversation.'));
                        return;
                    }

                    realtimeReady = true;
                    subscription.on('message', event => {
                        const payload = event && event.data;
                        if (!payload || Number(payload.conversation_id) !== actualConversationId) {
                            return;
                        }

                        if (payload.event === 'message.created') {
                            loadHistory().then(() => {
                                window.dispatchEvent(new CustomEvent('vnz:chat-message-received', {
                                    detail: {
                                        transactionId,
                                        conversationId: actualConversationId,
                                        conversationType
                                    }
                                }));
                            });
                            return;
                        }

                        if (payload.event === 'message.edited' || payload.event === 'message.deleted' || payload.event === 'message.reaction_updated') {
                            const targetId = Number(payload.message_id);
                            if (!Number.isInteger(targetId) || !messageMap.has(targetId)) {
                                loadHistory();
                                return;
                            }

                            if (payload.event === 'message.deleted') {
                                const existing = messageMap.get(targetId) || {};
                                messageMap.set(targetId, {
                                    ...existing,
                                    deleted_at: payload.deleted_at || new Date().toISOString().slice(0, 19).replace('T', ' '),
                                    content: 'This message was deleted'
                                });
                            } else if (payload.event === 'message.edited') {
                                loadHistory();
                                return;
                            } else if (payload.event === 'message.reaction_updated') {
                                loadHistory();
                                return;
                            }
                            renderMessages(shouldAutoScroll());
                            return;
                        }

                        if (payload.event === 'typing.started' || payload.event === 'typing.stopped') {
                            handleTypingEvent(payload);
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
                        emojiToggle.disabled = false;
                    } catch (authError) {
                        setConnection('Unavailable', 'error');
                        setStatus(`Realtime authorization failed: ${authError.message}`, 'error');
                    }
                });

                drone.on('disconnect', () => {
                    realtimeReady = false;
                    setConnection('Reconnecting');
                });

                drone.on('reconnect', async () => {
                    if (disposed) {
                        return;
                    }
                    try {
                        roomSubscription = null;
                        activeRoomName = null;
                        realtimeReady = false;
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
            const submittedContent = input.value.trim();
            closeEmojiPicker();

            if (!submittedContent || sendingMessage || sendOutcomeUnknown || !actualConversationId) {
                return;
            }

            const isBookMeCommand = submittedContent.toLowerCase() === bookMeCommand;
            if (isBookMeCommand && currentActorType !== 'admin') {
                setStatus('Only Admin can use the /bookme command.', 'warning');
                return;
            }

            const content = submittedContent;
            sendingMessage = true;
            sendButton.disabled = true;
            setStatus(isBookMeCommand ? 'Sending Lalamove booking details…' : 'Sending message…');

            try {
                const payload = {
                    conversation_id: String(actualConversationId),
                    content,
                    message_type: 'text',
                    csrf_token: csrfToken
                };

                if (editingMessageId) {
                    payload.action = 'edit';
                    payload.message_id = String(editingMessageId);
                } else {
                    payload.action = 'send';
                    if (replyToMessageId) {
                        payload.reply_to_message_id = String(replyToMessageId);
                    }
                }

                const data = await postForm('/VNZ-TECH/backend/chat_messages.php', payload);

                if (editingMessageId) {
                    const updatedMessage = data.message;
                    messageMap.set(Number(updatedMessage.id), updatedMessage);
                    clearComposerState();
                    renderMessages(true);
                    setStatus('Message updated.');
                } else {
                    upsertMessages([data.message], {forceScroll: true});
                    clearComposerState();
                    input.value = '';
                    if (data.realtime_delivery === 'published') {
                        setStatus('Message sent.');
                    } else {
                        setStatus(
                            `Message saved. Realtime delivery: ${String(data.realtime_delivery || 'unknown').replaceAll('_', ' ')}. The message remains in history.`,
                            'warning'
                        );
                    }
                }
            } catch (error) {
                if (Number.isInteger(error.status)) {
                    setStatus(`Message was not sent: ${error.message}`, 'error');
                } else {
                    sendOutcomeUnknown = true;
                    input.disabled = true;
                    emojiToggle.disabled = true;
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

        input.addEventListener('input', () => {
            if (!actualConversationId || !realtimeReady || !activeRoomName) {
                return;
            }

            if (typingTimer) {
                clearTimeout(typingTimer);
            }

            if (!typingActive) {
                typingActive = true;
                publishTypingState(true);
            }
            typingTimer = setTimeout(() => {
                typingActive = false;
                publishTypingState(false);
                typingTimer = null;
            }, 1200);
        });

        input.addEventListener('blur', () => {
            if (typingTimer) {
                clearTimeout(typingTimer);
                typingTimer = null;
            }
            if (typingActive) {
                typingActive = false;
                publishTypingState(false);
            }
        });

        messageList.addEventListener('click', async event => {
            const actionsToggle = event.target.closest('[data-chat-actions-toggle]');
            if (actionsToggle && messageList.contains(actionsToggle)) {
                const row = actionsToggle.closest('.transaction-chat-message');
                const opening = !row.classList.contains('is-actions-open');
                messageList.querySelectorAll('.transaction-chat-message.is-actions-open')
                    .forEach(openRow => {
                        openRow.classList.remove('is-actions-open');
                        openRow.querySelector('[data-chat-actions-toggle]')
                            ?.setAttribute('aria-expanded', 'false');
                    });
                row.classList.toggle('is-actions-open', opening);
                actionsToggle.setAttribute('aria-expanded', String(opening));
                return;
            }

            const moreButton = event.target.closest('[data-chat-more]');
            if (moreButton && messageList.contains(moreButton)) {
                const menu = moreButton.parentElement.querySelector('[role="menu"]');
                const opening = menu.hidden;
                messageList.querySelectorAll('[data-chat-more]').forEach(button => {
                    button.setAttribute('aria-expanded', 'false');
                    const otherMenu = button.parentElement.querySelector('[role="menu"]');
                    if (otherMenu) {
                        otherMenu.hidden = true;
                    }
                });
                menu.hidden = !opening;
                moreButton.setAttribute('aria-expanded', String(opening));
                if (opening) {
                    menu.querySelector('[role="menuitem"]')?.focus();
                }
                return;
            }

            const replyButton = event.target.closest('[data-chat-reply]');
            if (replyButton && messageList.contains(replyButton)) {
                setComposerState('reply', replyButton.dataset.messageId);
                return;
            }

            const editButton = event.target.closest('[data-chat-edit]');
            if (editButton && messageList.contains(editButton)) {
                const menu = editButton.closest('[role="menu"]');
                menu.hidden = true;
                menu.parentElement.querySelector('[data-chat-more]')
                    ?.setAttribute('aria-expanded', 'false');
                const row = editButton.closest('.transaction-chat-message');
                row.classList.remove('is-actions-open');
                row.querySelector('[data-chat-actions-toggle]')
                    ?.setAttribute('aria-expanded', 'false');
                const messageId = Number(editButton.dataset.messageId);
                const target = messageMap.get(messageId);
                if (!target || target.deleted_at) {
                    setStatus('This message can no longer be edited.', 'warning');
                    return;
                }
                setComposerState('edit', messageId);
                input.value = target.content === 'This message was deleted' ? '' : String(target.content || '');
                return;
            }

            const deleteButton = event.target.closest('[data-chat-delete]');
            if (deleteButton && messageList.contains(deleteButton)) {
                const menu = deleteButton.closest('[role="menu"]');
                menu.hidden = true;
                menu.parentElement.querySelector('[data-chat-more]')
                    ?.setAttribute('aria-expanded', 'false');
                const row = deleteButton.closest('.transaction-chat-message');
                row.classList.remove('is-actions-open');
                row.querySelector('[data-chat-actions-toggle]')
                    ?.setAttribute('aria-expanded', 'false');
                const messageId = Number(deleteButton.dataset.messageId);
                if (!window.confirm('Delete this message?')) {
                    return;
                }
                try {
                    const data = await postForm('/VNZ-TECH/backend/chat_messages.php', {
                        action: 'delete',
                        conversation_id: String(actualConversationId),
                        message_id: String(messageId),
                        csrf_token: csrfToken
                    });
                    const current = messageMap.get(messageId) || {};
                    messageMap.set(messageId, {
                        ...current,
                        deleted_at: data.deleted_at || new Date().toISOString().slice(0, 19).replace('T', ' '),
                        content: 'This message was deleted',
                        is_edited: false
                    });
                    renderMessages(shouldAutoScroll());
                    setStatus('Message deleted.');
                } catch (error) {
                    setStatus(`Could not delete this message: ${error.message}`, 'error');
                }
                return;
            }

            const reactionButton = event.target.closest('[data-chat-reaction]');
            if (reactionButton && messageList.contains(reactionButton)) {
                const messageId = Number(reactionButton.dataset.messageId);
                const reaction = reactionButton.dataset.reaction;
                try {
                    const data = await postForm('/VNZ-TECH/backend/chat_messages.php', {
                        action: 'reaction',
                        conversation_id: String(actualConversationId),
                        message_id: String(messageId),
                        reaction,
                        csrf_token: csrfToken
                    });
                    const current = messageMap.get(messageId) || {};
                    current.reactions = data.reactions || [];
                    messageMap.set(messageId, current);
                    renderMessages(shouldAutoScroll());
                } catch (error) {
                    setStatus(`Reaction could not be updated: ${error.message}`, 'error');
                }
                return;
            }

            const previewButton = event.target.closest('[data-landmark-preview]');
            if (previewButton && messageList.contains(previewButton)) {
                if (!landmarkDialog.open) {
                    landmarkDialog.showModal();
                    dialogClose.focus();
                }
                return;
            }

            const copyButton = event.target.closest('[data-copy-value]');
            if (!copyButton || !messageList.contains(copyButton)) {
                return;
            }

            try {
                await navigator.clipboard.writeText(copyButton.dataset.copyValue);
                setStatus('Drop-off address copied.');
            } catch (error) {
                setStatus('Could not copy the address. Please select and copy it manually.', 'warning');
            }
        });

        document.addEventListener('click', event => {
            const menuControl = event.target.closest('[role="menu"], [data-chat-more]');
            if (menuControl && messageList.contains(menuControl)) {
                return;
            }
            messageList.querySelectorAll('.transaction-chat-message.is-actions-open')
                .forEach(row => {
                    row.classList.remove('is-actions-open');
                    row.querySelector('[data-chat-actions-toggle]')
                        ?.setAttribute('aria-expanded', 'false');
                });
            messageList.querySelectorAll('[data-chat-more]').forEach(button => {
                button.setAttribute('aria-expanded', 'false');
                const menu = button.parentElement.querySelector('[role="menu"]');
                if (menu) {
                    menu.hidden = true;
                }
            });
        }, {signal: widgetEventController.signal});

        messageList.addEventListener('keydown', event => {
            if (event.key !== 'Escape') {
                return;
            }

            const openMenu = messageList.querySelector('[role="menu"]:not([hidden])');
            if (openMenu) {
                const moreButton = openMenu.parentElement.querySelector('[data-chat-more]');
                openMenu.hidden = true;
                moreButton?.setAttribute('aria-expanded', 'false');
                moreButton?.focus();
                return;
            }

            messageList.querySelectorAll('.transaction-chat-message.is-actions-open')
                .forEach(row => {
                    row.classList.remove('is-actions-open');
                    const toggle = row.querySelector('[data-chat-actions-toggle]');
                    toggle?.setAttribute('aria-expanded', 'false');
                    toggle?.focus();
                });
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
                emojiToggle.disabled = false;
                await loadHistory(null, {forceScroll: true});
                await connectRealtime();
            } catch (error) {
                setStatus(`Chat is unavailable: ${error.message}`, 'error');
                setConnection('Unavailable', 'error');
                input.disabled = true;
                sendButton.disabled = true;
                emojiToggle.disabled = true;
            }
        }

        activeChats.set(widget, () => {
            disposed = true;
            widgetEventController.abort();
            if (typingTimer) {
                clearTimeout(typingTimer);
            }
            typingUsers.forEach(entry => clearTimeout(entry.timer));
            if (landmarkDialog.open) {
                landmarkDialog.close();
            }
            landmarkDialog.remove();
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
