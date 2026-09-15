<?php

namespace App\Http\Middleware;

use Illuminate\Http\Middleware\TrustProxies as Middleware;
use Illuminate\Http\Request;

/**
 * Behind nginx/FastPanel every request arrives from 127.0.0.1; without a
 * trusted proxy the IP-based rate limiter would throttle everyone at once.
 * Configure with TRUSTED_PROXIES in .env ("*" trusts every upstream).
 */
class TrustProxies extends Middleware
{
    protected $headers = Request::HEADER_X_FORWARDED_FOR
        | Request::HEADER_X_FORWARDED_HOST
        | Request::HEADER_X_FORWARDED_PORT
        | Request::HEADER_X_FORWARDED_PROTO;

    public function __construct()
    {
        $configured = trim((string) config('app.trusted_proxies', ''));

        if ($configured === '') {
            $this->proxies = null;
        } elseif ($configured === '*' || $configured === '**') {
            $this->proxies = $configured;
        } else {
            $this->proxies = array_values(array_filter(array_map('trim', explode(',', $configured))));
        }
    }
}
