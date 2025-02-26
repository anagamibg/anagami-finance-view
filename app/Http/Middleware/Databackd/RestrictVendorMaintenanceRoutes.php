<?php

namespace App\Http\Middleware\Databackd;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * AFV (S6 / P7-02, P7-03): the Froiden envato package registers maintenance endpoints WITHOUT any
 * authentication (vendor/froiden/envato/src/routes.php):
 *   GET /update-database  -> artisan migrate --force
 *   GET /clear-cache      -> config:clear, route:clear, view:clear, cache:clear + cache()->flush()
 *                            (flushing the cache also resets every RateLimiter counter = login/API throttling)
 *   GET /refresh-cache    -> artisan optimize + route:clear
 *   GET /check-env        -> JSON with the first user's e-mail, debug flag, versions and the licence key (all but 5 chars)
 *   GET /admin/update-version/* -> the in-app self-updater (this deployment is updated with rsync instead)
 * The routes cannot be edited (vendor package), so they are closed here.
 *
 * Registered twice (app/Http/Kernel.php):
 *   1. global stack: no session exists yet, so only the "hidden" list can be enforced (check-env has no "web" group);
 *   2. "web" group, after StartSession: the "super_admin_only" list is enforced for the logged-in identity.
 * Everything answers 404 as if the route did not exist. config('security-headers.restricted_routes.enabled') = false
 * switches the middleware off (SECURITY_RESTRICT_VENDOR_ROUTES=false in .env).
 */
class RestrictVendorMaintenanceRoutes
{

    public function handle(Request $request, Closure $next): Response
    {
        if (!config('security-headers.restricted_routes.enabled', true)) {
            return $next($request);
        }

        if ($this->matches($request, (array)config('security-headers.restricted_routes.hidden', []))) {
            abort(404);
        }

        // Without a started session we cannot tell who is asking: the "web" group instance decides.
        if ($request->hasSession() && $this->matches($request, (array)config('security-headers.restricted_routes.super_admin_only', []))) {
            $user = auth()->check() ? user() : null;

            if (!$user || !$user->is_superadmin) {
                abort(404);
            }
        }

        return $next($request);
    }

    private function matches(Request $request, array $patterns): bool
    {
        foreach ($patterns as $pattern) {
            if ($request->is($pattern)) {
                return true;
            }
        }

        return false;
    }

}
