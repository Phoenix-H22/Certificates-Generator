<?php

namespace App\Casts;

use App\Certificates\Data\FieldSchema;
use Illuminate\Contracts\Database\Eloquent\CastsAttributes;
use Illuminate\Database\Eloquent\Model;
use InvalidArgumentException;

/**
 * templates.fields_schema (JSON) <-> FieldSchema value object.
 *
 * @implements CastsAttributes<FieldSchema, FieldSchema|iterable<mixed>|null>
 */
class FieldSchemaCast implements CastsAttributes
{
    public function get(Model $model, string $key, mixed $value, array $attributes): FieldSchema
    {
        if ($value === null || $value === '') {
            return new FieldSchema;
        }

        $decoded = is_string($value) ? json_decode($value, true, 512, JSON_THROW_ON_ERROR) : $value;

        return FieldSchema::fromArray((array) $decoded);
    }

    public function set(Model $model, string $key, mixed $value, array $attributes): string
    {
        if ($value instanceof FieldSchema) {
            return json_encode($value->toArray(), JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE);
        }

        if ($value === null) {
            return '[]';
        }

        if (is_iterable($value)) {
            // Validate through the VO so bad keys fail at save time, not render time.
            return json_encode(FieldSchema::fromArray($value)->toArray(), JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE);
        }

        throw new InvalidArgumentException('fields_schema must be a FieldSchema or an array of field definitions.');
    }
}
