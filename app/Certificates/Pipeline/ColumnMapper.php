<?php

namespace App\Certificates\Pipeline;

use App\Certificates\Data\FieldSchema;
use App\Certificates\Rendering\DataMerger;
use App\Models\Template;

/**
 * Guesses which spreadsheet column feeds which certificate field, so the
 * admin usually only has to confirm the mapping.
 */
final class ColumnMapper
{
    /** @var array<string, list<string>> field => header synonyms (lower-case) */
    private const SYNONYMS = [
        'name' => ['name', 'full name', 'fullname', 'participant', 'الاسم', 'اسم', 'الاسم الكامل', 'الإسم', 'اسم المشارك', 'الاسم بالكامل'],
        'title' => ['title', 'degree', 'prefix', 'الصفة', 'اللقب', 'الدرجة', 'الدرجة العلمية', 'الوظيفة', 'المسمى'],
        'email' => ['email', 'e-mail', 'mail', 'البريد', 'البريد الإلكتروني', 'البريد الالكتروني', 'الايميل', 'الإيميل', 'ايميل'],
        'phone' => ['phone', 'mobile', 'tel', 'telephone', 'whatsapp', 'الهاتف', 'الجوال', 'الموبايل', 'رقم الهاتف', 'رقم الموبايل', 'رقم الجوال', 'تليفون', 'واتساب', 'رقم الواتساب'],
    ];

    /**
     * @param  list<string>  $headers  raw header names from the sheet
     * @return array<string, string|null> field key => header (or null when unmatched)
     */
    public function suggest(array $headers, Template $template): array
    {
        $map = [];
        $normalised = [];

        foreach ($headers as $header) {
            $normalised[DataMerger::normaliseHeader($header)] = $header;
        }

        foreach (array_keys(FieldSchema::CORE_FIELDS) as $field) {
            $map[$field] = $this->match($normalised, self::SYNONYMS[$field] ?? [$field]);
        }

        foreach ($template->rowFields() as $field) {
            $map[$field->key] = $this->match($normalised, [
                $field->key,
                str_replace('_', ' ', $field->key),
                $field->label,
            ]);
        }

        return $map;
    }

    /**
     * Fields that must be mapped: name + required row-scope template fields.
     *
     * @return list<string>
     */
    public function requiredFields(Template $template): array
    {
        $required = ['name'];

        foreach ($template->rowFields() as $field) {
            if ($field->required) {
                $required[] = $field->key;
            }
        }

        return $required;
    }

    /**
     * @param  array<string, string|null>  $map
     * @return list<string> missing required field keys
     */
    public function missing(array $map, Template $template): array
    {
        return array_values(array_filter(
            $this->requiredFields($template),
            fn (string $key) => empty($map[$key]),
        ));
    }

    /**
     * @param  array<string, string>  $normalised  normalised header => original header
     * @param  list<string>  $candidates
     */
    private function match(array $normalised, array $candidates): ?string
    {
        foreach ($candidates as $candidate) {
            $candidate = DataMerger::normaliseHeader($candidate);

            if (isset($normalised[$candidate])) {
                return $normalised[$candidate];
            }
        }

        // Fall back to "header contains synonym" for things like "Email Address".
        foreach ($candidates as $candidate) {
            $candidate = DataMerger::normaliseHeader($candidate);

            foreach ($normalised as $key => $original) {
                if ($candidate !== '' && str_contains($key, $candidate)) {
                    return $original;
                }
            }
        }

        return null;
    }
}
