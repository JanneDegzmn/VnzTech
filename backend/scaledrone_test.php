<?php

ob_start();
ini_set('display_errors', '0');
header('Cache-Control: no-store, private');

require_once __DIR__ . '/chat_scaledrone.php';

try {
    $actor = chatGetCurrentActor();

    global $pdo;

    $conversationStmt = $pdo->prepare("
        SELECT id
        FROM conversations
        WHERE type = 'transaction'
          AND transaction_id = ?
        LIMIT 1
    ");
    $conversationStmt->execute([1]);
    $conversationId = $conversationStmt->fetchColumn();

    if (!$conversationId) {
        throw new ChatHttpException(
            'Transaction 1 conversation was not found.',
            404
        );
    }

    $conversationId = (int) $conversationId;
    chatRequireConversationMembership($conversationId, $actor);

    $config = chatScaledroneConfig();
    if ($config === null) {
        throw new ChatHttpException(
            'Realtime messaging is not configured.',
            503
        );
    }

    $csrfToken = generateCsrfToken();
} catch (ChatHttpException $e) {
    http_response_code($e->httpStatus);
    header('Content-Type: text/plain; charset=utf-8');
    exit($e->getMessage());
} catch (Throwable $e) {
    error_log('Temporary Scaledrone test page could not be initialized.');
    http_response_code(500);
    header('Content-Type: text/plain; charset=utf-8');
    exit('Unable to initialize the realtime test page.');
}

$clientConfig = json_encode(
    [
        'channelId' => $config['channel_id'],
        'conversationId' => $conversationId
    ],
    JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT
);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Temporary Scaledrone Test | VNZ TECH</title>
    <style>
        body {
            font: 16px/1.5 system-ui, sans-serif;
            margin: 2rem auto;
            max-width: 48rem;
            padding: 0 1rem;
        }
        button, input {
            font: inherit;
            padding: 0.5rem;
        }
        input {
            box-sizing: border-box;
            width: min(100%, 36rem);
        }
        #status, #events, #send-result {
            overflow-wrap: anywhere;
        }
        #events {
            max-height: 16rem;
            overflow: auto;
        }
    </style>
</head>
<body>
    <h1>Temporary Transaction 1 Realtime Test</h1>
    <p>
        Authenticated as
        <?= htmlspecialchars($actor['actor_type'], ENT_QUOTES, 'UTF-8') ?>
        #<?= (int) $actor['actor_id'] ?>.
        This page uses the existing PHP authorization and message endpoints.
        Messages are created only when you submit the form below.
    </p>

    <input
        type="hidden"
        id="csrf-token"
        value="<?= htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8') ?>"
    >
    <button type="button" id="connect">Connect and subscribe</button>
    <p id="status" role="status">Not connected.</p>

    <form id="send-form">
        <label for="content">Test message</label><br>
        <input
            id="content"
            name="content"
            maxlength="4000"
            required
            autocomplete="off"
        >
        <button type="submit" id="send" disabled>Send through PHP</button>
    </form>
    <p id="send-result" role="status"></p>

    <h2>Realtime events</h2>
    <p>Only event name and IDs are displayed; message content is not displayed.</p>
    <ol id="events"></ol>

    <script src="https://cdn.scaledrone.com/scaledrone.min.js"></script>
    <script>
        (() => {
            const config = <?= $clientConfig ?>;
            const status = document.getElementById('status');
            const sendResult = document.getElementById('send-result');
            const sendButton = document.getElementById('send');
            const sendForm = document.getElementById('send-form');
            const eventList = document.getElementById('events');
            const csrfToken = document.getElementById('csrf-token').value;
            let drone = null;
            let connected = false;
            let sendOutcomeUnknown = false;

            function setStatus(value) {
                status.textContent = value;
            }

            async function postForm(url, fields) {
                const response = await fetch(url, {
                    method: 'POST',
                    credentials: 'same-origin',
                    headers: {
                        'Content-Type': 'application/x-www-form-urlencoded'
                    },
                    body: new URLSearchParams(fields)
                });

                let payload;
                try {
                    payload = await response.json();
                } catch (error) {
                    throw new Error('Endpoint returned an invalid response.');
                }

                return {response, payload};
            }

            document.getElementById('connect').addEventListener('click', () => {
                if (connected) {
                    setStatus('Already subscribed to the authorized conversation.');
                    return;
                }

                if (typeof window.Scaledrone !== 'function') {
                    setStatus('Scaledrone client library did not load.');
                    return;
                }

                setStatus('Opening Scaledrone connection...');
                drone = new window.Scaledrone(config.channelId);

                drone.on('error', error => {
                    const message = error && typeof error.message === 'string'
                        ? error.message
                        : 'Connection error.';
                    setStatus('Scaledrone error: ' + message);
                });

                drone.on('open', async error => {
                    if (error) {
                        setStatus('Scaledrone connection failed: ' + error.message);
                        return;
                    }

                    setStatus('Connected. Requesting conversation authorization...');

                    try {
                        const {response, payload} = await postForm(
                            '/VNZ-TECH/backend/chat_scaledrone_auth.php',
                            {
                                conversation_id: String(config.conversationId),
                                client_id: drone.clientId,
                                csrf_token: csrfToken
                            }
                        );

                        if (!response.ok || payload.success !== true) {
                            setStatus(
                                'Authorization failed (HTTP '
                                + response.status
                                + '): '
                                + (payload.message || 'Request rejected.')
                            );
                            return;
                        }

                        const authorization = payload.data;

                        drone.on('authenticate', authError => {
                            if (authError) {
                                setStatus('Scaledrone authentication failed.');
                                return;
                            }

                            const room = drone.subscribe(authorization.room);
                            room.on('open', roomError => {
                                if (roomError) {
                                    setStatus('Authorized room subscription failed.');
                                    return;
                                }

                                connected = true;
                                sendButton.disabled = false;
                                setStatus('Subscribed to the authorized transaction conversation.');
                            });

                            room.on('message', message => {
                                const event = message && message.data;
                                if (
                                    !event
                                    || event.event !== 'message.created'
                                    || Number(event.conversation_id) !== config.conversationId
                                ) {
                                    return;
                                }

                                const item = document.createElement('li');
                                item.textContent = 'message.created — conversation '
                                    + event.conversation_id
                                    + ', message '
                                    + event.message_id;
                                eventList.prepend(item);
                            });
                        });

                        drone.authenticate(authorization.token);
                        setStatus('Authenticating Scaledrone client...');
                    } catch (requestError) {
                        setStatus('Authorization request failed.');
                    }
                });
            });

            sendForm.addEventListener('submit', async event => {
                event.preventDefault();
                if (!connected || sendButton.disabled) {
                    return;
                }

                sendButton.disabled = true;
                sendResult.textContent = 'Sending once through the PHP endpoint...';

                try {
                    const {response, payload} = await postForm(
                        '/VNZ-TECH/backend/chat_messages.php',
                        {
                            action: 'send',
                            conversation_id: String(config.conversationId),
                            content: document.getElementById('content').value,
                            message_type: 'text',
                            csrf_token: csrfToken
                        }
                    );

                    if (!response.ok || payload.success !== true) {
                        sendResult.textContent = 'Send rejected (HTTP '
                            + response.status
                            + '): '
                            + (payload.message || 'Request rejected.');
                        return;
                    }

                    const data = payload.data;
                    sendResult.textContent = 'Saved: '
                        + String(data.persisted === true)
                        + '; realtime delivery: '
                        + String(data.realtime_delivery)
                        + '; message ID: '
                        + String(data.message.id)
                        + '; sender: '
                        + String(data.message.sender_type)
                        + ' '
                        + String(data.message.sender_id)
                        + '. Do not retry; inspect the database if delivery is uncertain.';
                    sendForm.reset();
                } catch (requestError) {
                    sendOutcomeUnknown = true;
                    sendResult.textContent = 'Send outcome is uncertain. Do not retry; inspect the database first.';
                } finally {
                    sendButton.disabled = sendOutcomeUnknown;
                }
            });
        })();
    </script>
</body>
</html>
