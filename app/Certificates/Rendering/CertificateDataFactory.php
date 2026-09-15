<?php

namespace App\Certificates\Rendering;

use App\Certificates\Support\ArabicText;
use App\Certificates\Support\Placeholders;
use App\Certificates\Support\VerificationCode;
use App\Enums\FieldType;
use App\Models\BrandAsset;
use App\Models\Certificate;
use App\Models\OrganisationSettings;
use App\Models\Template;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

/** Resolves models, assets and texts into an immutable CertificateData. */
final class CertificateDataFactory
{
    public function __construct(
        private readonly QrCodeGenerator $qr,
        private readonly NameSizer $nameSizer,
    ) {}

    public function fromCertificate(Certificate $certificate): CertificateData
    {
        $certificate->loadMissing('template', 'batch');

        return $this->build(
            template: $certificate->template,
            data: $certificate->data,
            uuid: $certificate->uuid,
            code: $certificate->code,
            verifyUrl: $certificate->verifyUrl(),
            issuedAt: $certificate->batch?->finished_at ?? $certificate->created_at ?? now(),
        );
    }

    /** Realistic placeholder data for previews and thumbnails. */
    public function sample(Template $template, ?string $recipientName = null): CertificateData
    {
        $data = $template->fields_schema->defaults();

        foreach ($template->fields_schema as $field) {
            $data[$field->key] ??= match ($field->type) {
                FieldType::Date => now()->toDateString(),
                FieldType::Number => 12,
                FieldType::Email => 'participant@example.com',
                FieldType::Phone => '+201012345678',
                FieldType::Text => match ($field->key) {
                    'event_name' => 'الورشة الإقليمية حول الرقمنة والتعلم مدى الحياة',
                    'event_type' => 'الحضور والمشاركة',
                    default => $field->label,
                },
            };
        }

        $data['name'] = $recipientName ?? 'أحمد محمود عبد الرحمن السيد الشرقاوي';
        $data['title'] = 'الأستاذ الدكتور';
        $data['email'] = 'participant@example.com';
        $data['phone'] = '+201012345678';

        $uuid = (string) Str::uuid();

        return $this->build(
            template: $template,
            data: $data,
            uuid: $uuid,
            code: VerificationCode::format(VerificationCode::random()),
            verifyUrl: route('verify.show', ['identifier' => $uuid]),
            issuedAt: now(),
        );
    }

    /** @param array<string, mixed> $data */
    public function build(Template $template, array $data, string $uuid, string $code, string $verifyUrl, Carbon $issuedAt): CertificateData
    {
        $settings = OrganisationSettings::current();
        $layout = Template::normalizeLayout($template->layout_config ?? [], $template->design);
        $light = $template->design->prefersLightAssets();
        $arabicDigits = ($layout['numerals'] ?? 'arabic') === 'arabic';

        $fields = $this->displayFields($template, $data, $arabicDigits);

        $placeholders = $fields + [
            'name' => (string) ($data['name'] ?? ''),
            'title' => (string) ($data['title'] ?? ''),
            'organisation' => $settings->name_ar,
            'parent_organisation' => (string) $settings->parent_name_ar,
            'code' => $code,
            'uuid' => $uuid,
            'issued_at' => ArabicText::date($issuedAt, $arabicDigits),
        ];

        $stampAsset = $template->stamp();

        return new CertificateData(
            design: $template->design,
            recipientName: (string) ($data['name'] ?? ''),
            recipientTitle: $data['title'] ?? null,
            fields: $fields,
            organisation: $settings->name_ar,
            parentOrganisation: $settings->parent_name_ar,
            logos: $template->logos()->map(fn (BrandAsset $a) => [
                'name' => $a->name,
                'uri' => $a->dataUri($light),
                'width_mm' => $a->width_mm,
            ])->filter(fn ($l) => $l['uri'] !== null)->values()->all(),
            signatures: $template->signatures()->map(fn (BrandAsset $a) => [
                'name' => $a->name,
                'role' => $a->role,
                'uri' => $a->dataUri($light),
                'width_mm' => $a->width_mm,
            ])->values()->all(),
            stamp: [
                'uri' => $stampAsset?->dataUri(),
                'position' => (string) $layout['stamp']['position'],
                'width_mm' => (int) $layout['stamp']['width_mm'],
                'opacity' => (float) $layout['stamp']['opacity'],
                'rotate' => (int) $layout['stamp']['rotate'],
            ],
            background: $template->background()?->dataUri(),
            layout: $layout,
            qrSvg: $this->qr->svg($verifyUrl, $light ? '#111111' : '#111111'),
            uuid: $uuid,
            code: $code,
            verifyUrl: $verifyUrl,
            issuedAt: ArabicText::date($issuedAt, $arabicDigits),
            titleText: Placeholders::render((string) $layout['title_text'], $placeholders),
            bodyText: Placeholders::render((string) $layout['body_text'], $placeholders),
            closingText: Placeholders::render((string) $layout['closing_text'], $placeholders),
            nameFontPt: $this->nameSizer->initialFontPt((string) ($data['name'] ?? '')),
        );
    }

    /**
     * Template fields formatted for display (dates in Arabic, numerals per
     * layout), keyed by field key.
     *
     * @param  array<string, mixed>  $data
     * @return array<string, string>
     */
    private function displayFields(Template $template, array $data, bool $arabicDigits): array
    {
        $out = [];

        foreach ($template->fields_schema as $field) {
            $raw = $data[$field->key] ?? null;

            $out[$field->key] = match ($field->type) {
                FieldType::Date => ArabicText::date($raw, $arabicDigits),
                FieldType::Number => $arabicDigits ? ArabicText::digits((string) $raw) : (string) $raw,
                default => (string) ($raw ?? ''),
            };
        }

        return $out;
    }
}
