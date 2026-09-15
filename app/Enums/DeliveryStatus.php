<?php

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

enum DeliveryStatus: string implements HasColor, HasLabel
{
    case NotRequested = 'not_requested';
    case Pending = 'pending';
    case Sent = 'sent';
    case Failed = 'failed';
    case Skipped = 'skipped';

    public function label(): string
    {
        return match ($this) {
            self::NotRequested => 'غير مطلوب',
            self::Pending => 'قيد الإرسال',
            self::Sent => 'تم الإرسال',
            self::Failed => 'فشل الإرسال',
            self::Skipped => 'تم التخطي',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::NotRequested => 'gray',
            self::Pending => 'info',
            self::Sent => 'success',
            self::Failed => 'danger',
            self::Skipped => 'warning',
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
}
