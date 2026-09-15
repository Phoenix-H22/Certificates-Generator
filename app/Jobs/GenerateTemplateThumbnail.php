<?php

namespace App\Jobs;

use App\Certificates\Rendering\CertificateDataFactory;
use App\Certificates\Rendering\CertificateRenderer;
use App\Models\Template;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\Middleware\WithoutOverlapping;
use Illuminate\Support\Facades\Storage;

/** Renders the gallery thumbnail for a template (PNG on the public disk). */
class GenerateTemplateThumbnail implements ShouldQueue
{
    use Queueable;

    public int $tries = 2;

    public int $timeout = 120;

    public function __construct(public Template $template)
    {
        $this->onQueue(config('certificates.queues.render', 'render'));
    }

    /** @return array<int, object> */
    public function middleware(): array
    {
        return [new WithoutOverlapping('template-thumbnail-'.$this->template->getKey())];
    }

    public function handle(CertificateRenderer $renderer, CertificateDataFactory $factory): void
    {
        $png = $renderer->thumbnail($factory->sample($this->template));
        $path = 'templates/'.$this->template->slug.'.png';

        Storage::disk('public')->put($path, $png);

        $this->template->forceFill(['preview_path' => $path])->saveQuietly();
    }
}
