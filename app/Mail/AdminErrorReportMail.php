<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Storage;

class AdminErrorReportMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public string $sheetPath,
        public int    $count
    ) {}

    public function build()
    {
        return $this->subject("Certificate job finished with {$this->count} errors")
            ->markdown('emails.admin_error_report', [   // <‑‑ pass $count to the view
                'count' => $this->count,
            ])
            ->attach(Storage::path($this->sheetPath), [
                'as'   => 'certificate_errors.xlsx',
                'mime' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            ]);
    }
}
