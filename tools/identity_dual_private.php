<?php
/** Shared privileged CLI setup primitives. No standalone action or secret output. */
declare(strict_types=1);
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }

function identityDualSetupConfigPath(): string
{
    $expected = 'C:/xampp/private/VNZ-TECH/identity/config.php';
    $actual = realpath($expected);
    if (!$actual || is_link($expected) || strcasecmp(str_replace('\\', '/', $actual), $expected) !== 0) {
        throw new RuntimeException('private_path');
    }
    return $actual;
}

function identityDualSetupWrite(string $path, string $contents): void
{
    $temporary = dirname($path) . '/.' . basename($path) . '-dual-' . bin2hex(random_bytes(12)) . '.tmp';
    $stream = @fopen($temporary, 'xb');
    if (!$stream) { throw new RuntimeException('private_write'); }
    try {
        if (fwrite($stream, $contents) !== strlen($contents) || !fflush($stream)) { throw new RuntimeException('private_write'); }
        if (function_exists('fsync') && !fsync($stream)) { throw new RuntimeException('private_sync'); }
        fclose($stream);
        $stream = null;
        if (!@rename($temporary, $path)) { throw new RuntimeException('private_replace'); }
    } finally {
        if (is_resource($stream)) { fclose($stream); }
        if (is_file($temporary)) { @unlink($temporary); }
    }
}
