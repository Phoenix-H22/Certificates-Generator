<?php

namespace App\Console\Commands;

use App\Jobs\GenerateTemplateThumbnail;
use App\Models\Template;
use Illuminate\Console\Command;

class GenerateTemplateThumbnails extends Command
{
    protected $signature = 'certificates:thumbnails
        {--force : Regenerate even when a thumbnail already exists}
        {--queue : Dispatch to the queue instead of rendering now}';

    protected $description = 'Render PNG thumbnails for the template gallery';

    public function handle(): int
    {
        $templates = Template::query()
            ->when(! $this->option('force'), fn ($q) => $q->whereNull('preview_path'))
            ->get();

        if ($templates->isEmpty()) {
            $this->info('Nothing to do.');

            return self::SUCCESS;
        }

        foreach ($templates as $template) {
            if ($this->option('queue')) {
                GenerateTemplateThumbnail::dispatch($template);
                $this->line("queued: {$template->name}");

                continue;
            }

            GenerateTemplateThumbnail::dispatchSync($template);
            $this->info("rendered: {$template->name} → ".$template->fresh()->preview_path);
        }

        return self::SUCCESS;
    }
}
