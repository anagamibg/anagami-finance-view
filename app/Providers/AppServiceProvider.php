<?php

namespace App\Providers;

use App\Models\Company;
use Barryvdh\LaravelIdeHelper\IdeHelperServiceProvider;
use Carbon\CarbonInterval;
use Illuminate\Database\Eloquent\Model;
use Craftsys\Msg91\Client;
use Craftsys\Notifications\Channels\Msg91Channel;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\View;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\ServiceProvider;
use Laravel\Cashier\Cashier;
use Laravel\Sanctum\Sanctum;

class AppServiceProvider extends ServiceProvider
{

    /**
     * Register any application services.
     *
     * @return void
     */

    public function register()
    {
//        Cashier::ignoreMigrations();
//        Sanctum::ignoreMigrations();

        if (config('app.redirect_https')) {
            $this->app['request']->server->set('HTTPS', true);
        }
    }

    public function boot()
    {
        // Override Froiden RestAPI handler (fixes undefined $response on API 500s).
        $this->app->singleton(
            \Illuminate\Contracts\Debug\ExceptionHandler::class,
            \App\Exceptions\ApiExceptionHandler::class
        );

        Cashier::useCustomerModel(Company::class);

        $this->refuseInsecureAppKey();

        if (config('app.redirect_https')) {
            URL::forceScheme('https');
        }

        Schema::defaultStringLength(191);

        DB::prohibitDestructiveCommands(app()->isProduction());

        Notification::extend('msg91', function ($app) {
            return new Msg91Channel($app->make(Client::class));
        });

        if (app()->environment('development')) {
            $this->app->register(IdeHelperServiceProvider::class);
        }

        CarbonInterval::macro('formatHuman', function ($totalMinutes, $seconds = false): string {

            if ($seconds) {
                return static::seconds($totalMinutes)->cascade()->forHumans(['short' => true, 'options' => 0]);
                /** @phpstan-ignore-line */
            }

            return static::minutes($totalMinutes)->cascade()->forHumans(['short' => true, 'options' => 0]);
            /** @phpstan-ignore-line */
        });

        // databackd: every new company gets the chart of accounts
        Company::created(function (Company $company) {
            \App\Services\Databackd\ChartOfAccounts::ensureFor($company->id);
        });

        // databackd: "Financial analysis" menu section = every file in resources/views/databackd/menu (alphabetical)
        View::composer('sections.menu', function ($view) {
            $dir = resource_path('views/databackd/menu');
            $views = [];

            if (File::isDirectory($dir)) {
                foreach (File::glob($dir . '/*.blade.php') as $file) {
                    $views[] = 'databackd.menu.' . basename($file, '.blade.php');
                }

                sort($views);
            }

            // AFV (S5): clients only see the items listed in config('databackd.client_menu')
            if (in_array('client', user_roles())) {
                $allowed = array_map(fn($name) => 'databackd.menu.' . $name, config('databackd.client_menu', []));
                $views = array_values(array_intersect($views, $allowed));
            }

            // AFV (S8): only the items whose package module is allowed - the whole section disappears when none is
            $views = array_values(array_filter($views, function ($menuView) {
                $module = databackd_menu_module(substr($menuView, strlen('databackd.menu.')));

                return $module === null || databackd_module($module);
            }));

            $view->with('databackdMenuViews', $views);
        });

            //    Model::preventLazyLoading(app()->environment('development'));

    }

    /**
     * Block boot when APP_KEY is empty or still the publicly shipped sample value.
     */
    protected function refuseInsecureAppKey(): void
    {
        if ($this->app->runningInConsole()) {
            return;
        }

        $appKey = (string) config('app.key');
        $shippedSample = 'base64:wID/CO/WkfE3yoWpQOoy15de8FtOpvC3dM/z5FuHiEU=';

        if ($appKey === '' || $appKey === $shippedSample) {
            abort(500, 'APP_KEY is missing or still set to the shared sample value. Run: php artisan key:generate');
        }
    }

}
