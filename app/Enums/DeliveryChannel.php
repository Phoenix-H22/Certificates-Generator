<?php

namespace App\Enums;

use Filament\Support\Contracts\HasLabel;

enum DeliveryChannel: string implements HasLabel
{
    case Email = 'email';
    case WhatsApp = 'whatsapp';

    public function label(): string
    {
        return match ($this) {
            self::Email => 'البريد الإلكتروني',
            self::WhatsApp => 'واتساب',
        };
    }

    public function getLabel(): string
    {
        return $this->label();
    }

    /**
     * Normalise a list that may mix enum instances and strings (Filament
     * form state) into unique, valid channel values.
     *
     * @param  iterable<mixed>  $channels
     * @return list<string>
     */
    public static function normalize(iterable $channels): array
    {
        $out = [];

        foreach ($channels as $channel) {
            $case = $channel instanceof self ? $channel : self::tryFrom(strtolower(trim((string) $channel)));

            if ($case !== null && ! in_array($case->value, $out, true)) {
                $out[] = $case->value;
            }
        }

        return $out;
    }

    /** @return array<string, string> */
    public static function options(): array
    {
        return array_combine(
            array_map(fn (self $c) => $c->value, self::cases()),
            array_map(fn (self $c) => $c->label(), self::cases()),
        );
    }
}
