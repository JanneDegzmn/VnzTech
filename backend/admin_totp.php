<?php

function generateAdminTotpSecret(): string
{
    $alphabet = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ234567';
    $bits = '';

    foreach (str_split(random_bytes(20)) as $byte) {
        $bits .= str_pad(decbin(ord($byte)), 8, '0', STR_PAD_LEFT);
    }

    $secret = '';

    foreach (str_split($bits, 5) as $chunk) {
        $secret .= $alphabet[bindec(str_pad($chunk, 5, '0'))];
    }

    return $secret;
}

function decodeAdminTotpSecret(string $secret): string
{
    $alphabet = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ234567';
    $normalized = strtoupper(rtrim($secret, '='));
    $bits = '';

    foreach (str_split($normalized) as $character) {
        $value = strpos($alphabet, $character);

        if ($value === false) {
            throw new InvalidArgumentException('Invalid authenticator secret.');
        }

        $bits .= str_pad(decbin($value), 5, '0', STR_PAD_LEFT);
    }

    $bytes = '';

    foreach (str_split(substr($bits, 0, intdiv(strlen($bits), 8) * 8), 8) as $byte) {
        $bytes .= chr(bindec($byte));
    }

    return $bytes;
}

function matchAdminTotpCode(string $secret, string $code, ?int $timestamp = null): ?int
{
    if (!preg_match('/^\d{6}$/', $code)) {
        return null;
    }

    $key = decodeAdminTotpSecret($secret);
    $timestamp = $timestamp ?? time();
    $currentCounter = intdiv($timestamp, 30);

    for ($offset = -1; $offset <= 1; $offset++) {
        $counter = $currentCounter + $offset;

        if ($counter < 0) {
            continue;
        }

        $high = intdiv($counter, 4294967296);
        $low = $counter % 4294967296;
        $message = pack('N2', $high, $low);
        $hash = hash_hmac('sha1', $message, $key, true);
        $offsetIndex = ord($hash[19]) & 0x0f;
        $binary = (
            ((ord($hash[$offsetIndex]) & 0x7f) << 24)
            | ((ord($hash[$offsetIndex + 1]) & 0xff) << 16)
            | ((ord($hash[$offsetIndex + 2]) & 0xff) << 8)
            | (ord($hash[$offsetIndex + 3]) & 0xff)
        );
        $expected = str_pad((string) ($binary % 1000000), 6, '0', STR_PAD_LEFT);

        if (hash_equals($expected, $code)) {
            return $counter;
        }
    }

    return null;
}

function buildAdminTotpUri(string $secret, string $accountName): string
{
    $label = rawurlencode('VNZ TECH:' . $accountName);
    $query = http_build_query([
        'secret' => $secret,
        'issuer' => 'VNZ TECH',
        'algorithm' => 'SHA1',
        'digits' => 6,
        'period' => 30,
    ], '', '&', PHP_QUERY_RFC3986);

    return 'otpauth://totp/' . $label . '?' . $query;
}

function generateAdminTotpRecoveryCodes(int $count = 8): array
{
    $codes = [];

    for ($index = 0; $index < $count; $index++) {
        $random = strtoupper(bin2hex(random_bytes(8)));
        $codes[] = implode('-', str_split($random, 4));
    }

    return $codes;
}

function normalizeAdminTotpRecoveryCode(string $code): string
{
    return strtoupper(preg_replace('/[^A-F0-9]/i', '', $code));
}
