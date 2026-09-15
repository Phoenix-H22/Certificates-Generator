<?php

use App\Certificates\Pipeline\ErrorReportBuilder;
use App\Certificates\Pipeline\ZipBuilder;
use App\Enums\BatchStatus;
use App\Enums\DeliveryStatus;
use App\Jobs\FinalizeBatch;
use App\Mail\BatchFinishedMail;
use App\Models\Batch;
use App\Models\Certificate;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;

beforeEach(function () {
    Storage::fake('certificates');
    Mail::fake();
    config()->set('services.admin_email', 'admin@example.com');
});

it('builds the zip and the error report, recounts and notifies the admin', function () {
    $batch = Batch::factory()->create(['status' => BatchStatus::Rendering, 'deliver_via' => ['email']]);

    $rendered = Certificate::factory()->count(2)->rendered()->emailSent()->create([
        'batch_id' => $batch->id, 'template_id' => $batch->template_id,
    ]);
    $rendered->each(fn (Certificate $c) => Storage::disk('certificates')->put($c->pdf_path, '%PDF-1.7 '.$c->code));

    Certificate::factory()->renderFailed('Row 5: name: required')->create([
        'batch_id' => $batch->id, 'template_id' => $batch->template_id,
    ]);
    Certificate::factory()->rendered()->emailFailed('SMTP 550')->create([
        'batch_id' => $batch->id, 'template_id' => $batch->template_id,
        'pdf_path' => null, // rendered flag without file: must not break the zip
    ]);

    (new FinalizeBatch($batch->id))->handle(app(ZipBuilder::class), app(ErrorReportBuilder::class));

    $batch->refresh();

    expect($batch->status)->toBe(BatchStatus::CompletedWithErrors)
        ->and($batch->total_rows)->toBe(4)
        ->and($batch->rendered_count)->toBe(3)
        ->and($batch->render_failed_count)->toBe(1)
        ->and($batch->email_sent_count)->toBe(2)
        ->and($batch->email_failed_count)->toBe(1)
        ->and($batch->finished_at)->not->toBeNull()
        ->and($batch->zip_path)->not->toBeNull()
        ->and($batch->error_report_path)->not->toBeNull();

    Storage::disk('certificates')->assertExists($batch->zip_path);
    Storage::disk('certificates')->assertExists($batch->error_report_path);

    $zip = new ZipArchive;
    $zip->open(Storage::disk('certificates')->path($batch->zip_path));
    expect($zip->numFiles)->toBe(2);
    $zip->close();

    Mail::assertSent(BatchFinishedMail::class, fn (BatchFinishedMail $m) => $m->hasTo('admin@example.com') && $m->batch->is($batch));
});

it('completes cleanly without a report when nothing failed', function () {
    $batch = Batch::factory()->create(['status' => BatchStatus::Rendering, 'deliver_via' => []]);

    $certificate = Certificate::factory()->rendered()->create(['batch_id' => $batch->id, 'template_id' => $batch->template_id]);
    Storage::disk('certificates')->put($certificate->pdf_path, '%PDF-1.7');

    (new FinalizeBatch($batch->id))->handle(app(ZipBuilder::class), app(ErrorReportBuilder::class));

    $batch->refresh();

    expect($batch->status)->toBe(BatchStatus::Completed)
        ->and($batch->error_report_path)->toBeNull()
        ->and($batch->zip_path)->not->toBeNull();
});

it('leaves cancelled batches cancelled', function () {
    $batch = Batch::factory()->create(['status' => BatchStatus::Cancelled]);

    (new FinalizeBatch($batch->id))->handle(app(ZipBuilder::class), app(ErrorReportBuilder::class));

    expect($batch->fresh()->status)->toBe(BatchStatus::Cancelled);
    Mail::assertNothingSent();
});

it('treats skipped deliveries as reportable but not as failures', function () {
    $batch = Batch::factory()->create(['status' => BatchStatus::Rendering, 'deliver_via' => ['email']]);

    $certificate = Certificate::factory()->rendered()->create([
        'batch_id' => $batch->id, 'template_id' => $batch->template_id,
        'email' => null, 'email_status' => DeliveryStatus::Skipped,
    ]);
    Storage::disk('certificates')->put($certificate->pdf_path, '%PDF-1.7');

    (new FinalizeBatch($batch->id))->handle(app(ZipBuilder::class), app(ErrorReportBuilder::class));

    expect($batch->fresh()->status)->toBe(BatchStatus::Completed)
        ->and($batch->fresh()->error_report_path)->not->toBeNull();
});
