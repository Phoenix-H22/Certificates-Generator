<?php

namespace App\Http\Controllers;

use App\Models\Batch;
use App\Models\Certificate;
use Illuminate\Contracts\Filesystem\Filesystem;
use Illuminate\Filesystem\FilesystemAdapter;
use Illuminate\Support\Facades\Storage;
use League\Flysystem\Local\LocalFilesystemAdapter;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
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

    public function batchZip(Batch $batch): BinaryFileResponse|StreamedResponse
    {
        $disk = $this->disk();

        abort_unless($batch->zip_path && $disk->exists($batch->zip_path), 404);

        return $this->download($batch->zip_path, 'certificates-batch-'.$batch->getKey().'.zip');
    }

    public function batchErrors(Batch $batch): BinaryFileResponse|StreamedResponse
    {
        $disk = $this->disk();

        abort_unless($batch->error_report_path && $disk->exists($batch->error_report_path), 404);

        return $this->download($batch->error_report_path, 'errors-batch-'.$batch->getKey().'.xlsx');
    }

    public function batchSource(Batch $batch): BinaryFileResponse|StreamedResponse
    {
        $disk = $this->disk();

        abort_unless($batch->source_path && $disk->exists($batch->source_path), 404);

        return $this->download($batch->source_path, $batch->source_original_name ?: 'participants.xlsx');
    }

    /**
     * Send a file as an attachment a small chunk at a time.
     *
     * A batch ZIP can be hundreds of MB. FilesystemAdapter::download() fpassthru()s the whole file into
     * PHP's output buffer in one write, which ran out of memory on a 328 MB archive. BinaryFileResponse
     * reads and sends it in 16 KB chunks (and supports resumed downloads). Non-local disks keep the stream.
     */
    private function download(string $path, string $name): BinaryFileResponse|StreamedResponse
    {
        $disk = $this->disk();

        if ($disk instanceof FilesystemAdapter && $disk->getAdapter() instanceof LocalFilesystemAdapter) {
            return response()->download($disk->path($path), $name);
        }

        return $disk->download($path, $name);
    }

    private function disk(): Filesystem
    {
        return Storage::disk(config('certificates.disk', 'certificates'));
    }
}
