<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class CertificateMail extends Mailable
{
    use Queueable, SerializesModels;

    public $pdfPath;
    public $name;
    public $title;
    public $email;

    public function __construct($pdfPath, $name, $title, $email)
    {
        $this->pdfPath = $pdfPath;
        $this->name = $name;
        $this->title = $title;
        $this->email = $email;
    }

    public function envelope()
    {
        return new Envelope(
            subject: 'شهادة حضور'
        );
    }

    public function content()
    {
        return new Content(
            view: 'emails.cert',
            with: [
                'name' => $this->name,
                'title' => $this->title
            ]
        );
    }

    public function attachments()
    {
        return [
            [
                'file' => $this->pdfPath,
                'as' => 'شهادة_حضور'. $this->title . '_' . $this->name . '.pdf',
                'mime' => 'application/pdf',
            ]
        ];
    }
}
