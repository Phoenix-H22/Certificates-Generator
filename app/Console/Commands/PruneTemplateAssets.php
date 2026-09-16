<?php

namespace App\Console\Commands;

use App\Models\Template;
use Illuminate\Console\Command;

/**
 * One-off repair for templates left holding deleted brand-asset IDs
 * (stale signature/logo/stamp references block the Filament edit form
 * with "value not in allowed list"). New deletes self-clean via
 * BrandAsset::deleting, this command fixes rows created before that.
 */
class PruneTemplateAssets extends Command
{
    protected $signature = 'templates:prune-assets
        {--dry-run : Report only, change nothing}';

    protected $description = 'Remove deleted brand-asset references from all template layouts';

    public function handle(): int
    {
        $dry = (bool) $this->option('dry-run');
        $checked = 0;
        $fixed = 0;

        Template::query()->chunkById(100, function ($templates) use (&$checked, &$fixed, $dry) {
            foreach ($templates as $template) {
                $checked++;
                $pruned = Template::pruneLayoutAssetReferences($template->layout_config ?? []);

                if ($pruned !== $template->layout_config) {
                    $fixed++;
                    $this->line(sprintf('%sTemplate #%d (%s): pruned stale asset reference%s', $dry ? '[dry-run] ' : '', $template->id, $template->name, $dry ? '' : ' — saved'));

                    if (! $dry) {
                        $template->updateQuietly(['layout_config' => $pruned]);
                    }
                }
            }
        });

        $this->info(sprintf('%sChecked %d template(s), %s %d.', $dry ? '[dry-run] ' : '', $checked, $dry ? 'would fix' : 'fixed', $fixed));

        return self::SUCCESS;
    }
}
