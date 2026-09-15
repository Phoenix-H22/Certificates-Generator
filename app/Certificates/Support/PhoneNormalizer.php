<?php

namespace App\Certificates\Support;

use Propaganistas\LaravelPhone\PhoneNumber;
use Throwable;

/**
 * Turn whatever Excel hands us into an E.164 number, or null.
 *
 * Egyptian mobiles are the common case and arrive in every shape:
 * 01012345678, 1012345678, 201012345678, +20 10 1234 5678, 1.01234567E9 ...
 */
final class PhoneNormalizer
{
    public const DEFAULT_COUNTRY = 'EG';

    public static function normalize(mixed $raw, string $country = self::DEFAULT_COUNTRY): ?string
    {
        if ($raw === null) {
            return null;
        }

        // Excel may deliver numbers as floats (1.01234567E9).
        if (is_float($raw) || is_int($raw)) {
            $raw = number_format((float) $raw, 0, '', '');
        }

        $raw = trim((string) $raw);

        if ($raw === '') {
            return null;
        }

        $digits = preg_replace('/[^\d+]/', '', $raw) ?? '';

        if ($digits === '' || $digits === '+') {
            return null;
        }

        if ($country === 'EG') {
            $bare = ltrim($digits, '+');

            // 01xxxxxxxxx → +201xxxxxxxxx
            if (preg_match('/^01[0125]\d{8}$/', $bare)) {
                return '+20'.substr($bare, 1);
            }

            // 1xxxxxxxxx → +201xxxxxxxxx
            if (preg_match('/^1[0125]\d{8}$/', $bare)) {
                return '+20'.$bare;
            }

            // 201xxxxxxxxx (country code without +) → +201xxxxxxxxx
            if (preg_match('/^201[0125]\d{8}$/', $bare)) {
                return '+'.$bare;
            }

            // 00201xxxxxxxxx (international prefix) → +201xxxxxxxxx
            if (preg_match('/^00201[0125]\d{8}$/', $bare)) {
                return '+'.substr($bare, 2);
            }
        }

        // Anything else: let libphonenumber decide.
        try {
            $phone = new PhoneNumber($digits, $country);

            return $phone->isValid() ? $phone->formatE164() : null;
        } catch (Throwable) {
            return null;
        }
    }
}
