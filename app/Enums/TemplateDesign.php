<?php

namespace App\Enums;

use Filament\Support\Contracts\HasDescription;
use Filament\Support\Contracts\HasLabel;

enum TemplateDesign: string implements HasDescription, HasLabel
{
    case ClassicGold = 'classic_gold';
    case ModernMinimal = 'modern_minimal';
    case FormalBlue = 'formal_blue';
    case ElegantDark = 'elegant_dark';

    public function label(): string
    {
        return match ($this) {
            self::ClassicGold => 'الكلاسيكي الذهبي',
            self::ModernMinimal => 'الحديث البسيط',
            self::FormalBlue => 'الرسمي المؤسسي',
            self::ElegantDark => 'الأنيق الداكن',
        };
    }

    public function description(): string
    {
        return match ($this) {
            self::ClassicGold => 'ورق كريمي وإطار ذهبي مزدوج بزخارف تقليدية',
            self::ModernMinimal => 'خلفية بيضاء وشريط لون جانبي مع فراغات واسعة',
            self::FormalBlue => 'شريط كحلي علوي يحمل الشعارات واسم الجهة',
            self::ElegantDark => 'خلفية داكنة ونصوص ذهبية بإطار رفيع',
        };
    }

    public function getLabel(): string
    {
        return $this->label();
    }

    public function getDescription(): string
    {
        return $this->description();
    }

    /** Blade view name that renders this design. */
    public function view(): string
    {
        return 'certificates.designs.'.str_replace('_', '-', $this->value);
    }

    /** Static sample image used by the design picker. */
    public function sampleImage(): string
    {
        return asset('images/designs/'.$this->value.'.png');
    }

    /** Default accent colour used when the template has none configured. */
    public function defaultAccent(): string
    {
        return match ($this) {
            self::ClassicGold => '#C9A227',
            self::ModernMinimal => '#0F766E',
            self::FormalBlue => '#0B2545',
            self::ElegantDark => '#D4AF37',
        };
    }

    /** Whether logos should use their light variant (dark backgrounds). */
    public function prefersLightAssets(): bool
    {
        return $this === self::ElegantDark;
    }

    /** @return array<string, string> value => label */
    public static function options(): array
    {
        return array_combine(
            array_map(fn (self $d) => $d->value, self::cases()),
            array_map(fn (self $d) => $d->label(), self::cases()),
        );
    }
}
