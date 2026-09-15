<?php

namespace Database\Seeders;

use App\Enums\BrandAssetType;
use App\Models\BrandAsset;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Storage;

/**
 * Copies the bundled ASFEC sample logos onto the public disk and registers
 * them, plus placeholder signatures so seeded templates render completely.
 */
class BrandAssetsSeeder extends Seeder
{
    public function run(): void
    {
        $disk = Storage::disk(BrandAsset::DISK);
        $samples = resource_path('brand-samples');

        $assets = [
            ['file' => 'asfec-logo.png', 'type' => BrandAssetType::Logo, 'name' => 'شعار المركز الإقليمي لتعليم الكبار', 'width_mm' => 28, 'sort' => 0],
            ['file' => 'asfec-statue-gold-text.png', 'type' => BrandAssetType::Logo, 'name' => 'شعار أسفك الذهبي', 'width_mm' => 24, 'sort' => 1],
            ['file' => 'signature-director.png', 'type' => BrandAssetType::Signature, 'name' => 'د/ محمد عبد الوارث القاضي', 'role' => 'مدير المركز', 'width_mm' => 42, 'sort' => 0],
            ['file' => 'signature-chairman.png', 'type' => BrandAssetType::Signature, 'name' => 'أ.د/ عيد عبد الواحد', 'role' => 'رئيس الهيئة', 'width_mm' => 42, 'sort' => 1],
            ['file' => 'stamp-asfec.png', 'type' => BrandAssetType::Stamp, 'name' => 'ختم المركز', 'width_mm' => 38, 'sort' => 0],
        ];

        foreach ($assets as $asset) {
            $source = $samples.DIRECTORY_SEPARATOR.$asset['file'];

            if (! is_file($source)) {
                $this->command?->warn("Brand sample missing: {$asset['file']}");

                continue;
            }

            $target = BrandAsset::DIRECTORY.'/'.$asset['file'];

            if (! $disk->exists($target)) {
                $disk->put($target, file_get_contents($source));
            }

            BrandAsset::updateOrCreate(
                ['path' => $target],
                [
                    'type' => $asset['type'],
                    'name' => $asset['name'],
                    'role' => $asset['role'] ?? null,
                    'width_mm' => $asset['width_mm'],
                    'sort' => $asset['sort'],
                    'is_active' => true,
                ],
            );
        }
    }
}
