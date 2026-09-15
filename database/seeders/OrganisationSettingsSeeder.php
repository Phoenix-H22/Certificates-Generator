<?php

namespace Database\Seeders;

use App\Models\OrganisationSettings;
use Illuminate\Database\Seeder;

class OrganisationSettingsSeeder extends Seeder
{
    public function run(): void
    {
        OrganisationSettings::current();
    }
}
