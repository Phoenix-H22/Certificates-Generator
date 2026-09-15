<?php

namespace Database\Factories;

use App\Enums\BrandAssetType;
use App\Models\BrandAsset;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<BrandAsset>
 */
class BrandAssetFactory extends Factory
{
    public function definition(): array
    {
        return [
            'type' => BrandAssetType::Logo,
            'name' => 'شعار '.fake()->company(),
            'role' => null,
            'path' => 'brand/'.fake()->uuid().'.png',
            'path_light' => null,
            'width_mm' => 30,
            'sort' => 0,
            'is_active' => true,
        ];
    }

    public function logo(): static
    {
        return $this->state(fn () => ['type' => BrandAssetType::Logo]);
    }

    public function signature(?string $name = null, ?string $role = null): static
    {
        return $this->state(fn () => [
            'type' => BrandAssetType::Signature,
            'name' => $name ?? 'د/ '.fake()->name(),
            'role' => $role ?? 'مدير المركز',
            'width_mm' => 40,
        ]);
    }

    public function stamp(): static
    {
        return $this->state(fn () => [
            'type' => BrandAssetType::Stamp,
            'name' => 'ختم المركز',
            'width_mm' => 38,
        ]);
    }

    public function background(): static
    {
        return $this->state(fn () => [
            'type' => BrandAssetType::Background,
            'name' => 'خلفية',
            'width_mm' => null,
        ]);
    }

    public function inactive(): static
    {
        return $this->state(fn () => ['is_active' => false]);
    }
}
