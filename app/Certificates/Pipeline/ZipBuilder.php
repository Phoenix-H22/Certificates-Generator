<?php

namespace App\Certificates\Pipeline;

use App\Models\Batch;
use App\Models\Certificate;
use Illuminate\Support\Facades\Storage;
use RuntimeException;
use ZipArchive;

/** Bundles every rendered PDF of a batch into one ZIP on the certificates disk. */
final class ZipBuilder
{
    /** @return string|null path on the certificates disk, null when nothing was rendered */
    public function build(Batch $batch): ?string
    {
        $disk = Storage::disk(config('certificates.disk', 'certificates'));
        $relative = $batch->directory().'/certificates-'.$batch->getKey().'.zip';
        $absolute = $disk->path($relative);

        $disk->makeDirectory($batch->directory());

        if (is_file($absolute)) {
            unlink($absolute);
        }

        $zip = new ZipArchive;

        if ($zip->open($absolute, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
            throw new RuntimeException("Cannot create ZIP at {$absolute}");
        }

        $count = 0;

        $batch->certificates()
            ->rendered()
            ->orderBy('row_number')
            ->lazyById(200)
            ->each(function (Certificate $certificate) use ($zip, $disk, &$count) {
                if (! $certificate->pdf_path || ! $disk->exists($certificate->pdf_path)) {
                    return;
                }

                $zip->addFile($disk->path($certificate->pdf_path), $certificate->downloadName(), 0, 0, ZipArchive::FL_ENC_UTF_8);
                $count++;
            });

        $zip->close();

        if ($count === 0) {
            @unlink($absolute);

            return null;
        }

        return $relative;
    }
}
