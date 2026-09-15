<?php

namespace Database\Factories;

use App\Enums\CertificateStatus;
use App\Enums\DeliveryStatus;
use App\Models\Batch;
use App\Models\Certificate;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Certificate>
 */
class CertificateFactory extends Factory
{
    public function definition(): array
    {
        $name = fake()->name();

        return [
            'batch_id' => Batch::factory(),
            'template_id' => fn (array $attrs) => Batch::find($attrs['batch_id'])?->template_id,
            'row_number' => fake()->unique()->numberBetween(1, 100000),
            'recipient_name' => $name,
            'recipient_title' => fake()->randomElement(['الدكتور', 'الأستاذ', 'المهندسة', 'الأستاذة']),
            'email' => fake()->unique()->safeEmail(),
            'phone' => '+2010'.fake()->numerify('########'),
            'data' => [
                'name' => $name,
                'title' => 'الدكتور',
                'event_name' => 'الورشة الإقليمية',
                'event_date' => '2026-05-10',
            ],
            'status' => CertificateStatus::Pending,
        ];
    }

    public function rendered(): static
    {
        return $this->state(fn () => [
            'status' => CertificateStatus::Rendered,
            'pdf_path' => fn (array $attrs) => 'batches/'.$attrs['batch_id'].'/pdf/sample.pdf',
            'rendered_at' => now(),
        ]);
    }

    public function renderFailed(string $error = 'Chromium timed out'): static
    {
        return $this->state(fn () => [
            'status' => CertificateStatus::RenderFailed,
            'render_error' => $error,
        ]);
    }

    public function revoked(?string $reason = 'أُصدرت بالخطأ'): static
    {
        return $this->state(fn () => [
            'status' => CertificateStatus::Revoked,
            'revoked_at' => now(),
            'revoke_reason' => $reason,
        ]);
    }

    public function emailSent(): static
    {
        return $this->state(fn () => ['email_status' => DeliveryStatus::Sent, 'email_sent_at' => now()]);
    }

    public function emailFailed(string $error = 'SMTP rejected'): static
    {
        return $this->state(fn () => ['email_status' => DeliveryStatus::Failed, 'email_error' => $error]);
    }
}
