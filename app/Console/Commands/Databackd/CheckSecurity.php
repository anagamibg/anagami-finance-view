<?php

namespace App\Console\Commands\Databackd;

use App\Exports\Databackd\Concerns\NeutralizesFormulas;
use App\Http\Middleware\Databackd\VerifyCsrfTokenPathOnly;
use App\Models\Company;
use App\Models\Databackd\AnalysisRequest;
use App\Models\Databackd\FinApiToken;
use App\Models\User;
use App\Models\UserAuth;
use App\Services\Databackd\Analytics\FormulaEngine;
use App\Services\Databackd\Data\GoogleSheetsReader;
use App\Services\Databackd\Reports\PeriodBuckets;
use App\Services\Databackd\Reports\ReportBuilder;
use App\Services\Databackd\Reports\ReportFilters;
use Closure;
use Illuminate\Console\Command;
use Illuminate\Contracts\Http\Kernel as HttpKernel;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

/**
 * databackd: security regression check (evidence for the security review, docs/mvp/security/2026-10-security-review.md).
 *
 * Runs against the LOCAL demo database only (refuses unless DB_DATABASE ends with _local). Every write happens in a
 * transaction that is rolled back; HTTP requests go through the application kernel in-process (no server).
 * Groups: static, pure, isolation, roles, api, http, auth. Exit code 1 when any assert fails.
 */
class CheckSecurity extends Command
{

    protected $signature = 'databackd:check-security {--only= : static|pure|isolation|roles|api|http|auth (comma separated)} {--show : print the detail of every FAIL}';

    protected $description = 'Security regression check: company isolation, roles, SSRF/formula/SQL whitelists, API token auth, headers, auth hardening';

    private int $passed = 0;

    private int $failed = 0;

    private int $skipped = 0;

    private ?Company $demo = null;

    private ?Company $foreign = null;

    /** @var array<string, UserAuth|null> */
    private array $actors = [];

    private bool $csrfOff = true;

    public function handle(): int
    {
        $database = (string)config('database.connections.' . config('database.default') . '.database');

        if (!str_ends_with($database, '_local')) {
            $this->error("Refusing to run: DB_DATABASE ({$database}) does not end with _local.");

            return self::FAILURE;
        }

        // Keep the in-process requests away from the real session files and rate-limit counters.
        // (Cache::flush() between actors then only empties the in-memory store, never the real file cache.)
        config(['session.driver' => 'array', 'cache.default' => 'array']);

        $this->demo = Company::withoutGlobalScopes()->where('company_name', 'like', 'Демо Финанс%')->first();
        $this->foreign = $this->demo
            ? Company::withoutGlobalScopes()->where('id', '!=', $this->demo->id)->orderBy('id')->first()
            : null;

        if (!$this->demo) {
            $this->error('Demo company not found. Run: php artisan databackd:demo');

            return self::FAILURE;
        }

        foreach (['admin' => 'admin@demofinans.bg', 'employee' => 'g.petrov@demofinans.bg', 'client' => 'p.yordanov@balkan.bg',
                     'client2' => 'i.stoycheva@rodopi.bg', 'superadmin' => 'superadmin@databackd.local'] as $role => $email) {
            $user = User::withoutGlobalScopes()->where('email', $email)->first();
            $this->actors[$role] = $user && $user->user_auth_id ? UserAuth::find($user->user_auth_id) : null;
        }

        $groups = $this->option('only') ? explode(',', (string)$this->option('only')) : ['static', 'pure', 'isolation', 'roles', 'api', 'http', 'auth'];

        $this->info("databackd:check-security | DB: {$database} | demo company #{$this->demo->id}" . ($this->foreign ? " | foreign company #{$this->foreign->id}" : ' | NO foreign company'));

        foreach ($groups as $group) {
            $method = 'group' . Str::studly(trim($group));

            if (!method_exists($this, $method)) {
                $this->warn("Unknown group: {$group}");
                continue;
            }

            $this->line('');
            $this->line("== {$group}");

            DB::beginTransaction();

            try {
                $this->$method();
            } catch (Throwable $e) {
                $this->check("group {$group} finished without an exception", false, get_class($e) . ': ' . $e->getMessage() . ' @ ' . basename($e->getFile()) . ':' . $e->getLine());
            } finally {
                while (DB::transactionLevel() > 0) {
                    DB::rollBack();
                }
            }
        }

        $this->line('');
        $this->line("passed: {$this->passed}   failed: {$this->failed}   skipped: {$this->skipped}");

        return $this->failed ? self::FAILURE : self::SUCCESS;
    }

    // ------------------------------------------------------------------ static

    private function groupStatic(): void
    {
        // P1: every AFV model is tenant scoped and its table has company_id.
        $models = glob(app_path('Models/Databackd/*.php'));
        $bad = [];

        foreach ($models as $file) {
            $class = 'App\\Models\\Databackd\\' . basename($file, '.php');
            $table = (new $class())->getTable();

            if (!in_array(\App\Traits\HasCompany::class, class_uses_recursive($class), true) || !\Schema::hasColumn($table, 'company_id')) {
                $bad[] = basename($file, '.php');
            }
        }

        $this->check('P1 all ' . count($models) . ' Anagami Finance View models use HasCompany and have company_id', !$bad, implode(', ', $bad));

        // P4/P10: no dompdf enable_php anywhere, no leftover tools in the web root.
        $hits = $this->grep('/[\'"]enable_php[\'"]\s*(,|=>)\s*true/i', [app_path()]);
        $this->check("P10-01 no 'enable_php' => true in app/", !$hits, implode(', ', $hits));
        $this->check('P7/P8 public/backup-and-restore and public/check.php are gone', !is_dir(public_path('backup-and-restore')) && !is_file(public_path('check.php')));
        $this->check("config('dompdf.options.enable_php') is false", config('dompdf.options.enable_php', false) === false);

        // P4: every Excel export neutralises formulas; PDFs fetch nothing remote; analysis files live outside public/.
        $unsafe = [];

        foreach (glob(app_path('Exports/Databackd/*/*Export.php')) as $file) {
            if (!str_contains((string)file_get_contents($file), 'NeutralizesFormulas')) {
                $unsafe[] = basename($file);
            }
        }

        $this->check('P4-03 every Anagami Finance View *Export class uses NeutralizesFormulas', !$unsafe, implode(', ', $unsafe));
        $hits = $this->grep('/isRemoteEnabled[\'"]\s*,\s*true/', [app_path('Exports/Databackd/Reports')]);
        $this->check('P4-05 the report PDF classes never enable remote fetching in dompdf', !$hits, implode(', ', $hits));
        $root = (string)config('filesystems.disks.databackd_private.root');
        $this->check('P4-02 disk databackd_private exists and is outside public/', $root !== '' && !str_starts_with(realpath(dirname($root)) ?: $root, public_path()));
        $this->check('P4-02 AnalysisRequest has the private upload/download helpers', method_exists(AnalysisRequest::class, 'storeUpload') && method_exists(AnalysisRequest::class, 'resolveDownload'));

        // P2: no mass assignment of raw request data.
        $hits = $this->grep('/(->(create|update|fill|forceFill)|::create|updateOrCreate|firstOrCreate)\(\s*(\$request|request\(\))->(all|validated|input|except)\(/',
            [app_path('Http/Controllers/Databackd'), app_path('Http/Requests/Databackd'), app_path('Services/Databackd'), app_path('Imports'), app_path('Jobs/Databackd')]);
        $this->check('P2-12 no create/update/fill() with raw request data in Anagami Finance View code', !$hits, implode(', ', $hits));
        $hits = $this->grep('/::unguard\(|::unguarded\(|->unguard\(/', [app_path()]);
        $this->check('P2-12 no Model::unguard() in app/', !$hits, implode(', ', $hits));

        // P3: no eval / dynamic code in AFV code.
        $hits = $this->grep('/\b(eval|create_function|unserialize|shell_exec|passthru|proc_open|popen)\s*\(/', [app_path('Services/Databackd'), app_path('Http/Controllers/Databackd'), app_path('Exports/Databackd'), app_path('Imports')]);
        $this->check('P3 no eval/unserialize/shell calls in Anagami Finance View code', !$hits, implode(', ', $hits));

        // P7: configuration.
        $this->check('P7 session cookie is http_only', config('session.http_only') === true);
        $this->check('P7 session same_site is lax or strict', in_array(config('session.same_site'), ['lax', 'strict'], true));
        $this->check('P9-07 bcrypt rounds >= 12', (int)config('hashing.bcrypt.rounds') >= 12);

        $gitignore = (string)@file_get_contents(base_path('.gitignore'));

        foreach (['.env.*', '*.sql', 'demo-passwords', '.claude/'] as $needle) {
            $this->check("P7-10 .gitignore covers {$needle}", str_contains($gitignore, $needle));
        }

        // P6/P7/P9/P2: the hardening middleware is registered (global or in the web group).
        $kernel = app(HttpKernel::class);
        $global = $kernel->getGlobalMiddleware();
        $web = $kernel->getMiddlewareGroups()['web'] ?? [];

        foreach (['SecurityHeaders' => $global, 'FinApiGate' => $global, 'XssLinearMerge' => $global,
                     'RestrictVendorMaintenanceRoutes' => array_merge($global, $web), 'VerifyCsrfTokenPathOnly' => $web, 'AuthHardening' => $web] as $name => $list) {
            $class = 'App\\Http\\Middleware\\Databackd\\' . $name;
            $this->check("middleware {$name} is registered", in_array($class, $list, true));
        }

        $this->check('P6-02 the quadratic Froiden XSS middleware is not in the global stack', !in_array(\Froiden\Envato\Middleware\XSS::class, $global, true));

        // P7-01: log viewer API is behind auth + super-admin.
        $logViewer = (array)config('log-viewer.api_middleware', []);
        $this->check('P7-01 log-viewer API middleware has auth + super-admin', in_array('auth', $logViewer, true) && in_array('super-admin', $logViewer, true));
    }

    // ------------------------------------------------------------------ pure functions

    private function groupPure(): void
    {
        // P5: SSRF validator (no network).
        $deny = ['http://docs.google.com/x', 'https://docs.google.com.evil.com/x', 'https://evil.com/?docs.google.com', 'https://evil.com#@docs.google.com/',
            'https://docs.google.com@127.0.0.1/', 'https://user:pass@docs.google.com/x', 'https://127.0.0.1/', 'https://localhost/', 'https://[::1]/',
            'https://[::ffff:7f00:1]/', 'https://0x7f000001/', 'https://2130706433/', 'https://169.254.169.254/latest/meta-data/', 'https://docs.google.com\\.evil.com/',
            'file:///etc/passwd', 'gopher://docs.google.com/', 'ftp://docs.google.com/x', 'http://127.0.0.1:8123/login', 'https://docs.google.com:8443/x', ''];
        $bad = [];

        foreach ($deny as $url) {
            try {
                GoogleSheetsReader::assertSafeUrl($url, false);
                $bad[] = $url;
            } catch (\RuntimeException) {
            }
        }

        $this->check('P5 SSRF validator denies ' . count($deny) . ' hostile URLs', !$bad, implode(' | ', $bad));

        $accept = ['https://docs.google.com/spreadsheets/d/1AbCdEfGhIjKlMnOpQrStUvWxYz0123456789/export?format=csv&gid=0', 'https://docs.google.com/spreadsheets/d/e/2PACX-1vTabcdefghijk/pub?output=csv'];
        $bad = [];

        foreach ($accept as $url) {
            try {
                GoogleSheetsReader::assertSafeUrl($url, false);
            } catch (\RuntimeException) {
                $bad[] = $url;
            }
        }

        $this->check('P5 SSRF validator accepts the Google Sheets URLs', !$bad, implode(' | ', $bad));

        $publicOk = array_reduce(['8.8.8.8', '172.217.21.238', '2a00:1450:4017:81c::200e'], fn($c, $ip) => $c && GoogleSheetsReader::isPublicIp($ip), true);
        $privateOk = array_reduce(['127.0.0.1', '10.1.2.3', '172.16.0.1', '192.168.0.1', '169.254.169.254', '100.64.0.1', '0.0.0.0', '::1', 'fe80::1', 'fd12:3456::1', '::ffff:127.0.0.1'],
            fn($c, $ip) => $c && !GoogleSheetsReader::isPublicIp($ip), true);
        $this->check('P5 isPublicIp: public true, private/loopback/link-local/metadata false', $publicOk && $privateOk);

        // P3: FormulaEngine limits.
        $engine = new FormulaEngine($this->demo->id);
        $hostile = [
            'nesting' => str_repeat('(', 21) . '1' . str_repeat(')', 21),
            'length' => str_repeat('1+', 600) . '1',
            'garbage' => "1+1; system('id')",
            'cyrillic' => 'приходи + 1',
        ];

        foreach ($hostile as $label => $formula) {
            $this->check("P3-02 FormulaEngine rejects hostile formula ({$label})", count($engine->validate($formula)) > 0);
        }

        $this->check('P3-02 FormulaEngine still accepts a plain formula', count($engine->validate('1 + 2 * 3')) === 0);

        // P3: SQL whitelists.
        $cfg = ReportBuilder::normalizeConfig(['rows' => "x') OR 1=1 --", 'columns' => "period') UNION SELECT 1 --", 'measure' => "sum'; DROP TABLE x; --", 'chart' => '<script>']);
        $this->check('P3-05 ReportBuilder::normalizeConfig falls back to whitelisted values', ($cfg['rows'] ?? null) === 'account' && ($cfg['columns'] ?? null) === 'period' && ($cfg['measure'] ?? null) === 'sum' && ($cfg['chart'] ?? null) === 'bar', json_encode($cfg));

        $f = ReportFilters::fromArray(['periodicity' => "day'; DROP TABLE fin_entries; --", 'period' => "x' OR 1=1", 'entity_ids' => ['1) OR 1=1 --', '2', 'abc'], 'region_id' => '1 OR 1=1', 'client_id' => '1; DROP TABLE x'], $this->demo->id);
        $this->check('P3-05 ReportFilters normalises hostile query input', $f->periodicity === 'month' && $f->entityIds === [1, 2] && $f->regionId === null && $f->clientId === null, json_encode([$f->periodicity, $f->entityIds, $f->regionId, $f->clientId]));

        $this->check("P3-05 PeriodBuckets::sqlExpression normalises a hostile periodicity", PeriodBuckets::sqlExpression("day'; DROP TABLE fin_entries; --", 'fin_entries.entry_date') === "DATE_FORMAT(fin_entries.entry_date, '%Y-%m')");

        $threw = 0;

        foreach (['entry_date) OR 1=1 --', 'a.b.c', "a'b", ''] as $column) {
            try {
                PeriodBuckets::sqlExpression('month', $column);
            } catch (\InvalidArgumentException) {
                $threw++;
            }
        }

        $this->check('P3-05 PeriodBuckets::sqlExpression rejects hostile column names', $threw === 4, "{$threw}/4");

        // P4: spreadsheet formula injection.
        $helper = new class {
            use NeutralizesFormulas;
        };
        $cases = ['=1+1' => "'=1+1", '+cmd|calc' => "'+cmd|calc", '-2+3' => "'-2+3", '@SUM(1)' => "'@SUM(1)", "\t=1" => "'\t=1", "\r=1" => "'\r=1", 'Plain' => 'Plain', 'ООД Тест' => 'ООД Тест'];
        $bad = [];

        foreach ($cases as $in => $out) {
            if ($helper::neutralizeFormula($in) !== $out) {
                $bad[] = json_encode($in);
            }
        }

        $this->check('P4 NeutralizesFormulas prefixes = + - @ TAB CR and leaves text alone', !$bad, implode(', ', $bad));
        $this->check('P4 NeutralizesFormulas leaves numbers untouched', $helper::neutralizeFormula(-5.5) === -5.5 && $helper::neutralizeFormula(null) === null);
    }

    // ------------------------------------------------------------------ company isolation

    private function groupIsolation(): void
    {
        if (!$this->foreign) {
            $this->skip('company isolation (no second company in this database)');

            return;
        }

        $this->csrf(false);
        $foreign = $this->foreign->id;
        $marker = 'S6-TMP-CHECK-' . Str::random(6);
        $now = now();

        $ids = [
            'entities' => DB::table('fin_entities')->insertGetId(['company_id' => $foreign, 'name' => $marker, 'created_at' => $now, 'updated_at' => $now]),
            'accounts' => DB::table('fin_accounts')->insertGetId(['company_id' => $foreign, 'code' => 'S6X', 'name' => $marker, 'type' => 'expense', 'created_at' => $now, 'updated_at' => $now]),
            'dimensions' => DB::table('fin_dimensions')->insertGetId(['company_id' => $foreign, 'type' => 'region', 'name' => $marker, 'created_at' => $now, 'updated_at' => $now]),
            'saved' => DB::table('fin_saved_reports')->insertGetId(['company_id' => $foreign, 'name' => $marker, 'created_at' => $now, 'updated_at' => $now]),
            'sources' => DB::table('fin_data_sources')->insertGetId(['company_id' => $foreign, 'name' => $marker, 'created_at' => $now, 'updated_at' => $now]),
        ];

        // Model level: the global scope hides foreign rows from a logged-in demo user.
        $this->as('admin');
        session(['company' => $this->demo]);
        $leaks = [];

        foreach (['entities' => \App\Models\Databackd\FinEntity::class, 'accounts' => \App\Models\Databackd\FinAccount::class, 'dimensions' => \App\Models\Databackd\FinDimension::class,
                     'saved' => \App\Models\Databackd\FinSavedReport::class, 'sources' => \App\Models\Databackd\FinDataSource::class] as $key => $class) {
            if ($class::find($ids[$key]) !== null || $class::query()->where('company_id', '!=', $this->demo->id)->exists()) {
                $leaks[] = $key;
            }
        }

        $this->check('P1 model scope: foreign rows are invisible to the demo admin', !$leaks, implode(', ', $leaks));

        // URL level.
        $probes = [
            ['GET', "/account/databackd/data/entities/{$ids['entities']}/edit"],
            ['PUT', "/account/databackd/data/entities/{$ids['entities']}", ['name' => 'HACKED']],
            ['DELETE', "/account/databackd/data/entities/{$ids['entities']}"],
            ['GET', "/account/databackd/data/accounts/{$ids['accounts']}/edit"],
            ['PUT', "/account/databackd/data/accounts/{$ids['accounts']}", ['name' => 'HACKED', 'code' => 'S6X', 'type' => 'expense']],
            ['POST', "/account/databackd/data/accounts/{$ids['accounts']}/toggle"],
            ['DELETE', "/account/databackd/data/accounts/{$ids['accounts']}"],
            ['GET', "/account/databackd/data/dimensions/{$ids['dimensions']}/edit"],
            ['PUT', "/account/databackd/data/dimensions/{$ids['dimensions']}", ['name' => 'HACKED', 'type' => 'region']],
            ['DELETE', "/account/databackd/data/dimensions/{$ids['dimensions']}"],
            ['GET', "/account/databackd/reports/saved/{$ids['saved']}"],
            ['POST', "/account/databackd/reports/saved/{$ids['saved']}/duplicate"],
            ['DELETE', "/account/databackd/reports/saved/{$ids['saved']}"],
            ['POST', "/account/databackd/data/sources/{$ids['sources']}/sync"],
            ['DELETE', "/account/databackd/data/sources/{$ids['sources']}"],
        ];

        foreach (['admin', 'employee'] as $role) {
            if (!$this->actors[$role]) {
                $this->skip("isolation as {$role} (user not found)");
                continue;
            }

            $bad = [];

            foreach ($probes as $probe) {
                [$method, $uri, $params] = array_pad($probe, 3, []);
                $response = $this->http($method, $uri, $role, $params);
                $status = $response->getStatusCode();
                $body = (string)$response->getContent();

                if (!in_array($status, [403, 404, 422], true) && !($status >= 300 && $status < 400) || str_contains($body, $marker)) {
                    $bad[] = "{$method} {$uri} -> {$status}" . (str_contains($body, $marker) ? ' (marker in body)' : '');
                }
            }

            $this->check("P1 foreign-company ids: " . count($probes) . " GET/PUT/POST/DELETE probes as {$role} are refused and leak nothing", !$bad, implode("\n        ", $bad));
        }

        // Control: the same URLs for a row of the demo company DO reach the controller (otherwise the 404s above prove nothing).
        $own = DB::table('fin_entities')->insertGetId(['company_id' => $this->demo->id, 'name' => $marker . '-own', 'created_at' => $now, 'updated_at' => $now]);
        $control = $this->http('GET', "/account/databackd/data/entities/{$own}/edit", 'admin');
        $this->check('control: the demo admin reaches the edit form of an own entity (not 403/404)', !in_array($control->getStatusCode(), [403, 404], true), (string)$control->getStatusCode());
        $update = $this->http('PUT', "/account/databackd/data/entities/{$own}", 'admin', ['name' => $marker . '-own-renamed', 'currency' => 'EUR', 'country' => 'BG']);
        $this->check('control: the demo admin can rename an own entity (the PUT probes above are therefore meaningful)', DB::table('fin_entities')->where('id', $own)->value('name') === $marker . '-own-renamed', $update->getStatusCode() . ' ' . substr((string)$update->getContent(), 0, 200));

        // Nothing was changed or deleted in the foreign company.
        $same = true;

        foreach (['fin_entities', 'fin_accounts', 'fin_dimensions', 'fin_saved_reports', 'fin_data_sources'] as $table) {
            $row = DB::table($table)->where('company_id', $foreign)->where('name', $marker)->first();
            $same = $same && $row !== null && $row->name === $marker;
        }

        $this->check('P1 the foreign rows are still there and unchanged after the probes', $same);

        // P1-01: names in report titles come only from the current company.
        $summary = ReportFilters::fromArray(['entity_ids' => [$ids['entities']], 'region_id' => $ids['dimensions']], $this->demo->id)->summary();
        $this->check('P1-01 ReportFilters::summary() does not reveal foreign names', !str_contains(json_encode($summary, JSON_UNESCAPED_UNICODE), $marker), json_encode($summary, JSON_UNESCAPED_UNICODE));

        // Client against client in the same company (P2).
        $client2 = $this->actors['client2'] ? User::withoutGlobalScopes()->where('user_auth_id', $this->actors['client2']->id)->first() : null;

        if ($client2 && $this->actors['client']) {
            $analysisId = DB::table('analysis_requests')->insertGetId(['company_id' => $this->demo->id, 'client_id' => $client2->id, 'title' => $marker, 'created_at' => $now, 'updated_at' => $now]);
            $bad = [];

            foreach ([['GET', "/account/databackd/teams/analysis/{$analysisId}"], ['GET', "/account/databackd/teams/analysis/{$analysisId}/edit"],
                         ['PUT', "/account/databackd/teams/analysis/{$analysisId}", ['title' => 'HACKED']], ['GET', "/account/databackd/teams/analysis/{$analysisId}/download"],
                         ['POST', "/account/databackd/teams/analysis/{$analysisId}/comment", ['comment' => 'x']], ['POST', "/account/databackd/teams/analysis/{$analysisId}/rate", ['rating' => 5]]] as $probe) {
                [$method, $uri, $params] = array_pad($probe, 3, []);
                $response = $this->http($method, $uri, 'client', $params);

                if (!in_array($response->getStatusCode(), [403, 404, 422], true) || str_contains((string)$response->getContent(), $marker)) {
                    $bad[] = "{$method} {$uri} -> " . $response->getStatusCode();
                }
            }

            $clientA = User::withoutGlobalScopes()->where('user_auth_id', $this->actors['client']->id)->first();
            $mine = DB::table('analysis_requests')->insertGetId(['company_id' => $this->demo->id, 'client_id' => $clientA->id, 'title' => $marker . '-mine', 'created_at' => $now, 'updated_at' => $now]);
            $control = $this->http('GET', "/account/databackd/teams/analysis/{$mine}", 'client')->getStatusCode();
            $this->check('control: client A reaches his own analysis request (not 403/404)', !in_array($control, [403, 404], true), (string)$control);
            $this->check("P2 client A cannot read or change client B's analysis request", !$bad, implode("\n        ", $bad));
            $this->check('P2 the analysis request of client B is unchanged', AnalysisRequest::withoutGlobalScopes()->find($analysisId)?->title === $marker);
        } else {
            $this->skip('client-vs-client analysis request check (demo clients not found)');
        }
    }

    // ------------------------------------------------------------------ roles

    private function groupRoles(): void
    {
        $this->csrf(false);

        $routes = [];

        foreach (Route::getRoutes() as $route) {
            $name = (string)$route->getName();

            if (!str_starts_with($name, 'databackd.') || str_starts_with($name, 'databackd.api.') || !in_array('GET', $route->methods(), true) || preg_match('/\{[^}?]+\}/', $route->uri())) {
                continue;
            }

            $routes[$name] = '/' . ltrim($route->uri(), '/');
        }

        $this->line('   (' . count($routes) . ' parameterless GET routes)');

        // Control: the admin and the employee are not refused (otherwise the 403s below prove nothing).
        foreach (['admin', 'employee'] as $role) {
            if ($this->actors[$role]) {
                $status = $this->http('GET', '/account/databackd/data/accounts', $role)->getStatusCode();
                $this->check("control: {$role} reaches data/accounts (not 403)", $status !== 403, (string)$status);
            }
        }

        // The client only gets the Teams requests screens.
        if ($this->actors['client']) {
            $bad = [];

            foreach ($routes as $name => $uri) {
                if (str_starts_with($name, 'databackd.teams.analysis.') || str_starts_with($name, 'databackd.teams.requests.')) {
                    continue;
                }

                $status = $this->http('GET', $uri, 'client')->getStatusCode();

                if ($status !== 403) {
                    $bad[] = "{$name} -> {$status}";
                }
            }

            $this->check('P2 client: every financial/other Anagami Finance View screen answers 403', !$bad, implode("\n        ", $bad));
        } else {
            $this->skip('client role matrix (user not found)');
        }

        if ($this->actors['superadmin']) {
            $bad = [];

            foreach ($routes as $name => $uri) {
                $status = $this->http('GET', $uri, 'superadmin')->getStatusCode();

                if ($status !== 403) {
                    $bad[] = "{$name} -> {$status}";
                }
            }

            $this->check('P1-03/P2-04 super admin (no company): every Anagami Finance View screen answers 403', !$bad, implode("\n        ", $bad));
        } else {
            $this->skip('super admin matrix (superadmin@databackd.local not found)');
        }

        // Employees must not manage API tokens.
        if ($this->actors['employee']) {
            $post = $this->http('POST', '/account/databackd/data/integrations/tokens', 'employee', ['name' => 'S6-TMP-employee'])->getStatusCode();
            $delete = $this->http('DELETE', '/account/databackd/data/integrations/tokens/999999999', 'employee')->getStatusCode();
            $this->check('P2 employee cannot create or revoke API tokens', $post === 403 && $delete === 403, "POST {$post}, DELETE {$delete}");
        } else {
            $this->skip('employee token check (user not found)');
        }

        // Guests are sent to the login.
        $bad = [];

        foreach (array_slice($routes, 0, 25, true) as $name => $uri) {
            $status = $this->http('GET', $uri, null)->getStatusCode();

            if (!in_array($status, [301, 302, 401, 403], true)) {
                $bad[] = "{$name} -> {$status}";
            }
        }

        $this->check('guest: Anagami Finance View screens redirect to the login / refuse', !$bad, implode("\n        ", $bad));
    }

    // ------------------------------------------------------------------ API

    private function groupApi(): void
    {
        $this->csrf(false);
        $uri = '/api/databackd/fin-entries';
        $ip = '10.66.' . random_int(1, 250) . '.' . random_int(1, 250);
        [$token, $plain] = FinApiToken::generate('S6-TMP-check', null, $this->demo->id);
        [, $plainForeign] = $this->foreign ? FinApiToken::generate('S6-TMP-check-foreign', null, $this->foreign->id) : [null, null];

        $call = function (string $method, ?string $bearer = null, ?string $body = '[]', array $extra = [], string $query = '') use ($uri, $ip): Response {
            $server = $extra + ['REMOTE_ADDR' => $ip, 'HTTP_ACCEPT' => 'application/json', 'CONTENT_TYPE' => 'application/json'];

            if ($bearer !== null) {
                $server['HTTP_AUTHORIZATION'] = 'Bearer ' . $bearer;
            }

            if ($body !== null) {
                $server['CONTENT_LENGTH'] = strlen($body);
            }

            return $this->send(Request::create(config('app.url') . $uri . $query, $method, [], [], [], $server, $body));
        };

        $none = $call('POST');
        $this->check('P6 no token -> 401 with WWW-Authenticate: Bearer', $none->getStatusCode() === 401 && str_starts_with((string)$none->headers->get('WWW-Authenticate'), 'Bearer'));

        $wrong = $call('POST', 'dbk_' . Str::random(40));
        $this->check('P6 unknown token -> 401, same body as no token', $wrong->getStatusCode() === 401 && $wrong->getContent() === $none->getContent());

        $this->check('P6 non-string / empty / huge credentials -> 401 (no exception)',
            $call('POST', '')->getStatusCode() === 401 && $call('POST', str_repeat('A', 10000))->getStatusCode() === 401
            && $call('POST', null, '[]', [], '?token[]=x&api_token[]=y')->getStatusCode() === 401);

        $this->check('P6 token in query string or X-Api-Key is not accepted',
            $call('POST', null, '[]', [], '?api_token=' . $plain . '&token=' . $plain)->getStatusCode() === 401
            && $call('POST', null, '[]', ['HTTP_X_API_KEY' => $plain])->getStatusCode() === 401);

        $this->check('P6 GET/PUT/DELETE with a valid token -> 405 (not 500)', collect(['GET', 'PUT', 'DELETE'])->every(fn($m) => $call($m, $plain, null)->getStatusCode() === 405));
        $this->check('P6 wrong Content-Type -> 415', $call('POST', $plain, '[]', ['CONTENT_TYPE' => 'text/plain'])->getStatusCode() === 415);
        $this->check('P6 oversized body (4 MiB) -> 413, with or without a token', $call('POST', $plain, '[' . str_repeat('0,', 2 * 1024 * 1024) . '0]')->getStatusCode() === 413 && $call('POST', null, '[' . str_repeat('0,', 2 * 1024 * 1024) . '0]')->getStatusCode() === 413);
        $this->check('P6 more than the record limit -> 422', $call('POST', $plain, json_encode(array_fill(0, 5000, ['amount' => 1])))->getStatusCode() === 422);
        $this->check('P6 malformed JSON / scalar bodies -> 422', collect(['{bad', '"text"', '123', 'null', '{}'])->every(fn($b) => $call('POST', $plain, $b)->getStatusCode() === 422));

        // A valid record: company, source and author come from the token, not from the body.
        $doc = 'S6-TMP-' . Str::random(6);
        $record = ['entry_date' => now()->format('Y-m-d'), 'account_code' => '703', 'amount' => 100.5, 'document_no' => $doc,
            'company_id' => $this->foreign?->id ?? 999, 'created_by' => 999, 'source' => 'demo', 'import_batch_id' => 1, 'amount_base' => 1];
        $ok = $call('POST', $plain, json_encode([$record]));
        $row = DB::table('fin_entries')->where('document_no', $doc)->first();

        $this->check('P6 valid record -> 200 and stored with the token company, source=api, ignoring body overrides',
            $ok->getStatusCode() === 200 && $row && (int)$row->company_id === $this->demo->id && $row->source === 'api' && (int)$row->created_by !== 999 && (float)$row->amount_base !== 1.0,
            $ok->getStatusCode() . ' ' . substr((string)$ok->getContent(), 0, 200) . ' ' . json_encode($row));

        if ($plainForeign) {
            $bad = $call('POST', $plainForeign, json_encode([['entry_date' => now()->format('Y-m-d'), 'account_code' => 'S6-NO-SUCH', 'amount' => 5, 'document_no' => $doc . 'F']]));
            $this->check("P6/P1 a token of another company cannot use this company's chart of accounts", DB::table('fin_entries')->where('document_no', $doc . 'F')->doesntExist() && in_array($bad->getStatusCode(), [200, 207, 422], true));
        }

        $token->forceFill(['revoked_at' => now()])->save();
        $this->check('P6 revoked token -> 401', $call('POST', $plain)->getStatusCode() === 401);

        $this->check('P7-06 API responses carry X-Content-Type-Options: nosniff', $none->headers->get('X-Content-Type-Options') === 'nosniff');
        $cors = $call('OPTIONS', null, null, ['HTTP_ORIGIN' => 'https://evil.example', 'HTTP_ACCESS_CONTROL_REQUEST_METHOD' => 'POST']);
        $this->check('P6-08 no CORS headers for a foreign origin', !$cors->headers->has('Access-Control-Allow-Origin') && !$none->headers->has('Access-Control-Allow-Credentials'));
    }

    // ------------------------------------------------------------------ headers and vendor routes

    private function groupHttp(): void
    {
        $this->csrf(false);
        $login = $this->http('GET', '/login', null);

        $this->check('P7-06 /login: X-Content-Type-Options nosniff', $login->headers->get('X-Content-Type-Options') === 'nosniff');
        $this->check('P7-06 /login: Referrer-Policy is set', (bool)$login->headers->get('Referrer-Policy'));
        $this->check('P7-06 /login: Permissions-Policy is set', (bool)$login->headers->get('Permissions-Policy'));
        $this->check('P7-06 /login: X-Frame-Options SAMEORIGIN', strtoupper((string)$login->headers->get('X-Frame-Options')) === 'SAMEORIGIN');
        $this->check('P7-06 /login: CSP is Report-Only (not enforcing)', $login->headers->has('Content-Security-Policy-Report-Only') && !$login->headers->has('Content-Security-Policy'));

        // Vendor maintenance routes are protected by middleware (not requested: they execute artisan commands).
        $names = ['clear-cache', 'refresh-cache', 'update-database', 'check-env'];
        $missing = [];

        foreach (Route::getRoutes() as $route) {
            if (in_array(ltrim($route->uri(), '/'), $names, true) || in_array(ltrim($route->uri(), '/'), array_map(fn($n) => 'account/' . $n, $names), true)) {
                $gathered = array_merge(app(HttpKernel::class)->getGlobalMiddleware(), app(HttpKernel::class)->getMiddlewareGroups()['web'] ?? [], $route->gatherMiddleware());

                if (!in_array(\App\Http\Middleware\Databackd\RestrictVendorMaintenanceRoutes::class, $gathered, true)) {
                    $missing[] = $route->uri();
                }
            }
        }

        $this->check('P7-02/03 vendor maintenance routes pass through RestrictVendorMaintenanceRoutes', !$missing, implode(', ', $missing));

        // Log viewer API as a guest: never 200.
        // (In console mode the "Unauthenticated" exception may surface as a 500 page; the exception class tells the truth.)
        $logs = $this->http('GET', '/log-viewer/api/files', null);
        $refused = in_array($logs->getStatusCode(), [301, 302, 401, 403, 404], true)
            || ($logs->getStatusCode() === 500 && ($logs->exception ?? null) instanceof \Illuminate\Auth\AuthenticationException);
        $this->check('P7-01 log-viewer API refuses a guest', $refused, $logs->getStatusCode() . ' ' . get_class($logs->exception ?? new \stdClass()));

        // CSRF: the exception list is matched on the path only.
        $this->csrf(true);
        $bad = [];

        foreach (['/account/databackd/data/entries/apply-quick-action', '/account/databackd/data/entries/apply-quick-action?x=-webhook', '/account/databackd/data/entries/apply-quick-action?x=a_webhook'] as $uri) {
            $response = $this->http('POST', $uri, 'admin');

            if ($response->getStatusCode() !== 419 && !str_contains(get_class($response->exception ?? new \stdClass()), 'TokenMismatch')) {
                $bad[] = "{$uri} -> {$response->getStatusCode()}";
            }
        }

        $this->check('P2-01 POST without a CSRF token is refused (also with ?x=-webhook)', !$bad, implode(', ', $bad));
        $this->csrf(false);
    }

    // ------------------------------------------------------------------ authentication

    private function groupAuth(): void
    {
        $this->csrf(false);

        $code = $this->http('POST', '/check-code', null, ['user_id' => 999999, 'code' => '123456']);
        $this->check('P9-01 POST /check-code without a login challenge in the session -> 403', $code->getStatusCode() === 403, (string)$code->getStatusCode());
        $resend = $this->http('GET', '/resend-code', null);
        $this->check('P9-01 GET /resend-code without a login challenge in the session -> 403', $resend->getStatusCode() === 403, (string)$resend->getStatusCode());

        // choose-workspace: a client may not switch into somebody else's identity.
        $victim = User::withoutGlobalScopes()->where('email', 'admin@demofinans.bg')->first();

        if ($this->actors['client'] && $victim) {
            $response = $this->http('POST', '/account/choose-workspace', 'client', ['user_id' => $victim->id, 'company_id' => $victim->company_id]);
            $this->check('P9-02 choose-workspace refuses a user that belongs to another identity', in_array($response->getStatusCode(), [403, 404, 422], true), (string)$response->getStatusCode());
        } else {
            $this->skip('choose-workspace check (demo users not found)');
        }

        // Generic answer for the password reset request (no account enumeration).
        $a = $this->http('POST', '/forgot-password', null, ['email' => 'admin@demofinans.bg']);
        $b = $this->http('POST', '/forgot-password', null, ['email' => 's6-nobody-' . Str::random(6) . '@example.test']);
        $this->check('P9-06 forgot-password answers the same for known and unknown e-mails', $a->getStatusCode() === $b->getStatusCode());

        $demoCommand = (string)@file_get_contents(app_path('Console/Commands/DatabackdDemo.php'));
        $this->check('P9-04 databackd:demo requires a strong non-default --password outside _local (source check)',
            str_contains($demoCommand, 'strlen($password) < 12') && str_contains($demoCommand, '$password === DatabackdDemoSeeder::PASSWORD'));
    }

    // ------------------------------------------------------------------ helpers

    private function check(string $label, bool $ok, string $detail = ''): void
    {
        if ($ok) {
            $this->passed++;
            $this->line("  <info>OK</info>    {$label}");

            return;
        }

        $this->failed++;
        $this->line("  <error>FAIL</error>  {$label}");

        if ($detail !== '' && ($this->option('show') || strlen($detail) < 300)) {
            $this->line('        ' . $detail);
        }
    }

    private function skip(string $label): void
    {
        $this->skipped++;
        $this->line("  <comment>SKIP</comment>  {$label}");
    }

    /** Searches PHP files below the given paths for a regex; returns "relative/path:line" hits (comments are not filtered). */
    private function grep(string $regex, array $paths): array
    {
        $hits = [];

        foreach ($paths as $path) {
            if (!file_exists($path)) {
                continue;
            }

            $files = is_file($path) ? [new \SplFileInfo($path)] : new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($path, \FilesystemIterator::SKIP_DOTS));

            foreach ($files as $file) {
                if ($file->getExtension() !== 'php' || $file->getFilename() === basename(__FILE__)) {
                    continue;
                }

                foreach (file($file->getPathname()) as $n => $line) {
                    $trim = ltrim($line);

                    if ($trim === '' || $trim[0] === '*' || str_starts_with($trim, '//') || str_starts_with($trim, '/*') || str_starts_with($trim, '#')) {
                        continue;
                    }

                    if (preg_match($regex, $line)) {
                        $hits[] = str_replace(base_path() . '/', '', $file->getPathname()) . ':' . ($n + 1);
                    }
                }
            }
        }

        return $hits;
    }

    /** Turns the CSRF check off (to reach the controllers) or back on (to test it). */
    private function csrf(bool $verify): void
    {
        $this->csrfOff = !$verify;
        $class = VerifyCsrfTokenPathOnly::class;

        if ($verify) {
            $this->laravel->bind($class, fn($app) => $app->build($class));
        } else {
            $this->laravel->bind($class, fn() => new class {
                public function handle($request, Closure $next)
                {
                    return $next($request);
                }
            });
        }
    }

    private function as(?string $role): void
    {
        $auth = $this->laravel['auth'];
        $auth->shouldUse('web');

        if ($role && $this->actors[$role]) {
            $auth->guard('web')->setUser($this->actors[$role]);
        } else {
            $auth->guard('web')->forgetUser();
        }

        $this->laravel['session']->driver()->flush();
        Cache::flush();
    }

    /** One in-process request as $role (null = guest). */
    private function http(string $method, string $uri, ?string $role, array $params = []): Response
    {
        $this->as($role);

        $isRead = in_array($method, ['GET', 'HEAD'], true);
        $request = Request::create(config('app.url') . $uri, $method, $isRead ? $params : $params, [], [], ['HTTP_ACCEPT' => 'application/json', 'HTTP_X_REQUESTED_WITH' => 'XMLHttpRequest', 'REMOTE_ADDR' => '10.77.0.1']);

        return $this->send($request);
    }

    private function send(Request $request): Response
    {
        $kernel = $this->laravel->make(HttpKernel::class);
        $response = $kernel->handle($request);
        $kernel->terminate($request, $response);

        return $response;
    }

}
