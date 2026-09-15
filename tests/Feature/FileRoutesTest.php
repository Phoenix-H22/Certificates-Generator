<?php

use App\Certificates\Delivery\MessageTemplate;
use App\Models\Batch;
use App\Models\Certificate;
use App\Models\User;
use Illuminate\Support\Facades\Storage;

beforeEach(function () {
    Storage::fake('certificates');
});

it('serves the PDF through a signed link and refuses tampered ones', function () {
    $certificate = Certificate::factory()->rendered()->create();
    Storage::disk('certificates')->put($certificate->pdf_path, '%PDF-1.7 fake');

    $url = MessageTemplate::downloadUrl($certificate);

    $this->get($url)
        ->assertOk()
        ->assertHeader('content-type', 'application/pdf')
        ->assertDownload('certificate-'.$certificate->code.'.pdf');

    $this->get(route('certificates.public-download', $certificate->uuid))->assertForbidden();
    $this->get($url.'x')->assertForbidden();
});

it('hides revoked certificates from the public link', function () {
    $certificate = Certificate::factory()->rendered()->revoked()->create();
    Storage::disk('certificates')->put($certificate->pdf_path, '%PDF-1.7 fake');

    $this->get(MessageTemplate::downloadUrl($certificate))->assertNotFound();
});

it('lets admins download batch files and blocks everyone else', function () {
    $batch = Batch::factory()->create(['zip_path' => 'batches/1/certificates-1.zip', 'error_report_path' => 'batches/1/errors-1.xlsx', 'source_path' => 'uploads/x.xlsx']);
    Storage::disk('certificates')->put($batch->zip_path, 'zip');
    Storage::disk('certificates')->put($batch->error_report_path, 'xlsx');
    Storage::disk('certificates')->put($batch->source_path, 'xlsx');

    $certificate = Certificate::factory()->rendered()->create(['batch_id' => $batch->id, 'template_id' => $batch->template_id]);
    Storage::disk('certificates')->put($certificate->pdf_path, '%PDF-1.7');

    $admin = User::factory()->admin()->create();
    $user = User::factory()->create();

    $this->get(route('files.batch.zip', $batch))->assertRedirect(); // guest → login

    $this->actingAs($user);
    $this->get(route('files.batch.zip', $batch))->assertForbidden();
    $this->get(route('files.certificate.pdf', $certificate))->assertForbidden();

    $this->actingAs($admin);
    $this->get(route('files.batch.zip', $batch))->assertOk();
    $this->get(route('files.batch.errors', $batch))->assertOk();
    $this->get(route('files.batch.source', $batch))->assertOk();
    $this->get(route('files.certificate.pdf', $certificate))->assertOk()->assertHeader('content-type', 'application/pdf');
});
