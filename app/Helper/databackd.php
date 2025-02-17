<?php

// databackd: helpers for the MVP customization (see docs/APP-UPDATE-AND-CUSTOMIZATIONS.md, CUS_databackd_mvp)

// AFV (S8): package modules helpers (databackd_module() ...). Loaded here because this file is in composer's
// autoload files (also on production, where composer is not available) and routes may be cached.
require_once __DIR__ . '/databackd_modules.php';

if (!function_exists('databackd_hidden')) {

    /**
     * Whether a front-end element is hidden by config/databackd.php.
     * Hiding applies only to the company accounts (admin, employee, client); the super admin sees everything
     * (a super admin who "logs in as" a company is that company's admin, so hiding applies to them there).
     *
     * @param string $group One of: menu, dashboard_tabs, dashboard_widgets, settings, pages, topbar, quick_add
     * @param string $key   Element key inside the group
     */
    function databackd_hidden(string $group, string $key): bool
    {
        if (databackd_is_superadmin()) {
            return false;
        }

        return (bool)config('databackd.hidden.' . $group . '.' . $key, false);
    }

}

if (!function_exists('databackd_license_check')) {

    /**
     * AFV (upgrade 6.0.19): whether the AFV purchase code is verified with Froiden (isLegal()) on the
     * login and front pages. Off by default (config/databackd.php license_check) while the site is not public.
     */
    function databackd_license_check(): bool
    {
        return (bool)config('databackd.license_check', false);
    }

}

if (!function_exists('databackd_is_superadmin')) {

    /**
     * Whether the current user is the SaaS super admin (not a company account).
     * user() may be a stdClass (no company in the session yet) or null (guest).
     */
    function databackd_is_superadmin(): bool
    {
        $user = user();

        return $user && !empty($user->is_superadmin);
    }

}

if (!function_exists('databackd_profile_access')) {

    /**
     * AFV (audit 2026-10-30, G02): what the current user may open among the employee/client profile pages,
     * computed once per request (lists build a link per row and permission() queries the database every time).
     *
     * @return array{viewer: int|null, employees: bool, employee_menu_all: bool, clients: bool}
     */
    function databackd_profile_access(): array
    {
        static $cache = [];

        $user = user();
        // user() may be a stdClass without a company yet (see databackd_is_superadmin())
        $viewerId = ($user && empty($user->is_superadmin) && !empty($user->id) && method_exists($user, 'permission')) ? (int)$user->id : null;

        if (is_null($viewerId)) {
            return ['viewer' => null, 'employees' => false, 'employee_menu_all' => false, 'clients' => false];
        }

        if (!isset($cache[$viewerId])) {
            $isClient = in_array('client', user_roles());
            $modules = user_modules();

            $cache[$viewerId] = [
                'viewer' => $viewerId,
                // EmployeeController: employees module in the constructor, show() denies clients
                'employees' => !$isClient && in_array('employees', $modules),
                'employee_menu_all' => $user->permission('view_employee_menu') == 'all',
                // ClientController: clients module in the constructor, show() needs view_clients all/added/both
                'clients' => !$isClient && in_array('clients', $modules)
                    && in_array($user->permission('view_clients'), ['all', 'added', 'both']),
            ];
        }

        return $cache[$viewerId];
    }

}

if (!function_exists('databackd_employee_url')) {

    /**
     * URL of an employee's profile (employees.show), or null when the current user would get a 403 there:
     * no employees module, client role, or someone else's profile without view_employee_menu = all.
     */
    function databackd_employee_url($userId, array $query = []): ?string
    {
        $access = databackd_profile_access();

        if (is_null($userId) || !$access['employees']) {
            return null;
        }

        if (!$access['employee_menu_all'] && (int)$userId !== $access['viewer']) {
            return null;
        }

        return route('employees.show', array_merge([$userId], $query));
    }

}

if (!function_exists('databackd_client_url')) {

    /**
     * URL of a client's profile (clients.show), or null when the current user cannot open client profiles
     * (no clients module, client role, or view_clients none/owned).
     */
    function databackd_client_url($userId): ?string
    {
        return (!is_null($userId) && databackd_profile_access()['clients']) ? route('clients.show', $userId) : null;
    }

}
