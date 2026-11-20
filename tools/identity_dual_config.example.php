<?php
/** NONSECRET example only. Merge dual fields into the existing PRIVATE config. */
return [
    // Preserve existing enabled/storage_path/key_path and cleanup configuration.
    // manual_v2 stays current until provider readiness and activation checks pass.
    'mode' => 'manual_v2',
    'dual' => [
        'node_binary' => 'C:/Program Files/nodejs/node.exe',
        'region' => 'us', // Select the region of the actual Face++ account/key.
        'api_key' => '', // Use configure_identity_dual.ps1; never put real keys here.
        'api_secret' => '',
        'contract_confirmed' => false,
        'matching_validated' => false,
        'validation_policy' => '',
        'validation_recorded_at' => null,
        'validation_pair_counts' => null,
    ],
];
