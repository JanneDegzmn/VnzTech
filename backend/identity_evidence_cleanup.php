<?php
if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}
require_once __DIR__ . '/database.php';
require_once __DIR__ . '/identity_verification.php';
try {
    echo json_encode(vnzIdentityCleanup($pdo), JSON_UNESCAPED_SLASHES) . PHP_EOL;
} catch (Throwable $error) {
    fwrite(STDERR, "Identity cleanup failed. Inspect private configuration, storage and migration.\n");
    exit(1);
}
