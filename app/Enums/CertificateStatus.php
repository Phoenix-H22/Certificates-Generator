<?php

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

enum CertificateStatus: string implements HasColor, HasLabel
{
    case Pending = 'pending';
    case Rendered = 'rendered';
    case RenderFailed = 'render_failed';
    case Revoked = 'revoked';

    public function label(): string
    {
        return match ($this) {
            self::Pending => 'قيد الانتظار',
            self::Rendered => 'تم التوليد',
            self::RenderFailed => 'فشل التوليد',
            self::Revoked => 'ملغاة',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::Pending => 'gray',
            self::Rendered => 'success',
            self::RenderFailed => 'danger',
            self::Revoked => 'warning',
        };
    }

    public function getLabel(): string
    {
        return $this->label();
    }

    public function getColor(): string
    {
        return $this->color();
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
