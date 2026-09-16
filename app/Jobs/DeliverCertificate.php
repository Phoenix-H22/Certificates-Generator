<?php

namespace App\Jobs;

use App\Certificates\Delivery\DeliveryException;
use App\Certificates\Delivery\WhatsAppSender;
use App\Certificates\Support\EmailNormalizer;
use App\Enums\DeliveryChannel;
use App\Enums\DeliveryStatus;
use App\Mail\CertificateMail;
use App\Models\Batch;
use App\Models\Certificate;
use Illuminate\Bus\Batchable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\Middleware\RateLimited;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Throwable;

/**
 * Stage 3: e-mail and/or WhatsApp one rendered certificate.
 *
 * Each channel is tracked separately on the certificate, so a retry after
 * a WhatsApp outage never re-sends an e-mail that already went out.
 */
class DeliverCertificate implements ShouldQueue
{
    use Batchable, Queueable;

    public int $tries = 3;

    /** @var list<int> */
    public array $backoff = [30, 120, 600];

    public int $timeout = 90;

    /** @param list<string>|null $channels override the batch's channels (panel "resend" actions) */
    public function __construct(public int $certificateId, public ?array $channels = null)
    {
        $this->onQueue(config('certificates.queues.deliver', 'deliver'));
    }

    /** @return array<int, object> */
    public function middleware(): array
    {
        return [new RateLimited('whatsapp')];
    }

    public function handle(WhatsAppSender $whatsapp): void
    {
        if ($this->batch()?->cancelled()) {
            return;
        }

        $certificate = Certificate::with('batch', 'template')->find($this->certificateId);

        if ($certificate === null || ! $certificate->isRendered() || $certificate->batch?->isCancelled()) {
            return;
        }

        $channels = $this->channels ?? $certificate->batch?->deliver_via ?? [];
        $transient = null;

        foreach ($channels as $channelValue) {
            $channel = DeliveryChannel::tryFrom((string) $channelValue);

            if ($channel === null || $certificate->deliveryStatus($channel) === DeliveryStatus::Sent) {
                continue;
            }

            try {
                match ($channel) {
                    DeliveryChannel::Email => $this->email($certificate),
                    DeliveryChannel::WhatsApp => $this->whatsapp($certificate, $whatsapp),
                };
            } catch (DeliveryException $e) {
                $transient = $e;

                if ($this->attempts() >= $this->tries) {
                    $this->record($certificate, $channel, DeliveryStatus::Failed, $e->getMessage());
                }
            }
        }

        // Retry the whole job (already-sent channels are skipped above).
        if ($transient !== null && $this->attempts() < $this->tries) {
            throw $transient;
        }
    }

    private function email(Certificate $certificate): void
    {
        if (! EmailNormalizer::isValid($certificate->email)) {
            $this->record($certificate, DeliveryChannel::Email, DeliveryStatus::Skipped, 'لا يوجد بريد إلكتروني صالح');

            return;
        }

        try {
            Mail::to($certificate->email)->send(new CertificateMail($certificate));
        } catch (Throwable $e) {
            Log::warning('[Deliver] e-mail failed', ['certificate' => $certificate->id, 'error' => $e->getMessage()]);

            throw new DeliveryException('E-mail failed: '.$e->getMessage(), 0, $e);
        }

        $this->record($certificate, DeliveryChannel::Email, DeliveryStatus::Sent);
    }

    private function whatsapp(Certificate $certificate, WhatsAppSender $sender): void
    {
        if (! $sender->isEnabled()) {
            $this->record($certificate, DeliveryChannel::WhatsApp, DeliveryStatus::Skipped, 'قناة الواتساب موقوفة (WHATSAPP_ENABLED=false)');

            return;
        }

        if (! $certificate->phone) {
            $this->record($certificate, DeliveryChannel::WhatsApp, DeliveryStatus::Skipped, 'لا يوجد رقم هاتف صالح');

            return;
        }

        $result = $sender->send($certificate); // throws DeliveryException on transient failure

        $result->ok
            ? $this->record($certificate, DeliveryChannel::WhatsApp, DeliveryStatus::Sent)
            : $this->record($certificate, DeliveryChannel::WhatsApp, DeliveryStatus::Failed, $result->error);
    }

    private function record(Certificate $certificate, DeliveryChannel $channel, DeliveryStatus $status, ?string $error = null): void
    {
        $prefix = $channel->value; // email | whatsapp

        $certificate->forceFill([
            "{$prefix}_status" => $status,
            "{$prefix}_sent_at" => $status === DeliveryStatus::Sent ? now() : null,
            "{$prefix}_error" => $error ? mb_substr($error, 0, 1000) : null,
        ])->save();

        if ($status === DeliveryStatus::Sent) {
            Batch::whereKey($certificate->batch_id)->increment("{$prefix}_sent_count");
        } elseif ($status === DeliveryStatus::Failed) {
            Batch::whereKey($certificate->batch_id)->increment("{$prefix}_failed_count");
        }
    }

    public function failed(?Throwable $e): void
    {
        $certificate = Certificate::with('batch')->find($this->certificateId);

        if ($certificate === null) {
            return;
        }

        foreach ($this->channels ?? $certificate->batch?->deliver_via ?? [] as $channelValue) {
            $channel = DeliveryChannel::tryFrom((string) $channelValue);

            if ($channel !== null && $certificate->deliveryStatus($channel) === DeliveryStatus::Pending) {
                $this->record($certificate, $channel, DeliveryStatus::Failed, $e?->getMessage());
            }
        }
    }
}
