<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class AdminFailedCertificateMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(public array $cert) {}

    public function build()
    {
        $from = config('mail.mailers.smtp.username') ?: config('mail.from.address');
        $row = $this->cert['Row'] ?? '?';
        $name = $this->cert['Name'] ?? 'Unknown';

        $mail = $this->from($from, config('mail.from.name'))
            ->subject("Manual certificate send – row {$row}: {$name}")
            ->markdown('emails.admin_failed_certificate', ['cert' => $this->cert]);

        $pdfPath = $this->cert['pdfPath'] ?? null;
        if ($pdfPath && file_exists($pdfPath)) {
            $mail->attachData(
                file_get_contents($pdfPath),
                "certificate_row_{$row}.pdf",
                ['mime' => 'application/pdf']
            );
        }

        return $mail;
    }
}
