<?php

use App\Certificates\Exceptions\InvalidRowException;
use App\Certificates\Pipeline\SingleCertificateIssuer;
use App\Enums\BatchStatus;
use App\Enums\CertificateStatus;
use App\Enums\DeliveryStatus;
use App\Jobs\DeliverCertificate;
use App\Jobs\RenderCertificate;
use App\Models\Template;
use App\Models\User;
use Illuminate\Bus\PendingBatch;
use Illuminate\Support\Facades\Bus;

it('creates a one-row batch and dispatches the render chain', function () {
    Bus::fake();

    $template = Template::factory()->create();
    $admin = User::factory()->admin()->create();

    $certificate = app(SingleCertificateIssuer::class)->issue(
        template: $template,
        fixedValues: ['event_name' => 'ندوة التعليم المستمر', 'event_date' => '2026-10-01'],
        recipient: ['name' => 'منى أحمد', 'title' => 'الأستاذة', 'email' => 'mona@example.com', 'phone' => '01098765432'],
        channels: ['email', 'bogus'],
        userId: $admin->id,
    );

    expect($certificate->status)->toBe(CertificateStatus::Pending)
        ->and($certificate->email)->toBe('mona@example.com')
        ->and($certificate->phone)->toBe('+201098765432')
        ->and($certificate->data['event_name'])->toBe('ندوة التعليم المستمر')
        ->and($certificate->email_status)->toBe(DeliveryStatus::Pending)
        ->and($certificate->whatsapp_status)->toBe(DeliveryStatus::NotRequested)
        ->and($certificate->batch->status)->toBe(BatchStatus::Rendering)
        ->and($certificate->batch->total_rows)->toBe(1)
        ->and($certificate->batch->deliver_via)->toBe(['email'])
        ->and($certificate->batch->name)->toContain('منى أحمد');

    Bus::assertBatched(fn (PendingBatch $b) => $b->jobs->count() === 1
        && $b->jobs->first()[0] instanceof RenderCertificate
        && $b->jobs->first()[1] instanceof DeliverCertificate);
});

it('rejects a recipient without a name', function () {
    Bus::fake();
    $template = Template::factory()->create();

    expect(fn () => app(SingleCertificateIssuer::class)->issue($template, ['event_name' => 'x', 'event_date' => '2026-01-01'], ['name' => '']))
        ->toThrow(InvalidRowException::class);
});

it('renders the issue page for admins', function () {
    Template::factory()->create();

    $this->actingAs(User::factory()->admin()->create())
        ->get('/admin/issue-certificate')
        ->assertOk()
        ->assertSee('إصدار شهادة فردية');
});
