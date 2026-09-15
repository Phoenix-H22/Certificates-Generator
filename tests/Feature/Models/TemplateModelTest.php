<?php

use App\Certificates\Data\FieldSchema;
use App\Enums\BrandAssetType;
use App\Enums\TemplateDesign;
use App\Models\BrandAsset;
use App\Models\Template;

it('casts fields_schema to a FieldSchema value object', function () {
    $template = Template::factory()->create();

    expect($template->fresh()->fields_schema)->toBeInstanceOf(FieldSchema::class)
        ->and($template->fresh()->fields_schema->keys())->toBe(['event_name', 'event_date', 'event_type']);
});

it('normalises layout_config on save', function () {
    $template = Template::factory()->design(TemplateDesign::FormalBlue)->create([
        'layout_config' => [
            'logos' => ['3', 0, '5'],
            'signatures' => [1, 2, 3, 4],
            'stamp' => ['position' => 'nowhere', 'width_mm' => 500, 'opacity' => 5, 'rotate' => 90],
            'accent' => 'not-a-colour',
            'qr_position' => 'middle',
            'qr_size_mm' => 3,
        ],
    ]);

    $layout = $template->fresh()->layout_config;

    expect($layout['logos'])->toBe([3, 5])
        ->and($layout['signatures'])->toBe([1, 2, 3])
        ->and($layout['stamp']['position'])->toBe('bottom-center')
        ->and($layout['stamp']['width_mm'])->toBe(80)
        ->and($layout['stamp']['opacity'])->toEqual(1.0)
        ->and($layout['stamp']['rotate'])->toBe(45)
        ->and($layout['accent'])->toBe(TemplateDesign::FormalBlue->defaultAccent())
        ->and($layout['qr_position'])->toBe('bottom-left')
        ->and($layout['qr_size_mm'])->toBe(18)
        ->and($layout['title_text'])->toBe('شهادة شكر وتقدير');
});

it('resolves referenced brand assets in layout order, skipping inactive ones', function () {
    $a = BrandAsset::factory()->logo()->create();
    $b = BrandAsset::factory()->logo()->create();
    $inactive = BrandAsset::factory()->logo()->inactive()->create();
    $signature = BrandAsset::factory()->signature()->create();
    $stamp = BrandAsset::factory()->stamp()->create();

    $template = Template::factory()->create([
        'layout_config' => [
            'logos' => [$b->id, $inactive->id, $a->id],
            'signatures' => [$signature->id],
            'stamp' => ['asset_id' => $stamp->id],
        ],
    ]);

    expect($template->logos()->pluck('id')->all())->toBe([$b->id, $a->id])
        ->and($template->signatures()->first()?->type)->toBe(BrandAssetType::Signature)
        ->and($template->stamp()?->id)->toBe($stamp->id);
});

it('generates a slug from the name when none is given', function () {
    $template = Template::factory()->create(['name' => 'Workshop Certificate', 'slug' => null]);

    expect($template->slug)->toBe('workshop-certificate');
});
