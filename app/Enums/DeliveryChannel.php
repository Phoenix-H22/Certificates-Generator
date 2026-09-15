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

    /** @return array<string, string> */
    public static function options(): array
    {
        return array_combine(
            array_map(fn (self $c) => $c->value, self::cases()),
            array_map(fn (self $c) => $c->label(), self::cases()),
        );
    }
}
