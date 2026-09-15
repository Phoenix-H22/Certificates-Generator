<?php

namespace App\Jobs;

use App\Certificates\Rendering\CertificateDataFactory;
use App\Certificates\Rendering\CertificateRenderer;
use App\Enums\CertificateStatus;
use App\Models\Batch;
use App\Models\Certificate;
use Illuminate\Bus\Batchable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\Middleware\ThrottlesExceptions;
use Illuminate\Queue\Middleware\WithoutOverlapping;
use Illuminate\Support\Facades\Storage;
use Throwable;

/** Stage 2: Chromium renders one certificate to PDF on the private disk. */
class RenderCertificate implements ShouldQueue
{
    use Batchable, Queueable;

    public int $tries = 2;

    /** @var list<int> */
    public array $backoff = [15, 60];

    public int $timeout = 120;

    public function __construct(public int $certificateId)
    {
        $this->onQueue(config('certificates.queues.render', 'render'));
    }

    /** @return array<int, object> */
    public function middleware(): array
    {
        return [
            (new WithoutOverlapping('render-certificate-'.$this->certificateId))->releaseAfter(30),
            // If Chromium keeps crashing, pause this queue for a while instead of burning every job.
            (new ThrottlesExceptions(5, 2 * 60))->backoff(1),
        ];
    }

    public function handle(CertificateRenderer $renderer, CertificateDataFactory $factory): void
    {
        if ($this->batch()?->cancelled()) {
            return;
        }

        $certificate = Certificate::with('batch', 'template')->find($this->certificateId);

        if ($certificate === null || $certificate->batch?->isCancelled() || $certificate->isRevoked()) {
            return;
        }

        $disk = Storage::disk(config('certificates.disk', 'certificates'));

        // Idempotent: a retry after a crash must not render (or count) twice.
        if ($certificate->isRendered() && $disk->exists($certificate->pdf_path)) {
            return;
        }

        $pdf = $renderer->pdf($factory->fromCertificate($certificate));
        $path = $certificate->pdfPath();

        $disk->put($path, $pdf);

        $certificate->forceFill([
            'status' => CertificateStatus::Rendered,
            'pdf_path' => $path,
            'rendered_at' => now(),
            'render_error' => null,
        ])->save();

        Batch::whereKey($certificate->batch_id)->increment('rendered_count');
    }

    public function failed(?Throwable $e): void
    {
        $certificate = Certificate::find($this->certificateId);

        if ($certificate === null || $certificate->status === CertificateStatus::RenderFailed) {
            return;
        }

        $certificate->forceFill([
            'status' => CertificateStatus::RenderFailed,
            'render_error' => mb_substr((string) $e?->getMessage(), 0, 2000),
        ])->save();

        Batch::whereKey($certificate->batch_id)->increment('render_failed_count');
    }
}
