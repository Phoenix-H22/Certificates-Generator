<?php

namespace App\Certificates\Rendering;

use App\Certificates\Exceptions\RenderFailedException;
use App\Enums\TemplateDesign;
use App\Models\Template;
use Illuminate\Contracts\View\Factory as ViewFactory;
use Throwable;

/**
 * Turns CertificateData into HTML (for previews), PDF bytes (the real
 * certificate) or PNG bytes (template thumbnails).
 */
class CertificateRenderer
{
    /** A4 landscape at 96 dpi — matches the @page size in the layout. */
    public const PAGE_WIDTH_PX = 1123;

    public const PAGE_HEIGHT_PX = 794;

    public function __construct(
        private readonly ViewFactory $views,
        private readonly BrowsershotFactory $browsershot,
        private readonly AssetUrl $assetUrl,
    ) {}

    public function html(CertificateData $data, RenderMode $mode = RenderMode::Pdf): string
    {
        return $this->views->make($data->design->view(), [
            'cert' => $data,
            'mode' => $mode,
            'asset' => fn (string $path) => ($this->assetUrl)($path, $mode),
        ])->render();
    }

    /** @return string PDF bytes */
    public function pdf(CertificateData $data): string
    {
        $html = $this->html($data, RenderMode::Pdf);

        try {
            return $this->browsershot->make($html)
                ->format('A4')
                ->landscape()
                ->margins(0, 0, 0, 0)
                ->setOption('preferCSSPageSize', true)
                ->waitUntilNetworkIdle()
                ->waitForFunction('window.__certReady === true', null, 15000)
                ->pdf();
        } catch (Throwable $e) {
            throw RenderFailedException::wrap($e, "PDF render failed for design {$data->design->value}:");
        }
    }

    /** @return string PNG bytes, page-sized */
    public function thumbnail(CertificateData $data): string
    {
        $html = $this->html($data, RenderMode::Thumbnail);

        try {
            return $this->browsershot->make($html)
                ->windowSize(self::PAGE_WIDTH_PX, self::PAGE_HEIGHT_PX)
                ->deviceScaleFactor(1)
                ->waitUntilNetworkIdle()
                ->waitForFunction('window.__certReady === true', null, 15000)
                ->screenshot();
        } catch (Throwable $e) {
            throw RenderFailedException::wrap($e, "Thumbnail render failed for design {$data->design->value}:");
        }
    }

    /** Convenience for the panel: sample thumbnail for a template. */
    public function sampleThumbnail(Template $template, CertificateDataFactory $factory): string
    {
        return $this->thumbnail($factory->sample($template));
    }

    /** @return list<TemplateDesign> designs that have a Blade view on disk */
    public function availableDesigns(): array
    {
        return array_values(array_filter(
            TemplateDesign::cases(),
            fn (TemplateDesign $d) => $this->views->exists($d->view()),
        ));
    }
}
