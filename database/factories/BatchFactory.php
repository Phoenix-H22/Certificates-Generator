<?php

namespace Database\Factories;

use App\Enums\BatchStatus;
use App\Models\Batch;
use App\Models\Template;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Batch>
 */
class BatchFactory extends Factory
{
    public function definition(): array
    {
        return [
            'template_id' => Template::factory(),
            'name' => 'دفعة '.fake()->date('Y-m-d').' '.fake()->word(),
            'status' => BatchStatus::Draft,
            'deliver_via' => ['email'],
            'fixed_values' => [
                'event_name' => 'الورشة الإقليمية للرقمنة والتعلم مدى الحياة',
                'event_date' => fake()->date('Y-m-d'),
                'event_type' => 'الحضور والمشاركة',
            ],
            'column_map' => [
                'name' => 'Name',
                'title' => 'Title',
                'email' => 'Email',
                'phone' => 'Phone',
            ],
            'source_path' => null,
            'source_original_name' => 'participants.xlsx',
            'created_by' => User::factory()->admin(),
        ];
    }

    public function status(BatchStatus $status): static
    {
        return $this->state(fn () => ['status' => $status]);
    }
}
