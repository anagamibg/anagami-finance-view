<?php

namespace App\Http\Middleware\Databackd;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * databackd: the SaaS billing pages (account/settings/billing/*) are hidden for the company accounts while
 * config/databackd.php -> hidden.pages.billing is true. Answers 404 as if the page did not exist.
 * The super admin is not affected (databackd_hidden() returns false for them).
 */
class HideBillingPages
{

    public function handle(Request $request, Closure $next): Response
    {
        if (databackd_hidden('pages', 'billing')) {
            abort(404);
        }

        return $next($request);
    }

}
