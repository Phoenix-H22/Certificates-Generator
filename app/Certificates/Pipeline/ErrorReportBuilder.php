<?php

namespace App\Certificates\Pipeline;

use App\Enums\CertificateStatus;
use App\Enums\DeliveryStatus;
use App\Models\Batch;
use App\Models\Certificate;
use Illuminate\Support\Facades\Storage;
use Rap2hpoutre\FastExcel\FastExcel;

/** Writes the per-row failure spreadsheet (validation / render / delivery). */
final class ErrorReportBuilder
{
    /** @return string|null path on the certificates disk, null when there is nothing to report */
    public function build(Batch $batch): ?string
    {
        $rows = collect();

        $batch->certificates()
            ->where(function ($q) {
                $q->where('status', CertificateStatus::RenderFailed->value)
                    ->orWhere('email_status', DeliveryStatus::Failed->value)
                    ->orWhere('whatsapp_status', DeliveryStatus::Failed->value)
                    ->orWhere('email_status', DeliveryStatus::Skipped->value)
                    ->orWhere('whatsapp_status', DeliveryStatus::Skipped->value);
            })
            ->orderBy('row_number')
            ->lazyById(200)
            ->each(function (Certificate $certificate) use ($rows) {
                foreach ($this->problems($certificate) as [$stage, $error]) {
                    $rows->push([
                        'الصف' => $certificate->row_number,
                        'الاسم' => $certificate->recipient_name,
                        'الصفة' => $certificate->recipient_title,
                        'البريد الإلكتروني' => $certificate->email,
                        'الهاتف' => $certificate->phone,
                        'كود الشهادة' => $certificate->code,
                        'المرحلة' => $stage,
                        'الخطأ' => $error,
                    ]);
                }
            });

        if ($rows->isEmpty()) {
            return null;
        }

        $disk = Storage::disk(config('certificates.disk', 'certificates'));
        $relative = $batch->directory().'/errors-'.$batch->getKey().'.xlsx';
        $disk->makeDirectory($batch->directory());

        (new FastExcel($rows))->export($disk->path($relative));

        return $relative;
    }

    /** @return list<array{0: string, 1: string}> [stage, message] */
    private function problems(Certificate $certificate): array
    {
        $out = [];

        if ($certificate->status === CertificateStatus::RenderFailed) {
            $out[] = [
                str_starts_with((string) $certificate->render_error, 'Row ') ? 'التحقق من البيانات' : 'التوليد',
                (string) $certificate->render_error,
            ];
        }

        if ($certificate->email_status === DeliveryStatus::Failed) {
            $out[] = ['البريد الإلكتروني', (string) $certificate->email_error];
        } elseif ($certificate->email_status === DeliveryStatus::Skipped) {
            $out[] = ['البريد الإلكتروني', 'لا يوجد بريد إلكتروني صالح'];
        }

        if ($certificate->whatsapp_status === DeliveryStatus::Failed) {
            $out[] = ['واتساب', (string) $certificate->whatsapp_error];
        } elseif ($certificate->whatsapp_status === DeliveryStatus::Skipped) {
            $out[] = ['واتساب', 'لا يوجد رقم هاتف صالح'];
        }

        return $out;
    }
}
