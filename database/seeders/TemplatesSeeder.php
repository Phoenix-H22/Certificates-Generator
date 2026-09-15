<?php

namespace Database\Seeders;

use App\Enums\BrandAssetType;
use App\Enums\TemplateDesign;
use App\Models\BrandAsset;
use App\Models\OrganisationSettings;
use App\Models\Template;
use Database\Factories\TemplateFactory;
use Illuminate\Database\Seeder;

/**
 * One ready-to-use template per design, wired to whatever brand assets the
 * BrandAssetsSeeder registered.
 */
class TemplatesSeeder extends Seeder
{
    public function run(): void
    {
        $logos = BrandAsset::active()->ofType(BrandAssetType::Logo)->ordered()->pluck('id')->all();
        $signatures = BrandAsset::active()->ofType(BrandAssetType::Signature)->ordered()->pluck('id')->take(2)->all();
        $stamp = BrandAsset::active()->ofType(BrandAssetType::Stamp)->ordered()->value('id');

        foreach (TemplateDesign::cases() as $design) {
            $layout = Template::defaultLayout($design);
            $layout['logos'] = $logos;
            $layout['signatures'] = $signatures;
            $layout['stamp']['asset_id'] = $stamp;

            Template::updateOrCreate(
                ['slug' => str_replace('_', '-', $design->value)],
                [
                    'name' => 'شهادة شكر وتقدير — '.$design->label(),
                    'design' => $design,
                    'fields_schema' => TemplateFactory::defaultFields(),
                    'layout_config' => $layout,
                    'is_active' => true,
                ],
            );
        }

        $settings = OrganisationSettings::current();

        if ($settings->default_template_id === null) {
            $settings->update(['default_template_id' => Template::where('slug', 'classic-gold')->value('id')]);
        }
    }
}
