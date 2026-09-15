<?php

use App\Certificates\Delivery\WhatsAppSender;
use App\Enums\DeliveryStatus;
use App\Jobs\DeliverCertificate;
use App\Mail\CertificateMail;
use App\Models\Batch;
use App\Models\Certificate;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;

beforeEach(function () {
    Storage::fake('certificates');
    Mail::fake();

    config()->set('services.whatsapp', [
        'url' => 'https://gateway.test/send',
        'app_key' => 'app',
        'auth_key' => 'secret',
        'timeout' => 5,
    ]);
});

function deliverable(array $certificateOverrides = [], array $batchOverrides = []): Certificate
{
    $batch = Batch::factory()->create(array_merge(['deliver_via' => ['email', 'whatsapp']], $batchOverrides));

    $certificate = Certificate::factory()->rendered()->create(array_merge([
        'batch_id' => $batch->id,
        'template_id' => $batch->template_id,
        'email_status' => DeliveryStatus::Pending,
        'whatsapp_status' => DeliveryStatus::Pending,
    ], $certificateOverrides));

    Storage::disk('certificates')->put($certificate->pdf_path, '%PDF-1.7 fake');

    return $certificate;
}

function runDelivery(Certificate $certificate, ?array $channels = null): void
{
    (new DeliverCertificate($certificate->id, $channels))->handle(app(WhatsAppSender::class));
}

it('sends the e-mail and the WhatsApp message', function () {
    Http::fake(['gateway.test/*' => Http::response(['status' => 'success', 'id' => 'abc'])]);

    $certificate = deliverable();

    runDelivery($certificate);

    $certificate->refresh();

    Mail::assertSent(CertificateMail::class, fn (CertificateMail $m) => $m->hasTo($certificate->email));

    Http::assertSent(function ($request) use ($certificate) {
        return $request['to'] === $certificate->phone
            && str_contains($request['file'], '/c/'.$certificate->uuid)
            && str_contains($request['message'], $certificate->recipient_name);
    });

    expect($certificate->email_status)->toBe(DeliveryStatus::Sent)
        ->and($certificate->whatsapp_status)->toBe(DeliveryStatus::Sent)
        ->and($certificate->batch->email_sent_count)->toBe(1)
        ->and($certificate->batch->whatsapp_sent_count)->toBe(1);
});

it('skips channels without a valid address', function () {
    Http::fake();

    $certificate = deliverable(['email' => null, 'phone' => null]);

    runDelivery($certificate);

    $certificate->refresh();

    Mail::assertNothingSent();
    Http::assertNothingSent();

    expect($certificate->email_status)->toBe(DeliveryStatus::Skipped)
        ->and($certificate->whatsapp_status)->toBe(DeliveryStatus::Skipped);
});

it('records a permanent WhatsApp rejection without retrying', function () {
    Http::fake(['gateway.test/*' => Http::response(['message' => 'invalid number'], 400)]);

    $certificate = deliverable();

    runDelivery($certificate);

    $certificate->refresh();

    expect($certificate->whatsapp_status)->toBe(DeliveryStatus::Failed)
        ->and($certificate->whatsapp_error)->toContain('400')
        ->and($certificate->batch->whatsapp_failed_count)->toBe(1)
        ->and($certificate->email_status)->toBe(DeliveryStatus::Sent);
});

it('only delivers the requested channel and never re-sends a sent one', function () {
    Http::fake(['gateway.test/*' => Http::response(['status' => 'success'])]);

    $certificate = deliverable(['email_status' => DeliveryStatus::Sent]);

    runDelivery($certificate, ['email', 'whatsapp']);

    Mail::assertNothingSent();
    Http::assertSentCount(1);

    expect($certificate->fresh()->whatsapp_status)->toBe(DeliveryStatus::Sent);
});

it('does nothing for certificates that are not rendered', function () {
    Http::fake();

    $batch = Batch::factory()->create(['deliver_via' => ['email']]);
    $certificate = Certificate::factory()->create(['batch_id' => $batch->id, 'template_id' => $batch->template_id]);

    runDelivery($certificate);

    Mail::assertNothingSent();
    expect($certificate->fresh()->email_status)->toBe(DeliveryStatus::NotRequested);
});
