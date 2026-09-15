<?php

use App\Models\Certificate;
use App\Models\OrganisationSettings;
use Illuminate\Support\Facades\RateLimiter;

beforeEach(function () {
    RateLimiter::clear('verify');
    config()->set('certificates.security.verify_per_minute', 10);
    config()->set('certificates.security.verify_per_hour', 60);
    config()->set('certificates.security.verify_global_per_minute', 300);
});

it('sends hardening headers on public pages', function () {
    $certificate = Certificate::factory()->rendered()->create();

    $response = $this->get(route('verify.show', $certificate->uuid))->assertOk();

    $response->assertHeader('X-Frame-Options', 'DENY')
        ->assertHeader('X-Content-Type-Options', 'nosniff')
        ->assertHeader('Referrer-Policy', 'no-referrer')
        ->assertHeader('X-Robots-Tag', 'noindex, nofollow');

    expect($response->headers->get('Content-Security-Policy'))
        ->toContain("script-src 'none'")
        ->toContain("frame-ancestors 'none'")
        ->toContain("form-action 'self'");

    expect($response->headers->get('Cache-Control'))->toContain('no-store');
});

it('escapes attacker-controlled text on the page', function () {
    $certificate = Certificate::factory()->rendered()->create([
        'recipient_name' => '<script>alert(1)</script> أحمد',
        'recipient_title' => '"><img src=x onerror=alert(2)>',
    ]);

    OrganisationSettings::current()->update(['verification_footer' => '<b onmouseover=alert(3)>x</b>']);

    $html = $this->get(route('verify.show', $certificate->uuid))->assertOk()->getContent();

    expect($html)->not->toContain('<script>alert(1)</script>')
        ->not->toContain('<img src=x')
        ->not->toContain('<b onmouseover')
        ->toContain('&lt;script&gt;alert(1)&lt;/script&gt;');
});

it('refuses identifiers that are not a uuid or a short code without querying', function (string $input) {
    $this->get('/verify/'.rawurlencode($input))->assertNotFound();
})->with([
    'sql-ish' => ["' OR 1=1 --"],
    'too long' => [str_repeat('A', 65)],
    'html' => ['<script>x</script>'],
    'almost a code' => ['ABCDE-FGH2'],
]);

it('rejects malformed search input with a friendly message instead of redirecting', function () {
    $this->get('/verify?code='.rawurlencode("'; DROP TABLE certificates; --"))
        ->assertOk()
        ->assertSee('الصيغة غير صحيحة')
        ->assertDontSee("'; DROP TABLE", false)          // never echoed raw…
        ->assertSee('&#039;; DROP TABLE', false);         // …only HTML-escaped inside the input value

    $this->get('/verify?code='.rawurlencode(str_repeat('x', 300)))->assertSessionHasErrors('code');
});

it('accepts the search form only via GET with a bounded code', function () {
    $this->post('/verify', ['code' => 'ABCDE-FGH23'])->assertStatus(405);
});

it('throttles bursts per ip and returns the arabic 429 page', function () {
    $certificate = Certificate::factory()->rendered()->create();

    for ($i = 0; $i < 10; $i++) {
        $this->get(route('verify.show', $certificate->uuid))->assertOk();
    }

    $this->get(route('verify.show', $certificate->uuid))
        ->assertStatus(429)
        ->assertSee('محاولات كثيرة');
});

it('applies a global ceiling across all visitors', function () {
    config()->set('certificates.security.verify_per_minute', 1000);
    config()->set('certificates.security.verify_global_per_minute', 3);

    $certificate = Certificate::factory()->rendered()->create();

    foreach (['10.0.0.1', '10.0.0.2', '10.0.0.3'] as $ip) {
        $this->withServerVariables(['REMOTE_ADDR' => $ip])->get(route('verify.show', $certificate->uuid))->assertOk();
    }

    $this->withServerVariables(['REMOTE_ADDR' => '10.0.0.4'])->get(route('verify.show', $certificate->uuid))->assertStatus(429);
});
