<?php

namespace Database\Factories;

use App\Enums\FieldScope;
use App\Enums\FieldType;
use App\Enums\TemplateDesign;
use App\Models\Template;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Template>
 */
class TemplateFactory extends Factory
{
    public function definition(): array
    {
        $design = fake()->randomElement(TemplateDesign::cases());
        $name = 'قالب '.$design->label().' '.fake()->unique()->numberBetween(1, 9999);

        return [
            'name' => $name,
            'slug' => Str::slug($design->value.'-'.fake()->unique()->numberBetween(1, 99999)),
            'design' => $design,
            'fields_schema' => self::defaultFields(),
            'layout_config' => Template::defaultLayout($design),
            'is_active' => true,
            'preview_path' => null,
            'created_by' => null,
        ];
    }

    public function design(TemplateDesign $design): static
    {
        return $this->state(fn () => [
            'design' => $design,
            'layout_config' => Template::defaultLayout($design),
        ]);
    }

    public function inactive(): static
    {
        return $this->state(fn () => ['is_active' => false]);
    }

    /**
     * The schema most ASFEC certificates need: which event, when, what kind.
     *
     * @return list<array<string, mixed>>
     */
    public static function defaultFields(): array
    {
        return [
            [
                'key' => 'event_name',
                'label' => 'اسم الفعالية',
                'type' => FieldType::Text->value,
                'scope' => FieldScope::Fixed->value,
                'required' => true,
                'max_length' => 160,
                'default' => null,
                'show_on_verify' => true,
            ],
            [
                'key' => 'event_date',
                'label' => 'تاريخ الفعالية',
                'type' => FieldType::Date->value,
                'scope' => FieldScope::Fixed->value,
                'required' => true,
                'max_length' => null,
                'default' => null,
                'show_on_verify' => true,
            ],
            [
                'key' => 'event_type',
                'label' => 'نوع المشاركة',
                'type' => FieldType::Text->value,
                'scope' => FieldScope::Fixed->value,
                'required' => false,
                'max_length' => 80,
                'default' => 'الحضور والمشاركة',
                'show_on_verify' => false,
            ],
        ];
    }
}
