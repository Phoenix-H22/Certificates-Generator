<?php

namespace App\Certificates\Rendering;

use App\Certificates\Data\FieldSchema;
use App\Certificates\Exceptions\InvalidRowException;
use App\Certificates\Support\ArabicText;
use App\Certificates\Support\EmailNormalizer;
use App\Certificates\Support\PhoneNormalizer;
use App\Enums\FieldType;
use App\Models\Template;
use Illuminate\Support\Facades\Validator;

/**
 * Combine template defaults, batch-level fixed values and one spreadsheet
 * row into the flat `data` array stored on a certificate.
 *
 * Precedence (lowest → highest): schema defaults, batch fixed values,
 * row values (row-scope fields only), core recipient fields.
 */
final class DataMerger
{
    /**
     * @param  array<string, mixed>  $fixedValues  batch-level values keyed by field key
     * @param  array<string, mixed>  $row  one spreadsheet row keyed by (normalised) header
     * @param  array<string, string|null>  $columnMap  field key => spreadsheet header
     * @return array<string, mixed>
     *
     * @throws InvalidRowException
     */
    public function merge(Template $template, array $fixedValues, array $row, array $columnMap, int $rowNumber = 0): array
    {
        $schema = $template->fields_schema;
        $row = self::normaliseHeaders($row);
        $columnMap = array_map(fn ($h) => $h === null ? null : self::normaliseHeader((string) $h), $columnMap);

        $data = $schema->defaults();

        foreach ($schema->fixed() as $field) {
            if (array_key_exists($field->key, $fixedValues) && $fixedValues[$field->key] !== null && $fixedValues[$field->key] !== '') {
                $data[$field->key] = $this->clean($fixedValues[$field->key], $field->type);
            }
        }

        foreach ($schema->row() as $field) {
            $value = $this->cell($row, $columnMap[$field->key] ?? null);

            if ($value !== null && $value !== '') {
                $data[$field->key] = $this->clean($value, $field->type);
            }
        }

        $data['name'] = $this->cleanText($this->cell($row, $columnMap['name'] ?? null));
        $data['title'] = $this->cleanText($this->cell($row, $columnMap['title'] ?? null));
        $email = EmailNormalizer::normalize($this->cell($row, $columnMap['email'] ?? null));
        $data['email'] = EmailNormalizer::isValid($email) ? $email : null;
        $data['phone'] = PhoneNormalizer::normalize($this->cell($row, $columnMap['phone'] ?? null));

        $this->validate($schema, $data, $rowNumber);

        return $data;
    }

    /**
     * @param  array<string, mixed>  $data
     *
     * @throws InvalidRowException
     */
    private function validate(FieldSchema $schema, array $data, int $rowNumber): void
    {
        $rules = ['name' => ['required', 'string', 'max:255']] + $schema->rowRules();

        // Fixed fields were validated when the batch was created; only check
        // that required ones actually made it through (defaults included).
        foreach ($schema->fixed() as $field) {
            if ($field->required) {
                $rules[$field->key] = ['required'];
            }
        }

        $validator = Validator::make($data, $rules);

        if ($validator->fails()) {
            throw new InvalidRowException($rowNumber, $validator->errors()->toArray());
        }
    }

    private function cell(array $row, ?string $header): mixed
    {
        if ($header === null || $header === '') {
            return null;
        }

        return $row[$header] ?? null;
    }

    private function clean(mixed $value, FieldType $type): mixed
    {
        return match ($type) {
            FieldType::Date => ArabicText::toIsoDate($value) ?? $this->cleanText($value),
            FieldType::Email => EmailNormalizer::normalize($value),
            FieldType::Phone => PhoneNormalizer::normalize($value) ?? $this->cleanText($value),
            FieldType::Number => is_numeric($value) ? $value + 0 : $this->cleanText($value),
            FieldType::Text => $this->cleanText($value),
        };
    }

    private function cleanText(mixed $value): ?string
    {
        if ($value === null) {
            return null;
        }

        if ($value instanceof \DateTimeInterface) {
            return $value->format('Y-m-d');
        }

        $text = trim((string) $value);
        // Strip bidi/zero-width control characters that break shaping, keep everything else.
        $text = preg_replace('/[\x{200B}-\x{200D}\x{202A}-\x{202E}\x{2066}-\x{2069}\x{FEFF}]/u', '', $text) ?? $text;
        $text = preg_replace('/\s+/u', ' ', $text) ?? $text;

        return $text === '' ? null : $text;
    }

    /** Lower-case, trimmed header names so "Name", " name " and "NAME" all match. */
    public static function normaliseHeader(string $header): string
    {
        return mb_strtolower(trim(preg_replace('/\s+/u', ' ', $header) ?? $header));
    }

    /**
     * @param  array<string, mixed>  $row
     * @return array<string, mixed>
     */
    public static function normaliseHeaders(array $row): array
    {
        $out = [];

        foreach ($row as $header => $value) {
            $out[self::normaliseHeader((string) $header)] = $value;
        }

        return $out;
    }
}
