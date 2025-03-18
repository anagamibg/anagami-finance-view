<?php

/*
 * AFV MVP configuration.
 *
 * `hidden` lists front-end elements that are hidden from the UI (routes and code stay intact).
 * Set a key to `true` to hide it, `false` to show it again. Read via databackd_hidden($group, $key).
 */
return [

    'hidden' => [

        // Sidebar menu items (resources/views/sections/menu.blade.php)
        'menu' => [
            'my_calendar' => true,
            'leads' => true,              // Leads + Deals
            'leaves' => true,
            'shifts' => true,
            'attendance' => true,
            'holidays' => true,
            'appreciation' => true,       // Appreciations / awards
            'proposals' => true,
            'estimates' => true,
            'subscriptions' => true,
            'orders' => true,
            'tickets' => true,
            'events' => true,
            'messages' => true,
            'gdpr' => true,
            'notices' => true,
            'knowledgebase' => true,
            'notes' => true,
            'report_tasks' => true,
            'report_weekly_timesheet' => true,
            'report_leaves' => true,
            'report_attendance' => true,
            'report_deals' => true,
        ],

        // Tabs of the advanced (admin) dashboard
        'dashboard_tabs' => [
            'project' => true,
            'client' => true,
            'hr' => true,
            'ticket' => true,
        ],

        // Widgets of the private (employee/client) dashboard
        'dashboard_widgets' => [
            'shift_schedule' => true,
            'appreciation' => true,
            'leave' => true,
            'work_from_home' => true,
            'follow_ups' => true,
            'lead' => true,
            'tickets' => true,
            'my_calendar' => true,
            'notices' => true,
        ],

        // Settings sidebar items (components/setting-sidebar.blade.php)
        'settings' => [
            'attendance_settings' => true,
            'leave_settings' => true,
            'message_settings' => true,
            'lead_settings' => true,
            'ticket_settings' => true,
            'gdpr_settings' => true,
            'google_calendar_settings' => true,
            'custom_link_settings' => true,
            'sign_up_setting' => true,
            'billing' => true,            // "Billing" (package, invoices, payment)
        ],

        // Whole pages that answer 404 for everyone (routes stay registered)
        'pages' => [
            'billing' => true,            // /account/settings/billing/* (HideBillingPages middleware)
        ],

        // Buttons in the top bar (sections/topbar.blade.php)
        'topbar' => [
            'upgrade_plan' => true,       // "Upgrade plan" / trial days left
        ],

        // "Create new" dropdown in the top bar (sections/topbar.blade.php)
        'quick_add' => [
            'ticket' => true,
        ],
    ],

    // AFV (Froiden) license verification of the purchase code on the login and front pages (isLegal()).
    // Off while databackd.com is in development and not public: no daily call to Froiden's server and no redirect
    // to /verify-purchase, the stored purchase code is left as it is. Set DATABACKD_LICENSE_CHECK=true once the
    // license key is entered (upgrade 6.0.19, see docs/APP-UPDATE-AND-CUSTOMIZATIONS.md 4.10).
    'license_check' => (bool) env('DATABACKD_LICENSE_CHECK', false),

    // Files of resources/views/databackd/menu (without .blade.php) that the client role also sees (S5: Teams service)
    'client_menu' => ['50-teams-requests', '51-teams-analysis'],

];
