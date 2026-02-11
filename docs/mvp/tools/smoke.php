<?php

/*
 * AFV MVP smoke check — renders pages in-process, without a running web server.
 *
 * Usage (from the app root):
 *   php docs/mvp/tools/smoke.php <route-name-prefix> [options]
 *
 * Examples:
 *   php docs/mvp/tools/smoke.php databackd.data.
 *   php docs/mvp/tools/smoke.php databackd.reports. --ajax
 *   php docs/mvp/tools/smoke.php databackd.teams. --email=client1@demo.databackd.local
 *   php docs/mvp/tools/smoke.php - --url=/account/databackd/ratios/5
 *
 * Options:
 *   --email=<email>   Log in as this user (default: first admin of the demo company "Демо Финанс Груп")
 *   --url=<path>      Extra URL to check (repeatable); use for routes with required parameters
 *   --ajax            Also request every URL as an AJAX/JSON call (DataTables endpoints)
 *   --show            Print the first 400 chars of the body for non-200 responses
 *   --dump=<dir>      Save every page as <dir>/<name>.html and its visible text (one text node per line) as <dir>/<name>.txt
 *
 * Every GET route whose name starts with the prefix and has no required parameters is requested.
 * Exit code is 1 when any page fails (status >= 400, an exception, or an unexpected redirect).
 */

error_reporting(E_ALL & ~E_DEPRECATED & ~E_USER_DEPRECATED);

// Render like a web request: AccountBaseController only sets $currentRouteName outside console mode
putenv('APP_RUNNING_IN_CONSOLE=false');
$_ENV['APP_RUNNING_IN_CONSOLE'] = 'false';
$_SERVER['APP_RUNNING_IN_CONSOLE'] = 'false';

$root = dirname(__DIR__, 3);

// The app reads public/version.txt relative to the cwd.
chdir($root . '/public');

require $root . '/vendor/autoload.php';
$app = require $root . '/bootstrap/app.php';

$app->instance('request', Illuminate\Http\Request::create('http://127.0.0.1:8123/'));

$kernel = $app->make(Illuminate\Contracts\Http\Kernel::class);
$kernel->bootstrap();

$args = array_slice($argv, 1);
$prefix = null;
$email = null;
$extraUrls = [];
$ajax = false;
$show = false;
$dumpDir = null;

foreach ($args as $arg) {
    if (str_starts_with($arg, '--email=')) {
        $email = substr($arg, 8);
    }
    elseif (str_starts_with($arg, '--url=')) {
        $extraUrls[] = substr($arg, 6);
    }
    elseif ($arg === '--ajax') {
        $ajax = true;
    }
    elseif ($arg === '--show') {
        $show = true;
    }
    elseif (str_starts_with($arg, '--dump=')) {
        $dumpDir = rtrim(substr($arg, 7), '/');
        @mkdir($dumpDir, 0775, true);
    }
    elseif ($prefix === null) {
        $prefix = $arg;
    }
}

if ($prefix === null) {
    fwrite(STDERR, "Usage: php docs/mvp/tools/smoke.php <route-name-prefix|-> [--email=] [--url=] [--ajax] [--show]\n");
    exit(2);
}

$database = config('database.connections.' . config('database.default') . '.database');

if (!str_ends_with((string)$database, '_local')) {
    fwrite(STDERR, "Refusing to run: DB_DATABASE ({$database}) does not end with _local.\n");
    exit(2);
}

// Resolve the user to log in as.
$userQuery = App\Models\User::withoutGlobalScopes();

if ($email) {
    $user = $userQuery->where('email', $email)->first();
}
else {
    $company = App\Models\Company::withoutGlobalScopes()->where('company_name', 'like', 'Демо Финанс%')->first();

    if (!$company) {
        fwrite(STDERR, "Demo company not found. Run: php artisan databackd:demo\n");
        exit(2);
    }

    $user = $userQuery->where('company_id', $company->id)
        ->whereHas('roles', fn($q) => $q->withoutGlobalScopes()->where('name', 'admin'))
        ->first();
}

if (!$user || !$user->user_auth_id) {
    fwrite(STDERR, "User not found" . ($email ? " ({$email})" : '') . ".\n");
    exit(2);
}

$userAuth = App\Models\UserAuth::find($user->user_auth_id);

// Collect URLs.
$urls = [];
$skipped = [];

if ($prefix !== '-') {
    foreach (Illuminate\Support\Facades\Route::getRoutes() as $route) {
        $name = $route->getName();

        if (!$name || !str_starts_with($name, $prefix) || !in_array('GET', $route->methods())) {
            continue;
        }

        if (preg_match('/\{[^}?]+\}/', $route->uri())) {
            $skipped[] = $name . ' (/' . $route->uri() . ')';
            continue;
        }

        $urls[$name] = '/' . ltrim($route->uri(), '/');
    }
}

foreach ($extraUrls as $url) {
    $urls[$url] = $url;
}

if (!$urls) {
    fwrite(STDERR, "No routes matched '{$prefix}'.\n");
    exit(1);
}

$failures = 0;
$appUrl = rtrim(config('app.url'), '/');

$request = function (string $url, bool $asAjax) use ($app, $kernel, $userAuth) {
    $server = $asAjax ? ['HTTP_X_REQUESTED_WITH' => 'XMLHttpRequest', 'HTTP_ACCEPT' => 'application/json'] : [];
    $query = $asAjax ? ['draw' => 1, 'start' => 0, 'length' => 10] : [];

    $req = Illuminate\Http\Request::create($url, 'GET', $query, [], [], $server);

    $app['auth']->shouldUse('web');
    $app['auth']->guard('web')->setUser($userAuth);

    $response = $kernel->handle($req);
    $kernel->terminate($req, $response);

    return $response;
};

echo "User: {$user->email} | DB: {$database}\n\n";

foreach ($urls as $name => $url) {
    foreach ($ajax ? [false, true] : [false] as $asAjax) {
        $label = ($asAjax ? '[ajax] ' : '') . $name . ($name !== $url ? "  {$url}" : '');
        $problems = [];

        try {
            $response = $request($url, $asAjax);
            $status = $response->getStatusCode();
            $body = (string)$response->getContent();

            if (!empty($response->exception)) {
                $e = $response->exception;
                $problems[] = get_class($e) . ': ' . $e->getMessage() . ' @ ' . str_replace($GLOBALS['root'] . '/', '', $e->getFile()) . ':' . $e->getLine();
            }

            if ($status >= 400) {
                $problems[] = "HTTP {$status}";
            }

            if ($status >= 300 && $status < 400) {
                $problems[] = "redirect → " . str_replace($appUrl, '', (string)$response->headers->get('Location'));
            }

            if ($asAjax && $status === 200 && json_decode($body) === null && str_contains($response->headers->get('Content-Type', ''), 'json')) {
                $problems[] = 'invalid JSON';
            }

            if ($dumpDir && !$asAjax && $status === 200) {
                $base = $dumpDir . '/' . trim(preg_replace('/[^A-Za-z0-9._-]+/', '_', $name), '_');
                file_put_contents($base . '.html', $body);
                $visible = preg_replace('#<(script|style|noscript)\b.*?</\1>#is', '', $body);
                $visible = preg_replace('#<!--.*?-->#s', '', $visible);
                $visible = preg_replace('#<[^>]+>#', "\n", $visible);
                $lines = array_filter(array_map(fn ($l) => trim(preg_replace('/\s+/u', ' ', html_entity_decode($l, ENT_QUOTES | ENT_HTML5, 'UTF-8'))), explode("\n", $visible)), fn ($l) => $l !== '');
                file_put_contents($base . '.txt', implode("\n", $lines) . "\n");
            }

            if (preg_match_all('/databackd-[a-z0-9-]+\.[A-Za-z_][\w.]*/', strip_tags($body), $m)) {
                $problems[] = 'untranslated keys: ' . implode(', ', array_slice(array_unique($m[0]), 0, 5));
            }
        } catch (Throwable $e) {
            $status = 'EXC';
            $body = '';
            $problems[] = get_class($e) . ': ' . $e->getMessage() . ' @ ' . $e->getFile() . ':' . $e->getLine();
        }

        if ($problems) {
            $failures++;
            echo "FAIL  {$status}  {$label}\n      - " . implode("\n      - ", $problems) . "\n";

            if ($show && $body) {
                echo '      body: ' . mb_substr(trim(preg_replace('/\s+/', ' ', strip_tags($body))), 0, 400) . "\n";
            }
        }
        else {
            echo "OK    {$status}  {$label}\n";
        }
    }
}

if ($skipped) {
    echo "\nSkipped (required parameters — check them with --url=):\n  " . implode("\n  ", $skipped) . "\n";
}

echo "\n" . ($failures ? "{$failures} failing" : 'All pages OK') . "\n";
exit($failures ? 1 : 0);
