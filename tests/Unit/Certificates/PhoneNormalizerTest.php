<?php

use App\Certificates\Support\PhoneNormalizer;

it('normalises the Egyptian mobile formats spreadsheets produce', function (mixed $raw, ?string $expected) {
    expect(PhoneNormalizer::normalize($raw))->toBe($expected);
})->with([
    'local with leading zero' => ['01012345678', '+201012345678'],
    'local without leading zero' => ['1012345678', '+201012345678'],
    'country code without plus' => ['201012345678', '+201012345678'],
    'international 00 prefix' => ['00201012345678', '+201012345678'],
    'already e164' => ['+201012345678', '+201012345678'],
    'spaces and dashes' => ['010 1234-5678', '+201012345678'],
    'excel float' => [1012345678.0, '+201012345678'],
    'excel int' => [1012345678, '+201012345678'],
    'vodafone 010' => ['01098765432', '+201098765432'],
    'etisalat 011' => ['01198765432', '+201198765432'],
    'orange 012' => ['01298765432', '+201298765432'],
    'we 015' => ['01598765432', '+201598765432'],
]);

it('returns null for empty or garbage input', function (mixed $raw) {
    expect(PhoneNormalizer::normalize($raw))->toBeNull();
})->with([
    'null' => [null],
    'empty' => [''],
    'spaces' => ['   '],
    'letters' => ['not a phone'],
    'too short' => ['0101'],
    'just plus' => ['+'],
]);

it('accepts valid non-Egyptian numbers in E.164', function () {
    expect(PhoneNormalizer::normalize('+966501234567'))->toBe('+966501234567');
});
