<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Storage
    |--------------------------------------------------------------------------
    |
    | Private disk holding uploaded sheets, rendered PDFs, ZIP archives and
    | error reports. Files on this disk are never served directly; they are
    | streamed through authorised or signed routes.
    |
    */

    'disk' => env('CERT_DISK', 'certificates'),

    // Days to keep batch files (PDF/ZIP/source) before the prune command deletes them.
    'retention_days' => (int) env('CERT_RETENTION_DAYS', 180),

    /*
    |--------------------------------------------------------------------------
    | Upload limits
    |--------------------------------------------------------------------------
    */

    'max_upload_mb' => (int) env('CERT_MAX_UPLOAD_MB', 20),
    'max_rows' => (int) env('CERT_MAX_ROWS', 5000),

    /*
    |--------------------------------------------------------------------------
    | Browsershot / Chromium
    |--------------------------------------------------------------------------
    |
    | All paths are optional. When null, Browsershot resolves `node`/`npm`
    | from PATH and uses the Chrome bundled with the `puppeteer` npm package.
    |
    */

    'browsershot' => [
        'node_binary' => env('CERT_NODE_BINARY'),
        'npm_binary' => env('CERT_NPM_BINARY'),
        'chrome_path' => env('CERT_CHROME_PATH'),
        'node_modules_path' => env('CERT_NODE_MODULES_PATH'),
        'timeout' => (int) env('CERT_RENDER_TIMEOUT', 90),
        'no_sandbox' => (bool) env('CERT_NO_SANDBOX', false),
        'remote_ws' => env('CERT_REMOTE_WS'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Delivery
    |--------------------------------------------------------------------------
    */

    'delivery' => [
        // Lifetime of the signed public download link sent by email / WhatsApp.
        'link_ttl_days' => (int) env('CERT_LINK_TTL_DAYS', 30),
        'whatsapp_rate_per_minute' => (int) env('CERT_WHATSAPP_RATE_PER_MINUTE', 20),
    ],

    /*
    |--------------------------------------------------------------------------
    | Queues
    |--------------------------------------------------------------------------
    */

    /*
    |--------------------------------------------------------------------------
    | Public endpoint rate limits (per IP unless stated)
    |--------------------------------------------------------------------------
    */

    'security' => [
        'verify_per_minute' => (int) env('CERT_VERIFY_PER_MINUTE', 10),
        'verify_per_hour' => (int) env('CERT_VERIFY_PER_HOUR', 60),
        'verify_global_per_minute' => (int) env('CERT_VERIFY_GLOBAL_PER_MINUTE', 300),
        'downloads_per_minute' => (int) env('CERT_DOWNLOADS_PER_MINUTE', 10),
        'downloads_per_hour' => (int) env('CERT_DOWNLOADS_PER_HOUR', 60),
    ],

    'queues' => [
        'render' => env('CERT_RENDER_QUEUE', 'render'),
        'deliver' => env('CERT_DELIVER_QUEUE', 'deliver'),
    ],

];
