<?php

namespace App\Certificates\Pipeline;

use App\Certificates\Data\FieldSchema;
use App\Certificates\Exceptions\InvalidRowException;
use App\Certificates\Rendering\DataMerger;
use App\Enums\BatchStatus;
use App\Enums\CertificateStatus;
use App\Enums\DeliveryChannel;
use App\Enums\DeliveryStatus;
use App\Jobs\DeliverCertificate;
use App\Jobs\FinalizeBatch;
use App\Jobs\RenderCertificate;
use App\Models\Batch;
use App\Models\Certificate;
use App\Models\Template;
use Illuminate\Support\Facades\Bus;

/**
 * Issues one certificate without a spreadsheet: a one-row batch that goes
 * through the same render → deliver → finalize pipeline.
 */
final class SingleCertificateIssuer
{
    public function __construct(private readonly DataMerger $merger) {}

    /**
     * @param  array<string, mixed>  $fixedValues  template fixed fields
     * @param  array<string, mixed>  $recipient  name, title, email, phone + row-scope fields
     * @param  list<string>  $channels  DeliveryChannel values
     *
     * @throws InvalidRowException when the recipient data does not validate
     */
    public function issue(Template $template, array $fixedValues, array $recipient, array $channels = [], ?int $userId = null): Certificate
    {
        $columnMap = [];

        foreach (array_keys(FieldSchema::CORE_FIELDS) as $key) {
            $columnMap[$key] = $key;
        }

        foreach ($template->rowFields() as $field) {
            $columnMap[$field->key] = $field->key;
        }

        $data = $this->merger->merge($template, $fixedValues, $recipient, $columnMap, 1);

        $channels = DeliveryChannel::normalize($channels);

        $batch = Batch::create([
            'template_id' => $template->getKey(),
            'name' => 'شهادة فردية — '.$data['name'],
            'status' => BatchStatus::Queued,
            'deliver_via' => $channels,
            'fixed_values' => $fixedValues,
            'column_map' => $columnMap,
            'source_original_name' => null,
            'total_rows' => 1,
            'created_by' => $userId,
        ]);

        $certificate = Certificate::create([
            'batch_id' => $batch->getKey(),
            'template_id' => $template->getKey(),
            'row_number' => 1,
            'recipient_name' => $data['name'],
            'recipient_title' => $data['title'],
            'email' => $data['email'],
            'phone' => $data['phone'],
            'data' => $data,
            'status' => CertificateStatus::Pending,
            'email_status' => in_array(DeliveryChannel::Email->value, $channels, true) ? DeliveryStatus::Pending : DeliveryStatus::NotRequested,
            'whatsapp_status' => in_array(DeliveryChannel::WhatsApp->value, $channels, true) ? DeliveryStatus::Pending : DeliveryStatus::NotRequested,
        ]);

        $bus = Bus::batch([[new RenderCertificate($certificate->getKey()), new DeliverCertificate($certificate->getKey())]])
            ->name('single-certificate-'.$certificate->getKey())
            ->allowFailures()
            ->onQueue(config('certificates.queues.render', 'render'))
            ->finally(fn () => FinalizeBatch::dispatch($batch->getKey()))
            ->dispatch();

        $batch->forceFill(['bus_batch_id' => $bus->id])->save();
        $batch->markStatus(BatchStatus::Rendering);

        return $certificate;
    }
}
