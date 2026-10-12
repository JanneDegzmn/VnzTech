(() => {
    const activeChats = new Map();
    const bookMeCommand = '/bookme';
    const shareLocationUrl = 'https://maps.app.goo.gl/iqFq3UGQuqybbY6VA';
    const lalamoveBookingUrl = 'https://www.lalamove.com/en-ph/';
    const landmarkImageUrl = '/VNZ-TECH/assets/images/location.jpg';
    const bookingMessagePrefix = 'LALAMOVE_BOOKING_V1';
    let activeVoiceChat = null;
    let voiceMediaAcquisitionInFlight = 0;
    let activeCallChat = null;
    let activeCallSessionId = null;
    let guestPresenceHeartbeatTimer = null;
    let guestPresenceHeartbeatInFlight = false;

    // Diagnostic-only registry of VNZ-owned media streams (voice-message recording
    // and live call local streams). Used solely to report counts/states of active
    // recorder/call tracks at the video-call media request, so a NotReadableError
    // can be distinguished between a VNZ stream leak and an external device owner.
    // Never stores device labels, ids, tokens, SDP, or credentials.
    const vnzOwnedMediaStreams = new Set();

    function registerVnzMediaStream(stream) {
        if (stream && typeof stream.getTracks === 'function') {
            vnzOwnedMediaStreams.add(stream);
        }
    }

    function unregisterVnzMediaStream(stream) {
        if (stream) {
            vnzOwnedMediaStreams.delete(stream);
        }
    }

    function vnzOwnedMediaTrackSummary() {
        let audioLive = 0;
        let videoLive = 0;
        let audioEnded = 0;
        let videoEnded = 0;
        vnzOwnedMediaStreams.forEach(stream => {
            stream.getTracks().forEach(track => {
                if (track.kind === 'audio') {
                    if (track.readyState === 'live') {
                        audioLive += 1;
                    } else {
                        audioEnded += 1;
                    }
                } else if (track.kind === 'video') {
                    if (track.readyState === 'live') {
                        videoLive += 1;
                    } else {
                        videoEnded += 1;
                    }
                }
            });
        });
        return {
            streams: vnzOwnedMediaStreams.size,
            audioLive,
            videoLive,
            audioEnded,
            videoEnded
        };
    }

    function ensureCallOwnerHost() {
        let host = document.querySelector('[data-call-owner-host]');
        if (!host) {
            host = document.createElement('div');
            host.hidden = true;
            host.setAttribute('aria-hidden', 'true');
            host.dataset.callOwnerHost = '';
            document.body.append(host);
        }
        return host;
    }

    function releaseRetainedCallOwner(widget) {
        if (!widget) {
            return;
        }
        if (widget.hasAttribute('data-call-controller-only')) {
            window.dispatchEvent(new CustomEvent('vnz:call-controller-released', {
                detail: {
                    conversationId: Number(widget.dataset.callControllerConversationId)
                }
            }));
            const cleanup = activeChats.get(widget);
            widget.remove();
            cleanup?.();
            return;
        }
        widget.removeAttribute('data-active-call-owner');
        if (!widget.closest('[data-call-owner-host]')) {
            return;
        }
        const cleanup = activeChats.get(widget);
        widget.remove();
        cleanup?.();
    }

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

    async function postForm(url, fields, signal = null, onResponse = null) {
        const response = await fetch(url, {
            method: 'POST',
            credentials: 'same-origin',
            cache: 'no-store',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8'
            },
            body: new URLSearchParams(fields),
            signal: signal || undefined
        });
        if (typeof onResponse === 'function') {
            onResponse(response.status);
        }

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
        const callInboxCallbacks = new Map();
        let callInboxSubscription = null;
        let callInboxRoomName = null;
        let callInboxCsrfToken = '';

        function setCallInboxState(ready, error = null) {
            callInboxCallbacks.forEach(callbacks => callbacks.onState?.(ready, error));
        }

        function subscribeCallInbox(roomName) {
            if (callInboxSubscription) {
                return Promise.resolve();
            }
            return new Promise((resolve, reject) => {
                const subscription = drone.subscribe(roomName);
                callInboxSubscription = subscription;
                subscription.on('open', error => {
                    if (callInboxSubscription !== subscription) {
                        return;
                    }
                    if (error) {
                        callInboxSubscription = null;
                        setCallInboxState(false, new Error('Incoming call notifications are unavailable.'));
                        reject(new Error('Incoming call notifications are unavailable.'));
                        return;
                    }
                    setCallInboxState(true);
                    subscription.on('message', event => {
                        if (callInboxSubscription === subscription) {
                            callInboxCallbacks.forEach(callbacks => callbacks.onEvent?.(event?.data));
                        }
                    });
                    resolve();
                });
            });
        }

        function setRoomState(room, ready, error = null) {
            room.ready = ready;
            room.callbacks.forEach(callbacks => {
                callbacks.onState?.(ready, error);
            });
        }

        function notifyRoom(room, payload) {
            room.callbacks.forEach(callbacks => callbacks.onEvent?.(payload));
        }

        function subscribeRoom(roomName, room, callRoom = false) {
            const subscriptionKey = callRoom ? 'callSubscription' : 'subscription';
            if (room[subscriptionKey]) {
                return Promise.resolve();
            }

            return new Promise((resolve, reject) => {
                const subscription = drone.subscribe(roomName);
                room[subscriptionKey] = subscription;
                subscription.on('open', error => {
                    if (rooms.get(room.conversationId) !== room) {
                        return;
                    }
                    if (error) {
                        room[subscriptionKey] = null;
                        const callError = new Error(
                            typeof error?.message === 'string' && error.message.length > 0
                                ? error.message
                                : 'Call signaling room subscription was rejected.'
                        );
                        if (callRoom) {
                            room.callReady = false;
                            room.callbacks.forEach(callbacks => {
                                callbacks.onCallState?.(false, callError);
                            });
                        } else {
                            setRoomState(room, false, new Error('Could not subscribe to this conversation.'));
                        }
                        reject(callRoom
                            ? callError
                            : new Error('Could not subscribe to this conversation.'));
                        return;
                    }

                    if (callRoom) {
                        room.callReady = true;
                        room.callbacks.forEach(callbacks => callbacks.onCallState?.(true));
                    } else {
                        setRoomState(room, true);
                    }
                    subscription.on('message', event => {
                        if (rooms.get(room.conversationId) === room) {
                            if (callRoom) {
                                room.callbacks.forEach(callbacks => callbacks.onCallEvent?.(event?.data));
                            } else {
                                notifyRoom(room, event?.data);
                            }
                        }
                    });
                    resolve();
                });
            });
        }

        async function authenticateForRooms() {
            const desiredRooms = Array.from(rooms.entries())
                .filter(([, room]) => room.callbacks.size > 0);
            const watchCallInbox = callInboxCallbacks.size > 0;
            if (desiredRooms.length === 0 && !watchCallInbox) {
                return;
            }

            const csrfToken = desiredRooms[0]?.[1].csrfToken || callInboxCsrfToken;
            const authorization = await postForm(
                '/VNZ-TECH/backend/chat_scaledrone_auth.php',
                {
                    conversation_ids: desiredRooms.map(([id]) => id).join(','),
                    client_id: drone.clientId,
                    call_inbox: watchCallInbox ? '1' : '0',
                    csrf_token: csrfToken
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
            const authorizedCallRoomNames = new Map(
                (authorization.call_rooms || []).map(room => [
                    String(room.conversation_id),
                    room.room
                ])
            );

            await new Promise((resolve, reject) => {
                pendingAuthentication = {resolve, reject};
                drone.authenticate(authorization.token);
            });

            for (const [conversationId, room] of desiredRooms) {
                if (rooms.get(conversationId) !== room) {
                    continue;
                }
                const roomName = authorizedRoomNames.get(conversationId);
                if (!roomName) {
                    throw new Error('Realtime authorization omitted an active conversation.');
                }
                room.roomName = roomName;
                const needsConversationRoom = Array.from(room.callbacks.values())
                    .some(callbacks => callbacks.callOnly !== true);
                if (needsConversationRoom && !room.subscription) {
                    await subscribeRoom(roomName, room);
                } else if (!needsConversationRoom && room.subscription) {
                    room.subscription.unsubscribe?.();
                    room.subscription = null;
                    setRoomState(room, false);
                }
                room.callRoomName = authorizedCallRoomNames.get(conversationId) || null;
                if (room.callRoomName && !room.callSubscription) {
                    await subscribeRoom(room.callRoomName, room, true).catch(error => {
                        console.warn('Conversation call signaling subscription failed:', error.message);
                        room.callbacks.forEach(callbacks => {
                            callbacks.onCallState?.(false, error);
                        });
                    });
                } else if (!room.callRoomName) {
                    const error = new Error(
                        'Realtime authorization omitted the conversation call room.'
                    );
                    room.callReady = false;
                    room.callbacks.forEach(callbacks => {
                        callbacks.onCallState?.(false, error);
                    });
                }
            }
            if (watchCallInbox) {
                callInboxRoomName = authorization.call_inbox_room || null;
                if (!callInboxRoomName) {
                    throw new Error('Realtime authorization omitted the call inbox.');
                }
                if (!callInboxSubscription) {
                    await subscribeCallInbox(callInboxRoomName);
                }
            } else if (callInboxSubscription) {
                callInboxSubscription.unsubscribe?.();
                callInboxSubscription = null;
                callInboxRoomName = null;
                setCallInboxState(false);
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
                    rooms.forEach(room => {
                        room.callReady = false;
                        room.callbacks.forEach(callbacks => callbacks.onCallState?.(false));
                    });
                    setCallInboxState(false);
                });

                drone.on('reconnect', () => {
                    callInboxSubscription?.unsubscribe?.();
                    callInboxSubscription = null;
                    setCallInboxState(false);
                    rooms.forEach(room => {
                        room.subscription?.unsubscribe?.();
                        room.callSubscription?.unsubscribe?.();
                        room.subscription = null;
                        room.callSubscription = null;
                        room.callReady = false;
                        setRoomState(room, false);
                        room.callbacks.forEach(callbacks => callbacks.onCallState?.(false));
                    });
                    requestReconciliation().catch(error => {
                        rooms.forEach(room => setRoomState(room, false, error));
                    });
                });

                drone.on('error', error => {
                    rooms.forEach(room => setRoomState(room, false, error));
                    rooms.forEach(room => {
                        room.callReady = false;
                        room.callbacks.forEach(callbacks => callbacks.onCallState?.(false, error));
                    });
                    setCallInboxState(false, error);
                });
            });
            return connectionPromise;
        }

        async function acquire(requestedChannelId, conversationId, csrfToken, callbacks) {
            try {
                await connect(requestedChannelId);
            } catch (error) {
                if (rooms.size === 0 && callInboxCallbacks.size === 0 && drone) {
                    drone.close?.();
                    drone = null;
                    channelId = null;
                    connectionPromise = null;
                    pendingAuthentication = null;
                }
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
                    callSubscription: null,
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
                    room.callSubscription?.unsubscribe?.();
                }
                if (rooms.size === 0 && callInboxCallbacks.size === 0 && drone) {
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
            if (room.callReady) {
                callbacks.onCallState?.(true);
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
                room.callSubscription?.unsubscribe?.();
                room.subscription = null;
                room.callSubscription = null;
                room.callReady = false;
                if (rooms.size === 0 && callInboxCallbacks.size === 0 && drone) {
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

        async function acquireCallInbox(requestedChannelId, csrfToken, callbacks) {
            try {
                await connect(requestedChannelId);
            } catch (error) {
                if (rooms.size === 0 && callInboxCallbacks.size === 0 && drone) {
                    drone.close?.();
                    drone = null;
                    channelId = null;
                    connectionPromise = null;
                    pendingAuthentication = null;
                }
                throw error;
            }
            const callbackId = Symbol('call-inbox');
            callInboxCsrfToken = csrfToken;
            callInboxCallbacks.set(callbackId, callbacks);
            try {
                await requestReconciliation();
            } catch (error) {
                callInboxCallbacks.delete(callbackId);
                if (callInboxCallbacks.size === 0 && callInboxSubscription) {
                    callInboxSubscription.unsubscribe?.();
                    callInboxSubscription = null;
                }
                if (rooms.size === 0 && callInboxCallbacks.size === 0 && drone) {
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
            return {
                release: () => {
                    if (released) {
                        return;
                    }
                    released = true;
                    callInboxCallbacks.delete(callbackId);
                    if (callInboxCallbacks.size === 0) {
                        callInboxSubscription?.unsubscribe?.();
                        callInboxSubscription = null;
                        callInboxRoomName = null;
                        setCallInboxState(false);
                    }
                    if (rooms.size === 0 && callInboxCallbacks.size === 0 && drone) {
                        drone.close?.();
                        drone = null;
                        channelId = null;
                        connectionPromise = null;
                        pendingAuthentication = null;
                        reconciliation = Promise.resolve();
                        reconciliationRequested = false;
                    } else if (drone) {
                        requestReconciliation().catch(error => {
                            setCallInboxState(false, error);
                        });
                    }
                }
            };
        }

        return {acquire, acquireCallInbox};
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
        row.tabIndex = message.message_type === 'system' ? -1 : 0;
        const ownMessage = message.sender_type === currentActorType
            && Number(message.sender_id) === currentActorId;
        if (message.message_type === 'system') {
            row.className = 'transaction-chat-call-summary-row';
            row.dataset.messageId = String(message.id);
            const summary = document.createElement('div');
            summary.className = 'transaction-chat-call-summary';
            const icon = document.createElement('span');
            icon.className = 'transaction-chat-call-summary-icon';
            icon.setAttribute('aria-hidden', 'true');
            icon.textContent = '☎';
            const text = document.createElement('span');
            text.className = 'transaction-chat-call-summary-text';
            text.textContent = getMessageContentText(message);
            const time = document.createElement('time');
            time.dateTime = String(message.created_at || '');
            time.textContent = formatTimestamp(message.created_at);
            summary.append(icon, text, time);
            row.append(summary);
            return row;
        }
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
                || (
                    message.sender_type === 'seller'
                        ? 'Seller'
                        : (message.sender_type === 'guest' ? 'Guest Seller' : 'Admin')
                )
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
        const callsEnabled = widget.dataset.callEnabled === 'true';
        let iceServers = [];
        let callConfigurationError = '';
        if (callsEnabled) {
            try {
                iceServers = JSON.parse(widget.dataset.callIceServers || '[]');
                if (!Array.isArray(iceServers)) {
                    throw new Error('ICE configuration must be a list.');
                }
            } catch (error) {
                iceServers = [];
                callConfigurationError = 'WebRTC ICE server configuration is invalid.';
            }
        }
        const currentActorType = widget.dataset.actorType || '';
        const currentActorId = Number(widget.dataset.actorId) || 0;
        if (currentActorType === 'guest' && guestPresenceHeartbeatTimer === null) {
            const sendGuestPresenceHeartbeat = async () => {
                if (guestPresenceHeartbeatInFlight) {
                    return;
                }
                guestPresenceHeartbeatInFlight = true;
                try {
                    const response = await fetch('/VNZ-TECH/backend/chat_presence.php', {
                        method: 'POST',
                        credentials: 'same-origin',
                        cache: 'no-store',
                        headers: {
                            'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8'
                        },
                        body: new URLSearchParams({
                            csrf_token: widget.dataset.csrfToken || ''
                        })
                    });
                    const payload = await response.json();
                    if (!response.ok || payload.success !== true) {
                        if (response.status >= 400 && response.status < 500) {
                            window.clearInterval(guestPresenceHeartbeatTimer);
                            guestPresenceHeartbeatTimer = null;
                        }
                        throw new Error(payload.message || 'Presence heartbeat failed.');
                    }
                } catch (error) {
                    console.warn('Guest chat presence heartbeat failed:', error.message);
                } finally {
                    guestPresenceHeartbeatInFlight = false;
                }
            };
            sendGuestPresenceHeartbeat();
            guestPresenceHeartbeatTimer = window.setInterval(
                sendGuestPresenceHeartbeat,
                20000
            );
        }
        const currentActorDisplayName = widget.dataset.actorDisplayName
            || (
                currentActorType === 'admin'
                    ? 'Admin'
                    : (currentActorType === 'guest' ? 'Guest Seller' : 'Seller')
            );
        const currentActorProfilePicture = widget.dataset.actorProfilePicture || '';
        const conversationPeerName = widget.dataset.conversationPeerName
            || widget.dataset.conversationTitle
            || 'Participant';
        const conversationPeerPicture = widget.dataset.conversationPeerPicture || '';
        const status = widget.querySelector('[data-chat-status]');
        const revokeGuestButton = widget.querySelector('[data-chat-revoke-guest]');
        if (revokeGuestButton) {
            revokeGuestButton.addEventListener('click', async () => {
                const confirmed = await window.VNZDialog.confirm({
                    title: 'Revoke guest chat access?',
                    message: 'The guest will no longer be able to open or send messages in this conversation.',
                    confirmLabel: 'Revoke access',
                    danger: true
                });
                if (!confirmed) {
                    return;
                }

                revokeGuestButton.disabled = true;
                try {
                    const response = await fetch('/VNZ-TECH/backend/chat_conversations.php', {
                        method: 'POST',
                        credentials: 'same-origin',
                        cache: 'no-store',
                        headers: {
                            'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8'
                        },
                        body: new URLSearchParams({
                            action: 'revoke_guest_access',
                            conversation_id: widget.dataset.conversationId || '',
                            csrf_token: widget.dataset.csrfToken || ''
                        })
                    });
                    const payload = await response.json();
                    if (!response.ok || payload.success !== true) {
                        throw new Error(payload.message || 'Unable to revoke guest chat access.');
                    }
                    revokeGuestButton.textContent = 'Guest access revoked';
                    revokeGuestButton.disabled = true;
                    if (status) {
                        status.textContent = payload.message;
                    }
                } catch (error) {
                    revokeGuestButton.disabled = false;
                    if (status) {
                        status.textContent = error.message || 'Unable to revoke guest chat access.';
                    }
                }
            });
        }
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
        const audioCallButton = widget.querySelector('[data-chat-call-audio]');
        const videoCallButton = widget.querySelector('[data-chat-call-video]');
        const guestCallDialog = widget.querySelector('[data-guest-call-dialog]');
        const guestCallCloseButtons = widget.querySelectorAll('[data-guest-call-close]');
        const isGuestCallPrompt = currentActorType === 'guest'
            && widget.dataset.guestSubmissionChat === 'true';
        const callPanel = widget.querySelector('[data-chat-call-panel]');
        const callDragHandle = widget.querySelector('[data-chat-call-drag-handle]');
        const callAvatar = widget.querySelector('[data-chat-call-avatar]');
        const callTitle = widget.querySelector('[data-chat-call-title]');
        const callKind = widget.querySelector('[data-chat-call-kind]');
        const callStatus = widget.querySelector('[data-chat-call-status]');
        const callMedia = widget.querySelector('[data-chat-call-media]');
        const callVideoPlaceholder = widget.querySelector('[data-chat-call-video-placeholder]');
        const remoteVideo = widget.querySelector('[data-chat-call-remote-video]');
        const localVideo = widget.querySelector('[data-chat-call-local-video]');
        const remoteAudio = widget.querySelector('[data-chat-call-remote-audio]');
        const callAcceptButton = widget.querySelector('[data-chat-call-accept]');
        const callAcceptButtonLabel = Array.from(callAcceptButton?.childNodes || [])
            .find(node => node.nodeType === 3) || null;
        const callDeclineButton = widget.querySelector('[data-chat-call-decline]');
        const callCancelButton = widget.querySelector('[data-chat-call-cancel]');
        const callSoundButton = widget.querySelector('[data-chat-call-sound]');
        const callMinimizeButton = widget.querySelector('[data-chat-call-minimize]');
        const callMaximizeButton = widget.querySelector('[data-chat-call-maximize]');
        const callCloseButton = widget.querySelector('[data-chat-call-close]');
        const callResizeHandles = widget.querySelectorAll('[data-chat-call-resize]');
        const callIndicator = widget.querySelector('[data-chat-call-indicator]');
        const callIndicatorName = widget.querySelector('[data-chat-call-indicator-name]');
        const callIndicatorDuration = widget.querySelector('[data-chat-call-indicator-duration]');
        const callMuteButton = widget.querySelector('[data-chat-call-mute]');
        const callCameraButton = widget.querySelector('[data-chat-call-camera]');
        const callEndButton = widget.querySelector('[data-chat-call-end]');
        const attachmentPreview = widget.querySelector('[data-chat-attachment-preview]');
        const attachmentPreviewImage = widget.querySelector('[data-chat-attachment-preview-image]');
        const attachmentName = widget.querySelector('[data-chat-attachment-name]');
        const attachmentRemove = widget.querySelector('[data-chat-attachment-remove]');
        const uploadProgress = widget.querySelector('[data-chat-upload-progress]');
        const uploadProgressBar = widget.querySelector('[data-chat-upload-progress-bar]');
        const uploadError = widget.querySelector('[data-chat-upload-error]');
        const loadOlderButton = widget.querySelector('[data-chat-load-older]');
        const historyControls = widget.querySelector('.transaction-chat-history-controls');
        const scrollLatestButton = widget.querySelector('[data-chat-scroll-latest]');
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
        const pendingMessageDeletes = new Set();
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
        let olderHistoryExhausted = false;
        let awayFromBottom = false;
        let loadingHistory = false;
        let historyRefreshQueued = false;
        let sendingMessage = false;
        let sendOutcomeUnknown = false;
        let lastReadMessageId = 0;
        let readRequestInProgress = false;
        let queuedReadMessageId = 0;
        let disposed = false;
        let presenceRefreshInFlight = false;
        let presenceRefreshTimer = null;
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
        let callSignalReady = false;
        let callUnavailableReason = '';
        let currentCall = null;
        let callDurationTimer = null;
        let callUnansweredTimer = null;
        let callDisconnectTimer = null;
        let callHideTimer = null;
        let callConnectedTimer = null;
        let callDurationStartedAt = 0;
        let callToneContext = null;
        let callToneTimer = null;
        let callToneOscillators = [];
        let callToneGeneration = 0;
        let requestedCallTone = '';
        let callToneBlocked = false;
        let callWindowBounds = null;
        let callWindowPointerOperation = null;
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

        if (callsEnabled && callPanel && callIndicator) {
            document.body.append(callPanel, callIndicator);
        }

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

        function setPresence(isOnline) {
            const presenceLabel = widget.querySelector('[data-chat-presence]');
            if (presenceLabel) {
                presenceLabel.textContent = isOnline ? 'Online' : 'Offline';
            }
        }

        async function refreshConversationPresence() {
            if (!actualConversationId || presenceRefreshInFlight || disposed) {
                return;
            }
            presenceRefreshInFlight = true;
            try {
                const response = await fetch(
                    '/VNZ-TECH/backend/chat_conversations.php?action=list',
                    {credentials: 'same-origin', cache: 'no-store'}
                );
                const payload = await response.json();
                if (!response.ok || payload.success !== true) {
                    throw new Error(payload.message || 'Presence is unavailable.');
                }
                const conversations = Array.isArray(payload.data?.conversations)
                    ? payload.data.conversations
                    : [];
                const conversation = conversations.find(item =>
                    Number(item.conversation_id) === actualConversationId
                );
                const participant = conversation?.presence?.participants?.[0];
                if (!participant || typeof participant.is_online !== 'boolean') {
                    throw new Error('Presence is unavailable for this conversation.');
                }
                setPresence(participant.is_online);
            } catch {
                const presenceLabel = widget.querySelector('[data-chat-presence]');
                if (presenceLabel) {
                    presenceLabel.textContent = 'Presence unavailable';
                }
            } finally {
                presenceRefreshInFlight = false;
            }
        }

        if (currentActorType === 'guest' && actualConversationId > 0) {
            refreshConversationPresence();
            presenceRefreshTimer = window.setInterval(
                refreshConversationPresence,
                15000
            );
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
                unregisterVnzMediaStream(voiceMediaStream);
                voiceMediaStream.getTracks().forEach(track => track.stop());
                voiceMediaStream = null;
            }
        }

        function voiceRecorderBlocksCall() {
            const recorderState = voiceMediaRecorder?.state;
            const hasLiveMicrophoneTrack = voiceMediaStream
                ?.getAudioTracks()
                .some(track => track.readyState === 'live') === true;
            return voiceMediaAcquisitionInFlight > 0
                || activeVoiceChat !== null
                || ['requesting', 'recording', 'processing'].includes(voiceState)
                || recorderState === 'recording'
                || recorderState === 'paused'
                || hasLiveMicrophoneTrack;
        }

        function showVoiceRecorderCallBlock(call = null) {
            const message = 'Finish or cancel the voice recording before starting or accepting a call.';
            if (call && currentCall === call) {
                call.statusText = message;
                updateCallControls();
            } else {
                showCallOutcome('Call unavailable', message);
            }
            setStatus(message, 'warning');
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
                activeCallChat !== null
                || ['incoming', 'outgoing', 'connecting', 'active'].includes(currentCall?.state)
            ) {
                setAttachmentError('Voice recording is unavailable during a call.');
                return;
            }
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

            voiceMediaAcquisitionInFlight++;
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
                registerVnzMediaStream(stream);
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
            } finally {
                voiceMediaAcquisitionInFlight = Math.max(
                    0,
                    voiceMediaAcquisitionInFlight - 1
                );
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

        function formatCallDuration(milliseconds) {
            const totalSeconds = Math.max(0, Math.floor(milliseconds / 1000));
            const hours = Math.floor(totalSeconds / 3600);
            const minutes = Math.floor((totalSeconds % 3600) / 60);
            const seconds = totalSeconds % 60;
            return hours > 0
                ? `${String(hours).padStart(2, '0')}:${String(minutes).padStart(2, '0')}:${String(seconds).padStart(2, '0')}`
                : `${String(minutes).padStart(2, '0')}:${String(seconds).padStart(2, '0')}`;
        }

        function updateCallDuration(call) {
            if (currentCall !== call || call.state !== 'active' || !callDurationStartedAt) {
                return;
            }
            const duration = formatCallDuration(Date.now() - callDurationStartedAt);
            call.statusText = `Connected · ${duration}`;
            callStatus.textContent = call.statusText;
            if (callIndicatorDuration) {
                callIndicatorDuration.textContent = duration;
            }
        }

        function confirmCallConnected(call) {
            if (
                currentCall !== call
                || call.state !== 'active'
                || call.connectedAcknowledged
                || call.connectedAttempts >= 3
            ) {
                return;
            }
            call.connectedAttempts++;
            sendCallSignal(call, 'call.connected').then(() => {
                call.connectedAcknowledged = true;
                if (callConnectedTimer !== null) {
                    clearTimeout(callConnectedTimer);
                    callConnectedTimer = null;
                }
            }).catch(() => {
                if (currentCall === call && call.connectedAttempts < 3) {
                    callConnectedTimer = setTimeout(() => {
                        callConnectedTimer = null;
                        confirmCallConnected(call);
                    }, 2000);
                } else if (currentCall === call) {
                    setStatus(
                        'The call is connected, but its history confirmation could not be saved.',
                        'warning'
                    );
                }
            });
        }

        function stopCallTone() {
            callToneGeneration++;
            requestedCallTone = '';
            callToneBlocked = false;
            if (callToneTimer !== null) {
                clearTimeout(callToneTimer);
                callToneTimer = null;
            }
            callToneOscillators.forEach(oscillator => {
                try {
                    oscillator.stop();
                } catch (error) {
                    if (error.name !== 'InvalidStateError') {
                        setStatus('Call tone could not be stopped cleanly.', 'warning');
                    }
                }
            });
            callToneOscillators = [];
            if (callToneContext) {
                callToneContext.close().catch(() => {
                    if (!disposed) {
                        setStatus('Call audio could not be released cleanly.', 'warning');
                    }
                });
                callToneContext = null;
            }
            if (callSoundButton) {
                callSoundButton.hidden = true;
            }
        }

        function playCallToneBurst(generation) {
            if (
                generation !== callToneGeneration
                || !callToneContext
                || callToneContext.state !== 'running'
                || !requestedCallTone
                || callToneTimer !== null
                || callToneOscillators.length > 0
            ) {
                return;
            }
            const incoming = requestedCallTone === 'incoming';
            const frequencies = incoming ? [440, 480] : [440, 480];
            const toneDuration = incoming ? 0.48 : 1.8;
            const gain = callToneContext.createGain();
            gain.gain.setValueAtTime(0.0001, callToneContext.currentTime);
            gain.gain.exponentialRampToValueAtTime(0.09, callToneContext.currentTime + 0.025);
            gain.gain.setValueAtTime(0.09, callToneContext.currentTime + toneDuration - 0.04);
            gain.gain.exponentialRampToValueAtTime(0.0001, callToneContext.currentTime + toneDuration);
            gain.connect(callToneContext.destination);
            frequencies.forEach(frequency => {
                const oscillator = callToneContext.createOscillator();
                oscillator.type = 'sine';
                oscillator.frequency.value = frequency;
                oscillator.connect(gain);
                oscillator.onended = () => {
                    oscillator.disconnect();
                    callToneOscillators = callToneOscillators.filter(
                        activeOscillator => activeOscillator !== oscillator
                    );
                };
                oscillator.start();
                oscillator.stop(callToneContext.currentTime + toneDuration);
                callToneOscillators.push(oscillator);
            });
            callToneTimer = setTimeout(() => {
                if (generation !== callToneGeneration) {
                    return;
                }
                callToneTimer = null;
                playCallToneBurst(generation);
            }, incoming ? 2600 : 5200);
        }

        async function resumeCallTone(generation) {
            if (generation !== callToneGeneration || !requestedCallTone) {
                return;
            }
            try {
                const AudioContextClass = window.AudioContext || window.webkitAudioContext;
                if (!AudioContextClass) {
                    callToneBlocked = true;
                    callSoundButton.hidden = false;
                    return;
                }
                if (!callToneContext || callToneContext.state === 'closed') {
                    callToneContext = new AudioContextClass();
                }
                const context = callToneContext;
                await context.resume();
                if (
                    generation !== callToneGeneration
                    || !requestedCallTone
                    || callToneContext !== context
                ) {
                    return;
                }
                if (context.state !== 'running') {
                    throw new Error('Audio playback is blocked by browser policy.');
                }
                callToneBlocked = false;
                callSoundButton.hidden = true;
                playCallToneBurst(generation);
            } catch (error) {
                if (generation !== callToneGeneration || !requestedCallTone) {
                    return;
                }
                callToneBlocked = true;
                callSoundButton.hidden = false;
            }
        }

        function startCallTone(kind) {
            stopCallTone();
            requestedCallTone = kind;
            if (currentCall) {
                logVideoCallDiagnostic(currentCall, 'call-tone-started', null, {kind});
            }
            resumeCallTone(callToneGeneration);
        }

        function clampCallWindowBounds(bounds) {
            const viewportWidth = window.innerWidth;
            const viewportHeight = window.innerHeight;
            const minWidth = Math.min(360, viewportWidth - 24);
            const minHeight = Math.min(300, viewportHeight - 24);
            const width = Math.min(
                Math.max(bounds.width, minWidth),
                viewportWidth - 16
            );
            const height = Math.min(
                Math.max(bounds.height, minHeight),
                viewportHeight - 16
            );
            return {
                left: Math.min(Math.max(bounds.left, 8), viewportWidth - width - 8),
                top: Math.min(Math.max(bounds.top, 8), viewportHeight - height - 8),
                width,
                height
            };
        }

        function applyCallWindowBounds(bounds) {
            if (!callPanel || callPanel.classList.contains('is-maximized')) {
                return;
            }
            callWindowBounds = clampCallWindowBounds(bounds);
            callPanel.classList.add('is-positioned');
            callPanel.style.left = `${callWindowBounds.left}px`;
            callPanel.style.top = `${callWindowBounds.top}px`;
            callPanel.style.right = 'auto';
            callPanel.style.bottom = 'auto';
            callPanel.style.width = `${callWindowBounds.width}px`;
            callPanel.style.height = `${callWindowBounds.height}px`;
        }

        function currentCallWindowBounds() {
            const rect = callPanel.getBoundingClientRect();
            return {
                left: rect.left,
                top: rect.top,
                width: rect.width,
                height: rect.height
            };
        }

        function toggleCallWindowMaximize() {
            if (!currentCall || !callPanel) {
                return;
            }
            const activeWindow = currentCall.state === 'active';
            if (callPanel.classList.contains('is-maximized')) {
                callPanel.classList.remove('is-maximized');
                if (activeWindow) {
                    const bounds = callWindowBounds || {
                        left: window.innerWidth - Math.min(760, window.innerWidth - 32) - 24,
                        top: window.innerHeight - Math.min(520, window.innerHeight - 32) - 24,
                        width: Math.min(760, window.innerWidth - 32),
                        height: Math.min(520, window.innerHeight - 32)
                    };
                    applyCallWindowBounds(bounds);
                } else {
                    callWindowBounds = null;
                    ['left', 'top', 'right', 'bottom', 'width', 'height'].forEach(property => {
                        callPanel.style.removeProperty(property);
                    });
                }
            } else {
                if (activeWindow) {
                    callWindowBounds = currentCallWindowBounds();
                    ['left', 'top', 'right', 'bottom', 'width', 'height'].forEach(property => {
                        callPanel.style.removeProperty(property);
                    });
                }
                callPanel.classList.add('is-maximized');
            }
            updateCallControls();
        }

        function beginCallWindowPointerOperation(event, mode, direction = '') {
            if (
                !currentCall
                || currentCall.state !== 'active'
                || callPanel.classList.contains('is-maximized')
                || event.button !== 0
                || event.target.closest('button, video, audio, input, select, textarea')
            ) {
                return;
            }
            event.preventDefault();
            callWindowPointerOperation = {
                pointerId: event.pointerId,
                mode,
                direction,
                startX: event.clientX,
                startY: event.clientY,
                bounds: currentCallWindowBounds()
            };
            applyCallWindowBounds(callWindowPointerOperation.bounds);
            try {
                callPanel.setPointerCapture(event.pointerId);
            } catch (error) {
                callWindowPointerOperation = null;
                setStatus('The call window could not be moved or resized.', 'warning');
            }
        }

        function updateCallWindowPointerOperation(event) {
            const operation = callWindowPointerOperation;
            if (!operation || operation.pointerId !== event.pointerId) {
                return;
            }
            const deltaX = event.clientX - operation.startX;
            const deltaY = event.clientY - operation.startY;
            let {left, top, width, height} = operation.bounds;
            if (operation.mode === 'drag') {
                applyCallWindowBounds({
                    left: left + deltaX,
                    top: top + deltaY,
                    width,
                    height
                });
                return;
            }

            const direction = operation.direction;
            const resizingWest = direction.includes('w');
            const resizingNorth = direction.includes('n');
            const right = left + width;
            const bottom = top + height;
            if (direction.includes('e')) {
                width += deltaX;
            } else if (resizingWest) {
                width -= deltaX;
            }
            if (direction.includes('s')) {
                height += deltaY;
            } else if (resizingNorth) {
                height -= deltaY;
            }

            const minWidth = Math.min(360, window.innerWidth - 24);
            const minHeight = Math.min(300, window.innerHeight - 24);
            width = Math.min(Math.max(width, minWidth), window.innerWidth - 16);
            height = Math.min(Math.max(height, minHeight), window.innerHeight - 16);
            applyCallWindowBounds({
                left: resizingWest ? right - width : left,
                top: resizingNorth ? bottom - height : top,
                width,
                height
            });
        }

        function endCallWindowPointerOperation(event) {
            if (
                callWindowPointerOperation
                && event.pointerId === callWindowPointerOperation.pointerId
            ) {
                callWindowPointerOperation = null;
            }
        }

        function updateCallControls() {
            if (!callsEnabled || !callPanel) {
                return;
            }
            const call = currentCall;
            const busy = Boolean(call);
            const buttonsDisabled = !callSignalReady
                || !actualConversationId
                || busy
                || Boolean(callConfigurationError)
                || Boolean(callUnavailableReason);
            if (audioCallButton) {
                audioCallButton.disabled = buttonsDisabled;
            }
            if (videoCallButton) {
                videoCallButton.disabled = buttonsDisabled;
            }
            callPanel.hidden = !call || call.minimized === true;
            callPanel.classList.toggle('is-video', call?.mode === 'video');
            callPanel.classList.toggle('is-active-window', call?.state === 'active');
            callPanel.setAttribute(
                'aria-modal',
                String(Boolean(call && call.state !== 'active'))
            );
            callIndicator.hidden = !call || call.minimized !== true;
            callAcceptButton.hidden = call?.state !== 'incoming'
                && call?.mediaRetryAvailable !== true;
            callDeclineButton.hidden = call?.state !== 'incoming';
            callCancelButton.hidden = call?.state !== 'outgoing';
            callMuteButton.hidden = call?.state !== 'active';
            callCameraButton.hidden = call?.state !== 'active' || call?.mode !== 'video';
            callEndButton.hidden = !call || !['connecting', 'active'].includes(call.state);
            callMinimizeButton.hidden = !call;
            callMaximizeButton.hidden = !call;
            callCloseButton.hidden = !call;
            callMaximizeButton.setAttribute(
                'aria-label',
                callPanel.classList.contains('is-maximized')
                    ? 'Restore call window'
                    : 'Maximize call window'
            );
            callMaximizeButton.title = callPanel.classList.contains('is-maximized')
                ? 'Restore call window'
                : 'Maximize call window';
            callMaximizeButton.textContent = callPanel.classList.contains('is-maximized')
                ? '↙'
                : '□';
            callResizeHandles.forEach(handle => {
                handle.hidden = call?.state !== 'active'
                    || callPanel.classList.contains('is-maximized');
            });
            callSoundButton.hidden = !callToneBlocked || !call;
            callMedia.hidden = call?.state !== 'active' || call.mode !== 'video';

            if (!call) {
                if (callConfigurationError) {
                    setStatus(`Calling is unavailable: ${callConfigurationError}`, 'warning');
                } else if (callUnavailableReason) {
                    setStatus(callUnavailableReason, 'warning');
                }
                return;
            }

            const displayName = call.state === 'incoming' ? call.fromName : call.peerName;
            callTitle.textContent = displayName || 'Participant';
            callKind.textContent = `${call.mode === 'video' ? 'Video' : 'Voice'} call`;
            if (callAcceptButtonLabel) {
                callAcceptButtonLabel.textContent = call.mediaRetryAvailable
                    ? ' Retry'
                    : ' Accept';
            }
            callAcceptButton?.setAttribute(
                'aria-label',
                call.mediaRetryAvailable ? 'Retry call setup' : 'Accept call'
            );
            callAvatar.replaceChildren(createChatAvatar(
                call.profilePicture,
                displayName,
                'transaction-chat-avatar'
            ));
            callIndicatorName.textContent = displayName || 'Active call';
            callIndicatorDuration.textContent = call.state === 'active'
                ? formatCallDuration(Date.now() - callDurationStartedAt)
                : call.statusText || 'Call in progress';
            const closeCallLabel = call.state === 'incoming'
                ? 'Decline call'
                : call.state === 'outgoing'
                    ? 'Cancel call'
                    : 'End call';
            callCloseButton.setAttribute('aria-label', closeCallLabel);
            callCloseButton.title = closeCallLabel;
            callMuteButton.textContent = call.muted
                ? 'Unmute microphone'
                : 'Mute microphone';
            callCameraButton.textContent = call.cameraEnabled
                ? 'Turn camera off'
                : 'Turn camera on';
            callStatus.textContent = call.statusText || '';
        }

        function clearCallTimers() {
            [
                callDurationTimer,
                callUnansweredTimer,
                callDisconnectTimer,
                callHideTimer,
                callConnectedTimer
            ]
                .forEach(timer => {
                    if (timer !== null) {
                        clearTimeout(timer);
                        clearInterval(timer);
                    }
                });
            callDurationTimer = null;
            callUnansweredTimer = null;
            callDisconnectTimer = null;
            callHideTimer = null;
            callConnectedTimer = null;
        }

        function closeCallMedia(call) {
            if (!call || call.mediaClosed) {
                return;
            }
            call.mediaClosed = true;
            call.iceRequests?.forEach(controller => controller.abort());
            call.iceRequests?.clear();
            const localStream = call.localStream;
            const remoteStream = call.remoteStream;
            if (call.connection) {
                call.connection.onicecandidate = null;
                call.connection.ontrack = null;
                call.connection.onconnectionstatechange = null;
                call.connection.oniceconnectionstatechange = null;
                call.connection.close();
                call.connection = null;
            }
            unregisterVnzMediaStream(localStream);
            localStream?.getTracks().forEach(track => {
                track.onended = null;
                track.stop();
            });
            call.localStream = null;
            call.videoSender = null;
            call.iceCandidates = [];
            call.pendingOffer = null;
            remoteStream?.getTracks().forEach(track => {
                track.onmute = null;
                track.onunmute = null;
                track.onended = null;
                track.stop();
            });
            call.remoteStream = null;
            if (remoteVideo?.srcObject === remoteStream) {
                remoteVideo.pause();
                remoteVideo.srcObject = null;
            }
            if (remoteAudio?.srcObject === remoteStream) {
                remoteAudio.pause();
                remoteAudio.srcObject = null;
            }
            if (localVideo?.srcObject === localStream) {
                localVideo.pause();
                localVideo.srcObject = null;
            }
        }

        function showCallOutcome(title, text, hideAfterMs = 7000) {
            if (!callPanel || disposed) {
                return;
            }
            callTitle.textContent = title;
            callStatus.textContent = text;
            callKind.textContent = '';
            callAvatar.replaceChildren(createChatAvatar('', title, 'transaction-chat-avatar'));
            callPanel.hidden = false;
            callAcceptButton.hidden = true;
            callDeclineButton.hidden = true;
            callCancelButton.hidden = true;
            callMinimizeButton.hidden = true;
            callMaximizeButton.hidden = true;
            callCloseButton.hidden = true;
            callResizeHandles.forEach(handle => {
                handle.hidden = true;
            });
            callMuteButton.hidden = true;
            callCameraButton.hidden = true;
            callEndButton.hidden = true;
            callMedia.hidden = true;
            callIndicator.hidden = true;
            if (callHideTimer !== null) {
                clearTimeout(callHideTimer);
            }
            callHideTimer = setTimeout(() => {
                callHideTimer = null;
                updateCallControls();
            }, hideAfterMs);
        }

        function logVideoCallDiagnostic(call, stage, error = null, details = {}) {
            if (call?.mode !== 'video') {
                return;
            }
            const connection = call.connection;
            const monotonicMs = window.performance?.now?.() ?? Date.now();
            const diagnostic = {
                stage,
                correlationId: call.id,
                monotonicMs: Math.round(monotonicMs),
                elapsedMs: Number.isFinite(call.startedAt)
                    ? Math.round(monotonicMs - call.startedAt)
                    : null,
                mode: call.mode,
                state: call.state,
                mediaAcquisition: call.mediaAcquisition || 'not-started',
                terminal: call.terminal === true,
                acceptSent: call.acceptSent === true,
                offerPending: Boolean(call.pendingOffer),
                connectionState: connection?.connectionState || null,
                iceConnectionState: connection?.iceConnectionState || null,
                signalingState: connection?.signalingState || null,
                vnzOwnedTracks: vnzOwnedMediaTrackSummary(),
                ...details
            };
            if (error) {
                diagnostic.errorName = typeof error.name === 'string'
                    ? error.name.slice(0, 64)
                    : 'Error';
                diagnostic.errorMessage = typeof error.message === 'string'
                    ? error.message.replace(/[\r\n\t]+/g, ' ').slice(0, 160)
                    : 'Unknown error';
                if (Number.isInteger(error.status)) {
                    diagnostic.httpStatus = error.status;
                    diagnostic.errorCode = `HTTP_${error.status}`;
                }
            }
            console.warn(
                '[chat-video-call-timing] ' + JSON.stringify(diagnostic)
            );
        }

        function callTimingNow() {
            return window.performance?.now?.() ?? Date.now();
        }

        function sendCallSignal(call, event, fields = {}) {
            if (
                event === 'call.ice'
                && (call.terminal === true || currentCall !== call)
            ) {
                logVideoCallDiagnostic(call, 'stale-ice-ignored', null, {event});
                return Promise.resolve({stale: true});
            }
            if (
                !callSignalReady
                && !['call.cancel', 'call.decline', 'call.end', 'call.failed', 'call.missed']
                    .includes(event)
            ) {
                const error = new Error('Call signaling is disconnected.');
                logVideoCallDiagnostic(call, 'signal-failed', error, {event});
                return Promise.reject(error);
            }
            const iceController = event === 'call.ice'
                && typeof AbortController === 'function'
                ? new AbortController()
                : null;
            if (iceController) {
                if (!call.iceRequests) {
                    call.iceRequests = new Set();
                }
                call.iceRequests.add(iceController);
            }
            const requestStartedAt = callTimingNow();
            logVideoCallDiagnostic(call, 'signal-request-start', null, {event});
            return postForm('/VNZ-TECH/backend/chat_call_signal.php', {
                conversation_id: String(actualConversationId),
                call_id: call.id,
                call_event: event,
                csrf_token: csrfToken,
                ...fields
            }, iceController?.signal, httpStatus => {
                logVideoCallDiagnostic(call, 'signal-request-response', null, {
                    event,
                    durationMs: Math.round(callTimingNow() - requestStartedAt),
                    httpStatus
                });
            }).then(result => {
                if (event !== 'call.ice') {
                    logVideoCallDiagnostic(call, 'signal-sent', null, {event});
                }
                return result;
            }).catch(error => {
                logVideoCallDiagnostic(call, 'signal-request-error', null, {
                    event,
                    durationMs: Math.round(callTimingNow() - requestStartedAt),
                    httpStatus: Number.isInteger(error.status) ? error.status : null,
                    errorName: typeof error.name === 'string' ? error.name : 'Error'
                });
                if (iceController?.signal.aborted && error.name === 'AbortError') {
                    logVideoCallDiagnostic(call, 'ice-request-aborted', null, {event});
                    return {stale: true};
                }
                if (
                    event === 'call.ice'
                    && error.status === 409
                ) {
                    if (call.terminal === true) {
                        // Already torn down locally; nothing left to clean up.
                        logVideoCallDiagnostic(
                            call,
                            'stale-ice-rejected-after-terminal',
                            error,
                            {event}
                        );
                        return {stale: true};
                    }
                    // The backend reports the call session already ended (for
                    // example the peer's terminal signal was processed before
                    // this in-flight ICE request). Clean up locally without
                    // sending a redundant call.failed signal.
                    logVideoCallDiagnostic(
                        call,
                        'stale-ice-rejected-session-ended',
                        error,
                        {event}
                    );
                    if (currentCall === call) {
                        finishCall('The other participant ended the call.', null, 'ice-session-ended');
                    }
                    return {stale: true};
                }
                logVideoCallDiagnostic(call, 'signal-failed', error, {event});
                throw error;
            }).finally(() => {
                if (iceController) {
                    call.iceRequests?.delete(iceController);
                }
            });
        }

        function restoreOriginatingConversation(call) {
            const floatingWindow = call?.widget?.closest('[data-floating-chat-window]');
            if (!floatingWindow || !floatingWindow.isConnected) {
                return;
            }
            floatingWindow.hidden = false;
            floatingWindow.classList.remove('is-minimized', 'is-closing');
            floatingWindow.classList.add('is-open');
            if (window.innerWidth <= 700) {
                floatingWindow.classList.add('is-mobile-chat-open');
            }
        }

        function finishCall(outcome, signalEvent = null, terminationSource = 'unspecified') {
            const call = currentCall;
            if (call?.terminal) {
                logVideoCallDiagnostic(call, 'duplicate-termination-ignored', null, {
                    terminationSource,
                    reason: typeof outcome === 'string' ? outcome.slice(0, 120) : null
                });
                return;
            }
            if (call) {
                call.finishStartedAt = callTimingNow();
            }
            logVideoCallDiagnostic(call, 'terminal-cleanup-start', null, {
                signalEvent,
                terminationSource
            });
            stopCallTone();
            logVideoCallDiagnostic(call, 'local-tone-stopped', null, {
                durationMs: call
                    ? Math.round(callTimingNow() - call.finishStartedAt)
                    : null
            });
            if (call) {
                call.terminal = true;
            }
            logVideoCallDiagnostic(call, 'finish', null, {
                reason: typeof outcome === 'string' ? outcome.slice(0, 120) : null,
                signalEvent,
                cleanupStage: call?.diagnosticStage || 'unspecified'
            });
            let terminalSignal = Promise.resolve();
            if (call && signalEvent && !disposed) {
                terminalSignal = sendCallSignal(call, signalEvent).catch(() => {
                    if (!disposed) {
                        setStatus(`${outcome} Call history could not be synchronized.`, 'warning');
                    }
                });
            }
            if (call) {
                restoreOriginatingConversation(call);
                closeCallMedia(call);
            }
            logVideoCallDiagnostic(call, 'local-media-cleanup-complete');
            callWindowPointerOperation = null;
            callWindowBounds = null;
            callPanel.classList.remove(
                'is-maximized',
                'is-video',
                'is-active-window',
                'is-positioned'
            );
            ['left', 'top', 'right', 'bottom', 'width', 'height'].forEach(property => {
                callPanel.style.removeProperty(property);
            });
            clearCallTimers();
            currentCall = null;
            if (activeCallChat === widget) {
                activeCallChat = null;
            }
            if (activeCallSessionId === call?.id) {
                activeCallSessionId = null;
            }
            callPanel.hidden = true;
            callIndicator.hidden = true;
            if (outcome) {
                setStatus(outcome, /failed|unavailable|lost|could not connect/i.test(outcome)
                    ? 'warning'
                    : '');
            }
            updateCallControls();
            logVideoCallDiagnostic(call, 'terminal-local-cleanup-complete');
            if (call) {
                terminalSignal.finally(async () => {
                    if (!disposed && !widget.hasAttribute('data-call-controller-only')) {
                        await loadHistory();
                    }
                    releaseRetainedCallOwner(call.widget);
                });
            }
        }

        function callMediaErrorMessage(error) {
            if (error?.name === 'NotAllowedError' || error?.name === 'PermissionDeniedError') {
                return 'Microphone or camera permission was denied.';
            }
            if (error?.name === 'NotFoundError' || error?.name === 'DevicesNotFoundError') {
                return 'The required microphone or camera was not found.';
            }
            if (error?.name === 'NotReadableError' || error?.name === 'TrackStartError') {
                return 'The microphone or camera is already in use or unavailable.';
            }
            return null;
        }

        function createCallId() {
            if (window.crypto?.randomUUID) {
                return window.crypto.randomUUID();
            }
            if (window.crypto?.getRandomValues) {
                const bytes = window.crypto.getRandomValues(new Uint8Array(16));
                bytes[6] = (bytes[6] & 0x0f) | 0x40;
                bytes[8] = (bytes[8] & 0x3f) | 0x80;
                const hex = Array.from(bytes, byte => byte.toString(16).padStart(2, '0')).join('');
                return `${hex.slice(0, 8)}-${hex.slice(8, 12)}-${hex.slice(12, 16)}-${hex.slice(16, 20)}-${hex.slice(20)}`;
            }
            throw new Error('Secure random call IDs are unavailable in this browser.');
        }

        function signalFailure(call, error) {
            if (currentCall !== call) {
                return;
            }
            logVideoCallDiagnostic(call, call.diagnosticStage || 'signaling', error);
            finishCall(
                `Call failed: ${error.message || 'signaling unavailable'}.`,
                call.state === 'outgoing'
                    ? (call.inviteAttempted ? 'call.failed' : null)
                    : call.state === 'incoming'
                        ? 'call.decline'
                        : 'call.failed',
                'signaling-failure'
            );
        }

        async function configureCallPeer(call) {
            if (typeof window.RTCPeerConnection !== 'function') {
                throw new Error('WebRTC calling is not supported by this browser.');
            }
            registerVnzMediaStream(call.localStream);
            call.peerSetupStartedAt = callTimingNow();
            const connection = new window.RTCPeerConnection({iceServers});
            call.connection = connection;
            call.iceCandidates = [];
            call.iceCandidateCounts = {host: 0, srflx: 0, prflx: 0, relay: 0, other: 0};
            call.iceGatheringStartedAt = callTimingNow();
            call.remoteStream = new MediaStream();
            const iceServerTypes = iceServers.flatMap(server => {
                const urls = Array.isArray(server?.urls) ? server.urls : [server?.urls];
                return urls.map(url => {
                    const scheme = typeof url === 'string' ? url.match(/^(stuns?|turns?):/i) : null;
                    return scheme ? scheme[1].toLowerCase().replace(/s$/, '') : 'other';
                });
            });
            logVideoCallDiagnostic(call, 'peer-connection-created', null, {
                peerSetupDurationMs: Math.round(callTimingNow() - call.peerSetupStartedAt),
                iceServerTypes: [...new Set(iceServerTypes)],
                iceTransportPolicy: 'all'
            });
            connection.onicecandidate = event => {
                if (event.candidate && currentCall === call && !call.terminal) {
                    const candidateType = ['host', 'srflx', 'prflx', 'relay']
                        .includes(event.candidate.type)
                        ? event.candidate.type
                        : 'other';
                    call.iceCandidateCounts[candidateType]++;
                    logVideoCallDiagnostic(call, 'ice-candidate-gathered', null, {
                        candidateType
                    });
                    sendCallSignal(call, 'call.ice', {
                        candidate: JSON.stringify(event.candidate.toJSON())
                    }).catch(error => signalFailure(call, error));
                    return;
                }
                if (!event.candidate && currentCall === call && !call.terminal) {
                    logVideoCallDiagnostic(call, 'ice-gathering-complete', null, {
                        durationMs: Math.round(callTimingNow() - call.iceGatheringStartedAt),
                        candidateCounts: {...call.iceCandidateCounts}
                    });
                }
            };
            connection.ontrack = event => {
                if (currentCall !== call) {
                    return;
                }
                logVideoCallDiagnostic(call, 'remote-track', null, {
                    trackKind: event.track.kind,
                    trackState: event.track.readyState
                });
                const stream = event.streams?.[0];
                if (stream) {
                    call.remoteStream = stream;
                } else if (!call.remoteStream.getTracks().some(track => track.id === event.track.id)) {
                    call.remoteStream.addTrack(event.track);
                }
                const remoteMedia = call.mode === 'video' ? remoteVideo : remoteAudio;
                remoteMedia.srcObject = call.remoteStream;
                if (event.track.kind === 'video') {
                    event.track.onunmute = () => {
                        if (currentCall === call) {
                            callVideoPlaceholder.hidden = true;
                        }
                    };
                    event.track.onmute = () => {
                        if (currentCall === call) {
                            callVideoPlaceholder.hidden = false;
                        }
                    };
                    event.track.onended = () => {
                        if (currentCall === call) {
                            callVideoPlaceholder.hidden = false;
                        }
                    };
                    callVideoPlaceholder.hidden = event.track.readyState === 'live'
                        && !event.track.muted;
                }
                remoteMedia.play().catch(() => {
                    call.statusText = 'Connected. Tap the call media to start playback.';
                    callStatus.textContent = call.statusText;
                });
            };
            const updateConnectionState = () => {
                if (currentCall !== call) {
                    return;
                }
                const state = connection.connectionState;
                logVideoCallDiagnostic(call, 'connection-state-change', null, {state});
                if (state === 'connected') {
                    const toneStopStartedAt = callTimingNow();
                    stopCallTone();
                    logVideoCallDiagnostic(call, 'connected-tone-stopped', null, {
                        durationMs: Math.round(callTimingNow() - toneStopStartedAt)
                    });
                    if (callDisconnectTimer !== null) {
                        clearTimeout(callDisconnectTimer);
                        callDisconnectTimer = null;
                    }
                    if (callUnansweredTimer !== null) {
                        clearTimeout(callUnansweredTimer);
                        callUnansweredTimer = null;
                    }
                    call.state = 'active';
                    if (callDurationTimer === null) {
                        callDurationStartedAt = Date.now();
                        callDurationTimer = setInterval(() => {
                            updateCallDuration(call);
                        }, 1000);
                    }
                    call.statusText = `Connected · ${formatCallDuration(
                        Date.now() - callDurationStartedAt
                    )}`;
                    updateCallDuration(call);
                    logVideoCallDiagnostic(call, 'connected-ui-updated');
                    confirmCallConnected(call);
                    updateCallControls();
                } else if (state === 'disconnected') {
                    call.statusText = 'Reconnecting…';
                    callStatus.textContent = call.statusText;
                    if (callDisconnectTimer === null) {
                        callDisconnectTimer = setTimeout(() => {
                            if (currentCall === call && connection.connectionState !== 'connected') {
                                finishCall(
                                    'Call ended because the connection was lost.',
                                    'call.failed',
                                    'peer-disconnected-timeout'
                                );
                            }
                        }, 15000);
                    }
                } else if (state === 'failed' || state === 'closed') {
                    finishCall(
                        'Call failed because a media connection could not be established.',
                        'call.failed',
                        `peer-${state}`
                    );
                }
            };
            connection.onconnectionstatechange = updateConnectionState;
            connection.oniceconnectionstatechange = () => {
                logVideoCallDiagnostic(call, 'ice-connection-state-change', null, {
                    state: connection.iceConnectionState,
                    gatheringState: connection.iceGatheringState
                });
                updateConnectionState();
            };
            call.localStream.getTracks().forEach(track => {
                const sender = connection.addTrack(track, call.localStream);
                if (track.kind === 'video') {
                    call.videoSender = sender;
                }
            });
            if (call.mode === 'video') {
                localVideo.srcObject = call.localStream;
                callVideoPlaceholder.hidden = false;
            }
            logVideoCallDiagnostic(call, 'peer-configuration-complete', null, {
                durationMs: Math.round(callTimingNow() - call.peerSetupStartedAt)
            });
        }

        async function startCall(mode) {
            if (voiceRecorderBlocksCall()) {
                showVoiceRecorderCallBlock();
                return;
            }
            if (currentCall || activeCallChat || !callSignalReady) {
                if (activeCallChat && activeCallChat !== widget) {
                    showCallOutcome('Call unavailable', 'Finish the call in the other chat first.');
                }
                return;
            }
            if (!navigator.mediaDevices?.getUserMedia) {
                showCallOutcome('Call unavailable', 'This browser does not support microphone and camera access.');
                return;
            }
            if (typeof window.RTCPeerConnection !== 'function') {
                showCallOutcome('Call unavailable', 'WebRTC calling is not supported by this browser.');
                return;
            }
            if (!window.crypto?.randomUUID && !window.crypto?.getRandomValues) {
                showCallOutcome('Call unavailable', 'Secure call session identifiers are unavailable in this browser.');
                return;
            }

            const call = {
                id: createCallId(),
                startedAt: callTimingNow(),
                mode,
                state: 'outgoing',
                peerName: conversationPeerName,
                profilePicture: conversationPeerPicture,
                fromName: currentActorDisplayName,
                localStream: null,
                remoteStream: null,
                connection: null,
                muted: false,
                cameraEnabled: mode === 'video',
                connectedAttempts: 0,
                connectedAcknowledged: false,
                widget,
                conversationId: actualConversationId,
                statusText: `Starting ${mode === 'video' ? 'video' : 'voice'} call…`,
                minimized: false
            };
            currentCall = call;
            activeCallChat = widget;
            activeCallSessionId = call.id;
            widget.dataset.activeCallOwner = call.id;
            if (callHideTimer !== null) {
                clearTimeout(callHideTimer);
                callHideTimer = null;
            }
            callPanel.hidden = false;
            updateCallControls();
            logVideoCallDiagnostic(call, 'caller-start');
            try {
                call.diagnosticStage = 'get-user-media';
                call.mediaAcquisition = 'pending';
                const mediaStartedAt = callTimingNow();
                logVideoCallDiagnostic(call, 'caller-get-user-media-start');
                const localStream = await navigator.mediaDevices.getUserMedia({
                    audio: true,
                    video: mode === 'video'
                });
                logVideoCallDiagnostic(call, 'caller-get-user-media-complete', null, {
                    durationMs: Math.round(callTimingNow() - mediaStartedAt)
                });
                if (currentCall !== call || call.terminal || disposed) {
                    localStream.getTracks().forEach(track => track.stop());
                    call.mediaAcquisition = 'resolved-after-call-ended';
                    logVideoCallDiagnostic(call, 'late-media-stopped');
                    return;
                }
                call.localStream = localStream;
                call.mediaAcquisition = 'resolved';
                logVideoCallDiagnostic(call, 'local-media-ready', null, {
                    tracks: call.localStream.getTracks().map(track => ({
                        kind: track.kind,
                        state: track.readyState,
                        enabled: track.enabled
                    }))
                });
                call.diagnosticStage = 'peer-setup';
                await configureCallPeer(call);
                logVideoCallDiagnostic(call, 'peer-configured', null, {
                    tracks: call.localStream.getTracks().map(track => ({
                        kind: track.kind,
                        state: track.readyState,
                        enabled: track.enabled
                    }))
                });
                call.statusText = `Calling ${call.peerName}…`;
                updateCallControls();
                callUnansweredTimer = setTimeout(() => {
                    if (currentCall === call && call.state === 'outgoing') {
                        call.diagnosticStage = 'outgoing-no-answer-timeout';
                        finishCall('No answer. The call was missed.', 'call.missed', 'caller-no-answer-timeout');
                    }
                }, 35000);
                call.inviteAttempted = true;
                call.diagnosticStage = 'send-invite';
                await sendCallSignal(call, 'call.invite', {mode});
                if (currentCall === call && call.state === 'outgoing') {
                    call.statusText = 'Ringing…';
                    startCallTone('outgoing');
                    updateCallControls();
                }
            } catch (error) {
                if (call.diagnosticStage === 'get-user-media') {
                    call.mediaAcquisition = 'rejected';
                }
                if (currentCall !== call || call.terminal || disposed) {
                    logVideoCallDiagnostic(call, 'late-call-setup-failure', error);
                    return;
                }
                logVideoCallDiagnostic(call, call.diagnosticStage || 'call-setup', error);
                const detail = callMediaErrorMessage(error) || error.message || 'Call setup failed.';
                finishCall(
                    `Call failed: ${detail}`,
                    call.state === 'outgoing'
                        ? (call.inviteAttempted ? 'call.failed' : null)
                        : 'call.failed',
                    'caller-setup-failure'
                );
            }
        }

        async function acceptCall() {
            const call = currentCall;
            if (
                !call
                || (call.state !== 'incoming' && call.mediaRetryAvailable !== true)
                || call.mediaSetupInProgress === true
            ) {
                return;
            }
            const isMediaRetry = call.mediaRetryAvailable === true;
            call.mediaRetryAvailable = false;
            call.mediaSetupInProgress = true;
            logVideoCallDiagnostic(call, 'receiver-accept-click');
            if (voiceRecorderBlocksCall()) {
                call.mediaSetupInProgress = false;
                call.mediaRetryAvailable = isMediaRetry;
                showVoiceRecorderCallBlock(call);
                return;
            }
            call.mediaAcquisitionAttempts = (call.mediaAcquisitionAttempts || 0) + 1;
            const toneStopStartedAt = callTimingNow();
            stopCallTone();
            logVideoCallDiagnostic(call, 'accept-tone-stopped', null, {
                durationMs: Math.round(callTimingNow() - toneStopStartedAt)
            });
            if (call.state === 'incoming' && callUnansweredTimer !== null) {
                clearTimeout(callUnansweredTimer);
                callUnansweredTimer = null;
            }
            if (
                !navigator.mediaDevices?.getUserMedia
                || typeof window.RTCPeerConnection !== 'function'
            ) {
                finishCall('This browser does not support live calls.', 'call.decline', 'receiver-browser-unsupported');
                return;
            }
            call.state = 'connecting';
            call.statusText = 'Connecting…';
            updateCallControls();
            try {
                const startConnectTimeout = () => {
                    if (callUnansweredTimer !== null) {
                        return;
                    }
                    callUnansweredTimer = setTimeout(() => {
                        if (currentCall === call && call.state !== 'active') {
                            call.diagnosticStage = 'callee-connect-timeout';
                            finishCall('The call could not connect.', 'call.failed', 'receiver-connect-timeout');
                        }
                    }, 30000);
                };
                if (call.mode === 'video' && !call.acceptSent) {
                    call.diagnosticStage = 'send-accept';
                    await sendCallSignal(call, 'call.accept');
                    call.acceptSent = true;
                }
                startConnectTimeout();
                call.diagnosticStage = 'get-user-media';
                call.mediaAcquisition = 'pending';
                const mediaStartedAt = callTimingNow();
                logVideoCallDiagnostic(call, 'receiver-get-user-media-start', null, {
                    attempt: call.mediaAcquisitionAttempts
                });
                const localStream = await navigator.mediaDevices.getUserMedia({
                    audio: true,
                    video: call.mode === 'video'
                });
                logVideoCallDiagnostic(call, 'receiver-get-user-media-complete', null, {
                    durationMs: Math.round(callTimingNow() - mediaStartedAt)
                });
                if (currentCall !== call || call.terminal || disposed) {
                    localStream.getTracks().forEach(track => track.stop());
                    call.mediaAcquisition = 'resolved-after-call-ended';
                    logVideoCallDiagnostic(call, 'late-media-stopped');
                    return;
                }
                call.localStream = localStream;
                call.mediaAcquisition = 'resolved';
                logVideoCallDiagnostic(call, 'local-media-ready', null, {
                    tracks: call.localStream.getTracks().map(track => ({
                        kind: track.kind,
                        state: track.readyState,
                        enabled: track.enabled
                    }))
                });
                call.diagnosticStage = 'peer-setup';
                await configureCallPeer(call);
                logVideoCallDiagnostic(call, 'peer-configured', null, {
                    tracks: call.localStream.getTracks().map(track => ({
                        kind: track.kind,
                        state: track.readyState,
                        enabled: track.enabled
                    }))
                });
                if (!call.acceptSent) {
                    call.diagnosticStage = 'send-accept';
                    await sendCallSignal(call, 'call.accept');
                    call.acceptSent = true;
                }
                if (call.pendingOffer) {
                    const pendingOffer = call.pendingOffer;
                    call.pendingOffer = null;
                    await handleCallEvent(pendingOffer);
                }
                if (currentCall !== call || call.terminal || disposed) {
                    return;
                }
                call.mediaSetupInProgress = false;
                if (call.state !== 'active') {
                    call.statusText = 'Connecting…';
                }
                updateCallControls();
            } catch (error) {
                if (currentCall !== call || call.terminal || disposed) {
                    if (call.diagnosticStage === 'get-user-media') {
                        call.mediaAcquisition = 'rejected';
                    }
                    logVideoCallDiagnostic(call, 'late-call-setup-failure', error);
                    return;
                }
                if (call.diagnosticStage === 'get-user-media') {
                    call.mediaAcquisition = 'rejected';
                }
                logVideoCallDiagnostic(call, call.diagnosticStage || 'call-setup', error);
                const detail = callMediaErrorMessage(error) || error.message || 'Call setup failed.';
                if (
                    call.diagnosticStage === 'get-user-media'
                    && call.mediaAcquisitionAttempts < 2
                    && !call.connection
                    && !call.localStream
                    && call.state === 'connecting'
                ) {
                    call.mediaSetupInProgress = false;
                    call.mediaRetryAvailable = true;
                    call.statusText = `Could not start the ${call.mode} call. ${detail} Select Retry to try once more; the call will end if setup does not complete within the current connection window.`;
                    logVideoCallDiagnostic(call, 'media-retry-offered', error, {
                        attempts: call.mediaAcquisitionAttempts,
                        retriesRemaining: 1
                    });
                    updateCallControls();
                    return;
                }
                if (
                    call.diagnosticStage === 'get-user-media'
                    && call.widget?.hasAttribute('data-call-controller-only')
                ) {
                    const callKind = call.mode === 'video' ? 'video' : 'voice';
                    if (call.mediaAcquisitionAttempts >= 2) {
                        window.dispatchEvent(new CustomEvent('vnz:call-media-error', {
                            detail: {
                                message: `Could not start the ${callKind} call. ${detail}`
                            }
                        }));
                    }
                }
                call.mediaSetupInProgress = false;
                finishCall(
                    `Call failed: ${detail}`,
                    call.acceptSent ? 'call.failed' : 'call.decline',
                    'receiver-setup-failure'
                );
            }
        }

        async function flushCallIceCandidates(call) {
            while (
                currentCall === call
                && call.iceCandidates.length
                && call.connection?.remoteDescription
            ) {
                await call.connection.addIceCandidate(call.iceCandidates.shift());
            }
        }

        async function handleCallEvent(payload) {
            if (!widget.hasAttribute('data-call-controller-only')) {
                return;
            }
            if (
                !payload
                || Number(payload.conversation_id) !== actualConversationId
                || payload.to_actor_type !== currentActorType
                || Number(payload.to_actor_id) !== currentActorId
                || (
                    payload.actor_type === currentActorType
                    && Number(payload.actor_id) === currentActorId
                )
                || typeof payload.call_id !== 'string'
            ) {
                return;
            }

            if (payload.event === 'call.invite') {
                if (!['audio', 'video'].includes(payload.mode)) {
                    return;
                }
                if (
                    currentCall?.id === payload.call_id
                    || activeCallSessionId === payload.call_id
                ) {
                    return;
                }
                if (currentCall || (activeCallChat && activeCallChat !== widget)) {
                    const busyCall = {
                        id: payload.call_id,
                        mode: payload.mode,
                        peerName: payload.actor_display_name || 'Participant'
                    };
                    sendCallSignal(busyCall, 'call.busy').catch(error => {
                        setStatus(`Could not notify the caller that this chat is busy: ${error.message}`, 'warning');
                    }).finally(() => {
                        if (widget.hasAttribute('data-call-controller-only')) {
                            releaseRetainedCallOwner(widget);
                        }
                    });
                    return;
                }
                const call = {
                    id: payload.call_id,
                    startedAt: callTimingNow(),
                    mode: payload.mode,
                    state: 'incoming',
                    fromName: payload.actor_display_name || 'Participant',
                    peerName: payload.actor_display_name || 'Participant',
                    profilePicture: payload.actor_profile_picture || '',
                    localStream: null,
                    remoteStream: null,
                    connection: null,
                    iceCandidates: [],
                    muted: false,
                    cameraEnabled: payload.mode === 'video',
                    connectedAttempts: 0,
                    connectedAcknowledged: false,
                    widget,
                    conversationId: actualConversationId,
                    statusText: `Incoming ${payload.mode === 'video' ? 'video' : 'voice'} call`,
                    minimized: false
                };
                currentCall = call;
                activeCallChat = widget;
                activeCallSessionId = call.id;
                logVideoCallDiagnostic(call, 'receiver-invite-received');
                widget.dataset.activeCallOwner = call.id;
                callVideoPlaceholder.hidden = false;
                if (callHideTimer !== null) {
                    clearTimeout(callHideTimer);
                    callHideTimer = null;
                }
                startCallTone('incoming');
                callUnansweredTimer = setTimeout(() => {
                    if (currentCall === call && call.state === 'incoming') {
                        call.diagnosticStage = 'incoming-no-answer-timeout';
                        finishCall('Missed call.', 'call.missed', 'receiver-no-answer-timeout');
                    }
                }, 35000);
                updateCallControls();
                return;
            }

            const call = currentCall;
            if (!call || call.id !== payload.call_id) {
                return;
            }
            if (['call.accept', 'call.offer', 'call.answer', 'call.ice'].includes(payload.event)) {
                logVideoCallDiagnostic(call, 'signal-received', null, {event: payload.event});
            }
            if (payload.event === 'call.accept' && call.state === 'outgoing') {
                const toneStopStartedAt = callTimingNow();
                stopCallTone();
                logVideoCallDiagnostic(call, 'call-accept-tone-stopped', null, {
                    durationMs: Math.round(callTimingNow() - toneStopStartedAt)
                });
                try {
                    call.state = 'connecting';
                    call.statusText = 'Connecting…';
                    if (callUnansweredTimer !== null) {
                        clearTimeout(callUnansweredTimer);
                        callUnansweredTimer = null;
                    }
                    call.statusText = 'Connecting…';
                    callUnansweredTimer = setTimeout(() => {
                        if (currentCall === call && call.state !== 'active') {
                            call.diagnosticStage = 'outgoing-connect-timeout';
                            finishCall('The call could not connect.', 'call.failed', 'caller-connect-timeout');
                        }
                    }, 30000);
                    updateCallControls();
                    call.diagnosticStage = 'create-offer';
                    const offerStartedAt = callTimingNow();
                    const offer = await call.connection.createOffer();
                    logVideoCallDiagnostic(call, 'offer-created', null, {
                        durationMs: Math.round(callTimingNow() - offerStartedAt)
                    });
                    call.diagnosticStage = 'set-local-offer';
                    const localOfferStartedAt = callTimingNow();
                    await call.connection.setLocalDescription(offer);
                    logVideoCallDiagnostic(call, 'local-offer-set', null, {
                        durationMs: Math.round(callTimingNow() - localOfferStartedAt)
                    });
                    call.diagnosticStage = 'send-offer';
                    await sendCallSignal(call, 'call.offer', {
                        description: JSON.stringify(call.connection.localDescription)
                    });
                } catch (error) {
                    signalFailure(call, error);
                }
            } else if (payload.event === 'call.offer' && call.state === 'connecting') {
                if (!call.connection) {
                    call.pendingOffer = payload;
                    logVideoCallDiagnostic(call, 'offer-deferred-until-peer-ready', null, {
                        event: payload.event
                    });
                    return;
                }
                try {
                    call.diagnosticStage = 'set-remote-offer';
                    const remoteOfferStartedAt = callTimingNow();
                    await call.connection.setRemoteDescription(payload.description);
                    logVideoCallDiagnostic(call, 'remote-offer-set', null, {
                        durationMs: Math.round(callTimingNow() - remoteOfferStartedAt)
                    });
                    call.diagnosticStage = 'flush-ice-after-offer';
                    const flushOfferIceStartedAt = callTimingNow();
                    await flushCallIceCandidates(call);
                    logVideoCallDiagnostic(call, 'offer-ice-flush-complete', null, {
                        durationMs: Math.round(callTimingNow() - flushOfferIceStartedAt)
                    });
                    call.diagnosticStage = 'create-answer';
                    const answerStartedAt = callTimingNow();
                    const answer = await call.connection.createAnswer();
                    logVideoCallDiagnostic(call, 'answer-created', null, {
                        durationMs: Math.round(callTimingNow() - answerStartedAt)
                    });
                    call.diagnosticStage = 'set-local-answer';
                    const localAnswerStartedAt = callTimingNow();
                    await call.connection.setLocalDescription(answer);
                    logVideoCallDiagnostic(call, 'local-answer-set', null, {
                        durationMs: Math.round(callTimingNow() - localAnswerStartedAt)
                    });
                    call.diagnosticStage = 'send-answer';
                    await sendCallSignal(call, 'call.answer', {
                        description: JSON.stringify(call.connection.localDescription)
                    });
                } catch (error) {
                    signalFailure(call, error);
                }
            } else if (payload.event === 'call.answer' && call.state === 'connecting') {
                try {
                    call.diagnosticStage = 'set-remote-answer';
                    const remoteAnswerStartedAt = callTimingNow();
                    await call.connection.setRemoteDescription(payload.description);
                    logVideoCallDiagnostic(call, 'remote-answer-set', null, {
                        durationMs: Math.round(callTimingNow() - remoteAnswerStartedAt)
                    });
                    call.diagnosticStage = 'flush-ice-after-answer';
                    const flushAnswerIceStartedAt = callTimingNow();
                    await flushCallIceCandidates(call);
                    logVideoCallDiagnostic(call, 'answer-ice-flush-complete', null, {
                        durationMs: Math.round(callTimingNow() - flushAnswerIceStartedAt)
                    });
                } catch (error) {
                    signalFailure(call, error);
                }
            } else if (payload.event === 'call.ice') {
                try {
                    call.diagnosticStage = 'process-ice';
                    const candidate = new RTCIceCandidate(payload.candidate);
                    if (call.connection?.remoteDescription) {
                        await call.connection.addIceCandidate(candidate);
                    } else {
                        call.iceCandidates.push(candidate);
                    }
                } catch (error) {
                    signalFailure(call, error);
                }
            } else if (payload.event === 'call.decline') {
                finishCall('Call declined.', null, 'remote-call-decline');
            } else if (payload.event === 'call.busy') {
                finishCall('The other participant is busy.', null, 'remote-call-busy');
            } else if (payload.event === 'call.cancel') {
                finishCall('Call cancelled.', null, 'remote-call-cancel');
            } else if (payload.event === 'call.missed') {
                call.diagnosticStage = 'remote-call-missed-event';
                finishCall('No answer. The call was missed.', null, 'remote-call-missed');
            } else if (payload.event === 'call.end') {
                finishCall('The other participant ended the call.', null, 'remote-call-end');
            } else if (payload.event === 'call.failed') {
                finishCall('The other participant reported a call failure.', null, 'remote-call-failed');
            }
        }

        function endCurrentCall() {
            if (!currentCall) {
                return;
            }
            logVideoCallDiagnostic(currentCall, 'end-call-click');
            const event = currentCall.state === 'outgoing'
                ? 'call.cancel'
                : currentCall.state === 'incoming'
                    ? 'call.decline'
                    : 'call.end';
            finishCall('Call ended.', event, 'end-call-button');
        }

        function minimizeCall() {
            if (!currentCall) {
                return;
            }
            currentCall.minimized = true;
            updateCallControls();
        }

        function restoreCall() {
            if (!currentCall) {
                return;
            }
            currentCall.minimized = false;
            updateCallControls();
        }

        async function toggleCallMute() {
            if (!currentCall?.localStream) {
                return;
            }
            currentCall.muted = !currentCall.muted;
            currentCall.localStream.getAudioTracks()
                .forEach(track => {
                    track.enabled = !currentCall.muted;
                });
            updateCallControls();
        }

        async function toggleCallCamera() {
            const call = currentCall;
            if (!call || call.state !== 'active' || call.mode !== 'video') {
                return;
            }
            try {
                if (call.cameraEnabled) {
                    const videoTrack = call.localStream.getVideoTracks()[0];
                    if (call.videoSender) {
                        await call.videoSender.replaceTrack(null);
                    }
                    videoTrack?.stop();
                    if (videoTrack) {
                        call.localStream.removeTrack(videoTrack);
                    }
                    call.cameraEnabled = false;
                    localVideo.srcObject = null;
                } else {
                    const stream = await navigator.mediaDevices.getUserMedia({video: true});
                    if (currentCall !== call || disposed) {
                        stream.getTracks().forEach(track => track.stop());
                        return;
                    }
                    const videoTrack = stream.getVideoTracks()[0];
                    if (call.videoSender) {
                        await call.videoSender.replaceTrack(videoTrack);
                    } else {
                        call.videoSender = call.connection.addTrack(videoTrack, call.localStream);
                    }
                    call.localStream.addTrack(videoTrack);
                    call.cameraEnabled = true;
                    localVideo.srcObject = call.localStream;
                    localVideo.play().catch(() => {});
                }
                updateCallControls();
            } catch (error) {
                call.statusText = callMediaErrorMessage(error)
                    || error.message
                    || 'The camera could not be changed.';
                callStatus.textContent = call.statusText;
            }
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
                : (payload.actor_type === 'guest'
                    ? 'Guest Seller'
                    : payload.display_name || 'Participant');
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
        widget.addEventListener('vnz:call-signal', event => {
            handleCallEvent(event.detail).catch(error => {
                if (currentCall) {
                    signalFailure(currentCall, error);
                }
            });
        }, {signal: widgetEventController.signal});
        voiceCancel.addEventListener('click', cancelVoiceComposer, {
            signal: widgetEventController.signal
        });
        voiceSend.addEventListener('click', () => {
            if (voiceState === 'preview' && recordedVoiceFile && !sendingMessage) {
                form.requestSubmit();
            }
        }, {signal: widgetEventController.signal});
        const requestCallOrShowRegistration = mode => {
            if (isGuestCallPrompt) {
                if (guestCallDialog && !guestCallDialog.open) {
                    guestCallDialog.showModal();
                }
                return;
            }
            if (widget.hasAttribute('data-call-controller-only')) {
                startCall(mode);
            } else {
                window.dispatchEvent(new CustomEvent('vnz:call-request', {
                    detail: {
                        conversationId: actualConversationId,
                        mode
                    }
                }));
            }
        };
        audioCallButton?.addEventListener('click', () => {
            requestCallOrShowRegistration('audio');
        }, {signal: widgetEventController.signal});
        videoCallButton?.addEventListener('click', () => {
            requestCallOrShowRegistration('video');
        }, {signal: widgetEventController.signal});
        guestCallCloseButtons.forEach(button => {
            button.addEventListener('click', () => {
                if (guestCallDialog?.open) {
                    guestCallDialog.close();
                }
            }, {signal: widgetEventController.signal});
        });
        guestCallDialog?.addEventListener('click', event => {
            if (event.target === guestCallDialog) {
                guestCallDialog.close();
            }
        }, {signal: widgetEventController.signal});
        callAcceptButton?.addEventListener('click', acceptCall, {
            signal: widgetEventController.signal
        });
        callDeclineButton?.addEventListener('click', () => {
            if (currentCall?.state === 'incoming') {
                finishCall('Call declined.', 'call.decline', 'decline-button');
            }
        }, {signal: widgetEventController.signal});
        callCancelButton?.addEventListener('click', () => {
            if (currentCall?.state === 'outgoing') {
                finishCall('Call cancelled.', 'call.cancel', 'cancel-button');
            }
        }, {signal: widgetEventController.signal});
        callSoundButton?.addEventListener('click', () => {
            if (requestedCallTone) {
                resumeCallTone(callToneGeneration);
            }
        }, {signal: widgetEventController.signal});
        callMinimizeButton?.addEventListener('click', minimizeCall, {
            signal: widgetEventController.signal
        });
        callMaximizeButton?.addEventListener('click', toggleCallWindowMaximize, {
            signal: widgetEventController.signal
        });
        callCloseButton?.addEventListener('click', endCurrentCall, {
            signal: widgetEventController.signal
        });
        callIndicator?.addEventListener('click', restoreCall, {
            signal: widgetEventController.signal
        });
        callDragHandle?.addEventListener('pointerdown', event => {
            beginCallWindowPointerOperation(event, 'drag');
        }, {signal: widgetEventController.signal});
        callPanel?.addEventListener('pointerdown', event => {
            const resizeHandle = event.target.closest('[data-chat-call-resize]');
            if (resizeHandle) {
                beginCallWindowPointerOperation(
                    event,
                    'resize',
                    resizeHandle.dataset.chatCallResize
                );
            }
        }, {signal: widgetEventController.signal});
        callPanel?.addEventListener('pointermove', updateCallWindowPointerOperation, {
            signal: widgetEventController.signal
        });
        callPanel?.addEventListener('pointerup', endCallWindowPointerOperation, {
            signal: widgetEventController.signal
        });
        callPanel?.addEventListener('pointercancel', endCallWindowPointerOperation, {
            signal: widgetEventController.signal
        });
        window.addEventListener('resize', () => {
            if (callPanel.classList.contains('is-maximized')) {
                return;
            }
            if (callWindowBounds) {
                applyCallWindowBounds(callWindowBounds);
            }
        }, {signal: widgetEventController.signal});
        callMuteButton?.addEventListener('click', toggleCallMute, {
            signal: widgetEventController.signal
        });
        callCameraButton?.addEventListener('click', toggleCallCamera, {
            signal: widgetEventController.signal
        });
        callEndButton?.addEventListener('click', endCurrentCall, {
            signal: widgetEventController.signal
        });
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
            updateScrollToLatestButton();
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

        function isNearBottom() {
            return messageList.scrollHeight
                - messageList.scrollTop
                - messageList.clientHeight <= 100;
        }

        function shouldAutoScroll() {
            return isNearBottom();
        }

        function updateScrollToLatestButton() {
            awayFromBottom = !isNearBottom();
            scrollLatestButton.hidden = !messageList.isConnected
                || !isConversationViewed()
                || !awayFromBottom;
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
                    && candidate.message_type !== 'system'
                    && message.message_type !== 'system'
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
            messageList.replaceChildren(
                historyControls,
                fragment,
                emptyState,
                typingIndicator
            );
            emptyState.hidden = sorted.length > 0;
            loadOlderButton.hidden = !hasMoreMessages || oldestMessageCursor === null;

            if (preserveScroll) {
                messageList.scrollTop = oldTop + (messageList.scrollHeight - oldHeight);
            } else if (autoScroll) {
                messageList.scrollTop = messageList.scrollHeight;
            }
            updateScrollToLatestButton();

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
                const nextCursor = Number(data.next_cursor);
                const canAdvanceCursor = data.has_more === true
                    && Number.isSafeInteger(nextCursor)
                    && nextCursor > 0
                    && (beforeMessageId === null || nextCursor !== Number(beforeMessageId));
                if (beforeMessageId !== null) {
                    hasMoreMessages = canAdvanceCursor;
                    oldestMessageCursor = hasMoreMessages ? nextCursor : null;
                    olderHistoryExhausted = !hasMoreMessages;
                } else if (!oldestMessageCursor && !olderHistoryExhausted) {
                    hasMoreMessages = canAdvanceCursor;
                    oldestMessageCursor = hasMoreMessages ? nextCursor : null;
                } else if (data.has_more !== true) {
                    hasMoreMessages = false;
                    oldestMessageCursor = null;
                }
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
                loadOlderButton.hidden = !hasMoreMessages || oldestMessageCursor === null;
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
                if (
                    payload.actor_type !== currentActorType
                    && ['online', 'offline'].includes(payload.presence)
                ) {
                    setPresence(payload.presence === 'online');
                }
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
                        onCallEvent: payload => {
                            handleCallEvent(payload).catch(error => {
                                if (currentCall) {
                                    signalFailure(currentCall, error);
                                }
                            });
                        },
                        onCallState: (ready, error) => {
                            if (!callsEnabled) {
                                return;
                            }
                            callSignalReady = ready;
                            callUnavailableReason = ready
                                ? ''
                                : error
                                    ? 'Calling is unavailable. Realtime signaling could not connect.'
                                    : 'Calling is unavailable while realtime reconnects.';
                            if (widget.hasAttribute('data-call-controller-only')) {
                                widget.dataset.callControllerState = ready
                                    ? 'ready'
                                    : 'unavailable';
                                widget.dataset.callControllerError = ready
                                    ? ''
                                    : error?.message || callUnavailableReason;
                            }
                            if (!ready && currentCall) {
                                finishCall(
                                    'Call ended because realtime signaling was disconnected.',
                                    currentCall.state === 'outgoing'
                                        ? (currentCall.inviteAttempted ? 'call.failed' : null)
                                        : currentCall.state === 'incoming'
                                            ? 'call.decline'
                                            : 'call.failed',
                                    'realtime-signaling-disconnected'
                                );
                            }
                            updateCallControls();
                        },
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
                        },
                        callOnly: widget.hasAttribute('data-call-controller-only')
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
                    updateCallControls();
                }
            } catch (error) {
                realtimeReady = false;
                activeRoomName = null;
                if (widget.hasAttribute('data-call-controller-only')) {
                    widget.dataset.callControllerState = 'unavailable';
                    widget.dataset.callControllerError = error.message;
                }
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
        messageList.addEventListener('scroll', updateScrollToLatestButton, {
            passive: true,
            signal: widgetEventController.signal
        });
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
                if (!Number.isSafeInteger(messageId) || messageId <= 0) {
                    setStatus('This message could not be identified for deletion.', 'error');
                    return;
                }
                if (pendingMessageDeletes.has(messageId)) {
                    return;
                }
                pendingMessageDeletes.add(messageId);
                const confirmed = await window.VNZDialog.confirm({
                    title: 'Delete Message?',
                    message: 'Delete this message? This action cannot be undone.',
                    confirmLabel: 'Delete Message',
                    danger: true
                });
                if (!confirmed) {
                    pendingMessageDeletes.delete(messageId);
                    return;
                }
                deleteButton.disabled = true;
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
                    deleteButton.disabled = false;
                    setStatus(`Could not delete this message: ${error.message}`, 'error');
                } finally {
                    pendingMessageDeletes.delete(messageId);
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
            if (
                hasMoreMessages
                && oldestMessageCursor !== null
                && !loadingHistory
            ) {
                loadHistory(oldestMessageCursor, {preserveScroll: true});
            }
        });
        scrollLatestButton.addEventListener('click', () => {
            if (!messageList.isConnected || !isConversationViewed()) {
                return;
            }
            messageList.scrollTo({
                top: messageList.scrollHeight,
                behavior: 'smooth'
            });
        }, {signal: widgetEventController.signal});

        async function start() {
            if (!csrfToken || !currentActorType) {
                setStatus('Chat configuration is incomplete. Reload this page.', 'error');
                setConnection('Unavailable', 'error');
                widget.dataset.callControllerState = 'unavailable';
                return;
            }
            if (widget.hasAttribute('data-call-controller-only')) {
                widget.dataset.callControllerState = 'connecting';
            }

            try {
                if (conversationType === 'transaction' && !actualConversationId) {
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
                emojiToggle.disabled = widget.hasAttribute('data-call-controller-only');
                if (!widget.hasAttribute('data-call-controller-only')) {
                    await loadHistory(null, {forceScroll: true});
                }
                updateVoiceControls();
                await connectRealtime();
                if (widget.hasAttribute('data-call-controller-only')) {
                    widget.dataset.callControllerState = callSignalReady
                        ? 'ready'
                        : 'unavailable';
                    widget.dataset.callControllerError = callSignalReady
                        ? ''
                        : callUnavailableReason
                            || 'Call signaling is unavailable for this conversation.';
                    return;
                }
                if (input.value.trim()) {
                    handleComposerInput();
                }
                window.dispatchEvent(new CustomEvent('vnz:chat-state-changed', {
                    detail: {conversationId: actualConversationId}
                }));
            } catch (error) {
                widget.dataset.callControllerState = 'unavailable';
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
            if (disposed) {
                return;
            }
            if (presenceRefreshTimer !== null) {
                window.clearInterval(presenceRefreshTimer);
                presenceRefreshTimer = null;
            }
            stopTyping();
            stopCallTone();
            if (currentCall) {
                const call = currentCall;
                logVideoCallDiagnostic(call, 'widget-cleanup-start', null, {
                    terminationSource: 'widget-cleanup'
                });
                call.terminal = true;
                const signalEvent = call.state === 'incoming'
                    ? 'call.decline'
                    : call.state === 'outgoing'
                        ? 'call.cancel'
                        : 'call.end';
                sendCallSignal(call, signalEvent).catch(() => {});
                closeCallMedia(call);
                currentCall = null;
                if (activeCallChat === widget) {
                    activeCallChat = null;
                }
                if (activeCallSessionId === call.id) {
                    activeCallSessionId = null;
                }
                clearCallTimers();
            }
            callPanel?.remove();
            callIndicator?.remove();
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
            remoteAudio?.pause();
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
                if (widget.hasAttribute('data-active-call-owner')) {
                    ensureCallOwnerHost().append(widget);
                } else {
                    cleanup();
                }
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

    window.VNZTransactionChat = window.VNZTransactionChat || {};
    window.VNZTransactionChat.scan = scan;
    window.VNZTransactionChat.acquireCallInbox = (channelId, csrfToken, callbacks) => (
        sharedRealtime.acquireCallInbox(channelId, csrfToken, callbacks)
    );

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', startObserver, {once: true});
    } else {
        startObserver();
    }
})();
