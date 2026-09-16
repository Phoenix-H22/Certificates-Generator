<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Third Party Services
    |--------------------------------------------------------------------------
    */

    'postmark' => [
        'key' => env('POSTMARK_API_KEY'),
    ],

    'resend' => [
        'key' => env('RESEND_API_KEY'),
    ],

    'ses' => [
        'key' => env('AWS_ACCESS_KEY_ID'),
        'secret' => env('AWS_SECRET_ACCESS_KEY'),
        'region' => env('AWS_DEFAULT_REGION', 'us-east-1'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Certificate delivery
    |--------------------------------------------------------------------------
    */

    // Receives batch completion reports and error spreadsheets.
    'admin_email' => env('ADMIN_EMAIL'),

    // Only read by the AdminUserSeeder to bootstrap the first admin account.
    'admin_password' => env('ADMIN_PASSWORD'),

    // Form-POST WhatsApp gateway: appkey / authkey / to / message / file (URL).
    // Set WHATSAPP_ENABLED=false to pause the channel: jobs record "skipped"
    // instead of calling the gateway (no errors, no retries).
    'whatsapp' => [
        'enabled' => filter_var(env('WHATSAPP_ENABLED', true), FILTER_VALIDATE_BOOLEAN),
        'url' => env('WHATSAPP_APP_URL'),
        'app_key' => env('WHATSAPP_APP_KEY'),
        'auth_key' => env('WHATSAPP_APP_SECRET'),
        'timeout' => (int) env('WHATSAPP_TIMEOUT', 15),
    ],

];
