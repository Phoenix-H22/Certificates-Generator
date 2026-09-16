<?php

namespace App\Certificates\Delivery;

use App\Models\Certificate;
use App\Models\OrganisationSettings;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Sends a certificate over the form-POST WhatsApp gateway used by v1:
 * appkey / authkey / to / message / file (a URL the gateway fetches).
 */
final class WhatsAppSender
{
    /** @param array{url?: string|null, app_key?: string|null, auth_key?: string|null, timeout?: int} $config */
    public function __construct(private readonly array $config) {}

    public static function fromConfig(): self
    {
        return new self((array) config('services.whatsapp', []));
    }

    public function isConfigured(): bool
    {
        return ! empty($this->config['url']) && ! empty($this->config['app_key']) && ! empty($this->config['auth_key']);
    }

    public function isEnabled(): bool
    {
        return filter_var($this->config['enabled'] ?? true, FILTER_VALIDATE_BOOLEAN);
    }

    /**
     * @throws DeliveryException on transient (retryable) failures
     */
    public function send(Certificate $certificate): DeliveryResult
    {
        if (! $this->isEnabled()) {
            return DeliveryResult::failed('WhatsApp channel is disabled (WHATSAPP_ENABLED=false).');
        }

        if (! $this->isConfigured()) {
            return DeliveryResult::failed('WhatsApp gateway is not configured (WHATSAPP_APP_URL / KEY / SECRET).');
        }

        if (! $certificate->phone) {
            return DeliveryResult::failed('No valid phone number.');
        }

        $message = MessageTemplate::render(OrganisationSettings::current()->whatsapp_message, $certificate);

        try {
            $response = Http::asForm()
                ->timeout((int) ($this->config['timeout'] ?? 15))
                ->retry(2, 500, throw: false)
                ->post((string) $this->config['url'], [
                    'appkey' => $this->config['app_key'],
                    'authkey' => $this->config['auth_key'],
                    'to' => $certificate->phone,
                    'message' => $message,
                    'file' => MessageTemplate::downloadUrl($certificate),
                    'sandbox' => 'false',
                ]);
        } catch (ConnectionException $e) {
            throw new DeliveryException('WhatsApp gateway unreachable: '.$e->getMessage(), 0, $e);
        }

        $raw = $response->json();
        $raw = is_array($raw) ? $raw : ['body' => mb_substr((string) $response->body(), 0, 500)];

        if ($response->serverError()) {
            throw new DeliveryException("WhatsApp gateway error HTTP {$response->status()}");
        }

        if ($response->failed()) {
            Log::warning('[WhatsApp] rejected', ['certificate' => $certificate->id, 'status' => $response->status(), 'raw' => $raw]);

            return DeliveryResult::failed("WhatsApp gateway rejected the message (HTTP {$response->status()}).", $raw);
        }

        // Some gateways answer 200 with an error flag in the body.
        $status = strtolower((string) ($raw['message_status'] ?? $raw['status'] ?? 'success'));

        if (in_array($status, ['error', 'failed', 'fail'], true)) {
            return DeliveryResult::failed('WhatsApp gateway reported: '.(string) ($raw['message'] ?? $status), $raw);
        }

        return DeliveryResult::sent($raw);
    }
}
