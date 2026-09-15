<?php

namespace App\Enums;

enum DeliveryChannel: string
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

    /** @return array<string, string> */
    public static function options(): array
    {
        return array_combine(
            array_map(fn (self $c) => $c->value, self::cases()),
            array_map(fn (self $c) => $c->label(), self::cases()),
        );
    }
}
