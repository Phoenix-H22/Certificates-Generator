<?php

namespace App\Enums;

enum CertificateStatus: string
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

    /** @return array<string, string> */
    public static function options(): array
    {
        return array_combine(
            array_map(fn (self $s) => $s->value, self::cases()),
            array_map(fn (self $s) => $s->label(), self::cases()),
        );
    }
}
