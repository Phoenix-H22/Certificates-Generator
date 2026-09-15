<?php

namespace App\Enums;

enum FieldType: string
{
    case Text = 'text';
    case Date = 'date';
    case Email = 'email';
    case Phone = 'phone';
    case Number = 'number';

    public function label(): string
    {
        return match ($this) {
            self::Text => 'نص',
            self::Date => 'تاريخ',
            self::Email => 'بريد إلكتروني',
            self::Phone => 'هاتف',
            self::Number => 'رقم',
        };
    }

    /** @return list<string> Laravel validation rules for a value of this type. */
    public function rules(): array
    {
        return match ($this) {
            self::Text => ['string'],
            self::Date => ['date'],
            self::Email => ['email'],
            self::Phone => ['string'],
            self::Number => ['numeric'],
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
