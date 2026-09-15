<?php

namespace App\Providers;

use App\Certificates\Delivery\WhatsAppSender;
use App\Certificates\Rendering\BrowsershotFactory;
use App\Models\User;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->bind(BrowsershotFactory::class, fn () => BrowsershotFactory::fromConfig());
        $this->app->bind(WhatsAppSender::class, fn () => WhatsAppSender::fromConfig());
    }

    public function boot(): void
    {
        if ($this->app->isProduction()) {
            URL::forceScheme('https');
        }

        Gate::define('admin', fn (User $user) => $user->is_admin);

        $this->configureRateLimiting();
    }

    /**
     * Public endpoints are throttled on three levels: a short burst limit
     * per IP, an hourly ceiling per IP, and a global ceiling that blunts
     * distributed enumeration attempts regardless of source.
     */
    private function configureRateLimiting(): void
    {
        RateLimiter::for('verify', fn (Request $request) => [
            Limit::perMinute((int) config('certificates.security.verify_per_minute', 10))->by('verify:m:'.$request->ip()),
            Limit::perHour((int) config('certificates.security.verify_per_hour', 60))->by('verify:h:'.$request->ip()),
            Limit::perMinute((int) config('certificates.security.verify_global_per_minute', 300))->by('verify:global'),
        ]);

        RateLimiter::for('downloads', fn (Request $request) => [
            Limit::perMinute((int) config('certificates.security.downloads_per_minute', 10))->by('dl:m:'.$request->ip()),
            Limit::perHour((int) config('certificates.security.downloads_per_hour', 60))->by('dl:h:'.$request->ip()),
        ]);

        // Queue-side limiter for the WhatsApp gateway (see DeliverCertificate).
        RateLimiter::for('whatsapp', fn () => Limit::perMinute((int) config('certificates.delivery.whatsapp_rate_per_minute', 20)));
    }
}
