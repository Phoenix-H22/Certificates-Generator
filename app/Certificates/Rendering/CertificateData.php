<?php

namespace App\Certificates\Rendering;

use App\Enums\TemplateDesign;

/**
 * Everything a design view needs, already resolved: text, inlined images,
 * QR markup and layout knobs. Built by CertificateDataFactory.
 */
final readonly class CertificateData
{
    /**
     * @param  array<string, mixed>  $fields  merged template fields (event_name, event_date, ...)
     * @param  list<array{name: string, uri: string|null, width_mm: int|null}>  $logos
     * @param  list<array{name: string, role: string|null, uri: string|null, width_mm: int|null}>  $signatures
     * @param  array{uri: string|null, position: string, width_mm: int, opacity: float, rotate: int}  $stamp
     * @param  array<string, mixed>  $layout  normalised layout_config
     */
    public function __construct(
        public TemplateDesign $design,
        public string $recipientName,
        public ?string $recipientTitle,
        public array $fields,
        public string $organisation,
        public ?string $parentOrganisation,
        public array $logos,
        public array $signatures,
        public array $stamp,
        public ?string $background,
        public array $layout,
        public string $qrSvg,
        public string $uuid,
        public string $code,
        public string $verifyUrl,
        public string $issuedAt,
        public string $titleText,
        public string $bodyText,
        public string $closingText,
        public int $nameFontPt,
    ) {}

    /** Full display name, e.g. "الدكتور / أحمد محمد". */
    public function displayName(): string
    {
        return $this->recipientTitle
            ? $this->recipientTitle.' / '.$this->recipientName
            : $this->recipientName;
    }

    public function accent(): string
    {
        return (string) ($this->layout['accent'] ?? $this->design->defaultAccent());
    }

    public function field(string $key, mixed $default = null): mixed
    {
        return $this->fields[$key] ?? $default;
    }

    public function hasStamp(): bool
    {
        return $this->stamp['uri'] !== null;
    }

    public function stampPosition(): string
    {
        return $this->stamp['position'];
    }

    public function qrPosition(): string
    {
        return (string) ($this->layout['qr_position'] ?? 'bottom-left');
    }

    public function qrSizeMm(): int
    {
        return (int) ($this->layout['qr_size_mm'] ?? 24);
    }

    public function useArabicNumerals(): bool
    {
        return ($this->layout['numerals'] ?? 'arabic') === 'arabic';
    }
}
