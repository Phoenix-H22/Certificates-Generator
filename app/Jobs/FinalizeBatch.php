<?php

namespace App\Jobs;

use App\Certificates\Pipeline\ErrorReportBuilder;
use App\Certificates\Pipeline\ZipBuilder;
use App\Enums\BatchStatus;
use App\Enums\CertificateStatus;
use App\Enums\DeliveryStatus;
use App\Mail\BatchFinishedMail;
use App\Models\Batch;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\Middleware\WithoutOverlapping;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Throwable;

/**
 * Stage 4: recount, build the ZIP + error report, mark the batch done and
 * tell the admin.
 */
class FinalizeBatch implements ShouldQueue
{
    use Queueable;

    public int $tries = 2;

    public int $timeout = 900;

    public function __construct(public int $batchId) {}

    /** @return array<int, object> */
    public function middleware(): array
    {
        return [(new WithoutOverlapping('finalize-batch-'.$this->batchId))->releaseAfter(60)];
    }

    public function handle(ZipBuilder $zip, ErrorReportBuilder $report): void
    {
        $batch = Batch::find($this->batchId);

        if ($batch === null) {
            return;
        }

        if ($batch->isCancelled()) {
            $batch->markStatus(BatchStatus::Cancelled);

            return;
        }

        $this->recount($batch);

        $batch->forceFill([
            'zip_path' => $zip->build($batch),
            'error_report_path' => $report->build($batch),
        ])->save();

        $batch->markStatus($batch->hasFailures() ? BatchStatus::CompletedWithErrors : BatchStatus::Completed);

        $this->notifyAdmin($batch);
    }

    /** Counters are incremented by the jobs; make them exact from the rows. */
    private function recount(Batch $batch): void
    {
        $certificates = $batch->certificates();

        $batch->forceFill([
            'total_rows' => (clone $certificates)->count(),
            'rendered_count' => (clone $certificates)->where('status', CertificateStatus::Rendered->value)->count(),
            'render_failed_count' => (clone $certificates)->where('status', CertificateStatus::RenderFailed->value)->count(),
            'email_sent_count' => (clone $certificates)->where('email_status', DeliveryStatus::Sent->value)->count(),
            'email_failed_count' => (clone $certificates)->where('email_status', DeliveryStatus::Failed->value)->count(),
            'whatsapp_sent_count' => (clone $certificates)->where('whatsapp_status', DeliveryStatus::Sent->value)->count(),
            'whatsapp_failed_count' => (clone $certificates)->where('whatsapp_status', DeliveryStatus::Failed->value)->count(),
        ])->save();
    }

    private function notifyAdmin(Batch $batch): void
    {
        $admin = config('services.admin_email');

        if (! $admin) {
            return;
        }

        try {
            Mail::to($admin)->send(new BatchFinishedMail($batch->fresh()));
        } catch (Throwable $e) {
            Log::warning('[FinalizeBatch] admin mail failed', ['batch' => $batch->getKey(), 'error' => $e->getMessage()]);
        }
    }

    public function failed(?Throwable $e): void
    {
        Batch::find($this->batchId)?->markStatus(BatchStatus::Failed, 'Finalize failed: '.$e?->getMessage());
    }
}
