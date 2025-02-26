<?php

namespace App\Http\Middleware\Databackd;

use App\Http\Middleware\VerifyCsrfToken;
use Illuminate\Support\Str;

/**
 * AFV (S6 / P2-01): CSRF verification whose exceptions are matched against the request PATH only.
 *
 * Laravel's default matcher also tests the full URL, query string included, with patterns such as "*-webhook"
 * (see VerifyCsrfToken::$except). A forged cross-site POST/PUT/DELETE to any route could therefore skip the
 * CSRF check just by ending its query string with "-webhook" or "_webhook"
 * (e.g. /account/databackd/data/entries/apply-quick-action?x=-webhook). The exception list itself is the upstream
 * one (inherited from App\Http\Middleware\VerifyCsrfToken); real webhooks are matched by their path, as before.
 */
class VerifyCsrfTokenPathOnly extends VerifyCsrfToken
{

    /**
     * @param \Illuminate\Http\Request $request
     */
    protected function inExceptArray($request)
    {
        foreach ($this->getExcludedPaths() as $except) {
            if ($except !== '/') {
                $except = trim($except, '/');
            }

            // the path without and with the leading slash: "*/payfast-notification/*" has to match "/payfast-notification/3"
            // (the upstream matcher got that from the full URL)
            if ($request->is($except) || Str::is($except, '/' . $request->decodedPath())) {
                return true;
            }
        }

        return false;
    }

}
