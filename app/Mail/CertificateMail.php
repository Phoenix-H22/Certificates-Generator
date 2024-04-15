<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
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

    public function build()
    {
        return $this->subject('شهادة حضور')
            ->view('emails.cert')
            ->with([
                'name' => $this->name,
                'title' => $this->title
            ])
            ->attach($this->pdfPath, [
                'as' => 'certificate.pdf',
                'mime' => 'application/pdf',
            ]);
    }
}
