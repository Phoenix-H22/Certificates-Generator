<?php

namespace App\Certificates\Data;

use ArrayIterator;
use Countable;
use Illuminate\Contracts\Support\Arrayable;
use InvalidArgumentException;
use IteratorAggregate;
use JsonSerializable;
use Traversable;

/**
 * The ordered set of template-defined fields.
 *
 * The four core recipient fields (name, title, email, phone) are implicit
 * on every template and never part of the schema; see CORE_FIELDS.
 *
 * @implements IteratorAggregate<int, FieldDefinition>
 * @implements Arrayable<int, array<string, mixed>>
 */
final class FieldSchema implements Arrayable, Countable, IteratorAggregate, JsonSerializable
{
    /** Recipient columns every batch must map, regardless of template. */
    public const CORE_FIELDS = [
        'name' => ['label' => 'الاسم', 'required' => true],
        'title' => ['label' => 'الصفة / اللقب', 'required' => false],
        'email' => ['label' => 'البريد الإلكتروني', 'required' => false],
        'phone' => ['label' => 'رقم الهاتف', 'required' => false],
    ];

    /** Keys the renderer injects; templates may not redefine them. */
    public const RESERVED_KEYS = ['name', 'title', 'email', 'phone', 'uuid', 'code', 'verify_url', 'download_url', 'issued_at', 'organisation', 'parent_organisation'];

    /** @var array<string, FieldDefinition> keyed by field key */
    private array $fields = [];

    /** @param iterable<FieldDefinition> $fields */
    public function __construct(iterable $fields = [])
    {
        foreach ($fields as $field) {
            $this->add($field);
        }
    }

    /** @param iterable<array<string, mixed>> $rows */
    public static function fromArray(iterable $rows): self
    {
        $schema = new self;

        foreach ($rows as $row) {
            $schema->add(FieldDefinition::fromArray((array) $row));
        }

        return $schema;
    }

    private function add(FieldDefinition $field): void
    {
        if (in_array($field->key, self::RESERVED_KEYS, true)) {
            throw new InvalidArgumentException("Field key [{$field->key}] is reserved.");
        }

        if (isset($this->fields[$field->key])) {
            throw new InvalidArgumentException("Duplicate field key [{$field->key}].");
        }

        $this->fields[$field->key] = $field;
    }

    /** @return list<array<string, mixed>> */
    public function toArray(): array
    {
        return array_values(array_map(fn (FieldDefinition $f) => $f->toArray(), $this->fields));
    }

    /** @return list<array<string, mixed>> */
    public function jsonSerialize(): array
    {
        return $this->toArray();
    }

    /** @return list<FieldDefinition> */
    public function all(): array
    {
        return array_values($this->fields);
    }

    /** @return list<FieldDefinition> */
    public function fixed(): array
    {
        return array_values(array_filter($this->fields, fn (FieldDefinition $f) => $f->isFixed()));
    }

    /** @return list<FieldDefinition> */
    public function row(): array
    {
        return array_values(array_filter($this->fields, fn (FieldDefinition $f) => $f->isRow()));
    }

    public function get(string $key): ?FieldDefinition
    {
        return $this->fields[$key] ?? null;
    }

    public function has(string $key): bool
    {
        return isset($this->fields[$key]);
    }

    /** @return list<string> */
    public function keys(): array
    {
        return array_keys($this->fields);
    }

    /** @return array<string, list<string>> validation rules for the batch-level fixed values */
    public function fixedRules(): array
    {
        $rules = [];

        foreach ($this->fixed() as $field) {
            $rules[$field->key] = $field->rules();
        }

        return $rules;
    }

    /** @return array<string, list<string>> validation rules for one Excel row (template fields only) */
    public function rowRules(): array
    {
        $rules = [];

        foreach ($this->row() as $field) {
            $rules[$field->key] = $field->rules();
        }

        return $rules;
    }

    /** @return array<string, string|null> default values keyed by field */
    public function defaults(): array
    {
        $defaults = [];

        foreach ($this->fields as $field) {
            $defaults[$field->key] = $field->default;
        }

        return $defaults;
    }

    public function count(): int
    {
        return count($this->fields);
    }

    public function getIterator(): Traversable
    {
        return new ArrayIterator(array_values($this->fields));
    }
}
