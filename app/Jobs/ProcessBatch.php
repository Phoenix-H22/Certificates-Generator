<?php

namespace App\Jobs;

use App\Certificates\Exceptions\InvalidRowException;
use App\Certificates\Pipeline\SheetReader;
use App\Certificates\Rendering\DataMerger;
use App\Enums\BatchStatus;
use App\Enums\CertificateStatus;
use App\Enums\DeliveryChannel;
use App\Enums\DeliveryStatus;
use App\Models\Batch;
use App\Models\Certificate;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\Middleware\WithoutOverlapping;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use RuntimeException;
use Throwable;

/**
 * Stage 1: parse the uploaded sheet into certificate rows, then fan out a
 * bus batch of [RenderCertificate → DeliverCertificate] chains that ends
 * with FinalizeBatch.
 */
class ProcessBatch implements ShouldQueue
{
    use Queueable;

    public int $tries = 1;

    public int $timeout = 600;

    public function __construct(public int $batchId) {}

    /** @return array<int, object> */
    public function middleware(): array
    {
        return [(new WithoutOverlapping('process-batch-'.$this->batchId))->dontRelease()];
    }

    public function handle(SheetReader $reader, DataMerger $merger): void
    {
        $batch = Batch::with('template')->find($this->batchId);

        if ($batch === null || $batch->isCancelled()) {
            return;
        }

        $batch->markStatus(BatchStatus::Parsing);

        if ($batch->certificates()->doesntExist()) {
            $this->createCertificates($batch, $reader, $merger);
        }

        $pending = $batch->certificates()
            ->where('status', CertificateStatus::Pending->value)
            ->orderBy('row_number')
            ->pluck('id');

        if ($pending->isEmpty()) {
            $batch->markStatus(BatchStatus::Rendering);
            FinalizeBatch::dispatch($batch->getKey());

            return;
        }

        $chains = $pending->map(fn (int $id) => [
            new RenderCertificate($id),
            new DeliverCertificate($id),
        ])->all();

        $bus = Bus::batch($chains)
            ->name('certificates-batch-'.$batch->getKey())
            ->allowFailures()
            ->onQueue(config('certificates.queues.render', 'render'))
            ->finally(fn () => FinalizeBatch::dispatch($batch->getKey()))
            ->dispatch();

        $batch->forceFill(['bus_batch_id' => $bus->id])->save();
        $batch->markStatus(BatchStatus::Rendering);
    }

    private function createCertificates(Batch $batch, SheetReader $reader, DataMerger $merger): void
    {
        if (! $batch->source_path) {
            throw new RuntimeException('Batch has no uploaded spreadsheet.');
        }

        $disk = Storage::disk(config('certificates.disk', 'certificates'));
        $rows = $reader->rows($disk->path($batch->source_path));
        $template = $batch->template;

        $emailRequested = $batch->deliversVia(DeliveryChannel::Email);
        $whatsappRequested = $batch->deliversVia(DeliveryChannel::WhatsApp);

        $total = 0;
        $invalid = 0;

        foreach ($rows->chunk(200) as $chunk) {
            foreach ($chunk as $index => $row) {
                $rowNumber = $index + 2; // 1-based, after the header row
                $total++;

                try {
                    $data = $merger->merge($template, $batch->fixed_values ?? [], $row, $batch->column_map ?? [], $rowNumber);

                    Certificate::create([
                        'batch_id' => $batch->getKey(),
                        'template_id' => $template->getKey(),
                        'row_number' => $rowNumber,
                        'recipient_name' => $data['name'],
                        'recipient_title' => $data['title'],
                        'email' => $data['email'],
                        'phone' => $data['phone'],
                        'data' => $data,
                        'status' => CertificateStatus::Pending,
                        'email_status' => $emailRequested ? DeliveryStatus::Pending : DeliveryStatus::NotRequested,
                        'whatsapp_status' => $whatsappRequested ? DeliveryStatus::Pending : DeliveryStatus::NotRequested,
                    ]);
                } catch (InvalidRowException $e) {
                    $invalid++;

                    Certificate::create([
                        'batch_id' => $batch->getKey(),
                        'template_id' => $template->getKey(),
                        'row_number' => $rowNumber,
                        'recipient_name' => $this->guessName($row, $batch->column_map ?? []) ?? "صف {$rowNumber}",
                        'recipient_title' => null,
                        'email' => null,
                        'phone' => null,
                        'data' => [],
                        'status' => CertificateStatus::RenderFailed,
                        'render_error' => $e->getMessage(),
                    ]);
                }
            }
        }

        $batch->forceFill([
            'total_rows' => $total,
            'render_failed_count' => $invalid,
        ])->save();

        Log::info('[ProcessBatch] rows created', ['batch' => $batch->getKey(), 'total' => $total, 'invalid' => $invalid]);
    }

    /**
     * @param  array<string, mixed>  $row
     * @param  array<string, string|null>  $columnMap
     */
    private function guessName(array $row, array $columnMap): ?string
    {
        $header = $columnMap['name'] ?? null;

        if ($header === null) {
            return null;
        }

        $value = $row[DataMerger::normaliseHeader($header)] ?? null;
        $value = $value === null ? null : trim((string) $value);

        return $value === '' ? null : mb_substr($value, 0, 255);
    }

    public function failed(?Throwable $e): void
    {
        Batch::find($this->batchId)?->markStatus(BatchStatus::Failed, $e?->getMessage());
    }
}
