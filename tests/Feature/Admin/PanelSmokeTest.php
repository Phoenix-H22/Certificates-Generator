<?php

use App\Models\Batch;
use App\Models\BrandAsset;
use App\Models\Certificate;
use App\Models\Template;
use App\Models\User;

beforeEach(function () {
    $this->admin = User::factory()->admin()->create();
});

it('blocks non-admins from the panel', function () {
    $user = User::factory()->create();

    // Filament either aborts with 403 or bounces the user back to the login page.
    $status = $this->actingAs($user)->get('/admin')->getStatusCode();
    expect($status)->toBeIn([302, 403]);

    auth()->logout();
    $this->get('/admin')->assertRedirect('/admin/login');
});

it('renders every panel page for an admin', function () {
    $template = Template::factory()->create();
    BrandAsset::factory()->logo()->create();
    $batch = Batch::factory()->create(['template_id' => $template->id]);
    $certificate = Certificate::factory()->rendered()->create(['batch_id' => $batch->id, 'template_id' => $template->id]);

    $this->actingAs($this->admin);

    foreach ([
        '/admin',
        '/admin/templates',
        '/admin/templates/create',
        "/admin/templates/{$template->id}/edit",
        '/admin/brand-assets',
        '/admin/brand-assets/create',
        '/admin/batches',
        '/admin/batches/create',
        "/admin/batches/{$batch->id}",
        '/admin/certificates',
        "/admin/certificates/{$certificate->uuid}",
        '/admin/organisation-settings-page',
        '/admin/issue-certificate',
    ] as $url) {
        $this->get($url)->assertOk();
    }
});

it('shows the admin preview for a template', function () {
    $template = Template::factory()->create();

    $this->actingAs($this->admin)
        ->get(route('templates.preview', $template))
        ->assertOk()
        ->assertSee('dir="rtl"', false)
        ->assertSee('class="qr', false);

    $this->actingAs(User::factory()->create())
        ->get(route('templates.preview', $template))
        ->assertForbidden();
});
