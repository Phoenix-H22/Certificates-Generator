<?php

namespace App\Certificates\Support;

use Illuminate\Support\Facades\DB;

/**
 * Short, human-friendly verification codes printed on certificates.
 *
 * The alphabet excludes 0/O and 1/I/L so codes survive being read aloud
 * or typed from a printout. Format: XXXXX-XXXXX (10 symbols, ~50 bits).
 */
final class VerificationCode
{
    public const ALPHABET = 'ABCDEFGHJKMNPQRSTUVWXYZ23456789';

    public const LENGTH = 10;

    /** Generate a formatted code, retrying on (unlikely) collisions. */
    public static function generate(string $table = 'certificates', string $column = 'code'): string
    {
        for ($attempt = 0; $attempt < 10; $attempt++) {
            $code = self::format(self::random());

            if (! DB::table($table)->where($column, $code)->exists()) {
                return $code;
            }
        }

        throw new \RuntimeException('Unable to generate a unique verification code.');
    }

    /** Raw random symbols without formatting. */
    public static function random(): string
    {
        $max = strlen(self::ALPHABET) - 1;
        $out = '';

        for ($i = 0; $i < self::LENGTH; $i++) {
            $out .= self::ALPHABET[random_int(0, $max)];
        }

        return $out;
    }

    /** Insert the dash: ABCDEFGHJK → ABCDE-FGHJK */
    public static function format(string $raw): string
    {
        $raw = strtoupper($raw);

        return substr($raw, 0, 5).'-'.substr($raw, 5, 5);
    }

    /** Strip dashes/spaces and upper-case so lookups tolerate sloppy input. */
    public static function normalize(string $input): string
    {
        return strtoupper(preg_replace('/[^A-Za-z0-9]/', '', $input) ?? '');
    }

    /** Is this input shaped like a code we could have issued? */
    public static function looksLikeCode(string $input): bool
    {
        $clean = self::normalize($input);

        return strlen($clean) === self::LENGTH
            && preg_match('/^['.self::ALPHABET.']+$/', $clean) === 1;
    }

    /** Normalised then formatted, or null when the input cannot be a code. */
    public static function canonical(string $input): ?string
    {
        return self::looksLikeCode($input) ? self::format(self::normalize($input)) : null;
    }
}
