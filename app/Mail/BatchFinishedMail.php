<?php

namespace App\Mail;

use App\Models\Batch;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Attachment;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/** Admin summary when a batch reaches a terminal state. */
class BatchFinishedMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(public Batch $batch) {}

    public function envelope(): Envelope
    {
        $status = $this->batch->status->label();

        return new Envelope(
            subject: "[شهادات] الدفعة \"{$this->batch->name}\" — {$status}",
        );
    }

    public function content(): Content
    {
        return new Content(
            markdown: 'emails.batch-finished',
            with: [
                'batch' => $this->batch,
                'panelUrl' => url('/admin/batches/'.$this->batch->getKey()),
            ],
        );
    }

    /** @return list<Attachment> */
    public function attachments(): array
    {
        if (! $this->batch->error_report_path) {
            return [];
        }

        return [
            Attachment::fromStorageDisk(config('certificates.disk', 'certificates'), $this->batch->error_report_path)
                ->as('errors-batch-'.$this->batch->getKey().'.xlsx')
                ->withMime('application/vnd.openxmlformats-officedocument.spreadsheetml.sheet'),
        ];
    }
}
