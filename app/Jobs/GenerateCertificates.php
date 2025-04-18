<?php

namespace App\Jobs;

use App\Mail\AdminErrorReportMail;
use App\Mail\CertificateMail;
use App\Models\Setting;
use App\Services\WhatsAppService;
use Exception;
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
use Throwable;

class GenerateCertificates implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /** Absolute storage path to the uploaded sheet */
    protected string $sheetPath;

    /** Row‑level errors that will end up in the admin Excel */
    protected array  $errors = [];

    public int $timeout = 0;       // let it run as long as necessary
    public int $tries   = 1;       // we’ll handle row‑level retries ourselves

    public function __construct(string $sheetPath)
    {
        // keep the old “no limits” behavior
        @ini_set('memory_limit', '-1');
        @set_time_limit(0);
        $this->sheetPath = $sheetPath;
    }

    /**
     * Main entry
     */
    public function handle(): void
    {
        // Lazy import; FastExcel + chunk keeps memory low
        $rows = (new FastExcel())->import(Storage::path($this->sheetPath));

        $rows->chunk(50)->each(function ($chunk) {
            foreach ($chunk as $row) {
                try {
                    $this->processRow($row);
                } catch (Throwable $e) {
                    // collect for the final sheet
                    $this->errors[] = [
                        'Name'  => $row['Name']  ?? '',
                        'Email' => $row['Email'] ?? '',
                        'Phone' => $row['Phone'] ?? '',
                        'Error' => $e->getMessage(),
                    ];
                    // still log for quick inspection
                    Log::error('[CertificateJob] '.$e->getMessage());
                }
            }
        });

        $this->sendAdminReport();
        //Storage::delete($this->sheetPath);        // uploaded XLSX no longer needed
    }

    /* -------------------------------------------------------------------- */
    /*                          helpers                                     */
    /* -------------------------------------------------------------------- */

    /**
     * Validate + generate template + send mail + send WhatsApp.
     * Any failure throws → caught in handle().
     */
    private function processRow(array $line): void
    {
        // 1. validate
        validator($line, [
            'Name'  => ['required','string'],
            'Title' => ['required','string'],
            'Email' => ['required','email:filter,rfc,dns'],
            'Phone' => ['nullable','phone:AUTO,E164'],
        ])->validate();

        // 2. prepare directories
        $jobDir = 'certificates/'.Str::uuid();          // unique per row
        Storage::makeDirectory($jobDir);

        // 3. fill DOCX template
        $templatePath = Setting::first()->template_name;
        if (!file_exists(public_path($templatePath))) {
            throw new Exception("Template file not found: " . $templatePath);
        }

        $processor = new TemplateProcessor(public_path($templatePath));
        $processor->setValue('{Name}',  Str::limit(trim($line['Name']), 23));
        $processor->setValue('{Title}', trim($line['Title']));

        $basename     = 'cert_'.now()->format('His').rand(100,999);
        $docxFilename = "{$jobDir}/{$basename}.docx";
        $pdfFilename  = "{$jobDir}/{$basename}.pdf";

        $processor->saveAs(Storage::path($docxFilename));

        // 4. convert → PDF
        $this->convertToPdf(Storage::path($docxFilename), dirname(Storage::path($pdfFilename)));

        // 5. send e‑mail
        Mail::to($line['Email'])
            ->send(new CertificateMail(Storage::path($pdfFilename), $line['Name'], $line['Title'], $line['Email']));

        // 6. WhatsApp
        $whatsResp = WhatsAppService::sendMessage(
            $line['Phone'],
            <<<MSG
معالي الاستاذ / {$line['Name']}

تحية واحتراما وبعد

يسعدنا في المركز الاقليمي لتعليم الكبار اسفك مشاركة معاليكم في حضور ندوتنا
يشرفنا ارسال شهادة الحضور

مدير المركز
د / محمد عبداالوارث القاضي
MSG,
            Storage::url($pdfFilename)     // or asset() if you prefer
        );

        if (!($whatsResp['success'] ?? false)) {
            throw new \RuntimeException(
                'WhatsApp failed: '.($whatsResp['error'] ?? 'unknown error'. Storage::url($pdfFilename) . "File name : " . $pdfFilename)
            );
        }

        // 7. tidy row directory
        //Storage::deleteDirectory($jobDir);
    }

    /**
     * LibreOffice CLI conversion
     */
    private function convertToPdf(string $docxPath, string $outputDir): void
    {
        // detect libreoffice once – cheap
        static $loBinary = null;
        if ($loBinary === null) {
            $check = new Process(['which', 'libreoffice']);
            $check->run();
            if (!$check->isSuccessful()) {
                throw new \RuntimeException('LibreOffice not installed.');
            }
            $loBinary = trim($check->getOutput());
        }

        $cmd = "{$loBinary} --headless --convert-to pdf:writer_web_pdf_Export ".
            "--outdir ".escapeshellarg($outputDir).' '.escapeshellarg($docxPath);

        $proc = Process::fromShellCommandline($cmd);
        $proc->run();

        if (!$proc->isSuccessful()) {
            throw new ProcessFailedException($proc);
        }
    }

    /**
     * One consolidated spreadsheet to the admin
     */
    private function sendAdminReport(): void
    {
        if (empty($this->errors)) {
            return;
        }

        $path = 'error_reports/'.Str::uuid().'.xlsx';
        (new FastExcel(collect($this->errors)))->export(Storage::path($path));

        Mail::to(config('mail.admin_email'))
            ->send(new AdminErrorReportMail($path, count($this->errors)));

        Storage::delete($path);   // optional
    }
}
