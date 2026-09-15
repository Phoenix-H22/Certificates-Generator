<?php

namespace App\Console\Commands;

use App\Models\Batch;
use App\Models\Certificate;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;

/**
 * Deletes PDFs, ZIPs, sources and reports of old batches. Certificate rows
 * stay so verification keeps working; only the files go.
 */
class PruneCertificateFiles extends Command
{
    protected $signature = 'certificates:prune {--days= : Override certificates.retention_days} {--dry-run}';

    protected $description = 'Remove batch files older than the retention period (rows are kept for verification)';

    public function handle(): int
    {
        $days = (int) ($this->option('days') ?: config('certificates.retention_days', 180));
        $cutoff = now()->subDays($days);
        $disk = Storage::disk(config('certificates.disk', 'certificates'));
        $dry = (bool) $this->option('dry-run');

        $batches = Batch::query()
            ->whereNotNull('finished_at')
            ->where('finished_at', '<', $cutoff)
            ->where(fn ($q) => $q->whereNotNull('zip_path')->orWhereNotNull('source_path')->orWhereNotNull('error_report_path'))
            ->get();

        foreach ($batches as $batch) {
            $this->line(($dry ? '[dry-run] ' : '')."Pruning batch #{$batch->getKey()} ({$batch->name}, finished {$batch->finished_at?->toDateString()})");

            if ($dry) {
                continue;
            }

            $disk->deleteDirectory($batch->directory());

            if ($batch->source_path) {
                $disk->delete($batch->source_path);
            }

            $batch->forceFill(['zip_path' => null, 'error_report_path' => null, 'source_path' => null])->save();

            Certificate::where('batch_id', $batch->getKey())->update(['pdf_path' => null]);
        }

        $this->info(sprintf('%d batch(es) older than %d days processed.', $batches->count(), $days));

        return self::SUCCESS;
    }
}
