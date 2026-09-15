<?php

namespace App\Providers;

use App\Certificates\Delivery\WhatsAppSender;
use App\Certificates\Rendering\BrowsershotFactory;
use App\Models\User;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\RateLimiter;
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
        Gate::define('admin', fn (User $user) => $user->is_admin);

        RateLimiter::for('verify', fn (Request $request) => Limit::perMinute(30)->by($request->ip()));
        RateLimiter::for('downloads', fn (Request $request) => Limit::perMinute(20)->by($request->ip()));

        // Queue-side limiter for the WhatsApp gateway (see DeliverCertificate).
        RateLimiter::for('whatsapp', fn () => Limit::perMinute((int) config('certificates.delivery.whatsapp_rate_per_minute', 20)));
    }
}
