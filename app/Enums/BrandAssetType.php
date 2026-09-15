<?php

namespace App\Enums;

enum BrandAssetType: string
{
    case Logo = 'logo';
    case Signature = 'signature';
    case Stamp = 'stamp';
    case Background = 'background';

    public function label(): string
    {
        return match ($this) {
            self::Logo => 'شعار',
            self::Signature => 'توقيع',
            self::Stamp => 'ختم',
            self::Background => 'خلفية',
        };
    }

    /** @return array<string, string> */
    public static function options(): array
    {
        return array_combine(
            array_map(fn (self $t) => $t->value, self::cases()),
            array_map(fn (self $t) => $t->label(), self::cases()),
        );
    }
}
