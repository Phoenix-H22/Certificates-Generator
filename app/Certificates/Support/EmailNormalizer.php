<?php

namespace App\Certificates\Support;

/**
 * Clean e-mail addresses copied out of spreadsheets: invisible RTL/LTR
 * marks, zero-width characters, stray spaces and inconsistent case.
 */
final class EmailNormalizer
{
    public static function normalize(mixed $raw): ?string
    {
        if ($raw === null) {
            return null;
        }

        $email = (string) $raw;

        // Strip zero-width and bidi control characters that Excel/Word leave behind.
        $email = preg_replace('/[\x{200B}-\x{200F}\x{202A}-\x{202E}\x{2066}-\x{2069}\x{FEFF}\x{061C}]/u', '', $email) ?? $email;

        // Remove every kind of whitespace, including inside the address.
        $email = preg_replace('/\s+/u', '', $email) ?? $email;

        $email = mb_strtolower(trim($email));

        return $email === '' ? null : $email;
    }

    public static function isValid(?string $email): bool
    {
        return $email !== null && filter_var($email, FILTER_VALIDATE_EMAIL) !== false;
    }
}
