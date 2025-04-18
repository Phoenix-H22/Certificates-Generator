<?php

namespace App\Jobs;

use App\Mail\CertificateMail;
use App\Models\Setting;
use App\Services\WhatsAppService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use PhpOffice\PhpWord\TemplateProcessor;
use Rap2hpoutre\FastExcel\FastExcel;
use Symfony\Component\Process\Exception\ProcessFailedException;
use Symfony\Component\Process\Process;
use Exception;

class GenerateCertificates implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

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
        try {
            // Define the chunk size.
            $chunkSize = 50; // You can adjust this based on your server's capacity.

            // Read and chunk the Excel sheet.
            (new FastExcel())->import($this->sheet, function ($line) {
                try {
                    if (!filter_var($line['Email'], FILTER_VALIDATE_EMAIL)) {
                        throw new Exception("Invalid email format for " . $line['Email']);
                    }

                    $result = $this->exportPdf($line);

                    if (!$result["mail"]) {
                        $logMessage = 'Email not sent to ' . $line['Email'] . ' for ' . $line['Name'] . ' with title ' . $line['Title'];
                        Log::error($logMessage);
                        $this->notifyAdmin($logMessage);
                    }

                    if (isset($result["whatsapp"]['error'])) {
                        $logMessage = 'WhatsApp message not sent to ' . $line['Phone'] . ' for ' . $line['Name'] . ' with title ' . $line['Title'];
                        Log::error($logMessage);
                        $this->notifyAdmin($logMessage);
                    }
                } catch (Exception $e) {
                    Log::error("Error processing row: " . $e->getMessage());
                    $this->notifyAdmin("Error processing row: " . $e->getMessage());
                }
            }, $chunkSize);
        } catch (Exception $e) {
            Log::error("Job Failed: " . $e->getMessage());
            $this->notifyAdmin("Job Failed: " . $e->getMessage());
        }
    }

    public function exportPdf($line)
    {
        try {
            // Load and replace placeholders in the DOCX
            $templatePath = Setting::first()->template_name;
            if (!file_exists(public_path($templatePath))) {
                throw new Exception("Template file not found: " . $templatePath);
            }

            $templateProcessor = new TemplateProcessor(public_path($templatePath));

            // Fill template
            $templateProcessor->setValue('{Name}', Str::limit(trim($line['Name']), 23));
            $templateProcessor->setValue('{Title}', trim($line['Title']));

            // Generate file names
            $time = Carbon::now()->format('i');
            $newFileName = "certificate" . $time . rand(1, 1000);
            $newFilePath = public_path('pdf-docs/' . $newFileName . '.docx');
            $templateProcessor->saveAs($newFilePath);

            // Check if LibreOffice is installed
            $processCheck = new Process(['which', 'libreoffice']);
            $processCheck->run();
            if (!$processCheck->isSuccessful()) {
                throw new Exception("LibreOffice is not installed on the server.");
            }

            // Convert to PDF
            $outputDir = public_path('pdf-docs/');
            $pdfPath = public_path('pdf-docs/' . $newFileName . '.pdf');
            $command = "libreoffice --headless --convert-to pdf:writer_web_pdf_Export --outdir {$outputDir} {$newFilePath}";

            $process = Process::fromShellCommandline($command);
            $process->run();
            if (!$process->isSuccessful()) {
                throw new ProcessFailedException($process);
            }

            // Send Email
            try {
                Mail::to($line['Email'])->send(new CertificateMail($pdfPath, $line['Name'], $line['Title'], $line['Email']));
                $mailSent = true;
            } catch (Exception $e) {
                Log::error("Failed to send email to " . $line['Email'] . ": " . $e->getMessage());
                $this->notifyAdmin("Failed to send email to " . $line['Email']);
                $mailSent = false;
            }


            // Send WhatsApp message
            $message = WhatsAppService::sendMessage(
                $line['Phone'],
                <<<MSG
معالي الاستاذ / {$line['Name']}

تحية واحتراما وبعد

يسعدنا في المركز الاقليمي لتعليم الكبار اسفك مشاركة معاليكم في حضور ندوتنا
يشرفنا ارسال شهادة الحضور

مدير المركز
د / محمد عبداالوارث القاضي
MSG,
                asset('pdf-docs/' . $newFileName . '.pdf')
            );


            // Check if sending was successful
            if (!$message['success']) {
                $errorMsg = "Failed to send WhatsApp message to " . $line['Phone'] . ": " . $message['error'];

                // Log the error
                Log::error($errorMsg);

                // Notify the admin
                $this->notifyAdmin($errorMsg);

                // Store the error message in the response array
                $message = ['error' => $message['error']];
            } else {
                // Log success
                Log::info("WhatsApp message successfully sent to " . $line['Phone']);
            }


            return ['mail' => $mailSent, 'whatsapp' => $message];
        } catch (Exception $e) {
            Log::error("Failed to generate PDF for " . $line['Name'] . ": " . $e->getMessage());
            $this->notifyAdmin("Failed to generate PDF for " . $line['Name']);
            return ['mail' => false, 'whatsapp' => ['error' => 'PDF Generation Failed']];
        }
    }

    /**
     * Notify admin via email
     */
    private function notifyAdmin($message)
    {
        try {
            $adminEmail = config('mail.admin_email');
            if ($adminEmail) {
                Mail::raw($message, function ($mail) use ($adminEmail) {
                    $mail->to($adminEmail)->subject('Certificate Processing Error');
                });
            }
        } catch (Exception $e) {
            Log::error("Failed to notify admin: " . $e->getMessage());
        }
    }
}