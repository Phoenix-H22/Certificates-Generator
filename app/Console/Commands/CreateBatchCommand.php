<?php

namespace App\Console\Commands;

use App\Certificates\Pipeline\ColumnMapper;
use App\Certificates\Pipeline\SheetReader;
use App\Enums\BatchStatus;
use App\Enums\DeliveryChannel;
use App\Jobs\ProcessBatch;
use App\Models\Batch;
use App\Models\Template;
use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;

/**
 * Create and start a batch from the command line — the same pipeline the
 * panel wizard uses, handy for testing and scripted imports.
 */
class CreateBatchCommand extends Command
{
    protected $signature = 'certificates:batch
        {template : Template id or slug}
        {sheet : Path to the participants .xlsx}
        {--name= : Batch name (defaults to the file name)}
        {--fixed=* : Fixed field values, key=value (e.g. --fixed=event_name="ورشة ...")}
        {--map=* : Column overrides, field=Header (e.g. --map=name="الاسم")}
        {--via=* : Delivery channels: email, whatsapp (default: none, files only)}
        {--now : Run the pipeline synchronously instead of queueing it}';

    protected $description = 'Create a certificate batch from a spreadsheet and start processing it';

    public function handle(SheetReader $reader, ColumnMapper $mapper): int
    {
        $template = $this->resolveTemplate();
        $sheet = (string) $this->argument('sheet');

        if ($template === null) {
            $this->error('Template not found.');

            return self::FAILURE;
        }

        if (! is_file($sheet)) {
            $this->error("Sheet not found: {$sheet}");

            return self::FAILURE;
        }

        $fixed = $this->pairs($this->option('fixed'));
        $fixedValidator = Validator::make($fixed, $template->fields_schema->fixedRules());

        if ($fixedValidator->fails()) {
            foreach ($fixedValidator->errors()->all() as $error) {
                $this->error($error);
            }

            return self::FAILURE;
        }

        $headers = $reader->headers($sheet);
        $map = array_merge($mapper->suggest($headers, $template), $this->pairs($this->option('map')));

        if ($missing = $mapper->missing($map, $template)) {
            $this->error('Unmapped required columns: '.implode(', ', $missing).'. Headers found: '.implode(' | ', $headers));

            return self::FAILURE;
        }

        $via = array_values(array_filter(
            array_map(fn ($v) => DeliveryChannel::tryFrom(strtolower((string) $v))?->value, (array) $this->option('via')),
        ));

        $disk = Storage::disk(config('certificates.disk', 'certificates'));
        $storedPath = 'uploads/'.now()->format('Y/m').'/'.Str::uuid().'.xlsx';
        $disk->put($storedPath, file_get_contents($sheet));

        $batch = Batch::create([
            'template_id' => $template->getKey(),
            'name' => $this->option('name') ?: pathinfo($sheet, PATHINFO_FILENAME),
            'status' => BatchStatus::Queued,
            'deliver_via' => $via,
            'fixed_values' => $fixed,
            'column_map' => $map,
            'source_path' => $storedPath,
            'source_original_name' => basename($sheet),
            'created_by' => User::where('is_admin', true)->value('id'),
        ]);

        $this->table(['field', 'column'], collect($map)->map(fn ($h, $k) => [$k, $h ?? '—'])->values()->all());
        $this->info("Batch #{$batch->getKey()} created ({$batch->name}).");

        if ($this->option('now')) {
            ProcessBatch::dispatchSync($batch->getKey());
            $this->info('Processed synchronously. Status: '.$batch->fresh()->status->label());
        } else {
            ProcessBatch::dispatch($batch->getKey());
            $this->info('Queued. Run `php artisan queue:work --queue=render,deliver,default` to process it.');
        }

        return self::SUCCESS;
    }

    private function resolveTemplate(): ?Template
    {
        $arg = (string) $this->argument('template');

        return is_numeric($arg) ? Template::find($arg) : Template::where('slug', $arg)->first();
    }

    /**
     * @param  array<int, string>|null  $pairs
     * @return array<string, string>
     */
    private function pairs(?array $pairs): array
    {
        $out = [];

        foreach ((array) $pairs as $pair) {
            if (! str_contains((string) $pair, '=')) {
                continue;
            }

            [$key, $value] = explode('=', (string) $pair, 2);
            $out[trim($key)] = trim($value, " \t\n\r\"'");
        }

        return $out;
    }
}
