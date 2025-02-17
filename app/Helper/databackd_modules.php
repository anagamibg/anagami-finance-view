<?php

// AFV (S8): the AFV financial-analysis features as modules of the SaaS packages
// (see docs/mvp/features/08-packages-gaps.md).
//
// Loaded with require_once from routes/databackd/00-modules.php AND from the end of app/Helper/databackd.php
// (which is in composer's autoload files), so the functions exist even when the routes are cached
// (php artisan route:cache does not execute the route files) - without touching composer.json.

if (!function_exists('databackd_modules_catalog')) {

    /**
     * The AFV modules: name => the roles (module_settings.type) that get a module_settings row for it.
     * Keep in sync with the migration 2026_10_28_090000_add_databackd_modules (which hard-codes the same names).
     *
     * @return array<string, string[]>
     */
    function databackd_modules_catalog(): array
    {
        return [
            'databackd_data' => ['admin', 'employee'],          // data entry and import
            'databackd_reports' => ['admin', 'employee'],       // financial dashboard and reports
            'databackd_analytics' => ['admin', 'employee'],     // ratios and consolidation
            'databackd_planning' => ['admin', 'employee'],      // budgets and forecasts
            'databackd_teams' => ['admin', 'employee', 'client'], // the "Teams" service
            'databackd_integrations' => ['admin', 'employee'],  // API and external sources
        ];
    }

}

if (!function_exists('databackd_modules_for_type')) {

    /**
     * Names of the AFV modules for a role type (admin|employee|client).
     *
     * @return string[]
     */
    function databackd_modules_for_type(string $type): array
    {
        $names = [];

        foreach (databackd_modules_catalog() as $name => $types) {
            if (in_array($type, $types, true)) {
                $names[] = $name;
            }
        }

        return $names;
    }

}

if (!function_exists('databackd_module')) {

    /**
     * Whether the current user's package (and the company module settings) allow a AFV module.
     * Same source as the core modules: user_modules() (module_settings of the company, by role).
     */
    function databackd_module(string $module): bool
    {
        return in_array($module, user_modules(), true);
    }

}

if (!function_exists('databackd_any_module')) {

    /**
     * True when at least one of the AFV modules is allowed (the "Financial analysis" menu section).
     */
    function databackd_any_module(): bool
    {
        return count(array_intersect(array_keys(databackd_modules_catalog()), user_modules())) > 0;
    }

}

if (!function_exists('databackd_company_module')) {

    /**
     * Whether a company has a AFV module through its package (for code that runs without a logged-in user:
     * the API token endpoint, artisan commands). Looks at the admin module_settings row of the company.
     */
    function databackd_company_module(int $companyId, string $module): bool
    {
        return \Illuminate\Support\Facades\DB::table('module_settings')
            ->where('company_id', $companyId)
            ->where('module_name', $module)
            ->where('type', 'admin')
            ->where('is_allowed', 1)
            ->where('status', 'active')
            ->exists();
    }

}

if (!function_exists('databackd_module_label')) {

    /**
     * Display name of a package module: the databackd_* modules come from lang/databackd-packages.php,
     * the core modules from lang/modules.php.
     */
    function databackd_module_label(string $module): string
    {
        return str_starts_with($module, 'databackd_')
            ? __('databackd-packages.modules.' . $module . '.name')
            : __('modules.module.' . $module);
    }

}

if (!function_exists('databackd_module_description')) {

    /**
     * Short description of a databackd_* package module (empty for the core modules).
     */
    function databackd_module_description(string $module): string
    {
        return str_starts_with($module, 'databackd_')
            ? __('databackd-packages.modules.' . $module . '.description')
            : '';
    }

}

if (!function_exists('databackd_menu_module')) {

    /**
     * The module that a resources/views/databackd/menu/<name>.blade.php item belongs to
     * (null = not gated). Used to hide the whole "Financial analysis" section when no module is allowed.
     */
    function databackd_menu_module(string $menuName): ?string
    {
        $map = [
            '05-reports-dashboard' => 'databackd_reports',
            '10-data-entries' => 'databackd_data',
            '11-data-bulk' => 'databackd_data',
            '12-data-import' => 'databackd_data',
            '13-data-sources' => 'databackd_integrations',
            '14-data-batches' => 'databackd_data',
            '15-data-integrations' => 'databackd_integrations',
            '16-data-accounts' => 'databackd_data',
            '17-data-dimensions' => 'databackd_data',
            '20-reports-builder' => 'databackd_reports',
            '21-reports-pnl' => 'databackd_reports',
            '22-reports-cashflow' => 'databackd_reports',
            '23-reports-counterparties' => 'databackd_reports',
            '24-reports-saved' => 'databackd_reports',
            '30-analytics-ratios' => 'databackd_analytics',
            '31-analytics-comparison' => 'databackd_analytics',
            '32-analytics-builder' => 'databackd_analytics',
            '33-analytics-consolidation' => 'databackd_analytics',
            '34-analytics-consolidation-rules' => 'databackd_analytics',
            '40-planning-budgets' => 'databackd_planning',
            '41-planning-variance' => 'databackd_planning',
            '42-planning-forecasts' => 'databackd_planning',
            '50-teams-requests' => 'databackd_teams',
            '51-teams-analysis' => 'databackd_teams',
            '52-teams-specialists' => 'databackd_teams',
            // gaps (S8): the number puts the item in the right sidebar group (see the subgroups in sections/menu.blade.php)
            '06-gaps-onboarding' => 'databackd_data',
            '18-gaps-bank-import' => 'databackd_data',
            '19-gaps-fx-rates' => 'databackd_data',
            '25-gaps-margins' => 'databackd_reports',
            '26-gaps-statutory' => 'databackd_reports',
            '35-gaps-alerts' => 'databackd_analytics',
        ];

        return $map[$menuName] ?? null;
    }

}
