<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;
use Symfony\Component\Finder\Finder;

/**
 * Monthly housekeeping: old batch files (via certificates:prune), temp
 * spreadsheets/render files, and stale or oversized logs.
 */
class CleanupStorage extends Command
{
    protected $signature = 'certificates:cleanup
        {--days= : Override certificates.retention_days for batch files}
        {--log-days=30 : Delete rotated log files older than this many days}
        {--log-max-mb=50 : Truncate storage/logs/laravel.log when larger than this}
        {--dry-run : Report only}';

    protected $description = 'Delete old certificate files, temporary files and stale logs to free disk space';

    private int $freed = 0;

    public function handle(): int
    {
        $dry = (bool) $this->option('dry-run');

        // 1. Batch files past the retention period (rows stay for verification).
        $this->call('certificates:prune', array_filter([
            '--days' => $this->option('days'),
            '--dry-run' => $dry,
        ]));

        // 2. Temporary files.
        $this->purge(storage_path('app/tmp'), olderThanDays: 1, dry: $dry, label: 'temp spreadsheets');
        $this->purge(storage_path('app/render-tmp'), olderThanDays: 1, dry: $dry, label: 'render temp pages');
        $this->purge(storage_path('app/render-tests'), olderThanDays: 7, dry: $dry, label: 'render test output');
        $this->purge(storage_path('app/private/livewire-tmp'), olderThanDays: 1, dry: $dry, label: 'livewire uploads');
        $this->purge(storage_path('framework/cache/data'), olderThanDays: 30, dry: $dry, label: 'stale file cache');

        // 3. Logs: rotated files older than N days, and an oversized single log.
        $this->purge(storage_path('logs'), olderThanDays: (int) $this->option('log-days'), dry: $dry, label: 'rotated logs', pattern: '/^laravel-\d{4}-\d{2}-\d{2}\.log$|\.log\.\d+$|\.gz$/');
        $this->truncateIfLarge(storage_path('logs/laravel.log'), (int) $this->option('log-max-mb'), $dry);

        foreach (File::glob(storage_path('logs/queue-*.log')) as $queueLog) {
            $this->truncateIfLarge($queueLog, (int) $this->option('log-max-mb'), $dry);
        }

        $this->info(sprintf('%sFreed %s.', $dry ? '[dry-run] would have ' : '', $this->human($this->freed)));

        return self::SUCCESS;
    }

    private function purge(string $directory, int $olderThanDays, bool $dry, string $label, ?string $pattern = null): void
    {
        if (! is_dir($directory)) {
            return;
        }

        $finder = (new Finder)->files()->in($directory)->ignoreDotFiles(true)->date("< now - {$olderThanDays} days");

        if ($pattern !== null) {
            $finder->name($pattern);
        }

        $count = 0;
        $bytes = 0;

        foreach ($finder as $file) {
            $bytes += $file->getSize();
            $count++;

            if (! $dry) {
                @unlink($file->getRealPath());
            }
        }

        if ($count > 0) {
            $this->line(sprintf('%s: %d file(s), %s (%s)', $label, $count, $this->human($bytes), $dry ? 'dry-run' : 'deleted'));
            $this->freed += $bytes;
        }
    }

    private function truncateIfLarge(string $path, int $maxMb, bool $dry): void
    {
        if (! is_file($path)) {
            return;
        }

        $size = filesize($path) ?: 0;

        if ($size <= $maxMb * 1024 * 1024) {
            return;
        }

        $this->line(sprintf('%s: %s > %d MB (%s)', basename($path), $this->human($size), $maxMb, $dry ? 'dry-run' : 'truncated'));

        if (! $dry) {
            file_put_contents($path, '');
        }

        $this->freed += $size;
    }

    private function human(int $bytes): string
    {
        $units = ['B', 'KB', 'MB', 'GB'];
        $i = 0;

        while ($bytes >= 1024 && $i < count($units) - 1) {
            $bytes /= 1024;
            $i++;
        }

        return sprintf('%.1f %s', $bytes, $units[$i]);
    }
}
