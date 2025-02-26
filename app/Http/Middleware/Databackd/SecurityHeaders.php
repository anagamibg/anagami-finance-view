<?php

namespace App\Http\Middleware\Databackd;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * AFV (S6 / P7): HTTP security headers on every response (global middleware, app/Http/Kernel.php).
 * Everything is driven by config/security-headers.php (safe defaults, optional .env switches).
 *
 *  - X-Content-Type-Options, Referrer-Policy, Permissions-Policy: all responses (files and JSON included)
 *  - X-Frame-Options + Content-Security-Policy-Report-Only: HTML responses only (not for files/JSON/streams)
 *  - Strict-Transport-Security: only for HTTPS requests when APP_URL is https:// too (never on http://127.0.0.1:8123)
 *  - the public lead/ticket forms that customers embed with an <iframe> (config: frameable_paths) get no
 *    X-Frame-Options and no CSP frame-ancestors
 *  - a header that is already on the response (set by a controller or another middleware) is never overwritten
 *
 * The CSP is report-only on purpose (see config/security-headers.php). The X-Robots-Tag header is sent by
 * public/index.php (and public/.htaccess for static files) and is not touched here.
 */
class SecurityHeaders
{

    public function handle(Request $request, Closure $next): Response
    {
        // Read APP_URL before any controller runs: AFV's base controller rewrites config('app.url') per request.
        $appUrl = (string)config('app.url');

        $response = $next($request);

        if (!config('security-headers.enabled', true) || !$response instanceof Response) {
            return $response;
        }

        try {
            $this->apply($request, $response, $appUrl);
        } catch (\Throwable $e) {
            // Headers are an extra layer: a failure here must never turn a page into an error.
            report($e);
        }

        return $response;
    }

    private function apply(Request $request, Response $response, string $appUrl): void
    {
        $headers = $response->headers;

        $this->setIfMissing($response, 'X-Content-Type-Options', config('security-headers.x_content_type_options'));
        $this->setIfMissing($response, 'Referrer-Policy', config('security-headers.referrer_policy'));
        $this->setIfMissing($response, 'Permissions-Policy', config('security-headers.permissions_policy'));

        if (config('security-headers.hsts.enabled', true) && $request->isSecure() && str_starts_with(strtolower($appUrl), 'https://')) {
            $value = 'max-age=' . max(0, (int)config('security-headers.hsts.max_age', 31536000));

            if (config('security-headers.hsts.include_subdomains', false)) {
                $value .= '; includeSubDomains';
            }

            if (config('security-headers.hsts.preload', false)) {
                $value .= '; preload';
            }

            $this->setIfMissing($response, 'Strict-Transport-Security', $value);
        }

        if (!$this->isHtmlDocument($response)) {
            return;
        }

        $frameable = $this->isFrameable($request);

        if (!$frameable) {
            $this->setIfMissing($response, 'X-Frame-Options', config('security-headers.x_frame_options'));
        }

        if (config('security-headers.csp_report_only.enabled', true) && !$headers->has('Content-Security-Policy-Report-Only')) {
            $policy = $this->buildPolicy($frameable);

            if ($policy !== '') {
                $headers->set('Content-Security-Policy-Report-Only', $policy);
            }
        }
    }

    private function setIfMissing(Response $response, string $name, $value): void
    {
        if (is_string($value) && $value !== '' && !$response->headers->has($name)) {
            $response->headers->set($name, $value);
        }
    }

    /** X-Frame-Options and CSP only make sense for documents: not for downloads, JSON, redirects without a body or streams. */
    private function isHtmlDocument(Response $response): bool
    {
        if ($response instanceof BinaryFileResponse || $response instanceof StreamedResponse || $response->isRedirection()) {
            return false;
        }

        return str_contains(strtolower((string)$response->headers->get('Content-Type', '')), 'html');
    }

    /** Only GET/HEAD pages of the embeddable public forms; the form POST (which is not framed) is not exempt. */
    private function isFrameable(Request $request): bool
    {
        if (!$request->isMethodSafe()) {
            return false;
        }

        foreach ((array)config('security-headers.frameable_paths', []) as $pattern) {
            if ($request->is($pattern)) {
                return true;
            }
        }

        return false;
    }

    private function buildPolicy(bool $frameable): string
    {
        $parts = [];

        foreach ((array)config('security-headers.csp_report_only.directives', []) as $directive => $sources) {
            if ($frameable && $directive === 'frame-ancestors') {
                continue;
            }

            $sources = array_values(array_unique(array_filter((array)$sources, fn($source) => is_string($source) && $source !== '')));

            if ($sources === []) {
                continue;
            }

            $parts[] = $directive . ' ' . implode(' ', $sources);
        }

        if ($parts === []) {
            return '';
        }

        $reportUri = config('security-headers.csp_report_only.report_uri');

        if (is_string($reportUri) && $reportUri !== '' && preg_match('#^(https?://|/)[^\s;,]+$#', $reportUri)) {
            $parts[] = 'report-uri ' . $reportUri;
        }

        return implode('; ', $parts);
    }

}
