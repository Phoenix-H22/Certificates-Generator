<?php

use App\Certificates\Support\VerificationCode;

it('produces codes in XXXXX-XXXXX form from the safe alphabet', function () {
    $code = VerificationCode::format(VerificationCode::random());

    expect($code)->toMatch('/^['.VerificationCode::ALPHABET.']{5}-['.VerificationCode::ALPHABET.']{5}$/');
});

it('never emits confusable characters', function () {
    $seen = '';

    for ($i = 0; $i < 500; $i++) {
        $seen .= VerificationCode::random();
    }

    expect($seen)->not->toMatch('/[0O1IL]/');
});

it('normalises sloppy input', function () {
    expect(VerificationCode::normalize(' ab-cde 12 345 '))->toBe('ABCDE12345');
});

it('recognises well-formed codes and rejects others', function () {
    expect(VerificationCode::looksLikeCode('ABCDE-FGH23'))->toBeTrue()
        ->and(VerificationCode::looksLikeCode('abcde fgh23'))->toBeTrue()
        ->and(VerificationCode::looksLikeCode('ABCDE-FGH2'))->toBeFalse()
        ->and(VerificationCode::looksLikeCode('ABCDE-FGH10'))->toBeFalse()   // 1 and 0 are not in the alphabet
        ->and(VerificationCode::looksLikeCode('not a code at all'))->toBeFalse();
});

it('returns the canonical formatted code or null', function () {
    expect(VerificationCode::canonical('abcdefgh23'))->toBe('ABCDE-FGH23')
        ->and(VerificationCode::canonical('xyz'))->toBeNull();
});

it('does not repeat itself across many draws', function () {
    $codes = [];

    for ($i = 0; $i < 2000; $i++) {
        $codes[] = VerificationCode::random();
    }

    expect(count(array_unique($codes)))->toBe(2000);
});
