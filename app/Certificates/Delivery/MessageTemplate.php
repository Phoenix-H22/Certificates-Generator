<?php

namespace App\Certificates\Delivery;

use App\Certificates\Support\ArabicText;
use App\Certificates\Support\Placeholders;
use App\Enums\FieldType;
use App\Models\Certificate;
use App\Models\OrganisationSettings;
use Illuminate\Support\Facades\URL;

/**
 * Fills {placeholders} in e-mail / WhatsApp texts with a certificate's data.
 *
 * Available: {name} {title} {organisation} {parent_organisation} {code}
 * {uuid} {verify_url} {download_url} {issued_at} plus every template field
 * key (dates formatted in Arabic).
 */
final class MessageTemplate
{
    public static function render(string $text, Certificate $certificate): string
    {
        return Placeholders::render($text, self::values($certificate));
    }

    /** @return array<string, string> */
    public static function values(Certificate $certificate): array
    {
        $certificate->loadMissing('template', 'batch');
        $settings = OrganisationSettings::current();

        $values = [
            'name' => (string) $certificate->recipient_name,
            'title' => (string) ($certificate->recipient_title ?? ''),
            'organisation' => (string) $settings->name_ar,
            'parent_organisation' => (string) ($settings->parent_name_ar ?? ''),
            'code' => $certificate->code,
            'uuid' => $certificate->uuid,
            'verify_url' => $certificate->verifyUrl(),
            'download_url' => self::downloadUrl($certificate),
            'issued_at' => ArabicText::date($certificate->batch?->finished_at ?? $certificate->created_at ?? now()),
        ];

        if ($certificate->template) {
            foreach ($certificate->template->fields_schema as $field) {
                $raw = $certificate->data[$field->key] ?? null;
                $values[$field->key] = $field->type === FieldType::Date
                    ? ArabicText::date($raw)
                    : (string) ($raw ?? '');
            }
        }

        return $values;
    }

    /** Signed, expiring link to the PDF for recipients (no login required). */
    public static function downloadUrl(Certificate $certificate): string
    {
        $days = (int) config('certificates.delivery.link_ttl_days', 30);

        return URL::temporarySignedRoute(
            'certificates.public-download',
            now()->addDays(max(1, $days)),
            ['certificate' => $certificate->uuid],
        );
    }
}
