<?php

namespace App\Certificates\Support;

use Carbon\CarbonInterface;
use Illuminate\Support\Carbon;
use Throwable;

/** Small helpers for Arabic-facing output. */
final class ArabicText
{
    private const ARABIC_INDIC = ['٠', '١', '٢', '٣', '٤', '٥', '٦', '٧', '٨', '٩'];

    /** 2024 → ٢٠٢٤ */
    public static function digits(string $text): string
    {
        return strtr($text, array_combine(range(0, 9), self::ARABIC_INDIC));
    }

    /** ٢٠٢٤ → 2024 */
    public static function latinDigits(string $text): string
    {
        return strtr($text, array_combine(self::ARABIC_INDIC, range(0, 9)));
    }

    /**
     * "2024-04-28" → "٢٨ أبريل ٢٠٢٤" (or with Latin digits when requested).
     * Non-date input is returned untouched so free-text dates still print.
     */
    public static function date(mixed $value, bool $arabicDigits = true, string $format = 'j F Y'): string
    {
        if ($value === null || $value === '') {
            return '';
        }

        if ($value instanceof CarbonInterface) {
            $date = $value;
        } else {
            try {
                $date = Carbon::parse(self::latinDigits((string) $value));
            } catch (Throwable) {
                return (string) $value;
            }
        }

        $formatted = $date->locale('ar')->translatedFormat($format);

        return $arabicDigits ? self::digits($formatted) : $formatted;
    }

    /** Normalise a spreadsheet cell to Y-m-d when it is a date, else null. */
    public static function toIsoDate(mixed $value): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }

        if ($value instanceof CarbonInterface) {
            return $value->toDateString();
        }

        if ($value instanceof \DateTimeInterface) {
            return Carbon::instance($value)->toDateString();
        }

        try {
            return Carbon::parse(self::latinDigits(trim((string) $value)))->toDateString();
        } catch (Throwable) {
            return null;
        }
    }
}
