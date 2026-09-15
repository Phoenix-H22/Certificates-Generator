<?php

namespace App\Mail;

use App\Certificates\Delivery\MessageTemplate;
use App\Models\Certificate;
use App\Models\OrganisationSettings;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Attachment;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/** The certificate itself, sent to the recipient with the PDF attached. */
class CertificateMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(public Certificate $certificate) {}

    public function envelope(): Envelope
    {
        $settings = OrganisationSettings::current();

        return new Envelope(
            subject: MessageTemplate::render($settings->email_subject, $this->certificate),
        );
    }

    public function content(): Content
    {
        $settings = OrganisationSettings::current();

        return new Content(
            view: 'emails.certificate',
            with: [
                'body' => MessageTemplate::render($settings->email_body, $this->certificate),
                'organisation' => $settings->name_ar,
                'verifyUrl' => $this->certificate->verifyUrl(),
                'downloadUrl' => MessageTemplate::downloadUrl($this->certificate),
                'code' => $this->certificate->code,
            ],
        );
    }

    /** @return list<Attachment> */
    public function attachments(): array
    {
        if (! $this->certificate->pdf_path) {
            return [];
        }

        return [
            Attachment::fromStorageDisk(config('certificates.disk', 'certificates'), $this->certificate->pdf_path)
                ->as('certificate-'.$this->certificate->code.'.pdf')
                ->withMime('application/pdf'),
        ];
    }
}
