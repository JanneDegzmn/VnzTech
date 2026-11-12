<?php
declare(strict_types=1);

function vnzPasswordStrength(string $password): string
{
    $characterCount = preg_match_all('/[\s\S]/u', $password);
    if ($characterCount === false) {
        return 'weak';
    }

    $hasLowercase = preg_match('/[a-z]/', $password) === 1;
    $hasUppercase = preg_match('/[A-Z]/', $password) === 1;
    $hasNumber = preg_match('/[0-9]/', $password) === 1;
    $hasSymbol = preg_match('/[^A-Za-z0-9]/', $password) === 1;
    $classCount = (int) $hasLowercase
        + (int) $hasUppercase
        + (int) $hasNumber
        + (int) $hasSymbol;

    if ($characterCount >= 16 && $classCount === 4) {
        return 'strong';
    }
    if ($characterCount >= 8 && $classCount >= 3) {
        return 'medium';
    }

    return 'weak';
}
