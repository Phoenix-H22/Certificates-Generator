<?php

namespace App\Certificates\Data;

use App\Enums\FieldScope;
use App\Enums\FieldType;
use InvalidArgumentException;

/**
 * One template-defined field (e.g. event_name), as stored in
 * templates.fields_schema.
 */
final readonly class FieldDefinition
{
    public function __construct(
        public string $key,
        public string $label,
        public FieldType $type = FieldType::Text,
        public FieldScope $scope = FieldScope::Fixed,
        public bool $required = true,
        public ?int $maxLength = null,
        public ?string $default = null,
        public bool $showOnVerify = true,
    ) {
        if (! preg_match('/^[a-z][a-z0-9_]{0,49}$/', $key)) {
            throw new InvalidArgumentException("Invalid field key [{$key}]: use snake_case ASCII letters, digits and underscores.");
        }
    }

    /** @param array<string, mixed> $data */
    public static function fromArray(array $data): self
    {
        // Filament v4 enum Selects hand back enum instances (not strings) in
        // repeater state, so accept both before the model cast re-validates.
        $type = $data['type'] ?? FieldType::Text->value;
        $type = $type instanceof FieldType ? $type : FieldType::from((string) $type);

        $scope = $data['scope'] ?? FieldScope::Fixed->value;
        $scope = $scope instanceof FieldScope ? $scope : FieldScope::from((string) $scope);

        return new self(
            key: (string) ($data['key'] ?? ''),
            label: (string) ($data['label'] ?? $data['key'] ?? ''),
            type: $type,
            scope: $scope,
            required: (bool) ($data['required'] ?? true),
            maxLength: isset($data['max_length']) && $data['max_length'] !== '' ? (int) $data['max_length'] : null,
            default: isset($data['default']) && $data['default'] !== '' ? (string) $data['default'] : null,
            showOnVerify: (bool) ($data['show_on_verify'] ?? true),
        );
    }

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return [
            'key' => $this->key,
            'label' => $this->label,
            'type' => $this->type->value,
            'scope' => $this->scope->value,
            'required' => $this->required,
            'max_length' => $this->maxLength,
            'default' => $this->default,
            'show_on_verify' => $this->showOnVerify,
        ];
    }

    /** @return list<string> Laravel validation rules for one value of this field. */
    public function rules(): array
    {
        $rules = [$this->required ? 'required' : 'nullable', ...$this->type->rules()];

        if ($this->maxLength !== null && $this->type === FieldType::Text) {
            $rules[] = 'max:'.$this->maxLength;
        }

        return $rules;
    }

    public function isFixed(): bool
    {
        return $this->scope === FieldScope::Fixed;
    }

    public function isRow(): bool
    {
        return $this->scope === FieldScope::Row;
    }
}
