<?php

use App\Certificates\Exceptions\RenderFailedException;
use App\Certificates\Rendering\CertificateDataFactory;
use App\Certificates\Rendering\CertificateRenderer;
use App\Enums\CertificateStatus;
use App\Jobs\RenderCertificate;
use App\Models\Certificate;
use Illuminate\Support\Facades\Storage;

it('stores the PDF on the private disk and marks the certificate rendered', function () {
    Storage::fake('certificates');

    $this->mock(CertificateRenderer::class)
        ->shouldReceive('pdf')->once()->andReturn('%PDF-1.7 fake');

    $certificate = Certificate::factory()->create();

    (new RenderCertificate($certificate->id))->handle(app(CertificateRenderer::class), app(CertificateDataFactory::class));

    $certificate->refresh();

    expect($certificate->status)->toBe(CertificateStatus::Rendered)
        ->and($certificate->pdf_path)->toBe('batches/'.$certificate->batch_id.'/pdf/'.$certificate->code.'.pdf')
        ->and($certificate->rendered_at)->not->toBeNull()
        ->and($certificate->batch->rendered_count)->toBe(1);

    Storage::disk('certificates')->assertExists($certificate->pdf_path);
    expect(Storage::disk('certificates')->get($certificate->pdf_path))->toStartWith('%PDF');
});

it('is idempotent for already rendered certificates', function () {
    Storage::fake('certificates');

    $this->mock(CertificateRenderer::class)->shouldNotReceive('pdf');

    $certificate = Certificate::factory()->rendered()->create();
    Storage::disk('certificates')->put($certificate->pdf_path, '%PDF-1.7 existing');

    (new RenderCertificate($certificate->id))->handle(app(CertificateRenderer::class), app(CertificateDataFactory::class));

    expect($certificate->fresh()->batch->rendered_count)->toBe(0);
});

it('records the failure when rendering gives up', function () {
    $certificate = Certificate::factory()->create();

    (new RenderCertificate($certificate->id))->failed(new RenderFailedException('Chromium exploded'));

    $certificate->refresh();

    expect($certificate->status)->toBe(CertificateStatus::RenderFailed)
        ->and($certificate->render_error)->toBe('Chromium exploded')
        ->and($certificate->batch->render_failed_count)->toBe(1);
});
