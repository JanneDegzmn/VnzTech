(() => {
    const activeChats = new Map();
    const bookMeCommand = '/bookme';
    const shareLocationUrl = 'https://maps.app.goo.gl/iqFq3UGQuqybbY6VA';
    const lalamoveBookingUrl = 'https://www.lalamove.com/en-ph/';
    const landmarkImageUrl = '/VNZ-TECH/assets/images/location.jpg';
    const bookingMessagePrefix = 'LALAMOVE_BOOKING_V1';
    let activeVoiceChat = null;
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

    function postUploadForm(url, fields, file, onProgress) {
        return new Promise((resolve, reject) => {
            const request = new XMLHttpRequest();
            const formData = new FormData();
            Object.entries(fields).forEach(([key, value]) => {
                formData.append(key, String(value));
            });
            formData.append('attachment', file, file.name);
            request.open('POST', url);
            request.withCredentials = true;
            request.upload.addEventListener('progress', event => {
                if (event.lengthComputable) {
                    onProgress(Math.round((event.loaded / event.total) * 100));
                }
            });
            request.addEventListener('load', () => {
                let payload;
                try {
                    payload = JSON.parse(request.responseText);
                } catch (error) {
                    const responseError = new Error(
                        `The server returned an invalid upload response (HTTP ${request.status}).`
                    );
                    if (request.status < 200 || request.status >= 300) {
                        responseError.status = request.status;
                    }
                    reject(responseError);
                    return;
                }

                if (request.status < 200 || request.status >= 300 || payload.success !== true) {
                    const error = new Error(payload.message || `Upload failed (HTTP ${request.status}).`);
                    error.status = request.status;
                    reject(error);
                    return;
                }
                resolve(payload.data || {});
            });
            request.addEventListener('error', () => {
                reject(new Error('The attachment upload failed. Check your connection and try again.'));
            });
            request.addEventListener('abort', () => {
                reject(new Error('The image upload was cancelled.'));
            });
            request.send(formData);
        });
    }

    async function getJson(url) {
        const response = await fetch(url, {
            method: 'GET',
            credentials: 'same-origin',
            cache: 'no-store'
        });

        return readJsonResponse(response);
    }

    const sharedRealtime = (() => {
        let drone = null;
        let channelId = null;
        let connectionPromise = null;
        let pendingAuthentication = null;
        let reconciliation = Promise.resolve();
        let reconciliationRequested = false;
        const rooms = new Map();

        function setRoomState(room, ready, error = null) {
            room.ready = ready;
            room.callbacks.forEach(callbacks => {
                callbacks.onState?.(ready, error);
            });
        }

        function notifyRoom(room, payload) {
            room.callbacks.forEach(callbacks => callbacks.onEvent?.(payload));
        }

        function subscribeRoom(roomName, room) {
            if (room.subscription) {
                return Promise.resolve();
            }

            return new Promise((resolve, reject) => {
                const subscription = drone.subscribe(roomName);
                room.subscription = subscription;
                subscription.on('open', error => {
                    if (rooms.get(room.conversationId) !== room) {
                        return;
                    }
                    if (error) {
                        room.subscription = null;
                        setRoomState(room, false, new Error('Could not subscribe to this conversation.'));
                        reject(new Error('Could not subscribe to this conversation.'));
                        return;
                    }

                    setRoomState(room, true);
                    subscription.on('message', event => {
                        if (rooms.get(room.conversationId) === room) {
                            notifyRoom(room, event?.data);
                        }
                    });
                    resolve();
                });
            });
        }

        async function authenticateForRooms() {
            const desiredRooms = Array.from(rooms.entries())
                .filter(([, room]) => room.callbacks.size > 0);
            if (desiredRooms.length === 0) {
                return;
            }

            const firstRoom = desiredRooms[0][1];
            const authorization = await postForm(
                '/VNZ-TECH/backend/chat_scaledrone_auth.php',
                {
                    conversation_ids: desiredRooms.map(([id]) => id).join(','),
                    client_id: drone.clientId,
                    csrf_token: firstRoom.csrfToken
                }
            );

            if (authorization.channel_id !== channelId) {
                throw new Error('Realtime channel configuration changed. Reload this page.');
            }

            const authorizedRoomNames = new Map(
                (authorization.rooms || []).map(room => [
                    String(room.conversation_id),
                    room.room
                ])
            );

            await new Promise((resolve, reject) => {
                pendingAuthentication = {resolve, reject};
                drone.authenticate(authorization.token);
            });

            for (const [conversationId, room] of desiredRooms) {
                if (rooms.get(conversationId) !== room || room.subscription) {
                    continue;
                }
                const roomName = authorizedRoomNames.get(conversationId);
                if (!roomName) {
                    throw new Error('Realtime authorization omitted an active conversation.');
                }
                room.roomName = roomName;
                await subscribeRoom(roomName, room);
            }
        }

        function requestReconciliation() {
            reconciliationRequested = true;
            reconciliation = reconciliation.catch(() => {}).then(async () => {
                while (reconciliationRequested && drone) {
                    reconciliationRequested = false;
                    await authenticateForRooms();
                }
            });
            return reconciliation;
        }

        async function connect(requestedChannelId) {
            if (drone) {
                if (channelId !== requestedChannelId) {
                    throw new Error('A different realtime channel is already active on this page.');
                }
                return connectionPromise;
            }

            channelId = requestedChannelId;
            await loadScaledroneLibrary();
            drone = new window.Scaledrone(channelId);
            connectionPromise = new Promise((resolve, reject) => {
                let opened = false;
                drone.on('open', error => {
                    if (error) {
                        if (!opened) {
                            reject(new Error('Could not connect to realtime messaging.'));
                        }
                        return;
                    }
                    opened = true;
                    resolve();
                });

                drone.on('authenticate', error => {
                    const pending = pendingAuthentication;
                    if (!pending) {
                        return;
                    }
                    pendingAuthentication = null;
                    if (error) {
                        pending.reject(new Error('Realtime authentication was rejected.'));
                    } else {
                        pending.resolve();
                    }
                });

                drone.on('disconnect', () => {
                    rooms.forEach(room => setRoomState(room, false));
                });

                drone.on('reconnect', () => {
                    rooms.forEach(room => {
                        room.subscription?.unsubscribe?.();
                        room.subscription = null;
                        setRoomState(room, false);
                    });
                    requestReconciliation().catch(error => {
                        rooms.forEach(room => setRoomState(room, false, error));
                    });
                });

                drone.on('error', error => {
                    rooms.forEach(room => setRoomState(room, false, error));
                });
            });
            return connectionPromise;
        }

        async function acquire(requestedChannelId, conversationId, csrfToken, callbacks) {
            try {
                await connect(requestedChannelId);
            } catch (error) {
                drone?.close?.();
                drone = null;
                channelId = null;
                connectionPromise = null;
                pendingAuthentication = null;
                throw error;
            }
            const key = String(conversationId);
            let room = rooms.get(key);
            if (!room) {
                room = {
                    conversationId: key,
                    csrfToken,
                    callbacks: new Map(),
                    roomName: null,
                    subscription: null,
                    ready: false
                };
                rooms.set(key, room);
            }

            const callbackId = Symbol(key);
            room.callbacks.set(callbackId, callbacks);
            try {
                await requestReconciliation();
            } catch (error) {
                room.callbacks.delete(callbackId);
                if (room.callbacks.size === 0) {
                    rooms.delete(key);
                    room.subscription?.unsubscribe?.();
                }
                if (rooms.size === 0 && drone) {
                    drone.close?.();
                    drone = null;
                    channelId = null;
                    connectionPromise = null;
                    pendingAuthentication = null;
                    reconciliation = Promise.resolve();
                    reconciliationRequested = false;
                }
                throw error;
            }

            let released = false;
            const release = () => {
                if (released) {
                    return;
                }
                released = true;
                room.callbacks.delete(callbackId);
                if (room.callbacks.size > 0) {
                    return;
                }
                rooms.delete(key);
                room.subscription?.unsubscribe?.();
                room.subscription = null;
                if (rooms.size === 0 && drone) {
                    drone.close?.();
                    drone = null;
                    channelId = null;
                    connectionPromise = null;
                    pendingAuthentication = null;
                    reconciliation = Promise.resolve();
                    reconciliationRequested = false;
                } else if (drone) {
                    requestReconciliation().catch(error => {
                        rooms.forEach(activeRoom => setRoomState(activeRoom, false, error));
                    });
                }
            };

            return {
                release,
                publish: payload => {
                    if (
                        released
                        || rooms.get(key) !== room
                        || !room.ready
                        || !room.roomName
                        || !drone
                    ) {
                        return false;
                    }

                    drone.publish({
                        room: room.roomName,
                        message: payload
                    });
                    return true;
                }
            };
        }

        return {acquire};
    })();

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

    function formatFileSize(bytes) {
        if (bytes < 1024) {
            return `${bytes} B`;
        }
        if (bytes < 1024 * 1024) {
            return `${(bytes / 1024).toFixed(1)} KB`;
        }
        return `${(bytes / (1024 * 1024)).toFixed(1)} MB`;
    }

    function formatAudioTime(seconds) {
        if (!Number.isFinite(seconds) || seconds < 0) {
            return '0:00';
        }
        const wholeSeconds = Math.floor(seconds);
        return `${Math.floor(wholeSeconds / 60)}:${String(wholeSeconds % 60).padStart(2, '0')}`;
    }

    function sanitizeDisplayFilename(filename) {
        const baseName = String(filename || '').replace(/\\/g, '/').split('/').pop();
        return baseName.replace(/[\u0000-\u001f\u007f]/g, '').slice(0, 190) || 'attachment';
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

    function chatAvatarInitials(displayName) {
        return String(displayName || 'Participant')
            .trim()
            .split(/\s+/)
            .filter(Boolean)
            .slice(0, 2)
            .map(part => Array.from(part)[0].toLocaleUpperCase())
            .join('');
    }

    function createChatAvatar(profilePicture, fallbackName, className = 'transaction-chat-avatar') {
        const avatar = document.createElement('span');
        avatar.className = className;
        avatar.setAttribute('aria-hidden', 'true');

        const fallback = document.createElement('span');
        fallback.className = 'transaction-chat-avatar-fallback';
        fallback.textContent = chatAvatarInitials(fallbackName);
        avatar.append(fallback);

        const profilePath = String(profilePicture || '');
        if (/^uploads\/profile_pictures\/[A-Za-z0-9][A-Za-z0-9._-]*$/.test(profilePath)) {
            const image = document.createElement('img');
            image.src = `/VNZ-TECH/${profilePath}`;
            image.alt = '';
            image.loading = 'lazy';
            image.addEventListener('load', () => {
                fallback.hidden = true;
            }, {once: true});
            image.addEventListener('error', () => {
                image.remove();
            }, {once: true});
            avatar.append(image);
        }

        return avatar;
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
        conversationType,
        previousFromSender,
        showAvatar
    ) {
        const row = document.createElement('article');
        row.tabIndex = 0;
        const ownMessage = message.sender_type === currentActorType
            && Number(message.sender_id) === currentActorId;
        row.className = `transaction-chat-message${ownMessage ? ' is-own' : ''}${
            !ownMessage && previousFromSender ? ' is-grouped' : ''
        }`;
        row.dataset.messageId = String(message.id);

        const bubble = document.createElement('div');
        bubble.className = 'transaction-chat-bubble';

        const meta = document.createElement('div');
        meta.className = 'transaction-chat-message-meta';

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
        let attachmentButton = null;
        if (message.message_type === 'image' && message.attachment_url && !message.deleted_at) {
            attachmentButton = document.createElement('button');
            attachmentButton.type = 'button';
            attachmentButton.className = 'transaction-chat-attachment';
            attachmentButton.dataset.chatAttachmentView = message.attachment_url;
            attachmentButton.setAttribute('aria-label', 'View attached image');

            const attachmentImage = document.createElement('img');
            attachmentImage.src = message.attachment_url;
            attachmentImage.alt = 'Image attachment';
            attachmentImage.loading = 'lazy';
            attachmentButton.append(attachmentImage);
        }
        if (message.message_type === 'file' && message.attachment_url && !message.deleted_at) {
            const documentCard = document.createElement('section');
            documentCard.className = 'transaction-chat-document';

            const documentIcon = document.createElement('span');
            documentIcon.className = 'transaction-chat-document-icon';
            documentIcon.setAttribute('aria-hidden', 'true');
            documentIcon.textContent = 'PDF';

            const documentDetails = document.createElement('span');
            documentDetails.className = 'transaction-chat-document-details';
            const documentName = document.createElement('strong');
            documentName.className = 'transaction-chat-document-name';
            documentName.textContent = sanitizeDisplayFilename(message.attachment_name);
            documentName.title = documentName.textContent;
            const documentSize = document.createElement('span');
            documentSize.className = 'transaction-chat-document-size';
            documentSize.textContent = formatFileSize(Number(message.attachment_size) || 0);
            documentDetails.append(documentName, documentSize);

            const documentAction = document.createElement('a');
            documentAction.className = 'transaction-chat-document-action';
            documentAction.href = message.attachment_url;
            documentAction.download = sanitizeDisplayFilename(message.attachment_name);
            documentAction.rel = 'noopener noreferrer';
            documentAction.textContent = 'Download PDF';
            documentCard.append(documentIcon, documentDetails, documentAction);
            attachmentButton = documentCard;
        }
        if (message.message_type === 'voice' && !message.deleted_at) {
            const voiceCard = document.createElement('section');
            voiceCard.className = 'transaction-chat-audio';

            const voiceAudio = document.createElement('audio');
            voiceAudio.preload = 'metadata';
            if (message.attachment_url) {
                voiceAudio.src = message.attachment_url;
            }

            const playButton = document.createElement('button');
            playButton.type = 'button';
            playButton.className = 'transaction-chat-audio-play';
            playButton.textContent = '▶';
            playButton.setAttribute('aria-label', 'Play voice message');
            playButton.disabled = !message.attachment_url;

            const audioDetails = document.createElement('div');
            audioDetails.className = 'transaction-chat-audio-details';
            const audioTime = document.createElement('span');
            audioTime.className = 'transaction-chat-audio-time';
            audioTime.textContent = '0:00 / 0:00';
            const audioProgress = document.createElement('input');
            audioProgress.type = 'range';
            audioProgress.className = 'transaction-chat-audio-progress';
            audioProgress.min = '0';
            audioProgress.max = '1000';
            audioProgress.value = '0';
            audioProgress.disabled = !message.attachment_url;
            audioProgress.setAttribute('aria-label', 'Seek voice message');
            const audioStatus = document.createElement('span');
            audioStatus.className = 'transaction-chat-audio-status';
            audioStatus.textContent = message.attachment_url
                ? 'Voice message'
                : 'Voice message unavailable';
            audioDetails.append(audioTime, audioProgress, audioStatus);
            voiceCard.append(playButton, audioDetails, voiceAudio);

            const updateAudioTime = () => {
                const duration = Number.isFinite(voiceAudio.duration)
                    ? voiceAudio.duration
                    : 0;
                audioTime.textContent = `${formatAudioTime(voiceAudio.currentTime)} / ${formatAudioTime(duration)}`;
                audioProgress.value = duration > 0
                    ? String(Math.round((voiceAudio.currentTime / duration) * 1000))
                    : '0';
            };
            const markAudioUnavailable = () => {
                audioStatus.textContent = 'Voice message unavailable';
                playButton.disabled = true;
                audioProgress.disabled = true;
            };
            playButton.addEventListener('click', () => {
                if (voiceAudio.paused) {
                    voiceAudio.play().catch(markAudioUnavailable);
                } else {
                    voiceAudio.pause();
                }
            });
            voiceAudio.addEventListener('loadedmetadata', updateAudioTime);
            voiceAudio.addEventListener('durationchange', updateAudioTime);
            voiceAudio.addEventListener('timeupdate', updateAudioTime);
            voiceAudio.addEventListener('play', () => {
                playButton.textContent = 'Ⅱ';
                playButton.setAttribute('aria-label', 'Pause voice message');
            });
            voiceAudio.addEventListener('pause', () => {
                playButton.textContent = '▶';
                playButton.setAttribute('aria-label', 'Play voice message');
            });
            voiceAudio.addEventListener('ended', updateAudioTime);
            voiceAudio.addEventListener('error', markAudioUnavailable);
            audioProgress.addEventListener('input', () => {
                if (Number.isFinite(voiceAudio.duration) && voiceAudio.duration > 0) {
                    voiceAudio.currentTime = (
                        Number(audioProgress.value) / Number(audioProgress.max)
                    ) * voiceAudio.duration;
                    updateAudioTime();
                }
            });
            attachmentButton = voiceCard;
        }

        const bookingDetails = messageContent.split('\n');
        if (
            !['image', 'file', 'voice'].includes(message.message_type)
            && message.sender_type === 'admin'
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
        } else if (!['image', 'file', 'voice'].includes(message.message_type) && messageContent === shareLocationUrl) {
            const locationLink = document.createElement('a');
            locationLink.href = shareLocationUrl;
            locationLink.target = '_blank';
            locationLink.rel = 'noopener noreferrer';
            locationLink.textContent = shareLocationUrl;
            content.append(locationLink);
        } else if (
            messageContent !== ''
            && !['image', 'file'].includes(message.message_type)
            && (message.message_type !== 'voice' || Boolean(message.deleted_at))
        ) {
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
        if (ownMessage) {
            const receipt = document.createElement('span');
            receipt.className = 'transaction-chat-receipt-status';
            const state = String(message.receipt_status || 'sent');
            const receiptCounts = message.receipt_counts || {};
            if (conversationType === 'global' && state === 'read') {
                receipt.textContent = `Read by ${Number(receiptCounts.read) || 0}`;
                const readers = Array.isArray(message.read_receipt_readers)
                    ? message.read_receipt_readers
                    : [];
                if (readers.length > 0) {
                    receipt.classList.add('has-readers');
                    receipt.tabIndex = 0;
                    receipt.setAttribute('aria-label', `${receipt.textContent}: ${readers.join(', ')}`);
                    const tooltip = document.createElement('span');
                    tooltip.className = 'transaction-chat-read-receipt-tooltip';
                    tooltip.setAttribute('role', 'tooltip');
                    readers.slice(0, 100).forEach(readerName => {
                        const reader = document.createElement('span');
                        reader.textContent = readerName;
                        tooltip.append(reader);
                    });
                    if (Number(receiptCounts.read) > Math.min(readers.length, 100)) {
                        const more = document.createElement('span');
                        more.className = 'transaction-chat-read-receipt-more';
                        more.textContent = `Showing ${Math.min(readers.length, 100)} of ${Number(receiptCounts.read)} readers`;
                        tooltip.append(more);
                    }
                    receipt.append(tooltip);
                }
            } else if (conversationType === 'global' && state === 'delivered') {
                receipt.textContent = `Delivered to ${Number(receiptCounts.delivered) || 0}`;
            } else {
                receipt.textContent = state.charAt(0).toUpperCase() + state.slice(1);
            }
            statusLine.append(receipt);
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

            if (message.message_type !== 'voice') {
                menu.append(editAction);
            }
            menu.append(deleteAction);
            menuWrap.append(moreButton, menu);
            actionToolbar.append(menuWrap);
        }

        meta.append(time);
        bubble.append(meta);
        if (replyPreview.childNodes.length) {
            bubble.append(replyPreview);
        }

        if (attachmentButton) {
            bubble.append(attachmentButton);
        }
        bubble.append(content, statusLine);
        if (!ownMessage) {
            const senderName = String(
                message.sender_display_name
                || (message.sender_type === 'seller' ? 'Seller' : 'Admin')
            );
            const avatar = createChatAvatar(
                message.sender_profile_picture,
                senderName
            );
            if (!showAvatar) {
                avatar.classList.add('is-placeholder');
            }
            row.append(avatar);
        }
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
        const imageMaxBytes = Number(widget.dataset.attachmentMaxBytes) || 5 * 1024 * 1024;
        const documentMaxBytes = Number(widget.dataset.documentMaxBytes) || 10 * 1024 * 1024;
        const voiceMaxBytes = Number(widget.dataset.voiceMaxBytes) || 5 * 1024 * 1024;
        const voiceMaxDurationSeconds = Number(widget.dataset.voiceMaxDurationSeconds) || 120;
        const currentActorType = widget.dataset.actorType || '';
        const currentActorId = Number(widget.dataset.actorId) || 0;
        const currentActorDisplayName = widget.dataset.actorDisplayName
            || (currentActorType === 'admin' ? 'Admin' : 'Seller');
        const currentActorProfilePicture = widget.dataset.actorProfilePicture || '';
        const status = widget.querySelector('[data-chat-status]');
        const connection = widget.querySelector('[data-chat-connection]');
        const connectionLabel = widget.querySelector('[data-chat-connection-label]');
        const messageList = widget.querySelector('[data-chat-messages]');
        const emptyState = widget.querySelector('[data-chat-empty]');
        const form = widget.querySelector('[data-chat-form]');
        const input = widget.querySelector('[data-chat-input]');
        const sendButton = widget.querySelector('[data-chat-send]');
        const emojiToggle = widget.querySelector('[data-chat-emoji-toggle]');
        const attachmentToggle = widget.querySelector('[data-chat-attachment-toggle]');
        const attachmentInput = widget.querySelector('[data-chat-attachment-input]');
        const voiceToggle = widget.querySelector('[data-chat-voice-toggle]');
        const voiceComposer = widget.querySelector('[data-chat-voice-composer]');
        const voiceStatus = widget.querySelector('[data-chat-voice-status]');
        const voicePreview = widget.querySelector('[data-chat-voice-preview]');
        const voiceCancel = widget.querySelector('[data-chat-voice-cancel]');
        const voiceSend = widget.querySelector('[data-chat-voice-send]');
        const attachmentPreview = widget.querySelector('[data-chat-attachment-preview]');
        const attachmentPreviewImage = widget.querySelector('[data-chat-attachment-preview-image]');
        const attachmentName = widget.querySelector('[data-chat-attachment-name]');
        const attachmentRemove = widget.querySelector('[data-chat-attachment-remove]');
        const uploadProgress = widget.querySelector('[data-chat-upload-progress]');
        const uploadProgressBar = widget.querySelector('[data-chat-upload-progress-bar]');
        const uploadError = widget.querySelector('[data-chat-upload-error]');
        const loadOlderButton = widget.querySelector('[data-chat-load-older]');
        const widgetEventController = new AbortController();
        const typingIndicator = document.createElement('div');
        typingIndicator.className = 'transaction-chat-typing-indicator';
        typingIndicator.hidden = true;
        typingIndicator.setAttribute('role', 'status');
        typingIndicator.setAttribute('aria-live', 'polite');
        typingIndicator.setAttribute('aria-label', 'Another participant is typing');
        const typingPeople = document.createElement('span');
        typingPeople.className = 'transaction-chat-typing-people';
        typingIndicator.append(typingPeople);
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

        const attachmentDialog = document.createElement('dialog');
        attachmentDialog.className = 'transaction-chat-landmark-dialog transaction-chat-attachment-dialog';
        attachmentDialog.setAttribute('aria-label', 'Image attachment');
        const attachmentDialogPanel = document.createElement('div');
        attachmentDialogPanel.className = 'transaction-chat-landmark-dialog-panel';
        const attachmentDialogHeader = document.createElement('header');
        attachmentDialogHeader.className = 'transaction-chat-landmark-dialog-header';
        const attachmentDialogTitle = document.createElement('h2');
        attachmentDialogTitle.textContent = 'Image attachment';
        const attachmentDialogClose = document.createElement('button');
        attachmentDialogClose.className = 'transaction-chat-landmark-dialog-close';
        attachmentDialogClose.type = 'button';
        attachmentDialogClose.setAttribute('aria-label', 'Close image preview');
        attachmentDialogClose.textContent = '×';
        const attachmentDialogImage = document.createElement('img');
        attachmentDialogImage.className = 'transaction-chat-landmark-dialog-image';
        attachmentDialogImage.alt = 'Full-size image attachment';
        attachmentDialogHeader.append(attachmentDialogTitle, attachmentDialogClose);
        attachmentDialogPanel.append(attachmentDialogHeader, attachmentDialogImage);
        attachmentDialog.append(attachmentDialogPanel);
        document.body.append(attachmentDialog);

        let actualConversationId = Number(widget.dataset.conversationId) || 0;
        let realtimeLease = null;
        let oldestMessageCursor = null;
        let hasMoreMessages = false;
        let loadingHistory = false;
        let historyRefreshQueued = false;
        let sendingMessage = false;
        let sendOutcomeUnknown = false;
        let lastReadMessageId = 0;
        let readRequestInProgress = false;
        let queuedReadMessageId = 0;
        let disposed = false;
        let editingMessageId = null;
        let replyToMessageId = null;
        let selectedAttachment = null;
        let selectedImagePreviewUrl = null;
        let voiceState = 'idle';
        let voiceMediaRecorder = null;
        let voiceMediaStream = null;
        let voiceRecordingChunks = [];
        let voiceRecordingTimer = null;
        let voiceRecordingStartedAt = 0;
        let voiceRequestToken = 0;
        let voiceRecordingDiscarded = false;
        let voiceRecordingError = '';
        let recordedVoiceFile = null;
        let recordedVoicePreviewUrl = null;
        let typingTimer = null;
        let typingHeartbeatTimer = null;
        let typingUsers = new Map();
        let typingActive = false;
        let activeRoomName = null;
        let realtimeReady = false;
        let visibilityObserver = null;
        const typingIdleTimeout = 1800;
        const typingHeartbeatInterval = 900;
        const remoteTypingTimeout = 2000;

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
            updateVoiceControls();
        }

        function setComposerState(mode, messageId = null) {
            if (voiceState !== 'idle') {
                setStatus('Send or discard the voice message before changing composer state.', 'warning');
                return;
            }
            composeState.hidden = false;
            composeState.innerHTML = '';
            if (mode === 'edit') {
                clearSelectedImage();
                attachmentToggle.disabled = true;
            } else {
                attachmentToggle.disabled = input.disabled || sendingMessage;
            }

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
            updateVoiceControls();
        }

        function clearSelectedImage() {
            selectedAttachment = null;
            attachmentInput.value = '';
            attachmentPreview.hidden = true;
            attachmentPreviewImage.removeAttribute('src');
            attachmentPreviewImage.hidden = true;
            attachmentName.textContent = '';
            attachmentName.removeAttribute('title');
            if (selectedImagePreviewUrl) {
                URL.revokeObjectURL(selectedImagePreviewUrl);
                selectedImagePreviewUrl = null;
            }
            updateVoiceControls();
        }

        function setAttachmentError(message = '') {
            uploadError.textContent = message;
            uploadError.hidden = message === '';
        }

        function updateVoiceControls() {
            const voiceLocked = voiceState !== 'idle';
            input.disabled = voiceLocked || !actualConversationId || sendOutcomeUnknown;
            emojiToggle.disabled = input.disabled;
            attachmentToggle.disabled = input.disabled
                || sendingMessage
                || editingMessageId !== null;
            voiceToggle.disabled = voiceState === 'recording'
                ? false
                : voiceLocked
                    || !actualConversationId
                    || disposed
                    || sendingMessage
                    || sendOutcomeUnknown
                    || Boolean(selectedAttachment)
                    || editingMessageId !== null
                    || replyToMessageId !== null;
            sendButton.disabled = voiceLocked
                || sendingMessage
                || sendOutcomeUnknown
                || !actualConversationId
                || (!input.value.trim() && !selectedAttachment);
            voiceSend.disabled = voiceState !== 'preview' || sendingMessage || sendOutcomeUnknown;
            voiceComposer.hidden = voiceState === 'idle';
            voicePreview.hidden = voiceState !== 'preview';
            voiceCancel.disabled = voiceState === 'processing' || sendingMessage || disposed;
            voiceSend.hidden = voiceState !== 'preview';
            voiceToggle.textContent = voiceState === 'recording' ? '■' : '🎙';
            voiceToggle.setAttribute(
                'aria-label',
                voiceState === 'recording' ? 'Stop recording' : 'Record a voice message'
            );
            voiceToggle.title = voiceState === 'recording'
                ? 'Stop recording'
                : 'Record a voice message';
        }

        function releaseVoiceMedia() {
            if (voiceRecordingTimer) {
                clearInterval(voiceRecordingTimer);
                voiceRecordingTimer = null;
            }
            if (voiceMediaStream) {
                voiceMediaStream.getTracks().forEach(track => track.stop());
                voiceMediaStream = null;
            }
        }

        function releaseVoicePreview() {
            recordedVoiceFile = null;
            voicePreview.pause();
            voicePreview.removeAttribute('src');
            voicePreview.load();
            if (recordedVoicePreviewUrl) {
                URL.revokeObjectURL(recordedVoicePreviewUrl);
                recordedVoicePreviewUrl = null;
            }
        }

        function finishVoiceRecording() {
            const recorder = voiceMediaRecorder;
            const chunks = voiceRecordingChunks;
            const shouldDiscard = voiceRecordingDiscarded || disposed;
            const recordingError = voiceRecordingError;
            voiceMediaRecorder = null;
            voiceRecordingChunks = [];
            voiceRecordingDiscarded = false;
            voiceRecordingError = '';
            releaseVoiceMedia();
            if (activeVoiceChat === widget) {
                activeVoiceChat = null;
            }

            if (shouldDiscard) {
                releaseVoicePreview();
                voiceState = 'idle';
                updateVoiceControls();
                if (!disposed && recordingError) {
                    setAttachmentError(recordingError);
                }
                return;
            }

            const mimeType = String(
                recorder?.mimeType
                || chunks.find(chunk => chunk.type)?.type
                || ''
            ).split(';')[0].trim().toLowerCase();
            const extensionByMimeType = {
                'audio/webm': 'webm',
                'audio/ogg': 'ogg'
            };
            const extension = extensionByMimeType[mimeType];
            const voiceBlob = new Blob(chunks, {type: mimeType});
            if (!extension || voiceBlob.size < 1) {
                voiceState = 'idle';
                updateVoiceControls();
                setAttachmentError(
                    'This browser produced an unsupported audio format. Try a browser that records Opus in WebM or Ogg.'
                );
                return;
            }
            if (voiceBlob.size > voiceMaxBytes) {
                voiceState = 'idle';
                updateVoiceControls();
                setAttachmentError(
                    `Voice messages must be no larger than ${(voiceMaxBytes / 1024 / 1024).toFixed(1)} MB.`
                );
                return;
            }

            recordedVoiceFile = new File(
                [voiceBlob],
                `voice-message.${extension}`,
                {type: mimeType}
            );
            recordedVoicePreviewUrl = URL.createObjectURL(recordedVoiceFile);
            voicePreview.src = recordedVoicePreviewUrl;
            voicePreview.load();
            voiceState = 'preview';
            voiceStatus.textContent = 'Preview your voice message before sending.';
            setAttachmentError();
            updateVoiceControls();
        }

        function stopVoiceRecording(discard = false) {
            if (discard) {
                voiceRecordingDiscarded = true;
            }
            if (voiceRecordingTimer) {
                clearInterval(voiceRecordingTimer);
                voiceRecordingTimer = null;
            }
            if (voiceState === 'recording' || voiceState === 'processing') {
                voiceState = 'processing';
                voiceToggle.disabled = true;
                voiceCancel.disabled = true;
                if (voiceMediaRecorder?.state === 'recording') {
                    voiceMediaRecorder.stop();
                } else {
                    finishVoiceRecording();
                }
                releaseVoiceMedia();
            }
        }

        async function startVoiceRecording() {
            if (
                voiceState !== 'idle'
                || activeVoiceChat
                || selectedAttachment
                || input.value.trim()
                || editingMessageId !== null
                || replyToMessageId !== null
                || sendingMessage
            ) {
                if (activeVoiceChat && activeVoiceChat !== widget) {
                    setAttachmentError('Finish or discard the voice recording in the other chat first.');
                } else if (selectedAttachment || input.value.trim()) {
                    setAttachmentError('Send or remove the current composer content before recording.');
                }
                return;
            }
            if (
                !navigator.mediaDevices?.getUserMedia
                || typeof window.MediaRecorder !== 'function'
                || typeof window.MediaRecorder.isTypeSupported !== 'function'
            ) {
                setAttachmentError('Voice recording is not supported by this browser.');
                return;
            }

            const supportedMimeType = [
                'audio/webm;codecs=opus',
                'audio/ogg;codecs=opus',
                'audio/webm',
                'audio/ogg'
            ].find(type => window.MediaRecorder.isTypeSupported(type));
            if (!supportedMimeType) {
                setAttachmentError('This browser cannot record a supported Opus audio format. Try another browser.');
                return;
            }

            const requestToken = ++voiceRequestToken;
            activeVoiceChat = widget;
            voiceState = 'requesting';
            voiceStatus.textContent = 'Requesting microphone permission…';
            setAttachmentError();
            updateVoiceControls();

            try {
                const stream = await navigator.mediaDevices.getUserMedia({audio: true});
                if (
                    disposed
                    || requestToken !== voiceRequestToken
                    || activeVoiceChat !== widget
                ) {
                    stream.getTracks().forEach(track => track.stop());
                    return;
                }

                voiceMediaStream = stream;
                voiceRecordingChunks = [];
                voiceRecordingDiscarded = false;
                voiceRecordingError = '';
                voiceMediaRecorder = new window.MediaRecorder(stream, {
                    mimeType: supportedMimeType
                });
                voiceMediaRecorder.addEventListener('dataavailable', event => {
                    if (event.data?.size) {
                        voiceRecordingChunks.push(event.data);
                    }
                });
                voiceMediaRecorder.addEventListener('error', () => {
                    voiceRecordingError = 'Recording failed. Check microphone access and try again.';
                    stopVoiceRecording(true);
                }, {once: true});
                voiceMediaRecorder.addEventListener('stop', finishVoiceRecording, {once: true});
                voiceMediaRecorder.start();
                voiceRecordingStartedAt = Date.now();
                voiceState = 'recording';
                voiceStatus.textContent = 'Recording 0:00';
                voiceRecordingTimer = setInterval(() => {
                    const elapsedSeconds = Math.floor(
                        (Date.now() - voiceRecordingStartedAt) / 1000
                    );
                    voiceStatus.textContent = `Recording ${formatAudioTime(elapsedSeconds)}`;
                    if (elapsedSeconds >= voiceMaxDurationSeconds) {
                        voiceStatus.textContent = 'Maximum recording time reached. Preparing preview…';
                        stopVoiceRecording();
                    }
                }, 250);
                updateVoiceControls();
            } catch (error) {
                releaseVoiceMedia();
                if (activeVoiceChat === widget) {
                    activeVoiceChat = null;
                }
                voiceState = 'idle';
                updateVoiceControls();
                if (requestToken !== voiceRequestToken || disposed) {
                    return;
                }
                const denied = error?.name === 'NotAllowedError'
                    || error?.name === 'PermissionDeniedError'
                    || error?.name === 'SecurityError';
                setAttachmentError(denied
                    ? 'Microphone permission was denied. Allow microphone access in your browser settings and try again.'
                    : 'The microphone is unavailable. Check your device and browser permissions.');
            }
        }

        function cancelVoiceComposer() {
            if (voiceState === 'requesting') {
                voiceRequestToken++;
                if (activeVoiceChat === widget) {
                    activeVoiceChat = null;
                }
                voiceState = 'idle';
                updateVoiceControls();
                return;
            }
            if (voiceState === 'recording' || voiceState === 'processing') {
                stopVoiceRecording(true);
                return;
            }
            releaseVoicePreview();
            voiceState = 'idle';
            voiceStatus.textContent = 'Ready to record';
            updateVoiceControls();
        }

        function chooseAttachment(file) {
            setAttachmentError();
            if (!file) {
                return;
            }

            const extension = file.name.split('.').pop().toLowerCase();
            const isPdf = file.type === 'application/pdf' || extension === 'pdf';
            const allowedImageTypes = ['image/jpeg', 'image/png', 'image/webp'];
            const allowedImageExtensions = ['jpg', 'jpeg', 'png', 'webp'];
            const isImage = allowedImageTypes.includes(file.type)
                || (!file.type && allowedImageExtensions.includes(extension));
            if (!isPdf && !isImage) {
                clearSelectedImage();
                setAttachmentError('Choose a JPEG, PNG, WebP image, or PDF document.');
                return;
            }
            if (isImage && file.type && !allowedImageTypes.includes(file.type)) {
                clearSelectedImage();
                setAttachmentError('The selected file type does not match an allowed attachment.');
                return;
            }

            const maxBytes = isPdf ? documentMaxBytes : imageMaxBytes;
            if (file.size < 1 || file.size > maxBytes) {
                clearSelectedImage();
                setAttachmentError(
                    `${isPdf ? 'PDF files' : 'Images'} must be no larger than ${(maxBytes / 1024 / 1024).toFixed(1)} MB.`
                );
                return;
            }

            clearSelectedImage();
            selectedAttachment = file;
            attachmentName.textContent = `${sanitizeDisplayFilename(file.name)} · ${formatFileSize(file.size)}`;
            attachmentName.title = sanitizeDisplayFilename(file.name);
            if (isPdf) {
                attachmentName.classList.add('is-pdf');
            } else {
                attachmentName.classList.remove('is-pdf');
                selectedImagePreviewUrl = URL.createObjectURL(file);
                attachmentPreviewImage.src = selectedImagePreviewUrl;
                attachmentPreviewImage.hidden = false;
            }
            attachmentPreview.hidden = false;
            uploadProgress.hidden = true;
            uploadProgressBar.style.width = '0%';
            updateVoiceControls();
        }

        function publishTypingState(isTyping) {
            if (
                !actualConversationId
                || !activeRoomName
                || !realtimeReady
                || !realtimeLease
                || disposed
            ) {
                return false;
            }

            try {
                return realtimeLease.publish({
                    conversation_id: actualConversationId,
                    actor_type: currentActorType,
                    actor_id: currentActorId,
                    display_name: currentActorDisplayName,
                    profile_picture: currentActorProfilePicture,
                    event: isTyping ? 'typing.started' : 'typing.stopped'
                });
            } catch (error) {
                if (!disposed) {
                    setStatus(`Typing status could not be delivered: ${error.message}`, 'warning');
                }
                return false;
            }
        }

        function clearTypingTimers() {
            if (typingTimer) {
                clearTimeout(typingTimer);
                typingTimer = null;
            }
            if (typingHeartbeatTimer) {
                clearTimeout(typingHeartbeatTimer);
                typingHeartbeatTimer = null;
            }
        }

        function stopTyping() {
            clearTypingTimers();
            if (!typingActive) {
                return;
            }
            typingActive = false;
            publishTypingState(false);
        }

        function scheduleTypingHeartbeat() {
            if (typingHeartbeatTimer || !typingActive) {
                return;
            }

            typingHeartbeatTimer = setTimeout(() => {
                typingHeartbeatTimer = null;
                if (!typingActive || !input.value.trim()) {
                    stopTyping();
                    return;
                }
                publishTypingState(true);
                scheduleTypingHeartbeat();
            }, typingHeartbeatInterval);
        }

        function handleComposerInput() {
            if (!input.value.trim()) {
                stopTyping();
                return;
            }

            if (
                !actualConversationId
                || !realtimeReady
                || !activeRoomName
                || !realtimeLease
            ) {
                return;
            }

            if (!typingActive) {
                typingActive = true;
                publishTypingState(true);
            }
            if (typingTimer) {
                clearTimeout(typingTimer);
            }
            typingTimer = setTimeout(stopTyping, typingIdleTimeout);
            scheduleTypingHeartbeat();
        }

        function renderTypingStatus() {
            const keepNearBottom = shouldAutoScroll();
            const previousHeight = typingIndicator.getBoundingClientRect().height;
            typingPeople.replaceChildren();
            const users = Array.from(typingUsers.values());
            typingIndicator.hidden = users.length === 0;
            typingIndicator.setAttribute(
                'aria-label',
                users.length === 1
                    ? 'One participant is typing'
                    : `${users.length} participants are typing`
            );
            users.forEach(user => {
                const typingPerson = document.createElement('span');
                typingPerson.className = 'transaction-chat-typing-person';
                typingPerson.append(
                    createChatAvatar(
                        user.profilePicture,
                        user.fallbackName,
                        'transaction-chat-avatar transaction-chat-typing-avatar'
                    )
                );

                const dots = document.createElement('span');
                dots.className = 'transaction-chat-typing-dots';
                dots.setAttribute('aria-hidden', 'true');
                for (let index = 0; index < 3; index++) {
                    const dot = document.createElement('span');
                    dot.className = 'transaction-chat-typing-dot';
                    dots.append(dot);
                }
                typingPerson.append(dots);
                typingPeople.append(typingPerson);
            });
            const addedHeight = typingIndicator.getBoundingClientRect().height - previousHeight;
            if (keepNearBottom && addedHeight > 0) {
                messageList.scrollTop += addedHeight;
            }
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
            const fallbackName = payload.actor_type === 'admin'
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
                }, remoteTypingTimeout);
                typingUsers.set(actorKey, {
                    fallbackName,
                    profilePicture: payload.profile_picture || '',
                    timer
                });
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
        attachmentDialogClose.addEventListener('click', () => attachmentDialog.close(), {
            signal: widgetEventController.signal
        });
        attachmentDialog.addEventListener('click', event => {
            if (event.target === attachmentDialog) {
                attachmentDialog.close();
            }
        }, {signal: widgetEventController.signal});

        attachmentToggle.addEventListener('click', () => attachmentInput.click(), {
            signal: widgetEventController.signal
        });
        attachmentInput.addEventListener('change', () => {
            chooseAttachment(attachmentInput.files?.[0] || null);
        }, {signal: widgetEventController.signal});
        voiceToggle.addEventListener('click', () => {
            if (voiceState === 'recording') {
                stopVoiceRecording();
            } else {
                startVoiceRecording();
            }
        }, {signal: widgetEventController.signal});
        voiceCancel.addEventListener('click', cancelVoiceComposer, {
            signal: widgetEventController.signal
        });
        voiceSend.addEventListener('click', () => {
            if (voiceState === 'preview' && recordedVoiceFile && !sendingMessage) {
                form.requestSubmit();
            }
        }, {signal: widgetEventController.signal});
        attachmentRemove.addEventListener('click', () => {
            clearSelectedImage();
            setAttachmentError();
        }, {signal: widgetEventController.signal});

        function isConversationViewed() {
            const floatingWindow = widget.closest('[data-floating-chat-window]');
            const chatBody = widget.closest('.floating-chat-window-body');
            if (
                document.hidden
                || widget.getClientRects().length === 0
                || (chatBody && (
                    chatBody.getClientRects().length === 0
                    || getComputedStyle(chatBody).visibility === 'hidden'
                ))
            ) {
                return false;
            }
            if (!floatingWindow) {
                return true;
            }
            return !floatingWindow.hidden
                && (!floatingWindow.classList.contains('is-maximized')
                    || window.innerWidth > 700
                    || floatingWindow.classList.contains('is-mobile-chat-open'))
                && (
                    !floatingWindow.hidden
                    && !floatingWindow.classList.contains('is-closing')
                    && !floatingWindow.classList.contains('is-minimized')
                );
        }

        function markVisibleConversationRead() {
            if (!isConversationViewed() || messageMap.size === 0) {
                return;
            }
            const orderedMessages = Array.from(messageMap.values()).sort(compareMessages);
            const newestMessage = orderedMessages[orderedMessages.length - 1];
            if (newestMessage) {
                markRead(Number(newestMessage.id));
            }
        }

        document.addEventListener(
            'visibilitychange',
            markVisibleConversationRead,
            {signal: widgetEventController.signal}
        );
        window.addEventListener(
            'resize',
            markVisibleConversationRead,
            {signal: widgetEventController.signal}
        );
        const floatingWindow = widget.closest('[data-floating-chat-window]');
        if (floatingWindow) {
            visibilityObserver = new MutationObserver(markVisibleConversationRead);
            visibilityObserver.observe(floatingWindow, {
                attributes: true,
                attributeFilter: ['class', 'hidden']
            });
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

            sorted.forEach((message, index) => {
                const previous = sorted[index - 1];
                const next = sorted[index + 1];
                const sameSender = (candidate) => candidate
                    && candidate.sender_type === message.sender_type
                    && Number(candidate.sender_id) === Number(message.sender_id);
                fragment.append(renderMessage(
                    widget,
                    message,
                    currentActorType,
                    currentActorId,
                    conversationType,
                    sameSender(previous),
                    !sameSender(next)
                ));
            });

            messageList.querySelectorAll('audio').forEach(audio => audio.pause());
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

            if (
                readRequestInProgress
                || disposed
                || !actualConversationId
                || !isConversationViewed()
            ) {
                return;
            }

            readRequestInProgress = true;
            try {
                while (
                    queuedReadMessageId > lastReadMessageId
                    && !disposed
                    && isConversationViewed()
                ) {
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
                const incomingMessageIds = (data.messages || [])
                    .filter(message => (
                        message.sender_type !== currentActorType
                        || Number(message.sender_id) !== currentActorId
                    ))
                    .map(message => Number(message.id))
                    .filter(messageId => Number.isInteger(messageId) && messageId > 0);
                if (incomingMessageIds.length > 0) {
                    try {
                        await postForm('/VNZ-TECH/backend/chat_messages.php', {
                            action: 'delivered',
                            conversation_id: String(actualConversationId),
                            message_ids: incomingMessageIds.join(','),
                            csrf_token: csrfToken
                        });
                    } catch (error) {
                        setStatus(`Messages loaded, but delivery receipts could not be recorded: ${error.message}`, 'warning');
                    }
                }
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

        function handleRealtimeEvent(payload) {
            if (!payload || Number(payload.conversation_id) !== actualConversationId) {
                return;
            }

            if (payload.event === 'presence.updated') {
                window.dispatchEvent(new CustomEvent('vnz:chat-presence-changed', {
                    detail: {
                        conversationId: actualConversationId,
                        actorType: payload.actor_type,
                        actorId: payload.actor_id,
                        presence: payload.presence
                    }
                }));
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

            if (
                payload.event === 'message.edited'
                || payload.event === 'message.deleted'
                || payload.event === 'message.reaction_updated'
                || payload.event === 'message.receipts_updated'
            ) {
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
                } else {
                    loadHistory();
                    return;
                }
                renderMessages(shouldAutoScroll());
                return;
            }

            if (payload.event === 'typing.started' || payload.event === 'typing.stopped') {
                handleTypingEvent(payload);
            }
        }

        async function connectRealtime() {
            if (!channelId) {
                setConnection('Offline');
                setStatus('Realtime is not configured. Messages can still be loaded and sent.', 'warning');
                return;
            }

            try {
                setConnection('Connecting');
                realtimeLease = await sharedRealtime.acquire(
                    channelId,
                    actualConversationId,
                    csrfToken,
                    {
                        onEvent: handleRealtimeEvent,
                        onState: (ready, error) => {
                            realtimeReady = ready;
                            if (ready) {
                                activeRoomName = String(actualConversationId);
                                setConnection('Connected', 'connected');
                                setStatus(`Connected to ${conversationTitle}.`);
                                if (realtimeLease && input.value.trim()) {
                                    handleComposerInput();
                                }
                            } else {
                                activeRoomName = null;
                                clearTypingTimers();
                                typingActive = false;
                                typingUsers.forEach(entry => clearTimeout(entry.timer));
                                typingUsers.clear();
                                renderTypingStatus();
                                setConnection(error ? 'Unavailable' : 'Reconnecting', error ? 'error' : '');
                                if (error) {
                                    setStatus(`Realtime authorization failed: ${error.message}`, 'warning');
                                }
                            }
                        }
                    }
                );
                if (disposed) {
                    realtimeLease.release();
                    realtimeLease = null;
                    return;
                }
                if (!disposed) {
                    setConnection('Connected', 'connected');
                    setStatus(`Connected to ${conversationTitle}.`);
                    input.disabled = false;
                    sendButton.disabled = false;
                    emojiToggle.disabled = false;
                    attachmentToggle.disabled = false;
                    updateVoiceControls();
                }
            } catch (error) {
                realtimeReady = false;
                activeRoomName = null;
                setConnection('Unavailable', 'error');
                setStatus(`Realtime connection unavailable: ${error.message}`, 'warning');
            }
        }

        input.addEventListener('keydown', event => {
            if (
                event.key !== 'Enter'
                || event.shiftKey
                || event.isComposing
                || event.keyCode === 229
            ) {
                return;
            }

            event.preventDefault();
            if (sendingMessage || sendOutcomeUnknown) {
                return;
            }

            form.requestSubmit();
        });

        form.addEventListener('submit', async event => {
            event.preventDefault();
            const submittedContent = input.value.trim();
            const hasVoiceRecording = voiceState === 'preview' && Boolean(recordedVoiceFile);
            const hasAttachment = Boolean(selectedAttachment) || hasVoiceRecording;
            const attachmentFile = hasVoiceRecording ? recordedVoiceFile : selectedAttachment;
            closeEmojiPicker();

            if (
                (!submittedContent && !hasAttachment)
                || sendingMessage
                || sendOutcomeUnknown
                || !actualConversationId
            ) {
                return;
            }
            stopTyping();

            const isBookMeCommand = !hasAttachment
                && submittedContent.toLowerCase() === bookMeCommand;
            if (isBookMeCommand && currentActorType !== 'admin') {
                setStatus('Only Admin can use the /bookme command.', 'warning');
                return;
            }

            const content = submittedContent;
            sendingMessage = true;
            sendButton.disabled = true;
            attachmentToggle.disabled = true;
            updateVoiceControls();
            setAttachmentError();
            if (hasAttachment) {
                uploadProgress.hidden = false;
                uploadProgressBar.style.width = '0%';
                setStatus(hasVoiceRecording
                    ? 'Uploading voice message…'
                    : attachmentFile.type === 'application/pdf'
                        || attachmentFile.name.toLowerCase().endsWith('.pdf')
                        ? 'Uploading PDF…'
                        : 'Uploading image…');
            } else {
                uploadProgress.hidden = true;
                setStatus(isBookMeCommand ? 'Sending Lalamove booking details…' : 'Sending message…');
            }

            try {
                const payload = {
                    conversation_id: String(actualConversationId),
                    content,
                    message_type: hasAttachment
                        ? hasVoiceRecording
                            ? 'voice'
                            : attachmentFile.type === 'application/pdf'
                                || attachmentFile.name.toLowerCase().endsWith('.pdf')
                                ? 'file'
                                : 'image'
                        : 'text',
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

                const data = hasAttachment
                    ? await postUploadForm(
                        '/VNZ-TECH/backend/chat_messages.php',
                        payload,
                        attachmentFile,
                        percent => {
                            uploadProgressBar.style.width = `${percent}%`;
                            setStatus(`Uploading ${hasVoiceRecording ? 'voice message' : 'attachment'}… ${percent}%`);
                        }
                    )
                    : await postForm('/VNZ-TECH/backend/chat_messages.php', payload);

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
                    clearSelectedImage();
                    if (hasVoiceRecording) {
                        releaseVoicePreview();
                        voiceState = 'idle';
                        voiceStatus.textContent = 'Ready to record';
                        updateVoiceControls();
                    }
                    uploadProgress.hidden = true;
                    uploadProgressBar.style.width = '0%';
                    setAttachmentError();
                    if (data.realtime_delivery === 'published') {
                        setStatus(hasAttachment
                            ? (data.message.message_type === 'voice'
                                ? 'Voice message sent.'
                                : data.message.message_type === 'file' ? 'PDF sent.' : 'Image sent.')
                            : 'Message sent.');
                    } else {
                        setStatus(
                            `Message saved. Realtime delivery: ${String(data.realtime_delivery || 'unknown').replaceAll('_', ' ')}. The message remains in history.`,
                            'warning'
                        );
                    }
                }
            } catch (error) {
                uploadProgress.hidden = true;
                if (hasAttachment) {
                    setAttachmentError(error.message);
                }
                if (Number.isInteger(error.status)) {
                    setStatus(`Message was not sent: ${error.message}`, 'error');
                } else {
                    sendOutcomeUnknown = true;
                    input.disabled = true;
                    emojiToggle.disabled = true;
                    attachmentToggle.disabled = true;
                    setStatus(
                        `Message outcome is uncertain (${error.message}). Check message history before retrying.`,
                        'error'
                    );
                }
            } finally {
                sendingMessage = false;
                updateVoiceControls();
            }
        });

        input.addEventListener('input', () => {
            handleComposerInput();
            updateVoiceControls();
        });

        input.addEventListener('blur', () => {
            stopTyping();
        });

        function closeMessageMenu(menu, button) {
            menu.hidden = true;
            menu.classList.remove('opens-up', 'opens-left');
            button?.setAttribute('aria-expanded', 'false');
        }

        function positionMessageMenu(menu) {
            const bounds = messageList.getBoundingClientRect();
            const leftBound = bounds.left + messageList.clientLeft;
            const topBound = bounds.top + messageList.clientTop;
            const rightBound = leftBound + messageList.clientWidth;
            const bottomBound = topBound + messageList.clientHeight;
            let bestPlacement = null;
            let bestOverflow = Infinity;

            for (const opensUp of [false, true]) {
                for (const opensLeft of [false, true]) {
                    menu.classList.toggle('opens-up', opensUp);
                    menu.classList.toggle('opens-left', opensLeft);
                    const rect = menu.getBoundingClientRect();
                    const overflow = Math.max(0, leftBound - rect.left)
                        + Math.max(0, rect.right - rightBound)
                        + Math.max(0, topBound - rect.top)
                        + Math.max(0, rect.bottom - bottomBound);
                    if (overflow < bestOverflow) {
                        bestOverflow = overflow;
                        bestPlacement = {opensUp, opensLeft};
                    }
                }
            }

            menu.classList.toggle('opens-up', bestPlacement?.opensUp === true);
            menu.classList.toggle('opens-left', bestPlacement?.opensLeft === true);
        }

        const repositionOpenMessageMenu = () => {
            const menu = messageList.querySelector('[role="menu"]:not([hidden])');
            if (menu) {
                positionMessageMenu(menu);
            }
        };
        messageList.addEventListener('scroll', repositionOpenMessageMenu, {
            passive: true,
            signal: widgetEventController.signal
        });
        window.addEventListener('resize', repositionOpenMessageMenu, {
            signal: widgetEventController.signal
        });

        messageList.addEventListener('click', async event => {
            const attachmentView = event.target.closest('[data-chat-attachment-view]');
            if (attachmentView && messageList.contains(attachmentView)) {
                attachmentDialogImage.src = attachmentView.dataset.chatAttachmentView;
                if (!attachmentDialog.open) {
                    attachmentDialog.showModal();
                }
                attachmentDialogClose.focus();
                return;
            }

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
                    const otherMenu = button.parentElement.querySelector('[role="menu"]');
                    if (otherMenu) {
                        closeMessageMenu(otherMenu, button);
                    }
                });
                menu.hidden = !opening;
                moreButton.setAttribute('aria-expanded', String(opening));
                if (opening) {
                    positionMessageMenu(menu);
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
                closeMessageMenu(
                    menu,
                    menu.parentElement.querySelector('[data-chat-more]')
                );
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
                closeMessageMenu(
                    menu,
                    menu.parentElement.querySelector('[data-chat-more]')
                );
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
                    closeMessageMenu(menu, button);
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
                closeMessageMenu(openMenu, moreButton);
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
                widget.dataset.conversationId = String(actualConversationId);

                input.disabled = false;
                sendButton.disabled = false;
                emojiToggle.disabled = false;
                await loadHistory(null, {forceScroll: true});
                updateVoiceControls();
                await connectRealtime();
                if (input.value.trim()) {
                    handleComposerInput();
                }
                window.dispatchEvent(new CustomEvent('vnz:chat-state-changed', {
                    detail: {conversationId: actualConversationId}
                }));
            } catch (error) {
                setStatus(`Chat is unavailable: ${error.message}`, 'error');
                setConnection('Unavailable', 'error');
                input.disabled = true;
                sendButton.disabled = true;
                emojiToggle.disabled = true;
                attachmentToggle.disabled = true;
                voiceToggle.disabled = true;
                updateVoiceControls();
            }
        }

        activeChats.set(widget, () => {
            stopTyping();
            disposed = true;
            voiceRequestToken++;
            if (voiceState === 'recording' || voiceState === 'processing') {
                stopVoiceRecording(true);
            } else {
                cancelVoiceComposer();
            }
            releaseVoiceMedia();
            releaseVoicePreview();
            messageList.querySelectorAll('audio').forEach(audio => {
                audio.pause();
                audio.removeAttribute('src');
                audio.load();
            });
            widgetEventController.abort();
            visibilityObserver?.disconnect();
            realtimeLease?.release();
            realtimeLease = null;
            clearTypingTimers();
            typingUsers.forEach(entry => clearTimeout(entry.timer));
            if (landmarkDialog.open) {
                landmarkDialog.close();
            }
            if (attachmentDialog.open) {
                attachmentDialog.close();
            }
            clearSelectedImage();
            landmarkDialog.remove();
            attachmentDialog.remove();
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
