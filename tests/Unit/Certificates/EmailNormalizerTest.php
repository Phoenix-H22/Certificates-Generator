<?php

use App\Certificates\Support\EmailNormalizer;

it('strips invisible bidi and zero-width characters', function () {
    $dirty = "\u{200F}user\u{200B}@\u{FEFF}example.com\u{200E}";

    expect(EmailNormalizer::normalize($dirty))->toBe('user@example.com');
});

it('removes internal whitespace and lower-cases', function () {
    expect(EmailNormalizer::normalize(' User @ Example.COM '))->toBe('user@example.com');
});

it('returns null for empty input', function () {
    expect(EmailNormalizer::normalize(null))->toBeNull()
        ->and(EmailNormalizer::normalize('   '))->toBeNull();
});

it('validates addresses', function () {
    expect(EmailNormalizer::isValid('user@example.com'))->toBeTrue()
        ->and(EmailNormalizer::isValid('user@'))->toBeFalse()
        ->and(EmailNormalizer::isValid(null))->toBeFalse();
});
