<?php

/*
 * AFV visual check — screenshots of an app page without a running web server.
 *
 * Usage (from the app root):
 *   php docs/mvp/tools/snapshot.php <url> [options]
 *
 * Examples:
 *   php docs/mvp/tools/snapshot.php /account/databackd/reports/dashboard
 *   php docs/mvp/tools/snapshot.php /account/dashboard --out=/path/to/scratchpad/dashboard
 *   php docs/mvp/tools/snapshot.php /account/databackd/teams/requests --email=p.yordanov@balkan.bg
 *
 * Options:
 *   --email=<email>   Log in as this user (default: first admin of the demo company "Демо Финанс Груп")
 *   --out=<prefix>    Output path prefix (default: storage/app/snapshots/<slug>); writes
 *                     <prefix>.html, <prefix>-desktop.png (1440×900) and <prefix>-mobile.png (390×844)
 *   --only=desktop|mobile   Take one of the two screenshots
 *   --viewport        Capture only the visible viewport (default: the full page, capped at 6000 px)
 *   --wait=<ms>       Extra wait after the page settles (default 900 ms; charts animate)
 *   --dark            Render with the dark theme (sets user()->dark_theme for the request only)
 *   --offline         Block requests to external hosts (CDNs, fonts)
 *   --scss=<file>     Compile this SCSS file (e.g. resources/scss/databackd/_dashboard.scss) with the local sass and
 *                     inject it after main.css — preview CSS changes without `npm run prod` (repeatable)
 *
 * How it works: this script starts `snapshot.js` (Node + Playwright, headless Chromium). The browser
 * opens http://127.0.0.1:8123/<url>, but nothing listens on that port — every request is intercepted:
 * files that exist under public/ are served from disk, everything else (the page itself, DataTables
 * and other AJAX calls) is rendered in-process by a PHP worker (`snapshot.php --worker`) through the
 * Laravel HTTP kernel, logged in as the chosen user (same approach as smoke.php).
 * The rendered HTML is also saved with <base href="file://…/public/"> for inspection.
 *
 * Read the PNGs with the Read tool to see the result.
 */

error_reporting(E_ALL & ~E_DEPRECATED & ~E_USER_DEPRECATED);
ini_set('display_errors', 'stderr');

$root = dirname(__DIR__, 3);
$args = array_slice($argv, 1);

if (in_array('--worker', $args, true)) {
    runWorker($root, $args);
    exit(0);
}

$url = null;
$opts = [];

$scss = [];

foreach ($args as $arg) {
    if (str_starts_with($arg, '--scss=')) {
        $scss[] = substr($arg, 7);
    }
    elseif (str_starts_with($arg, '--')) {
        [$key, $value] = array_pad(explode('=', substr($arg, 2), 2), 2, true);
        $opts[$key] = $value;
    }
    elseif ($url === null) {
        $url = $arg;
    }
}

if ($url === null) {
    fwrite(STDERR, "Usage: php docs/mvp/tools/snapshot.php <url> [--email=] [--out=] [--only=desktop|mobile] [--viewport] [--wait=ms] [--dark] [--offline] [--scss=]\n");
    exit(2);
}

$url = '/' . ltrim(preg_replace('#^https?://[^/]+#', '', $url), '/');

$slug = trim(preg_replace('/[^a-z0-9]+/i', '-', strtolower(parse_url($url, PHP_URL_PATH) . '-' . (parse_url($url, PHP_URL_QUERY) ?? ''))), '-') ?: 'home';
$out = $opts['out'] ?? ($root . '/storage/app/snapshots/' . $slug);

if (!str_starts_with($out, '/')) {
    $out = getcwd() . '/' . $out;
}

@mkdir(dirname($out), 0777, true);

$config = [
    'root' => $root,
    'url' => $url,
    'out' => $out,
    'php' => PHP_BINARY,
    'worker' => __FILE__,
    'email' => is_string($opts['email'] ?? null) ? $opts['email'] : null,
    'only' => is_string($opts['only'] ?? null) ? $opts['only'] : null,
    'fullPage' => !isset($opts['viewport']),
    'wait' => (int)($opts['wait'] ?? 900),
    'dark' => isset($opts['dark']),
    'offline' => isset($opts['offline']),
    'scss' => $scss,
    'playwright' => getenv('PLAYWRIGHT_PATH') ?: '/Users/tsvet/Documents/Projects/kukie.io/kukie/node_modules/playwright',
];

$node = trim((string)shell_exec('command -v node')) ?: 'node';
$cmd = escapeshellarg($node) . ' ' . escapeshellarg(__DIR__ . '/snapshot.js') . ' ' . escapeshellarg(json_encode($config));

passthru($cmd, $exitCode);
exit($exitCode);

/**
 * Long-running renderer: reads one JSON request per line from STDIN and writes one JSON response per line
 * to STDOUT ({id, status, headers, body (base64)}).
 */
function runWorker(string $root, array $args): void
{
    $email = null;
    $dark = in_array('--dark', $args, true);

    foreach ($args as $arg) {
        if (str_starts_with($arg, '--email=')) {
            $email = substr($arg, 8) ?: null;
        }
    }

    // Render like a web request: AccountBaseController only sets $currentRouteName outside console mode
    putenv('APP_RUNNING_IN_CONSOLE=false');
    $_ENV['APP_RUNNING_IN_CONSOLE'] = 'false';
    $_SERVER['APP_RUNNING_IN_CONSOLE'] = 'false';

    // The app reads public/version.txt relative to the cwd.
    chdir($root . '/public');

    require $root . '/vendor/autoload.php';
    $app = require $root . '/bootstrap/app.php';

    $app->instance('request', Illuminate\Http\Request::create('http://127.0.0.1:8123/'));

    $kernel = $app->make(Illuminate\Contracts\Http\Kernel::class);
    $kernel->bootstrap();

    $database = config('database.connections.' . config('database.default') . '.database');

    if (!str_ends_with((string)$database, '_local')) {
        fwrite(STDERR, "Refusing to run: DB_DATABASE ({$database}) does not end with _local.\n");
        exit(2);
    }

    $userQuery = App\Models\User::withoutGlobalScopes();

    if ($email) {
        $user = $userQuery->where('email', $email)->first();
    }
    else {
        $company = App\Models\Company::withoutGlobalScopes()->where('company_name', 'like', 'Демо Финанс%')->first();
        $user = $company ? $userQuery->where('company_id', $company->id)
            ->whereHas('roles', fn($q) => $q->withoutGlobalScopes()->where('name', 'admin'))
            ->first() : null;
    }

    if (!$user || !$user->user_auth_id) {
        fwrite(STDERR, 'User not found' . ($email ? " ({$email})" : '') . ".\n");
        exit(2);
    }

    $userAuth = App\Models\UserAuth::find($user->user_auth_id);

    if ($dark) {
        // Applied per request, never saved.
        App\Models\User::retrieved(function ($model) use ($user) {
            if ($model->id === $user->id) {
                $model->dark_theme = 1;
            }
        });
    }

    // POST/PUT from the page (e.g. DataTables) have no CSRF token of ours.
    // S6 replaced VerifyCsrfToken with Databackd\VerifyCsrfTokenPathOnly in the web group - bypass both.
    foreach ([App\Http\Middleware\VerifyCsrfToken::class, App\Http\Middleware\Databackd\VerifyCsrfTokenPathOnly::class] as $csrfClass) {
        $app->bind($csrfClass, fn() => new class {
            public function handle($request, Closure $next)
            {
                return $next($request);
            }
        });
    }

    fwrite(STDOUT, json_encode(['ready' => true, 'user' => $user->email]) . "\n");

    while (($line = fgets(STDIN)) !== false) {
        $job = json_decode($line, true);

        if (!is_array($job)) {
            continue;
        }

        $status = 500;
        $headers = [];
        $body = '';

        try {
            $server = [];

            foreach (($job['headers'] ?? []) as $name => $value) {
                $key = strtoupper(str_replace('-', '_', $name));
                $server[in_array($key, ['CONTENT_TYPE', 'CONTENT_LENGTH']) ? $key : 'HTTP_' . $key] = $value;
            }

            unset($server['HTTP_COOKIE']);

            $parameters = [];
            $content = isset($job['body']) ? base64_decode($job['body']) : null;

            if ($content !== null && str_contains($server['CONTENT_TYPE'] ?? '', 'application/x-www-form-urlencoded')) {
                parse_str($content, $parameters);
            }

            $request = Illuminate\Http\Request::create('http://127.0.0.1:8123' . $job['url'], $job['method'] ?? 'GET', $parameters, [], [], $server, $content);

            $app['auth']->shouldUse('web');
            $app['auth']->guard('web')->setUser($userAuth);

            $response = $kernel->handle($request);
            $kernel->terminate($request, $response);

            $status = $response->getStatusCode();

            foreach ($response->headers->allPreserveCase() as $name => $values) {
                if (strtolower($name) !== 'set-cookie') {
                    $headers[$name] = implode(', ', $values);
                }
            }

            if ($response instanceof Symfony\Component\HttpFoundation\BinaryFileResponse) {
                $body = file_get_contents($response->getFile()->getPathname());
            }
            elseif ($response instanceof Symfony\Component\HttpFoundation\StreamedResponse) {
                ob_start();
                $response->sendContent();
                $body = ob_get_clean();
            }
            else {
                $body = (string)$response->getContent();
            }

            if (!empty($response->exception)) {
                $e = $response->exception;
                fwrite(STDERR, "[php] {$job['url']}: " . get_class($e) . ': ' . $e->getMessage() . ' @ ' . str_replace($root . '/', '', $e->getFile()) . ':' . $e->getLine() . "\n");
            }
        } catch (Throwable $e) {
            $body = get_class($e) . ': ' . $e->getMessage();
            fwrite(STDERR, "[php] {$job['url']}: {$body}\n");
        }

        fwrite(STDOUT, json_encode([
            'id' => $job['id'] ?? null,
            'status' => $status,
            'headers' => $headers,
            'body' => base64_encode($body),
        ]) . "\n");
    }
}
