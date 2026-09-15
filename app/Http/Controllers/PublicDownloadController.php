<?php

namespace App\Http\Controllers;

use App\Models\Certificate;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

/** Signed, expiring PDF download used in e-mails and WhatsApp messages. */
class PublicDownloadController extends Controller
{
    public function __invoke(Certificate $certificate): StreamedResponse
    {
        $disk = Storage::disk(config('certificates.disk', 'certificates'));

        abort_unless($certificate->isValid() && $disk->exists($certificate->pdf_path), 404);

        return $disk->download($certificate->pdf_path, 'certificate-'.$certificate->code.'.pdf', [
            'Content-Type' => 'application/pdf',
            'X-Robots-Tag' => 'noindex',
        ]);
    }
}
