<?php

use App\Certificates\Support\VerificationCode;
use App\Enums\CertificateStatus;
use App\Models\Certificate;

it('assigns a uuid and a short code on creation', function () {
    $certificate = Certificate::factory()->create();

    expect($certificate->uuid)->toBeString()->toHaveLength(36)
        ->and(VerificationCode::looksLikeCode($certificate->code))->toBeTrue()
        ->and($certificate->status)->toBe(CertificateStatus::Pending);
});

it('finds certificates by uuid or by sloppy short code', function () {
    $certificate = Certificate::factory()->create();

    expect(Certificate::byIdentifier($certificate->uuid)->first()?->is($certificate))->toBeTrue()
        ->and(Certificate::byIdentifier(strtoupper($certificate->uuid))->first()?->is($certificate))->toBeTrue()
        ->and(Certificate::byIdentifier(strtolower(str_replace('-', ' ', $certificate->code)))->first()?->is($certificate))->toBeTrue()
        ->and(Certificate::byIdentifier('garbage')->first())->toBeNull();
});

it('is only valid when rendered and not revoked', function () {
    $pending = Certificate::factory()->create();
    $rendered = Certificate::factory()->rendered()->create();
    $revoked = Certificate::factory()->rendered()->revoked()->create();

    expect($pending->isValid())->toBeFalse()
        ->and($rendered->isValid())->toBeTrue()
        ->and($revoked->isValid())->toBeFalse()
        ->and($revoked->isRevoked())->toBeTrue();
});

it('can be revoked and restored', function () {
    $certificate = Certificate::factory()->rendered()->create();

    $certificate->revoke('أُصدرت بالخطأ');
    expect($certificate->fresh()->isRevoked())->toBeTrue()
        ->and($certificate->fresh()->revoke_reason)->toBe('أُصدرت بالخطأ');

    $certificate->unrevoke();
    expect($certificate->fresh()->isValid())->toBeTrue()
        ->and($certificate->fresh()->status)->toBe(CertificateStatus::Rendered);
});

it('builds a filesystem-safe download name', function () {
    $certificate = Certificate::factory()->create([
        'row_number' => 7,
        'recipient_name' => 'د/ محمد: عبد*الوارث',
    ]);

    expect($certificate->downloadName())
        ->toStartWith('007-')
        ->toEndWith('-'.$certificate->code.'.pdf')
        ->not->toContain(':')
        ->not->toContain('*');
});
