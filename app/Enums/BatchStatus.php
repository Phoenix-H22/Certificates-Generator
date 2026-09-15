<?php

namespace App\Enums;

enum BatchStatus: string
{
    case Draft = 'draft';
    case Queued = 'queued';
    case Parsing = 'parsing';
    case Rendering = 'rendering';
    case Delivering = 'delivering';
    case Completed = 'completed';
    case CompletedWithErrors = 'completed_with_errors';
    case Failed = 'failed';
    case Cancelled = 'cancelled';

    public function label(): string
    {
        return match ($this) {
            self::Draft => 'مسودة',
            self::Queued => 'في الانتظار',
            self::Parsing => 'جاري قراءة الملف',
            self::Rendering => 'جاري التوليد',
            self::Delivering => 'جاري الإرسال',
            self::Completed => 'مكتملة',
            self::CompletedWithErrors => 'مكتملة مع أخطاء',
            self::Failed => 'فشلت',
            self::Cancelled => 'ملغاة',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::Draft => 'gray',
            self::Queued, self::Parsing, self::Rendering, self::Delivering => 'info',
            self::Completed => 'success',
            self::CompletedWithErrors => 'warning',
            self::Failed, self::Cancelled => 'danger',
        };
    }

    public function isTerminal(): bool
    {
        return in_array($this, [self::Completed, self::CompletedWithErrors, self::Failed, self::Cancelled], true);
    }

    public function isActive(): bool
    {
        return in_array($this, [self::Queued, self::Parsing, self::Rendering, self::Delivering], true);
    }

    /** @return list<self> */
    public static function active(): array
    {
        return array_values(array_filter(self::cases(), fn (self $s) => $s->isActive()));
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
