<?php

use App\Certificates\Data\FieldDefinition;
use App\Certificates\Data\FieldSchema;
use App\Enums\FieldScope;
use App\Enums\FieldType;

it('round-trips through arrays', function () {
    $rows = [
        ['key' => 'event_name', 'label' => 'اسم الفعالية', 'type' => 'text', 'scope' => 'fixed', 'required' => true, 'max_length' => 120],
        ['key' => 'department', 'label' => 'الإدارة', 'type' => 'text', 'scope' => 'row', 'required' => false],
    ];

    $schema = FieldSchema::fromArray($rows);

    expect($schema)->toHaveCount(2)
        ->and($schema->keys())->toBe(['event_name', 'department'])
        ->and($schema->toArray()[0]['max_length'])->toBe(120)
        ->and($schema->toArray()[1]['show_on_verify'])->toBeTrue();
});

it('separates fixed and row fields and builds rules', function () {
    $schema = FieldSchema::fromArray([
        ['key' => 'event_name', 'label' => 'x', 'type' => 'text', 'scope' => 'fixed', 'required' => true, 'max_length' => 50],
        ['key' => 'event_date', 'label' => 'x', 'type' => 'date', 'scope' => 'fixed', 'required' => true],
        ['key' => 'hours', 'label' => 'x', 'type' => 'number', 'scope' => 'row', 'required' => false],
    ]);

    expect(array_map(fn (FieldDefinition $f) => $f->key, $schema->fixed()))->toBe(['event_name', 'event_date'])
        ->and(array_map(fn (FieldDefinition $f) => $f->key, $schema->row()))->toBe(['hours'])
        ->and($schema->fixedRules())->toBe([
            'event_name' => ['required', 'string', 'max:50'],
            'event_date' => ['required', 'date'],
        ])
        ->and($schema->rowRules())->toBe(['hours' => ['nullable', 'numeric']]);
});

it('rejects reserved and duplicate keys', function () {
    expect(fn () => FieldSchema::fromArray([['key' => 'name', 'label' => 'x']]))
        ->toThrow(InvalidArgumentException::class, 'reserved');

    expect(fn () => FieldSchema::fromArray([
        ['key' => 'a', 'label' => 'x'],
        ['key' => 'a', 'label' => 'y'],
    ]))->toThrow(InvalidArgumentException::class, 'Duplicate');
});

it('rejects keys that are not snake_case ascii', function () {
    expect(fn () => new FieldDefinition(key: 'Event Name', label: 'x'))
        ->toThrow(InvalidArgumentException::class);
});

it('exposes defaults', function () {
    $schema = FieldSchema::fromArray([
        ['key' => 'event_type', 'label' => 'x', 'default' => 'الحضور'],
        ['key' => 'event_name', 'label' => 'x'],
    ]);

    expect($schema->defaults())->toBe(['event_type' => 'الحضور', 'event_name' => null]);
});

it('uses sensible defaults for type and scope', function () {
    $field = FieldDefinition::fromArray(['key' => 'x', 'label' => 'X']);

    expect($field->type)->toBe(FieldType::Text)
        ->and($field->scope)->toBe(FieldScope::Fixed)
        ->and($field->required)->toBeTrue();
});
