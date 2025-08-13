<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/**
 * AFV (S8): the financial-analysis features become modules of the SaaS packages.
 *
 *  1. `modules`: one row per AFV module (idempotent).
 *  2. `packages.module_in_package`: every EXISTING package gets all the new modules, so nothing disappears on production.
 *  3. `module_settings`: every EXISTING company gets rows for admin/employee (and databackd_teams for client),
 *     allowed according to its package (they all are, see 2).
 *  4. The per-user `user_modules_<id>` cache is cleared.
 *
 * Uses the query builder only (no models/observers/scopes) so it is safe on any database and re-runnable.
 * down() removes everything this migration added (including the modules from the packages made after it).
 *
 * The names and roles are hard-coded here on purpose (the catalog in app/Helper/databackd_modules.php must match).
 */
return new class extends Migration
{
    private const MODULES = [
        'databackd_data' => ['types' => ['admin', 'employee'], 'description' => 'Data entry and import'],
        'databackd_reports' => ['types' => ['admin', 'employee'], 'description' => 'Financial dashboard and reports'],
        'databackd_analytics' => ['types' => ['admin', 'employee'], 'description' => 'Ratios and consolidation'],
        'databackd_planning' => ['types' => ['admin', 'employee'], 'description' => 'Budgets and forecasts'],
        'databackd_teams' => ['types' => ['admin', 'employee', 'client'], 'description' => 'Teams service'],
        'databackd_integrations' => ['types' => ['admin', 'employee'], 'description' => 'API and external sources'],
    ];

    public function up(): void
    {
        $now = now();

        // 1. modules
        foreach (self::MODULES as $name => $module) {
            if (!DB::table('modules')->where('module_name', $name)->exists()) {
                DB::table('modules')->insert([
                    'module_name' => $name,
                    'description' => $module['description'],
                    'is_superadmin' => 0,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);
            }
        }

        $moduleIds = DB::table('modules')->whereIn('module_name', array_keys(self::MODULES))->pluck('id', 'module_name')->all();

        // 2. packages: module_in_package is a JSON list or an {"<module id>": "<name>"} object (the package form posts it keyed by id)
        foreach (DB::table('packages')->get(['id', 'module_in_package']) as $package) {
            $current = json_decode((string)$package->module_in_package, true);
            $current = is_array($current) ? $current : [];
            $isList = array_is_list($current);

            foreach ($moduleIds as $name => $id) {
                if (in_array($name, $current, true)) {
                    continue;
                }

                if ($isList) {
                    $current[] = $name;
                }
                else {
                    $current[$id] = $name;
                }
            }

            DB::table('packages')->where('id', $package->id)->update(['module_in_package' => json_encode($current)]);
        }

        // 3. module_settings for the existing companies (their package decides is_allowed)
        $packageModules = [];

        foreach (DB::table('packages')->get(['id', 'module_in_package']) as $package) {
            $packageModules[$package->id] = array_values((array)json_decode((string)$package->module_in_package, true));
        }

        foreach (DB::table('companies')->get(['id', 'package_id']) as $company) {
            $allowedByPackage = $packageModules[$company->package_id] ?? array_keys(self::MODULES);

            foreach (self::MODULES as $name => $module) {
                foreach ($module['types'] as $type) {
                    $exists = DB::table('module_settings')
                        ->where('company_id', $company->id)->where('module_name', $name)->where('type', $type)->exists();

                    if ($exists) {
                        continue;
                    }

                    $allowed = in_array($name, $allowedByPackage, true);

                    DB::table('module_settings')->insert([
                        'company_id' => $company->id,
                        'module_name' => $name,
                        'type' => $type,
                        'status' => $allowed ? 'active' : 'deactive',
                        'is_allowed' => $allowed ? 1 : 0,
                        'created_at' => $now,
                        'updated_at' => $now,
                    ]);
                }
            }
        }

        $this->clearUserModulesCache();
    }

    public function down(): void
    {
        $names = array_keys(self::MODULES);

        DB::table('module_settings')->whereIn('module_name', $names)->delete();

        foreach (DB::table('packages')->get(['id', 'module_in_package']) as $package) {
            $current = json_decode((string)$package->module_in_package, true);

            if (!is_array($current)) {
                continue;
            }

            $kept = array_filter($current, fn($name) => !in_array($name, $names, true));
            $kept = array_is_list($current) ? array_values($kept) : $kept;

            DB::table('packages')->where('id', $package->id)->update(['module_in_package' => json_encode($kept)]);
        }

        DB::table('modules')->whereIn('module_name', $names)->delete();

        $this->clearUserModulesCache();
    }

    private function clearUserModulesCache(): void
    {
        foreach (DB::table('users')->pluck('id') as $userId) {
            Cache::forget('user_modules_' . $userId);
        }
    }
};
