<?php

namespace App\Jobs;

use App\Mail\AdminErrorReportMail;
use App\Mail\CertificateMail;
use App\Mail\FailedCertificatesMail;
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

    /** Failed email certificates (with PDF paths) for manual sending */
    protected array  $failedEmailCertificates = [];

    /** Job directory for this execution */
    protected string $jobDir;

    public int $timeout = 3600; // 1 hour
    public int $tries   = 3;

    public function __construct(string $sheetPath)
    {
        // Set reasonable resource limits (configurable via env)
        $memoryLimit = env('CERTIFICATE_JOB_MEMORY_LIMIT', '512M');
        $timeLimit = env('CERTIFICATE_JOB_TIME_LIMIT', 3600);

        @ini_set('memory_limit', $memoryLimit);
        @set_time_limit($timeLimit);

        $this->sheetPath = $sheetPath;   // already a public‑path string
    }

    public function handle(): void
    {
        $startTime = microtime(true);
        $rowCount = 0;
        $processedCount = 0;

        Log::info('[CertificateJob] Starting certificate generation', [
            'sheet_path' => $this->sheetPath,
            'memory_limit' => ini_get('memory_limit'),
            'time_limit' => ini_get('max_execution_time'),
        ]);

        try {
            // Validate Excel file early
            $this->validateExcelFile();

            // Check disk space before processing
            $this->checkDiskSpace();

            // Validate template and setting once
            $templatePath = $this->validateTemplateAndSetting();

            // Create single job directory
            $this->jobDir = 'certificates/'.now()->format('Y-m-d').'/'.Str::uuid();
            try {
                File::ensureDirectoryExists(public_path($this->jobDir));
            } catch (Throwable $e) {
                Log::error('[CertificateJob] Failed to create job directory', [
                    'directory' => $this->jobDir,
                    'error' => $e->getMessage(),
                ]);
                throw new Exception("Failed to create working directory: ".$e->getMessage());
            }

            // Import Excel file
            try {
                $rows = (new FastExcel())->import($this->sheetPath);
            } catch (Throwable $e) {
                Log::error('[CertificateJob] Failed to import Excel file', [
                    'error' => $e->getMessage(),
                    'file' => $this->sheetPath,
                ]);
                throw new Exception("Failed to import Excel file. File may be corrupted or invalid format: ".$e->getMessage());
            }

            // Validate required columns exist
            $this->validateExcelColumns($rows);

            $rowCount = $rows->count();
            Log::info('[CertificateJob] Excel file loaded', ['row_count' => $rowCount]);

            // Process rows in chunks
            $rows->chunk(50)->each(function ($chunk) use ($templatePath, &$processedCount) {
                foreach ($chunk as $rowIndex => $row) {
                    $processedCount++;
                    try {
                        $this->processRow($row, $templatePath, $this->jobDir, $processedCount);
                    } catch (Throwable $e) {
                        $this->errors[] = [
                            'Row' => $processedCount,
                            'Name'  => $row['Name']  ?? '',
                            'Title' => $row['Title'] ?? '',
                            'Email' => $row['Email'] ?? '',
                            'Phone' => $row['Phone'] ?? '',
                            'Error' => $e->getMessage(),
                            'ErrorType' => $this->categorizeError($e),
                            'Timestamp' => now()->toDateTimeString(),
                        ];
                        Log::error('[CertificateJob] Row processing failed', [
                            'row' => $processedCount,
                            'error' => $e->getMessage(),
                            'error_type' => $this->categorizeError($e),
                        ]);
                    }
                }
            });

            $endTime = microtime(true);
            $duration = round($endTime - $startTime, 2);

            Log::info('[CertificateJob] Certificate generation completed', [
                'total_rows' => $rowCount,
                'processed' => $processedCount,
                'errors' => count($this->errors),
                'failed_emails' => count($this->failedEmailCertificates),
                'duration_seconds' => $duration,
                'rows_per_second' => $rowCount > 0 ? round($processedCount / $duration, 2) : 0,
            ]);

            // Send failed email certificates to admin for manual sending
            $this->sendFailedCertificatesToAdmin();

            // Send general error report
            $this->sendAdminReport();

            // Optional: Clean up uploaded Excel file
            if (env('CLEANUP_UPLOADED_SHEETS', false)) {
                try {
                    File::delete($this->sheetPath);
                    Log::info('[CertificateJob] Cleaned up uploaded sheet', ['path' => $this->sheetPath]);
                } catch (Throwable $e) {
                    Log::warning('[CertificateJob] Failed to cleanup uploaded sheet', [
                        'path' => $this->sheetPath,
                        'error' => $e->getMessage(),
                    ]);
                }
            }

        } catch (Throwable $e) {
            Log::error('[CertificateJob] Fatal error in job execution', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
            throw $e; // Re-throw to let queue handle retry
        }
    }
    private function normalizePhone(?string $raw): string
    {
        if (!$raw) return '';

        // strip everything except digits and plus
        $clean = preg_replace('/[^\d+]/', '', $raw);

        // 010xxxxxxxx → +2010xxxxxxxx
        if (preg_match('/^01[0125]\d{8}$/', $clean)) {
            return '+20'.substr($clean, 1);
        }

// 10xxxxxxxx → +2010xxxxxxxx
        if (preg_match('/^1[0125]\d{8}$/', $clean)) {
            return '+20'.$clean;
        }
        // anything else – return as‑is; the validator will decide
        return $raw;
    }
    /* ------------------------------------------------------------------ */

    private function processRow(array $line, string $templatePath, string $jobDir, int $rowNumber): void
    {
        // Normalize phone number
        $line['Phone'] = $this->normalizePhone($line['Phone'] ?? '');

        // Validate row data
        validator($line, [
            'Name'  => ['required','string'],
            'Title' => ['required','string'],
            'Email' => ['required','email:filter,rfc,dns'],
            'Phone' => ['nullable','phone:AUTO,E164'],
        ])->validate();

        // Verify template still exists (edge case: deleted mid-execution)
        $fullTemplatePath = public_path($templatePath);
        if (!file_exists($fullTemplatePath) || !is_readable($fullTemplatePath)) {
            throw new Exception("Template file not accessible: {$templatePath}");
        }

        // Generate unique filename
        $base      = 'cert_'.now()->format('His').'_'.Str::random(8);
        $docxPath  = public_path("{$jobDir}/{$base}.docx");
        $pdfPath   = public_path("{$jobDir}/{$base}.pdf");

        // Process template
        try {
            $processor = new TemplateProcessor($fullTemplatePath);
            $processor->setValue('{Name}',  Str::limit(trim($line['Name']), 23));
            $processor->setValue('{Title}', trim($line['Title']));
            $processor->saveAs($docxPath);
        } catch (Throwable $e) {
            throw new Exception("Failed to process template: ".$e->getMessage());
        }

        // Convert DOCX to PDF
        try {
            $this->convertToPdf($docxPath, dirname($pdfPath));
        } catch (Throwable $e) {
            // Clean up DOCX if PDF conversion fails
            $this->cleanupFile($docxPath, 'DOCX');
            throw new Exception("PDF conversion failed: ".$e->getMessage());
        }

        // Verify PDF was created
        if (!file_exists($pdfPath)) {
            $this->cleanupFile($docxPath, 'DOCX');
            throw new Exception("PDF file was not created after conversion");
        }

        // Clean up DOCX after successful PDF conversion
        $this->cleanupFile($docxPath, 'DOCX');

        // Queue email (async) - don't fail row if email fails
        try {
            Mail::to($line['Email'])
                ->queue(new CertificateMail($pdfPath, $line['Name'], $line['Title'], $line['Email']));

            Log::info('[CertificateJob] Email queued successfully', [
                'row' => $rowNumber,
                'email' => $line['Email'],
            ]);
        } catch (Throwable $e) {
            // Log but don't throw - email failure shouldn't fail the row
            Log::error('[CertificateJob] Failed to queue email', [
                'row' => $rowNumber,
                'email' => $line['Email'],
                'error' => $e->getMessage(),
            ]);

            // Store failed certificate info for manual sending to admin
            $this->failedEmailCertificates[] = [
                'Row' => $rowNumber,
                'Name'  => $line['Name'] ?? '',
                'Title' => $line['Title'] ?? '',
                'Email' => $line['Email'] ?? '',
                'Phone' => $line['Phone'] ?? '',
                'Error' => 'Email queuing failed: '.$e->getMessage(),
                'ErrorType' => 'email',
                'Timestamp' => now()->toDateTimeString(),
                'pdfPath' => $pdfPath, // Keep PDF path for attachment
            ];

            // Also add to general errors array
            $this->errors[] = [
                'Row' => $rowNumber,
                'Name'  => $line['Name'] ?? '',
                'Title' => $line['Title'] ?? '',
                'Email' => $line['Email'] ?? '',
                'Phone' => $line['Phone'] ?? '',
                'Error' => 'Email queuing failed: '.$e->getMessage(),
                'ErrorType' => 'email',
                'Timestamp' => now()->toDateTimeString(),
            ];
        }

        // Note: PDF is needed for email attachment
        // For failed emails, PDF is kept and sent to admin for manual distribution
        // For successful emails, PDF cleanup can be handled by a scheduled job
        // We don't delete PDFs here to ensure failed email certificates are available

        /* ---------------- WhatsApp ------------------ */
//        $resp = WhatsAppService::sendMessage(
//            $line['Phone'],
//            <<<MSG
//معالي الاستاذ / {$line['Name']}
//
//تحية واحتراما وبعد
//
//يسعدنا في المركز الاقليمي لتعليم الكبار اسفك مشاركة معاليكم في حضور ندوتنا
//يشرفنا ارسال شهادة الحضور
//
//مدير المركز
//د / محمد عبداالوارث القاضي
//MSG,
//            asset(str_replace(public_path('/'), '', $pdfPath))   // public URL
//        );
//
//        if (!($resp['success'] ?? false)) {
//            throw new \RuntimeException('WhatsApp failed: '.($resp['error'] ?? 'unknown error'));
//        }
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
        if (empty($this->errors)) {
            Log::info('[CertificateJob] No errors to report');
            return;
        }

        $reportDir = public_path('error_reports');
        try {
            File::ensureDirectoryExists($reportDir);
        } catch (Throwable $e) {
            Log::error('[CertificateJob] Failed to create error reports directory', [
                'error' => $e->getMessage(),
            ]);
            return;
        }

        $fileName = Str::uuid().'.xlsx';
        $filePath = "{$reportDir}/{$fileName}";

        try {
            (new FastExcel(collect($this->errors)))->export($filePath);
        } catch (Throwable $e) {
            Log::error('[CertificateJob] Failed to export error report', [
                'error' => $e->getMessage(),
            ]);
            return;
        }

        try {
            Mail::to(config('mail.admin_email'))
                ->queue(new AdminErrorReportMail($filePath, count($this->errors)));

            Log::info('[CertificateJob] Admin error report queued', [
                'error_count' => count($this->errors),
                'report_path' => $filePath,
            ]);
        } catch (Throwable $e) {
            Log::error('[CertificateJob] Failed to queue admin error report', [
                'error' => $e->getMessage(),
            ]);
        }

        // Optional: Clean up error report after sending
        if (env('CLEANUP_ERROR_REPORTS', false)) {
            // Schedule cleanup after email is sent
            // For now, keep the report for reference
        }
    }

    /**
     * Send failed email certificates to admin for manual sending
     */
    private function sendFailedCertificatesToAdmin(): void
    {
        if (empty($this->failedEmailCertificates)) {
            Log::info('[CertificateJob] No failed email certificates to send');
            return;
        }

        // Filter out certificates where PDF doesn't exist
        $validCertificates = [];
        foreach ($this->failedEmailCertificates as $cert) {
            if (isset($cert['pdfPath']) && file_exists($cert['pdfPath'])) {
                $validCertificates[] = $cert;
            } else {
                Log::warning('[CertificateJob] Failed certificate PDF not found', [
                    'name' => $cert['Name'] ?? 'Unknown',
                    'email' => $cert['Email'] ?? 'Unknown',
                    'pdf_path' => $cert['pdfPath'] ?? 'Not set',
                ]);
            }
        }

        if (empty($validCertificates)) {
            Log::warning('[CertificateJob] No valid certificate PDFs found for failed emails');
            return;
        }

        try {
            Mail::to(config('mail.admin_email'))
                ->queue(new FailedCertificatesMail($validCertificates, count($validCertificates)));

            Log::info('[CertificateJob] Failed certificates email queued to admin', [
                'count' => count($validCertificates),
                'admin_email' => config('mail.admin_email'),
            ]);
        } catch (Throwable $e) {
            Log::error('[CertificateJob] Failed to queue failed certificates email to admin', [
                'error' => $e->getMessage(),
                'count' => count($validCertificates),
            ]);
        }
    }

    /**
     * Validate Excel file early
     */
    private function validateExcelFile(): void
    {
        if (!file_exists($this->sheetPath)) {
            throw new Exception("Excel file not found at: {$this->sheetPath}");
        }

        if (!is_readable($this->sheetPath)) {
            throw new Exception("Excel file is not readable: {$this->sheetPath}");
        }

        // Check file size (prevent processing extremely large files)
        $fileSize = filesize($this->sheetPath);
        $maxSize = env('MAX_EXCEL_FILE_SIZE', 50 * 1024 * 1024); // 50MB default

        if ($fileSize > $maxSize) {
            throw new Exception("Excel file exceeds maximum size limit: ".round($maxSize / 1024 / 1024, 2)."MB");
        }

        // Validate mime type
        $mimeType = mime_content_type($this->sheetPath);
        $allowedTypes = [
            'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'application/vnd.ms-excel',
        ];

        if (!in_array($mimeType, $allowedTypes)) {
            throw new Exception("Invalid file type. Expected Excel file, got: {$mimeType}");
        }
    }

    /**
     * Validate Excel has required columns
     */
    private function validateExcelColumns($rows): void
    {
        if ($rows->isEmpty()) {
            throw new Exception("Excel file is empty or contains no data");
        }

        // Get first row to check columns
        $firstRow = $rows->first();
        $requiredColumns = ['Name', 'Title', 'Email'];
        $missingColumns = [];

        foreach ($requiredColumns as $column) {
            if (!isset($firstRow[$column])) {
                $missingColumns[] = $column;
            }
        }

        if (!empty($missingColumns)) {
            throw new Exception("Excel file is missing required columns: ".implode(', ', $missingColumns));
        }
    }

    /**
     * Validate template and setting once
     */
    private function validateTemplateAndSetting(): string
    {
        $setting = Setting::first();
        if (!$setting) {
            throw new Exception("No settings found. Please configure the application first.");
        }

        if (!$setting->template_name) {
            throw new Exception("Template not configured in settings. Please upload a template first.");
        }

        $templatePath = $setting->template_name;
        $fullTemplatePath = public_path($templatePath);

        if (!file_exists($fullTemplatePath)) {
            throw new Exception("Template file not found: {$templatePath}");
        }

        if (!is_readable($fullTemplatePath)) {
            throw new Exception("Template file is not readable: {$templatePath}");
        }

        return $templatePath;
    }

    /**
     * Check available disk space
     */
    private function checkDiskSpace(): void
    {
        $publicPath = public_path();
        $freeSpace = disk_free_space($publicPath);
        $requiredSpace = env('MIN_DISK_SPACE_MB', 100) * 1024 * 1024; // 100MB default

        if ($freeSpace === false) {
            Log::warning('[CertificateJob] Could not determine disk space, continuing with caution');
            return;
        }

        if ($freeSpace < $requiredSpace) {
            throw new Exception("Insufficient disk space. Required: ".round($requiredSpace / 1024 / 1024, 2)."MB, Available: ".round($freeSpace / 1024 / 1024, 2)."MB");
        }

        Log::info('[CertificateJob] Disk space check passed', [
            'free_space_mb' => round($freeSpace / 1024 / 1024, 2),
            'required_mb' => round($requiredSpace / 1024 / 1024, 2),
        ]);
    }

    /**
     * Cleanup file with error handling
     */
    private function cleanupFile(string $filePath, string $fileType = 'file'): void
    {
        try {
            if (file_exists($filePath)) {
                File::delete($filePath);
                Log::debug('[CertificateJob] Cleaned up '.$fileType, ['path' => $filePath]);
            }
        } catch (Throwable $e) {
            Log::warning('[CertificateJob] Failed to cleanup '.$fileType, [
                'path' => $filePath,
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Categorize error type for better reporting
     */
    private function categorizeError(Throwable $e): string
    {
        $message = $e->getMessage();
        $class = get_class($e);

        if (str_contains($message, 'validation') || str_contains($message, 'required') || str_contains($message, 'email')) {
            return 'validation';
        }

        if (str_contains($message, 'template') || str_contains($message, 'Template')) {
            return 'template';
        }

        if (str_contains($message, 'PDF') || str_contains($message, 'LibreOffice') || $e instanceof ProcessFailedException) {
            return 'conversion';
        }

        if (str_contains($message, 'email') || str_contains($message, 'mail')) {
            return 'email';
        }

        if (str_contains($message, 'permission') || str_contains($message, 'readable')) {
            return 'permission';
        }

        if (str_contains($message, 'disk') || str_contains($message, 'space')) {
            return 'disk';
        }

        return 'unknown';
    }
}
