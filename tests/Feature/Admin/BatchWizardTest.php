<?php

use App\Filament\Resources\Batches\Pages\CreateBatch;
use App\Models\Template;
use App\Models\User;
use Livewire\Livewire;

beforeEach(function () {
    $this->actingAs(User::factory()->admin()->create());
});

it('accepts fixed values on the second wizard step', function () {
    $template = Template::factory()->create();

    Livewire::test(CreateBatch::class)
        ->fillForm(['name' => 'دفعة اختبار', 'template_id' => $template->id])
        ->goToNextWizardStep()
        ->assertHasNoFormErrors()
        ->fillForm([
            'fixed_values.event_name' => 'ورشة الرقمنة',
            'fixed_values.event_date' => '2026-09-22',
        ])
        ->goToNextWizardStep()
        ->assertHasNoFormErrors();
});
