<?php

// AFV (S8): the financial-analysis modules of the SaaS packages
return [
    'group_title' => 'Financial Analysis (Anagami Finance View)',
    'group_hint' => 'Choose which financial-analysis features the companies on this package can use.',
    'select_all' => 'Select all',
    'column' => 'Financial Analysis',
    'module_not_in_package' => 'The module ":module" is not included in your package. Contact the administrator to change the package.',
    'none' => 'No financial-analysis modules',
    'modules' => [
        'databackd_data' => [
            'name' => 'Data entry and import',
            'description' => 'Manual entry, bulk entry, import from Excel, CSV, Google Sheets and bank statements, accounts and dimensions.',
        ],
        'databackd_reports' => [
            'name' => 'Financial dashboard and reports',
            'description' => 'Financial dashboard, flexible reports, income statement, cash flow, counterparties, margins and annual statements.',
        ],
        'databackd_analytics' => [
            'name' => 'Ratios and consolidation',
            'description' => 'Standard and custom financial ratios, comparison of legal entities, consolidation and threshold alerts.',
        ],
        'databackd_planning' => [
            'name' => 'Budgets and forecasts',
            'description' => 'Budgets, budget versus actual and forecasts with scenarios.',
        ],
        'databackd_teams' => [
            'name' => 'Teams service',
            'description' => 'Ordering a team of specialists and monthly analysis requests.',
        ],
        'databackd_integrations' => [
            'name' => 'API and external sources',
            'description' => 'API tokens, REST endpoint and synchronised external sources.',
        ],
    ],
    // the package list (super admin)
    'list' => [
        'package' => 'Package',
        'price' => 'Price',
        'limits' => 'Limits',
        'modules' => 'Modules',
        'default' => 'Default',
        'trial' => 'Trial',
        'trial_days' => ':days days',
        'recommended' => 'Recommended',
        'private' => 'Private',
        'free' => 'Free',
        'per_month' => '/ month',
        'per_year' => '/ year',
        'lifetime' => 'one-off',
        'unlimited' => 'Unlimited',
        'storage' => 'File storage',
        'no_modules' => 'No modules selected',
        'all_modules' => 'All modules · :count',
        'of_modules' => ':count of :total modules',
        'without' => 'Without:',
        'show_included' => 'Included modules (:count)',
    ],
];
