<?php

namespace App\Certificates\Support;

/**
 * {placeholder} substitution used by certificate texts, e-mails and
 * WhatsApp messages. Unknown placeholders are left in place so admins can
 * see a typo rather than silently losing text.
 */
final class Placeholders
{
    /** @param array<string, mixed> $values */
    public static function render(string $text, array $values): string
    {
        return preg_replace_callback(
            '/\{([a-z][a-z0-9_]*)\}/i',
            function (array $m) use ($values): string {
                $key = strtolower($m[1]);

                if (! array_key_exists($key, $values) || $values[$key] === null) {
                    return $m[0];
                }

                return (string) $values[$key];
            },
            $text,
        ) ?? $text;
    }

    /** @return list<string> placeholder names found in the text */
    public static function extract(string $text): array
    {
        preg_match_all('/\{([a-z][a-z0-9_]*)\}/i', $text, $m);

        return array_values(array_unique(array_map('strtolower', $m[1])));
    }
}
