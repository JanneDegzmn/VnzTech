<?php
declare(strict_types=1);

require_once __DIR__ . '/../backend/social_auth_service.php';

function runSocialAuthConfigValidation(): int
{
    $configPath = __DIR__ . '/../backend/social_auth_config.local.php';
    if (!is_file($configPath) || !is_readable($configPath)) {
        fwrite(STDERR, "Social login config validation BLOCKED: config_missing_or_unreadable\n");
        return 1;
    }

    ob_start();
    try {
        $config = require $configPath;
    } catch (Throwable) {
        $config = null;
    } finally {
        ob_end_clean();
    }
    if (!is_array($config)) {
        fwrite(STDERR, "Social login config validation BLOCKED: config_invalid\n");
        return 1;
    }

    $errors = vnzSocialAuthConfigErrors($config);
    if ($errors !== []) {
        fwrite(STDERR, 'Social login config validation BLOCKED: ' . implode(',', $errors) . PHP_EOL);
        return 1;
    }

    fwrite(STDOUT, "Social login config PASS; localhost callback pinned; no database connection made.\n");
    return 0;
}

if (
    isset($_SERVER['SCRIPT_FILENAME'])
    && realpath((string) $_SERVER['SCRIPT_FILENAME']) === realpath(__FILE__)
) {
    if (PHP_SAPI !== 'cli') {
        http_response_code(404);
        exit;
    }
    exit(runSocialAuthConfigValidation());
}
