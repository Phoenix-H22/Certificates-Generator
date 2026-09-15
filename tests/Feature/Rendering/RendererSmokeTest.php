<?php

use App\Certificates\Rendering\CertificateDataFactory;
use App\Certificates\Rendering\CertificateRenderer;
use App\Certificates\Rendering\RenderMode;
use App\Enums\TemplateDesign;
use App\Models\Template;
use Illuminate\Support\Facades\File;

/*
|--------------------------------------------------------------------------
| Real Chromium renders. Skipped unless RENDER_TESTS=1 (CI "render" job).
|--------------------------------------------------------------------------
*/

beforeEach(function () {
    if (! env('RENDER_TESTS')) {
        $this->markTestSkipped('Set RENDER_TESTS=1 to run real Chromium renders.');
    }
});

it('renders the sample HTML for every design without errors', function () {
    $renderer = app(CertificateRenderer::class);
    $factory = app(CertificateDataFactory::class);

    foreach (TemplateDesign::cases() as $design) {
        $template = Template::factory()->design($design)->create();
        $html = $renderer->html($factory->sample($template), RenderMode::Preview);

        expect($html)->toContain('dir="rtl"')->toContain('class="qr');
    }
});

it('produces a real PDF and thumbnail for each design', function () {
    $renderer = app(CertificateRenderer::class);
    $factory = app(CertificateDataFactory::class);
    $out = storage_path('app/render-tests');
    File::ensureDirectoryExists($out);

    foreach (TemplateDesign::cases() as $design) {
        $template = Template::factory()->design($design)->create();
        $data = $factory->sample($template, 'أحمد محمود عبد الرحمن السيد الشرقاوي الطويل جداً');

        $pdf = $renderer->pdf($data);
        File::put("{$out}/{$design->value}.pdf", $pdf);

        expect($pdf)->toStartWith('%PDF')
            ->and(strlen($pdf))->toBeGreaterThan(20 * 1024);

        $png = $renderer->thumbnail($data);
        File::put("{$out}/{$design->value}.png", $png);

        expect(substr($png, 1, 3))->toBe('PNG');
    }
})->group('render');
