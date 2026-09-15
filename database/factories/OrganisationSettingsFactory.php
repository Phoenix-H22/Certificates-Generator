<?php

namespace Database\Factories;

use App\Models\OrganisationSettings;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<OrganisationSettings>
 */
class OrganisationSettingsFactory extends Factory
{
    public function definition(): array
    {
        return OrganisationSettings::defaults();
    }
}
