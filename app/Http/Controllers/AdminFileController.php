<?php

namespace App\Http\Controllers;

use App\Models\Batch;
use App\Models\Certificate;
use Illuminate\Contracts\Filesystem\Filesystem;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

/** Authenticated admin downloads from the private certificates disk. */
class AdminFileController extends Controller
{
    public function certificatePdf(Certificate $certificate): StreamedResponse
    {
        $disk = $this->disk();

        abort_unless($certificate->pdf_path && $disk->exists($certificate->pdf_path), 404);

        return $disk->response($certificate->pdf_path, $certificate->downloadName(), [
            'Content-Type' => 'application/pdf',
        ]);
    }

    public function batchZip(Batch $batch): StreamedResponse
    {
        $disk = $this->disk();

        abort_unless($batch->zip_path && $disk->exists($batch->zip_path), 404);

        return $disk->download($batch->zip_path, 'certificates-batch-'.$batch->getKey().'.zip');
    }

    public function batchErrors(Batch $batch): StreamedResponse
    {
        $disk = $this->disk();

        abort_unless($batch->error_report_path && $disk->exists($batch->error_report_path), 404);

        return $disk->download($batch->error_report_path, 'errors-batch-'.$batch->getKey().'.xlsx');
    }

    public function batchSource(Batch $batch): StreamedResponse
    {
        $disk = $this->disk();

        abort_unless($batch->source_path && $disk->exists($batch->source_path), 404);

        return $disk->download($batch->source_path, $batch->source_original_name ?: 'participants.xlsx');
    }

    private function disk(): Filesystem
    {
        return Storage::disk(config('certificates.disk', 'certificates'));
    }
}
