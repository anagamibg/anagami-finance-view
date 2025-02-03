<?php

namespace App\Console\Commands;

use App\Models\Company;
use Database\Seeders\Databackd\DatabackdDemoSeeder;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;

/**
 * databackd: (re)generate the demo company with 18 months of data. Idempotent.
 */
class DatabackdDemo extends Command
{

    protected $signature = 'databackd:demo {--force : Run even if DB_DATABASE does not end with _local} {--password= : Password for the demo accounts (default: the local test password)} {--only= : Run only this class from database/seeders/Databackd/Demo (file name or class name)}';

    protected $description = 'Create / refresh the Anagami Finance View demo company and its demo data (local databases only)';

    public function handle(): int
    {
        $database = (string)config('database.connections.' . config('database.default') . '.database');

        if (!str_ends_with($database, '_local') && !$this->option('force')) {
            $this->error("Refusing to run: database '$database' does not end with _local (use --force to override).");

            return self::FAILURE;
        }

        // Security: the default password is public (repo + docs). On a non-local database the accounts must get a
        // strong, non-default password chosen by the operator.
        if (!str_ends_with($database, '_local')) {
            $password = (string)$this->option('password');

            if (strlen($password) < 12 || $password === DatabackdDemoSeeder::PASSWORD) {
                $this->error('On a non-local database --password is required (min. 12 characters, different from the public default).');

                return self::FAILURE;
            }
        }

        if ($this->option('password')) {
            config(['databackd.demo_password' => $this->option('password')]);
        }

        // Stops notifications / emails while seeding
        config(['app.seeding' => true]);
        mt_srand(2026);

        $base = new DatabackdDemoSeeder();
        $base->setCommand($this);
        $base->run();
        /** @var Company $company */
        $company = $base->company;

        $only = $this->option('only');
        $files = File::glob(database_path('seeders/Databackd/Demo/*.php')) ?: [];
        sort($files);

        foreach ($files as $file) {
            $name = basename($file, '.php');
            // File "10WorkDemoSeeder.php" holds class "WorkDemoSeeder" (the numeric prefix only defines the order)
            $className = preg_replace('/^\d+/', '', $name);

            if ($only && $only !== $name && $only !== $className) {
                continue;
            }

            $class = 'Database\\Seeders\\Databackd\\Demo\\' . $className;
            $this->info("Seeding $name ...");
            mt_srand(2026);
            // numeric file prefixes are not PSR-4 compatible, so load the file explicitly
            require_once $file;
            $seeder = new $class();
            $seeder->setCommand($this);
            $seeder->run($company);
        }

        cache()->flush();
        config(['app.seeding' => false]);
        $this->info('Demo data ready for company #' . $company->id . ' (' . $company->company_name . ').');

        return self::SUCCESS;
    }

}
