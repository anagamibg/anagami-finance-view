<?php

/*
|--------------------------------------------------------------------------
| Anagami Finance View (S6 / P7): HTTP security headers
|--------------------------------------------------------------------------
| Read by App\Http\Middleware\Databackd\SecurityHeaders (global middleware, see app/Http/Kernel.php).
| Every value has a safe default, so nothing has to be set in .env. The .env switches below are optional.
|
| A header that a controller/middleware already put on the response is never overwritten.
| The Content-Security-Policy is delivered ONLY as Content-Security-Policy-Report-Only: Anagami Finance View relies on a lot of
| inline scripts/styles and a handful of CDNs, so the policy is observed first (browser console / report-uri) and
| tightened later. Do not switch it to an enforcing header before the reports are quiet.
*/

/** Comma separated .env list -> array (for the *_EXTRA switches). */
$list = static fn(string $key): array => array_values(array_filter(array_map('trim', explode(',', (string)env($key, '')))));

return [

    // Master switch. false = the middleware does nothing at all.
    'enabled' => env('SECURITY_HEADERS_ENABLED', true),

    // X-Content-Type-Options (leave 'nosniff'; empty string = do not send).
    'x_content_type_options' => env('SECURITY_X_CONTENT_TYPE_OPTIONS', 'nosniff'),

    // X-Frame-Options for HTML responses (SAMEORIGIN | DENY | empty string = do not send).
    'x_frame_options' => env('SECURITY_X_FRAME_OPTIONS', 'SAMEORIGIN'),

    // Public forms that customers embed with an <iframe> on their own sites (the snippets are generated in
    // Leads -> Lead form and Tickets -> Ticket form). They get neither X-Frame-Options nor the CSP frame-ancestors
    // directive. Request paths without a leading slash; "*" is a wildcard. Only GET/HEAD requests are exempt.
    'frameable_paths' => [
        'lead-form/*',
        'ticket-form/*',
    ],

    'referrer_policy' => env('SECURITY_REFERRER_POLICY', 'strict-origin-when-cross-origin'),

    // The employee dashboard (clock in with location) and the login page use navigator.geolocation; nothing in the
    // app uses the camera or the microphone. Empty string = do not send.
    'permissions_policy' => env('SECURITY_PERMISSIONS_POLICY', 'camera=(), microphone=(), geolocation=(self), payment=(self), usb=()'),

    // Strict-Transport-Security. Sent only on HTTPS requests AND when APP_URL itself starts with https://
    // (so never on the local http://127.0.0.1:8123 environment).
    'hsts' => [
        'enabled' => env('SECURITY_HSTS_ENABLED', true),
        'max_age' => (int)env('SECURITY_HSTS_MAX_AGE', 31536000),
        // AFV (S10): off by default - the production host is shared and some of its sub-domains may still be
        // served over plain HTTP; includeSubDomains would make browsers refuse them for max_age. Set
        // SECURITY_HSTS_INCLUDE_SUBDOMAINS=true only once every sub-domain of the host is reachable over HTTPS.
        'include_subdomains' => env('SECURITY_HSTS_INCLUDE_SUBDOMAINS', false),
        // "preload" is deliberately off: it is practically irreversible. Enable only after a conscious decision.
        'preload' => env('SECURITY_HSTS_PRELOAD', false),
    ],

    // Content-Security-Policy-Report-Only (HTML responses only).
    'csp_report_only' => [
        'enabled' => env('SECURITY_CSP_REPORT_ONLY', true),

        // Optional collector, e.g. https://example.report-uri.com/r/d/csp/reportOnly (adds report-uri to the header).
        'report_uri' => env('SECURITY_CSP_REPORT_URI'),

        // directive => list of sources. An empty list removes the directive. Add hosts without editing the file with
        // SECURITY_CSP_SCRIPT_SRC_EXTRA=https://a.example,https://b.example (same for _STYLE_, _CONNECT_, _FRAME_, _IMG_).
        'directives' => [
            'default-src' => ["'self'"],

            // AFV renders a lot of inline <script> blocks and uses eval-style code in vendored plugins; the
            // CDN list is taken from resources/views (layouts, front pages, invoice/estimate pages, payment pages).
            'script-src' => array_merge([
                "'self'", "'unsafe-inline'", "'unsafe-eval'",
                'https://cdn.jsdelivr.net', 'https://cdnjs.cloudflare.com', 'https://code.jquery.com',
                'https://ajax.googleapis.com', 'https://maxcdn.bootstrapcdn.com', 'https://unpkg.com',
                'https://use.fontawesome.com', 'https://cdn.dhtmlx.com', 'https://code.jscharting.com',
                'https://js.pusher.com', 'https://cdn.onesignal.com', 'https://onesignal.com',
                'https://www.google.com', 'https://www.gstatic.com', 'https://maps.googleapis.com',
                'https://www.googletagmanager.com', 'https://www.google-analytics.com',
                'https://js.stripe.com', 'https://checkout.razorpay.com', 'https://js.paystack.co',
                'https://checkout.flutterwave.com', 'https://www.paypal.com',
            ], $list('SECURITY_CSP_SCRIPT_SRC_EXTRA')),

            'style-src' => array_merge([
                "'self'", "'unsafe-inline'",
                'https://fonts.googleapis.com', 'https://cdn.jsdelivr.net', 'https://cdnjs.cloudflare.com',
                'https://maxcdn.bootstrapcdn.com', 'https://use.fontawesome.com', 'https://cdn.dhtmlx.com',
                'https://unpkg.com',
            ], $list('SECURITY_CSP_STYLE_SRC_EXTRA')),

            'font-src' => ["'self'", 'data:', 'https://fonts.gstatic.com', 'https://cdn.jsdelivr.net',
                'https://cdnjs.cloudflare.com', 'https://maxcdn.bootstrapcdn.com', 'https://use.fontawesome.com'],

            // Avatars, company logos and attachments can live on S3/Wasabi/Gravatar/any https host.
            'img-src' => array_merge(["'self'", 'data:', 'blob:', 'https:'], $list('SECURITY_CSP_IMG_SRC_EXTRA')),
            'media-src' => ["'self'", 'data:', 'blob:', 'https:'],

            // XHR/fetch/WebSocket: own origin, Pusher/Echo (wss), payment APIs, Google Sheets import, etc.
            'connect-src' => array_merge(["'self'", 'https:', 'wss:'], $list('SECURITY_CSP_CONNECT_SRC_EXTRA')),

            'frame-src' => array_merge([
                "'self'", 'https://www.google.com', 'https://maps.google.com', 'https://www.youtube.com',
                'https://miro.com', 'https://js.stripe.com', 'https://checkout.razorpay.com',
                'https://www.paypal.com', 'https://docs.google.com',
            ], $list('SECURITY_CSP_FRAME_SRC_EXTRA')),

            'worker-src' => ["'self'", 'blob:'],
            'manifest-src' => ["'self'"],

            'object-src' => ["'none'"],
            'base-uri' => ["'self'"],

            // Payment gateways that are reached through a form POST / redirect after a form POST.
            'form-action' => array_merge([
                "'self'", 'https://checkout.stripe.com', 'https://www.paypal.com', 'https://www.sandbox.paypal.com',
                'https://www.payfast.co.za', 'https://sandbox.payfast.co.za', 'https://accept.authorize.net',
                'https://test.authorize.net',
            ], $list('SECURITY_CSP_FORM_ACTION_EXTRA')),

            // Dropped automatically for config('security-headers.frameable_paths').
            'frame-ancestors' => ["'self'"],
        ],
    ],

    // Vendor maintenance endpoints (Froiden envato package, unauthenticated by default) that this deployment does not
    // use - see docs/mvp/security/findings/P7.md (P7-02/P7-03). Handled by RestrictVendorMaintenanceRoutes.
    'restricted_routes' => [
        'enabled' => env('SECURITY_RESTRICT_VENDOR_ROUTES', true),

        // 404 for everybody (these routes have no session, so there is no way to tell who is asking).
        'hidden' => [
            'check-env',
            // AFV (upgrade 6.0.19): Froiden's remote switch (vendor/froiden/envato AppBoot::down()/up(), a hash only
            // Froiden knows): "artisan down --secret=froiden" when no purchase code is stored, "artisan up" at any time
            'down/*',
            'up/*',
        ],

        // 404 unless the logged-in user is a super admin. "*" is a wildcard.
        'super_admin_only' => [
            'update-database',
            'clear-cache',
            'refresh-cache',
            'admin/update-version/*',
            'sync-user-permissions', // AFV (S10): runs the artisan command for every company (P7-15)
            // AFV (upgrade 6.0.19): the license check is off (databackd.license_check); guests could send the stored
            // purchase code to Froiden and overwrite it (purchase-verified) or write global_settings (hide-review-modal)
            'verify-purchase',
            'purchase-verified',
            'hide-review-modal/*',
        ],
    ],
];
