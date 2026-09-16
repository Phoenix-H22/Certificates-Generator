<?php

namespace App\Models;

use App\Enums\BrandAssetType;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;

/**
 * A logo, signature image, stamp or background stored on the public disk.
 */
class BrandAsset extends Model
{
    use HasFactory;

    public const DISK = 'public';

    public const DIRECTORY = 'brand';

    protected $fillable = [
        'type',
        'name',
        'role',
        'path',
        'path_light',
        'width_mm',
        'sort',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'type' => BrandAssetType::class,
            'width_mm' => 'integer',
            'sort' => 'integer',
            'is_active' => 'boolean',
        ];
    }

    protected static function booted(): void
    {
        // Keep templates saveable: a deleted logo/signature/stamp left inside
        // layout_config makes the template edit form fail validation
        // ("value not in allowed list") and blocks saving.
        static::deleting(function (self $asset) {
            Template::detachBrandAsset($asset->id);
        });
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    public function scopeOfType(Builder $query, BrandAssetType $type): Builder
    {
        return $query->where('type', $type->value);
    }

    public function scopeOrdered(Builder $query): Builder
    {
        return $query->orderBy('sort')->orderBy('id');
    }

    public function url(bool $light = false): ?string
    {
        $path = $light && $this->path_light ? $this->path_light : $this->path;

        return $path ? Storage::disk(self::DISK)->url($path) : null;
    }

    /**
     * Inline the image as a data: URI so rendered HTML is self-contained
     * (Chromium then needs no network or file access for brand images).
     */
    public function dataUri(bool $light = false): ?string
    {
        $path = $light && $this->path_light ? $this->path_light : $this->path;

        if (! $path || ! Storage::disk(self::DISK)->exists($path)) {
            return null;
        }

        $bytes = Storage::disk(self::DISK)->get($path);
        $mime = Storage::disk(self::DISK)->mimeType($path) ?: 'image/png';

        return 'data:'.$mime.';base64,'.base64_encode($bytes);
    }
}
