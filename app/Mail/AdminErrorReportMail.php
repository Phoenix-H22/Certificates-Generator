<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Str;

class AdminErrorReportMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public int $totalRows,
        public int $successCount,
        public int $errorCount,
        public ?string $sheetPath = null,
        public array $failedCertificates = [],
    ) {}

    public function build()
    {
        $from = config('mail.mailers.smtp.username') ?: config('mail.from.address');
        $failedPdfCount = count($this->failedCertificates);

        $subject = $this->errorCount > 0
            ? "Certificate job finished: {$this->successCount} sent, {$this->errorCount} failed"
            : "Certificate job finished: all {$this->successCount} certificates sent";

        $mail = $this->from($from, config('mail.from.name'))
            ->subject($subject)
            ->markdown('emails.admin_error_report', [
                'totalRows' => $this->totalRows,
                'successCount' => $this->successCount,
                'errorCount' => $this->errorCount,
                'failedPdfCount' => $failedPdfCount,
            ]);

        if ($this->sheetPath && file_exists($this->sheetPath)) {
            $mail->attachData(
                file_get_contents($this->sheetPath),
                'certificate_errors.xlsx',
                ['mime' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet']
            );
        }

        foreach ($this->failedCertificates as $cert) {
            $pdfPath = $cert['pdfPath'] ?? null;
            if ($pdfPath && file_exists($pdfPath)) {
                $row = $cert['Row'] ?? 'unknown';
                $name = preg_replace('/[^a-zA-Z0-9_-]/', '_', $cert['Name'] ?? 'certificate');
                $mail->attachData(
                    file_get_contents($pdfPath),
                    'failed_row_'.$row.'_'.Str::limit($name, 30, '').'.pdf',
                    ['mime' => 'application/pdf']
                );
            }
        }

        return $mail;
    }
}
