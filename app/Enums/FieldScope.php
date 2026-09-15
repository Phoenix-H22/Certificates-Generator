<?php

namespace App\Enums;

use Filament\Support\Contracts\HasLabel;

enum FieldScope: string implements HasLabel
{
    /** One value for the whole batch (e.g. event name). */
    case Fixed = 'fixed';

    /** A value per Excel row (e.g. participant's department). */
    case Row = 'row';

    public function label(): string
    {
        return match ($this) {
            self::Fixed => 'ثابت لكل الدفعة',
            self::Row => 'لكل صف في الملف',
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
            array_map(fn (self $s) => $s->value, self::cases()),
            array_map(fn (self $s) => $s->label(), self::cases()),
        );
    }
}
