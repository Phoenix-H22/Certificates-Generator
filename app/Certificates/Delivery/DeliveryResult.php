<?php

namespace App\Certificates\Delivery;

final readonly class DeliveryResult
{
    /** @param array<string, mixed>|null $raw */
    private function __construct(
        public bool $ok,
        public ?string $error = null,
        public ?array $raw = null,
    ) {}

    /** @param array<string, mixed>|null $raw */
    public static function sent(?array $raw = null): self
    {
        return new self(true, null, $raw);
    }

    /** @param array<string, mixed>|null $raw */
    public static function failed(string $error, ?array $raw = null): self
    {
        return new self(false, $error, $raw);
    }
}
