<?php

namespace App\Jobs;

use App\Mail\CertificateMail;
use App\Models\Setting;
use App\Services\WhatsAppService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use PhpOffice\PhpWord\TemplateProcessor;
use Rap2hpoutre\FastExcel\FastExcel;
use Symfony\Component\Process\Exception\ProcessFailedException;
use Symfony\Component\Process\Process;

class GenerateCertificates implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    protected $sheet;
    /**
     * Create a new job instance.
     *
     * @return void
     */
    public function __construct($sheet)
    {
        ini_set('memory_limit', '-1');
        set_time_limit(0);
        ini_set('max_execution_time', 0);

        $this->sheet = $sheet;
    }

    /**
     * Execute the job.
     *
     * @return void
     */
    public function handle()
    {

        // Define the chunk size.
        $chunkSize = 50; // You can adjust this based on your server's capacity.

        // Read and chunk the Excel sheet.
        (new FastExcel)->import($this->sheet, function ($line) {
            if (!filter_var($line['Email'], FILTER_VALIDATE_EMAIL)) {
                    return;
            }
            $result = $this->exportPdf($line);
            // If the email was not sent successfully, log it and notify the admin.
            if (!$result["mail"]) {
                $logMessage = 'Email not sent to ' . $line['Email'] . ' for ' . $line['Name'] . ' with title ' . $line['Title'];
                Log::error($logMessage);

                // Notify the admin by sending an email
                $adminEmail = config('mail.admin_email'); // You should define this in your .env or config file
                Mail::raw($logMessage, function ($message) use ($adminEmail) {
                    $message->to($adminEmail)
                        ->subject('Certificate Email Sending Failed');
                });
            }

            if (isset($result["whatsapp"]['error'])) {
                $logMessage = 'WhatsApp message not sent to ' . $line['Phone'] . ' for ' . $line['Name'] . ' with title ' . $line['Title'];
                Log::error($logMessage);

                // Notify the admin by sending an email
                $adminEmail = config('mail.admin_email'); // You should define this in your .env or config file
                Mail::raw($logMessage, function ($message) use ($adminEmail) {
                    $message->to($adminEmail)
                        ->subject('Certificate WhatsApp Message Sending Failed');
                });
            }
        }, $chunkSize);

    }
    public function exportPdf($line)
    {
        // Load and replace placeholders in the DOCX
        $templateProcessor = new TemplateProcessor(public_path(Setting::first()->template_name));
        // make the file rtl for arabic
        $templateProcessor->setValue('{Name}', Str::limit(trim($line['Name']), 23));
        $templateProcessor->setValue('{Title}', trim($line['Title']));
        // save the file
        // new file name
        $time = Carbon::now()->format('i');
        $new_file_name = "certificate".$time.rand(1, 1000);
        // Save as a new file
        $newFilePath = public_path('pdf-docs/'.$new_file_name.'.docx');
        $templateProcessor->saveAs($newFilePath);

        $outputDir = public_path('pdf-docs/');
        // PDF file path
        $pdfPath = public_path('pdf-docs/'.$new_file_name.'.pdf');


        // Check if LibreOffice is installed
        $processCheck = new Process(['which', 'libreoffice']);
        $processCheck->run();

        if (!$processCheck->isSuccessful()) {
            // Handle the error appropriately
            return response()->json(['error' => 'LibreOffice is not installed on the server.'], 500);
        }

        $path = $newFilePath;
        // Prepare the command
        $command = "libreoffice --headless --convert-to pdf:writer_web_pdf_Export --outdir {$outputDir} {$path}";

        // Run the command
        $process = Process::fromShellCommandline($command);
        $process->run();

        // Executes after the command finishes
        if (!$process->isSuccessful()) {
            // Handle the error if the conversion fails
            throw new ProcessFailedException($process);
        }
        // Generate the public URL for the certificate
        $pdfUrl = asset('pdf-docs/'.$new_file_name.'.pdf');

       $mail =  Mail::to($line['Email'])->send(new CertificateMail($pdfPath, $line['Name'], $line['Title'], $line['Email']));
        $message = WhatsAppService::sendMessage($line['Phone'], 'Hello, ' . $line['Name'] . '! Your certificate is ready.', $pdfUrl);

        return ['mail' => $mail, 'whatsapp' => $message];
    }
}
