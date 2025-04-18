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
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use PhpOffice\PhpWord\TemplateProcessor;
use Rap2hpoutre\FastExcel\FastExcel;
use Symfony\Component\Process\Exception\ProcessFailedException;
use Symfony\Component\Process\Process;
use Throwable;

class GenerateCertificates implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /** Absolute path to the uploaded sheet inside /public */
    protected string $sheetPath;

    /** Row‑level errors collected for the admin report */
    protected array  $errors = [];

    public int $timeout = 0;
    public int $tries   = 1;

    public function __construct(string $sheetPath)
    {
        @ini_set('memory_limit', '-1');
        @set_time_limit(0);

        $this->sheetPath = $sheetPath;   // already a public‑path string
    }

    public function handle(): void
    {
        $rows = (new FastExcel())->import($this->sheetPath);

        $rows->chunk(50)->each(function ($chunk) {
            foreach ($chunk as $row) {
                try {
                    $this->processRow($row);
                } catch (Throwable $e) {
                    $this->errors[] = [
                        'Name'  => $row['Name']  ?? '',
                        'Email' => $row['Email'] ?? '',
                        'Phone' => $row['Phone'] ?? '',
                        'Error' => $e->getMessage(),
                    ];
                    Log::error('[CertificateJob] '.$e->getMessage());
                }
            }
        });

        $this->sendAdminReport();
        // File::delete($this->sheetPath);  // uncomment if you want it gone after run
    }

    /* ------------------------------------------------------------------ */

    private function processRow(array $line): void
    {
        validator($line, [
            'Name'  => ['required','string'],
            'Title' => ['required','string'],
            'Email' => ['required','email:filter,rfc,dns'],
            'Phone' => ['nullable','phone:AUTO,E164'],
        ])->validate();

        /* ---------------- working dirs under /public ------------------ */
        $jobDir = 'certificates/'.Str::uuid();               // relative to /public
        File::ensureDirectoryExists(public_path($jobDir));

        /* ---------------- template ------------------ */
        $templatePath = Setting::first()->template_name;
        if (!file_exists(public_path($templatePath))) {
            throw new Exception("Template file not found: ".$templatePath);
        }

        $processor = new TemplateProcessor(public_path($templatePath));
        $processor->setValue('{Name}',  Str::limit(trim($line['Name']), 23));
        $processor->setValue('{Title}', trim($line['Title']));

        $base      = 'cert_'.now()->format('His').rand(100,999);
        $docxPath  = public_path("{$jobDir}/{$base}.docx");
        $pdfPath   = public_path("{$jobDir}/{$base}.pdf");

        $processor->saveAs($docxPath);

        /* ---------------- DOCX → PDF ---------------- */
        $this->convertToPdf($docxPath, dirname($pdfPath));

        /* ---------------- e‑mail -------------------- */
        Mail::to($line['Email'])
            ->send(new CertificateMail($pdfPath, $line['Name'], $line['Title'], $line['Email']));

        /* ---------------- WhatsApp ------------------ */
        $resp = WhatsAppService::sendMessage(
            $line['Phone'],
            <<<MSG
معالي الاستاذ / {$line['Name']}

تحية واحتراما وبعد

يسعدنا في المركز الاقليمي لتعليم الكبار اسفك مشاركة معاليكم في حضور ندوتنا
يشرفنا ارسال شهادة الحضور

مدير المركز
د / محمد عبداالوارث القاضي
MSG,
            asset(str_replace(public_path('/'), '', $pdfPath))   // public URL
        );

        if (!($resp['success'] ?? false)) {
            throw new \RuntimeException('WhatsApp failed: '.($resp['error'] ?? 'unknown error'));
        }

        // File::deleteDirectory(public_path($jobDir)); // tidy per‑row dir if you like
    }

    private function convertToPdf(string $docxPath, string $outputDir): void
    {
        static $lo = null;
        if ($lo === null) {
            $probe = new Process(['which', 'libreoffice']); $probe->run();
            if (!$probe->isSuccessful()) {
                throw new \RuntimeException('LibreOffice not installed.');
            }
            $lo = trim($probe->getOutput());
        }

        $cmd = "{$lo} --headless --convert-to pdf:writer_web_pdf_Export ".
            "--outdir ".escapeshellarg($outputDir).' '.escapeshellarg($docxPath);

        $p = Process::fromShellCommandline($cmd); $p->run();
        if (!$p->isSuccessful()) {
            throw new ProcessFailedException($p);
        }
    }

    private function sendAdminReport(): void
    {
        if (empty($this->errors)) return;

        $reportDir = public_path('error_reports');
        File::ensureDirectoryExists($reportDir);

        $fileName = Str::uuid().'.xlsx';
        $filePath = "{$reportDir}/{$fileName}";

        (new FastExcel(collect($this->errors)))->export($filePath);

        Mail::to(config('mail.admin_email'))
            ->send(new AdminErrorReportMail($filePath, count($this->errors)));

        // File::delete($filePath);  // remove if you don't want to keep past reports
    }
}
