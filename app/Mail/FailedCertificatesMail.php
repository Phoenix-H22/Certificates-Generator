<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Str;

class FailedCertificatesMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public array $failedCertificates,
        public int   $count
    ) {}

    public function build()
    {
        $mail = $this->subject("Failed Email Certificates - {$this->count} certificate(s) need manual sending")
            ->markdown('emails.failed_certificates', [
                'count' => $this->count,
                'failedCertificates' => $this->failedCertificates
            ]);

        // Attach all failed certificate PDFs
        foreach ($this->failedCertificates as $cert) {
            if (isset($cert['pdfPath']) && file_exists($cert['pdfPath'])) {
                $mail->attach($cert['pdfPath'], [
                    'as' => $this->sanitizeFilename($cert['Name'] ?? 'certificate').'.pdf',
                    'mime' => 'application/pdf',
                ]);
            }
        }

        return $mail;
    }

    private function sanitizeFilename(string $name): string
    {
        // Remove special characters and replace spaces with underscores
        $name = preg_replace('/[^a-zA-Z0-9_-]/', '_', $name);
        return Str::limit($name, 50, '');
    }
}

