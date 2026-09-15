<?php

use App\Models\Certificate;
use Illuminate\Support\Facades\RateLimiter;

it('shows a valid certificate by uuid', function () {
    $certificate = Certificate::factory()->rendered()->create(['recipient_name' => 'أحمد محمود']);

    $this->get(route('verify.show', $certificate->uuid))
        ->assertOk()
        ->assertSee('شهادة صحيحة')
        ->assertSee('أحمد محمود')
        ->assertSee($certificate->code)
        ->assertSee('الورشة الإقليمية');  // fixed field from the factory data marked show_on_verify

    expect($certificate->fresh()->verified_count)->toBe(1);
});

it('accepts the short code in any casing or spacing', function () {
    $certificate = Certificate::factory()->rendered()->create();

    $sloppy = strtolower(str_replace('-', ' ', $certificate->code));

    $this->get('/verify/'.rawurlencode($sloppy))->assertOk()->assertSee('شهادة صحيحة');
});

it('redirects the search form to the canonical url', function () {
    $certificate = Certificate::factory()->rendered()->create();

    $this->get('/verify?code='.strtolower($certificate->code))
        ->assertRedirect(route('verify.show', $certificate->code));

    $this->get('/verify')->assertOk()->assertSee('التحقق من صحة شهادة');
});

it('flags revoked certificates', function () {
    $certificate = Certificate::factory()->rendered()->revoked('أُصدرت بالخطأ')->create();

    $this->get(route('verify.show', $certificate->uuid))
        ->assertOk()
        ->assertSee('شهادة ملغاة')
        ->assertSee('أُصدرت بالخطأ');
});

it('returns 404 for unknown identifiers', function () {
    $this->get('/verify/ABCDE-FGH23')->assertNotFound()->assertSee('لم يتم العثور على الشهادة');
    $this->get('/verify/'.fake()->uuid())->assertNotFound();
});

it('counts a verification once per hour per visitor', function () {
    $certificate = Certificate::factory()->rendered()->create();

    $this->get(route('verify.show', $certificate->uuid));
    $this->get(route('verify.show', $certificate->uuid));

    expect($certificate->fresh()->verified_count)->toBe(1);
});

it('is rate limited', function () {
    RateLimiter::clear('verify');
    config()->set('certificates.security.verify_per_minute', 5);
    $certificate = Certificate::factory()->rendered()->create();

    for ($i = 0; $i < 5; $i++) {
        $this->get(route('verify.show', $certificate->uuid))->assertOk();
    }

    $this->get(route('verify.show', $certificate->uuid))->assertStatus(429);
});
