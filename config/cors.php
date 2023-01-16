<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Cross-Origin Resource Sharing (CORS) Configuration
    |--------------------------------------------------------------------------
    |
    | Here you may configure your settings for cross-origin resource sharing
    | or "CORS". This determines what cross-origin operations may execute
    | in web browsers. You are free to adjust these settings as needed.
    |
    | To learn more: https://developer.mozilla.org/en-US/docs/Web/HTTP/CORS
    |
    */

    /*
     * IMPORTANT:
     * Some CORS matchers treat `*` as "one segment", so `api/*` won't match
     * nested routes like `api/v1/auth/login`. Add common nested depths.
     *
     * AFV (S6/P6): /api/databackd/* is a server-to-server API (Bearer token, no cookies, no browser client). It is
     * excluded from CORS by App\Http\Middleware\Databackd\FinApiGate (global middleware): preflights get no Access-Control-*
     * headers and the headers added by HandleCors / ForceCorsForApi are removed. The paths below apply to the other
     * api/* routes only. Session routes (/account/*, /login ...) are not listed on purpose: they never get CORS headers.
     */
    'paths' => [
        'api/*',
        'api/*/*',
        'api/*/*/*',
        'api/*/*/*/*',
        'sanctum/csrf-cookie'
    ],

    'allowed_methods' => ['*'],

    'allowed_origins' => ['*'],

    'allowed_origins_patterns' => [],

    'allowed_headers' => ['*'],

    'exposed_headers' => [],

    'max_age' => 0,

    'supports_credentials' => false,

];
