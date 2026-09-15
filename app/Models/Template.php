<?php

namespace App\Models;

use App\Casts\FieldSchemaCast;
use App\Certificates\Data\FieldDefinition;
use App\Certificates\Data\FieldSchema;
use App\Enums\BrandAssetType;
use App\Enums\TemplateDesign;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * A certificate template: a visual design plus the fields it needs and
 * the brand assets (logos, signatures, stamp) it places.
 *
 * @property FieldSchema $fields_schema
 * @property array<string, mixed> $layout_config
 */
class Template extends Model
{
    use HasFactory, SoftDeletes;

    public const MAX_SIGNATURES = 3;

    /** Positions a stamp or QR block may occupy. */
    public const POSITIONS = [
        'bottom-left' => 'أسفل اليسار',
        'bottom-center' => 'أسفل المنتصف',
        'bottom-right' => 'أسفل اليمين',
        'over-signature-1' => 'فوق التوقيع الأول',
        'over-signature-2' => 'فوق التوقيع الثاني',
        'over-signature-3' => 'فوق التوقيع الثالث',
    ];

    public const QR_POSITIONS = [
        'bottom-left' => 'أسفل اليسار',
        'bottom-right' => 'أسفل اليمين',
        'top-left' => 'أعلى اليسار',
        'top-right' => 'أعلى اليمين',
    ];

    protected $fillable = [
        'name',
        'slug',
        'design',
        'fields_schema',
        'layout_config',
        'is_active',
        'preview_path',
        'created_by',
    ];

    protected function casts(): array
    {
        return [
            'design' => TemplateDesign::class,
            'fields_schema' => FieldSchemaCast::class,
            'layout_config' => 'array',
            'is_active' => 'boolean',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (self $template) {
            $template->slug ??= Str::slug($template->name) ?: Str::random(8);
            $template->layout_config = self::normalizeLayout($template->layout_config ?? [], $template->design);
        });

        static::updating(function (self $template) {
            $template->layout_config = self::normalizeLayout($template->layout_config ?? [], $template->design);
        });
    }

    /* ----------------------------------------------------------------- */
    /*  Relationships */
    /* ----------------------------------------------------------------- */

    public function batches(): HasMany
    {
        return $this->hasMany(Batch::class);
    }

    public function certificates(): HasMany
    {
        return $this->hasMany(Certificate::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    /* ----------------------------------------------------------------- */
    /*  Layout config */
    /* ----------------------------------------------------------------- */

    /** @return array<string, mixed> */
    public static function defaultLayout(TemplateDesign $design): array
    {
        return [
            'logos' => [],
            'signatures' => [],
            'stamp' => [
                'asset_id' => null,
                'position' => 'bottom-center',
                'width_mm' => 38,
                'opacity' => 0.9,
                'rotate' => -6,
            ],
            'background_asset_id' => null,
            'accent' => $design->defaultAccent(),
            'qr_position' => 'bottom-left',
            'qr_size_mm' => 24,
            'title_text' => 'شهادة شكر وتقدير',
            'body_text' => 'يتقدم {organisation} بأسمى آيات الشكر والتقدير إلى',
            'closing_text' => 'وذلك لمشاركته الفعّالة في {event_name} المنعقد بتاريخ {event_date}',
            'name_font' => 'Amiri',
            'numerals' => 'arabic',
        ];
    }

    /**
     * Fill missing keys with design defaults and clamp values, so views can
     * trust the shape of layout_config.
     *
     * @param  array<string, mixed>  $layout
     * @return array<string, mixed>
     */
    public static function normalizeLayout(array $layout, TemplateDesign $design): array
    {
        $defaults = self::defaultLayout($design);
        $merged = array_replace_recursive($defaults, $layout);

        $merged['logos'] = array_values(array_filter(array_map('intval', (array) ($merged['logos'] ?? []))));
        $merged['signatures'] = array_slice(
            array_values(array_filter(array_map('intval', (array) ($merged['signatures'] ?? [])))),
            0,
            self::MAX_SIGNATURES,
        );
        $merged['stamp']['asset_id'] = $merged['stamp']['asset_id'] ? (int) $merged['stamp']['asset_id'] : null;
        $merged['stamp']['width_mm'] = max(15, min(80, (int) ($merged['stamp']['width_mm'] ?? 38)));
        $merged['stamp']['opacity'] = max(0.1, min(1, (float) ($merged['stamp']['opacity'] ?? 0.9)));
        $merged['stamp']['rotate'] = max(-45, min(45, (int) ($merged['stamp']['rotate'] ?? 0)));
        $merged['stamp']['position'] = array_key_exists($merged['stamp']['position'] ?? '', self::POSITIONS)
            ? $merged['stamp']['position'] : 'bottom-center';
        $merged['background_asset_id'] = $merged['background_asset_id'] ? (int) $merged['background_asset_id'] : null;
        $merged['qr_position'] = array_key_exists($merged['qr_position'] ?? '', self::QR_POSITIONS)
            ? $merged['qr_position'] : 'bottom-left';
        $merged['qr_size_mm'] = max(18, min(40, (int) ($merged['qr_size_mm'] ?? 24)));
        $merged['accent'] = preg_match('/^#[0-9a-f]{6}$/i', (string) ($merged['accent'] ?? '')) ? $merged['accent'] : $design->defaultAccent();
        $merged['numerals'] = in_array($merged['numerals'] ?? '', ['arabic', 'latin'], true) ? $merged['numerals'] : 'arabic';

        return $merged;
    }

    public function layout(string $key, mixed $default = null): mixed
    {
        return data_get($this->layout_config, $key, $default);
    }

    /* ----------------------------------------------------------------- */
    /*  Assets referenced by the layout */
    /* ----------------------------------------------------------------- */

    /** @return Collection<int, BrandAsset> in layout order */
    public function logos(): Collection
    {
        return $this->assetsInOrder((array) $this->layout('logos', []), BrandAssetType::Logo);
    }

    /** @return Collection<int, BrandAsset> in layout order */
    public function signatures(): Collection
    {
        return $this->assetsInOrder((array) $this->layout('signatures', []), BrandAssetType::Signature);
    }

    public function stamp(): ?BrandAsset
    {
        $id = $this->layout('stamp.asset_id');

        return $id ? BrandAsset::active()->ofType(BrandAssetType::Stamp)->find($id) : null;
    }

    public function background(): ?BrandAsset
    {
        $id = $this->layout('background_asset_id');

        return $id ? BrandAsset::active()->ofType(BrandAssetType::Background)->find($id) : null;
    }

    /**
     * @param  list<int>  $ids
     * @return Collection<int, BrandAsset>
     */
    private function assetsInOrder(array $ids, BrandAssetType $type): Collection
    {
        if ($ids === []) {
            return new Collection;
        }

        $assets = BrandAsset::active()->ofType($type)->whereIn('id', $ids)->get()->keyBy('id');

        return (new Collection(array_map(fn (int $id) => $assets->get($id), $ids)))->filter()->values();
    }

    /* ----------------------------------------------------------------- */
    /*  Fields */
    /* ----------------------------------------------------------------- */

    /** @return list<FieldDefinition> */
    public function fixedFields(): array
    {
        return $this->fields_schema->fixed();
    }

    /** @return list<FieldDefinition> */
    public function rowFields(): array
    {
        return $this->fields_schema->row();
    }

    public function previewUrl(): ?string
    {
        return $this->preview_path ? Storage::disk('public')->url($this->preview_path) : null;
    }
}
