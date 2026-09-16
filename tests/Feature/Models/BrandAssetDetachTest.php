<?php

use App\Enums\TemplateDesign;
use App\Models\BrandAsset;
use App\Models\Template;

it('detaches a deleted signature from template layouts so the edit form stays saveable', function () {
    $keep = BrandAsset::factory()->signature()->create();
    $doomed = BrandAsset::factory()->signature()->create();
    $stamp = BrandAsset::factory()->stamp()->create();

    $template = Template::factory()->create([
        'layout_config' => array_replace_recursive(
            Template::defaultLayout(TemplateDesign::FormalBlue),
            [
                'signatures' => [$keep->id, $doomed->id],
                'stamp' => ['asset_id' => $stamp->id],
            ]
        ),
    ]);

    $doomed->delete();

    $layout = $template->fresh()->layout_config;

    expect($layout['signatures'])->toBe([$keep->id])
        ->and($layout['stamp']['asset_id'])->toBe($stamp->id);
});

it('nulls stamp and background references when those assets are deleted', function () {
    $stamp = BrandAsset::factory()->stamp()->create();
    $background = BrandAsset::factory()->background()->create();

    $template = Template::factory()->create([
        'layout_config' => array_replace_recursive(
            Template::defaultLayout(TemplateDesign::FormalBlue),
            [
                'stamp' => ['asset_id' => $stamp->id],
                'background_asset_id' => $background->id,
            ]
        ),
    ]);

    $stamp->delete();
    $background->delete();

    $layout = $template->fresh()->layout_config;

    expect($layout['stamp']['asset_id'])->toBeNull()
        ->and($layout['background_asset_id'])->toBeNull();
});

it('prunes deleted ids but keeps inactive ones', function () {
    $active = BrandAsset::factory()->signature()->create();
    $inactive = BrandAsset::factory()->signature()->create(['is_active' => false]);
    $deletedId = 999999;

    $layout = array_replace_recursive(
        Template::defaultLayout(TemplateDesign::FormalBlue),
        ['signatures' => [$active->id, $inactive->id, $deletedId]]
    );

    $pruned = Template::pruneLayoutAssetReferences($layout);

    expect($pruned['signatures'])->toBe([$active->id, $inactive->id]);
});
