<?php

namespace App\Http\Middleware\Databackd;

use Closure;
use Froiden\Envato\Middleware\XSS;
use Illuminate\Http\Request;

/**
 * AFV (S6/P6): drop-in replacement of Froiden\Envato\Middleware\XSS in app/Http/Kernel.php ($middleware).
 *
 * Same sanitising (strip_tags + <script> removal on every POST/PUT input value, same ignore list), two differences:
 *  1. The upstream class puts the cleaned input back with Request::merge(), which is O(n^2) for a big JSON list:
 *     an UNAUTHENTICATED 9 MB JSON array burnt 35+ s of CPU in this middleware alone (measured), i.e. a cheap DoS of every
 *     POST route. Here the input bag is replaced in one linear step.
 *  2. /api/databackd/* is skipped: that API reads the raw body itself (the sanitised copy would be thrown away) and validates
 *     every field strictly (FinEntryApiController / FinEntryImporter).
 */
class XssLinearMerge extends XSS
{

    public function handle(Request $request, Closure $next)
    {
        if (!in_array(strtolower($request->method()), ['put', 'post']) || $request->is('api/databackd/*')) {
            return $next($request);
        }

        $input = $request->all();

        $IGNORE_REQUESTS = config('xss_ignore', ['widget_code']);

        if (class_exists('\Nwidart\Modules\Facades\Module')) {
            $currentModule = \Nwidart\Modules\Facades\Module::allEnabled();

            foreach ($currentModule as $module) {
                $moduleConfig = config(strtolower($module->getName()) . '::xss_ignore', []);
                $IGNORE_REQUESTS = array_unique(array_merge($IGNORE_REQUESTS, $moduleConfig));
            }
        }

        array_walk_recursive($input, function (&$input, $index) use ($IGNORE_REQUESTS) {
            if (!empty($input) && !in_array($index, $IGNORE_REQUESTS)) {
                $input = preg_replace('#<script(.*?)>(.*?)</script>#is', '', strip_tags($input));
            }
        });

        // same effect as $request->merge($input) (add/overwrite keys in the input source), in linear time
        $bag = $request->isJson() ? $request->json() : $request->request;
        $bag->replace(array_replace($bag->all(), $input));

        return $next($request);
    }

}
