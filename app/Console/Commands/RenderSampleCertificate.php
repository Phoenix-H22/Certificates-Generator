<?php

namespace App\Console\Commands;

use App\Certificates\Rendering\CertificateDataFactory;
use App\Certificates\Rendering\CertificateRenderer;
use App\Certificates\Rendering\RenderMode;
use App\Enums\TemplateDesign;
use App\Models\Template;
use Illuminate\Console\Command;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\File;

class RenderSampleCertificate extends Command
{
    protected $signature = 'certificates:render-sample
        {template? : Template id or slug (defaults to every active template)}
        {--design= : Override the design (classic_gold, modern_minimal, formal_blue, elegant_dark)}
        {--name= : Recipient name to render}
        {--out= : Output directory (default storage/app/render-tests)}
        {--html : Also write the HTML used for the PDF}
        {--png : Also write a PNG thumbnail}';

    protected $description = 'Render sample certificate PDFs through the real Chromium pipeline';

    public function handle(CertificateRenderer $renderer, CertificateDataFactory $factory): int
    {
        $out = $this->option('out') ?: storage_path('app/render-tests');
        File::ensureDirectoryExists($out);

        $templates = $this->resolveTemplates();

        if ($templates->isEmpty()) {
            $this->error('No templates found. Run `php artisan db:seed` first.');

            return self::FAILURE;
        }

        foreach ($templates as $template) {
            if ($design = $this->option('design')) {
                $template->design = TemplateDesign::from($design);
            }

            $data = $factory->sample($template, $this->option('name'));
            $base = $out.DIRECTORY_SEPARATOR.$template->slug.'-'.$template->design->value;

            $started = microtime(true);
            $pdf = $renderer->pdf($data);
            File::put($base.'.pdf', $pdf);
            $ms = (int) ((microtime(true) - $started) * 1000);

            $this->info(sprintf('%s → %s (%d KB, %d ms)', $template->name, $base.'.pdf', strlen($pdf) / 1024, $ms));

            if ($this->option('html')) {
                File::put($base.'.html', $renderer->html($data, RenderMode::Pdf));
            }

            if ($this->option('png')) {
                File::put($base.'.png', $renderer->thumbnail($data));
                $this->line('   thumbnail → '.$base.'.png');
            }
        }

        return self::SUCCESS;
    }

    /** @return Collection<int, Template> */
    private function resolveTemplates()
    {
        $arg = $this->argument('template');

        if ($arg === null) {
            return Template::active()->orderBy('id')->get();
        }

        $query = is_numeric($arg) ? Template::whereKey($arg) : Template::where('slug', $arg);

        return $query->get();
    }
}
