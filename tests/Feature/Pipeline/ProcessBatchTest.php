<?php

use App\Certificates\Pipeline\SheetReader;
use App\Certificates\Rendering\DataMerger;
use App\Enums\BatchStatus;
use App\Enums\CertificateStatus;
use App\Enums\DeliveryStatus;
use App\Jobs\DeliverCertificate;
use App\Jobs\FinalizeBatch;
use App\Jobs\ProcessBatch;
use App\Jobs\RenderCertificate;
use App\Models\Batch;
use App\Models\Template;
use Illuminate\Bus\PendingBatch;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Storage;
use Tests\Support\MakesSheets;

uses(MakesSheets::class);

function makeBatchWithSheet(array $rows, array $overrides = []): Batch
{
    Storage::fake('certificates');

    $sheet = test()->makeSheet($rows);
    Storage::disk('certificates')->put('uploads/test.xlsx', file_get_contents($sheet));

    $template = Template::factory()->create();

    return Batch::factory()->create(array_merge([
        'template_id' => $template->id,
        'status' => BatchStatus::Queued,
        'deliver_via' => ['email', 'whatsapp'],
        'fixed_values' => ['event_name' => 'ورشة الرقمنة', 'event_date' => '2026-05-10'],
        'column_map' => ['name' => 'Name', 'title' => 'Title', 'email' => 'Email', 'phone' => 'Phone'],
        'source_path' => 'uploads/test.xlsx',
    ], $overrides));
}

it('creates certificate rows and dispatches a render/deliver bus batch', function () {
    Bus::fake();

    $batch = makeBatchWithSheet($this->participantRows());

    (new ProcessBatch($batch->id))->handle(app(SheetReader::class), app(DataMerger::class));

    $batch->refresh();

    expect($batch->status)->toBe(BatchStatus::Rendering)
        ->and($batch->total_rows)->toBe(3)
        ->and($batch->render_failed_count)->toBe(1)
        ->and($batch->certificates()->count())->toBe(3);

    $valid = $batch->certificates()->where('row_number', 2)->first();
    expect($valid->status)->toBe(CertificateStatus::Pending)
        ->and($valid->email)->toBe('ahmed@example.com')
        ->and($valid->phone)->toBe('+201012345678')
        ->and($valid->data['event_name'])->toBe('ورشة الرقمنة')
        ->and($valid->data['event_date'])->toBe('2026-05-10')
        ->and($valid->data['event_type'])->toBe('الحضور والمشاركة') // schema default
        ->and($valid->email_status)->toBe(DeliveryStatus::Pending)
        ->and($valid->uuid)->not->toBeEmpty()
        ->and($valid->code)->not->toBeEmpty();

    $badEmail = $batch->certificates()->where('row_number', 3)->first();
    expect($badEmail->status)->toBe(CertificateStatus::Pending)
        ->and($badEmail->email)->toBeNull()   // invalid address dropped, certificate still issued
        ->and($badEmail->phone)->toBeNull();

    $noName = $batch->certificates()->where('row_number', 4)->first();
    expect($noName->status)->toBe(CertificateStatus::RenderFailed)
        ->and($noName->render_error)->toContain('name');

    Bus::assertBatched(function (PendingBatch $pending) {
        return $pending->jobs->count() === 2
            && $pending->jobs->every(fn ($chain) => $chain[0] instanceof RenderCertificate && $chain[1] instanceof DeliverCertificate);
    });
});

it('finalises immediately when every row is invalid', function () {
    Bus::fake();

    $batch = makeBatchWithSheet([['Name' => '', 'Title' => '', 'Email' => '', 'Phone' => '']]);

    (new ProcessBatch($batch->id))->handle(app(SheetReader::class), app(DataMerger::class));

    Bus::assertBatchCount(0);
    Bus::assertDispatched(FinalizeBatch::class, fn (FinalizeBatch $job) => $job->batchId === $batch->id);
});

it('does not duplicate rows when run twice', function () {
    Bus::fake();

    $batch = makeBatchWithSheet($this->participantRows());
    $job = new ProcessBatch($batch->id);

    $job->handle(app(SheetReader::class), app(DataMerger::class));
    $job->handle(app(SheetReader::class), app(DataMerger::class));

    expect($batch->certificates()->count())->toBe(3);
});

it('marks the batch failed when the sheet is missing', function () {
    Bus::fake();

    $batch = makeBatchWithSheet($this->participantRows(), ['source_path' => 'uploads/missing.xlsx']);
    $job = new ProcessBatch($batch->id);

    try {
        $job->handle(app(SheetReader::class), app(DataMerger::class));
    } catch (Throwable $e) {
        $job->failed($e);
    }

    expect($batch->fresh()->status)->toBe(BatchStatus::Failed)
        ->and($batch->fresh()->error_message)->toContain('missing.xlsx');
});
