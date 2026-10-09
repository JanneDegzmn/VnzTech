<?php

/*
 * Copy this file to scaledrone_config.local.php and replace both values.
 * Keep the secret server-side; never include this file from public assets.
 */
return [
    'channel_id' => 'your-scaledrone-channel-id',
    'secret_key' => 'your-scaledrone-secret-key',
    // Add your TURN server here for networks that block direct peer connectivity.
    // WEBRTC_ICE_SERVERS may instead provide the ICE server list as JSON.
    'ice_servers' => [
        ['urls' => 'stun:stun.l.google.com:19302']
    ]
];
