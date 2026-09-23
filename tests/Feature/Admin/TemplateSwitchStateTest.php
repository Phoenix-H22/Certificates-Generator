<?php

use App\Filament\Pages\IssueCertificatePage;
use App\Filament\Resources\Batches\Pages\CreateBatch;
use App\Models\Template;
use App\Models\User;
use Livewire\Livewire;

/*
 * Switching the template resets the nested form state. It must stay a keyed array
 * (a JS object in the browser): null or [] silently drops what the user types into
 * recipient.* / fixed_values.*, and submitting then fails with "field is required".
 */

beforeEach(function () {
    $this->actingAs(User::factory()->admin()->create());
});

function expectKeyedObjectState(mixed $state, array $keys): void
{
    expect($state)->toBeArray();
    foreach ($keys as $key) {
        expect($state)->toHaveKey($key);
    }
    expect(json_encode($state))->toStartWith('{');
}

it('keeps recipient and fixed values as keyed objects after switching template on the single issue page', function () {
    $first = Template::factory()->create();
    $second = Template::factory()->create();

    $component = Livewire::test(IssueCertificatePage::class)
        ->fillForm(['template_id' => $first->id])
        ->fillForm(['template_id' => $second->id]);

    expectKeyedObjectState($component->get('data.recipient'), ['name', 'title', 'email', 'phone']);
    expectKeyedObjectState(
        $component->get('data.fixed_values'),
        array_map(fn ($field) => $field->key, $second->fixedFields()),
    );
});

it('keeps fixed values and the column map as keyed objects after switching template in the batch wizard', function () {
    $first = Template::factory()->create();
    $second = Template::factory()->create();

    $component = Livewire::test(CreateBatch::class)
        ->fillForm(['name' => 'دفعة', 'template_id' => $first->id])
        ->fillForm(['template_id' => $second->id]);

    expectKeyedObjectState(
        $component->get('data.fixed_values'),
        array_map(fn ($field) => $field->key, $second->fixedFields()),
    );
    expectKeyedObjectState($component->get('data.column_map'), ['name']);
});
